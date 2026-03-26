<?php
// includes/encryption_key.php
function get_file_encryption_key(): string
{
    $keyPath = __DIR__ . '/../config/encryption.key';
    if (!file_exists($keyPath)) {
        throw new RuntimeException('Encryption key file not found.');
    }

    $key = trim(file_get_contents($keyPath));
    if (strlen($key) !== 32) {
        throw new RuntimeException('Encryption key must be 32 bytes for AES-256.');
    }

    return $key;
}
