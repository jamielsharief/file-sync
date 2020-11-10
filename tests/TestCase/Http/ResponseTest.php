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

use FileSync\Http\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function testStatusCode()
    {
        $exepected = 404;
        $response = new Response();

        $this->assertEquals(200, $response->statusCode());
        $response->statusCode($exepected);
        $this->assertEquals($exepected, $response->statusCode());
    }

    public function testHeaders()
    {
        $response = new Response();
        $response->header('Content-Encoding: gzip');
        $this->assertEquals(['Content-Encoding: gzip'], $response->headers());
    }
}
