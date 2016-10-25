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
    );

    public function providePermissions()
    {
        return array(
            "PURGE_ALL"  => "Purge All Cache",
            "PURGE_CSS"  => "Purge CSS Cache",
            "PURGE_JS"   => "Purge JS Cache",
            "PURGE_PAGE" => "Purge Page Cache",
        );
    }

    public function init()
    {
        parent::init();

        Requirements::css('cloudflare/css/cloudflare.css');
    }

    public function FormSingleUrlForm()
    {
        return CloudFlareSingleUrlForm::create($this, 'purge-single');
    }

    public function purge_all()
    {
        CloudFlare::purgeAll();

        return $this->redirect($this->Link('/'));
    }

    public function purge_css()
    {
        CloudFlare::purgeCss();

        return $this->redirect($this->Link('/'));
    }

    public function purge_javascript()
    {
        CloudFlare::purgeJs();

        return $this->redirect($this->Link('/'));
    }

    public function purge_images()
    {
        CloudFlare::purgeImages();

        return $this->redirect($this->Link('/'));
    }

    /**
     * Template function to check for a response "alert" from CloudFlare functionality
     *
     * @return ArrayData
     */
    public function CFAlert()
    {
        $jar = CloudFlare::sessionJar();

        $array = array(
            "HasAlert" => (array_key_exists('CFAlert', $jar) && $jar[ 'CFAlert' ]) ? TRUE : FALSE,
            "Type" => (array_key_exists('CFType', $jar)) ? $jar['CFType'] : FALSE,
            "Message" => (array_key_exists('CFMessage', $jar)) ? $jar['CFMessage'] : FALSE,
        );

        $jar['CFAlert'] = false;

        CloudFlare::sessionJar($jar);

        return ArrayData::create($array);
    }

    public function ZoneID() {
        return CloudFlare::fetchZoneID() ?: "<strong class='cf-no-zone-id'>UNABLE TO DETECT</strong>";
    }


}