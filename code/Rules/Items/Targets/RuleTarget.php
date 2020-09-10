<?php
namespace Steadlane\CloudFlare\Rules;

use SilverStripe\Core\Object;

class RuleTarget extends Object
{

    /**
     * The target of this object. Default: "url"
     *
     * @var string
     */
    protected $target = "url";

    /**
     * @var string
     */
    protected $operator = "matches";

    /**
     * @var string
     */
    protected $value;

    /**
     * The value of this target
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Sets the value for this target; which is what the inheriting page rule will trigger against
     *
     * @example *.example.com/*
     *
     * @param string $value Usually a domain mask
     *
     * @return $this
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Gets the matching operator, by default "match"
     * @return string
     */
    public function getOperator()
    {
        return $this->operator;
    }

    /**
     * Sets the matching operator. Default: "match"
     *
     * @param string $operator
     *
     * @return $this
     */
    public function setOperator($operator)
    {
        $this->operator = $operator;

        return $this;
    }

    /**
     * Gets the target of this object. Default: "url"
     *
     * @return string
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * Sets the target of this object. Default: "url"
     *
     * @param string $target
     *
     * @return $this
     */
    public function setTarget($target)
    {
        $this->target = $target;

        return $this;
    }

    /**
     * Converts this object to an array in the format expected by CloudFlare
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            "target"     => $this->getTarget(),
            "constraint" => array(
                "operator" => $this->getOperator(),
                "value"    => $this->getValue()
            )
        );
    }

    /**
     * Converts this object to a JSON string in the format expected by CloudFlare
     *
     * @return array
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }

}