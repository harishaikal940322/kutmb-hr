<?php

namespace Tests\Feature\Identity;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProtectedRoleMutationConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_simultaneous_role_revocations_serialize_on_independent_mysql_connections(): void
    {
        [$first, $second, $role] = $this->twoSyntheticAdministrators();
        $results = $this->runWorkers([
            [$first->id, $first->id, $role->code, 'revoke'],
            [$second->id, $second->id, $role->code, 'revoke'],
        ]);

        $this->assertInvariantAndResults($results);
        $this->assertCount(2, array_unique(array_column($results, 'connection_id')));
    }

    public function test_concurrent_deactivation_and_role_revocation_preserve_the_invariant(): void
    {
        [$first, $second, $role] = $this->twoSyntheticAdministrators();
        $results = $this->runWorkers([
            [$first->id, $first->id, $role->code, 'deactivate'],
            [$second->id, $second->id, $role->code, 'revoke'],
        ]);

        $this->assertInvariantAndResults($results);
        $this->assertCount(2, array_unique(array_column($results, 'connection_id')));
    }

    /** @return array{User, User, Role} */
    private function twoSyntheticAdministrators(): array
    {
        $role = Role::create(['code' => 'SYNTHETIC_CONCURRENT_PROTECTED', 'name' => 'Synthetic concurrent protected']);
        $first = User::factory()->create(['username' => 'concurrent.admin1']);
        $second = User::factory()->create(['username' => 'concurrent.admin2']);
        $first->roles()->attach($role);
        $second->roles()->attach($role);

        return [$first, $second, $role];
    }

    /** @param list<array{int, int, string, string}> $workers
     * @return list<array{result: string, connection_id?: int, message?: string}>
     */
    private function runWorkers(array $workers): array
    {
        $directory = sys_get_temp_dir().'/kutmb-last-admin-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $barrier = $directory.'/go';
        $processes = [];

        try {
            foreach ($workers as $index => [$actorId, $targetId, $roleCode, $operation]) {
                $result = $directory.'/result-'.$index;
                $entered = $directory.'/entered-'.$index;
                $command = [PHP_BINARY, base_path('tests/Support/protected_role_mutation_worker.php'), (string) $actorId, (string) $targetId, $roleCode, $operation, $barrier, $result, $entered];
                $pipes = [];
                $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                $this->assertIsResource($process);
                fclose($pipes[0]);
                $processes[] = [$process, $pipes, $result];

                if ($index === 0) {
                    touch($barrier);
                    $deadline = microtime(true) + 10;
                    while (! is_file($entered) && microtime(true) < $deadline) {
                        usleep(10000);
                    }
                    $this->assertFileExists($entered, 'First worker did not enter its protected transaction.');
                }
            }

            $results = [];
            foreach ($processes as [$process, $pipes, $result]) {
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $exit = proc_close($process);
                $this->assertSame(0, $exit, 'Worker failed: '.$stdout.$stderr);
                $this->assertFileExists($result, 'Worker produced no result. '.$stderr);
                $decoded = json_decode(file_get_contents($result), true, flags: JSON_THROW_ON_ERROR);
                $results[] = $decoded;
            }

            return $results;
        } finally {
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    /** @param list<array{result: string, connection_id?: int, message?: string}> $results */
    private function assertInvariantAndResults(array $results): void
    {
        $this->assertSame(1, count(array_filter($results, fn (array $result): bool => $result['result'] === 'success')));
        $this->assertSame(1, count(array_filter($results, fn (array $result): bool => $result['result'] === 'failed')));
        $this->assertSame(1, DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->join('users', 'users.id', '=', 'user_roles.user_id')
            ->where('roles.code', 'SYNTHETIC_CONCURRENT_PROTECTED')
            ->where('users.status', 'ACTIVE')
            ->whereNotNull('users.username')
            ->count());
        $this->assertDatabaseCount('audit_events', 1);
    }
}
