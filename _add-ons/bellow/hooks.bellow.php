<?php

class Hooks_bellow extends Hooks 
{
	private $hipchat_endpoint = 'https://api.hipchat.com/v1/rooms/message';
	private $slack_endpoint = 'https://slack.com/api/chat.postMessage';
	private $asana_endpoint = 'https://app.asana.com/api/1.0/tasks';

	private $order_details;
	private $profile_data;

	/**
	 * Run when checkout is completed
	 *
	 * @return string
	 **/
	function bison__checkout_complete($order_details)
	{
		if (!function_exists('curl_init')) {
			$this->log->error('cURL is not installed.');
			return;
		}
		$this->order_details = $order_details;
		$this->notifyHipChatCheckoutComplete();
		$this->notifySlackCheckoutComplete();
		$this->createAsanaTaskCheckoutComplete();
	}

	private function notifyHipChatCheckoutComplete()
	{
		$config = $this->config['hipchat']['checkout_complete'];
		if (!$config['enabled'])
			return;

		$query = http_build_query(array(
			'auth_token' => $this->config['hipchat']['api_key'],
			'from'       => $config['from'],
			'room_id'    => $config['room_id'],
			'color'      => $config['color'],
			'notify'     => $config['notify'],
			'message'    => strip_tags(Content::parse($config['message'], $this->order_details))
		));

		$url = $this->hipchat_endpoint.'?'.$query;
		$response = json_decode($this->performRequest($url));

		if (isset($response->error)) {
			$this->log->error('Error posting to HipChat on checkout completion: '.$response->error->message);
		}
	}

	private function notifySlackCheckoutComplete()
	{
		$config = $this->config['slack']['checkout_complete'];
		if (!$config['enabled'])
			return;

		$query = http_build_query(array(
			'token'    => $this->config['slack']['api_key'],
			'channel'  => '#'.$config['channel'],
			'username' => array_get($config, 'from', Config::getSiteName()),
			'text'     => strip_tags(Content::parse($config['message'], $this->order_details))
		));

		$url = $this->slack_endpoint.'?'.$query;
		$response = json_decode($this->performRequest($url));
		
		if (!$response->ok) {
			$this->log->error('Error posting to Slack on checkout completion: '.$response->error);
		}
	}

	private function createAsanaTaskCheckoutComplete()
	{
		$config = $this->config['asana']['checkout_complete'];
		if (!$config['enabled'])
			return;

		$data = json_encode(array(
			'data' => array(
				'workspace' => $config['workspace'],
				'assignee'  => $config['assignee'],
				'name'      => strip_tags(Content::parse($config['task_name'], $this->order_details)),
				'notes'     => strip_tags(Content::parse($config['notes'], $this->order_details))
			)
		));

		$response = json_decode($this->performRequest($this->asana_endpoint, $data, $this->config['asana']['api_key']));
		
		if (isset($response->errors)) {
			$this->log->error('Error posting to Asana on checkout completion: '.$response->errors[0]->message);
		}
	}

	/**
	 * Run when a member is registered
	 *
	 * @return string
	 **/
	function member__registration_complete($member)
	{
		if (!function_exists('curl_init')) {
			$this->log->error('cURL is not installed.');
			return;
		}
		$username = $member->get('username');
		$this->profile_data = Member::getProfile($username);
		$this->notifySlackMemberRegistration();
		$this->notifyHipChatMemberRegistration();
		$this->createAsanaTaskMemberRegistration();
	}

	private function notifySlackMemberRegistration()
	{
		$config = $this->config['slack']['member_registration'];
		if (!$config['enabled'])
			return;

		$query = http_build_query(array(
			'token'    => $this->config['slack']['api_key'],
			'channel'  => '#'.$config['channel'],
			'username' => array_get($config, 'from', Config::getSiteName()),
			'text'     => strip_tags(Content::parse($config['message'], $this->profile_data))
		));

		$url = $this->slack_endpoint.'?'.$query;
		$response = json_decode($this->performRequest($url));
		
		if (!$response->ok) {
			$this->log->error('Error posting to Slack on member registration: '.$response->error);
		}
	}

	private function notifyHipChatMemberRegistration()
	{
		$config = $this->config['hipchat']['member_registration'];
		if (!$config['enabled'])
			return;

		$query = http_build_query(array(
			'auth_token' => $this->config['hipchat']['api_key'],
			'from'       => $config['from'],
			'room_id'    => $config['room_id'],
			'color'      => $config['color'],
			'notify'     => $config['notify'],
			'message'    => strip_tags(Content::parse($config['message'], $this->profile_data))
		));

		$url = $this->hipchat_endpoint.'?'.$query;
		$response = json_decode($this->performRequest($url));
		
		if (isset($response->error)) {
			$this->log->error('Error posting to HipChat on member registration: '.$response->error->message);
		}
	}

	private function createAsanaTaskMemberRegistration()
	{
		$config = $this->config['asana']['member_registration'];
		if (!$config['enabled'])
			return;

		$data = json_encode(array(
			'data' => array(
				'workspace' => $config['workspace'],
				'assignee'  => $config['assignee'],
				'name'      => strip_tags(Content::parse($config['task_name'], $this->profile_data)),
				'notes'     => strip_tags(Content::parse($config['notes'], $this->profile_data))
			)
		));

		$response = json_decode($this->performRequest($this->asana_endpoint, $data, $this->config['asana']['api_key']));
		
		if (isset($response->errors)) {
			$this->log->error('Error posting to Asana on member registration: '.$response->errors[0]->message);
		}
	}

	private function performRequest($url, $data = null, $auth = null)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		if ($auth)
			curl_setopt($ch, CURLOPT_USERPWD, $auth);
		if ($data)
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		$output = curl_exec($ch);
		curl_close($ch);
		return $output;
	}
}