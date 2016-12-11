<?php
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
        CloudFlare::singleton()->canUser('CF_PURGE_PAGE');
        
        if (empty($request) || empty($request['ID'])) {
            return;
        }

        CloudFlare_Purge::singleton()->quick('page', $request['ID']);
    }
}
