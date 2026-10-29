<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MySqlTransactionCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_mysql_transaction_rollback_restores_the_original_row(): void
    {
        $user = User::factory()->create();

        DB::beginTransaction();
        DB::table('users')->where('id', $user->id)->update(['name' => 'Transaction Probe']);
        DB::rollBack();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => $user->name,
        ]);
    }
}
