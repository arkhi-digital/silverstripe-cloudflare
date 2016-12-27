<?php
namespace Steadlane\CloudFlare\Rules;

use SilverStripe\Core\Object;

class RuleItemList extends Object
{
    /**
     * @var array An array of `RuleItem`'s
     */
    protected $items = array();

    /**
     * Pushes a RuleItem object into this object
     *
     * @param \Steadlane\CloudFlare\Rules\RuleItem $item
     */
    public function push(RuleItem $item)
    {
        if (in_array($item, $this->items)) {
            return;
        }

        $this->items[] = $item;
    }

    /**
     * Converts all children of this object into an array
     *
     * @return array
     */
    public function toArray()
    {
        $stack = array();

        foreach ($this->items as $item) {
            $stack[] = $item->toArray();
        }

        return $stack;
    }

    /**
     * Converts all children of this object into an array and outputs it as JSON
     *
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }
}