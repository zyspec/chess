<?php
// $Id: index.php,v 1.1 2004/01/29 14:45:49 buennagel Exp $
//  ------------------------------------------------------------------------ //
//                XOOPS - PHP Content Management System                      //
//                    Copyright (c) 2000 XOOPS.org                           //
//                       <http://www.xoops.org/>                             //
// ------------------------------------------------------------------------- //
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

include '../../mainfile.php';
$xoopsOption['template_main'] = 'chess_games.html';
include_once XOOPS_ROOT_PATH . '/header.php';
require_once XOOPS_ROOT_PATH . '/modules/chess/include/constants.inc.php';

#var_dump($_REQUEST);#*#DEBUG#
chess_get_games();

include_once XOOPS_ROOT_PATH.'/footer.php';

// -----------------------
function chess_get_games()
{
	global $xoopsDB, $xoopsTpl;

	$member_handler = xoops_getHandler('member');

	$games_table = $xoopsDB->prefix('chess_games');

	$result = $xoopsDB->query(trim("
		SELECT game_id, fen_active_color, white_uid, black_uid, pgn_result, UNIX_TIMESTAMP(create_date) AS create_date,
			UNIX_TIMESTAMP(start_date) AS start_date, UNIX_TIMESTAMP(last_date) AS last_date
		FROM $games_table
		ORDER BY last_date DESC, start_date DESC, create_date DESC
	"));

	$games = [];

 	while ($row = $xoopsDB->fetchArray($result)) {

		$user_white     = $member_handler->getUser($row['white_uid']);
		$username_white =  is_object($user_white) ? $user_white->getVar('uname') : '(open)';

		$user_black     = $member_handler->getUser($row['black_uid']);
		$username_black =  is_object($user_black) ? $user_black->getVar('uname') : '(open)';

		$games[] = [
			'game_id'          => $row['game_id'],
			'username_white'   => $username_white,
			'username_black'   => $username_black,
			'create_date'      => $row['create_date'],
			'start_date'       => $row['start_date'],
			'last_date'        => $row['last_date'],
			'fen_active_color' => $row['fen_active_color'],
			'pgn_result'       => $row['pgn_result'],
        ];
	}

	$xoopsDB->freeRecordSet($result);

	$xoopsTpl->assign('chess_games', $games);

	$challenges_table = $xoopsDB->prefix('chess_challenges');

	 $result = $xoopsDB->query(trim("
		SELECT challenge_id, game_type, color_option, player1_uid, player2_uid, UNIX_TIMESTAMP(create_date) AS create_date
		FROM $challenges_table
		ORDER BY create_date DESC
	"));

	$challenges = [];

 	while ($row = $xoopsDB->fetchArray($result)) {

		$user_player1     = $member_handler->getUser($row['player1_uid']);
		$username_player1 =  is_object($user_player1) ? $user_player1->getVar('uname') : '?';

		$user_player2     = $member_handler->getUser($row['player2_uid']);
		$username_player2 =  is_object($user_player2) ? $user_player2->getVar('uname') : '?';

		$challenges[] = [
			'challenge_id'     => $row['challenge_id'],
			'game_type'        => $row['game_type'],
			'color_option'     => $row['color_option'],
			'username_player1' => $username_player1,
			'username_player2' => $username_player2,
			'create_date'      => $row['create_date'],
        ];
	}

	$xoopsDB->freeRecordSet($result);

	$xoopsTpl->assign('chess_challenges', $challenges);

	$xoopsTpl->assign('chess_date_format', _MEDIUMDATESTRING);
}

?>
