<?php

namespace SteadLane\Cloudflare;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Security;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use Steadlane\CloudFlare\Messages\Notifications;

/**
 * Class CloudFlareAdmin
 * @package SteadLane\Cloudflare
 */
class CloudFlareAdmin extends LeftAndMain implements PermissionProvider
{
    private static $url_segment = 'cloudflare';
    private static $url_rule    = '/$Action/$ID/$OtherID';
    private static $menu_title  = 'Cloudflare';
    private static $menu_icon_class = 'font-icon-upload';
    private static $menu_priority = -9;

    private static $allowed_actions = array(
        'purge_all',
        'purge_stylesheets',
        'purge_images',
        'purge_javascript',
        'purge_single',
    );

    /**
     * @return array
     */
    public function providePermissions()
    {
        return array(
            "CF_PURGE_ALL" => array(
                'name' => "Cloudflare: Purge All Cache",
                'category' => _t('SilverStripe\\Security\\Permission.CONTENT_CATEGORY', 'Content permissions'),
                //'help' => '',
            ),
            "CF_PURGE_CSS" => array(
                'name' => "Cloudflare: Purge Stylesheet Cache",
                'category' => _t('SilverStripe\\Security\\Permission.CONTENT_CATEGORY', 'Content permissions'),
                //'help' => '',
            ),
            "CF_PURGE_JAVASCRIPT" => array(
                'name' => "Cloudflare: Purge Javascript Cache",
                'category' => _t('SilverStripe\\Security\\Permission.CONTENT_CATEGORY', 'Content permissions'),
                //'help' => '',
            ),
            "CF_PURGE_STYLESHEETS" => array(
                'name' => "Cloudflare: Purge Stylesheet Cache",
                'category' => _t('SilverStripe\\Security\\Permission.CONTENT_CATEGORY', 'Content permissions'),
                //'help' => '',
            ),
            "CF_PURGE_PAGE" => array(
                'name' => "Cloudflare: Purge Page Cache",
                'category' => _t('SilverStripe\\Security\\Permission.CONTENT_CATEGORY', 'Content permissions'),
                //'help' => '',
            ),
            "CF_PURGE_SINGLE" => array(
                'name' => "Cloudflare: Purge Single File Cache",
                'category' => _t('SilverStripe\\Security\\Permission.CONTENT_CATEGORY', 'Content permissions'),
                //'help' => '',
            ),
        );
    }

    /**
     * Include our CSS
     */
    public function init()
    {
        parent::init();

        //Requirements::css('cloudflare/css/cloudflare.min.css');
        $css=@file_get_contents(dirname(__FILE__).'/../../css/cloudflare.min.css');
        if (!empty($css)) {
            Requirements::customCSS($css);
        }
    }

    /**
     * @return \SilverStripe\Control\HTTPResponse|string
     */
    public function purge_all()
    {
        if (!Permission::check('CF_PURGE_ALL')) {
            Security::permissionFailure();
        }

        $purger = Purge::create();
        $purger
            ->setPurgeEverything(true)
            ->setSuccessMessage(
                _t(
                    "CloudFlare.PurgedEverything",
                    "Successfully purged EVERYTHING from cache."
                )
            )
            ->purge();

        return $this->redirect($this->Link('/'));
    }

    /**
     * @return \SilverStripe\Control\HTTPResponse|string
     */
    public function purge_stylesheets()
    {
        if (!Permission::check('CF_PURGE_STYLESHEETS')) {
            Security::permissionFailure();
        }

        Purge::singleton()->quick('css');

        return $this->redirect($this->Link('/'));
    }

    /**
     * @return \SilverStripe\Control\HTTPResponse|string
     */
    public function purge_javascript()
    {
        if (!Permission::check('CF_PURGE_JAVASCRIPT')) {
            Security::permissionFailure();
        }

        Purge::singleton()->quick('javascript');

        return $this->redirect($this->Link('/'));
    }

    /**
     * @return \SilverStripe\Control\HTTPResponse|string
     */
    public function purge_images()
    {
        if (!Permission::check('CF_PURGE_IMAGES')) {
            Security::permissionFailure();
        }

        Purge::singleton()->quick('image');

        return $this->redirect($this->Link('/'));
    }

    /**
     * @return \SilverStripe\Control\HTTPResponse|string
     */
    public function purge_single()
    {
        if (!Permission::check('CF_PURGE_SINGLE')) {
            Security::permissionFailure();
        }

        if (!($urlToPurge = $this->request->postVar('url_to_purge'))) {
            Notifications::handleMessage(
                _t(
                    "CloudFlare.ProvidedFileNotFound",
                    "Please provide a valid file to purge first"
                )
            );

            return $this->redirect($this->Link('/'));
        }

        $urlToPurge = CloudFlare::singleton()->prependServerName($urlToPurge);

        $purger = Purge::create();
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


        return $this->redirect($this->Link('/'));
    }

    /**
     * Gets whether the required Cloudflare credentials are defined.
     *
     * @return bool
     */
    public function getCredentialsDefined()
    {
        return (bool)CloudFlare::singleton()->hasCFCredentials();
    }

    /**
     * Template function to check for a response "alert" from Cloudflare functionality
     *
     * @return \SilverStripe\View\ArrayData
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

        $jar['CFType']    = false;
        $jar['CFMessage'] = false;

        CloudFlare::singleton()->setSessionJar($jar);
    }

    /**
     * Template function to determine if Cloudflare is ready (ergo has a zone ID)
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
     * @return CloudFlareSingleUrlForm
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
        return (CloudFlare::singleton()->fetchZoneID() ?: _t("CloudFlare.UnableToDetect", "UNABLE TO DETECT"));
    }

    /**
     * @param $code
     *
     * @return bool|int
     */
    public function HasPermission($code)
    {
        return Permission::check($code);
    }

    /**
     * Determines if the current user has access to any of the GUI functionality
     *
     * @return bool
     */
    public function HasAnyAccess()
    {
        foreach ($this->providePermissions() as $permission => $context) {
            if (!Permission::check($permission)) {
                return false;
            }
        }
        return true;
    }
}
