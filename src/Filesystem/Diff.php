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

class Diff
{
    /**
     * Gets a list of files/directories that are different,
     *
     * @param array $src
     * @param array $dest
     * @param array $options
     * @return array
     */
    public function generate(array $src, array $dest, array $options = []): array
    {
        $options += ['checksum' => false];
 
        $out = ['update' => [], 'delete' => []];

        $src = $this->reindex($src);
        $dest = $this->reindex($dest);

        foreach (array_keys($src) as $path) {
            if (! isset($dest[$path]) || ! $this->compare($src[$path], $dest[$path], $options['checksum'])) {
                $out['update'][] = $src[$path];
            }
        }

        foreach (array_keys($dest) as $path) {
            if (! isset($src[$path])) {
                $out['delete'][] = $path;
            }
        }
  
        return $out;
    }

    /**
     * @param array $src
     * @param array $dest
     * @param boolean $checksum
     * @return boolean
     */
    protected function compare(array $src, array $dest, bool $checksum = false): bool
    {
        if ($checksum) {
            return $src['checksum'] === $dest['checksum'];
        }

        unset($src['checksum'],$dest['checksum']);

        return $src === $dest;
    }

    /**
     * Reindexes using the path as the key
     *
     * @param array $data
     * @return array
     */
    private function reindex(array $data): array
    {
        $out = [];
        foreach ($data as $value) {
            $out[$value['path']] = $value;
        }

        return $out;
    }
}
