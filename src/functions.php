<?php

declare(strict_types=1);

use PHPdot\Env\Env;

if (!function_exists('env')) {
    /**
     * Get a typed environment variable.
     *
     * Uses the full phpdot/env pipeline:
     * Parser (Lexer + Resolver) → EnvSchema (validation + type casting)
     *
     * Returns $default if the key is not in the schema or Env is not initialized.
     *
     * @param string $key The variable name
     * @param mixed $default Returned if key not found
     */
    function env(string $key, mixed $default = null): mixed
    {
        return Env::env($key, $default);
    }
}
