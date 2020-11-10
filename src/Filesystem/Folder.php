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
namespace FileSync\Filesystem;

use InvalidArgumentException;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class Folder
{
    /**
     * @var array
     */
    protected $ignorePatterns = [];

    /**
     * Creates a list of files and directories
     *
     * @param string $directory
     * @return array
     */
    public function list(string $directory): array
    {
        $directory = realpath(rtrim($directory, '/'));

        if (! $directory) {
            throw new InvalidArgumentException('The path does not exist');
        }

        if (file_exists($directory . '/.syncignore')) {
            $this->loadSyncIgnore($directory);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $startFrom = strlen($directory) + 1;

        $out = [];
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                continue;
            }
            $path = $item->getRealPath();
            
            $relativePath = substr($path, $startFrom);

            if ($this->ignorePath($relativePath)) {
                continue;
            }
         
            $out[] = [
                'path' => $relativePath,
                'size' => $item->getSize(),
                'modified' => $item->getMTime(),
                'permissions' => substr(sprintf('%o', fileperms($path)), -4),
                'checksum' => hash_file('crc32', $path)
            ];
        }

        return $out;
    }

    /**
     * Checks a path against .syncignore
     *
     * @param string $path
     * @return boolean
     */
    private function ignorePath(string $path): bool
    {
        foreach ($this->ignorePatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Processes the .syncignore file
     *
     * e.g.
     *
     *      *.json
     *      tests/
     *
     * @param string $directory
     * @return void
     */
    private function loadSyncIgnore(string $directory): void
    {
        $contents = file($directory . '/.syncignore');

        foreach ($contents as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            $line = str_replace('*', '.*', $line);
            $this->ignorePatterns[] = '/' . str_replace('/', '\/', $line) .'/';
        }
    }
}
