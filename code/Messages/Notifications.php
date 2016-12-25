<?php
namespace Steadlane\CloudFlare\Messages;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Object;
use SilverStripe\Control\Director;
use SilverStripe\ORM\ArrayLib;
use Steadlane\CloudFlare;

class Notifications extends Object
{

    /**
     * Sets the X-Status header which creates the toast-like popout notification
     *
     * @param string $message
     */
    protected static function setToast($message)
    {
        Controller::curr()->response->addHeader('X-Status', rawurlencode('CloudFlare: ' . $message));
    }

    /**
     * Sets an Alert that will display on the CloudFlare LeftAndMain
     *
     * @param string $message
     * @param string $type
     */
    protected static function setAlert($message, $type = 'success')
    {
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
            static::setAlert($message);
        }
    }

}
