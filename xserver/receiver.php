<?php
/*
 * on xserver
 * chatwork webhook
 * 抽出メッセージを all-staff@ML へ転送する
 *
 * 2021/08/06 H.Konoue
 */
require_once('./base.php');
define('PATH_BASE',	dirname(__FILE__). '/');
define('LIB_DIR',	PATH_BASE. 'inc/');
define('PATH_LOG',	PATH_BASE. 'cw.log');

function getMessageInfo($message_id)
{
	global $Rooms;
	$roomid = $Rooms['epi-all'];
	$url = sprintf("%s/rooms/%s/messages/%s", CWAPI_URL, $roomid, $message_id);

	$json = callAPI($url, False);
/*
	if (is_object($json)) {
		$buf = 'object';
	} else if (is_array($json)) {
		$buf = 'array';
	} else {
		$buf = 'other';
	}
*/
	return $json['account']['name'];
}

function sendMessage($msg)
{
	global $Rooms;
	$roomid = $Rooms['epi-all'];
	$url = sprintf("%s/rooms/%s/messages", CWAPI_URL, $roomid);

	$params = array('body' => $msg);

	$json = callAPI($url, True, $params);

	return $json;
}


function queueDice()
{
	$dice = intval(mt_rand()%6)+1;
	$buf = sprintf("%d が出ました！", $dice);
	sendMessage($buf);
}

function queueAnswer($name = '')
{
	$buf = $name. "さん、そのメッセージはMLにも送ったよ";
	sendMessage($buf);
}

function responceFinish()
{
	http_response_code( 200 );
	exit();
}

//--------------------------------------
// リクエスト取得
error_log("----=----\n", 3, PATH_LOG);
$raw = file_get_contents('php://input');

// 署名検証
if (!checkSignature($raw, $Token['xsvbot'])) {
	error_log("auth failed\n ", 3, PATH_LOG);
	responceFinish();
}

$receive = json_decode($raw, true);
error_log("----raw2decode----\n", 3, PATH_LOG);
error_log(var_export($receive, true). "\n", 3, PATH_LOG);

// 発信者ID
$from_account_id = isset($receive['webhook_event']['account_id']) ? 
						$receive['webhook_event']['account_id'] : '';
// メッセージID
$message_id		 = $receive['webhook_event']['message_id'];
// メッセージ内容
$body			 = $receive['webhook_event']['body'];

if ($from_account_id == '') {
	responceFinish();
}
else if ($from_account_id == BOT_ACCOUNT_ID) {
	responceFinish();	// 自身の発言は無視する
}

$name = getMessageInfo($message_id);
$pos = mb_strpos($body, "#ML");

if ($pos === FALSE) {
	responceFinish();	// 呼ばれてない
}
else {
	queueAnswer($name);

	// mail
	$body = preg_replace("/#ML/i", "", $body);

	// info タグを使用している場合はこれを抽出する
	if (preg_match("/\[info\](.*)\[\/info\]/i", $body, $mb)) {
		if (preg_match("/\[title\](.*)\[\/title\]/i", $mb[1], $mt)) {
			$body = str_replace($mt[0], '', $mb[1]);
			$title = $mt[1];
		}
	}
	if (trim($title) != '') {
		$subject = '[CW] '. $title;
	} else {
		$subject = '[CW] '. $name. "さんより";
	}

	if (!sendMail(
				'all-staff@epi-net.co.jp',
				$subject,
				$body,
				'epibot@epi-net.co.jp',
				'ロボ'
	)) {
		error_log("mail send failed\n", 3, PATH_LOG);
	}
}
responceFinish();


/*
error_log("log:\n", 3, PATH_LOG);
foreach($receive['webhook_event'] as $key => $val) {
	error_log($key."=".$val."\n", 3, PATH_LOG);
}
*/