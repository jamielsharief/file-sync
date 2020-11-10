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

use FileSync\Http\Request;
use FileSync\Http\Response;
use FileSync\Filesystem\Diff;
use FileSync\Filesystem\Folder;
use Encryption\AsymmetricEncryption;

class Server extends BaseFileSync
{
    /**
     * @var string
     */
    protected $keychainPath;

    /**
     * Path to files that being synced
     *
     * @var string
     */
    protected $path;

    /**
     * How long before token expires, client will deauthorize a token when
     * complete, but in the case of terminated connections this will kill them
     *
     * @var int
     */
    protected $duration;

    /**
     * @var \FileSync\Http\Request
     */
    protected $request;

    /**
     * @var \FileSync\Http\Response
     */
    protected $response;

    /**
     * @var array
     */
    protected $allowedActions = ['authorize','unauthorize','difference','download'];

    /**
     * @param string $keychainPath A directory where all keys are stored
     * @param array $options The following options are supported:
     *  - duration: default:3600 how long the token is valid for.
     */
    public function __construct(string $keychainPath, array $options = [])
    {
        $options += ['duration' => 3600];

        if (is_string($options['duration'])) {
            $options['duration'] = strtotime($options['duration']) - time();
        }

        $this->duration = $options['duration'];

        $this->setKeychainPath($keychainPath);
        
        $this->encryption = new AsymmetricEncryption();

        $this->request = new Request();
        $this->response = new Response();
    }

    /**
     * Dispatches the server
     *
     * TODO: this is ugly
     *
     * @return void
     */
    public function dispatch(string $path, array $options = []): void
    {
        $this->path = realpath($path);

        $data = $this->request->data();

        if (! $this->request->isPost() || empty($data['action']) || ! in_array($data['action'], $this->allowedActions)) {
            $this->renderError('Unauthorized', 401);

            return;
        }

        if ($data['action'] === 'authorize') {
            $this->authorize($data);

            return;
        }

        if ($this->isAuthorized($data['token']) === false) {
            $this->renderError('Unauthorized', 401);

            return;
        }

        switch ($data['action']) {
            case 'unauthorize':
                $this->unauthorize($data);
            break;
            case 'difference':
                $this->difference($data);
            break;
            case 'download':
                $this->download($data);
            break;
        }
    }

    /**
     * Checks if authorized (including token has not expired)
     *
     * @param string $uid
     * @return boolean
     */
    protected function isAuthorized(string $uid = null): bool
    {
        $path = $this->tokenPath($uid);

        if (! $uid || ! file_exists($path)) {
            return false;
        }

        $expires = filemtime($path) + $this->duration;

        return $expires > time();
    }

    /**
     * @param string $userId
     * @return boolean
     */
    private function isValidUserId(string $userId): bool
    {
        $this->validateUserId($userId);
  
        return $this->hasPublicKey($userId);
    }

    /**
     * Creates the Challenge use for authentication
     *
     * @param string $userId
     * @return string
     */
    protected function generateChallenge(string $userId): string
    {
        $challenge = bin2hex(random_bytes(20));
        file_put_contents($this->tokenPath($challenge), date('Y-m-d H:i:s'));

        return $this->encryption->encrypt(
            $challenge, $this->loadPublicKey($userId)
        );
    }

    /**
     * Authorization handler
     *
     * @param array $data
     * @return void
     */
    protected function authorize(array $data): void
    {
        if (isset($data['username']) && $this->isValidUserId($data['username'])) {
            $this->renderResponse([
                'data' => [
                    'challenge' => $this->generateChallenge($data['username'])
                ]
            ]);
        } else {
            $this->renderError('Unauthorized', 401);
        }
    }

    /**
     * Unauthorize action
     *
     * @internal Must be authorized to unauthorize
     *
     * @param string $token
     * @return void
     */
    protected function unauthorize(array $data): void
    {
        unlink($this->tokenPath($data['token']));

        $this->renderResponse([
            'data' => []
        ]);
    }

    /**
     * Post handler
     *
     * @return void
     */
    protected function difference(array $data)
    {
        if (! isset($data['files']) || ! isset($data['checksum'])) {
            $this->renderError('Bad Request', 400);

            return;
        }

        $files = (new Diff())->generate(
            (new Folder())->list($this->path), $data['files'], ['checksum' => $data['checksum']]
        );

        $this->renderResponse([
            'data' => $files
        ]);
    }

    /**
     * Handles the download action
     *
     * @param array $data
     * @return void
     */
    protected function download(array $data): void
    {
        if (! isset($data['file'])) {
            $this->renderError('Bad Request', 400);

            return;
        }

        $relativePath = urldecode($data['file']);
        $path = $this->path . '/' . $relativePath;
      
        $realPath = realpath($path);

        $folder = new Folder();
        $folder->loadSyncignore($this->path);

        /**
         * SECURITY - Check for possible Directory Traversal Attacks
         * @see https://www.acunetix.com/websitesecurity/directory-traversal/
         * @internal
         * - Real path will return false if the file does not exist or does not have readable
         *   permissions.
         * - Also make sure attempts to get data outside of directory are blocked e.g. using docs/../../password.dat
         * - Check for malicious requests trying to access .syncignore or files/folders blocked by sync ignore.
         */

        if (
                $realPath === false ||
                substr($realPath, 0, strlen($this->path)) !== $this->path ||
                substr($realPath, -11) === '.syncignore' ||
                $folder->ignorePath($realPath)
            ) {
            $this->renderError('Not Found', 404);

            return;
        }

        $this->response->sendFile($path);
    }

    /**
     * A a quick way to render an error
     *
     * @param string $message
     * @param integer $code
     * @return void
     */
    protected function renderError(string $message, int $code): void
    {
        $this->renderResponse([
            'error' => ['message' => $message,'code' => $code]
        ], $code);
    }

    /**
     * Renders the response
     *
     * @param array $response
     * @return void
     */
    protected function renderResponse(array $response, int $status = 200): void
    {
        $this->response->header('Content-Type: application/json');
        $this->response->statusCode($status);
        
        $this->response->body(json_encode($response));
        $this->response->render();
    }

    /**
     * Gets the path of the token
     *
     * @param string $uid
     * @return string
     */
    protected function tokenPath(string $uid): string
    {
        return sys_get_temp_dir() . '/' . $uid . '.tmp';
    }
}
