<?php

namespace SteadLane\Cloudflare\Messages;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Control\Director;
use SilverStripe\ORM\ArrayLib;
use Steadlane\CloudFlare\CloudFlare;

/**
 * Class Notifications
 * @package SteadLane\Cloudflare\Messages
 */
class Notifications
{
    use Configurable;
    use Injectable;
    use Extensible;

    /**
     * Sets the X-Status header which creates the toast-like popout notification
     *
     * @param string $message
     */
    protected static function setToast($message)
    {
        Controller::curr()->getResponse()->addHeader('X-Status', rawurlencode('Cloudflare: ' . $message));
    }

    /**
     * Sets an Alert that will display on the CloudFlare LeftAndMain
     *
     * @param string $message
     * @param string $type
     */
    protected static function setAlert($message, $type = 'success')
    {
        if (!$type || empty($type)){ $type='success'; }
        $jar = CloudFlare::singleton()->getSessionJar();

        $jar['CFMessage'] = $message;
        $jar['CFType'] = $type;

        CloudFlare::singleton()->setSessionJar($jar);
    }

    /**
     * Determines the origin of the request, if AJAX the message will be provided in X-Status, otherwise display
     * in interface
     *
     * @param string $message
     * @param array $params
     */
    public static function handleMessage($message, $params = null)
    {
        if (!$message) {
            return;
        }

        if (is_array($params)) {
            if (!ArrayLib::is_associative($params)) {
                user_error("The second parameter for handleMessage must be an associative array", E_USER_ERROR);
            }

            foreach ($params as $search => $replace) {
                $message = str_replace('{' . $search . '}', $replace, $message);
            }
        }

        if (Director::is_ajax()) {
            static::setToast($message);
        } else {
            static::setAlert($message, ($params && isset($params['type'])?$params['type']:null));
        }
    }

}
