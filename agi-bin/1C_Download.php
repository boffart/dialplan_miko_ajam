#!/usr/bin/php -q
<?php
/*-----------------------------------------------------
// ООО "МИКО" - 2014-03-04	 
// v.3.2 	  - 1C_Download - 10000666
// Загрузка факсов / записей разговоров на клиента
-------------------------------------------------------
FreePBX       - 2.11
AGI           - Written for PHP 4.3.4 version 2.0
PHP           - 5.1.6
sqlite3 	  - 3.3.6
-------------------------------------------------------
/var/lib/asterisk/agi-bin/1C_Download.php 
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

$chan       = GetVarChannnel($agi,'v1');
$uniqueid1c = GetVarChannnel($agi,'v2'); 
$faxrecfile = GetVarChannnel($agi,'v3'); 
$RecFax     = GetVarChannnel($agi,'v6'); 
// 
$sub_dir    = ""; // вложенная директория для поиска файла записи / факса
$_idle_name = "";
$filename   = "";

$file_exists = false;

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
		if($RecFax == "Records"){
			$filename  = $ar_str[0];
		  	$searchDir = GetVarChannnel($agi, "ASTSPOOLDIR").'/monitor/';
			//$agi->verbose($searchDir.$filename, 1);
		}else{
	  		$searchDir = GetVarChannnel($agi, "ASTSPOOLDIR").'/fax/';
	  		$filename  = $ar_str[2];  
		}
		
		if(is_file($searchDir.$filename)){
			$file_exists = true;
		}
	} // count fields $results	  
}

if($file_exists==true){
	$search_file = $filename;
	$req  	  = "type=$RecFax&view=$filename&";
	
	$chk_summ = sha1(strtolower($req));
	$path = "/1c/download/index.php?$req";
	$path.= "checksum=".$chk_summ;
	
	if($RecFax == "FAX"){
	    $agi->exec("UserEvent", "StartDownloadFax,Channel:$chan,FileName:80$path");
	}elseif($RecFax == "Records"){
	    $agi->exec("UserEvent", "StartDownloadRecord,Channel:$chan,FileName:80$path");
	} 
}else{
	if($RecFax == "FAX"){
	    $agi->exec("UserEvent", "FailDownloadFax,Channel:$chan");
	}elseif($RecFax == "Records"){
	    $agi->exec("UserEvent", "FailDownloadRecord,Channel:$chan");
	} 
}
// отклюаем запись CDR для приложения
// $agi->exec("NoCDR", "");
// ответить должны лишь после выполнения всех действий
// если не ответим, то оргининация вернет ошибку 
$agi->answer(); 
?>​