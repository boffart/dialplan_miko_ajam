#!/usr/bin/php -q
<?php
/*-----------------------------------------------------
// ООО "МИКО" - 2017-10-12	 
// v.4.1 	 - 1C_HistoryFax - 10000444		|
// Получение истории факсимильных сообщений |
-------------------------------------------------------
Asterisk     - 1.8 / 10 / 12 / 13
AGI          - Written for PHP 5.3 / 7.0.22
PHP          - 5.1.6+
-------------------------------------------------------
/var/lib/asterisk/agi-bin/1C_HistoryFax.php
-------------------------------------------------------*/

require_once(__DIR__.'/func/pt1c_sql_func.php');
require_once(__DIR__.'/func/pt1c_ini_parser.php');
define('PT1C_SKRIPTNAME', basename($argv[0]));

if( isset($argv[1]) ){
	require_once(__DIR__.'/func/pt1c_phpagi_debug.php');
}else{	
	require_once('phpagi.php');
}

// Получение переменной AGI канала
//	
function GetVarChannnel($agi, $_varName){
  $v = $agi->get_variable($_varName);
  if(!$v['result'] == 0){
    return $v['data'];
  }
  else{
    return "";
  }
} // GetVarChannnel($_agi, $_varName)

$agi = new AGI();
// 1.Формируем запрос и сохраняем результат выполнения во временный файл
$chan    = GetVarChannnel($agi,'v1');
$date1   = GetVarChannnel($agi,'v2');
$date2   = GetVarChannnel($agi,'v3');

$db_name = "asteriskcdrdb";
/*------------------------------------------*/
$ini = new pt1c_ini_parser();
$ini->read('/etc/asterisk/extensions.conf');
$username 	= $ini->get('globals', 'AMPDBUSER');
$password 	= $ini->get('globals', 'AMPDBPASS');
$dbname 	= $ini->get('globals', 'AMPDBNAME');
$dbhost 	= $ini->get('globals', 'AMPDBHOST');
$dbhandle 	= connect_db($dbhost, $username, $password, $dbname);
/*------------------------------------------*/
// //////////////////// //////////////////// //////////////////// //////////////////// //////////////////
// MySQL
$zapros=
"SELECT 
	 `a`.`calldate`,
	 `a`.`src`,
	 `a`.`dst`,
	 `a`.`lastdata`,
	 `a`.`uniqueid`,
	 `a`.`lastapp`,
	 `a`.`clid`,
	 `a`.`linkedid`
FROM
	(SELECT * from `$db_name`.`PT1C_cdr` where `calldate` BETWEEN '$date1' AND '$date2')AS a
WHERE `a`.`recordingfile`!='' AND (`a`.`userfield`='SendFAX' OR `a`.`userfield`='ReceiveFAX')	
";

$res_q 	  	 = query_db($dbhandle, $zapros);
if($res_q){
	// ------------------------------------------------------------------
	// 2. Обрабатываем временный файл и отправляем данные в 1С
	$result = ""; $ch = 1;
	// обходим файл построчно
	$_data = fetch_assoc($res_q);
	while ($_data) {
		// набор символов - разделитель строк
		if(! $result=="") $result = $result.".....";
		
		foreach($_data as $field){
			$result=$result.trim(str_replace(" ", '\ ', $field)).'@.@';
		}
		// если необходимо отправляем данные порциями
		if($ch == 8){
			// отправляем данные в 1С, обнуляем буфер
		    $agi->exec("UserEvent", "FaxFromCDR,Channel:$chan,Date:$date1,Lines:$result");
		    $result = ""; $ch = 1;
		}
		$ch = $ch + 1;
		$ar_str = fetch_assoc($res_q);
	} // 
	
	// проверяем, есть ли остаток данных для отправки
	if(!$result == ""){
	    $agi->exec("UserEvent", "FaxFromCDR,chan1c:$chan,Date:$date1,Lines:$result");
	}
}
// завершающее событие пакета, оповещает 1С, что следует обновить историю
$agi->exec("UserEvent", "Refresh1CFAXES,chan1c:$chan,Date:$date1");

// отклюаем запись CDR для приложения
// $agi->exec("NoCDR", "");
// ответить должны лишь после выполнения всех действий
// если не ответим, то оргининация вернет ошибку 
$agi->answer(); 
?>​