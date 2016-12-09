<?php

class CloudFlareAdmin extends LeftAndMain implements PermissionProvider
{
    private static $url_segment = 'cloudflare';
    private static $url_rule = '/$Action/$ID/$OtherID';
    private static $menu_title = 'CloudFlare';
    private static $menu_icon = 'cloudflare/assets/cloudflare.jpg';

    private static $allowed_actions = array(
        'purge_all',
        'purge_css',
        'purge_images',
        'purge_javascript',
    );

    /**
     * @todo Actually implement this
     * @return array
     */
    public function providePermissions()
    {
        return array(
            "PURGE_ALL" => "Purge All Cache",
            "PURGE_CSS" => "Purge CSS Cache",
            "PURGE_JS" => "Purge JS Cache",
            "PURGE_PAGE" => "Purge Page Cache",
        );
    }

    /**
     * Include our CSS
     */
    public function init()
    {
        parent::init();

        Requirements::css('cloudflare/css/cloudflare.css');
    }

    /**
     * @return \SS_HTTPResponse|string
     */
    public function purge_all()
    {
        $purger = CloudFlare_Purge::create();
        $purger
            ->purgeEverything(true)
            ->setSuccessMessage(
                _t(
                    "CloudFlare.PurgedEverything",
                    "Successfully purged <strong>EVERYTHING</strong> from cache."
                )
            )
            ->purge();

        return $this->redirect($this->Link('/'));
    }

    /**
     * @return \SS_HTTPResponse|string
     */
    public function purge_css()
    {
        $purger = CloudFlare_Purge::create();
        $purger
            ->setSuccessMessage(
                _t(
                    "CloudFlare.SuccessPurgedCSS",
                    "Successfully purged {file_count} CSS files from cache."
                )
            )
            ->findFilesWithExts(array(".css", ".css.map"));

        if (!$purger->count()) {
            CloudFlare_Notifications::handleMessage(
                _t(
                    "CloudFlare.NoCSSFilesFound",
                    "No CSS files were found."
                )
            );
        }

        $purger->purge();

        return $this->redirect($this->Link('/'));
    }

    /**
     * @return \SS_HTTPResponse|string
     */
    public function purge_javascript()
    {
        $purger = CloudFlare_Purge::create();
        $purger
            ->setSuccessMessage(
                _t(
                    "CloudFlare.SuccessPurgedJavascript",
                    "Successfully purged {file_count} javascript files from cache."
                )
            )
            ->findFilesWithExts(array(".js"));

        if (!$purger->count()) {
            CloudFlare_Notifications::handleMessage(
                _t(
                    "CloudFlare.NoJavascriptFilesFound",
                    "No javascript files were found."
                )
            );
        }

        $purger->purge();

        return $this->redirect($this->Link('/'));
    }

    /**
     * @return \SS_HTTPResponse|string
     */
    public function purge_images()
    {
        $purger = CloudFlare_Purge::create();
        $purger
            ->setSuccessMessage(
                _t(
                    "CloudFlare.SuccessPurgedImages",
                    "Successfully purged {file_count} image files from cache."
                )
            )
            ->findFilesWithExts(array(".jpg", ".jpeg", ".gif", ".png", ".ico", ".bmp", ".svg"));

        if (!$purger->count()) {
            CloudFlare_Notifications::handleMessage(
                _t(
                    "CloudFlare.NoImageFilesFound",
                    "No image files were found."
                )
            );
        }

        $purger->purge();

        return $this->redirect($this->Link('/'));
    }

    /**
     * Gets whether the required CloudFlare credentials are defined.
     *
     * @return bool
     */
    public function getCredentialsDefined()
    {
        return (bool) CloudFlare::inst()->hasCFCredentials();
    }

    /**
     * Template function to check for a response "alert" from CloudFlare functionality
     *
     * @return ArrayData
     */
    public function CFAlert()
    {
        $jar = CloudFlare::inst()->getSessionJar();

        $array = array(
            "Type" => (array_key_exists('CFType', $jar)) ? $jar['CFType'] : FALSE,
            "Message" => (array_key_exists('CFMessage', $jar)) ? $jar['CFMessage'] : FALSE,
        );

        return ArrayData::create($array);
    }

    /**
     * Destroys the alert message that is saved in session
     */
    public function DestroyCFAlert()
    {
        $jar = CloudFlare::inst()->getSessionJar();

        $jar['CFType'] = false;
        $jar['CFMessage'] = false;

        CloudFlare::inst()->setSessionJar($jar);
    }

    /**
     * Template function to determine if CloudFlare is ready (ergo has a zone ID)
     *
     * @return bool|null
     */
    public function isReady()
    {
        return CloudFlare::inst()->isReady();
    }

    /**
     * @todo Actually implement this
     * @return static
     */
    public function FormSingleUrlForm()
    {
        return CloudFlareSingleUrlForm::create($this, 'purge-single');
    }

    /**
     * Template function to display the detected zone ID
     *
     * @return string
     */
    public function ZoneID()
    {
        return CloudFlare::inst()->fetchZoneID() ?: "<strong class='cf-no-zone-id'>UNABLE TO DETECT</strong>";
    }


}
