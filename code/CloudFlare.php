<?php
namespace Steadlane\CloudFlare;

use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Object;
use SilverStripe\Control\Session;
use SilverStripe\Core\Cache;
use Steadlane\CloudFlare\Messages\Notifications;

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
     * Gathers the current server name, which will be used as the CloudFlare zone ID
     *
     * @return string
     */
    public function getServerName()
    {
        $serverName = '';
        if (!empty($_SERVER['SERVER_NAME'])) {
            $server = Convert::raw2xml($_SERVER); // "Fixes" #1
            $serverName = $server['SERVER_NAME'];
        }

        // CI support
        if (getenv('TRAVIS')) {
            $serverName = "example.com";
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
            $factory = Cache::factory("CloudFlare");

            if ($cache = $factory->load(self::CF_ZONE_ID_CACHE_KEY)) {
                $this->isReady(true);

                return $cache;
            }
        }

        $serverName = $this->getServerName();

        if ($serverName == 'localhost') {
            Notifications::handleMessage(
                _t(
                    "CloudFlare.NoLocalhost",
                    "This module does not operate under localhost." .
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
            Notifications::handleMessage(
                _t(
                    "CloudFlare.ZoneIdNotFound",
                    "Unable to detect a Zone ID for {server_name} under the defined CloudFlare" .
                    " user. Please create a new zone under this account to use this module on this domain.",
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
     * Get or Set the Session Jar
     *
     * @return array|mixed|null|Session
     */
    public function getSessionJar()
    {
        $session = Session::get('slCloudFlare') ?: (Session::set('slCloudFlare', array())) ?: Session::get('slCloudFlare');

        return $session;
    }

    /**
     * @param $data
     *
     * @return $this
     */
    public function setSessionJar($data)
    {
        Session::set('slCloudFlare', $data);

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
     * Fetch the CloudFlare configuration
     * @return \SilverStripe\Core\Config\Config_ForClass
     */
    public static function config()
    {
        return Config::inst()->forClass('CloudFlare');
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
                'email' => 'person@example.com',
                'key' => 'MY_SECRET_AUTH_KEY',
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

    /**
     * Appends server name to input
     *
     * @param array|string $input
     *
     * @return array|string
     */
    public function prependServerName($input)
    {
        $serverName = CloudFlare::singleton()->getServerName();

        if (is_array($input)) {

            $stack = array();
            foreach ($input as $string) {
                $stack[] = $this->prependServerName($string);
            }

            return $stack;
        }


        if (strstr($input, "http://") || strstr($input, "https://")) {
            $input = str_replace(array("http://", "https://"), "", trim($input));
        }

        if (strstr($input, $serverName)) {
            return "http://" . $input;
        }

        return "http://" . str_replace("//", "/", "{$serverName}/{$input}");
    }

    /**
     * @param string $type         Class you want to test, this is purely based on the file naming convention
     *                             in /tests/Mock of {$type}{$isSuccessful}.json
     * @param bool   $isSuccessful Should the response be of successful nature or a failure?
     *
     * @return array
     */
    public static function getMockResponse($type, $isSuccessful) {
        $mockDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . Director::baseURL() . "cloudflare/tests/Mock/";

        if (getenv('TRAVIS')) {
            $mockDir = '/home/travis/builds/ss' . $mockDir;
        }

        if (!is_dir($mockDir)) {
            user_error("The directory $mockDir needs to exist to get mock responses from the CloudFlare module", E_USER_ERROR);
        }

        $filename = ucfirst($type) . (($isSuccessful) ? "Success" : "Failure") . ".json";
        $path = $mockDir . $filename;

        $result = json_decode(file_get_contents($path), true);

        if (!$result || !is_array($result)) {
            user_error($filename . " contents must be a valid JSON string", E_USER_ERROR);
        }

        return $result;
    }
}
