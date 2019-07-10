<?php

class CloudFlare_Purge extends SS_Object
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
     * @var array
     */
    protected $fileTypes = array(
        'image' => array(
            "bmp" ,"gif" ,"jpg" ,"jpeg" ,"pcx" ,"tif" ,"png" ,"alpha","als" ,"cel" ,"icon" ,"ico" ,"ps", "svg"
        ),
        'javascript' => array(
            "js"
        ),
        'css' => array(
            'css', 'cssmap'
        )
    );

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
        $this->pushFile($files);

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
            foreach ($file as $pointer) {
                $this->pushFile($pointer);
            }

            return $this;
        }

        array_push($this->files, $this->convertToAbsolute($file));

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
                $files = array_merge($this->rglob(rtrim($rootDir, "/") . "/*.{$ext}"), $files);
            }
        }

        if (is_string($extensions)) {
            $files = $this->rglob(rtrim($rootDir, "/") . "/*{$extensions}");
        }

        $this->pushFile($files);

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
            if (basename($dir) == 'framework') {
                continue;
            }
            // if we implode the array we should be able to search for all extensions at once like below
            // http://stackoverflow.com/a/23969253/2266583 w/ GLOB_BRACE
            // however it would always return no files for me, so i'll just leave it here for now
            // $files = array_merge($this->rglob(rtrim($rootDir, "/") . '/*.{'.$extensions.'}'), $files);
            ////////////////////////////////////////
            $files = array_merge($files, $this->rglob($dir . '/' . basename($pattern), $flags));
        }

        return $files;
    }

    /**
     * Converts /public_html/path/to/file.ext to example.com/path/to/file.ext, it is perfectly safe to hand this
     * an "already absolute" url.
     *
     * @param string|array $files
     *
     * @return string|array|bool Dependent on input, returns false if input is neither an array, or a string.
     */
    public function convertToAbsolute($files)
    {
        // It's not the best feeling to have to add http:// here, despite it's SSL variant being picked up
        // by getUrlVariants(). However without it cloudflare will respond with an error similar to:
        // "You may only purge files for this zone only"
        $baseUrl = "http://" . CloudFlare::singleton()->getServerName() . "/";
        $rootDir = str_replace("//", "/", $_SERVER['DOCUMENT_ROOT']);

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
     * @return $this
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

        CloudFlare_Notifications::handleMessage(
            ($success) ? ($this->getSuccessMessage() ?: false) : ($this->getFailureMessage() ?: false),
            array(
                'file_count' => $this->count()
            )
        );

        return $this;
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
     *
     * @return $this
     */
    public function setResponse($response)
    {
        $this->extend("onBeforeSetResponse", $response);

        $this->response = $response;

        return $this;
    }

    /**
     * Handles requests for cache purging
     *
     * @param array|null $data
     * @param string $method
     *
     * @param bool $isRecursing
     * @return string|array
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
     * @return string
     */
    public function getEndpoint()
    {
        $zoneId = CloudFlare::singleton()->fetchZoneID();
        return str_replace(":identifier", $zoneId, static::$endpoint);
    }

    /**
     * @return bool
     */
    public function isSuccessful()
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
     * @return array
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
     * @param bool $bool
     * @return $this
     */
    public function setPurgeEverything($bool = null)
    {
        $this->purgeEverything = ($bool);
        return $this;
    }


    /**
     * @param string $failureMessage
     * @return $this
     */
    public function setFailureMessage($failureMessage)
    {
        $this->failureMessage = $failureMessage;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getFailureMessage()
    {
        return $this->failureMessage;
    }

    /**
     * @param string $successMessage
     * @return $this
     */
    public function setSuccessMessage($successMessage)
    {
        $this->successMessage = $successMessage;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getSuccessMessage()
    {
        return $this->successMessage;
    }

    /**
     * Should we purge related Pages as well as the Page/file/URL that is requested?
     *
     * @return bool
     */
    public function getShouldPurgeRelations()
    {
        return (bool) CloudFlare::config()->should_purge_relations;
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
     *
     * @return $this
     */
    public function clearFiles()
    {
        $this->files = null;

        return $this;
    }

    /**
     * @return array
     */
    public function getFileTypes() {

        $types = $this->fileTypes;
        $this->extend('updateCloudFlarePurgeFileTypes', $types);

        return $types;
    }

    /**
     * Allows you to quickly purge cache for particular files defined in $fileTypes (See ::getFileTypes() for an
     * extension point to update file types)
     *
     * @param string $what E.g 'image', 'javascript', 'css', or user defined
     *
     * @param null   $other_id Allows you to provide a Page ID for example
     *
     * @return bool
     */
    public function quick($what, $other_id = null) {
        // create a new instance of self so we don't interrupt anything
        $purger = self::create();
        $what = trim(strtolower($what));

        if ($what == 'page' && isset($other_id)) {
            if (!($other_id instanceof SiteTree)) {
                $other_id = DataObject::get_by_id('SiteTree', $other_id);
            }
            $page = $other_id;

            $purger
                ->pushFile(str_replace("//","/",$_SERVER['DOCUMENT_ROOT'] . "/" .$page->Link()))
                ->setSuccessMessage('Cache has been purged for: ' . $page->Link())
                ->purge();

            return $purger->isSuccessful();
        }

        if ($what == 'all') {
            $purger->setPurgeEverything(true)->purge();
            return $purger->isSuccessful();
        }

        $fileTypes = $this->getFileTypes();

        if (!isset($fileTypes[$what])) {
            user_error("Attempted to purge all {$what} types but it has no file extension list defined. See CloudFlare_Purge::\$fileTypes", E_USER_ERROR);
        }

        $purger->findFilesWithExts($fileTypes[$what]);

        if (!$purger->count()) {
            CloudFlare_Notifications::handleMessage(
                _t(
                    "CloudFlare.NoFilesToPurge",
                    "No {what} files were found to purge.",
                    "",
                    array(
                        "what" => $what
                    )
                )
            );
        } else {
            $purger->setSuccessMessage(
                _t(
                    "CloudFlare.SuccessFilesPurged",
                    "Successfully purged {file_count} {what} files from cache.",
                    "",
                    array(
                        "what" => $what
                    )
                )
            );

            $purger->purge();
        }

        return $purger->isSuccessful();
    }

}
