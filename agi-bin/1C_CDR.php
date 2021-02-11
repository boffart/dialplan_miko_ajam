#!/usr/bin/php -q
<?php
/*-----------------------------------------------------
// ООО "МИКО"- 2016-03-25 
// v.4.2 	 - 1С_CDR - 10000555
// Получение настроек с сервера Asterisk
-------------------------------------------------------
Asterisk     - 1.8 / 10 / 12 / 13
AGI          - Written for PHP 5.3 / 7.0.22
PHP          - 5.1.6+
-------------------------------------------------------
/var/lib/asterisk/agi-bin/1C_CDR.php
-------------------------------------------------------*/

require_once(__DIR__.'/func/pt1c_sql_func.php');
require_once(__DIR__.'/func/pt1c_ini_parser.php');

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

if( isset($argv[1]) ){
	define('PT1C_SKRIPTNAME', basename($argv[0]));
	require_once(__DIR__.'/func/pt1c_phpagi_debug.php');
}else{	
	require_once('phpagi.php');
}
$agi = new AGI();
// 1.Формируем запрос и сохраняем результат выполнения во временный файл
$chan    	= GetVarChannnel($agi,'v1');
$date1   	= GetVarChannnel($agi,'v2');
$date2   	= GetVarChannnel($agi,'v3');
$numbers 	= explode("-",GetVarChannnel($agi,'v4'));

$attr_name 	= 'chan1c';
$db_name 	  = "asteriskcdrdb";

/*------------------------------------------*/
$ini = new pt1c_ini_parser();
$ini->read('/etc/asterisk/extensions.conf');
$username 	= $ini->get('globals', 'AMPDBUSER');
$password 	= $ini->get('globals', 'AMPDBPASS');
$dbname 	= $ini->get('globals', 'AMPDBNAME');
$dbhost 	= $ini->get('globals', 'AMPDBHOST');
		
$dbhandle = connect_db($dbhost, $username, $password, $dbname);

/*------------------------------------------*/

// //////////////////// //////////////////// //////////////////// //////////////////// //////////////////
// MySQL

$uid = preg_replace('%[^A-Za-z0-9]%', '', uniqid(""));
$name_tmp_cdr 		= 'cdr_'.$uid;
$name_tmp_linkedid  = 'linkedid_'.$uid; 

query_db($dbhandle, "DROP TABLE IF EXISTS $db_name.$name_tmp_linkedid;");
query_db($dbhandle, "DROP TABLE IF EXISTS $db_name.$name_tmp_cdr;");

$res_q = query_db($dbhandle, "CREATE TEMPORARY TABLE $db_name.$name_tmp_linkedid  (linkedid varchar(32));");
$res_q = query_db($dbhandle, "CREATE TEMPORARY TABLE $db_name.$name_tmp_cdr  ( calldate TEXT, src TEXT, dst TEXT, channel TEXT, dstchannel  TEXT, billsec TEXT, disposition TEXT, uniqueid TEXT, lastapp TEXT, linkedid varchar(32),recordingfile TEXT, lastdata TEXT, did TEXT,INDEX (linkedid(8)));");

$res_q = $zapros="
INSERT INTO $db_name.$name_tmp_cdr ( calldate, billsec, channel, disposition, dst, dstchannel, lastapp, linkedid, recordingfile, src, uniqueid, lastdata, did) 
SELECT
  calldate, 
  billsec, 
  channel, 
  disposition,
  dst,
  dstchannel,
  lastapp,
  linkedid,
  recordingfile,
  src,
  uniqueid,
  lastdata,
  did AS DID
FROM $db_name.PT1C_cdr 
WHERE PT1C_cdr.calldate BETWEEN '$date1' AND '$date2';";

query_db($dbhandle, $zapros);
// print_r($zapros."\n");

$zapros="
INSERT INTO $db_name.$name_tmp_linkedid (linkedid) 
SELECT DISTINCT 
  $name_tmp_cdr.linkedid
FROM $db_name.$name_tmp_cdr 
WHERE 
linkedid<>'' AND ";

$rowCount = count($numbers);
for($i=0; $i < $rowCount; $i++) {
  $num = $numbers[$i];
  if($num == ''){
        continue;
  }
  if(!$i == 0)
        $zapros=$zapros.' OR ';

  $zapros=$zapros."(( lastapp='Transferred Call' AND lastdata LIKE '%/$num@%')
  					  OR (lastapp='Dial' AND disposition='NO ANSWER' AND (lastdata LIKE '%/$num\&%' OR lastdata LIKE '%/$num,%'))
                      OR channel LIKE '%/$num-%'
                      OR dstchannel LIKE '%/$num-%'
                      OR dstchannel LIKE '%/$num@%'
                      OR src='$num'
                      OR dst='$num'
                    )";  
}

$zapros=$zapros." LIMIT 1000";
query_db($dbhandle, $zapros);
// print_r($zapros."\n");

$zapros="SELECT 
  P.calldate,
  P.src,
  P.dst,
  P.channel,
  P.dstchannel,
  P.billsec,
  P.disposition,
  P.uniqueid,
  P.recordingfile,
  '',
  P.lastapp,
  P.linkedid,
  P.did
FROM $db_name.$name_tmp_cdr AS P INNER JOIN ".$name_tmp_linkedid." AS L ON L.linkedid = P.linkedid 
ORDER BY uniqueid;
";

$res_q = query_db($dbhandle, $zapros);
// print_r($zapros."\n");
if($res_q){
	$_data = fetch_assoc($res_q);
	// ------------------------------------------------------------------
	// 2. Обрабатываем временный файл и отправляем данные в 1С
	// необходимо отправлять данные пачками по 10 шт.
	$result = ""; $ch = 0;
	// обходим файл построчно
	while ($_data) {
	    // набор символов - разделитель строк
	    if(! $result=="") $result = $result.".....";
		foreach($_data as $field){
			$result=$result.trim(str_replace(" ", '\ ', $field)).'@.@';
		}
	    // если необходимо отправляем данные порциями
	    if($ch == 7){
	        // отправляем данные в 1С, обнуляем буфер
	        $agi->exec("UserEvent", "FromCDR,$attr_name:$chan,Date:$date1,Lines:$result");
	        $result = ""; $ch = 0;
	    }
		$_data = fetch_assoc($res_q);
		$ch = $ch + 1;
	}
	// проверяем, есть ли остаток данных для отправки
	if(!$result == ""){
	    $agi->exec("UserEvent", "FromCDR,$attr_name:$chan,Date:$date1,Lines:$result");
	}
}
close_db($dbhandle);
// завершающее событие пакета, оповещает 1С, что следует обновить историю
$agi->exec("UserEvent", "Refresh1CHistory,$attr_name:$chan,Date:$date1");
// отклюаем запись CDR для приложения
// ответить должны лишь после выполнения всех действий
// если не ответим, то оргининация вернет ошибку 
$agi->answer(); 
?>​