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

use FileSync\Client;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use FileSync\Exception\FileSyncException;

class ClientTest extends TestCase
{
    protected static $process;

    public static function setUpBeforeClass(): void
    {
        $tests = dirname(__DIR__);
      
        self::$process = new Process(['php','-S','localhost:3000','-t', $tests]);
        self::$process->start();
        sleep(3); // not working on travis, will increase delay
    }
 
    public static function tearDownAfterClass(): void
    {
        self::$process->stop();
    }

    protected function setUp(): void
    {
        $this->keychainPath = dirname(__DIR__, 1) . '/Fixture/keys';
        $this->sourcePath = dirname(__DIR__, 1) . '/Fixture/data';
        $this->destPath = sys_get_temp_dir() . '/' . uniqid();
        mkdir($this->destPath, 0775);
    }

    public function testInvalidKeychainPath()
    {
        $this->expectException(FileSyncException::class);
        new Client('/etc/password');
    }

    // TODO: how should errors be handle here? HTTP library already has stuff to handle this

    public function testInvalidServer()
    {
        $client = new Client($this->keychainPath);
        $this->expectException(FileSyncException::class);
        $client->dispatch('http://localhost:1024/test-server.php', 'tony@stark.io', $this->destPath);
    }

    public function testInvalidUserId()
    {
        $client = new Client($this->keychainPath);
        $this->expectException(FileSyncException::class);
        $client->dispatch('http://localhost:3000/test-server.php', 'darth vader', $this->destPath);
    }

    /**
     * We have private key but server does not have public key
     */
    public function testAuthorizationFailure()
    {
        $client = new Client($this->keychainPath);
        $this->expectException(FileSyncException::class);
        $client->dispatch('http://localhost:3000/test-server.php', 'demo2@stark.io', $this->destPath);
    }

    public function testDispatch()
    {
        $client = new Client($this->keychainPath);
        $client->dispatch('http://localhost:3000/test-server.php', 'demo@example.com', $this->destPath);
        $this->assertFileIsReadable($this->destPath . '/README.md');
        $this->assertFileIsReadable($this->destPath . '/folder/.gitignore');
    }

    /**
     * @internal testing on the mac helped me found a bug due to the symbolic links and temp folder
     */
    public function testDispatchWithDelete()
    {
        $pathToDelete = $this->destPath . '/foo.txt';
        file_put_contents($pathToDelete, 'foo');

        $this->assertFileIsReadable($pathToDelete);

        $client = new Client($this->keychainPath);
        $client->dispatch('http://localhost:3000/test-server.php', 'demo@example.com', $this->destPath, [
            'delete' => true
        ]);
      
        $this->assertFileIsReadable($this->destPath . '/README.md');
        $this->assertFileIsReadable($this->destPath . '/folder/.gitignore');
        $this->assertFileDoesNotExist($pathToDelete);
    }

    public function testInvalidFileSyncServer()
    {
        $client = new Client($this->keychainPath);
        $this->expectException(FileSyncException::class);
        $client->dispatch('https://www.example.com', 'tony@stark.io', $this->destPath);
    }
}
