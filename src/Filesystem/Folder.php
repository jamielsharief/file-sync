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

        $this->loadSyncignore($directory);

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

            // I am thinking .syncignore should not never be synced?
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
     * Checks a path against patterns loaded from syncignore
     *
     * @param string $path
     * @return boolean
     */
    public function ignorePath(string $path): bool
    {
        foreach ($this->ignorePatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }
        
        // should equal false
        return $path === '.syncignore';
    }

    /**
     * Loads the .syncignore file from a directory if it exists
     *
     * e.g.
     *
     *      *.json
     *      tests/
     *
     * @param string $directory
     * @return array
     */
    public function loadSyncignore(string $directory): array
    {
        $patterns = [];

        $path = $directory . '/.syncignore';

        if (file_exists($path)) {
            foreach (file($path) as $line) {
                $line = trim($line);
                if ($line) {
                    $line = str_replace('*', '.*', $line);
                    $patterns[] = '/' . str_replace('/', '\/', $line) .'/';
                }
            }
        }
       
        return $this->ignorePatterns = $patterns;
    }
}
