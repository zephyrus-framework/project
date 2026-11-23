<?php

declare(strict_types=1);

/**
 * Creates .env from .env.example when it is missing and fills an empty ENCRYPTION_KEY.
 * A key already set is never replaced.
 *
 * Composer runs it after create-project. Run it again on a fresh clone: php bin/create-env.php
 */

use Zephyrus\Security\Cryptography;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};

$envFile = $root . '/.env';
if (!is_file($envFile)) {
    if (!copy($root . '/.env.example', $envFile)) {
        $fail('Could not copy .env.example to .env.');
    }
    echo "Created .env from .env.example.\n";
}

$env = file_get_contents($envFile);
if ($env === false) {
    $fail('Could not read .env.');
}

if (preg_match('/^ENCRYPTION_KEY=/m', $env) !== 1) {
    fwrite(STDERR, "Warning: .env has no ENCRYPTION_KEY line. Add \"ENCRYPTION_KEY=\" and run php bin/create-env.php again.\n");
    exit(0);
}

$emptyKey = '/^ENCRYPTION_KEY=\h*(?:""|\'\')?\h*(?=\r?$)/m';
if (preg_match($emptyKey, $env) === 1) {
    $key = Cryptography::encodeKey(Cryptography::generateEncryptionKey());
    if (file_put_contents($envFile, preg_replace($emptyKey, 'ENCRYPTION_KEY=' . $key, $env, 1)) === false) {
        $fail('Could not write ENCRYPTION_KEY to .env.');
    }
    echo "Generated ENCRYPTION_KEY in .env.\n";
}
