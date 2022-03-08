<?php
require_once('./base.php');

$msg = array(
        'body' => '送信メッセージ'
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

