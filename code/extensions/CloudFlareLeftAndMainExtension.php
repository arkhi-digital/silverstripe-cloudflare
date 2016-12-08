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
        if (empty($request) || empty($request['ID'])) {
            return;
        }

        CloudFlare::inst()->purgePage($request['ID']);
    }
}
