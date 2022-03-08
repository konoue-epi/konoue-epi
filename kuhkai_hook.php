#!/usr/bin/php
<?php
/*-----
 *	kuhkaiルーム用bot
 *	(hot pepperからの予約メールを発言する）
 *	2021/07/10 H.Konoue
 */

require_once(dirname(__FILE__). '/base.php');
require_once('/usr/share/php/Mail/mimeDecode.php');

define("ERROR_LOG",	dirname(__FILE__). "/debug.log");

function putLog($msg)
{
	if (trim($msg) == '')
		return;

	error_log($msg, 3, ERROR_LOG);
}

function Main()
{
	$stdin = file_get_contents('php://stdin');
	putLog("\n-=-=-= hook -=-=-=-=-\n");

	$params = array("include_bodies" => true,
					"decode_bodies" => true,
					"decode_headers" => true,
					"crlf" => "\r\n",
					"input" => $stdin
				);

	$object = null;
	try {
		$object = Mail_mimeDecode::decode($params);
putLog("export:". var_export($object));
	}
	catch( Exception $e ) {
		fputs(STDERR, $e->getMessage(). "\n");
		$object = null;
		exit();
	}

	$buf = mb_convert_encoding($object->parts[0]->body, "UTF-8", "ISO-2022-JP");
putLog("buf1:". $buf);
	$buf = preg_replace("/\r?\n/", "\n", $buf);
	$lines = explode("\n", $buf);

	mb_regex_encoding("UTF-8");


	putLog($buf);

	$pairs = [];
	foreach($lines as $line)
	{
		if (preg_match("/^([◇■　])(.+)/u", $line, $m)) {
			if ($m[1] == '◇') {
				continue;
			}
			else if ($m[1] == '■') {
				$key = $m[2];
			}
			else if ($m[1] == '　') {
				if ($key == '') {
					die("■ is empty");
				}
				else if (!array_key_exists($key, $pairs)) {
					$pairs[$key] = '';
				}
				$pairs[$key] .= $m[2];
			}
		}
	}
	sendMessage($pairs);
}

function sendMessage($pairs)
{
	global $Rooms;

	$buf = $pairs['氏名']. '様からご予約が入りました。('. $pairs['予約番号']. ')'
		 . ' ご来店は '. $pairs['来店日時']. '、「'. $pairs['メニュー']. '」をご希望です。';
	if ($pairs['指名スタッフ'] != '指名なし') {
		$buf .= '(ご指名:'. $pairs['指名スタッフ']. ')';
	}

	foreach($pairs as $key => $val) {
		putLog($key.'='.$val."\n");
	}

	$msg = array(
	        'body' => $buf
	        );
	$room = $Rooms['develop'];

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
exit;


?>
