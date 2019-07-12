<?php

namespace SteadLane\Cloudflare;

use SilverStripe\CMS\Model\SiteTreeExtension;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Security\Permission;

/**
 * Class CloudFlareExtension
 * @package silverstripe-cloudflare
 */
class CloudFlareExtension extends SiteTreeExtension
{
    /**
     * Extension Hook
     *
     * @param SiteTree $original
     */
    public function onAfterPublish(&$original)
    {
        // if the page was just created, then there is no cache to purge and $original doesn't actually exist so bail out - resolves #3
        // we don't purge anything if we're operating on localhost
        if (CloudFlare::singleton()->hasCFCredentials() && $original && strlen($original->URLSegment) && Permission::check('CF_PURGE_PAGE')) {

            $purger = Purge::create();
            $shouldPurgeRelations = Purge::singleton()->getShouldPurgeRelations();

            $pageUrl=ltrim(DataObject::get_by_id(SiteTree::class, $this->owner->ID)->Link(), "/");
            if ($pageUrl!='/' && substr($pageUrl,-1)=='/') {
                $pageUrl=substr($pageUrl,0,strlen($pageUrl)-1); // add first URL without trailing slash
            }
            $urls = array($_SERVER['DOCUMENT_ROOT'].$pageUrl);
            if ($pageUrl!='/') {
                array_push($urls, $_SERVER['DOCUMENT_ROOT'].$pageUrl.'/'); // add second URL with trailing slash
            }

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
                    ->setSuccessMessage(
                        _t(
                            "CloudFlare.SuccessCriticalElementChanged",
                            "A critical element has changed in this page (url, menu label, or page title) as a result; everything was purged"
                        )
                    )
                    ->setPurgeEverything(true)
                    ->purge();
            }

            if ($shouldPurgeRelations && isset($top)) {
                if ($this->owner->URLSegment != $top->URLSegment) {
                    // this is a little convoluted consider refactoring/renaming
                    $this->getChildrenRecursive($top->ID, $urls);
                }
            }

            if (count($urls)===1 && empty($urls[0])) {
                $urls=array();
            }

            if (count($urls) === 1) {
                $purger
                    ->reset()
                    ->setSuccessMessage(
                        _t(
                            "CloudFlare.SuccessPurgeCurrentPage",
                            "Successfully purged cache for the current page"
                        )
                    )
                    ->setFailureMessage(
                        _t(
                            "CloudFlare.FailurePurgeCurrentPage",
                            "An error occurred while attempting to purge cache for the current page"
                        )
                    )
                    ->pushFile($urls[0])
                    ->purge();
            } else if (count($urls) > 1) {
                $purger
                    ->reset()
                    ->setSuccessMessage(
                        _t(
                            "CloudFlare.SuccessPurgeMultiple",
                            "Cache has been purged for: {file_count} files"
                        )
                    )
                    ->setFailureMessage(
                        _t(
                            "CloudFlare.FailurePurgeMultiple",
                            "An error occurred while attempting to purge cache for multiple pages"
                        )
                    )
                    ->pushFile($urls)
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
        if (CloudFlare::singleton()->hasCFCredentials() && Permission::check('CF_PURGE_PAGE')) {
            $purger = Purge::create();
            $purger
                ->setPurgeEverything(true)
                ->setSuccessMessage(
                    _t(
                        "CloudFlare.UnpublishAllCachePurged",
                        "All cache has been purged as a result of unpublishing a page."
                    )
                )
                ->setPurgeEverything(true)
                ->setSuccessMessage(
                    _t(
                        "CloudFlare.FailureAllCachePurged",
                        "We encountered an error when attempting to purge all cache, consider doing this manually."
                    )
                )
                ->purge();

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
     * @return DataList
     */
    public function getChildren($parentID = NULL)
    {
        $id = (is_null($parentID)) ? $this->owner->ID : $parentID;

        return SiteTree::get()->filter("ParentID", $id);
    }

    /**
     * Traverses through the SiteTree hierarchy until it reaches the top level parent
     *
     * @return DataObject|Object
     */
    public function getTopLevelParent()
    {
        $obj = $this->owner;

        while ((int)$obj->ParentID) {
            $obj = SiteTree::get()->filter("ID", $obj->ParentID)->first();
        }

        return $obj;
    }

    /**
     * Recursively fetches all children of the given page ID
     *
     * @param null|int $parentID
     * @param array $output
     */
    public function getChildrenRecursive($parentID, &$output)
    {
        $id = (is_null($parentID)) ? $this->owner->ID : $parentID;

        if (!is_array($output)) { $output = array(); }

        $children = $this->getChildren($id);

        foreach ($children as $child) {
            if ($this->isParent($child->ID)) {
                $this->getChildrenRecursive($child->ID, $output);
            }

            $output[] = ltrim(DataObject::get_by_id(SiteTree::class, $child->ID)->Link(), "/");
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
        if (!CloudFlare::singleton()->hasCFCredentials()) {
            return;
        }

        $actions->addFieldToTab(
            'ActionMenus.MoreOptions',
            FormAction::create('purgesinglepageAction', 
                _t(
                    'CloudFlare.ActionMenuPurge',
                    'Purge in Cloudflare'
                )
            )->addExtraClass('btn-secondary')
        );
    }
}
