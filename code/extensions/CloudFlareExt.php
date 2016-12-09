<?php
/**
 * Class CloudFlareExt
 *
 * @package silverstripe-cloudflare
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
        if (CloudFlare::singleton()->hasCFCredentials() && strlen($original->URLSegment)) {

            $purger = CloudFlare_Purge::create();
            $shouldPurgeRelations = CloudFlare::inst()->getShouldPurgeRelations();
            $urls = array(DataObject::get_by_id("SiteTree", $this->owner->ID)->Link());

            if ($shouldPurgeRelations) {
                $top = $this->getTopLevelParent();
            }

            if (
                $this->owner->URLSegment != $original->URLSegment || // the slug has been altered
                $this->owner->MenuTitle != $original->MenuTitle || // the navigation label has been altered
                $this->owner->Title != $original->Title // the title has been altered
            ) {
                // purge everything
                $purger
                    ->setSuccessMessage("A critical element has changed in this page (url, menu label, or page title) as a result; everything was purged")
                    ->purgeEverything(true)
                    ->purge();
            }

            if ($shouldPurgeRelations) {
                if ($this->owner->URLSegment != $top->URLSegment) {
                    // this is a little convoluted consider refactoring/renaming
                    $this->getChildrenRecursive($top->ID, $urls);
                }
            }

            if (count($urls) === 1) {
                $purger
                    ->reset()
                    ->setSuccessMessage("Successfully purged cache for the current page")
                    ->setFailureMessage("An error occurred while attempting to purge cache for the current page")
                    ->pushFile($urls[0])
                    ->purge();
            }

            // phpmd will insult me if I use else :'(
            if (count($urls) > 1) {
                $purger
                    ->reset()
                    ->setSuccessMessage("CloudFlare cache has been purged for: {file_count} files")
                    ->setFailureMessage("An error occurred while attempting to purge cache for the current page")
                    ->pushFiles($urls)
                    ->purge();
            }

        }

        parent::onAfterPublish($original);
    }

    /**
     * If something gets unpublished we purge EVERYTHING just to be safe (ie nav menus etc)
     */
    public function onAfterUnpublish()
    {

        if (CloudFlare::singleton()->hasCFCredentials()) {
            //CloudFlare::inst()->purgeAll('CloudFlare: All cache has been purged as a result of unpublishing a page.');
            $purger = CloudFlare_Purge::create();
            $purger->purgeEverything(true);
            $purger->purge();
        }

        parent::onBeforeUnpublish();
    }

    /**
     * Determines if the current owner or given page ID is a parent
     *
     * @param null|int $id SiteTree.ID
     *
     * @return bool
     */
    public function isParent($id = NULL)
    {
        return ($this->getChildren($id)->count()) ? TRUE : FALSE;
    }

    /**
     * Gets the immediate children of a Page (doesn't care if those children are parents themselves - see getChildrenRecursive instead)
     *
     * @param null|int $parentID SiteTree.ParentID
     *
     * @return \DataList
     */
    public function getChildren($parentID = NULL)
    {
        $id = (is_null($parentID)) ? $this->owner->ID : $parentID;

        return SiteTree::get()->filter("ParentID", $id);
    }

    /**
     * Traverses through the SiteTree hierarchy until it reaches the top level parent
     *
     * @return \DataObject|Object
     */
    public function getTopLevelParent() {
        $obj = $this->owner;

        while ((int)$obj->ParentID) {
            $obj = SiteTree::get()->filter("ID", $obj->ParentID)->first();
        }

        return $obj;
    }

    /**
     * Recursively fetches all children of the given page ID
     *
     * @param null $parentID SiteTree.ParentID
     * @param      $output
     */
    public function getChildrenRecursive($parentID = NULL, &$output) {
        $id = (is_null($parentID)) ? $this->owner->ID : $parentID;

        if (!is_array($output)) { $output = array(); }

        $children = $this->getChildren($id);

        foreach ($children as $child) {
            if ($this->isParent($child->ID)) {
                $this->getChildrenRecursive($child->ID, $output);
            }

            $output[] = ltrim(DataObject::get_by_id('SiteTree', $child->ID)->Link(), "/");
        }
    }

    /**
     * Add a "purge page" CMS action
     *
     * @param  FieldList $actions
     * @return void
     */
    public function updateCMSActions(FieldList $actions)
    {
        if (!CloudFlare::inst()->hasCFCredentials()) {
            return;
        }

        $actions->addFieldToTab(
            'ActionMenus.MoreOptions',
            FormAction::create('purgesinglepageAction', 'Purge in CloudFlare')
        );
    }
}
