<?php

define('CWAPI_URL', 		'https://api.chatwork.com/v2');
define('APIKEY', 			'bb1e4b2824de6b63104ebdeb128b0194');
define('APITOKEN',			APIKEY);

define('BOT_ACCOUNT_ID',	'5847952');

define('SIGNATURE_KEY', 	'X-ChatWorkWebhookSignature');	// webhook署名認証のヘッダ添字
define('REQUEST_KEY',		'X-ChatWorkToken');				// APIトークン格納添字


$Rooms = array(
	'epi-all' => '227185888',	// ePI-NET全体
	'develop' => '227450061',	// ロボ
	'kuhkai'  => '196086152'
);
$Token = array(
	'epi-all' => 'R7yh0DS/eNFnVVyqxigurgBf1d/fh0WH6I61QPTaX6A=',
	'develop' => 'cDAT7gPEp4nrLOvJVrCNM5hlHfBedFk7eHy//DWvu5s=',
	'kuhkai'  => 'xk6xAfBbdpdDKoseMEkk1B6x2+0Mg3VayPfw0gXxs/0='
);


// ヘッダから署名抽出
function getWebhookSignature()
{
	foreach(getallheaders() as $k => $v) {
		error_log("===\n". $k. " = ". $v. "\n", 3, "/var/www/html/cwhook/log.log");
		if ($k === SIGNATURE_KEY) {
			return $v;
		}
	}
}

// webhook リクエスト署名検証
// https://developer.chatwork.com/ja/webhook.html
function checkSignature($body, $token)
{
	$sign = getWebhookSignature();
	$secret_key = base64_decode($token);
	$digest = hash_hmac('sha256', $body, $secret_key, TRUE);
	$auth = base64_encode($digest);

	return ($sign === $auth) ? TRUE : FALSE;
}

// APIをコール
// $url: エンドポイント
// $isMethodPost: 呼び出しメソッドがPOSTならTrue
// $params: パラメーター群
// $isUpload: ファイルアップロードを伴う(multipart/form-data)
function callAPI($url, $isMethodPost=FALSE, $params=NULL, $isUpload=NULL)
{
	$httpheader = array('X-ChatWorkToken: '. APIKEY);

	$options = array(
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,	// 文字列
		CURLOPT_POST => $isMethodPost,
	);

	if (!empty($isUpload)) {
		$options[CURLOPT_POSTFIELDS] = $params;
		array_push($httpheader, 'Content-Type: multipart/form-data');
	}
	else {
		if (!empty($params)) {
			$options[CURLOPT_POSTFIELDS] = http_build_query($params, '', '&');
		}
	}
	$options[CURLOPT_HTTPHEADER] = $httpheader;

	$ch = curl_init();
	curl_setopt_array($ch, $options);
	$response = curl_exec($ch);
	curl_close($ch);

	$result = json_decode($response, true);

	return $result;
}

?>