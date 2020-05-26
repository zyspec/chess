<?php

//  ------------------------------------------------------------------------ //
//                XOOPS - PHP Content Management System                      //
//                    Copyright (c) 2000 XOOPS.org                           //
//                       <https://xoops.org>                             //
//  ------------------------------------------------------------------------ //
//  This program is free software; you can redistribute it and/or modify     //
//  it under the terms of the GNU General Public License as published by     //
//  the Free Software Foundation; either version 2 of the License, or        //
//  (at your option) any later version.                                      //
//                                                                           //
//  You may not change or alter any portion of this comment or credits       //
//  of supporting developers from this source code or any supporting         //
//  source code which is considered copyrighted (c) material of the          //
//  original comment or credit authors.                                      //
//                                                                           //
//  This program is distributed in the hope that it will be useful,          //
//  but WITHOUT ANY WARRANTY; without even the implied warranty of           //
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            //
//  GNU General Public License for more details.                             //
//                                                                           //
//  You should have received a copy of the GNU General Public License        //
//  along with this program; if not, write to the Free Software              //
//  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307 USA //
//  ------------------------------------------------------------------------ //

/**
 * Admin page
 *
 * @package    chess
 * @subpackage admin
 */

use Xmf\Request;

/**#@+
 */
require_once __DIR__ . '/admin_header.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsformloader.php';
require_once XOOPS_ROOT_PATH . '/class/pagenav.php';
require_once XOOPS_ROOT_PATH . '/modules/chess/include/functions.php';

// user input
$op    = Request::getCmd('op', '', 'GET');;
$start = Request::getInt('start', 0, 'GET'); // offset of first row of table to display (default to 0)

/** @var \XoopsModules\Chess\Helper $helper */
// get maximum number of items to display on a page, and constrain it to a reasonable value
$max_items_to_display = $helper->getConfig('max_items');
$max_items_to_display = min(max($max_items_to_display, 1), 1000);

xoops_cp_header();

$memberHandler = xoops_getHandler('member');

switch ($op) {
    case 'suspended_games':

        /* Two queries are performed, one without a limit clause to count the total number of rows for the page navigator,
         * and one with a limit clause to get the data for display on the current page.
         *
         * SQL_CALC_FOUND_ROWS and FOUND_ROWS(), available in MySQL 4.0.0, provide a more efficient way of doing this.
        */

        $games_table = $GLOBALS['xoopsDB']->prefix('chess_games');
        $result      = $GLOBALS['xoopsDB']->query("SELECT COUNT(*) FROM $games_table WHERE suspended != ''");
        [$num_rows]  = $GLOBALS['xoopsDB']->fetchRow($result);
        $GLOBALS['xoopsDB']->freeRecordSet($result);

        /* Sort by date-suspended in ascending order, so that games that were suspended the earliest will be displayed
         * at the top, and can more easily be arbitrated on a first-come first-serve basis.
         * Note that the suspended column begins with the date-suspended in the format 'YYYY-MM-DD HH:MM:SS', so the sorting
         * will work as desired.
         */

        $result = $GLOBALS['xoopsDB']->query(
            trim(
                "
        SELECT   game_id, white_uid, black_uid, UNIX_TIMESTAMP(start_date) AS start_date, suspended
        FROM     $games_table
        WHERE    suspended != ''
        ORDER BY suspended
        LIMIT    $start, $max_items_to_display
    "
                )
            );

        if ($GLOBALS['xoopsDB']->getRowsNum($result) > 0) {
            echo '<h3>' . _AM_CHESS_SUSPENDED_GAMES . "</h3>\n";

            while (false !== ($row = $GLOBALS['xoopsDB']->fetchArray($result))) {
                $user_white     = $memberHandler->getUser($row['white_uid']);
                $username_white = $user_white instanceof \XoopsUser ? $user_white->getVar('uname') : '(open)';
                $user_black     = $memberHandler->getUser($row['black_uid']);
                $username_black = $user_black instanceof \XoopsUser ? $user_black->getVar('uname') : '(open)';
                //@todo move hard coded language string to language file --------------vvv
                $date = $row['start_date'] ? date('Y.m.d', $row['start_date']) : 'not yet started';

                $title_text = _AM_CHESS_GAME . " #{$row['game_id']}&nbsp;&nbsp;&nbsp;$username_white " . _AM_CHESS_VS . " $username_black&nbsp;&nbsp;&nbsp;($date)";

                $form = new \XoopsThemeForm($title_text, "game_{$row['game_id']}", $helper->url("game.php?game_id={$row['game_id']}"), 'post', true);

                [$date, $suspender_uid, $type, $explain] = explode('|', $row['suspended']);

                switch ($type) {
                    case 'arbiter_suspend':
                        $type_display = _AM_CHESS_SUSP_TYPE_ARBITER;
                        break;
                    case 'want_arbitration':
                        $type_display = _AM_CHESS_SUSP_TYPE_PLAYER;
                        break;
                    default:
                        $type_display = _AM_CHESS_ERROR;
                        break;
                }

                $suspender_user     = $memberHandler->getUser($suspender_uid);
                $suspender_username = $suspender_user instanceof \XoopsUser ? $suspender_user->getVar('uname') : _AM_CHESS_UNKNOWN_USER;

                $form->addElement(new \XoopsFormLabel(_AM_CHESS_WHEN_SUSPENDED . ':', formatTimestamp(strtotime($date))));
                $form->addElement(new \XoopsFormLabel(_AM_CHESS_SUSPENDED_BY . ':', $suspender_username));
                $form->addElement(new \XoopsFormLabel(_AM_CHESS_SUSPENSION_TYPE . ':', $type_display));
                $form->addElement(new \XoopsFormLabel(_AM_CHESS_SUSPENSION_REASON . ':', $explain));
                $form->addElement(new \XoopsFormButton('', 'submit', _AM_CHESS_ARBITRATE_SUBMIT, 'submit'));
                $form->addElement(new \XoopsFormHidden('show_arbiter_ctrl', 1));
                $form->display();
            }

            $pagenav = new \XoopsPageNav($num_rows, $max_items_to_display, $start, 'start', "op={$op}");

            echo '<div class="center">' . $pagenav->renderNav() . "&nbsp;</div>\n";
        } else {
            echo '<h3>' . _AM_CHESS_NO_SUSPENDED_GAMES . "</h3>\n";
        }

        $GLOBALS['xoopsDB']->freeRecordSet($result);
        break;

    case 'active_games':
        /* Two queries are performed, one without a limit clause to count the total number of rows for the page navigator,
         * and one with a limit clause to get the data for display on the current page.
         *
         * SQL_CALC_FOUND_ROWS and FOUND_ROWS(), available in MySQL 4.0.0, provide a more efficient way of doing this.
         */

        $games_table = $GLOBALS['xoopsDB']->prefix('chess_games');
        $result      = $GLOBALS['xoopsDB']->query("SELECT COUNT(*) FROM $games_table WHERE pgn_result = '*' AND suspended = ''");
        [$num_rows]  = $GLOBALS['xoopsDB']->fetchRow($result);
        $GLOBALS['xoopsDB']->freeRecordSet($result);

        $result = $GLOBALS['xoopsDB']->query(
            trim(
                "
        SELECT   game_id, white_uid, black_uid, UNIX_TIMESTAMP(start_date) AS start_date, GREATEST(create_date,start_date,last_date) AS most_recent_date
        FROM     $games_table
        WHERE    pgn_result = '*' AND suspended = ''
        ORDER BY most_recent_date DESC
        LIMIT    $start, $max_items_to_display
    "
                )
            );

        if ($GLOBALS['xoopsDB']->getRowsNum($result) > 0) {
            echo '<h3>' . _AM_CHESS_ACTIVE_GAMES . "</h3>\n";

            while (false !== ($row = $GLOBALS['xoopsDB']->fetchArray($result))) {
                $user_white     = $memberHandler->getUser($row['white_uid']);
                $username_white = $user_white instanceof \XoopsUser ? $user_white->getVar('uname') : '(' . _CHESS_GAMETYPE_OPEN . ')';
                $user_black     = $memberHandler->getUser($row['black_uid']);
                $username_black = $user_black instanceof \XoopsUser ? $user_black->getVar('uname') : '(' . _CHESS_GAMETYPE_OPEN . ')';

                $date = $row['start_date'] ? date('Y.m.d', $row['start_date']) : 'not yet started';

                $title_text = _AM_CHESS_GAME . " #{$row['game_id']}&nbsp;&nbsp;&nbsp;$username_white " . _AM_CHESS_VS . " $username_black&nbsp;&nbsp;&nbsp;($date)";

                $form = new \XoopsThemeForm($title_text, "game_{$row['game_id']}", $helper->url("game.php?game_id={$row['game_id']}"), 'post', true);
                $form->addElement(new \XoopsFormButton('', 'submit', _AM_CHESS_ARBITRATE_SUBMIT, 'submit'));
                $form->addElement(new \XoopsFormHidden('show_arbiter_ctrl', 1));
                $form->display();
            }

            $pagenav = new \XoopsPageNav($num_rows, $max_items_to_display, $start, 'start', "op=$op");

            echo '<div class="center">' . $pagenav->renderNav() . "&nbsp;</div>\n";
        } else {
            echo '<h3>' . _AM_CHESS_NO_ACTIVE_GAMES . "</h3>\n";
        }

        $GLOBALS['xoopsDB']->freeRecordSet($result);
        break;

    case 'challenges':
        /* Two queries are performed, one without a limit clause to count the total number of rows for the page navigator,
         * and one with a limit clause to get the data for display on the current page.
         *
         * SQL_CALC_FOUND_ROWS and FOUND_ROWS(), available in MySQL 4.0.0, provide a more efficient way of doing this.
         */

        $challenges_table = $GLOBALS['xoopsDB']->prefix('chess_challenges');
        $result           = $GLOBALS['xoopsDB']->query("SELECT COUNT(*) FROM $challenges_table");
        [$num_rows]       = $GLOBALS['xoopsDB']->fetchRow($result);
        $GLOBALS['xoopsDB']->freeRecordSet($result);

        $result = $GLOBALS['xoopsDB']->query(
            trim(
                "
        SELECT   challenge_id, game_type, color_option, player1_uid, player2_uid, UNIX_TIMESTAMP(create_date) AS create_date
        FROM     $challenges_table
        ORDER BY create_date DESC
        LIMIT    $start, $max_items_to_display
    "
                )
            );

        if ($GLOBALS['xoopsDB']->getRowsNum($result) > 0) {
            echo '<h3>' . _AM_CHESS_CHALLENGES . "</h3>\n";

            while (false !== ($row = $GLOBALS['xoopsDB']->fetchArray($result))) {
                $user_player1     = $memberHandler->getUser($row['player1_uid']);
                $username_player1 = $user_player1 instanceof \XoopsUser ? $user_player1->getVar('uname') : '?';
                $user_player2     = $memberHandler->getUser($row['player2_uid']);
                $username_player2 = $user_player2 instanceof \XoopsUser ? $user_player2->getVar('uname') : '(open)';

                $date = date('Y.m.d', $row['create_date']);

                $title_text = _AM_CHESS_CHALLENGE . " #{$row['challenge_id']}&nbsp;&nbsp;&nbsp;$username_player1 " . _AM_CHESS_CHALLENGED . ": $username_player2&nbsp;&nbsp;&nbsp;(" . _AM_CHESS_CREATED . " $date)";

                $form = new \XoopsThemeForm($title_text, "challenge_{$row['challenge_id']}", XOOPS_URL . '/modules/' . $xoopsModule->getVar('dirname') . "/create.php?challenge_id={$row['challenge_id']}", 'post', true);
                $form->addElement(new \XoopsFormButton('', 'submit', _AM_CHESS_ARBITRATE_SUBMIT, 'submit'));
                $form->addElement(new \XoopsFormHidden('show_arbiter_ctrl', 1));
                $form->display();
            }

            $pagenav = new \XoopsPageNav($num_rows, $max_items_to_display, $start, 'start', "op=$op");

            echo '<div class="center">' . $pagenav->renderNav() . "&nbsp;</div>\n";
        } else {
            echo '<h3>' . _AM_CHESS_NO_CHALLENGES . "</h3>\n";
        }

        $GLOBALS['xoopsDB']->freeRecordSet($result);
        break;

    default:
        $helper->redirect('admin/index.php', _CHESS_REDIRECT_DELAY_IMMEDIATE, ''); // should never get here, but jump to index just in case
        echo '<h4> ' . _AM_CHESS_CONF . ' </h4>'
        //    <table width='100%' border='0' cellspacing='1' class='outer'>
        //    <tr>
        //        <td><a href='" . XOOPS_URL . '/modules/' . $xoopsModule->getVar('dirname') . "/admin/index.php?op=suspended_games'>" . _AM_CHESS_SUSPENDED_GAMES . '</a>
        //        <td>' . _AM_CHESS_SUSPENDED_GAMES_DES . "</td>
        //    </tr>
        //    <tr>
        //        <td><a href='" . XOOPS_URL . '/modules/' . $xoopsModule->getVar('dirname') . "/admin/index.php?op=active_games'>" . _AM_CHESS_ACTIVE_GAMES . '</a>
        //        <td>' . _AM_CHESS_ACTIVE_GAMES_DES . "</td>
        //    </tr>
        //    <tr>
        //        <td><a href='" . XOOPS_URL . '/modules/' . $xoopsModule->getVar('dirname') . "/admin/index.php?op=challenges'>" . _AM_CHESS_CHALLENGES . '</a>
        //        <td>' . _AM_CHESS_CHALLENGES_DES . "</td>
        //    </tr>
        //    <tr>
        //        <td><a href='" . XOOPS_URL . '/modules/system/admin.php?fct=preferences&amp;op=showmod&amp;mod=' . $xoopsModule->getVar('mid') . "'>" . _AM_CHESS_PREFS . '</a>
        //        <td>' . _AM_CHESS_PREFS_DESC . '</td>
        //    </tr>
        //    </table>
        //'
        ;
        break;
}

xoops_cp_footer();
/**#@-*/
