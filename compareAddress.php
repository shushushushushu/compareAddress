<?php

/**
 * 功能：对比详细地址相似度
 * 版本：1.0
 * author: yyz 2021-01-20
 * 描述: 对比两个收货地址的详细地址相似度，用于风控处理，不处理收货地址的固定省市区
 *
 */

// 引入控制器基类

class compareAddress
{
    const SIMILARATEEDGE = 0.8;//相似度占比,>=该值即认为一样

	/*判断地址相似度*/
	public function similarAddress($addr1, $addr2)
	{

		if(empty($addr1) || empty($addr2)){
			return false;
		}
		if($addr1 == $addr2){
			return true;
		}else{
			$similaRate = 0;
			$addr1 = $this->detectAddress($addr1);
			$addr2 = $this->detectAddress($addr2);
			if(!empty($addr1) && !empty($addr2)){
				$optnum = 0;
				$samenum = 0;
				if(isset($addr1['number']) && isset($addr2['number'])){
					$optnum++;
					if($addr1['number'] != $addr2['number']){
						return false;
					}else{
						$samenum++;
					}
				}
				if(isset($addr1['door']) && isset($addr2['door'])){
					$optnum++;
					if($addr1['door'] != $addr2['door']){
						return false;
					}else{
						$samenum++;
					}
				}
				if(isset($addr1['room']) && isset($addr2['room'])){
					$optnum++;
					if($addr1['room'] != $addr2['room']){
						return false;
					}else{
						$samenum++;
					}
				}
				if(isset($addr1['unit']) && isset($addr2['unit'])){
					$optnum++;
					if($addr1['unit'] != $addr2['unit']){
						return false;
					}else{
						$samenum++;
					}
				}
				if(isset($addr1['build']) && isset($addr2['build'])){
					$optnum++;
					if($addr1['build'] != $addr2['build']){
						return false;
					}else{
						$samenum++;
					}
				}
				if(isset($addr1['floor']) && isset($addr2['floor'])){
					$optnum++;
					if($addr1['floor'] != $addr2['floor']){
						return false;
					}else{
						$samenum++;
					}
				}

				if(isset($addr1['yard']) && isset($addr2['yard'])){
					$optnum++;
					if($addr1['yard'] != $addr2['yard']){
						return false;
					}else{
						$samenum++;
					}
				}

				if(isset($addr1['local']) && isset($addr2['local'])){
					$optnum++;
					$samenum += $this->compareStr($addr1['local'], $addr2['local']);
				}

				$optRate = $optnum > 0 ? bcdiv($samenum, $optnum, 2) : 1;
				$estateRate = $this->compareStr($addr1['estate'], $addr2['estate']);

				$resRate = bcadd(bcdiv($optRate, 2, 2), bcdiv($estateRate, 2, 2), 2);//小区名和具体楼房比例各占一半

				return $resRate >= self::SIMILARATEEDGE;//概率大于SIMILARATEEDGE即可认为是相同，可修改。
			}else{
				return false;
			}
		}
	}

	/*对比字符串,提前删除空格等不可见字符*/
	public function compareStr($str1, $str2)
	{
		// $str1 = str_replace(array(' ', '	'), '', $str1);
		// $str2 = str_replace(array(' ', '	'), '', $str2);
		if(empty($str1) || empty($str2)){
			return 0;
		}
		if($str1 == $str2){
			return 1;
		}
		$len1 = mb_strlen($str1, 'utf-8');
		$len2 = mb_strlen($str2, 'utf-8');
		if($len1 >= $len2){
			$maxStr = $str1;
			$minStr = $str2;
			$maxlen = $len1;
			$minlen = $len2;
		}else{
			$maxStr = $str2;
			$minStr = $str1;
			$maxlen = $len2;
			$minlen = $len1;
		}

		$maxSameLen = 0;
		for($i = $minlen; $i > 0; $i--){
			for($j = 0; $j <= $minlen - $i; $j++){
				$tar = mb_substr($minStr, $j, $i);
				if(strpos($maxStr, $tar) !== false){
					$maxSameLen = $i;
					// echo $tar;
					break 2;
				}
			}
		}
		return bcdiv($maxSameLen, bcdiv(bcadd($minlen, $maxlen), 2, 2), 2);
	}


	/*调整提取地址数据，存在不确定性*/
	public function detectAddress($addr)
	{
		$address = array();
		$addr = str_replace(array(' ', '	'), '', $addr);
		if(($lupos = strpos($addr, '路')) !== false){
			$lulen = strlen('路');
			$lustr = substr($addr, 0, $lupos + $lulen);
			if(preg_match('/\d+/', $lustr)){
				$addr = $this->uptonum($addr);
			}else{
				$addr = $lustr . $this->uptonum(substr($addr, $lupos + $lulen));
			}
		}elseif(($jiepos = strpos($addr, '街')) !== false){
			$jielen = strlen('街');
			$jiestr = substr($addr, 0, $jiepos + $jielen);
			if(preg_match('/\d+/', $jiestr)){
				$addr = $this->uptonum($addr);
			}else{
				$addr = $jiestr . $this->uptonum(substr($addr, $jiepos + $jielen));
			}
		}else{
			$addr = $this->uptonum($addr);
		}
		preg_match_all('/[\D|\s]+(\d+)/', $addr, $matches, PREG_OFFSET_CAPTURE);
		if(!empty($matches[1])){
			$locations = explode($matches[1][0][0], $addr);
			if(mb_substr($locations[1], 0, 1, 'utf-8') == '号' && !in_array(mb_substr($locations[1], 1, 1, 'utf-8'), ['院', '楼']) && (strpos($locations[0], '小区') == false)){
				$address['number'] = $matches[1][0][0];
				if(isset($matches[1][1])){
					$locations = explode($matches[1][1][0], mb_substr($locations[1], 1));
				}else{
					$address['estate'] = mb_substr($locations[1], 1);
					return $address;
				}
			}
			$estate = $locations[0];
			$offset = $matches[1][0][1];
			$room = array();
			$matCount = count($matches[1]);
			for($k = 1;$k <= $matCount;$k++){
				if(isset($matches[1][$k][1])){
					$len = $matches[1][$k][1] - $offset;
					$room[] = substr($addr, $offset, $len);
					$offset = $matches[1][$k][1];
				}else{
					$room[] = substr($addr, $offset);
					break;
				}
			}
			/*根据个人需求添加条件*/
			$minusNums = array();
			foreach ($room as $rk => $rom) {
				if(mb_substr($rom, strlen($matches[1][$rk][0]), 1) == '门'){
					$address['door'] = $matches[1][$rk][0];
				}elseif(strpos($rom, '院') !== false){
					$address['yard'] = $matches[1][$rk][0];
				}elseif(strpos($rom, '楼') !== false || strpos($rom, '栋') !== false){
					$address['build'] = $matches[1][$rk][0];
				}elseif(strpos($rom, '单元') !== false){
					$address['unit'] = $matches[1][$rk][0];
				}elseif(strpos($rom, '层') !== false){
					$address['floor'] = $matches[1][$rk][0];
				}elseif(strpos($rom, '室') !== false){
					$address['room'] = $matches[1][$rk][0];
				}elseif($rom == $matches[1][$rk][0] . '-' && isset($matches[1][$rk + 1][0]) && is_numeric($matches[1][$rk + 1][0])){
					$minusNums[] = $matches[1][$rk][0];
				}
			}

			if(!isset($address['room']) && is_numeric(substr($addr, -1))){
				$address['room'] = $matches[1][$matCount - 1][0];
			}
			if(isset($address['room']) && !isset($address['build']) && !empty($minusNums)){
				$address['build'] = $minusNums[0];
				if(!isset($address['floor']) && isset($minusNums[1])){
					$address['floor'] = $minusNums[1];
				}
			}
			if(empty($address)){
				$address['local'] = $locations[1];
			}
			$address['estate'] = $estate;
		}
		return $address;
	}

	/*大写数字转数字*/
	public function uptonum($str)
	{
		$upNum = array(
			'0' => '零',
			'1' => '一',
			'2' => '二',
			'3' => '三',
			'4' => '四',
			'5' => '五',
			'6' => '六',
			'7' => '七',
			'8' => '八',
			'9' => '九',
		);
		foreach ($upNum as $k => $up) {
			if(strpos($str, $up) !== false){
				$str = str_replace($up, $k, $str);
			}
		}
		/*$oldupNum = array(
			'1' => '壹',
			'2' => '贰',
			'3' => '叁',
			'4' => '肆',
			'5' => '伍',
			'6' => '陆',
			'7' => '柒',
			'8' => '捌',
			'9' => '玖',
		);*/
		/*foreach ($oldupNum as $k => $up) {
			if(strpos($str, $up) !== false){
				$str = str_replace($up, $k, $str);
			}
		}*/

		while(($tenpos = strpos($str, '十')) !== false){
			$selfLen = strlen('十');
			if($tenpos > 0){
				$leftChar = substr($str, $tenpos - 1, 1);
				$rightChar = substr($str, $tenpos + $selfLen, 1);
				if(is_numeric($leftChar) && is_numeric($rightChar)){
					$str = preg_replace('/十/', '', $str, 1);//每次替换一个，str_replace不能控制替换个数
				}elseif(!is_numeric($leftChar) && is_numeric($rightChar)){
					$str = preg_replace('/十/', '1', $str, 1);
				}elseif(!is_numeric($leftChar) && !is_numeric($rightChar)){
					$str = preg_replace('/十/', '10', $str, 1);
				}

			}elseif($tenpos == 0){
				$rightChar = substr($str, $selfLen, 1);
				if(is_numeric($rightChar)){
					$str = preg_replace('/十/', '1', $str, 1);
				}else{
					$str = preg_replace('/十/', '10', $str, 1);
				}
			}
		}

		return $str;
	}
}


$compareAddress = new compareAddress();
$addr1 = "广渠大理寺小区  	3号楼  七单元 302室 ";
$addr2 = "广渠大理寺小区3号楼302";
$res = $compareAddress->similarAddress($addr1, $addr2);var_dump($res);
// $addr1 = '北苑路甲13号院北辰新纪元2号楼10层2-1005';
// var_dump($compareAddress->detectAddress($addr1));
// var_dump($compareAddress->compareStr($addr1, $addr2));