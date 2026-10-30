<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('users')->whereNotIn('status', ['ACTIVE', 'INACTIVE'])->exists()) {
            throw new RuntimeException('Cannot add users_status_allowed: existing users contain an unsupported status. Remediate those rows explicitly and retry.');
        }

        $invalidUsername = DB::selectOne(<<<'SQL'
            SELECT 1
            FROM users
            WHERE NOT (
                (username IS NULL AND username_canonical IS NULL)
                OR (
                    username IS NOT NULL
                    AND username_canonical IS NOT NULL
                    AND BINARY username_canonical = BINARY LOWER(username)
                    AND CHAR_LENGTH(username) BETWEEN 1 AND 64
                    AND REGEXP_LIKE(LEFT(username, 1), '^[A-Za-z0-9]$', 'c')
                    AND NOT REGEXP_LIKE(username, '[^A-Za-z0-9._-]', 'c')
                )
            )
            LIMIT 1
            SQL);

        if ($invalidUsername !== null) {
            throw new RuntimeException('Cannot add users_username_identity_consistent: existing usernames are malformed or disagree with their canonical values. Remediate those rows explicitly and retry.');
        }

        DB::statement(<<<'SQL'
            ALTER TABLE users
                ADD CONSTRAINT users_status_allowed
                    CHECK (status IN ('ACTIVE', 'INACTIVE')),
                ADD CONSTRAINT users_username_identity_consistent
                    CHECK (
                        (username IS NULL AND username_canonical IS NULL)
                        OR (
                            username IS NOT NULL
                            AND username_canonical IS NOT NULL
                            AND BINARY username_canonical = BINARY LOWER(username)
                            AND CHAR_LENGTH(username) BETWEEN 1 AND 64
                            AND REGEXP_LIKE(LEFT(username, 1), '^[A-Za-z0-9]$', 'c')
                            AND NOT REGEXP_LIKE(username, '[^A-Za-z0-9._-]', 'c')
                        )
                    )
            SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE users
                DROP CHECK users_username_identity_consistent,
                DROP CHECK users_status_allowed
            SQL);
    }
};
