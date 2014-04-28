<?php
/**
*
* This file is part of the phpBB Forum Software package.
*
* @copyright (c) phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
* For full copyright and license information, please see
* the docs/CREDITS.txt file.
*
*/

require_once dirname(__FILE__) . '/../../phpBB/includes/functions.php';
require_once dirname(__FILE__) . '/../../phpBB/includes/functions_content.php';
require_once dirname(__FILE__) . '/../../phpBB/includes/functions_posting.php';
require_once dirname(__FILE__) . '/../../phpBB/includes/utf/utf_tools.php';

abstract class phpbb_notification_submit_post_base extends phpbb_database_test_case
{
	protected $notifications, $db, $container, $user, $config, $auth, $cache;

	protected $item_type = '';

	protected $poll_data = array();
	protected $post_data = array(
		'forum_id'		=> 1,
		'topic_id'		=> 1,
		'topic_title'	=> 'topic_title',
		'icon_id'		=> 0,
		'enable_bbcode'		=> 0,
		'enable_smilies'	=> 0,
		'enable_urls'		=> 0,
		'enable_sig'		=> 0,
		'message'			=> '',
		'message_md5'		=> '',
		'attachment_data'	=> array(),
		'bbcode_bitfield'	=> '',
		'bbcode_uid'		=> '',
		'post_edit_locked'	=> false,
		//'force_approved_state'	=> 1,
	);

	public function getDataSet()
	{
		return $this->createXMLDataSet(dirname(__FILE__) . '/fixtures/submit_post_' . $this->item_type . '.xml');
	}

	public function setUp()
	{
		parent::setUp();

		global $auth, $cache, $config, $db, $phpbb_container, $phpbb_dispatcher, $user, $request, $phpEx, $phpbb_root_path, $user_loader;

		// Database
		$this->db = $this->new_dbal();
		$db = $this->db;

		// Auth
		$auth = $this->getMock('\phpbb\auth\auth');
		$auth->expects($this->any())
			->method('acl_get')
			->with($this->stringContains('_'),
				$this->anything())
			->will($this->returnValueMap(array(
				array('f_noapprove', 1, true),
				array('f_postcount', 1, true),
				array('m_edit', 1, false),
			)));

		// Config
		$config = new \phpbb\config\config(array(
			'num_topics' => 1,
			'num_posts' => 1,
			'allow_board_notifications'	=> true,
		));

		$cache = new \phpbb\cache\service(
			new \phpbb\cache\driver\dummy(),
			$config,
			$db,
			$phpbb_root_path,
			$phpEx
		);

		// Event dispatcher
		$phpbb_dispatcher = new phpbb_mock_event_dispatcher();

		// User
		$user = $this->getMock('\phpbb\user', array(), array(
			new \phpbb\language\language(new \phpbb\language\language_file_loader($phpbb_root_path, $phpEx)),
			'\phpbb\datetime'
		));
		$user->ip = '';
		$user->data = array(
			'user_id'		=> 2,
			'username'		=> 'user-name',
			'is_registered'	=> true,
			'user_colour'	=> '',
		);

		// Request
		$type_cast_helper = $this->getMock('\phpbb\request\type_cast_helper_interface');
		$request = $this->getMock('\phpbb\request\request');

		// Container
		$phpbb_container = new phpbb_mock_container_builder();
		$phpbb_dispatcher = new phpbb_mock_event_dispatcher();
		$phpbb_container->set('content.visibility', new \phpbb\content_visibility($auth, $config, $phpbb_dispatcher, $db, $user, $phpbb_root_path, $phpEx, FORUMS_TABLE, POSTS_TABLE, TOPICS_TABLE, USERS_TABLE));

		$user_loader = new \phpbb\user_loader($db, $phpbb_root_path, $phpEx, USERS_TABLE);

		// Notification Types
		$notification_types = array('quote', 'bookmark', 'post', 'post_in_queue', 'topic', 'topic_in_queue', 'approve_topic', 'approve_post');
		$notification_types_array = array();
		foreach ($notification_types as $type)
		{
			$class = $this->build_type($type);
			$phpbb_container->set('notification.type.' . $type, array(array($this, 'build_type'), array($type)));
			$notification_types_array['notification.type.' . $type] = $class;
		}

		// Methods Types
		$class_name = 'phpbb\notification\method\board';
		$class = new $class_name(
			$user_loader, $db, $cache->get_driver(), $user, $auth, $config,
			$phpbb_root_path, $phpEx,
			NOTIFICATION_TYPES_TABLE, NOTIFICATIONS_TABLE);
		$phpbb_container->set('notification.method.board', $class);
		$notification_methods_array = array('notification.method.board' => $class);

		// Notification Manager
		$phpbb_notifications = new \phpbb\notification\manager($notification_types_array, $notification_methods_array,
			$phpbb_container, $user_loader, $config, $phpbb_dispatcher, $db, $cache, $user,
			$phpbb_root_path, $phpEx,
			NOTIFICATION_TYPES_TABLE, USER_NOTIFICATIONS_TABLE);
		$phpbb_container->set('notification_manager', $phpbb_notifications);
	}

	public function build_type($type)
	{
		global $auth, $cache, $config, $db, $phpbb_container, $phpbb_dispatcher, $user, $request, $phpEx, $phpbb_root_path, $user_loader;

		$class_name = '\phpbb\notification\type\\' . $type;
		$class = new $class_name(
			$user_loader, $db, $cache->get_driver(), $user, $auth, $config,
			$phpbb_root_path, $phpEx,
			NOTIFICATION_TYPES_TABLE, USER_NOTIFICATIONS_TABLE);

		if ($type === 'quote')
		{
			$class->set_utils(new \phpbb\textformatter\s9e\utils);
		}		

		return $class;
	}

	/**
	* @dataProvider submit_post_data
	*/
	public function test_submit_post($additional_post_data, $expected_before, $expected_after)
	{
		$sql = 'SELECT user_id, item_id, item_parent_id
			FROM ' . NOTIFICATIONS_TABLE . ' n, ' . NOTIFICATION_TYPES_TABLE . " nt
			WHERE nt.notification_type_name = '" . $this->item_type . "'
				AND n.notification_type_id = nt.notification_type_id
			ORDER BY user_id ASC, item_id ASC";
		$result = $this->db->sql_query($sql);
		$this->assertEquals($expected_before, $this->db->sql_fetchrowset($result));
		$this->db->sql_freeresult($result);

		$poll_data = $this->poll_data;
		$post_data = array_merge($this->post_data, $additional_post_data);
		submit_post('reply', '', 'poster-name', POST_NORMAL, $poll_data, $post_data, false, false);

		$result = $this->db->sql_query($sql);
		$this->assertEquals($expected_after, $this->db->sql_fetchrowset($result));
		$this->db->sql_freeresult($result);
	}

	protected function build_method($method)
	{
		global $phpbb_root_path, $phpEx;

		return new $method($this->user_loader, $this->db, $this->cache->get_driver(), $this->user, $this->auth, $this->config, $phpbb_root_path, $phpEx, 'phpbb_notification_types', 'phpbb_notifications', 'phpbb_user_notifications');
	}
}
