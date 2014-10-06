<?php 
/*-----------------------------------------------------
// ООО "МИКО" // 2014-03-05 
// v.3.3 // CDR - синхронизация
// Получение настроек с сервера Asterisk
-------------------------------------------------------
// Скрипт протестирован на FreePBX Distro v4:
//   PHP 5.3.3
// пример вызова скрипта:
//   http://HOST:80/admin/1c/cdr_xml/index.php?limit=ХХХ&offset=YYY
//			http://10.0.1.22/1c/cdr_xml/index.php?limit=10&offset=1 
//
//	 HOST - адрес сервера АТС.
//	 ХХХ  - количество пакетов (должно быть меньше 500)
//	 YYY  - смещение выборки.
-------------------------------------------------------*/
require_once('/var/lib/asterisk/agi-bin/func/pt1c_ini_parser.php');

// Количество записей
$limit  =  $_GET['limit'];
// Смещение для выборки следующих пакетов  
$offset =  $_GET['offset']; 
if ((ctype_digit($limit)) && (ctype_digit($offset))) {
	if ($limit > 500) {
		echo ("<pre>The variable 'limit' should be less than 500</pre>");
	}else {
		$file_cdr_mysql='/etc/asterisk/extensions.conf';
	
		$ini = new pt1c_ini_parser();
		$ini->read($file_cdr_mysql);
		$username = $ini->get('globals', 'AMPDBUSER');
		$password = $ini->get('globals', 'AMPDBPASS');
		$dbname = $ini->get('globals', 'AMPDBNAME');
		$dbhost = $ini->get('globals', 'AMPDBHOST');
		
		$output 	= array();
		
		$dbhandle = mysql_connect($dbhost, $username, $password);
		if (!$dbhandle) {
		    echo('Error connect: ' . mysql_error());
		}
		mysql_select_db($dbname,$dbhandle);
		
		$res_q = mysql_query("select * from PT1C_cdr limit ".$limit." offset ".$offset);
		if(!$res_q){
			echo 'Error query: '.mysql_error();
		}
		
		// echo mysql_num_rows($res_q);
		$xml_output = "<?xml version=\"1.0\"?>\n"; 
		$xml_output.= "<cdr-table>\n"; 
		while ($_data = mysql_fetch_assoc($res_q)) {
			
			$atributs = '';
			foreach($_data as $tmp_key => $tmp_val){
				$atributs.=$tmp_key."=\"".urlencode($tmp_val)."\" ";
			}
			$xml_output.= "<cdr-row $atributs />\n"; 
		}
		mysql_close($dbhandle);
		$xml_output .= "</cdr-table>"; 
		echo "$xml_output";	
	}
}else {
	echo ("<pre>Variable 'limit' and 'offset' must be numeric.</pre>");
}
?>