<?php

namespace SteadLane\Cloudflare;

use SilverStripe\Control\RequestHandler;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\FormAction;

/**
 * Class CloudFlareSingleUrlForm
 * @package SteadLane\Cloudflare
 */
class CloudFlareSingleUrlForm extends Form
{
    /**
     * CloudFlareSingleUrlForm constructor.
     * @param RequestHandler $controller
     * @param string $name
     */
    public function __construct($controller, $name)
    {
        $fields = FieldList::create(
            TextField::create("url_to_purge", "")->setAttribute("placeholder", '/path/to/file.js [, /path/to/file.css, /path/to/url]')
        );

        $actions = FieldList::create(
            FormAction::create('handlePurgeUrl', 
                _t(
                    'CloudFlare.SingleUrlPurgeButton',
                    'Purge'
                )
            )->addExtraClass('btn action btn-primary px-3 mt-4')
        );

        parent::__construct($controller, $name, $fields, $actions);
    }
}