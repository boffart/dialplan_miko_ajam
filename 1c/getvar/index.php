<?php
/*
// -- Скрипт предназначен для пакетного получения переменных канала.
v.1.3
// -- Зависимости:

PHP 4.4.9 / 5 / 7

// -- Пример использования:
	http://172.16.32.162/1c/getvar/index.php?channel=SIP/04-00000086&variables=CDR(linkedid),EXTEN
*/
$amp_conf = array(
  'ASTMANAGERHOST' => '127.0.0.1',
  'ASTMANAGERPORT' => '5038',
  'AMPMGRUSER' 	   => 'getvar1c',
  'AMPMGRPASS'     => 'dfsdfsdjfSSS33fksd',
  'ASTAGIDIR'	   => '/var/lib/asterisk/agi-bin',
);

$php_v = explode('.', phpversion());
if($php_v[0] <> 4){
	date_default_timezone_set('Europe/Moscow');
}

require($amp_conf['ASTAGIDIR'].'/phpagi.php');
function getvar($ami, $_channel, $_variable, $_actionid){
	$ret = $ami->GetVar($_channel, $_variable, $_actionid);
	// var_dump($ret);
	if($ret['Response'] == 'Success'){
		$val = $ret['Value'];
	}else{
		$val = '';
	}
	return $val;
}

$event = ''; $actionid = '';
if(array_key_exists('event',$_REQUEST)){
	$event     = $_REQUEST['event'];
}
if(array_key_exists('actionid',$_REQUEST)){
	$actionid     = $_REQUEST['actionid'];
}
$channel   = $_REQUEST['channel'];
$variables = $_REQUEST['variables'];

$ami = new AGI_AsteriskManager();
if($ami->connect($amp_conf['ASTMANAGERHOST'], $amp_conf['AMPMGRUSER'], $amp_conf['AMPMGRPASS'])){
	if($event == 'Ping'){
		echo("true");
		exit; 	
	}else if(empty($channel)){
		echo('New Structure("Result", false)'); 
		exit;	
	}
	$variables = explode(",", $variables);
	
	$result_part1 = 'Result';
	$result_part2 = 'true';
	foreach($variables as $var_name){
		if(empty($var_name)) continue;
		
		if($result_part1!='')
			$result_part1 .= ',';

		if($result_part2!='')
			$result_part2 .= ', ';

		$val = getvar($ami, $channel, $var_name, $actionid); 
		
		$vowels = array("(", ")");
		$var_name = str_replace($vowels, '_', $var_name);
		$result_part1 .= "p_$var_name";
		$result_part2 .= "\"$val\"";
	}
	
	$result = 'New Structure("'.$result_part1.'", '.$result_part2.')';
	print_r($result);
	// Отключаемся от AMI.
	$ami->disconnect();	
}else{
	echo('New Structure("Result", false)'); 	
}
?>