<?php

declare(strict_types=1);

/**
 * Creates .env from .env.example when it is missing and fills an empty ENCRYPTION_KEY.
 *
 * Composer runs it after create-project. Run it again on a fresh clone: php bin/create-env.php
 */

use Zephyrus\Security\Cryptography;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$envFile = $root . '/.env';
if (!is_file($envFile)) {
    if (!copy($root . '/.env.example', $envFile)) {
        fwrite(STDERR, "Could not copy .env.example to .env.\n");
        exit(1);
    }
    echo "Created .env from .env.example.\n";
}

$emptyKey = '/^ENCRYPTION_KEY=[ \t]*$/m';
$env = (string) file_get_contents($envFile);
if (preg_match($emptyKey, $env) === 1) {
    $key = Cryptography::encodeKey(Cryptography::generateEncryptionKey());
    file_put_contents($envFile, preg_replace($emptyKey, 'ENCRYPTION_KEY=' . $key, $env, 1));
    echo "Generated ENCRYPTION_KEY in .env.\n";
}
