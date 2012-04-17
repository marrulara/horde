<?php
/**
 * The Ingo_Script_Maildrop:: class represents a maildrop script generator.
 *
 * Copyright 2005-2007 Matt Weyland <mathias@weyland.ch>
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/apache.
 *
 * @author  Matt Weyland <mathias@weyland.ch>
 * @package Ingo
 */
class Ingo_Script_Maildrop extends Ingo_Script
{

    /* Additional storage action since maildrop does not support the
     * "c-flag" as in procmail. */
    const MAILDROP_STORAGE_ACTION_STOREANDFORWARD = 100;

    /**
     * The list of actions allowed (implemented) for this driver.
     *
     * @var array
     */
    protected $_actions = array(
        Ingo_Storage::ACTION_KEEP,
        Ingo_Storage::ACTION_MOVE,
        Ingo_Storage::ACTION_DISCARD,
        Ingo_Storage::ACTION_REDIRECT,
        Ingo_Storage::ACTION_REDIRECTKEEP,
        Ingo_Storage::ACTION_REJECT,
    );

    /**
     * The categories of filtering allowed.
     *
     * @var array
     */
    protected $_categories = array(
        Ingo_Storage::ACTION_BLACKLIST,
        Ingo_Storage::ACTION_WHITELIST,
        Ingo_Storage::ACTION_VACATION,
        Ingo_Storage::ACTION_FORWARD,
        Ingo_Storage::ACTION_SPAM,
    );

    /**
     * The types of tests allowed (implemented) for this driver.
     *
     * @var array
     */
    protected $_types = array(
        Ingo_Storage::TYPE_HEADER,
    );

    /**
     * The list of tests allowed (implemented) for this driver.
     *
     * @var array
     */
    protected $_tests = array(
        'contains', 'not contain',
        'is', 'not is',
        'begins with','not begins with',
        'ends with', 'not ends with',
        'regex',
        'matches', 'not matches',
        'exists', 'not exist',
        'less than', 'less than or equal to',
        'equal', 'not equal',
        'greater than', 'greater than or equal to',
    );

    /**
     * Can tests be case sensitive?
     *
     * @var boolean
     */
    protected $_casesensitive = true;

    /**
     * Does the driver support the stop-script option?
     *
     * @var boolean
     */
    protected $_supportStopScript = false;

    /**
     * Does the driver require a script file to be generated?
     *
     * @var boolean
     */
    protected $_scriptfile = true;

    /**
     * The recipes that make up the code.
     *
     * @var array
     */
    protected $_recipes = array();

    /**
     * The vacation reason to be saved in vacation.msg.
     *
     * @var string
     */
    protected $_reason;

    /**
     * Returns a script previously generated with generate().
     *
     * @return string  The maildrop script.
     */
    public function toCode()
    {
        $code = '';
        foreach ($this->_recipes as $item) {
            $code .= $item->generate() . "\n";
        }
        return rtrim($code);
    }

    /**
     * Generates the maildrop script to do the filtering specified in
     * the rules.
     *
     * @return string  The maildrop script.
     */
    public function generate()
    {
        $filters = $GLOBALS['injector']->getInstance('Ingo_Factory_Storage')->create()->retrieve(Ingo_Storage::ACTION_FILTERS);

        $this->addItem(new Ingo_Script_Maildrop_Comment(_("maildrop script generated by Ingo") . ' (' . date('F j, Y, g:i a') . ')'));

        /* Add variable information, if present. */
        if (!empty($this->_params['variables']) &&
            is_array($this->_params['variables'])) {
            foreach ($this->_params['variables'] as $key => $val) {
                $this->addItem(new Ingo_Script_Maildrop_Variable(array('name' => $key, 'value' => $val)));
            }
        }

        foreach ($filters->getFilterList() as $filter) {
            switch ($filter['action']) {
            case Ingo_Storage::ACTION_BLACKLIST:
                $this->generateBlacklist(!empty($filter['disable']));
                break;

            case Ingo_Storage::ACTION_WHITELIST:
                $this->generateWhitelist(!empty($filter['disable']));
                break;

            case Ingo_Storage::ACTION_FORWARD:
                $this->generateForward(!empty($filter['disable']));
                break;

            case Ingo_Storage::ACTION_VACATION:
                $this->generateVacation(!empty($filter['disable']));
                break;

            case Ingo_Storage::ACTION_SPAM:
                $this->generateSpamfilter(!empty($filter['disable']));
                break;

            default:
                if (in_array($filter['action'], $this->_actions)) {
                    /* Create filter if using AND. */
                    $recipe = new Ingo_Script_Maildrop_Recipe($filter, $this->_params);
                    foreach ($filter['conditions'] as $condition) {
                        $recipe->addCondition($condition);
                    }
                    $this->addItem(new Ingo_Script_Maildrop_Comment($filter['name'], !empty($filter['disable']), true));
                    $this->addItem($recipe);
                }
            }
        }

        return $this->toCode();
    }

    /**
     * Generates the maildrop script to handle the blacklist specified in
     * the rules.
     *
     * @param boolean $disable  Disable the blacklist?
     */
    public function generateBlacklist($disable = false)
    {
        $blacklist = $GLOBALS['injector']->getInstance('Ingo_Factory_Storage')->create()->retrieve(Ingo_Storage::ACTION_BLACKLIST);
        $bl_addr = $blacklist->getBlacklist();
        $bl_folder = $blacklist->getBlacklistFolder();

        $bl_type = empty($bl_folder)
            ? Ingo_Storage::ACTION_DISCARD
            : Ingo_Storage::ACTION_MOVE;

        if (!empty($bl_addr)) {
            $this->addItem(new Ingo_Script_Maildrop_Comment(_("Blacklisted Addresses"), $disable, true));
            $params = array('action-value' => $bl_folder,
                            'action' => $bl_type,
                            'disable' => $disable);

            foreach ($bl_addr as $address) {
                if (!empty($address)) {
                    $recipe = new Ingo_Script_Maildrop_Recipe($params, $this->_params);
                    $recipe->addCondition(array('field' => 'From', 'value' => $address));
                    $this->addItem($recipe);
                }
            }
        }
    }

    /**
     * Generates the maildrop script to handle the whitelist specified in
     * the rules.
     *
     * @param boolean $disable  Disable the whitelist?
     */
    public function generateWhitelist($disable = false)
    {
        $whitelist = $GLOBALS['injector']->getInstance('Ingo_Factory_Storage')->create()->retrieve(Ingo_Storage::ACTION_WHITELIST);
        $wl_addr = $whitelist->getWhitelist();

        if (!empty($wl_addr)) {
            $this->addItem(new Ingo_Script_Maildrop_Comment(_("Whitelisted Addresses"), $disable, true));
            foreach ($wl_addr as $address) {
                if (!empty($address)) {
                    $recipe = new Ingo_Script_Maildrop_Recipe(array('action' => Ingo_Storage::ACTION_KEEP, 'disable' => $disable), $this->_params);
                    $recipe->addCondition(array('field' => 'From', 'value' => $address));
                    $this->addItem($recipe);
                }
            }
        }
    }

    /**
     * Generates the maildrop script to handle mail forwards.
     *
     * @param boolean $disable  Disable forwarding?
     */
    public function generateForward($disable = false)
    {
        $forward = $GLOBALS['injector']->getInstance('Ingo_Factory_Storage')->create()->retrieve(Ingo_Storage::ACTION_FORWARD);
        $addresses = $forward->getForwardAddresses();

        if (!empty($addresses)) {
            $this->addItem(new Ingo_Script_Maildrop_Comment(_("Forwards"), $disable, true));
            $params = array('action' => Ingo_Storage::ACTION_FORWARD,
                            'action-value' => $addresses,
                            'disable' => $disable);
            if ($forward->getForwardKeep()) {
                $params['action'] = self::MAILDROP_STORAGE_ACTION_STOREANDFORWARD;
            }
            $recipe = new Ingo_Script_Maildrop_Recipe($params, $this->_params);
            $recipe->addCondition(array('field' => 'From', 'value' => ''));
            $this->addItem($recipe);
        }
    }

    /**
     * Generates the maildrop script to handle vacation messages.
     *
     * @param boolean $disable  Disable forwarding?
     */
    public function generateVacation($disable = false)
    {
        $vacation = $GLOBALS['injector']->getInstance('Ingo_Factory_Storage')->create()->retrieve(Ingo_Storage::ACTION_VACATION);
        $addresses = $vacation->getVacationAddresses();
        $actionval = array('addresses' => $addresses,
                           'subject' => $vacation->getVacationSubject(),
                           'days' => $vacation->getVacationDays(),
                           'ignorelist' => $vacation->getVacationIgnorelist(),
                           'excludes' => $vacation->getVacationExcludes(),
                           'start' => $vacation->getVacationStart(),
                           'end' => $vacation->getVacationEnd());

        if (!empty($addresses)) {
            $this->addItem(new Ingo_Script_Maildrop_Comment(_("Vacation"), $disable, true));
            $params = array('action' => Ingo_Storage::ACTION_VACATION,
                            'action-value' => $actionval,
                            'disable' => $disable);
            $recipe = new Ingo_Script_Maildrop_Recipe($params, $this->_params);
            $this->addItem($recipe);
            $this->_reason = Ingo::getReason($vacation->getVacationReason(),
                                             $vacation->getVacationStart(),
                                             $vacation->getVacationEnd());
        }
    }

    /**
     * Generates the maildrop script to handle spam as identified by
     * SpamAssassin.
     *
     * @param boolean $disable  Disable the spam-filter?
     */
    public function generateSpamfilter($disable = false)
    {
        $spam = $GLOBALS['injector']->getInstance('Ingo_Factory_Storage')->create()->retrieve(Ingo_Storage::ACTION_SPAM);
        if ($spam == false) {
            return;
        }

        $spam_folder = $spam->getSpamFolder();
        $spam_action = (empty($spam_folder)) ? Ingo_Storage::ACTION_DISCARD : Ingo_Storage::ACTION_MOVE;

        $this->addItem(new Ingo_Script_Maildrop_Comment(_("Spam Filter"), $disable, true));

        $params = array('action-value' => $spam_folder,
                        'action' => $spam_action,
                        'disable' => $disable);
        $recipe = new Ingo_Script_Maildrop_Recipe($params, $this->_params);
        if ($this->_params['spam_compare'] == 'numeric') {
            $recipe->addCondition(array('match' => 'greater than or equal to',
                                        'field' => $this->_params['spam_header'],
                                        'value' => $spam->getSpamLevel()));
        } elseif ($this->_params['spam_compare'] == 'string') {
            $recipe->addCondition(array('match' => 'contains',
                                        'field' => $this->_params['spam_header'],
                                        'value' => str_repeat($this->_params['spam_char'], $spam->getSpamLevel())));
        }

        $this->addItem($recipe);
    }

    /**
     * Returns any additional scripts that need to be sent to the transport
     * layer.
     *
     * @return array  A list of scripts with script names as keys and script
     *                code as values.
     */
    public function additionalScripts()
    {
        return array('vacation.msg' => $this->_reason);
    }

    /**
     * Adds an item to the recipe list.
     *
     * @param object $item  The item to add to the recipe list.
     *                      The object should have a generate() function.
     */
    public function addItem($item)
    {
        $this->_recipes[] = $item;
    }

}
