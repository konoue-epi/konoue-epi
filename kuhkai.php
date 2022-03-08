#!/usr/bin/php
<?php
/*-----
 *	kuhkaiルーム用bot
 *	(hot pepperからの予約メールを発言する）
 *	2021/07/10 H.Konoue
 *	Rev.2022.0308.1
 */
require_once(dirname(__FILE__). '/base.php');

define("ERROR_LOG",	dirname(__FILE__). "/debug.log");
define("DEBUG",		false);

require_once('/usr/share/php/Mail/mimeDecode.php');
require_once(dirname(__FILE__). '/SalonBoardReservation.php');


function putLog($msg, $force = false)
{
	if (!DEBUG) {
		if (!$force) {
			return;
		}
	}
	if (trim($msg) == '')
		return;

	error_log($msg, 3, ERROR_LOG);
}

function Main()
{
	$stdin = file_get_contents('php://stdin');
	putLog("\n-=-=-= kuhkai -=-=-=-=-\n", true);

	$params = array("include_bodies" => true,
					"decode_bodies" => true,
					"decode_headers" => true,
					"crlf" => "\r\n",
					"input" => $stdin
				);

	$object = null;
	try {
		$object = Mail_mimeDecode::decode($params);
		putLog("export:". var_export($object, true). "\n");
	}
	catch( Exception $e ) {
		putLog("exception:". $e->getMessage(). "\n");
		$object = null;
		exit();
	}

	$pairs = [];

	// subject判定
	$subject = mb_convert_encoding($object->headers['subject'], "UTF-8", $object->ctype_parameters['charset']);
	if (preg_match("/キャンセル/", $subject)) {
		$pairs['cancellation'] = 'cancellation';
	}

	// body判定
	$buf = mb_convert_encoding($object->body, "UTF-8", $object->ctype_parameters['charset']);
	putLog("buf1:". $buf. "\n\n");
	$buf = preg_replace("/\r?\n/", "\n", $buf);
	$lines = explode("\n", $buf);

	mb_regex_encoding("UTF-8");
	putLog($buf);

	foreach($lines as $line)
	{
		// メール本文内の見出し記号で分類
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
					$pairs[$key] = array();
				}
				array_push($pairs[$key], trim($m[2]));
			}
		}
	}
	sendMessage($pairs);
}

function sendMessage($pairs)
{
	global $Rooms;

	$customer = new SalonBoardReservation($pairs);

	// 来店日時
	$pretitle = '';
	$appointment = $customer->appointment;
	if ($customer->reservation_date != NULL) {
		if (date("Ymd") == date("Ymd", $customer->reservation_date)) {
			$pretitle = "【当日】";
			$appointment = '本日'. date("G:i", $customer->reservation_date);
		}
	}

	// 本文
	if (!$customer->isCancellation()) {
		$iscancel = 'のキャンセル';
	}
	else {
		$iscancel = '';
	}

	$buf = '[info][title]'. $pretitle. $customer->name. '様からご予約'. $iscancel
		. 'が入りました。('. $customer->reserve_no. ')[/title]'
		. ' ご来店は '. $appointment. '、「'. $customer->menu. '」をご希望です。';

	// 指名あり
	if ($customer->reserve_staff != '指名なし') {
		$buf .= '(ご指名:'. $customer->reserve_staff. ')';
	}

	// クーポンあり
	if ($customer->coupon != '') {
		$buf .= "\nご利用クーポン:". $customer->coupon;
	}

	// 金額
	$buf .= "\n予約時合計金額:". number_format($customer->amount_total). '円';
	$buf .= "\nお支払い予定金額:". number_format($customer->amount_all). '円';
	if ($customer->amount_gift > 0 || $customer->amount_point > 0) {
		$buf .= sprintf(" (ギフト: %s円 / ポイント: %sP)",
					number_format($customer->amount_gift),
					number_format($customer->amount_point));
	}

	// ご要望
	if (trim($customer->request) != '-') {
		$buf .= "\nご要望・ご相談:". $customer->request;
	}

	// Salon Board URL
	$buf .= "\nhttps://salonboard.com/KLP/reserve/net/reserveDetail/?reserveId=". $customer->reserve_no. '[/info]';

/*
	foreach($pairs as $key => $val) {
		putLog($key.'='.$val."\n");
	}
*/
	putLog($buf, true);

	$msg = array(
	        'body' => $buf
	        );
	$room = $Rooms['kuhkai'];

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-ChatWorkToken: '. APITOKEN));
	curl_setopt($ch, CURLOPT_URL, "https://api.chatwork.com/v2/rooms/". $room. "/messages");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($msg, '', '&'));

	//$result = curl_exec($ch);

	curl_close($ch);
}


Main();
exit;


?>
