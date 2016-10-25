<?php

class CloudFlare
{

    const CF_ZONE_ID_CACHE_KEY = 'CFZoneID';

    /**
     * This will toggle to TRUE when a ZoneID has been detected thus allowing the functionality in the admin panel to be available.
     *
     * @var bool
     */
    protected static $ready = false;

    /**
     * Ensures that CloudFlare authentication credentials are set in code/_config/cloudflare.yml
     *
     * @return bool
     */
    public static function hasCFCredentials()
    {
        $config = Config::inst()->get("CloudFlare", "auth");

        if (!isset($config[ 'email' ]) || !isset($config[ 'key' ])) {
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Fetches CloudFlare Credentials from YML configuration
     *
     * @return array|bool
     */
    public static function getCFCredentials()
    {
        if (static::hasCFCredentials()) {
            return Config::inst()->get("CloudFlare", "auth");
        }

        return FALSE;
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
    public static function purgeSingle($fileOrUrl)
    {
        // fetch zone ID dynamically
        $zoneId = static::fetchZoneID();

        if (!$zoneId) {
            error_log("CloudFlareExt: Attempted to purge cache for {$fileOrUrl} but unable to find Zone ID");

            return FALSE;
        }

        $baseUrl = Director::absoluteBaseURL();

        $purgeUrl = $baseUrl . $fileOrUrl;

        $data = json_encode(
            array(
                "files" => array(
                    $purgeUrl
                )
            )
        );

        $result = static::purgeRequest($data);

        if (!is_object($result = json_decode($result))) {
            // a non-JSON string was returned?
            Controller::curr()->response->addHeader('X-Status', rawurlencode('CloudFlare: The response received from CloudFlare is malformed. See PHP error log for more information'));
            error_log("CloudFlare: The response received from CloudFlare is malformed. Response was: " . print_r($result, TRUE));

            return FALSE;
        }

        if ($result->success) {
            Controller::curr()->response->addHeader('X-Status', rawurlencode('CloudFlare cache has been purged for: /' . $fileOrUrl . '/'));

            return TRUE;
        }

        Controller::curr()->response->addHeader('X-Status', rawurlencode('CloudFlare: The API responded with an error. See PHP error log for more information'));
        error_log("CloudFlare: The response received from CloudFlare is malformed. Response was: " . print_r($result, TRUE));

        return TRUE;
    }

    /**
     * Purges everything that CloudFlare has cached
     *
     * @return mixed
     */
    public static function purgeAll()
    {
        $data = array(
            "purge_everything" => TRUE
        );

        $response = json_decode(static::purgeRequest($data), TRUE);

        if (!is_array($response) || !array_key_exists('success', $response) || !$response[ 'success' ]) {
            static::setAlert("We didn't get a valid response from CloudFlare. Assume that files still require purging", "error");
            error_log("CloudFlare ran into an error:\n\n " . print_r($response, TRUE));

            return FALSE;
        }

        static::setAlert("Successfully purged <strong>EVERYTHING</strong> from cache.");

        return TRUE;
    }

    /**
     * Finds all .CSS files recursively from root directory, and purges them from cache in a single request
     *
     * @return bool
     */
    public static function purgeCss()
    {
        $files = static::findFilesWithExts(array( ".css", ".css.map" )); // map here for the SASS/LESS enthusiasts

        if (empty($files)) {
            static::setAlert("No CSS files were found.", "error");

            return FALSE;
        }

        $data = array(
            "files" => array()
        );

        foreach ($files as $file) {
            $data[ 'files' ][] = static::convert2absolute($file);
        }

        $result   = static::purgeRequest($data);
        $response = (is_array($result)) ? $result : json_decode($result, TRUE);

        // Really need to DRY this up.
        if (array_key_exists('0', $response)) {
            // request was split and has multiple responses. Ensure that ALL responses were successful.
            // break on the first failure
            foreach ($response as $resp) {
                if (!is_array($resp) || !array_key_exists('success', $resp) || !$resp[ 'success' ]) {
                    static::setAlert("We didn't get a valid response from CloudFlare. Assume that files still require purging", "error");
                    error_log("CloudFlare ran into an error:\n\n " . print_r($resp, TRUE));

                    return FALSE;
                }
            }
        }
        elseif (!is_array($response) || !array_key_exists('success', $response) || !$response[ 'success' ]) {
            static::setAlert("We didn't get a valid response from CloudFlare. Assume that files still require purging", "error");
            error_log("CloudFlare ran into an error:\n\n " . print_r($response, TRUE));

            return FALSE;
        }

        static::setAlert("Successfully purged " . count($data[ 'files' ]) . " CSS files from cache.");

        return TRUE;
    }

    /**
     * Finds all .JS files recursively from root directory, and purges them from cache in a single request
     *
     * @return bool
     */
    public static function purgeJs()
    {
        $files = static::findFilesWithExts(".js");

        if (empty($files)) {
            static::setAlert("No JS files were found.", "error");

            return FALSE;
        }

        $data = array(
            "files" => array()
        );

        foreach ($files as $file) {
            $data[ 'files' ][] = static::convert2absolute($file);
        }

        $result   = static::purgeRequest($data);
        $response = (is_array($result)) ? $result : json_decode($result, TRUE);

        // Really need to DRY this up.
        if (array_key_exists('0', $response)) {
            // request was split and has multiple responses. Ensure that ALL responses were successful.
            // break on the first failure
            foreach ($response as $resp) {
                if (!is_array($resp) || !array_key_exists('success', $resp) || !$resp[ 'success' ]) {
                    static::setAlert("We didn't get a valid response from CloudFlare. Assume that files still require purging", "error");
                    error_log("CloudFlare ran into an error:\n\n " . print_r($resp, TRUE));

                    return FALSE;
                }
            }
        }
        elseif (!is_array($response) || !array_key_exists('success', $response) || !$response[ 'success' ]) {
            static::setAlert("We didn't get a valid response from CloudFlare. Assume that files still require purging", "error");
            error_log("CloudFlare ran into an error:\n\n " . print_r($response, TRUE));

            return FALSE;
        }

        static::setAlert("Successfully purged " . count($data[ 'files' ]) . " JS files from cache.");

        return TRUE;
    }

    /**
     * Finds all image files recursively from root directory, and purges them from cache in a single request
     *
     * @return bool
     */
    public static function purgeImages()
    {
        $files = static::findFilesWithExts(array( ".jpg", ".jpeg", ".gif", ".png", ".ico", ".bmp", ".svg" ));

        if (empty($files)) {
            static::setAlert("No image files were found.", "error");

            return FALSE;
        }

        $data = array(
            "files" => array()
        );

        foreach ($files as $file) {
            $data[ 'files' ][] = static::convert2absolute($file);
        }

        $result   = static::purgeRequest($data);
        $response = (is_array($result)) ? $result : json_decode($result, TRUE);

        // Really need to DRY this up.
        if (array_key_exists('0', $response)) {
            // request was split and has multiple responses. Ensure that ALL responses were successful.
            // break on the first failure
            foreach ($response as $resp) {
                if (!is_array($resp) || !array_key_exists('success', $resp) || !$resp[ 'success' ]) {
                    static::setAlert("We didn't get a valid response from CloudFlare. Assume that files still require purging", "error");
                    error_log("CloudFlare ran into an error:\n\n " . print_r($resp, TRUE));

                    return FALSE;
                }
            }
        }
        elseif (!is_array($response) || !array_key_exists('success', $response) || !$response[ 'success' ]) {
            static::setAlert("We didn't get a valid response from CloudFlare. Assume that files still require purging", "error");
            error_log("CloudFlare ran into an error:\n\n " . print_r($response, TRUE));

            return FALSE;
        }

        static::setAlert("Successfully purged " . count($data[ 'files' ]) . " image files from cache.");

        return TRUE;
    }

    /**
     * Gets the CF Zone ID for the current domain.
     *
     * @return string|bool
     */
    public static function fetchZoneID()
    {
        if (!$auth = static::getCFCredentials()) {
            user_error("CloudFlare API credentials have not been provided.");
        }

        $factory = \SS_Cache::factory("CloudFlare");

        if ($cache = $factory->load(self::CF_ZONE_ID_CACHE_KEY)) {
            return $cache;
        }

        $replaceWith = array(
            "www."     => "",
            "http://"  => "",
            "https://" => ""
        );

        $server = Convert::raw2xml($_SERVER); // "Fixes" #1

        $serverName = str_replace(array_keys($replaceWith), array_values($replaceWith), $server[ 'SERVER_NAME' ]);

        if ($serverName == 'localhost') {
            static::setAlert("This module does not operate under <strong>localhost</strong>. Please ensure your website has a resolvable DNS and access the website via the domain.", "error");
            return FALSE;
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://api.cloudflare.com/client/v4/zones?name={$serverName}&status=active&page=1&per_page=20&order=status&direction=desc&match=all");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $headers = array(
            "X-Auth-Email: {$auth['email']}",
            "X-Auth-Key: {$auth['key']}",
            "Content-Type: application/json"
        );

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        // See comment regarding faking UserAgent in CloudFlare::purgeRequest()
        curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.143 Safari/537.36");

        $result = curl_exec($curl);
        curl_close($curl);

        $array = json_decode($result, TRUE);

        if (!array_key_exists("result", $array) || empty($array['result'])) {
            static::isReady(false);
            static::setAlert("Unable to detect a Zone ID for <strong>{$serverName}</strong> under the user <strong>{$auth['email']}</strong>.<br/><br/>Please create a new zone under this account to use this module on this domain.", "error");
            return FALSE;
        }

        $zoneID = $array[ 'result' ][ 0 ][ 'id' ];

        $factory->save($zoneID, self::CF_ZONE_ID_CACHE_KEY);

        static::isReady(true);

        return $zoneID;

    }

    /**
     * DRY helper for Purging Cache
     *
     * @param array|NULL $data
     * @param string     $method
     *
     * @return mixed
     */
    public static function purgeRequest(array $data = NULL, $method = 'DELETE')
    {
        if (array_key_exists('files', $data) && count($data[ 'files' ]) > 500) {
            // slice the array into chunks of 500 then recursively call this function.
            // cloudflare limits cache purging to 500 files per request.
            $chunks = ceil(count($data[ 'files' ]) / 500);
            $start  = 0;

            $responses = array();

            for ($i = 0; $i < $chunks; $i++) {
                $chunk       = array_slice($data[ 'files' ], $start, 500);
                $result      = static::purgeRequest(array( 'files' => $chunk ));
                $responses[] = json_decode($result, TRUE);
                $start += 500;
            }

            return $responses;
        }

        $zoneId = static::fetchZoneID();
        $auth   = static::getCFCredentials();

        $url = str_replace(":identifier", $zoneId, "https://api.cloudflare.com/client/v4/zones/:identifier/purge_cache");

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $headers = array(
            "X-Auth-Email: {$auth['email']}",
            "X-Auth-Key: {$auth['key']}",
            "Content-Type: application/json"
        );

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);

        if (!is_null($data)) {
            if (is_array($data)) {
                $data = json_encode($data);
            }

            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        // We have no remorse in faking a UserAgent here as CloudFlare API now actually requires you to.
        //
        // Support Msg from CloudFlare Rep (John Roberts):
        //      Last week, as part of some anti-abuse measures, we started blocking requests with no user-agent.
        //      Apologies for the side effect, and glad you sorted it quickly.
        //      We have other measures in place; this was simply an expedient one, since it took advantage of an existing WAF rule.
        curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.143 Safari/537.36");

        $result = curl_exec($curl);
        curl_close($curl);

        return $result;
    }

    /**
     * Recursive Glob Function
     *
     * @param     $pattern
     * @param int $flags
     *
     * @return array
     */
    public static function rglob($pattern, $flags = 0)
    {
        $files = glob($pattern, $flags);
        foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
            $files = array_merge($files, static::rglob($dir . '/' . basename($pattern), $flags));
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
    public static function convert2absolute($files)
    {

        $baseUrl = rtrim(Director::absoluteBaseURL(), "/");

        if (is_array($files)) {
            foreach ($files as $index => $file) {
                $basename         = basename($file);
                $basename_encoded = urlencode($basename);
                $file             = str_replace($basename, $basename_encoded, $file);

                $files[ $index ] = str_replace($_SERVER[ 'DOCUMENT_ROOT' ], $baseUrl, $file);
            }
        }

        if (is_string($files)) {
            $basename         = basename($files);
            $basename_encoded = urlencode($basename);
            $files            = str_replace($basename, $basename_encoded, $files);

            return str_replace($_SERVER[ 'DOCUMENT_ROOT' ], $baseUrl, $files);
        }

        return FALSE;
    }

    /**
     * Set or get the ready state
     *
     * @param null $state
     *
     * @return bool|null
     */
    public function isReady($state = null) {
        if ($state) {
            self::$ready = (bool)$state;

            return $state;
        }

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
                $files = array_merge(static::rglob($_SERVER[ 'DOCUMENT_ROOT' ] . "/*{$ext}"), $files);
            }
        }

        if (is_string($extensions)) {
            $files = static::rglob($_SERVER[ 'DOCUMENT_ROOT' ] . "/*{$extensions}");
        }

        return $files;
    }

    /**
     * Get or Set the Session Jar
     *
     * @param bool $data
     *
     * @return array|mixed|null|\Session
     */
    public static function sessionJar($data = NULL)
    {
        $session = \Session::get('slCloudFlare') ?: (\Session::set('slCloudFlare', array())) ?: \Session::get('slCloudFlare');
        if (!$data) {
            return $session;
        }

        \Session::set('slCloudFlare', $data);

        return static::sessionJar();
    }

    /**
     * Sets an Alert that will display on the CloudFlare LeftAndMain
     *
     * @param        $message
     * @param string $type
     */
    public static function setAlert($message, $type = 'success')
    {
        $jar = static::sessionJar();

        $jar[ 'CFAlert' ]   = TRUE;
        $jar[ 'CFMessage' ] = $message;
        $jar[ 'CFType' ]    = $type;

        static::sessionJar($jar);
    }
}