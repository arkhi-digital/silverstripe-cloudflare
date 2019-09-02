<?php

namespace SteadLane\Cloudflare\Errors;

use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SteadLane\Cloudflare\CloudFlare;

/**
 * Class HandlerMethods
 * @package SteadLane\Cloudflare\Errors
 */
class HandlerMethods
{
    use Injectable;

    /**
     * Generic Error
     *
     * @param array|string $response
     */
    public static function generic($response)
    {
        self::log(print_r($response));
    }

    /**
     * @param array|string $response
     */
    public static function malformed($response)
    {
        self::log(sprintf("The API response was malformed:\r\n%s", print_r($response, true)));
    }

    /**
     * @param string $message
     * @param bool $force
     */
    public static function log($message, $force = false)
    {
        if (!$force || !CloudFlare::config()->log_errors) {
            return;
        }

        $error="Cloudflare module reported an error:\r\n".$message;
        if (Injector::inst()->get(LoggerInterface::class)) {
        	Injector::inst()->get(LoggerInterface::class)->debug($error);
		}
        error_log($error);
    }
}
