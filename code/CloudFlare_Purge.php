<?php

class CloudFlare_Purge extends Object
{

    /**
     * @var
     */
    protected $successMessage;

    /**
     * @var
     */
    protected $failureMessage;

    /**
     * @var array
     */
    protected $files;

    /**
     * @var bool
     */
    protected $purgeEverything = false;

    /**
     * @var
     */
    protected $response;

    /**
     * @var string
     */
    protected static $endpoint = "https://api.cloudflare.com/client/v4/zones/:identifier/purge_cache";

    /**
     * @param array $files
     * @return $this
     */
    public function setFiles(array $files)
    {
        $this->clearFiles();
        $this->pushFiles($files);

        return $this;
    }

    /**
     * @param string $file
     * @return $this
     */
    public function pushFile($file)
    {
        if (!is_array($this->files)) {
            $this->files = array();
        }

        if (is_array($file)) {
            return $this->pushFiles($file);
        }

        array_push($this->files, $this->convertToAbsolute($file));

        return $this;
    }

    /**
     * @param $files
     * @return CloudFlare_Purge
     */
    public function pushFiles($files)
    {
        if (is_string($files)) {
            return $this->pushFile($files);
        }

        foreach ($files as $file) {
            $this->pushFile($file);
        }

        return $this;
    }

    /**
     * Recursively find files with a specific extension(s) starting at the document root
     *
     * @param string|array $extensions
     *
     * @param null|string $dir A directory relevant to the project root, if null the entire project root will be search
     * @return $this
     */
    public function findFilesWithExts($extensions, $dir = null)
    {
        $files = array();
        $rootDir = str_replace('//', '/', $_SERVER['DOCUMENT_ROOT'] . Director::baseURL() . "/" . $dir);

        if (is_array($extensions)) {
            foreach ($extensions as $ext) {
                $files = array_merge($this->rglob($rootDir . "/*{$ext}"), $files);
            }
        }

        if (is_string($extensions)) {
            $files = $this->rglob($rootDir . "/*{$extensions}");
        }

        $this->pushFiles($this->convertToAbsolute($files));

        return $this;
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
    public function convertToAbsolute($files)
    {
        // It's not the best feeling to have to add http:// here, despite it's SSL variant being picked up
        // by getUrlVariants(). However without it cloudflare will response with an error similiar to:
        // "You may only purge files for this zone only"
        $baseUrl = "http://" . CloudFlare::singleton()->getServerName() . "/";
        $rootDir = $_SERVER['DOCUMENT_ROOT'] . Director::baseURL();

        if (is_array($files)) {
            foreach ($files as $index => $file) {
                $basename = basename($file);
                $basenameEncoded = urlencode($basename);
                $file = str_replace($basename, $basenameEncoded, $file);

                $files[$index] = str_replace($rootDir, $baseUrl, $file);
            }

            return $files;
        }

        if (is_string($files)) {
            $basename = basename($files);
            $basenameEncoded = urlencode($basename);
            $files = str_replace($basename, $basenameEncoded, $files);

            return str_replace($rootDir, $baseUrl, $files);
        }

        return false;
    }

    /**
     * @return int
     */
    public function count()
    {
        return (is_array($this->files)) ? count($this->files) : 0;
    }

    /**
     *
     */
    public function purge()
    {
        $files = $this->getFiles();

        $this->extend("updateFilesBeforePurge", $files);

        if ($this->purgeEverything) {
            $data = array(
                "purge_everything" => true
            );
        } else {
            $data = array(
                "files" => $files
            );
        }

        $this->setResponse($this->handleRequest($data));

        $success = $this->isSuccessful();

        if ($success && $this->getSuccessMessage()) {
            CloudFlare_Notifications::handleMessage(
                $this->getSuccessMessage(),
                array(
                    'file_count' => $this->count()
                )
            );
        }

        if (!$success && $this->getFailureMessage()) {
            CloudFlare_Notifications::handleMessage(
                $this->getFailureMessage(),
                array(
                    'file_count' => $this->count()
                )
            );
        }

        return $success;
    }

    /**
     * @return null|array
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @param $response
     */
    private function setResponse($response)
    {
        $this->extend("onBeforeSetResponse", $response);

        $this->response = $response;
    }

    /**
     * Handles requests for cache purging
     *
     * @param array|null $data
     * @param string $method
     *
     * @param null $isRecursing
     * @return mixed
     */
    public function handleRequest(array $data = null, $isRecursing = null, $method = 'DELETE')
    {
        if (array_key_exists('files', $data) && !$isRecursing) {
            // get URL variants
            $data['files'] = $this->getUrlVariants($data['files']);
        }

        if (array_key_exists('files', $data) && count($data['files']) > 500) {
            // slice the array into chunks of 500 then recursively call this function.
            // cloudflare limits cache purging to 500 files per request.
            $chunks = ceil(count($data['files']) / 500);
            $start = 0;
            $responses = array();

            for ($i = 0; $i < $chunks; $i++) {
                $chunk = array_slice($data['files'], $start, 500);
                $result = $this->handleRequest(array('files' => $chunk), true);
                $responses[] = json_decode($result, true);
                $start += 500;
            }

            return $responses;
        }


        return CloudFlare::singleton()->curlRequest($this->getEndpoint(), $data, $method);
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

        $this->extend("onAfterGetUrlVariants", $output);

        return $output;
    }

    /**
     * @return mixed
     */
    public function getEndpoint()
    {
        $zoneId = CloudFlare::singleton()->fetchZoneID();
        return str_replace(":identifier", $zoneId, static::$endpoint);
    }

    /**
     * @return bool
     */
    protected function isSuccessful()
    {
        $response = $this->getResponse();

        if (!is_array($response)) {
            return false;
        }

        if (array_key_exists("0", $response)) {
            // multiple responses in payload, all of them need to be successful otherwise return false;
            foreach ($response as $singular) {
                if ($singular['success']) {
                    continue;
                }

                return false;
            }

            return true;
        }

        if (array_key_exists('success', $response) && $response['success']) {
            return true;
        }

        return false;

    }

    /**
     * @return mixed
     */
    public function getResponse()
    {
        $response = $this->response;
        if (is_string($response)) {
            $response = json_decode($response, true);
        }

        return $response;
    }

    /**
     * @param null $bool
     * @return $this
     */
    public function purgeEverything($bool = null)
    {
        $this->purgeEverything = ($bool);
        return $this;
    }


    /**
     * @param mixed $failureMessage
     * @return $this
     */
    public function setFailureMessage($failureMessage)
    {
        $this->failureMessage = $failureMessage;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getFailureMessage()
    {
        return $this->failureMessage;
    }

    /**
     * @param mixed $successMessage
     * @return $this
     */
    public function setSuccessMessage($successMessage)
    {
        $this->successMessage = $successMessage;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSuccessMessage()
    {
        return $this->successMessage;
    }

    /**
     * Resets the instance
     *
     * @return $this
     */
    public function reset()
    {
        $this->clearFiles();

        $this->response = null;
        $this->successMessage = null;
        $this->failureMessage = null;
        $this->purgeEverything = false;

        return $this;
    }

    /**
     * Clears files
     */
    public function clearFiles()
    {
        $this->files = null;

        return $this;
    }

}
