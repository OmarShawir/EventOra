<?php

namespace App;

/**
 * Minimal .env file loader.
 *
 * vlucas/phpdotenv (the "standard" choice, and what's listed in
 * composer.json for when the team runs `composer install` with full
 * internet access) pulls in several small dependencies of its own
 * (phpoption, result-type, symfony polyfills). Since this dev environment
 * can only reach GitHub directly and not packagist.org, this tiny loader
 * covers the same job — read KEY=VALUE lines from .env into $_ENV/getenv()
 * — without chasing that whole dependency tree. Swap back to
 * Dotenv::createImmutable() if/when the team runs a real `composer
 * install`; the public/index.php call site is a one-line change either way.
 */
class Env
{
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Strip matching surrounding quotes, e.g. KEY="some value"
            if (strlen($value) >= 2 &&
                (($value[0] === '"' && substr($value, -1) === '"') ||
                 ($value[0] === "'" && substr($value, -1) === "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            if (getenv($key) === false) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}
