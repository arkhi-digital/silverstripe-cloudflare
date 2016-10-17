<?php

/**
 * Class CloudFlareExt
 */
class CloudFlareExt extends SiteTreeExtension
{

    /**
     * Extension Hook
     *
     * @param \SiteTree $original
     */
    public function onAfterPublish(&$original)
    {
        // if the page was just created, then there is no cache to purge and $original doesn't actually exist so bail out - resolves #3
        // we don't purge anything if we're operating on localhost
        if ($this->hasCFCredentials() && strlen($original->URLSegment)) {
            $this->purgeCacheFor($original->URLSegment);
        }

        parent::onAfterPublish($original);
    }

    /**
     * We purge CloudFlare cache for files that were removed from published state so that they no longer appear for the
     * user should cache have not expired yet.
     */
    public function onAfterUnpublish()
    {
        if ($this->hasCFCredentials()) {
            $this->purgeCacheFor($this->owner->URLSegment);
        }

        parent::onBeforeUnpublish();
    }


    /**
     * Ensures that CloudFlare authentication credentials are set in code/_config/cloudflare.yml
     *
     * @return bool
     */
    public function hasCFCredentials()
    {
        $config = Config::inst()->get("CloudFlare", "auth");

        if (!isset($config[ 'email' ]) || !isset($config[ 'key' ])) {
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Fetches CloudFlare Credentials
     * @return array|bool
     */
    public function getCFCredentials()
    {
        if ($this->hasCFCredentials()) {
            return Config::inst()->get("CloudFlare", "auth");
        }

        return FALSE;
    }

    /**
     * Purges CloudFlare's cache for URL provided. We currently don't care for the response
     *
     * @note The CloudFlare API sets a maximum of 1,200 requests in a five minute period.
     *
     * @param $url
     *
     * @return bool
     */
    public function purgeCacheFor($url)
    {
        // fetch zone ID dynamically
        $zoneId = $this->fetchZoneID();

        if (!$zoneId) {
            error_log("CloudFlareExt: Attempted to purge cache for {$url} but unable to find Zone ID");

            return FALSE;
        }

        $baseUrl = Director::absoluteBaseURL();

        $purgeUrl = $baseUrl . $url;
        $auth     = $this->getCFCredentials();


        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://api.cloudflare.com/client/v4/zones/{$zoneId}/purge_cache");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $headers = array(
            "X-Auth-Email: {$auth['email']}",
            "X-Auth-Key: {$auth['key']}",
            "Content-Type: application/json"
        );

        $data = json_encode(
            array(
                "files" => array(
                    $purgeUrl
                )
            )
        );

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

        $result = curl_exec($curl);
        curl_close($curl);

        if (!is_object($result = json_decode($result))) {
            // a non-JSON string was returned?
            Controller::curr()->response->addHeader('X-Status', rawurlencode('CloudFlare: The response received from CloudFlare is malformed. See PHP error log for more information'));
            error_log("CloudFlare: The response received from CloudFlare is malformed. Response was: " . print_r($result, true));
            return FALSE;
        }

        if ($result->success) {
            Controller::curr()->response->addHeader('X-Status', rawurlencode('CloudFlare cache has been purged for: /' . $url . '/'));
            return TRUE;
        }

        Controller::curr()->response->addHeader('X-Status', rawurlencode('CloudFlare: The API responded with an error. See PHP error log for more information'));
        error_log("CloudFlare: The response received from CloudFlare is malformed. Response was: " . print_r($result, true));
        return TRUE;
    }

    /**
     * Gets the CF Zone ID for our domain.
     *
     * @return string|bool
     */
    public function fetchZoneID()
    {
        if (!$auth = $this->getCFCredentials()) {
            user_error("CloudFlare API credentials have not been provided.");
        }

        $replaceWith = array(
            "www."     => "",
            "http://"  => "",
            "https://" => ""
        );

        $server = Convert::raw2xml($_SERVER); // "Fixes" #1

        $serverName = str_replace(array_keys($replaceWith), array_values($replaceWith), $server[ 'SERVER_NAME' ]);

        if ($serverName == 'localhost') {
            return FALSE;
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://api.cloudflare.com/client/v4/zones?name={$serverName}&status=active&page=1&per_page=20&order=status&direction=desc&match=all");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $headers = array(
            "X-Auth-Email: {$auth['email']}",
            "X-Auth-Key: {$auth['key']}"
        );

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        // I didn't like the idea of having to fake a User Agent, but without it; Cloud Flare's WAF will block you.
        // I'm not exactly sure why it expects an API request to come from a browser.
        curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.143 Safari/537.36");

        $result = curl_exec($curl);
        curl_close($curl);

        $array = json_decode($result, TRUE);

        if (!array_key_exists("result", $array)) {
            return FALSE;
        }

        return $array[ 'result' ][ 0 ][ 'id' ];

    }

}