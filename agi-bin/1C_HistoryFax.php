#!/usr/bin/php -q
<?php
/*-----------------------------------------------------
// ООО "МИКО" - 2014-03-04	 
// v.3.1 	 - 1C_HistoryFax - 10000444		|
// Получение истории факсимильных сообщений |
-------------------------------------------------------
FreePBX      - 2.10:
Asterisk     - 1.8.11
AGI          - Written for PHP 4.3.4 version 2.0
PHP          - 5.1.6
sqlite3 	 - 3.3.6
-------------------------------------------------------
/var/lib/asterisk/agi-bin/1C_HistoryFax.php
-------------------------------------------------------*/

require_once('phpagi.php');
require_once('func/1C_sql_class.php');

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

$db_name = GetVarChannnel($agi,'CDRDBNAME');
$db_name = !empty($amp_conf['CDRDBNAME'])?$amp_conf['CDRDBNAME']:"asteriskcdrdb";
/*------------------------------------------*/
$AGIDB = new AGIDB($agi, $db_name);
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
$output 	= array();

$output= $AGIDB->sql($zapros, 'NUM');
// ------------------------------------------------------------------
// 2. Обрабатываем временный файл и отправляем данные в 1С
$result = ""; $ch = 1;
// обходим файл построчно
foreach($output as $_data){
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
} // 

// проверяем, есть ли остаток данных для отправки
if(!$result == ""){
    $agi->exec("UserEvent", "FaxFromCDR,Channel:$chan,Date:$date1,Lines:$result");
}
// завершающее событие пакета, оповещает 1С, что следует обновить историю
$agi->exec("UserEvent", "Refresh1CFAXES,Channel:$chan,Date:$date1");

// отклюаем запись CDR для приложения
// $agi->exec("NoCDR", "");
// ответить должны лишь после выполнения всех действий
// если не ответим, то оргининация вернет ошибку 
$agi->answer(); 
?>​