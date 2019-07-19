<?php
namespace Steadlane\CloudFlare\Errors;

use SilverStripe\Core\Object;
use Steadlane\CloudFlare\CloudFlare;

use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Config\Configurable;
class HandlerMethods
{
    use Extensible;
    use Injectable;
    use Configurable;
    /**
     * Generic Error
     *
     * @param $response
     */
    public static function generic($response)
    {
        self::log(print_r($response));
    }
    public static function malformed($response)
    {
        self::log(sprintf("The API response was malformed:\r\n%s", print_r($response, true)));
    }
    public static function log($message, $force = null)
    {
        if (!$force || !CloudFlare::config()->log_errors) {
            return;
        }
        error_log(sprintf("CloudFlare Module Reported An Error:\r\n%s", $message));
    }
}
