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
            Controller::curr()->response->addHeader('X-Status', rawurlencode('CloudFlare cache has been purged for: /' . $original->URLSegment) . '/');
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
            Controller::curr()->response->addHeader('X-Status', rawurlencode('CloudFlare cache has been purged for: /' . $this->owner->URLSegment) . '/');
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
            return $config = Config::inst()->get("CloudFlare", "auth");
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

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.cloudflare.com/client/v4/zones/{$zoneId}/purge_cache");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
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

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $result = curl_exec($ch);
        curl_close($ch);

        // todo act on $result - it's wrong to assume success
        return TRUE;
    }

    /**
     * Gets the CF Zone ID for our domain.
     *
     * @return string|bool
     */
    public function fetchZoneID()
    {
        $replaceWith = array(
            "www."     => "",
            "http://"  => "",
            "https://" => ""
        );

        $serverName = str_replace(array_keys($replaceWith), array_values($replaceWith), $_SERVER[ 'SERVER_NAME' ]);

        if ($serverName == 'localhost') {
            return FALSE;
        }

        $auth = $this->getCFCredentials();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.cloudflare.com/client/v4/zones?name={$serverName}&status=active&page=1&per_page=20&order=status&direction=desc&match=all");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $headers = array(
            "X-Auth-Email: {$auth['email']}",
            "X-Auth-Key: {$auth['key']}"
        );

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        curl_close($ch);

        $array = json_decode($result, TRUE);

        if (!array_key_exists("result", $array)) {
            return FALSE;
        }

        return $array[ 'result' ][ 0 ][ 'id' ];

    }

}