<?php

use SilverStripe\Core\Object;

class CloudFlare_ErrorHandlers extends Object
{

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

        error_log(
            sprintf(
                "CloudFlare Module Reported An Error:\r\n%s",
                $message
            )
        );
    }

}
