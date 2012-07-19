<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Service
 */

namespace ZendService\Rackspace;

use Zend\Http\Client as HttpClient;
use ZendService\Rackspace\Exception;

abstract class AbstractRackspace
{
    const VERSION                = 'v1.0';
    const US_AUTH_URL            = 'https://auth.api.rackspacecloud.com';
    const UK_AUTH_URL            = 'https://lon.auth.api.rackspacecloud.com';
    const API_FORMAT             = 'json';
    const USER_AGENT             = 'ZendService\Rackspace';
    const STORAGE_URL            = "X-Storage-Url";
    const AUTHTOKEN              = "X-Auth-Token";
    const AUTHUSER_HEADER        = "X-Auth-User";
    const AUTHKEY_HEADER         = "X-Auth-Key";
    const AUTHUSER_HEADER_LEGACY = "X-Storage-User";
    const AUTHKEY_HEADER_LEGACY  = "X-Storage-Pass";
    const AUTHTOKEN_LEGACY       = "X-Storage-Token";
    const CDNM_URL               = "X-CDN-Management-Url";
    const MANAGEMENT_URL         = "X-Server-Management-Url";

    /**
     * Rackspace Key
     *
     * @var string
     */
    protected $key;

    /**
     * Rackspace account name
     *
     * @var string
     */
    protected $user;

    /**
     * Token of authentication
     *
     * @var string
     */
    protected $token;

    /**
     * Authentication URL
     *
     * @var string
     */
    protected $authUrl;

    /**
     * @var HttpClient
     */
    protected $httpClient;

    /**
     * Error Msg
     *
     * @var string
     */
    protected $errorMsg;

    /**
     * HTTP error code
     *
     * @var string
     */
    protected $errorCode;

    /**
     * Storage URL
     *
     * @var string
     */
    protected $storageUrl;

    /**
     * CDN URL
     *
     * @var string
     */
    protected $cdnUrl;

    /**
     * Server management URL
     *
     * @var string
     */
    protected $managementUrl;

    /**
     * Constructor
     *
     * You must pass the account and the Rackspace authentication key.
     * Optional: the authentication url (default is US)
     *
     * @param string $user
     * @param string $key
     * @param string $authUrl
     * @throws Exception\InvalidArgumentException
     */
    public function __construct($user, $key, $authUrl = self::US_AUTH_URL, HttpClient $httpClient = null)
    {
        if (!isset($user)) {
            throw new Exception\InvalidArgumentException("The user cannot be empty");
        }
        if (!isset($key)) {
            throw new Exception\InvalidArgumentException("The key cannot be empty");
        }
        if (!in_array($authUrl, array(self::US_AUTH_URL, self::UK_AUTH_URL))) {
            throw new Exception\InvalidArgumentException("The authentication URL should be valid");
        }
        $this->setUser($user);
        $this->setKey($key);
        $this->setAuthUrl($authUrl);
        $this->setHttpClient($httpClient ?: new HttpClient);
    }

    /**
     * @param HttpClient $httpClient
     * @return AbstractRackspace
     */
    public function setHttpClient(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
        return $this;
    }

    /**
     * get the HttpClient instance
     *
     * @return HttpClient
     */
    public function getHttpClient()
    {
        return $this->httpClient;
    }

    /**
     * Get User account
     *
     * @return string
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Get user key
     *
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Get authentication URL
     *
     * @return string
     */
    public function getAuthUrl()
    {
        return $this->authUrl;
    }

    /**
     * Get the storage URL
     *
     * @return string|boolean
     */
    public function getStorageUrl()
    {
        if (empty($this->storageUrl)) {
            if (!$this->authenticate()) {
                return false;
            }
        }
        return $this->storageUrl;
    }

    /**
     * Get the CDN URL
     *
     * @return string|boolean
     */
    public function getCdnUrl()
    {
        if (empty($this->cdnUrl)) {
            if (!$this->authenticate()) {
                return false;
            }
        }
        return $this->cdnUrl;
    }

    /**
     * Get the management server URL
     *
     * @return string|boolean
     * @throws Exception\RuntimeException
     */
    public function getManagementUrl()
    {
        if (empty($this->managementUrl)) {
            if (!$this->authenticate()) {
                throw new Exception\RuntimeException('Authentication failed, you need a valid token to use the Rackspace API');
            }
        }
        return $this->managementUrl;
    }

    /**
     * Set the user account
     *
     * @param string $user
     * @return void
     */
    public function setUser($user)
    {
        if (!empty($user)) {
            $this->user = $user;
        }
    }

    /**
     * Set the authentication key
     *
     * @param string $key
     * @return void
     */
    public function setKey($key)
    {
        if (!empty($key)) {
            $this->key = $key;
        }
    }

    /**
     * Set the Authentication URL
     *
     * @param string $url
     * @return void
     * @throws Exception\InvalidArgumentException
     */
    public function setAuthUrl($url)
    {
        if (!empty($url) && in_array($url, array(self::US_AUTH_URL, self::UK_AUTH_URL))) {
            $this->authUrl = $url;
        } else {
            throw new Exception\InvalidArgumentException("The authentication URL is not valid");
        }
    }

    /**
     * Get the authentication token
     *
     * @return string
     * @throws Exception\RuntimeException
     */
    public function getToken()
    {
        if (empty($this->token)) {
            if (!$this->authenticate()) {
                throw new Exception\RuntimeException('Authentication failed, you need a valid token to use the Rackspace API');
            }
        }
        return $this->token;
    }

    /**
     * Get the error msg of the last HTTP call
     *
     * @return string
     */
    public function getErrorMsg()
    {
        return $this->errorMsg;
    }

    /**
     * Get the error code of the last HTTP call
     *
     * @return string
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * Return true is the last call was successful
     *
     * @return boolean
     */
    public function isSuccessful()
    {
        return (empty($this->errorMsg));
    }

    /**
     * HTTP call
     *
     * @param string $url
     * @param string $method
     * @param array $headers
     * @param array $get
     * @param string $body
     * @return Zend\Http\Response
     */
    protected function httpCall($url,$method,$headers=array(),$data=array(),$body=null)
    {
        $client = $this->getHttpClient();
        $client->resetParameters();
        if (empty($headers[self::AUTHUSER_HEADER])) {
            $headers[self::AUTHTOKEN]= $this->getToken();
        }
        $client->setMethod($method);
        if (empty($data['format'])) {
            $data['format']= self::API_FORMAT;
        }
        $client->setParameterGet($data);
        if (!empty($body)) {
            $client->setRawBody($body);
            if (!isset($headers['Content-Type'])) {
                $headers['Content-Type']= 'application/json';
            }
        }
        $client->setHeaders($headers);
        $client->setUri($url);
        $this->errorMsg = null;
        $this->errorCode = null;
        return $client->send();
    }

    /**
     * Authentication
     *
     * @return boolean
     */
    public function authenticate()
    {
        $headers = array (
            self::AUTHUSER_HEADER => $this->user,
            self::AUTHKEY_HEADER => $this->key
        );
        $result = $this->httpCall($this->authUrl.'/'.self::VERSION,'GET', $headers);
        if ($result->getStatusCode()===204) {
            $this->token = $result->getHeaders()->get(self::AUTHTOKEN)->getFieldValue();
            $this->storageUrl = $result->getHeaders()->get(self::STORAGE_URL)->getFieldValue();
            $this->cdnUrl = $result->getHeaders()->get(self::CDNM_URL)->getFieldValue();
            $this->managementUrl = $result->getHeaders()->get(self::MANAGEMENT_URL)->getFieldValue();
            return true;
        }
        $this->errorMsg = $result->getBody();
        $this->errorCode = $result->getStatusCode();
        return false;
    }
}
