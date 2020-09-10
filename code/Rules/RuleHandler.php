<?php
namespace Steadlane\CloudFlare\Rules;

use SilverStripe\Core\Cache;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Object;
use Steadlane\CloudFlare\CloudFlare;

class RuleHandler extends Object implements Flushable
{
    /**
     * The cache key that page rules are stored under
     */
    const CF_PAGERULES_CACHE_KEY = 'CFPageRules';

    /**
     * @var string
     */
    protected $successMessage;

    /**
     * @var string
     */
    protected $failureMessage;

    /**
     * @var bool
     */
    protected $testOnly = false;

    /**
     * @var string
     */
    protected $testResultSuccess;

    /**
     * @var
     */
    protected $response;

    /**
     * @var string
     */
    protected static $endpoint = "https://api.cloudflare.com/client/v4/zones/:identifier/pagerules";

    /**
     * Gets the list of all active page rules, currently does not support pagination
     *
     * @return RuleItemList
     */
    public function getList()
    {
        $cacheFactory = Cache::factory('CloudFlare');

        if ($cache = $cacheFactory->load(static::CF_PAGERULES_CACHE_KEY)) {
            $json = $cache;
        }

        if (!isset($json) || $json) {
            $json = CloudFlare::singleton()->curlRequest(
                $this->getEndpoint(),
                array(
                    'status'    => 'active',
                    'order'     => 'priority',
                    'direction' => 'desc',
                    'match'     => 'all'
                ),
                'GET'
            );

            Cache::set_cache_lifetime('CloudFlare', 60 * 30);
            $cacheFactory->save($json, static::CF_PAGERULES_CACHE_KEY);
        }

        $array = json_decode($json, true);
        $rules = $array['result'];

        $list  = RuleItemList::create();
        foreach ($rules as $rule) {
            $list->push(RuleItem::create()->load($rule));
        }

        return $list;
    }

    /**
     * @return string
     */
    public function getEndpoint()
    {
        $zoneId = CloudFlare::singleton()->fetchZoneID();

        return str_replace(":identifier", $zoneId, static::$endpoint);
    }

    /**
     * Invalidates the cache, usually called after making a change to a rule
     *
     * @return bool
     */
    public static function flush() {
        return Cache::factory('CloudFlare')->remove(static::CF_PAGERULES_CACHE_KEY);
    }
}