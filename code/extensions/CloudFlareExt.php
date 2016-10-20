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
        if (CloudFlare::hasCFCredentials() && strlen($original->URLSegment)) {
            CloudFlare::purgeSingle($original->URLSegment);
        }

        parent::onAfterPublish($original);
    }

    /**
     * We purge CloudFlare cache for files that were removed from published state so that they no longer appear for the
     * user should cache have not expired yet.
     */
    public function onAfterUnpublish()
    {
        if (CloudFlare::hasCFCredentials()) {
            CloudFlare::purgeSingle($this->owner->URLSegment);
        }

        parent::onBeforeUnpublish();
    }




}