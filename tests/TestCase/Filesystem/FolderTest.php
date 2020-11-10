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

use InvalidArgumentException;
use FileSync\Filesystem\Folder;
use PHPUnit\Framework\TestCase;

class FolderTest extends TestCase
{
    public function testListException()
    {
        $folder = new Folder();
        $this->expectException(InvalidArgumentException::class);

        $folder->list('/foo/1234');
    }

    public function testSyncignore()
    {
        $tmpFolder = sys_get_temp_dir() . '/' . uniqid();
        mkdir($tmpFolder . '/sub', 0775, true);
        file_put_contents($tmpFolder . '/README.md', '010101');
        file_put_contents($tmpFolder . '/config.json', json_encode(['foo' => 'bar']));
        file_put_contents($tmpFolder . '/sub/foo.txt', 'bar');

        $folder = new Folder();

        $this->assertCount(3, $folder->list($tmpFolder));

        /**
         * Syncignore wont be synced across but still should never sync a directory containing
         * important stuff e.g. .env to an untrusted party
         */
       
        file_put_contents($tmpFolder . '/.syncignore', implode("\n", ['*.json','sub/']));
        $this->assertCount(1, $folder->list($tmpFolder));
    }
}
