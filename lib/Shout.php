<?php
/**
 * Shout:: defines an set of classes for the Shout application.
 *
 * $Id$
 *
 * Copyright 2005 Ben Klang <ben@alkaloid.net>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Ben Klang <ben@alkaloid.net>
 * @version $Revision: 94 $
 * @since   Shout 0.1
 * @package Shout
 */

class Shout
{
    var $applist = array();
    var $_applist_curapp = '';
    var $_applist_curfield = '';

    /**
     * Build Shout's list of menu items.
     *
     * @access public
     */
    static public function getMenu($returnType = 'object')
    {
        global $conf, $context, $section, $action;

        require_once 'Horde/Menu.php';

        $menu = new Horde_Menu(HORDE_MENU_MASK_ALL);
        $permprefix = "shout:contexts:$context";

        if (isset($context) && $section == "usermgr" &&
            Shout::checkRights("$permprefix:users",
                PERMS_EDIT, 1)) {
            $url = Horde::applicationUrl("index.php");
            $url = Horde_Util::addParameter($url, array('context' => $context,
                                                  'section' => $section,
                                                  'action' => 'add'));

            # Goofy hack to make the icon make a little more sense
            # when editing/deleting users
//             if (!isset($action)) {
                $icontitle = "Add";
//             } else {
//                 $icontitle = $action;
//                 $icontitle[0] = strtoupper($action[0]);
//             }
            # End goofy hack

            $menu->add($url, _("$icontitle User"), "add-user.gif");
        }

        if (isset($context) && isset($section) && $section == "dialplan" &&
            Shout::checkRights("$permprefix:dialplan",
                PERMS_EDIT, 1)) {
            $url = Horde::applicationUrl("dialplan.php");
            $url = Horde_Util::addParameter($url, array('context' => $context,
                                                  'section' => $section,
                                                  'action' => 'add'));

            # Goofy hack to make the icon make a little sense
            # when editing/deleting users
            if (!isset($action)) {
                $icontitle = "Add";
            } else {
                $icontitle = $action;
                $icontitle[0] = strtoupper($action[0]);
            }
            # End goofy hack

            $menu->add($url, _("$icontitle Extension"), "add-extension.gif");
        }

        if ($returnType == 'object') {
            return $menu;
        } else {
            return $menu->render();
        }
    }

    /**
     * Generate the tabs at the top of each Shout pages
     *
     * @param &$vars Reference to the passed in variables
     *
     * @return object Horde_UI_Tabs
     */
    static public function getTabs($context, &$vars)
    {
        global $shout;
        $perms = Horde_Perms::singleton();

        $permprefix = 'shout:contexts:' . $context;

        $tabs = new Horde_UI_Tabs('section', $vars);

        if (Shout::checkRights($permprefix . ':extensions', null, 1)) {
            $url = Horde::applicationUrl('extensions.php');
            $url = Horde_Util::addParameter($url, 'context', $context);
            $tabs->addTab(_("_Extensions"), $url, 'usermgr');
        }

        if (Shout::checkRights($permprefix . ':dialplan', null, 1)) {
            $url = Horde::applicationUrl('dialplan.php');
            $url = Horde_Util::addParameter($url, 'context', $context);
            $tabs->addTab(_("_Automated Attendant"), $url, 'dialplan');
        }

        if (Shout::checkRights($permprefix . ':conference', null, 1)) {
            $url = Horde::applicationUrl('conference.php');
            $url = Horde_Util::addParameter($url, 'context', $context);
            $tabs->addTab(_("_Conference Rooms"), $url, 'conference');
        }

       if (Shout::checkRights($permprefix . ':moh', null, 1)) {
            $url = Horde::applicationUrl('moh.php');
            $url = Horde_Util::addParameter($url, 'context', $context);
            $tabs->addTab(_("_Music on Hold"), $url, 'moh');
        }

        return $tabs;
    }

    /**
     * Checks for the given permissions for the current user on the given
     * permission.  Optionally check for higher-level permissions and ultimately
     * test for superadmin priveleges.
     *
     * @param string $permname Name of the permission to check
     *
     * @param optional int $permmask Bitfield of permissions to check for
     *
     * @param options int $numparents Check for the same permissions this
     *                                many levels up the tree
     *
     * @return boolean the effective permissions for the user.
     */
    static public function checkRights($permname, $permmask = null, $numparents = 0)
    {
        if (Horde_Auth::isAdmin()) { return true; }

        $perms = Horde_Perms::singleton();
        if ($permmask === null) {
            $permmask = PERMS_SHOW|PERMS_READ;
        }

        # Default deny all permissions
        $user = 0;
        $superadmin = 0;

        $superadmin = $perms->hasPermission('shout:superadmin',
            Horde_Auth::getAuth(), $permmask);

        while ($numparents >= 0) {
            $tmpuser = $perms->hasPermission($permname,
                Horde_Auth::getAuth(), $permmask);

            $user = $user | $tmpuser;
            if ($numparents > 0) {
                $pos = strrpos($permname, ':');
                if ($pos) {
                    $permname = substr($permname, 0, $pos);
                }
            }
            $numparents--;
        }
        $test = $superadmin | $user;
$ret = ($test & $permmask) == $permmask;
print "Shout::checkRights() returning $ret";
        return ($test & $permmask) == $permmask;
    }
}
