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
namespace FileSync\Http;

/**
 * Mini request object
 */
class Request
{
    /**
     * @var array
     */
    protected $data = [];
    
    /**
     * @var string|null
     */
    protected $method = null;

    public function __construct()
    {
        $this->data = $this->readInput();
        $this->method = $_SERVER['REQUEST_METHOD'] ?? null;
    }

    /**
     * Gets the data
     *
     * @return array
     */
    public function data(array $data = null): array
    {
        if ($data === null) {
            $data = $this->data;
        }

        return $this->data = $data;
    }

    /**
     * Gets the method
     *
     * @param string $method
     * @return string|null
     */
    public function method(string $method = null): ?string
    {
        if ($method === null) {
            $method = $this->method;
        }

        return $this->method = $method;
    }

    /**
     * @return boolean
     */
    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /**
     * Reads the input
     *
     * @return array
     */
    protected function readInput(): array
    {
        $input = file_get_contents('php://input');
    
        return $input ? json_decode($input, true) : [];
    }
}
