<?php

class CloudFlare extends Object
{
    /**
     * @var string
     */
    const CF_ZONE_ID_CACHE_KEY = 'CFZoneID';

    /**
     * This will toggle to TRUE when a ZoneID has been detected thus allowing the functionality in the admin panel to
     * be available.
     *
     * @var bool
     */
    protected static $ready = false;

    /**
     * Get a singleton instance. Use the default Object functionality
     *
     * @deprecated Use CloudFlare::singleton() instead
     * @return \CloudFlare
     */
    public static function inst()
    {
        Deprecation::notice('2.0', 'Use CloudFlare::singleton() instead');

        return self::singleton();
    }

    /**
     * Ensures that CloudFlare authentication credentials are defined as constants
     *
     * @return bool
     */
    public function hasCFCredentials()
    {
        if (!getenv('TRAVIS') && (!defined('CLOUDFLARE_AUTH_EMAIL') || !defined('CLOUDFLARE_AUTH_KEY'))) {
            return false;
        }

        return true;
    }

    /**
     * Fetches CloudFlare Credentials from YML configuration
     *
     * @return array|bool
     */
    public function getCFCredentials()
    {
        if ($this->hasCFCredentials()) {
            return array(
                'email' => CLOUDFLARE_AUTH_EMAIL,
                'key'   => CLOUDFLARE_AUTH_KEY
            );
        }

        return false;
    }

    /**
     * Purges CloudFlare's cache for URL provided.
     *
     * @note The CloudFlare API sets a maximum of 1,200 requests in a five minute period.
     * @deprecated This method will be removed in favor for CloudFlare_Purge functionality
     *
     * @param $fileOrUrl
     *
     * @return bool
     */
    public function purgeSingle($fileOrUrl)
    {
        Deprecation::notice('2.0', 'This method will be removed in favor for CloudFlare_Purge functionality');

        $purger = CloudFlare_Purge::create();
        $purger
            ->setSuccessMessage('CloudFlare cache has been purged for: ' . $fileOrUrl)
            ->pushFile($fileOrUrl)
            ->purge();

        return $purger->getResponse();
    }

    /**
     * Purge a specific SiteTree instance, or by its ID
     *
     * @deprecated This method will be removed in favor for CloudFlare_Purge functionality

     * @param  SiteTree|int $pageOrId
     * @return bool
     */
    public function purgePage($pageOrId)
    {
        Deprecation::notice('2.0', 'This method will be removed in favor for CloudFlare_Purge functionality');

        return CloudFlare_Purge::singleton()->quick('page', $pageOrId);
    }

    /**
     * Same as purgeSingle with the obvious functionality to handle many
     *
     * @deprecated This method will be removed in favor for CloudFlare_Purge functionality
     *
     * @param array $filesOrUrls
     *
     * @return bool
     */
    public function purgeMany(array $filesOrUrls)
    {
        Deprecation::notice('2.0', 'This method will be removed in favor for CloudFlare_Purge functionality');

        $purger = CloudFlare_Purge::create();
        $purger
            ->setSuccessMessage("CloudFlare cache has been purged for: {file_count} files")
            ->setFiles($filesOrUrls)
            ->purge();

        return $purger->getResponse();

    }

    /**
     * Purges everything that CloudFlare has cached
     *
     * @deprecated This method will be removed in favor for CloudFlare_Purge functionality
     *
     * @param null $customAlert
     *
     * @return bool
     */
    public function purgeAll($customAlert = null)
    {
        Deprecation::notice('2.0', 'This method will be removed in favor for CloudFlare_Purge functionality');

        return CloudFlare_Purge::singleton()->quick('all');
    }

    /**
     * Finds all .CSS files recursively from root directory, and purges them from cache in a single request
     *
     * @deprecated This method will be removed in favor for CloudFlare_Purge functionality
     *
     * @return bool
     */
    public function purgeCss()
    {
        Deprecation::notice('2.0', 'This method will be removed in favor for CloudFlare_Purge functionality');

        return CloudFlare_Purge::singleton()->quick('css');
    }

    /**
     * Finds all .JS files recursively from root directory, and purges them from cache in a single request
     *
     * @deprecated This method will be removed in favor for CloudFlare_Purge functionality
     *
     * @return bool
     */
    public function purgeJavascript()
    {
        Deprecation::notice('2.0', 'This method will be removed in favor for CloudFlare_Purge functionality');

        return CloudFlare_Purge::singleton()->quick('javascript');

    }

    /**
     * Finds all image files recursively from root directory, and purges them from cache in a single request
     *
     * @deprecated This method will be removed in favor for CloudFlare_Purge functionality
     */
    public function purgeImages()
    {
        Deprecation::notice('2.0', 'This method will be removed in favor for CloudFlare_Purge functionality');

        return CloudFlare_Purge::singleton()->quick('image');
    }

    /**
     * Gathers the current server name, which will be used as the CloudFlare zone ID
     *
     * @return string
     */
    public function getServerName()
    {
        $serverName = '';
        if (!empty($_SERVER['SERVER_NAME'])) {
            $server = \Convert::raw2xml($_SERVER); // "Fixes" #1
            $serverName = $server['SERVER_NAME'];
        }

        // CI support
        if (getenv('TRAVIS')) {
            $serverName = getenv('CLOUDFLARE_DUMMY_SITE');
        }

        // Remove protocols, etc
        $replaceWith = array(
            'www.' => '',
            'http://' => '',
            'https://' => ''
        );
        $serverName = str_replace(array_keys($replaceWith), array_values($replaceWith), $serverName);

        // Allow extensions to modify or replace the server name if required
        $this->extend('updateCloudFlareServerName', $serverName);

        return $serverName;
    }

    /**
     * Returns whether caching is enabled for the CloudFlare class instance
     * @return bool
     */
    public function getCacheEnabled()
    {
        return (bool)self::config()->cache_enabled === true;
    }

    /**
     * Gets the CF Zone ID for the current domain.
     *
     * @return string|bool
     */
    public function fetchZoneID()
    {
        if ($this->getCacheEnabled()) {
            $factory = \SS_Cache::factory("CloudFlare");

            if ($cache = $factory->load(self::CF_ZONE_ID_CACHE_KEY)) {
                $this->isReady(true);

                return $cache;
            }
        }

        $serverName = $this->getServerName();

        if ($serverName == 'localhost') {
            CloudFlare_Notifications::handleMessage(
                _t(
                    "CloudFlare.NoLocalhost",
                    "This module does not operate under <strong>localhost</strong>." .
                    "Please ensure your website has a resolvable DNS and access the website via the domain."
                ),
                "error"
            );

            return false;
        }

        $url = "https://api.cloudflare.com/client/v4/zones" .
            "?name={$serverName}" .
            "&status=active&page=1&per_page=20" .
            "&order=status&direction=desc&match=all";

        $result = $this->curlRequest($url, null, 'GET');

        $array = json_decode($result, true);

        if (!is_array($array) || !array_key_exists("result", $array) || empty($array['result'])) {
            $this->isReady(false);
            CloudFlare_Notifications::handleMessage(
                _t(
                    "CloudFlare.ZoneIdNotFound",
                    "Unable to detect a Zone ID for <strong>{server_name}</strong> under the defined CloudFlare" .
                    " user.<br/><br/>Please create a new zone under this account to use this module on this domain.",
                    "",
                    array(
                        "server_name" => $serverName
                    )
                ),
                "error"
            );

            return false;
        }

        $zoneID = $array['result'][0]['id'];

        if ($this->getCacheEnabled() && isset($factory)) {
            $factory->save($zoneID, self::CF_ZONE_ID_CACHE_KEY);
        }

        $this->isReady(true);

        return $zoneID;
    }

    /**
     * Handles requests for cache purging
     *
     * @param array|null $data
     * @param string $method
     *
     * @param null $isRecursing
     * @deprecated Moved to CloudFlare_Purge
     * @return array|string
     */
    public function purgeRequest(array $data = null, $isRecursing = null, $method = 'DELETE')
    {
        Deprecation::notice('2.0', 'This method has been moved to CloudFlare_Purge');

        return CloudFlare_Purge::singleton()->handleRequest($data, $isRecursing, $method);
    }

    /**
     * Converts /public_html/path/to/file.ext to example.com/path/to/file.ext
     *
     * @deprecated This method has been moved to CloudFlare_Purge
     *
     * @param string|array $files
     *
     * @return array|bool|string
     */
    public static function convertToAbsolute($files)
    {
        Deprecation::notice('2.0', 'This method has been moved to CloudFlare_Purge');

        return CloudFlare_Purge::singleton()->convertToAbsolute($files);
    }

    /**
     * Set or get the ready state
     *
     * @param null $state
     *
     * @return bool|null
     */
    public function isReady($state = null)
    {
        if ($state) {
            self::$ready = (bool)$state;

            return $state;
        }

        $this->fetchZoneID();

        return self::$ready;
    }

    /**
     * Recursively find files with a specific extension(s) starting at the document root
     *
     * @param string|array $extensions
     * @deprecated
     * @return array
     */
    public function findFilesWithExts($extensions)
    {
        Deprecation::notice('2.0', 'This method has been moved to CloudFlare_Purge');
        $purger = CloudFlare_Purge::singleton()->findFilesWithExts($extensions);
        return $purger->getFiles();
    }

    /**
     * Converts links to there "Stage" or "Live" counterpart
     *
     * @param array $urls
     * @deprecated To be removed in 2.0
     * @return array
     */
    protected function getStageUrls(array $urls = array())
    {
        Deprecation::notice('2.0', 'This module will no longer support purging stage URLs, instead see the README for setting up our recommended page rules for SilverStripe');

        foreach ($urls as &$url) {
            $parts = parse_url($url);
            if (isset($parts['query'])) {
                parse_str($parts['query'], $params);
            }

            $params = (isset($parts['query']) && isset($params)) ? $params : array();


            $url = $url . ((strstr($url, "?")) ? "&stage=Stage" : "?stage=Stage");
        }

        return $urls;
    }

    /**
     * Generates URL variants (Stage urls, HTTPS, Non-HTTPS)
     *
     * @param $urls
     * @deprecated See CloudFlare_Purge::singleton()->getUrlVariants()
     * @return array
     */
    public function getUrlVariants($urls)
    {
        Deprecation::notice('2.0', 'This method has been moved to CloudFlare_Purge');
        return CloudFlare_Purge::singleton()->getUrlVariants($urls);
    }

    /**
     * Get or Set the Session Jar
     *
     * @return array|mixed|null|\Session
     */
    public function getSessionJar()
    {
        $session = \Session::get('slCloudFlare') ?: (\Session::set('slCloudFlare',
            array())) ?: \Session::get('slCloudFlare');

        return $session;
    }

    /**
     * @param $data
     *
     * @return $this
     */
    public function setSessionJar($data)
    {
        \Session::set('slCloudFlare', $data);

        return $this;
    }

    /**
     * Returns the cURL execution timeout limit (seconds)
     *
     * @return int
     */
    public function getCurlTimeout()
    {
        return (int)self::config()->curl_timeout;
    }

    /**
     * Handles/delegates all responses from CloudFlare
     *
     * @deprecated Moved to CloudFlare_Purge
     *
     * @param $response
     *
     * @return bool
     * @internal   param null $successMsg
     * @internal   param null $errorMsg
     *
     */
    public function responseHandler($response)
    {
        Deprecation::notice('2.0', 'This method has been moved to CloudFlare_Purge');
        $purger = CloudFlare_Purge::create();
        
        return $purger->setResponse($response)->isSuccessful();
    }

    /**
     * Handles/delegates multiple responses
     *
     * @deprecated Moved to CloudFlare_Purge
     *
     * @param array|string $responses
     *
     * @return bool
     * @internal   param null $successMsg
     * @internal   param null $errorMsg
     */
    public function multiResponseHandler($responses)
    {
        Deprecation::notice('2.0', 'This method has been moved to CloudFlare_Purge');

        $purger = CloudFlare_Purge::create();

        return $purger->setResponse($responses)->isSuccessful();

    }

    /**
     * Sets the X-Status header which creates the toast-like popout notification
     *
     * @deprecated See CloudFlare_Notifications::handleMessage()
     * @param $message
     */
    public function setToast($message)
    {
        Deprecation::notice('2.0', 'This method has been moved to CloudFlare_Notifications::handleMessage()');

        CloudFlare_Notifications::handleMessage($message);
    }

    /**
     * Sets an Alert that will display on the CloudFlare LeftAndMain
     *
     * @deprecated See CloudFlare_Notifications::handleMessage()
     *
     * @param $message
     *
     * @internal   param string $type
     */
    public function setAlert($message)
    {
        Deprecation::notice('2.0', 'This method has been removed to CloudFlare_Notifications::handleMessage()');

        CloudFlare_Notifications::handleMessage($message);
    }

    /**
     * This just bloats the code alot so I made a function for it to DRY it up
     *
     * @deprecated This method has been moved to CloudFlare_ErrorHandlers::generic()
     *
     * @param $response
     */
    protected function genericErrorHandler($response)
    {
        Deprecation::notice('2.0', 'This method has been moved to CloudFlare_ErrorHandlers::generic()');

        return CloudFlare_ErrorHandlers::generic($response);
    }

    /**
     * Fetch the CloudFlare configuration
     * @return \Config_ForClass
     */
    public static function config()
    {
        return \Config::inst()->forClass('CloudFlare');
    }

    /**
     * Sends our cURL requests with our custom auth headers
     *
     * @param string $url    The URL
     * @param null   $data   Optional array of data to send
     * @param string $method GET, PUT, POST, DELETE etc
     *
     * @return string JSON
     */
    public function curlRequest($url, $data = null, $method = 'DELETE')
    {
        $curlTimeout = $this->getCurlTimeout();

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $curlTimeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $curlTimeout);

        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);


        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->getAuthHeaders());
        // This is intended, and was/is required by CloudFlare at one point
        curl_setopt($curl, CURLOPT_USERAGENT, $this->getUserAgent());

        if (!is_null($data)) {
            if (is_array($data)) {
                $data = json_encode($data);
            }
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }

        $result = curl_exec($curl);

        // Handle any errors
        if (false === $result) {
            user_error(sprintf("Error connecting to CloudFlare:\n%s", curl_error($curl)), E_USER_ERROR);
        }
        curl_close($curl);

        return $result;
    }

    /**
     * Fake a user agent
     *
     * @return string
     */
    public function getUserAgent()
    {
        return "Mozilla/5.0 " .
        "(Macintosh; Intel Mac OS X 10_11_6) " .
        "AppleWebKit/537.36 (KHTML, like Gecko) " .
        "Chrome/53.0.2785.143 Safari/537.36";
    }

    /**
     * Get Authentication Headers
     *
     * @return array
     */
    public function getAuthHeaders()
    {
        if (getenv('TRAVIS')) {
            $auth = array(
                'email' => getenv('AUTH_EMAIL'),
                'key' => getenv('AUTH_KEY'),
            );
        } elseif (!$auth = $this->getCFCredentials()) {
            user_error("CloudFlare API credentials have not been provided.");
            exit;
        }

        $headers = array(
            "X-Auth-Email: {$auth['email']}",
            "X-Auth-Key: {$auth['key']}",
            "Content-Type: application/json"
        );

        $this->extend("updateCloudFlareAuthHeaders", $headers);

        return $headers;
    }
}
