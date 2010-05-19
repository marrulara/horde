<?php
/**
 * $Horde: mnemo/notes/index.php,v 1.5 2009/12/04 17:42:24 jan Exp $
 *
 * Copyright 2001-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL). If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 */

@define('MNEMO_BASE', dirname(dirname(__FILE__)));
require_once MNEMO_BASE . '/lib/Application.php';
Horde_Registry::appInit('mnemo');

$search = Horde_Util::getGet('q');
if (!$search) {
    header('HTTP/1.0 204 No Content');
    exit;
}

$memos = Mnemo::listMemos($prefs->getValue('sortby'), $prefs->getValue('sortdir'));

$search_pattern = '/^' . preg_quote($search, '/') . '/i';
$search_results = array();
foreach ($memos as $memo_id => $memo) {
    if (preg_match($search_pattern, $memo['desc'])) {
        $search_results[$memo_id] = $memo;
    }
}

if (count($search_results) == 1) {
    $note = array_shift($search_results);
    header('Location: ' . Horde::applicationUrl(Horde_Util::addParameter('view.php', array('memo' => $note['memo_id'], 'memolist' => $note['memolist_id'])), true));
    exit;
}

$title = _("Search Results");
$memos = $search_results;

Horde::addScriptFile('tooltips.js', 'horde', true);
Horde::addScriptFile('tables.js', 'horde', true);
Horde::addScriptFile('prototype.js', 'horde', true);
Horde::addScriptFile('QuickFinder.js', 'horde', true);

require MNEMO_TEMPLATES . '/common-header.inc';
require MNEMO_TEMPLATES . '/menu.inc';
require MNEMO_TEMPLATES . '/list/header.inc';

if (count($memos)) {
    $cManager = new Horde_Prefs_CategoryManager();
    $colors = $cManager->colors();
    $fgcolors = $cManager->fgColors();
    $sortby = $prefs->getValue('sortby');
    $sortdir = $prefs->getValue('sortdir');
    $showNotepad = $prefs->getValue('show_notepad');

    $baseurl = 'list.php';
    require MNEMO_TEMPLATES . '/list/memo_headers.inc';

    foreach ($memos as $memo_id => $memo) {
        $viewurl = Horde_Util::addParameter(
            'view.php',
            array('memo' => $memo['memo_id'],
                  'memolist' => $memo['memolist_id']));

        $memourl = Horde_Util::addParameter(
            'memo.php', array('memo' => $memo['memo_id'],
                              'memolist' => $memo['memolist_id']));
        try {
            $share = $GLOBALS['mnemo_shares']->getShare($memo['memolist_id']);
            $notepad = $share->get('name');
        } catch (Horde_Share_Exception $e) {
            $notepad = $memo['memolist_id'];
        }

        require MNEMO_TEMPLATES . '/list/memo_summaries.inc';
    }

    require MNEMO_TEMPLATES . '/list/memo_footers.inc';
} else {
    require MNEMO_TEMPLATES . '/list/empty.inc';
}

require MNEMO_TEMPLATES . '/panel.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
