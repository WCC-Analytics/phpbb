<?php
/***************************************************************************
 *                           admin_permissions.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id$
 *
 ***************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

if ( !empty($setmodules) )
{
	if ( !$acl->get_acl_admin('auth') )
	{
		return;
	}
	
	$filename = basename(__FILE__);
	$module['Forums']['Permissions']   = $filename . $SID . '&amp;mode=forums';
	$module['Forums']['Moderators']   = $filename . $SID . '&amp;mode=moderators';
	$module['General']['Administrators']   = $filename . $SID . '&amp;mode=administrators';

	return;
}

define('IN_PHPBB', 1);
//
// Include files
//
$phpbb_root_path = '../';
require($phpbb_root_path . 'extension.inc');
require('pagestart.' . $phpEx);

//
// Do we have forum admin permissions?
//
if ( !$acl->get_acl_admin('auth') )
{
	message_die(MESSAGE, $lang['No_admin']);
}

//
// Define some vars
//
if ( isset($HTTP_GET_VARS['f']) || isset($HTTP_POST_VARS['f']) )
{
	$forum_id = ( isset($HTTP_POST_VARS['f']) ) ? intval($HTTP_POST_VARS['f']) : intval($HTTP_GET_VARS['f']);
	$forum_sql = " WHERE forum_id = $forum_id";
}
else
{
	unset($forum_id);
	$forum_sql = '';
}

$mode = ( isset($HTTP_GET_VARS['mode']) ) ? $HTTP_GET_VARS['mode'] : $HTTP_POST_VARS['mode'];

//
// Start program proper
//
switch ( $mode )
{
	case 'forums':
		$l_title = $lang['Permissions'];
		$l_title_explain = $lang['Permissions_explain'];
		$l_can = '_can';
		break;
	case 'moderators':
		$l_title = $lang['Moderators'];
		$l_title_explain = $lang['Moderators_explain'];
		$l_can = '_can';
		break;
	case 'administrators':
		$l_title = $lang['Administrators'];
		$l_title_explain = $lang['Administrators_explain'];
		$l_can = '_can_admin';
		break;
}

//
// Get required information, either all forums if
// no id was specified or just the requsted if it
// was
//
if ( !empty($forum_id) || $mode == 'administrators' )
{
	//
	// Clear some vars, grab some info if relevant ...
	//
	$s_hidden_fields = '';
	if ( !empty($forum_id) )
	{
		$sql = "SELECT forum_name  
			FROM " . FORUMS_TABLE . " 
			WHERE forum_id = $forum_id";
		$result = $db->sql_query($sql);

		$forum_info = $db->sql_fetchrow($result);

		$l_title .= ' : <i>' . $forum_info['forum_name'] . '</i>';
	}

	//
	// Generate header
	// 
	page_header($l_title);

?>

<h1><?php echo $l_title; ?></h1>

<p><?php echo $l_title_explain; ?></p>

<?php

	switch ( $mode )
	{
		case 'forums':

			$type_sql = 'forum';
			$forum_sql = "AND a.forum_id = $forum_id";

			break;

		case 'moderators':

			$type_sql = 'mod';
			$forum_sql = "AND a.forum_id = $forum_id";

			break;

		case 'administrators':

			$type_sql = 'admin';
			$forum_sql = '';

			break;
	}

	$sql = "SELECT group_id, group_name  
		FROM " . GROUPS_TABLE;
	$result = $db->sql_query($sql);

	$group_list = '';
	while ( $row = $db->sql_fetchrow($result) ) 
	{
		$group_list .= '<option value="' . $row['group_id'] . '">' . $row['group_name'] . '</option>';
	}
	$db->sql_freeresult($result);

	if ( empty($HTTP_POST_VARS['advanced']) || empty($HTTP_POST_VARS['entries']) )
	{

?>

<table width="100%" cellspacing="0" cellpadding="0" border="0">
	<tr>
		<td align="center"><h1><?php echo $lang['Users']; ?></h1></td>
		<td align="center"><h1><?php echo $lang['Groups']; ?></h1></td>
	</tr>
	<tr>

		<td><form method="post" name="adminusers" action="<?php echo "admin_permissions.$phpEx$SID&amp;mode=$mode"; ?>"><table width="90%" class="bg" cellspacing="1" cellpadding="4" border="0" align="center">
<?php

		$sql = "SELECT DISTINCT u.user_id, u.username  
			FROM " . USERS_TABLE . " u, " . ACL_USERS_TABLE . " a, " . ACL_OPTIONS_TABLE . " o 
			WHERE o.auth_type LIKE '$type_sql' 
				AND a.auth_option_id = o.auth_option_id 
				$forum_sql 
				AND u.user_id = a.user_id
			ORDER BY u.username, u.user_regdate ASC";
		$result = $db->sql_query($sql);

		$users = '';
		while ( $row = $db->sql_fetchrow($result) )
		{
			$users .= '<option value="' . $row['user_id'] . '">' . $row['username'] . '</option>';
		}
		$db->sql_freeresult($result);

?>
			<tr>
				<th><?php echo $lang['Manage_users']; ?></th>
			</tr>
			<tr>
				<td class="row1" align="center"><select style="width:280px" name="entries[]" multiple="multiple" size="5"><?php echo $users; ?></select></td>
			</tr>
			<tr>
				<td class="cat" align="center"><input class="liteoption" type="submit" name="delete" value="<?php echo $lang['Remove_selected']; ?>" /> &nbsp; <input class="liteoption" type="submit" name="advanced" value="<?php echo $lang['Advanced']; ?>" /><input type="hidden" name="type" value="user" /><input type="hidden" name="f" value="<?php echo $forum_id; ?>" /></td>
			</tr>
		</table></form></td>

		<td align="center"><form method="post" name="admingroups" action="<?php echo "admin_permissions.$phpEx$SID&amp;mode=$mode"; ?>"><table width="90%" class="bg" cellspacing="1" cellpadding="4" border="0" align="center">
<?php

		$sql = "SELECT DISTINCT g.group_id, g.group_name  
			FROM " . GROUPS_TABLE . " g, " . ACL_GROUPS_TABLE . " a, " . ACL_OPTIONS_TABLE . " o 
			WHERE o.auth_type LIKE '$type_sql' 
				$forum_sql 
				AND a.auth_option_id = o.auth_option_id 
				AND g.group_id = a.group_id 
			ORDER BY g.group_name ASC";
		$result = $db->sql_query($sql);

		$groups = '';
		while ( $row = $db->sql_fetchrow($result) )
		{
			$groups .= '<option value="' . $row['group_id'] . '">' . ( ( $row['group_name'] == 'ADMINISTRATORS' ) ? $lang['Admin_group'] : $row['group_name'] ) . '</option>';
		}
		$db->sql_freeresult($result);

?>
		<tr>
			<th><?php echo $lang['Manage_groups']; ?></th>
		</tr>
		<tr>
			<td class="row1" align="center"><select style="width:280px" name="entries[]" multiple="multiple" size="5"><?php echo $groups; ?></select></td>
		</tr>
		<tr>
			<td class="cat" align="center"><input class="liteoption" type="submit" name="delete" value="<?php echo $lang['Remove_selected']; ?>" /> &nbsp; <input class="liteoption" type="submit" name="advanced" value="<?php echo $lang['Advanced']; ?>" /><input type="hidden" name="type" value="group" /><input type="hidden" name="f" value="<?php echo $forum_id; ?>" /></td>
		</tr>
	</table></form></td>

	</tr>
	<tr>

		<td><form method="post" action="<?php echo "admin_permissions.$phpEx$SID&amp;mode=$mode"; ?>"><table class="bg" width="90%" cellspacing="1" cellpadding="4" border="0" align="center">
			<tr> 
				<th><?php echo $lang['Add_users']; ?></th>
			</tr>
			<tr> 
				<td class="row1" align="center"><textarea cols="40" rows="4" name="new"></textarea></td>
			</tr>
			<tr> 
				<td class="cat" align="center"> <input type="submit" name="add" value="<?php echo $lang['Submit']; ?>" class="mainoption" />&nbsp; <input type="reset" value="<?php echo $lang['Reset']; ?>" class="liteoption" />&nbsp; <input type="submit" name="usersubmit" value="<?php echo $lang['Find_username']; ?>" class="liteoption" onClick="window.open('<?php echo "../search.$phpEx$SID"; ?>&amp;mode=searchuser&amp;form=2&amp;field=newuser', '_phpbbsearch', 'HEIGHT=500,resizable=yes,scrollbars=yes,WIDTH=650');return false;" /><input type="hidden" name="type" value="user" /><input type="hidden" name="f" value="<?php echo $forum_id; ?>" /></td>
			</tr>
		</table></form></td>

		<td><form method="post" action="<?php echo "admin_permissions.$phpEx$SID&amp;mode=$mode"; ?>"><table width="90%" class="bg" cellspacing="1" cellpadding="4" border="0" align="center">
			<tr> 
				<th><?php echo $lang['Add_groups']; ?></th>
			</tr>
			<tr> 
				<td class="row1" align="center"><select name="new" multiple="multiple" size="4"><?php echo $group_list; ?></select></td>
			</tr>
			<tr> 
				<td class="cat" align="center"> <input type="submit" name="add" value="<?php echo $lang['Submit']; ?>" class="mainoption" />&nbsp; <input type="reset" value="<?php echo $lang['Reset']; ?>" class="liteoption" /><input type="hidden" name="type" value="group" /><input type="hidden" name="f" value="<?php echo $forum_id; ?>" /></td>
			</tr>
		</table></form></td>

	</tr>
</table>

<?php

	}
	else
	{

		$sql = "SELECT auth_option 
			FROM " . ACL_OPTIONS_TABLE . " 
			WHERE auth_type LIKE '$type_sql'";
		$result = $db->sql_query($sql);

		$auth_options = array();
		while ( $row = $db->sql_fetchrow($result) ) 
		{
			$auth_options[] = $row;
		}
		$db->sql_freeresult($result);

		$where_sql = '';
		foreach ( $HTTP_POST_VARS['entries'] as $id )
		{
			$where_sql .= ( ( $where_sql != '' ) ? ', ' : '' ) . intval($id);
		}

		switch ( $HTTP_POST_VARS['type'] )
		{
			case 'group':
				$l_type = 'Group';

				$sql = "SELECT g.group_id, g.group_name, o.auth_option, a.auth_allow_deny   
					FROM " . GROUPS_TABLE . " g, " . ACL_GROUPS_TABLE . " a, " . ACL_OPTIONS_TABLE . " o 
					WHERE o.auth_type LIKE '$type_sql' 
						AND a.auth_option_id = o.auth_option_id 
						$forum_sql 
						AND g.group_id = a.group_id 
						AND g.group_id IN ($where_sql) 
					ORDER BY g.group_name ASC";
				break;

			case 'user':
				$l_type = 'User';

				$sql = "SELECT u.user_id, u.username, o.auth_option, a.auth_allow_deny 
					FROM " . USERS_TABLE . " u, " . ACL_USERS_TABLE . " a, " . ACL_OPTIONS_TABLE . " o 
					WHERE o.auth_type LIKE '$type_sql' 
						AND a.auth_option_id = o.auth_option_id 
						$forum_sql 
						AND u.user_id = a.user_id
						AND u.user_id IN ($where_sql)  
					ORDER BY u.username, u.user_regdate ASC";
				break;
		}

		$result = $db->sql_query($sql);

		$ug = '';;
		$auth = array();
		while ( $row = $db->sql_fetchrow($result) )
		{
			$ug_name = ( ( $row['group_name'] == 'ADMINISTRATORS' ) ? $lang['Admin_group'] : ( ( $HTTP_POST_VARS['type'] == 'user' ) ? $row['username'] : $row['group_name'] ) );
			$ug .= ( !strstr($ug, $ug_name) ) ? $ug_name . "\n" : '';

			$auth[$row['auth_option']] = ( isset($auth_group[$row['auth_option']]) ) ?  min($auth_group[$row['auth_option']], $row['auth_allow_deny']) : $row['auth_allow_deny'];
		}
		$db->sql_freeresult($result);

?>

<form method="post" action="<?php echo "admin_permissions.$phpEx$SID&amp;mode=$mode"; ?>"><table class="bg" cellspacing="1" cellpadding="4" border="0" align="center">
	<tr>
		<th>&nbsp;<?php echo $lang[$l_type . $l_can]; ?>&nbsp;</th>
		<th>&nbsp;<?php echo $lang['Allow']; ?>&nbsp;</th>
		<th>&nbsp;<?php echo $lang['Deny']; ?>&nbsp;</th>
	</tr>
<?php

		for($i = 0; $i < sizeof($auth_options); $i++)
		{
			$row_class = ( $row_class == 'row1' ) ? 'row2' : 'row1';

			$l_can_cell = ( !empty($lang['acl_admin_' . $auth_options[$i]['auth_option']]) ) ? $lang['acl_admin_' . $auth_options[$i]['auth_option']] : $auth_options[$i]['auth_option'];

			$can_type = ( !empty($auth[$auth_options[$i]['auth_option']]) ) ? ' checked="checked"' : '';
			$cannot_type = ( empty($auth[$auth_options[$i]['auth_option']]) ) ? ' checked="checked"' : '';

?>
	<tr>
		<td class="<?php echo $row_class; ?>"><?php echo $l_can_cell; ?></td>
		<td class="<?php echo $row_class; ?>" align="center"><input type="radio" name="<?php echo $auth_options[$i]['auth_option']; ?>" value="1"<?php echo $can_type; ?> /></td>
		<td class="<?php echo $row_class; ?>" align="center"><input type="radio" name="<?php echo $auth_options[$i]['auth_option']; ?>" value="0"<?php echo $cannot_type; ?> /></td>
	</tr>
<?php

		}

?>
	<tr>
		<th colspan="3"><?php echo $lang['Applies_to_' . $l_type]; ?></th>
	</tr>
	<tr>
		<td class="row1" colspan="3"><textarea cols="40" rows="3"><?php echo $ug; ?></textarea></td>
	</tr>
	<tr>
		<td class="cat" colspan="3" align="center"><input class="mainoption" type="submit" name="update" value="<?php echo $lang['Update']; ?>" />&nbsp;&nbsp;<input class="liteoption" type="submit" name="cancel" value="<?php echo $lang['Cancel']; ?>" /><input type="hidden" name="f" value="<?php echo $forum_id; ?>" /></td>
	</tr>
</table></form>

<?php

	}

}
else
{
	$sql = "SELECT forum_id, forum_name 
		FROM " . FORUMS_TABLE . "  
		ORDER BY cat_id ASC, forum_order ASC";
	$result = $db->sql_query($sql);

	$select_list = '';
	while ( $row = $db->sql_fetchrow($result) )
	{
		$select_list .= '<option value="' . $row['forum_id'] . '">' . $row['forum_name'] . '</option>';
	}
	$db->sql_freeresult($result);

	page_header($l_title);

?>

<h1><?php echo $l_title; ?></h1>

<p><?php echo $l_title_explain ?></p>

<form method="post" action="<?php echo "admin_permissions.$phpEx$SID&amp;mode=$mode"; ?>"><table class="bg" cellspacing="1" cellpadding="4" border="0" align="center">
	<tr>
		<th align="center"><?php echo $lang['Select_a_Forum']; ?></th>
	</tr>
	<tr>
		<td class="row1" align="center">&nbsp;<select name="f"><?php echo $select_list; ?></select> &nbsp;<input type="submit" value="<?php echo $lang['Look_up_Forum']; ?>" class="mainoption" />&nbsp;</td>
	</tr>
</table></form>

<?php

}

page_footer();

?>