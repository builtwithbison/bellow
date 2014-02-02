<?php

class Hooks_bellow extends Hooks 
{
	private $order_details = array();

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
		$this->notifyHipChat();
		$this->notifySlack();
	}

	private function notifyHipChat()
	{
		$config = $this->config['hipchat'];
		if (!$config['enabled'])
			return;

		$endpoint = 'https://api.hipchat.com/v1/rooms/message';
		$query = http_build_query(array(
			'auth_token' => $config['api_key'],
			'from'       => $config['from'],
			'room_id'    => $config['room_id'],
			'color'      => $config['color'],
			'notify'     => $config['notify'],
			'message'    => strip_tags(Content::parse($config['message'], $this->order_details))
		));

		$url = $endpoint.'?'.$query;
		$response = json_decode($this->performRequest($url));

		if (isset($response->error)) {
			$this->log->error('Error posting to HipChat: '.$response->error->message);
		}
	}

	private function notifySlack()
	{
		$config = $this->config['slack'];
		if (!$config['enabled'])
			return;

		$endpoint = 'https://slack.com/api/chat.postMessage';
		$query = http_build_query(array(
			'token'    => $config['api_key'],
			'channel'  => '#'.$config['channel'],
			'username' => array_get($config, 'from', Config::getSiteName()),
			'text'     => strip_tags(Content::parse($config['message'], $this->order_details))
		));

		$url = $endpoint.'?'.$query;
		$response = json_decode($this->performRequest($url));
		
		if (!$response->ok) {
			$this->log->error('Error posting to Slack: '.$response->error);
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