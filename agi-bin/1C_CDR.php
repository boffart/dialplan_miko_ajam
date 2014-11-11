#!/usr/bin/php -q
<?php
/*-----------------------------------------------------
// ООО "МИКО"- 2014-10-05 
// v.3.0	 - 1С_CDR - 10000555
// Получение настроек с сервера Asterisk
-------------------------------------------------------
FreePBX      - 2.10:
Asterisk     - 1.8.11
AGI          - Written for PHP 4.3.4 version 2.0
PHP          - 5.1.6
sqlite3 	 - 3.3.6
-------------------------------------------------------
/var/lib/asterisk/agi-bin/1C_CDR.php
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
$numbers = explode("-",GetVarChannnel($agi,'v4'));

$db_name = GetVarChannnel($agi,'CDRDBNAME');
$db_name = !empty($amp_conf['CDRDBNAME'])?$amp_conf['CDRDBNAME']:"asteriskcdrdb";
/*------------------------------------------*/
$AGIDB = new AGIDB($agi, $db_name);
/*------------------------------------------*/

// check for 2.8-style tables - START
$sql_fields="describe $db_name.PT1C_cdr";
$fields = $AGIDB->sql($sql_fields, 'ASSOC');
$recordingfile_exists = false;
foreach($fields as $_data){
	if($_data['Field']=='recordingfile'){
		$recordingfile_exists=true;
	}
}
$file_field = ($recordingfile_exists==true)?'recordingfile':'userfield';
// check for 2.8-style tables - END

// //////////////////// //////////////////// //////////////////// //////////////////// //////////////////
// MySQL
$zapros=
"SELECT 
	`a`.`calldate`,
	`a`.`src`,
	`a`.`dst`,
	`a`.`channel`,
	`a`.`dstchannel`,
	`a`.`billsec`,
	`a`.`disposition`,
	`a`.`uniqueid`,
	`a`.`$file_field`,
	`a`.`peer`,
	`a`.`lastapp`,
	`a`.`linkedid`
FROM
	(SELECT * from `$db_name`.`PT1C_cdr`	
	
	LEFT JOIN 
	
	(SELECT `peer` AS `peer`, `linkedid` AS `link`, `uniqueid` AS `uid`  
	 FROM `$db_name`.`cel` 
	 WHERE `eventtype`='BRIDGE_START'
	) AS `tmp_cel`
	
	ON 
	
	(`$db_name`.`PT1C_cdr`.`uniqueid` = `tmp_cel`.`link`  OR `$db_name`.`PT1C_cdr`.`uniqueid` = `tmp_cel`.`uid`)
	
	WHERE `calldate` BETWEEN '$date1' AND '$date2' ) 

AS `a`

WHERE ";


$rowCount = count($numbers);
for($i=0; $i < $rowCount; $i++) {	
	$num = $numbers[$i];
	if($num == ""){
		continue;
	}
	if(!$i == 0)
		$zapros=$zapros." OR ";
	
	$zapros=$zapros."(( `a`.`lastapp`='Transferred Call' AND `a`.`lastdata` like   '%/$num@%')
	                                OR ((`a`.`lastapp`='Dial' OR `a`.`lastapp`='Queue')
	                                        AND (`a`.`channel` like '%/$num-%'
	                                               OR `a`.`dstchannel` like '%/$num-%'
	                                               OR `a`.`dstchannel` like '%/$num@%'
	                                               OR `a`.`src`='$num'
	                                               OR `a`.`dst`='$num'))
									OR (`a`.`peer` LIKE '%/$num-%')
				                    OR (`a`.`peer` LIKE '%/$num@%')	                                
			        )";  
}	
$output= $AGIDB->sql($zapros, 'NUM');

// ------------------------------------------------------------------
// 2. Обрабатываем временный файл и отправляем данные в 1С
// необходимо отправлять данные пачками по 10 шт.
$result = ""; $ch = 1;

// обходим файл построчно
foreach($output as $_data){
    // набор символов - разделитель строк
    if(! $result=="") $result = $result.".....";
	
	foreach($_data as $field){
		$_field = ($recordingfile_exists==false)?str_replace("audio:", '', $field): $field;
		$result=$result.trim(str_replace(" ", '\ ', $_field)).'@.@';
	}

    // если необходимо отправляем данные порциями
    if($ch == 7){
        // отправляем данные в 1С, обнуляем буфер
        $agi->exec("UserEvent", "FromCDR,Channel:$chan,Date:$date1,Lines:$result");
        $result = ""; $ch = 1;
    }
    $ch = $ch + 1;
}

// проверяем, есть ли остаток данных для отправки
if(!$result == ""){
    $agi->exec("UserEvent", "FromCDR,Channel:$chan,Date:$date1,Lines:$result");
}

// завершающее событие пакета, оповещает 1С, что следует обновить историю
$agi->exec("UserEvent", "Refresh1CHistory,Channel:$chan,Date:$date1");

// отклюаем запись CDR для приложения
// $agi->exec("NoCDR", "");
// ответить должны лишь после выполнения всех действий
// если не ответим, то оргининация вернет ошибку 
$agi->answer(); 
?>​