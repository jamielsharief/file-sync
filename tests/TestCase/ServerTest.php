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

use FileSync\Server;
use FileSync\Http\Request;
use FileSync\Http\Response;
use FileSync\Filesystem\Folder;
use PHPUnit\Framework\TestCase;
use Encryption\AsymmetricEncryption;

class ResponseStub extends Response
{
    public function sendFile(string $path): void
    {
        $this->body = file_get_contents($path);
    }

    public function render()
    {
    }
}

class MockServer extends Server
{
    public function set(string $key, $value)
    {
        $this->$key = $value;
    }
    public function request(): Request
    {
        return $this->request;
    }

    public function response(): ResponseStub
    {
        return $this->response;
    }
}

class ServerTest extends TestCase
{
    protected function setUp(): void
    {
        $this->keychainPath = dirname(__DIR__, 1) . '/Fixture/keys';
        $this->sourcePath = dirname(__DIR__, 1) . '/Fixture/data';
    }

    protected function createMockServer()
    {
        $server = new MockServer($this->keychainPath);
        $response = new ResponseStub();
        $request = new Request();
        $server->set('response', $response);
        $server->set('request', $request);

        return $server;
    }

    public function testDispatch()
    {
        $mock = $this->createMockServer();
        $mock->dispatch($this->sourcePath);
       
        $this->assertEquals('{"error":{"message":"Unauthorized","code":401}}', $mock->response()->body());
    }

    public function testAuthorize()
    {
        $response = $this->sendPostRequest([
            'action' => 'authorize',
            'username' => 'demo@example.com'
        ]);
        $this->assertStringContainsString('{"data":{"challenge":"-----BEGIN ENCRYPTED DATA-----', $response->body());
    }

    public function testAuthorizeUnkownUser()
    {
        $response = $this->sendPostRequest([
            'action' => 'authorize',
            'username' => 'tony@stark.com'
        ]);

        $this->assertStringContainsString('{"error":{"message":"Unauthorized","code":401}}', $response->body());
    }

    public function testUnauthorize()
    {
        $response = $this->sendPostRequest([
            'action' => 'unauthorize',
            'token' => $this->authorize()
        ]);

        $this->assertStringContainsString('{"data":[]}', $response->body());
    }

    /**
     * @depends testUnauthorize
     */
    public function testUnauthorizeInvalidToken()
    {
        $response = $this->sendPostRequest([
            'action' => 'unauthorize',
            'token' => 'foo'
        ]);
       
        $this->assertEquals('{"error":{"message":"Unauthorized","code":401}}', $response->body());
    }

    public function testDifferenceUnauthorized()
    {
        $response = $this->sendPostRequest([
            'action' => 'difference',
            'token' => 'foo',
            'files' => [],
            'checksum' => false
        ]);

        $this->assertEquals('{"error":{"message":"Unauthorized","code":401}}', $response->body());
    }

    public function testDifference()
    {
        $response = $this->sendPostRequest([
            'action' => 'difference',
            'token' => $this->authorize(),
            'files' => [
                [
                    'path' => 'foo.md',
                    'size' => 25,
                    'modified' => 1604925505,
                    'permissions' => '0644'
                ]
            ],
            'checksum' => false
        ]);
        // travis CI testing issues, modified & file order
        $json = preg_replace('/"modified":([\d]+),/', '"modified":123456789,', $response->body());
   
        $this->assertStringContainsString(
          '{"path":"README.md","size":20,"modified":123456789,"permissions":"0644","checksum":"357e0cdc"}',
          $json
      );
        $this->assertStringContainsString(
          '{"path":"folder\/.gitignore","size":13,"modified":123456789,"permissions":"0644","checksum":"fc786ea8"}',
          $json
      );

        $this->assertStringContainsString(
        '"delete":["foo.md"]',
        $json
    );
    }

    public function testDifferenceChecksum()
    {
        // Create Files
        $response = $this->sendPostRequest([
            'action' => 'difference',
            'token' => $this->authorize(),
            'files' => [],
            'checksum' => false
        ]);

        /**
         * To test checksum, going to create a list of files in the SOURCE, then modify
         * a checksum of one of them
         */
        $folder = new Folder();
        $list = $folder->list($this->sourcePath);
        $list[1]['checksum'] = '<-o->';

        $response = $this->sendPostRequest([
            'action' => 'difference',
            'token' => $this->authorize(),
            'files' => $list,
            'checksum' => true
        ]);
     
        // travis CI testing issues, modified & file order
        $json = preg_replace('/"modified":([\d]+),/', '"modified":123456789,', $response->body());
        
        $this->assertStringContainsString(
            '{"path":"README.md","size":20,"modified":123456789,"permissions":"0644","checksum":"357e0cdc"}',
            $json
        );
    }

    public function testDownload()
    {
        $response = $this->sendPostRequest([
            'action' => 'download',
            'token' => $this->authorize(),
            'file' => 'README.md'
        ]);
        $expected = file_get_contents($this->sourcePath  . '/README.md');
        $this->assertEquals($expected, $response->body());
    }

    public function testDownloadNotFound()
    {
        $response = $this->sendPostRequest([
            'action' => 'download',
            'token' => $this->authorize(),
            'file' => 'PASSWORDS.txt'
        ]);
        $this->assertEquals('{"error":{"message":"Not Found","code":404}}', $response->body());
    }

    /**
     * @depends testDownload
     */
    public function testDownloadPasswordEtc()
    {
        $response = $this->sendPostRequest([
            'action' => 'download',
            'token' => $this->authorize(),
            'file' => '/etc/passwd'
        ]);
      
        $this->assertEquals('{"error":{"message":"Not Found","code":404}}', $response->body());
    }

    /**
     * @depends testDownload
     * This file does exist, and if the securiy feature is disabled then it will be picked up
     */
    public function testDownloadSecurity()
    {
        // I temporarily disabled security during dev to test that its reachable.
        $relativePath = '../../../LICENSE.md';
        $this->assertFileIsReadable($this->sourcePath . '/' . $relativePath);

        $response = $this->sendPostRequest([
            'action' => 'download',
            'token' => $this->authorize(),
            'file' => $relativePath
        ]);
      
        $this->assertEquals('{"error":{"message":"Not Found","code":404}}', $response->body());
    }

    /**
     * Gets a Token
     *
     * @return string
     */
    private function authorize(): string
    {
        $response = $this->sendPostRequest([
            'action' => 'authorize',
            'username' => 'demo@example.com'
        ]);

        $data = json_decode($response->body(), true);
        $this->assertNotEmpty($data['data']['challenge']);

        return (new AsymmetricEncryption())->decrypt(
            $data['data']['challenge'],
            file_get_contents(dirname(__DIR__, 1) . '/Fixture/keys/demo@example.com.privateKey')
        );
    }

    protected function sendPostRequest(array $data)
    {
        $this->mock = $this->createMockServer();
        $request = $this->mock->request();
        $request->method('POST');
        $request->data($data);
       
        $this->mock->dispatch($this->sourcePath);

        return $this->mock->response();
    }
}
