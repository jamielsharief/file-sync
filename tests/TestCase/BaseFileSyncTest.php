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
namespace FileSync\Test\TestCase;

use FileSync\BaseFileSync;
use PHPUnit\Framework\TestCase;
use FileSync\Exception\FileSyncException;

class FileSync extends BaseFileSync
{
    public function __construct()
    {
        $this->setKeychainPath(dirname(__DIR__, 1) . '/Fixture/keys');
    }
    public function call(string $method, ...$args)
    {
        call_user_func([$this,$method], ...$args);
    }
}

class BaseFileSyncTest extends TestCase
{
    public function testKeyNotFound()
    {
        $fileSync = new FileSync();
      
        $this->expectException(FileSyncException::class);
        $fileSync->call('loadPrivateKey', 'foo');
    }

    public function testValidateKeyFailure()
    {
        $fileSync = new FileSync();
        $this->expectException(FileSyncException::class);
        $fileSync->call('validateUserId', 'not a / valid user id');
    }
}
