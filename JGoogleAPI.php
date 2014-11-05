<?php

/**
 * The MIT License
 *
 * @copyright Copyright 2012 João Parreira <joaofrparreira@gmail.com>.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 * ---------------------------------------------------- 
 *
 * Date: 28/Nov/2012
 * Time: 19:08:49 
 * File: JGoogleAPI.php 
 * Encoding: UTF-8
 *
 * @author: João Parreira <joaofrparreira@gmail.com>
 * @package Project: JGoogleAPI
 * @version 0.1a
 *
 */

// google lib path alias
YiiBase::setPathOfAlias('JGoogleAPISrcAlias', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Google');

/**
 * JGoogleAPI Extension for Yii
 * Credentials can be obtained at https://code.google.com/apis/console
 * 
 * @see http://code.google.com/p/google-api-php-client/wiki/OAuth2 for more information
 */
class JGoogleAPI extends CApplicationComponent
{
    /**
     * @var string Defines the default authentication type to be used
     */
    public $defaultAuthenticationType = 'serviceAPI';
    /**
     * @var string
     */
    private $authenticationType = null;
    /**
     * @var webApplication - Saves the data for api access (Accessed by web browsers over a network)
     */
    public $webappAPI = array(
        'clientId'          => null,
        'clientEmail'       => null,
        'clientSecret'      => null,
        'redirectUri'       => null,
        'javascriptOrigins' => null,
    );
    private $_webappAPI = array(
        'clientId'          => null,
        'clientEmail'       => null,
        'clientSecret'      => null,
        'redirectUri'       => null,
        'javascriptOrigins' => null,
    );

    /**
     * @var serviceAPI - Saves the data for api with a service account 
     *                   (Calls Google APIs on behalf of your application instead of an end-user)
     *                   This is the default authentication type
     */
    public $serviceAPI = array(
        'clientId'           => null,
        'clientEmail'        => null,
        'publicKey'          => null,
        'keyFilePath'        => null,
        'privateKeyPassword' => 'notasecret', //default
        'assertionType'      => 'http://oauth.net/grant_type/jwt/1.0/bearer',
        'prn'                => false
    );
    private $_serviceAPI = array(
        'clientId'           => null,
        'clientEmail'        => null,
        'publicKey'          => null,
        'keyFilePath'        => null,
        'privateKeyPassword' => 'notasecret', //default
        'assertionType'      => 'http://oauth.net/grant_type/jwt/1.0/bearer',
        'prn'                => false
    );
    public $simpleApiKey = null;

    /**
     * @var scopes array
     * This var defines the scopes that will be used by the application
     * Ex: $scopes = array(
     *          'serviceAPI'=>array(
     *              'drive'=>array(
     *                  'https://www.googleapis.com/auth/drive.file',
     *                  'https://www.googleapis.com/auth/drive'
     *              ),
     *          ),
     *          'webappAPI'=>array(
     *              'drive'=>array(
     *                  'https://www.googleapis.com/auth/drive.file',
     *                  'https://www.googleapis.com/auth/drive'
     *              ),
     *          ),
     *      );
     * 
     * Information for available scopes can be found in https://developers.google.com in the api you're trying to access
     */
    public $scopes     = null;
    public $useObjects = false;

    /**
     * @var Google_Client $client - Handle the google client when created
     */
    private $client     = null;
    private $clientType = 'serviceAPI';

    /**
     * @var GoogleService $_service - Handle the google services when created
     */
    private $_services = array();

    /**
     * @var boolean $autoSession - Automatic save the auth token to the session
     */
    public $autoSession = true;
    
    /**
     * @see CApplicationComponent::init()
     */
    public function init()
    {
        parent::init();
        // combine new API params with defaults
        $this->serviceAPI = array_merge($this->_serviceAPI, $this->serviceAPI);
        $this->webappAPI  = array_merge($this->_webappAPI, $this->webappAPI);
        
        // register google classes autoloader after Yii autoloader to prevent conflict
        require_once realpath(dirname(__FILE__) . '/lib/autoload.php');
        YiiBase::registerAutoloader('google_api_php_client_autoload', false);
        // new autoload appended now we can start import main client class
        Yii::import('JGoogleAPISrcAlias.Google_Client');
    }

    /**
     * @param string $type Authentication type "service account" or "User authentication" (serviceAPI or webappAPI) defualt is 'serviceAPI'
     * @return \Google_Client Return the google client object
     * @throws CException Throws exception if type is invalid
     */
    private function createClient($type=null)
    {
        $allowedTypes = array('serviceAPI', 'webappAPI');
        if ( ! isset($this->authenticationType) )
        {
            $this->authenticationType = $this->defaultAuthenticationType;
        }
        if ( ! is_null($type) )
        {
            $this->authenticationType = $type;
        }
        if ( ! isset($this->scopes[$this->authenticationType]) OR ! in_array($this->authenticationType, $allowedTypes) )
        {
            throw new CException("Invalid type given for the client authentication. Must be 'serviceAPI' or 'webappAPI'.");
        }
        // create new client
        $client = new Google_Client();
        $client->setApplicationName(Yii::app()->name);
        // collect all service scopes
        $scopes   = array();
        $services = $this->scopes[$this->authenticationType];
        // set client type same as authentication
        $this->clientType = $this->authenticationType;
        
        foreach ( $services as $service )
        {
            foreach ( $service as $scope )
            {
                $scopes[] = $scope;
            }
        }
        switch ( $this->authenticationType )
        {
            case 'serviceAPI':
                // @todo load service credentials
            break;
            case 'webappAPI':
                // load webapp credentials
                $client->setClientId($this->webappAPI['clientId']);
                $client->setClientSecret($this->webappAPI['clientSecret']);
                $client->setRedirectUri($this->webappAPI['redirectUri']);
                $client->setDeveloperKey($this->simpleApiKey);
                $client->setScopes($scopes);
            break;
        }
        return $client;
    }

    /**
     * 
     * 
     * @param  string $type Authentication type "service account" or "User authentication" (serviceAPI or webappAPI)
     * @return \Google_Client Return the google client object
     */
    public function getClient($type = null)
    {
        if ( is_null($this->client) )
        {
            $this->client = $this->createClient($type);
        }
        return $this->client;
    }

    /**
     * 
     * 
     * @param  string $serviceName Name of the service that we want to call
     * @param  string $type Authentication type "service account" or "User authentication" (serviceAPI or webappAPI)
     * @return service Return instance to Google Service
     * 
     * @throws CException  Throw exception if service name is not declared
     */
    public function getService($serviceName, $type=null)
    {
        if ( ! isset($serviceName) )
        {
            throw new CException("You must declare a service name to use.");
        }
        // Need to do this so the path is set by the api library
        $client = $this->getClient(is_null($this->clientType) ? $type : $this->clientType);
        if ( ! isset($this->_services[$serviceName]) OR ! array_key_exists($serviceName, $this->_services) )
        {
            $serviceClassName = "Google_Service_{$serviceName}";
            $service = new $serviceClassName($client);
            $this->_services[$serviceName] = $service;
        }
        return $this->_services[$serviceName];
    }

    /**
     * 
     * 
     * @param  string $name Name of the object that we want to call (Ex: DriveFile )
     * @param  string\Object $service Name or Instance of the service to use, needed to preload required class
     * @param  string $type Authentication type "service account" or "User authentication" (serviceAPI or webappAPI) 
     * @return \Object Return the Google Object
     * 
     * @throws  CException Throw Exception if we don't declare the Object name or the Service name or instance
     * @example getObject('DriveFile','Drive');
     *          If the service is not instanciated and we pass the service name 
     *          that'll be created automaticaly
     */
    public function getObject($name, $service, $type=null)
    {
        if ( ! isset($name) )
        {
            throw new CException("You must declare one name of the object to call.");
        }
        if ( ! isset($service) )
        {
            throw new CException("You must pass a service name or service object to use. Required for class preload.");
        }
        if ( is_string($service) )
        {
            $this->getService($service, $type);
        }
        $objName = "Google_Service_{$name}";
        
        return new $objName();
    }
}
