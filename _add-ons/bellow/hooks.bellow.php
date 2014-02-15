<?php

class Hooks_bellow extends Hooks 
{
	private $hipchat_endpoint = 'https://api.hipchat.com/v1/rooms/message';
	private $slack_endpoint = 'https://slack.com/api/chat.postMessage';

	private $order_details;
	private $member;

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
		$this->member = $member;
		$this->notifySlackMemberRegistration();
		$this->notifyHipChatMemberRegistration();
	}

	private function notifySlackMemberRegistration()
	{
		$config = $this->config['slack']['member_registration'];
		if (!$config['enabled'])
			return;

		$username = $this->member->get('username');
		$profile_data = Member::getProfile($username);

		$query = http_build_query(array(
			'token'    => $this->config['slack']['api_key'],
			'channel'  => '#'.$config['channel'],
			'username' => array_get($config, 'from', Config::getSiteName()),
			'text'     => strip_tags(Content::parse($config['message'], $profile_data))
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

		$username = $this->member->get('username');
		$profile_data = Member::getProfile($username);

		$query = http_build_query(array(
			'auth_token' => $this->config['hipchat']['api_key'],
			'from'       => $config['from'],
			'room_id'    => $config['room_id'],
			'color'      => $config['color'],
			'notify'     => $config['notify'],
			'message'    => strip_tags(Content::parse($config['message'], $profile_data))
		));

		$url = $this->hipchat_endpoint.'?'.$query;
		$response = json_decode($this->performRequest($url));
		
		if (isset($response->error)) {
			$this->log->error('Error posting to HipChat on member registration: '.$response->error->message);
		}
	}

	private function performRequest($url)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$output = curl_exec($ch);
		curl_close($ch);
		return $output;
	}
}