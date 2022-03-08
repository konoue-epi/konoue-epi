<?php
if (!defined("LIB_DIR"))
	die("LIB_DIR not defined.");

require_once(LIB_DIR. "epi-intra_AppControl.php");
require_once(LIB_DIR. 'PdoFactory.php');

function searchEmployeeKey($employee_id)
{
	try {
		$sql = 'select kot_key from r_kotkey where id = :id';

		$pdo = PdoFactory::getInstance();
		$dbh = $pdo->GetConnector();

		$sth = $dbh->prepare($sql);
		$sth->bindValue(':id', $employee_id);

		if (!$sth->execute()) {
			throw new Exception('sql query failed');
		}
		if ($sth == NULL) {
			return NULL;
		}
		else if (($record = $sth->fetch(PDO::FETCH_ASSOC)) == NULL) {
			return NULL;
		}
		return $record['kot_key'];
	}
	catch (Exception $e) {
		$sth = NULL;
		$db = NULL;
		return NULL;
	}
}

function searchEmployee($employee_id)
{
	$kotkey = searchEmployeeKey($employee_id);
	if ($kotkey == NULL) {
		return NULL;
	}

	$token = '032bb10f43614089831da1163212696c';	// KoT token
	$url = 'https://api.kingtime.jp/v1.0/';
	$page = 'daily-workings/timerecord';

	$headers = array(
		"Content-Type: application/json; charset=utf-8",
		"Cache-Control: no-cache",
		"Pragma: no-cache",
		"Authorization: Bearer ". $token
	);

	$data = array(
		"employeeKeys" => $kotkey
	);
	$url = sprintf("%s%s?%s", $url, $page, http_build_query($data));

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_HTTPGET, true);
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($curl, CURLOPT_TIMEOUT, 60);
	$json = curl_exec($curl);

	if (curl_errno($curl)) {
		return NULL;
	}
	else {
		curl_close($curl);
	}

	if (($data = json_decode($json, TRUE)) == NULL) {
		return NULL;
	}
	if ($data[0]["dailyWorkings"][0]["date"] != date("Y-m-d")) {
		return NULL;
	}
	if ($data[0]["dailyWorkings"][0]["timeRecord"][0]["name"] != '出勤') {
		return NULL;
	}

	$attend = $data[0]["dailyWorkings"][0]["timeRecord"][0]["time"];
	$time = substr($attend, 11, 8);

	return $time;
}

?>