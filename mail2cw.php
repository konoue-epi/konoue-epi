#!/usr/bin/php
<?php
/*-----
 *	mail2cw2
 *	all_staff@ 宛メールをcwへ転送する
 *	H.Konoue 2018/11/26
 *	ver.2021.8.5
 */

require_once('/usr/share/php/Mail/mimeDecode.php');
require_once('/var/www/html/cwhook/base.php');

define("ERROR_LOG",	"/var/log/attach.log");

// エラーログ
$ErrorBuf = array();

function putError($msg)
{
	error_log($msg. "\n", 3, ERROR_LOG);
}

function Main()
{
	$stdin = file_get_contents('php://stdin');
	$params = array("include_bodies" => true,
					"decode_bodies" => true
				);

	$decoder = new Mail_mimeDecode($stdin);
	$structure = $decoder->decode($params);

	$subject = mb_convert_encoding(mb_decode_mimeheader($structure->headers["subject"]), mb_internal_encoding(), "auto");
	$from = mb_convert_encoding(mb_decode_mimeheader($structure->headers["from"]), mb_internal_encoding(), "auto");
	$date = mb_convert_encoding(mb_decode_mimeheader($structure->headers["date"]), mb_internal_encoding(), "auto");
	putError("----\n". $subject. " / ". $from);
	putError($date);

	$pairs = [
		'subject' => $subject,
		'from' => $from,
		'date' => $date
	];

	if (strtolower($structure->ctype_primary) == 'multipart') {
		putError("*multipart");
		$pairs['body'] = '';
		foreach($structure->parts as $part) {
			$type = strtolower($part->ctype_primary);
			if ($type == 'multipart')
				continue;
			if ($type != 'text') {
				continue;
			}
			else {
				putError($part->body);
				$pairs['body'] .= $part->body;
			}
		}
	}
	else if (strtolower($structure->ctype_primary) == 'text') {
		putError("*singlepart");

		$charset = '';
		if (isset($structure->headers["content-type"])) {
			if (preg_match("/charset=([^\s].+)[;]?/", $structure->headers["content-type"], $m)) {
				$charset = strtoupper(preg_replace("/\"/", "", $m[1]));
			}
		}
		else {
			putError("charset is null");
		}

		if ($charset != "UTF-8") {
			$pairs['body'] = mb_convert_encoding($structure->body, mb_internal_encoding(), $charset);
		}
		else {
			$pairs['body'] = $structure->body;
		}
	}

	if (preg_match("/^cw /i", $pairs['subject']))
	{
		$pairs['subject'] = preg_replace("/^cw /i", "", $pairs['subject']);
		sendMessage($pairs);
	}
	else if (preg_match("/^ロボ /u", $pairs['subject']))
	{
		$pairs['subject'] = preg_replace("/^ロボ /u", "", $pairs['subject']);
		sendMessage($pairs);
	}
	else {
		putError('ignored');
	}
}

function sendMessage($pairs)
{
	global $Rooms;

	$from = preg_replace("/<[^>]+>/", "", $pairs['from']);

	if (isset($pairs['body'])) {
		$body = preg_replace("[\r]\n", "\n", $pairs['body']);
		$array_body = explode("\n", $body);

		$bodybuf = '';
		foreach($array_body as $line) {
			if (preg_match("/^\-\- $/", $line)) {
				break;
			}
			else{
				$bodybuf .= $line. "\n";
			}
		}
	}

	$buf = 'ML: '. $from. "\n"
		 . '[info][title]'. $pairs['subject']. '[/title]' . $bodybuf. "[/info]\n";

	$msg = array(
	        'body' => $buf
	        );
	$room = $Rooms['epi-all'];

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-ChatWorkToken: '. APITOKEN));
	curl_setopt($ch, CURLOPT_URL, "https://api.chatwork.com/v2/rooms/". $room. "/messages");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($msg, '', '&'));

	$result = curl_exec($ch);

	curl_close($ch);
}


Main();

?>
