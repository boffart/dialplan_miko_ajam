#!/usr/bin/php -q
<?php
/*-----------------------------------------------------
// ООО "МИКО" - 2014-03-04	 
// v.4.2 	 - 1С_Playback - 10000777 
// Поиск имени файла записи для воспроизведения в 1С 
-------------------------------------------------------
Asterisk     - 1.8 / 10 / 12 / 13
AGI          - Written for PHP 5.3 / 7.0.22
PHP          - 5.1.6+
-------------------------------------------------------
/var/lib/asterisk/agi-bin/1C_Playback.php
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

// Проверяет, существует ли файл с указанным именем
// 
function rec_file_exists($filename){
	if (@filetype($filename) == "file")
		return true;
	else
		return false;
}

$agi = new AGI();

$chan 	    = GetVarChannnel($agi, "chan");
$uniqueid1c = GetVarChannnel($agi, "uniqueid1c");
$attr_name = "chan1c";

$sub_dir    = ""; // вложенная директория для поиска файла записи / факса
$search_file='';
$db_name 	= "asteriskcdrdb";

if(strlen($uniqueid1c) >= 4){
	
	/*------------------------------------------*/
	$ini = new pt1c_ini_parser();
	$ini->read('/etc/asterisk/extensions.conf');
	$username 	= $ini->get('globals', 'AMPDBUSER');
	$password 	= $ini->get('globals', 'AMPDBPASS');
	$dbname 	= $ini->get('globals', 'AMPDBNAME');
	$dbhost 	= $ini->get('globals', 'AMPDBHOST');
	$dbhandle 	= connect_db($dbhost, $username, $password, $dbname);
	/*------------------------------------------*/
	// 1.Формируем запрос
	// форируем и выполняем запрос
	$zapros ="SELECT 
					DATE_FORMAT(`calldate`,'%Y/%m/%d%/') AS sub_dir,
					uniqueid AS uniqueid,
					recordingfile AS recordingfile
			  FROM $db_name.PT1C_cdr 
	  		  WHERE linkedid = '$uniqueid1c'";     

	$search_file   = '';
	$res_q 	  	 = query_db($dbhandle, $zapros);

	if($res_q){
	  	$searchDir = GetVarChannnel($agi, "ASTSPOOLDIR").'/monitor/';
		$ar_str = fetch_assoc($res_q);

		while ($ar_str) {
			$sub_dir  = $ar_str['sub_dir'];
			$filename = $ar_str['recordingfile'];				
				
			if(rec_file_exists($searchDir.$filename)){
				// файл лежит в директории $searchDir
				$_idle_name = $searchDir.$filename; 
			}else if(rec_file_exists($searchDir.$sub_dir.basename($filename)) ){
				// файл лежит во вложенной директории
				$_idle_name = $searchDir.$sub_dir.basename($filename); 
			}else if(rec_file_exists(basename($filename)) ){
		  		// известен полный путь к файлу	
		  		$_idle_name = basename($filename);  
			}else if(rec_file_exists('/var/spool/asterisk/monitor/'.basename($filename)) ){
		  		// известен полный путь к файлу	
		  		$_idle_name = '/var/spool/asterisk/monitor/'.basename($filename);  
			}else if(rec_file_exists($filename)){
			 	// известен полный путь к файлу	
				$_idle_name = $filename;  
			}else{
				$ar_str = fetch_assoc($res_q);
				$_idle_name = '';
				continue;
			}
			if(!$search_file=="") $search_file = $search_file."@.@";	
				$search_file = $search_file.$_idle_name; 
			
			$ar_str = fetch_assoc($res_q);
		} // foreach
	} // $RecFax == "FAX"
} // if(count()>=1)

if(!$search_file=='') {
    $response = "CallRecord,$attr_name:$chan,fPath:80/admin/1c/download/index.php?type=Records&view=,FileName:$search_file,uniqueid1c:$uniqueid1c";
}else{
    $response = "CallRecordFail,$attr_name:$chan,uniqueid1c:$uniqueid1c";
}
// отсылаем сообщение в 1С
$agi->exec("UserEvent", $response);  

// отклюаем запись CDR для приложения
// $agi->exec("NoCDR", "");
// ответить должны лишь после выполнения всех действий
// если не ответим, то оргининация вернет ошибку 
$agi->answer(); 
?>​