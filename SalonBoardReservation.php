<?php

/*
	hotpepper salon board 予約メール保存クラス
	空快用
	2021/12/07 H.Konoue
	rev.2022.0124.12
 */
class SalonBoardReservation
{
	// 項目名変換用
	private $_keylist = array(
		'予約番号' => 'reserve_no',
		'氏名' => 'name',
		'来店日時' => 'appointment',
		'指名スタッフ' => 'reserve_staff',
		'メニュー' => 'menu',
		'ご利用クーポン' => 'coupon',
		'合計金額' => 'amount',
		'ご要望・ご相談' => 'request',
		'予約時合計金額' => 'amount_total',
		'今回の利用ギフト券' => 'amount_gift',
		'今回の利用ポイント' => 'amount_point',
		'お支払い予定金額' => 'amount_all',
		'reservation_date' => 'reservation_date',	// 来店日時タイムスタンプ
		'キャンセル' => 'cancellation'
	);

	private $_data;

	function __construct($pairs = NULL) {
		$this->_data = array();

		if ($pairs !== NULL) {
			try {
				foreach($pairs as $key => $val) {
				
					if (!$this->setByKeyword($key, $val)) {
						throw new Exception($key);
					}
				}
			}
			catch (Exception $e) {
				die($e->GetMessage());
			}
		}
	}

	function __get($key) {
		try {
			if (array_key_exists($key, $this->_data)) {
				return $this->_data[$key];
			}
			throw new Exception();
		}
		catch (Exception $e) {
			print_r($e->GetMessage());
		}
	}

	function __set($key, $val) {
		$methodname = 'proc_'. $key;
		if (method_exists($this, $methodname)) {
			call_user_func(array($this, $methodname), $val);
		}
		else {
			$this->proc_other($this->_keylist[$key], $val);
		}
	}

	// メール見出し文言から値を設定
	function setByKeyword($keyword, $val) {
		if (array_key_exists($keyword, $this->_keylist)) {
			$key = $this->_keylist[$keyword];
			$this->$key = $val;
			return true;
		}
		else {
			error_log($keyword. ' is not exists.'. "\n", 3, ERROR_LOG);
		}
		return false;
	}

	// remove comma
	private function _rc($value) {
		return str_replace(',', '', $value);
	}

	function proc_other($key, $val) {
		$this->_data[$key] = $val;	// array
	}

	private function _proc_general($key, $val) {
		if (is_array($val)) {
			$v = array_shift($val);
		}
		else {
			$v = $val;
		}
		$this->_data[$key] = trim($v);
	}

	function proc_reserve_no($param) {
		$this->_proc_general('reserve_no', $param);
	}
	function proc_name($param) {
		$this->_proc_general('name', $param);
	}
	function proc_appointment($param) {
		$this->_proc_general('appointment', $param);

		// ex.) 2022年01月22日（土）19:00
		if (preg_match("/(20[0-9]{2})年([0-9]{2})月([0-9]{2})日（.+）([0-9]{2}):([0-9]{2})/", $this->appointment, $m)) {
			if ($resvdate = mktime($m[4], $m[5], 0, $m[2], $m[3], $m[1])) {
				$this->_proc_general('reservation_date', $resvdate);
			}
			else {
				$this->_proc_general('reservation_date', null);
			}
		}
	}
	function proc_reserve_staff($param) {
		$this->_proc_general('reserve_staff', $param);
	}
	function proc_menu($param) {
		$text = implode('', $param);
		$this->_data['menu'] = trim($text);
	}
	function proc_coupon($param) {
		if (count($param) > 0) {
			$this->_data['coupon'] = trim($param[0]);
		}
		else {
			$this->_data['coupon'] = '';
		}
	}
	function proc_amount($param) {
		if (count($param) > 0) {
			foreach($param as $line) {
				list($k, $v) = explode("　", $line);
				switch($k) {
				  case '予約時合計金額':
					if (preg_match("/^([0-9,]+)/", $v, $m)) {
						$this->_data['amount_total'] = $this->_rc($m[1]);
					}
					else {
						$this->_data['amount_total'] = 0;
					}
					break;
				  case '今回の利用ギフト券':
					if (preg_match("/^([0-9,]+)/", $v, $m)) {
						$this->_data['amount_gift'] = $this->_rc($m[1]);
					}
					else {
						$this->_data['amount_gift'] = 0;
					}
					break;
				  case '今回の利用ポイント':
					if (preg_match("/^([0-9,]+)/", $v, $m)) {
						$this->_data['amount_point'] = $this->_rc($m[1]);
					}
					else {
						$this->_data['amount_point'] = 0;
					}
					break;
				  case 'お支払い予定金額':
					if (preg_match("/^([0-9,]+)/", $v, $m)) {
						$this->_data['amount_all'] = $this->_rc($m[1]);
					}
					else {
						$this->_data['amount_all'] = 0;
					}
					break;
				  default:
				}
			}
		}
	}
	function proc_request($param) {
		$this->_proc_general('request', $param);
	}
}

?>