<?php

class CloudFlare extends Object
{

    const CF_ZONE_ID_CACHE_KEY = 'CFZoneID';

    /**
     * This will toggle to TRUE when a ZoneID has been detected thus allowing the functionality in the admin panel to
     * be available.
     *
     * @var bool
     */
    protected static $ready = false;

    /**
     * @var \CloudFlare
     */
    protected static $singleton;

    /**
     * Instance
     * @return \CloudFlare
     */
    public static function inst()
    {
        return (is_object(static::$singleton)) ? static::$singleton : static::$singleton = new CloudFlare();
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
     * Purges CloudFlare's cache for URL provided. We currently don't care for the response
     *
     * @note The CloudFlare API sets a maximum of 1,200 requests in a five minute period.
     *
     * @param $fileOrUrl
     *
     * @return bool
     */
    public function purgeSingle($fileOrUrl)
    {
        // fetch zone ID dynamically
        $zoneId = $this->fetchZoneID();

        if (!$zoneId) {
            if ($this->config()->log_errors) {
                error_log("CloudFlareExt: Attempted to purge cache for {$fileOrUrl} but unable to find Zone ID");
            }

            return false;
        }

        $baseUrl  = Director::absoluteBaseURL();
        $purgeUrl = $baseUrl . $fileOrUrl;

        $data = array(
            "files" => array(
                $purgeUrl
            )
        );

        $result = $this->purgeRequest($data);

        return $this->responseHandler($result, 'CloudFlare cache has been purged for: ' . $fileOrUrl);
    }

    /**
     * Same as purgeSingle with the obvious functionality to handle many
     *
     * @param array $filesOrUrls
     *
     * @return bool
     */
    public function purgeMany(array $filesOrUrls)
    {
        // fetch zone ID dynamically
        $zoneId = $this->fetchZoneID();

        $count = count($filesOrUrls);

        if (!$zoneId) {
            if ($this->config()->log_errors) {
                error_log("CloudFlareExt: Attempted to purge cache for {$count} files but unable to find Zone ID");
            }

            return false;
        }

        $baseUrl = Director::absoluteBaseURL();

        $purgeUrls = array();

        foreach ($filesOrUrls as $fileOrUrl) {
            $purgeUrls[] = $baseUrl . $fileOrUrl;
        }

        $data = array(
            "files" => $purgeUrls
        );

        $response = $this->purgeRequest($data);

        return $this->responseHandler(
            $response,
            "CloudFlare cache has been purged for: {$count} files as a result of updating this page"
        );

    }

    /**
     * Purges everything that CloudFlare has cached
     *
     * @param null $customAlert
     *
     * @return mixed
     */
    public function purgeAll($customAlert = null)
    {
        $data = array(
            "purge_everything" => true
        );

        $response = $this->purgeRequest($data);

        return $this->responseHandler(
            $response,
            $customAlert ?: _t(
                "CloudFlare.PurgedEverything",
                "Successfully purged <strong>EVERYTHING</strong> from cache."
            )
        );
    }

    /**
     * Finds all .CSS files recursively from root directory, and purges them from cache in a single request
     *
     * @return bool
     */
    public function purgeCss()
    {
        $files = $this->findFilesWithExts(array(".css", ".css.map")); // map here for the SASS/LESS enthusiasts

        if (empty($files)) {
            $this->setAlert(
                _t(
                    "CloudFlare.NoCssFilesFound",
                    "No CSS files were found."
                ),
                "error"
            );

            return false;
        }

        $data = array(
            "files" => array()
        );

        foreach ($files as $file) {
            $data[ 'files' ][] = $this->convertToAbsolute($file);
        }

        $result   = $this->purgeRequest($data);
        $response = (is_array($result)) ? $result : json_decode($result, true);

        return $this->responseHandler(
            $response,
            _t(
                "CloudFlare.SuccessPurgedCSS",
                "Successfully purged {file_count} CSS files from cache.",
                "",
                array(
                    'file_count' => count($data[ 'files' ])
                )
            )
        );

    }

    /**
     * Finds all .JS files recursively from root directory, and purges them from cache in a single request
     *
     * @return bool
     */
    public function purgeJavascript()
    {
        $files = $this->findFilesWithExts(".js");

        if (empty($files)) {
            $this->setAlert(
                _t(
                    "CloudFlare.NoJsFilesFound",
                    "No Javascript files were found."
                ),
                "error"
            );

            return false;
        }

        $data = array(
            "files" => array()
        );

        foreach ($files as $file) {
            $data[ 'files' ][] = $this->convertToAbsolute($file);
        }

        $result   = $this->purgeRequest($data);
        $response = (is_array($result)) ? $result : json_decode($result, true);

        return $this->responseHandler(
            $response,
            _t(
                "CloudFlare.SuccessPurgeJavascript",
                "Successfully purged {file_count} JS files from cache.",
                "",
                array(
                    'file_count' => count($data[ 'files' ])
                )
            )
        );

    }

    /**
     * Finds all image files recursively from root directory, and purges them from cache in a single request
     *
     * @return bool
     */
    public function purgeImages()
    {
        $files = $this->findFilesWithExts(array(".jpg", ".jpeg", ".gif", ".png", ".ico", ".bmp", ".svg"));

        if (empty($files)) {
            $this->setAlert(
                _t(
                    "CloudFlare.NoImageFilesFound",
                    "No image files were found."
                ),
                "error"
            );

            return false;
        }

        $data = array(
            "files" => array()
        );

        foreach ($files as $file) {
            $data[ 'files' ][] = $this->convertToAbsolute($file);
        }

        $result   = $this->purgeRequest($data);
        $response = (is_array($result)) ? $result : json_decode($result, true);

        return $this->responseHandler(
            $response,
            _t(
                "CloudFlare.SuccessPurgedImages",
                "Successfully purged {file_count} image files from cache.",
                "",
                array(
                    'file_count' => count($data[ 'files' ])
                )
            )
        );

    }

    /**
     * Gathers the current server name, which will be used as the CloudFlare zone ID
     *
     * @return string
     */
    public function getServerName()
    {
        $replaceWith = array(
            'www.'     => '',
            'http://'  => '',
            'https://' => ''
        );

        $server = \Convert::raw2xml($_SERVER); // "Fixes" #1
        $serverName = str_replace(array_keys($replaceWith), array_values($replaceWith), $server['SERVER_NAME']);

        // CI support
        if (getenv('TRAVIS')) {
            $serverName = getenv('CLOUDFLARE_DUMMY_SITE');
        }

        // Allow extensions to modify or replace the server name if required
        $this->extend('updateCloudFlareServerName', $serverName);

        return $serverName;
    }

    /**
     * Gets the CF Zone ID for the current domain.
     *
     * @return string|bool
     */
    public function fetchZoneID()
    {
        $factory = \SS_Cache::factory("CloudFlare");

        if ($cache = $factory->load(self::CF_ZONE_ID_CACHE_KEY)) {
            $this->isReady(true);

            return $cache;
        }

        $serverName = $this->getServerName();

        if ($serverName == 'localhost') {
            $this->setAlert(
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

        if (!is_array($array) || !array_key_exists("result", $array) || empty($array[ 'result' ])) {
            $this->isReady(false);
            $this->setAlert(
                _t(
                    "CloudFlare.ZoneIdNotFound",
                    "Unable to detect a Zone ID for <strong>{$serverName}</strong> under the defined CloudFlare" .
                    " user.<br/><br/>Please create a new zone under this account to use this module on this domain."
                ),
                "error"
            );

            return false;
        }

        $zoneID = $array[ 'result' ][ 0 ][ 'id' ];

        $factory->save($zoneID, self::CF_ZONE_ID_CACHE_KEY);

        $this->isReady(true);

        return $zoneID;

    }

    /**
     * Handles requests for cache purging
     *
     * @param array|null $data
     * @param string     $method
     *
     * @param null       $isRecursing
     *
     * @return mixed
     */
    public function purgeRequest(array $data = null, $isRecursing = null, $method = 'DELETE')
    {
        if (array_key_exists('files', $data) && !$isRecursing) {
            // get URL variants
            $data[ 'files' ] = $this->getUrlVariants($data[ 'files' ]);
        }

        if (array_key_exists('files', $data) && count($data[ 'files' ]) > 500) {
            // slice the array into chunks of 500 then recursively call this function.
            // cloudflare limits cache purging to 500 files per request.
            $chunks    = ceil(count($data[ 'files' ]) / 500);
            $start     = 0;
            $responses = array();

            for ($i = 0; $i < $chunks; $i++) {
                $chunk       = array_slice($data[ 'files' ], $start, 500);
                $result      = $this->purgeRequest(array('files' => $chunk), true);
                $responses[] = json_decode($result, true);
                $start += 500;
            }

            return $responses;
        }

        $url = str_replace(
            ":identifier",
            $this->fetchZoneID(),
            "https://api.cloudflare.com/client/v4/zones/:identifier/purge_cache"
        );

        return $this->curlRequest($url, $data, $method);
    }

    /**
     * Recursive Glob Function
     *
     * @param     $pattern
     * @param int $flags
     *
     * @return array
     */
    private function rglob($pattern, $flags = 0)
    {
        $files = glob($pattern, $flags);
        foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
            $files = array_merge($files, $this->rglob($dir . '/' . basename($pattern), $flags));
        }

        return $files;
    }

    /**
     * Converts /public_html/path/to/file.ext to example.com/path/to/file.ext
     *
     * @param string|array $files
     *
     * @return mixed
     */
    public static function convertToAbsolute($files)
    {
        $baseUrl = rtrim(Director::absoluteBaseURL(), "/");

        if (is_array($files)) {
            foreach ($files as $index => $file) {
                $basename        = basename($file);
                $basenameEncoded = urlencode($basename);
                $file            = str_replace($basename, $basenameEncoded, $file);

                $files[ $index ] = str_replace($_SERVER[ 'DOCUMENT_ROOT' ], $baseUrl, $file);
            }
        }

        if (is_string($files)) {
            $basename        = basename($files);
            $basenameEncoded = urlencode($basename);
            $files           = str_replace($basename, $basenameEncoded, $files);

            return str_replace($_SERVER[ 'DOCUMENT_ROOT' ], $baseUrl, $files);
        }

        return false;
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
     *
     * @return array
     */
    public function findFilesWithExts($extensions)
    {
        $files = array();

        if (is_array($extensions)) {
            foreach ($extensions as $ext) {
                $files = array_merge($this->rglob($_SERVER[ 'DOCUMENT_ROOT' ] . "/*{$ext}"), $files);
            }
        }

        if (is_string($extensions)) {
            $files = $this->rglob($_SERVER[ 'DOCUMENT_ROOT' ] . "/*{$extensions}");
        }

        return $files;
    }

    /**
     * Converts links to there "Stage" or "Live" counterpart
     *
     * @param array  $urls
     *
     * @return array
     */
    protected function getStageUrls(array $urls = array())
    {
        foreach ($urls as &$url) {
            $parts = parse_url($url);
            if (isset($parts[ 'query' ])) {
                parse_str($parts[ 'query' ], $params);
            }

            $params = (isset($parts[ 'query' ]) && isset($params)) ? $params : array();


            $url = $url . ((strstr($url, "?")) ? "&stage=Stage" : "?stage=Stage");
        }

        return $urls;
    }

    /**
     * Generates URL variants (Stage urls, HTTPS, Non-HTTPS)
     *
     * @param $urls
     *
     * @return array
     */
    public function getUrlVariants($urls)
    {
        $output = array();

        foreach ($urls as $url) {
            $output[] = $url;

            // HTTPS Equiv
            if (strstr($url, "http://") && !in_array(str_replace("http://", "https://", $url), $output)) {
                $output[] = str_replace("http://", "https://", $url);
            }

            // HTTP Equiv
            if (strstr($url, "https://") && !in_array(str_replace("https://", "http://", $url), $output)) {
                $output[] = str_replace("http://", "https://", $url);
            }
        }

        $stage = $this->getStageUrls($output);
        $urls  = array_merge($output, $stage);

        return $urls;
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
     * Sets an Alert that will display on the CloudFlare LeftAndMain
     *
     * @param        $message
     * @param string $type
     */
    public function setAlert($message, $type = 'success')
    {
        $jar = $this->getSessionJar();

        $jar[ 'CFMessage' ] = $message;
        $jar[ 'CFType' ]    = $type;

        $this->setSessionJar($jar);
    }

    /**
     * Handles/delegates all responses from CloudFlare
     *
     * @param      $response
     * @param null $successMsg
     * @param null $errorMsg
     *
     * @return bool
     */
    public function responseHandler($response, $successMsg = null, $errorMsg = null)
    {
        if (is_string($response) && !is_array($response = json_decode($response, true))) {
            // throw error, $response is a string but not JSON
            if ($this->config()->log_errors) {
                error_log("silverstripe-cloudflare: The response received from CloudFlare is malformed. Response was: " . print_r($response,
                        true));
            }

            return false;
        }

        if (isset($response[ 0 ])) {
            return $this->multiResponseHandler($response, $successMsg, $errorMsg);
        }

        if ($response[ 'success' ]) {

            if ($successMsg) {
                $this->setToast($successMsg)->setAlert($successMsg);
            }

            return true;
        }

        $this->setToast($errorMsg ?: 'CloudFlare: The API responded with an error. See PHP error log for more information');
        $this->genericErrorHandler($response);

        return false;
    }

    /**
     * Handles/delegates multiple responses
     *
     * @param array|string $responses
     * @param null         $successMsg
     * @param null         $errorMsg
     *
     * @return bool
     */
    public function multiResponseHandler($responses, $successMsg = null, $errorMsg = null)
    {
        if (is_string($responses) && !is_object($responses = json_decode($responses, true))) {
            // throw error, $response is a string but not JSON
            if ($this->config()->log_errors) {
                error_log("silverstripe-cloudflare: The response received from CloudFlare is malformed. Response was: " . print_r($responses,
                        true));
            }

            return false;
        }

        if (isset($responses[ 0 ])) {
            // request was split and has multiple responses. Ensure that ALL responses were successful.
            // break on the first failure
            foreach ($responses as $response) {
                if (!is_array($response) || !array_key_exists('success', $response) || !$response[ 'success' ]) {
                    $this->setAlert(
                        _t(
                            "CloudFlare.InvalidAPIResponse",
                            "We didn't get a valid response from CloudFlare. Assume that files still require purging"
                        ),
                        "error"
                    );

                    $this->setToast($errorMsg);
                    $this->genericErrorHandler($response);

                    return false;
                }
            }
        }

        $this->setAlert($successMsg);

        return true;
    }

    /**
     * Sets the X-Status header which creates the toast-like popout notification
     *
     * @param $message
     *
     * @return $this
     */
    public function setToast($message)
    {
        Controller::curr()->response->addHeader('X-Status', rawurlencode('CloudFlare: ' . $message));

        return $this;
    }

    /**
     * This just bloats the code alot so I made a function for it to DRY it up
     *
     * @param $response
     */
    private function genericErrorHandler($response)
    {
        if ($this->config()->log_errors) {
            error_log(
                _t(
                    "CloudFlare.GenericErrorForLog",
                    "silverstripe-cloudflare: An error occurred:\n\n " . print_r($response, true)
                )
            );
        }
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
        if (getenv('TRAVIS')) {
            $auth = array(
                'email' => getenv('AUTH_EMAIL'),
                'key'   => getenv('AUTH_KEY'),
            );
        } elseif (!$auth = $this->getCFCredentials()) {
            user_error("CloudFlare API credentials have not been provided.");
            die();
        }

        $headers = array(
            "X-Auth-Email: {$auth['email']}",
            "X-Auth-Key: {$auth['key']}",
            "Content-Type: application/json"
        );

        $userAgent = "Mozilla/5.0 " .
            "(Macintosh; Intel Mac OS X 10_11_6) " .
            "AppleWebKit/537.36 (KHTML, like Gecko) " .
            "Chrome/53.0.2785.143 Safari/537.36";

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        // This is intended, and was/is required by CloudFlare at one point
        curl_setopt($curl, CURLOPT_USERAGENT, $userAgent);

        if (!is_null($data)) {
            if (is_array($data)) {
                $data = json_encode($data);
            }
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }

        $result = curl_exec($curl);
        curl_close($curl);

        return $result;
    }
}
