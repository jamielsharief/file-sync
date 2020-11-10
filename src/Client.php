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
namespace FileSync;

use Exception;
use Origin\HttpClient\Http;
use FileSync\Filesystem\Folder;
use Origin\HttpClient\Response;
use Encryption\AsymmetricEncryption;
use FileSync\Exception\FileSyncException;

class Client extends BaseFileSync
{
    /**
     * @var \Origin\HttpClient\Http $http
     */
    private $http;

    /**
     * @var string
     */
    protected $serverURL;

    /**
    * @var string|null
    */
    protected $token;

    /**
     * @param string $keychainPath
     * @param array $options The following options keys are supported
     *  - httpOptions: An array of options to be passed to the http client
     *    @see https://www.originphp.com/docs/utility/http/
     */
    public function __construct(string $keychainPath, array $options = [])
    {
        $options += ['httpOptions' => []];

        $this->setKeychainPath($keychainPath);

        $this->http = new Http($options['httpOptions']);
        $this->encryption = new AsymmetricEncryption();
    }

    /**
     * Syncs the Client to the Remote
     *
     * @param string $server e.g. https://example.com/server.php
     * @param string $userId a unique user id, such as an email address, UUID etc.
     * @param string $directory The local directory that will be updated from the server
     * @param array $options The following options keys are supported:
     *  - delete: default:false. Deletes files on the client that do not exist on the server.
     *  - checksum: default:false Use checksum instead of size and time
     * @return void
     */
    public function dispatch(string $server, string $userId, string $directory, array $options = []): void
    {
        $options += ['delete' => false,'checksum' => false];
       
        $this->serverURL = $server;

        $this->validateUserId($userId);

        if (! $this->hasPrivateKey($userId)) {
            throw new FileSyncException('Invalid user id');
        }
                
        $this->token = $this->authorize($userId, $server);
        if (! $this->token) {
            throw new FileSyncException('Decryption error');
        }

        $data = $this->buildFileList($directory, $options['checksum']);
        if ($data) {
            $this->syncFiles($directory, $data, $options['delete']);
        }
        $this->unauthorize();
    }

    /**
     * Authorizes the client and gets token by decrypting the challenge
     *
     * @param string $username
     * @return string|null
     */
    protected function authorize(string $username): ? string
    {
        $response = $this->sendPostRequest([
            'action' => 'authorize',
            'username' => $username
        ]);

        if ($response->ok()) {
            $body = $response->json();

            if (isset($body['data']['challenge'])) {
                return $this->encryption->decrypt(
                    $body['data']['challenge'], $this->loadPrivateKey($username)
                );
            }
        }

        throw new FileSyncException('Authentication process failure');
    }

    /**
     * Log out
     *
     * @return \Origin\HttpClient\Response
     */
    protected function unauthorize(): Response
    {
        return $this->sendPostRequest([
            'action' => 'unauthorize',
            'token' => $this->token
        ]);
    }

    /**
     * Sends the post request
     *
     * @param array $data
     * @return \Origin\HttpClient\Response
     */
    protected function sendPostRequest(array $data): Response
    {
        try {
            return $this->http->post($this->serverURL, [
                'fields' => $data,
                'type' => 'json'
            ]);
        } catch (Exception $exception) {
            throw new FileSyncException($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * Send the file list to the server and compare the differences
     *
     * @param string $directory
     * @return array
     */
    protected function buildFileList(string $directory, bool $checksum = false): array
    {
        $response = $this->sendPostRequest([
            'action' => 'difference',
            'token' => $this->token,
            'files' => (new Folder())->list($directory),
            'checksum' => $checksum
        ]);
    
        return $response->json()['data'];
    }

    /**
     * Takes the final result, downloads new or changed files, set permissions. If the delete option
     * is supplied this will delete files that do match the sever.
     *
     * @param string $directory
     * @param array $data
     * @param boolean $delete
     * @return void
     */
    protected function syncFiles(string $directory, array $data, bool $delete): void
    {
        $this->updateFiles($directory, $data['update']);
        if ($delete) {
            $this->deleteFiles($directory, $data['delete']);
        }
    }

    /**
     * Updates the files
     *
     * @param string $directory
     * @param array $files
     * @return void
     */
    protected function updateFiles(string $directory, array $files): void
    {
        foreach ($files as $info) {
            $this->updateFile($directory, $info);
        }
    }

    /**
     * This downloads the file from the remote, saves the file to the local
     * storage, modifies permissions and then sets the modified timestamp to
     * the same as the server.
     *
     * @param string $directory
     * @param array $info
     * @return void
     */
    protected function updateFile(string $directory, array $info): void
    {
        $response = $this->sendPostRequest([
            'action' => 'download',
            'token' => $this->token,
            'file' => $info['path']
        ]);

        $path = $directory . '/' . $info['path'];
   
        if (! file_exists(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
   
        file_put_contents($path, $response->body(), LOCK_EX);
        chmod($path, octdec($info['permissions']));
        touch($path, $info['modified']); # Important: set after chmod
    }

    /**
     * Deletes the files
     *
     * @param string $directory
     * @param array $files
     * @return void
     */
    protected function deleteFiles(string $directory, array $files): void
    {
        foreach ($files as $relativePath) {
            unlink($directory . '/' . $relativePath);
        }
    }
}
