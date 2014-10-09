#!/usr/bin/php -q
<?php
/*-----------------------------------------------------
// ООО "МИКО" - 2014-03-04	 
// v.3.2 	 - 1С_Playback - 10000777 
// Поиск имени файла записи для воспроизведения в 1С 
-------------------------------------------------------
FreePBX      - 2.10:
AGI          - Written for PHP 4.3.4 version 2.0
PHP          - 5.1.6
sqlite3 	 - 3.3.6
-------------------------------------------------------
/var/lib/asterisk/agi-bin/1C_Playback.php
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
$chan 	    = GetVarChannnel($agi, "chan");
$uniqueid1c = GetVarChannnel($agi, "uniqueid1c");
$recordingfile    = ""; 
  
if(strlen($uniqueid1c) >= 4){
	$db_name = GetVarChannnel($agi,'CDRDBNAME');
	$db_name = !empty($amp_conf['CDRDBNAME'])?$amp_conf['CDRDBNAME']:"asteriskcdrdb";
	
	/*------------------------------------------*/
	$AGIDB = new AGIDB($agi, $db_name);

	// 1.Формируем запрос
	$zapros = "SELECT recordingfile FROM `$db_name`.`PT1C_cdr` WHERE `linkedid` LIKE '$uniqueid1c%' LIMIT 1";     
	$results= $AGIDB->sql($zapros, 'NUM');
	
	if(count($results)>=1 && count($results[0])==1){
		$ar_str=$results[0];
		$filename  = $ar_str[0];
		$searchDir = GetVarChannnel($agi, "ASTSPOOLDIR").'/monitor/';
		
		$recordingfile = $searchDir.$filename;
	} // count fields $results	  
}

$agi->verbose($recordingfile, '3');

if(is_file($recordingfile)) {
    $response = "CallRecord,Channel:$chan,FileName:$recordingfile";
}else{
    $response = "CallRecordFail,Channel:$chan,uniqueid1c:$uniqueid1c";
}
// отсылаем сообщение в 1С
$agi->exec("UserEvent", $response);  

// отклюаем запись CDR для приложения
// $agi->exec("NoCDR", "");
// ответить должны лишь после выполнения всех действий
// если не ответим, то оргининация вернет ошибку 
$agi->answer(); 
?>​