<?php
/*
 *	ePI-NET chatwork bot ロボ
 *	Ver.2021.0817
 *
 *	H.Konoue
 */
require_once('./base.php');

define('PATH_BASE',	dirname(__FILE__). '/');
define('LIB_DIR',	'/var/www/inc/');
define('PATH_LOG',	PATH_BASE. 'log.log');

define('STAGE',		'epi-all');

function getMessageInfo($message_id)
{
	global $Rooms;

	$roomid = $Rooms[STAGE];
	$url = sprintf("%s/rooms/%s/messages/%s", CWAPI_URL, $roomid, $message_id);

	$json = callAPI($url, False);

	if (is_object($json)) {
		$buf = 'object';
	} else if (is_array($json)) {
		$buf = 'array';
	} else {
		$buf = 'other';
	}
	error_log($buf."\n", 3, PATH_LOG);
	return $json['account']['name'];
}

function sendMessage($msg)
{
	global $Rooms;

	$roomid = $Rooms[STAGE];
	$url = sprintf("%s/rooms/%s/messages", CWAPI_URL, $roomid);

	$params = array('body' => $msg);

	$json = callAPI($url, True, $params);

	return $json;
}

function queueHoroscope($zodiac)
{
	$date = date("Y/m/d", time());
	$url = 'http://api.jugemkey.jp/api/horoscope/free/'. $date;
	$json = callAPI($url);

	if (preg_match("/^[あ-ん]+$/u", $zodiac)) {
		switch($zodiac) {
			case 'おひつじ':
				$zodiac = '牡羊座';
				break;
			case 'おうし':
				$zodiac = '牡牛座';
				break;
			case 'ふたご':
				$zodiac = '双子座';
				break;
			case 'かに':
				$zodiac = '蟹座';
				break;
			case 'しし':
				$zodiac = '獅子座';
				break;
			case 'おとめ':
				$zodiac = '乙女座';
				break;
			case 'てんびん':
				$zodiac = '天秤座';
				break;
			case 'さそり':
				$zodiac = '蠍座';
				break;
			case 'いて':
				$zodiac = '射手座';
				break;
			case 'やぎ':
				$zodiac = '山羊座';
				break;
			case 'みずがめ':
				$zodiac = '水瓶座';
				break;
			case 'うお':
				$zodiac = '魚座';
				break;
			default:
				sendMessage("えっと、何座だって？");
				return;
		}
	}
	else {
		$zodiac .= '座';
	}

	$buf = '';
	foreach($json['horoscope'][$date] as $data) {
		if ($data['sign'] == $zodiac) {
			$buf = $zodiac. 'の今日の運勢は…';

			switch(intval(date("U"))%2) {
			  case 0:
				if ($data['rank'] == 1) {
					$buf .= "ランクはトップ！きっといい一日。";
				} else if ($data['rank'] == 12) {
					$buf .= "ランクはビリ。そんな日もあるね。";
				} else {
					$buf .= sprintf("ランクは%s位だよ。", $data['rank']);
				}
				$buf .= sprintf("金運:%s 仕事運:%s 恋愛運:%sだよ。", $data['money'], $data['job'], $data['love']);
				break;
			  default:
				$buf .= sprintf("ラッキカラーは%s、ラッキーアイテムは%s。%s", $data['color'], $data['item'], $data['content']);
			}
		}
	}
	if ($buf == '') {
		sendMessage("えっと、何座だったっけ？");
	}
	else {
		sendMessage($buf);
	}
}

function queueForcast($pref)
{
	include_once("./jisx0401.php");
	$rjisx0401 = array_flip($jisx0401);

	error_log($pref."\n", 3, PATH_LOG);

	if (trim($pref) == '') {
		$code = '27';	// osaka
	}
	else if (!isset($rjisx0401[$pref])) {
		if (!preg_match("/.+[都道府県]$/u", $pref)) {
			if ($pref == '東京') {
				$pref .= '都';
			}
			else if ($pref == '大阪' || $pref == '京都') {
				$pref .= '府';
			}
			else {
				$pref .= '県';
			}
		}

		if (!isset($rjisx0401[$pref])) {
			$code = '27';
error_log("A:". $code."\n", 3, PATH_LOG);
		}
		else {
			$code = $rjisx0401[$pref];
error_log("B:". $code."\n", 3, PATH_LOG);
		}
	}
	else {
		$code = $rjisx0401[$pref];
error_log("C:". $code."\n", 3, PATH_LOG);
	}

	$url = 'https://www.jma.go.jp/bosai/forecast/data/forecast/'. $code. '0000.json';
	$json = callAPI($url, False);

	$now = date("Y-m-d", time() + 86400);

	$idx = -1;
	foreach($json[0]['timeSeries'][0]['timeDefines'] as $v) {
		if (strpos($v, $now) == 0) {
			$idx++;
			break;
		}
	}
	//error_log($idx."\n", 3, PATH_LOG);
	$result = $json[0]['timeSeries'][0]['areas'][0]['weathers'][$idx];

	$buf = "気象庁によると、". $pref. "の明日の天気はいまのところ". $result. "だよ";
	sendMessage($buf);
}

function queueZip($zip)
{
	$zip = mb_convert_kana($zip, 'as');
	$zip1 = substr($zip, 0, 3);
	$zip2 = (strlen($zip) != 7) ? substr($zip, 4) : substr($zip, 3);

	$url = sprintf('http://api.thni.net/jzip/X0401/JSON/%s/%s.js', $zip1, $zip2);
	$json = callAPI($url, False);

	if (!is_array($json)) {
		return;
	}
	$result = $json['stateName']. $json['city']. $json['street'];

	$buf = sprintf("〒%s-%s は %s ですね", $zip1, $zip2, $result);
	sendMessage($buf);
}

function queueDay($diff = 0)
{
	$now = time();
	if ($diff != 0 && ($diff >= -1 && $diff <= 1)) {
		$now = $now + (86400 * $diff);
	}

	$wday = array('日', '月', '火', '水', '木', '金', '土');
	$date = date("Y n j w z", $now);
	$d = explode(' ', $date);

	$buf = sprintf("%s年%s月%s日 %s曜日だよ。", $d[0], $d[1], $d[2], $wday[$d[3]]);
	if ($diff == 0) {
		$buf .= sprintf("今年も%s日経ったね。", $d[4]);
	}
	sendMessage($buf);
}
function queueTime() {
}

function queueEmployee($name) {
	$name = mb_convert_kana($name, 's');

	if (strpos($name, ' ') !== FALSE) {
		list($family, $first) = explode(' ', $name);
	}
	else {
		sendMessage("氏名はスペースで区切ってね");
		return;
	}

	require_once(LIB_DIR. 'EmployeeService.php');
	$srv = new EmployeeService();

	if ($srv->searchByFullname($family, $first)) {
		if (($obj = $srv->nextObj()) != NULL) {
			require('./api_kot.php');
			$time = searchEmployee($obj->id);
			if ($time == NULL) {
				$name = $obj->family;
				$buf = $name. "さんは今日は見てないよ";
			}
			list($hour, $min, $sec) = explode(':', $time);
			$hour = preg_replace("/^0+/", '', $hour);
			$min =  preg_replace("/^0+/", '', $min);
			$sec =  preg_replace("/^0+/", '', $sec);

			$buf = $name. "さんは今日は ". $hour. "時". $min. "分". $sec. "秒に出勤してますね";
		}
		else {
			$buf = $name. "さんって誰？？";
		}
	}
	else {
		$buf = $first. ':'. $name. "さんって誰？";
	}

	sendMessage($buf);
}

function queueDice()
{
	$dice = intval(mt_rand()%6)+1;
	$buf = sprintf("%d が出ました！", $dice);
	sendMessage($buf);
}

function queueLatLon()
{
	$buf = "緯度が Latitude、経度が Longitude だよ";
	sendMessage($buf);
}

function queueAnswer($name)
{
	$buf = $name. "さん、よんだ？";
	sendMessage($buf);
}

function responceFinish()
{
	http_response_code( 200 );
	exit();
}

// リクエスト取得
$raw = file_get_contents('php://input');

// 署名検証
if (!checkSignature($raw, $Token[STAGE])) {
	error_log("auth failed\n ", 3, PATH_LOG);
	responceFinish();
}

$receive = json_decode($raw, true);

$from_account_id = isset($receive['webhook_event']['account_id']) ? 
						$receive['webhook_event']['account_id'] : '';
$message_id = $receive['webhook_event']['message_id'];
$body = $receive['webhook_event']['body'];

if ($from_account_id != '' && $from_account_id != BOT_ACCOUNT_ID)
{
	$name = getMessageInfo($message_id);

	$pos = mb_strpos($body, "ロボ");

	if ($pos === FALSE) {
		if (mb_strpos($body, "ボロ") !== FALSE) {
			$buf = "ロボはボロくないよ！";
			sendMessage($buf);
		}
		responceFinish();
	}
	else if ($pos == 0) {
		if (preg_match("/、(.+)の天気/u", $body, $m)) {
			queueForcast($m[1]);
		}
		else if (preg_match("/、(.+)座を?占って/u", $body, $m)) {
			queueHoroscope($m[1]);
		}
		else if (preg_match("/サイコロ/u", $body)) {
			queueDice();
		}
		else if (preg_match("/([0-9]{3}.[0-9]{4})[^0-9]?/u", $body, $m)) {
			queueZip($m[0]);
		}
		else if (preg_match("/(.+)日は何日/u", $body, $m)) {
			error_log($m[1]. " match\n ", 3, PATH_LOG);

			if (preg_match("/今$/u", $m[1])) {
				queueDay();
			}
			else if (preg_match("/明$/u", $m[1])) {
				queueDay(1);
			}
			else if (preg_match("/昨$/u", $m[1])) {
				queueDay(-1);
			}
		}
		else if (preg_match("/今.+何時/u", $body)) {
			queueTime();
		}
		else if (preg_match("/[緯経]度/u", $body) ||
				 preg_match("/[Ll]atitude/u", $body) ||
				 preg_match("/[Ll]ongitude/u", $body)) {
			queueLatLon();
		}
		else if (preg_match("/[、。](.+)さんどこ/u", $body, $m)) {
			queueEmployee($m[1]);
		}
	}
	else {
		if ((mt_rand()%100) == 50) {
			queueAnswer($name);
		}
	}
}

/*
error_log("log:\n", 3, PATH_LOG);
foreach($receive['webhook_event'] as $key => $val) {
	error_log($key."=".$val."\n", 3, PATH_LOG);
}
*/

responceFinish();
?>