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

use InvalidArgumentException;

/**
 * Mini Response
 *
 * @internal Moved The request/response functions into their own objects.
 */
class Response
{
    /**
     * @var string|null
     */
    protected $body = null;

    /**
     * @var integer
     */
    protected $statusCode = 200;

    /**
     * @var array
     */
    protected $headers = [];
    
    /**
     * Sends the response body
     *
     * @param string $body
     * @return void
     */
    public function body(string $body = null): ?string
    {
        if ($body === null) {
            $body = $this->body;
        }

        return $this->body = $body;
    }

    /**
     * @param integer $statusCode
     * @return integer|null
     */
    public function statusCode(int $statusCode = null): ?int
    {
        if ($statusCode === null) {
            $statusCode = $this->statusCode;
        }

        return $this->statusCode = $statusCode;
    }

    /**
     * @return array
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * @param string $header
     * @return void
     */
    public function header(string $header): void
    {
        $this->headers[] = $header;
    }

    /**
     * @codeCoverageIgnore
     * @return void
     */
    public function render()
    {
        foreach ($this->headers as $header) {
            header($header);
        }
        http_response_code($this->statusCode);
        echo $this->body;
    }

    /**
     * Sends a file respone
     *
     * @param string $path
     */
    public function sendFile(string $path): void
    {
        if (file_exists($path)) {
            // @codeCoverageIgnoreStart
            header('Content-Type: application/octet-stream');
            $fp = fopen($path, 'rb');
            fpassthru($fp);
            exit();
            // @codeCoverageIgnoreEnd
        }
        throw new InvalidArgumentException('File not found');
    }
}
