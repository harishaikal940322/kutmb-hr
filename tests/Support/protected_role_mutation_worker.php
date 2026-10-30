<?php

use App\Services\Identity\ProtectedRoleMutationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $actorId, $targetId, $roleCode, $operation, $barrier, $result, $entered] = $argv;
while (! is_file($barrier)) {
    usleep(10000);
}

$connectionId = null;
try {
    $connectionId = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
    app(ProtectedRoleMutationService::class)->execute(
        (int) $actorId,
        'user:'.$targetId,
        $roleCode,
        'synthetic.concurrent.'.$operation,
        ['source' => 'concurrency_test', 'changed_fields' => [$operation === 'revoke' ? 'role' : 'status']],
        static function () use ($targetId, $roleCode, $operation, $entered): void {
            touch($entered);
            usleep(2000000);
            if ($operation === 'revoke') {
                $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
                DB::table('user_roles')->where('user_id', $targetId)->where('role_id', $roleId)->delete();
            } else {
                DB::table('users')->where('id', $targetId)->update(['status' => 'INACTIVE']);
            }
        },
    );
    file_put_contents($result, json_encode(['result' => 'success', 'connection_id' => $connectionId], JSON_THROW_ON_ERROR));
} catch (Throwable $exception) {
    file_put_contents($result, json_encode(['result' => 'failed', 'message' => $exception->getMessage(), 'connection_id' => $connectionId], JSON_THROW_ON_ERROR));
}
