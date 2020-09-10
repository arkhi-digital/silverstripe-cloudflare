<?php
namespace Steadlane\CloudFlare\Rules;

use SilverStripe\Core\Object;
use Steadlane\CloudFlare\CloudFlare;

class RuleItem extends Object
{

    /**
     * @var RuleTargetList
     */
    protected $targets;

    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var RuleActionList
     */
    protected $actions;

    /**
     * @var int
     */
    protected $priority;

    /**
     * @var bool
     */
    protected $status;

    /**
     * @var string
     */
    protected $createdOn;

    /**
     * @var string
     */
    protected $modifiedOn;

    /**
     * @var bool
     */
    protected $hasError = false;

    /**
     * @var array
     */
    protected $errorMessage;

    /**
     * Loads this object with data from source, expects a single rule in either JSON string format or an array,
     * in the format it was delivered in.
     *
     * @param string|array $source
     *
     * @return $this
     */
    public function load($source)
    {

        if (!is_string($source) && !is_array($source)) {
            user_error('RuleItem::load() must be provided a JSON string or an array', E_USER_ERROR);
        }

        if (is_string($source)) {
            $source = json_decode($source, true);
            if (!is_array($source)) {
                user_error('RuleItem::load() must be provided a JSON string or an array', E_USER_ERROR);
            }
        }

        if (array_key_exists(0, $source) && is_array($source[0])) {
            user_error('RuleItem::load() expects only a single item', E_USER_ERROR);
        }

        $targetList = RuleTargetList::create();
        foreach ($source['targets'] as $target) {
            $targetList->push(RuleTarget::create()
                ->setTarget($target['target'])
                ->setOperator('matches')
                ->setValue($target['constraint']['value'])
            );
        }

        $actionList = RuleActionList::create();
        foreach ($source['actions'] as $action) {
            $actionList->push(RuleAction::create()
                ->setActionId($action['id'])
                ->setValue($action['value'])
            );
        }

        $this->identifier = $source['id'];
        $this->targets    = $targetList;
        $this->actions    = $actionList;
        $this->priority   = $source['priority'];
        $this->status     = ($source['status'] == 'active');
        $this->createdOn  = $source['created_on'];
        $this->modifiedOn = $source['modified_on'];

        return $this;
    }

    /**
     * Used to update or create a new page rule, the difference is based on whether or not `$this->identifier` is set
     *
     * @return string|bool Returns the identifier on success
     */
    public function save()
    {
        $data = $this->toArray();

        unset($data['id']); // remove ID from data as if it exists; the endpoint is updated with it dynamically

        $json = CloudFlare::singleton()->curlRequest(
            $this->getEndpoint(),
            $data,
            'PUT'
        );

        $result = json_decode($json, true);

        if (!$result['success']) {
            $this->hasError     = true;
            $this->errorMessage = $result['errors'];

            return false;
        }

        $this->identifier = $result['result']['id'];
        RuleHandler::flush();

        return $this->identifier;

    }

    /**
     * Delete the active page rule
     *
     * @return bool
     */
    public function delete()
    {
        if (!$this->identifier) {
            return true;
        }

        $json   = CloudFlare::singleton()->curlRequest($this->getEndpoint());
        $result = json_decode($json, true);

        if (!$result['success']) {
            $this->hasError     = true;
            $this->errorMessage = $result['errors'];

            return false;
        }

        unset($this->identifier);
        RuleHandler::flush();

        return true;
    }

    /**
     * Get the identifier for the active page rule
     * @return string|null
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * Gets the list of targets
     *
     * @return array|null
     */
    public function getTargets()
    {
        return $this->targets;
    }

    /**
     * Sets the list of targets
     *
     * @param $targets
     *
     * @return $this
     */
    public function setTargets($targets)
    {
        foreach ($targets as $target) {
            if (!($target instanceof RuleTarget)) {
                user_error('setTargets() only accepts an array of RuleTarget\'s, one of the values was of type ' . gettype($target),
                    E_USER_ERROR);
            }
        }
        $this->targets = $targets;

        return $this;
    }

    /**
     * Gets the list of actions that is (or is tobe) placed against this page rule
     *
     * @return array An array of RuleAction's
     */
    public function getActions()
    {
        return $this->actions;
    }

    /**
     * @param $actions
     *
     * @return $this
     */
    public function setActions($actions)
    {
        foreach ($actions as $action) {
            if (!($action instanceof RuleTarget)) {
                user_error('setActions() only accepts an array of RuleTarget\'s, one of the values was of type ' . gettype($action),
                    E_USER_ERROR);
            }
        }

        $this->actions = $actions;

        return $this;
    }

    /**
     * Get priority
     *
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * Set Priority
     *
     * @param int $priority
     *
     * @return $this
     */
    public function setPriority($priority)
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Get Status
     *
     * @return bool
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set status
     *
     * @param bool $bool
     *
     * @return $this
     */
    public function setStatus($bool)
    {
        $this->status = $bool;

        return $this;
    }

    /**
     * Converts this object to an array in the format expected by CloudFlare
     *
     * @return array
     */
    public function toArray()
    {

        $output = array(
            'targets'  => $this->targets->toArray(),
            'actions'  => $this->actions->toArray(),
            'priority' => (int)$this->priority,
            'status'   => ($this->status) ? 'active' : 'disabled'
        );

        if (isset($this->identifier)) {
            $output = array_merge(array('id' => $this->identifier), $output);
        }

        return $output;
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

    /**
     * Gets the dynamic endpoint. Appends the rule identifier if the current object represents a rule
     * that already exists in CloudFlare
     *
     * @return string
     */
    public function getEndpoint()
    {
        $endpoint = rtrim(RuleHandler::singleton()->getEndpoint(), '/');
        if ($this->identifier) {
            $endpoint = $endpoint . '/' . $this->identifier;
        }

        return $endpoint;
    }
}