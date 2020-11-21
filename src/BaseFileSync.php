<?php
/**
 * FileSync
 * Copyright 2020 Jamiel Sharief.
 *
 * Licensed under The MIT License
 * The above copyright notice and this permission notice shall be included in all copies or substantial
 * portions of the Software.
 *
 * @copyright   Copyright (c) Jamiel Sharief
 * @license     https://opensource.org/licenses/mit-license.php MIT License
 */
declare(strict_types = 1);
namespace FileSync;

use FileSync\Exception\FileSyncException;

class BaseFileSync
{

    /**
     * @var string
     */
    protected $keychainPath;

    /**

     * @param string $path
     * @return void
     */
    protected function setKeychainPath(string $path): void
    {
        if (! is_dir($path)) {
            throw new FileSyncException('Keychain path does not exist');
        }
        $this->keychainPath = rtrim($path, '/');
    }

    /**
     * Loads a public key from the key file
     *
     * @param string $userId
     * @return string|null
     */
    protected function loadPublicKey(string $userId): ? string
    {
        return $this->loadKey($this->publicKeyPath($userId));
    }
    /**
     * Loads a Private key from the key file
     *
     * @param string $userId
     * @return string|null
     */
    protected function loadPrivateKey(string $userId): ? string
    {
        return $this->loadKey($this->privateKeyPath($userId));
    }

    /**
     * Loads a Key from the file
     *
     * @param string $path
     * @return string
     */
    protected function loadKey(string $path): string
    {
        if (file_exists($path)) {
            return file_get_contents($path);
        }

        throw new FileSyncException('Key not found');
    }

    /**
     * Checks if there is a Key for a userID
     *
     * @param string $userId
     * @return bool
     */
    protected function hasPublicKey(string $userId): bool
    {
        return file_exists($this->publicKeyPath($userId));
    }

    /**
     * Checks if there is a Key for a userID
     *
     * @param string $userId
     * @return bool
     */
    protected function hasPrivateKey(string $userId): bool
    {
        return file_exists($this->privateKeyPath($userId));
    }

    /**
     * @param string $userId
     * @return string
     */
    private function privateKeyPath(string $userId): string
    {
        return sprintf('%s/%s.privateKey',
            $this->keychainPath,
            $userId,
        );
    }

    /**
     * @param string $userId
     * @return string
     */
    private function publicKeyPath(string $userId): string
    {
        return sprintf('%s/%s.publicKey',
            $this->keychainPath,
            $userId,
        );
    }

    /**
     * @param string $userId
     * @return void
     */
    protected function validateUserId(string $userId): void
    {
        if (! preg_match('/^[a-z0-9_.+-@]+$/i', $userId)) {
            throw new FileSyncException('Invalid user id ' . $userId);
        }
    }
}
