<?php
namespace Steadlane\CloudFlare\Rules;

use SilverStripe\Core\Object;

class RuleAction extends Object
{
    /**
     * This represents the action that the inheriting page rule should trigger, ie: cache_level, browser_cache_ttl,
     * rocket_loader etc
     *
     * @var string
     */
    protected $actionId;

    /**
     * @var int|string This value is dependent on the action
     */
    protected $value;

    /**
     * Sets the action id, ie: cache_level, browser_cache_ttl, rocket_loader etc
     *
     * @param string $actionId
     *
     * @return $this
     */
    public function setActionId($actionId)
    {
        $this->actionId = $actionId;

        return $this;
    }

    /**
     * Gets the action id
     *
     * @return string
     */
    public function getActionId()
    {
        return $this->actionId;
    }

    /**
     * Set the value for this objects action id
     *
     * @param int|string $value
     *
     * @return $this
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get the value of this objects action id
     *
     * @return int|string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Converts this object to an array in the format expected by CloudFlare
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            'id'    => $this->getActionId(),
            'value' => $this->getValue()
        );
    }

    /**
     * Converts this object to a JSON string in the format expected by CloudFlare
     *
     * @return array
     */
    public function toJson() {
        return json_encode($this->toArray());
    }
}