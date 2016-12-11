<?php

class CloudFlareAdmin extends LeftAndMain implements PermissionProvider
{
    private static $url_segment = 'cloudflare';
    private static $url_rule    = '/$Action/$ID/$OtherID';
    private static $menu_title  = 'CloudFlare';
    private static $menu_icon   = 'cloudflare/assets/cloudflare.jpg';

    private static $allowed_actions = array(
        'purge_all',
        'purge_css',
        'purge_images',
        'purge_javascript',
        'purge_single',
    );

    /**
     * @todo Actually implement this
     * @return array
     */
    public function providePermissions()
    {
        return array(
            "PURGE_ALL"    => "Purge All Cache",
            "PURGE_CSS"    => "Purge CSS Cache",
            "PURGE_JS"     => "Purge JS Cache",
            "PURGE_PAGE"   => "Purge Page Cache",
            "PURGE_SINGLE" => "Purge Single File Cache",
        );
    }

    /**
     * Include our CSS
     */
    public function init()
    {
        parent::init();

        Requirements::css('cloudflare/css/cloudflare.min.css');
    }

    /**
     * @return \SS_HTTPResponse|string
     */
    public function purge_all()
    {
        $purger = CloudFlare_Purge::create();
        $purger
            ->setPurgeEverything(true)
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
        CloudFlare_Purge::singleton()->quick('css');

        return $this->redirect($this->Link('/'));
    }

    /**
     * @return \SS_HTTPResponse|string
     */
    public function purge_javascript()
    {
        CloudFlare_Purge::singleton()->quick('javascript');

        return $this->redirect($this->Link('/'));
    }

    /**
     * @return \SS_HTTPResponse|string
     */
    public function purge_images()
    {
        CloudFlare_Purge::singleton()->quick('image');

        return $this->redirect($this->Link('/'));
    }

    /**
     * @return \SS_HTTPResponse|string
     */
    public function purge_single()
    {
        if (!$urlToPurge = $this->request->postVar('url_to_purge')) {
            CloudFlare_Notifications::handleMessage(
                _t(
                    "CloudFlare.ProvidedFileNotFound",
                    "Please provide a valid file to purge first"
                )
            );
        } else {

            $urlToPurge = CloudFlare::singleton()->prependServerName($urlToPurge);

            $purger = CloudFlare_Purge::create();
            $purger
                ->pushFile($urlToPurge)
                ->setSuccessMessage(
                    _t(
                        "CloudFlare.SuccessPurgeProvidedFile",
                        "The provided file(s) have been successfully purged"
                    )
                )
                ->setFailureMessage(
                    _t(
                        "CloudFlare.FailurePurgeProvidedFile",
                        "An error occurred while attempting to purge the provided file(s)"
                    )
                )->purge();
        }

        return $this->redirect($this->Link('/'));
    }

    /**
     * Gets whether the required CloudFlare credentials are defined.
     *
     * @return bool
     */
    public function getCredentialsDefined()
    {
        return (bool) CloudFlare::singleton()->hasCFCredentials();
    }

    /**
     * Template function to check for a response "alert" from CloudFlare functionality
     *
     * @return ArrayData
     */
    public function CFAlert()
    {
        $jar = CloudFlare::singleton()->getSessionJar();

        $array = array(
            "Type"    => (array_key_exists('CFType', $jar)) ? $jar['CFType'] : false,
            "Message" => (array_key_exists('CFMessage', $jar)) ? $jar['CFMessage'] : false,
        );

        return ArrayData::create($array);
    }

    /**
     * Destroys the alert message that is saved in session
     */
    public function DestroyCFAlert()
    {
        $jar = CloudFlare::singleton()->getSessionJar();

        $jar['CFType'] = false;
        $jar['CFMessage'] = false;

        CloudFlare::singleton()->setSessionJar($jar);
    }

    /**
     * Template function to determine if CloudFlare is ready (ergo has a zone ID)
     *
     * @return bool|null
     */
    public function isReady()
    {
        return CloudFlare::singleton()->isReady();
    }

    /**
     * Produces the single url form for the admin GUI
     * 
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
        return CloudFlare::singleton()->fetchZoneID() ?: "<strong class='cf-no-zone-id'>UNABLE TO DETECT</strong>";
    }


}
