<?php

namespace App\Support\Identity;

use InvalidArgumentException;

final class Username
{
    public static function canonicalize(string $username): string
    {
        if (strlen($username) > 64 || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,63}\z/D', $username) !== 1) {
            throw new InvalidArgumentException('Username must be 1–64 ASCII letters, digits, dots, underscores or hyphens, and start with a letter or digit.');
        }

        return strtolower($username);
    }
}
