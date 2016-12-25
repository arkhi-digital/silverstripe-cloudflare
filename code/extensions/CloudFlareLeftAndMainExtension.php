<?php

use SilverStripe\Admin\LeftAndMainExtension;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use Steadlane\CloudFlare\Purge;

/**
 * Class CloudFlareLeftAndMainExtension
 *
 * @package silverstripe-cloudflare
 */
class CloudFlareLeftAndMainExtension extends LeftAndMainExtension
{
    /**
     * {@inheritDoc}
     */
    private static $allowed_actions = array(
        'purgesinglepage'
    );

    /**
     * Purge a single page in CloudFlare
     *
     * @param array $request The SiteTree data requested to be purged
     */
    public function purgesinglepageAction($request)
    {
        if (!Permission::check('CF_PURGE_PAGE')) {
            Security::permissionFailure();
        }
        
        if (empty($request) || empty($request['ID'])) {
            return;
        }

        Purge::singleton()->quick('page', $request['ID']);
    }
}
