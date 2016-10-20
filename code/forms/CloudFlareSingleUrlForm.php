<?php

class CloudFlareSingleUrlForm extends Form {
    public function __construct($controller, $name)
    {
        $fields = FieldList::create(
            TextField::create("url_to_purge", "")->setAttribute("placeholder", '/path/to/file.js [, /path/to/file.css, /path/to/url]')
        );

        $actions = FieldList::create(
            FormAction::create('handlePurgeUrl', 'Purge')
        );

        parent::__construct($controller, $name, $fields, $actions);
    }
}