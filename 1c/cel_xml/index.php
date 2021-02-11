<?php 
/*-----------------------------------------------------
// ООО "МИКО" // 2016-03-25 
// v.3.4 // CEL - синхронизация 
// Получение настроек с сервера Asterisk
-------------------------------------------------------
// Скрипт протестирован на FreePBX Distro v4:
//   PHP 5.3.3
// пример вызова скрипта:
//   http://HOST:80/admin/1c/cel_xml/index.php?limit=ХХХ&offset=YYY
// 
//	 HOST - адрес сервера АТС.
//	 ХХХ  - количество пакетов (должно быть меньше 500)
//	 YYY  - смещение выборки.
-------------------------------------------------------*/
require_once('/var/lib/asterisk/agi-bin/func/pt1c_ini_parser.php');
require_once('/var/lib/asterisk/agi-bin/func/pt1c_sql_func.php');

$limit 	= (isset($_GET['limit'])) 	? $_GET['limit'] 	: $argv[1];
$offset = (isset($_GET['offset'])) 	? $_GET['offset'] 	: $argv[2];

if ((ctype_digit($limit)) && (ctype_digit($offset))) {
	if ($limit > 500) {
		echo ("<pre>The variable 'limit' should be less than 500</pre>");
	}else {
			
		$file_cdr_mysql='/etc/asterisk/extensions.conf';
		$ini = new pt1c_ini_parser();
		$ini->read($file_cdr_mysql);
		
		$username = $ini->get('globals', 'AMPDBUSER');
		$password = $ini->get('globals', 'AMPDBPASS');
		$dbname   = $ini->get('globals', 'AMPDBNAME');
		$dbhost   = $ini->get('globals', 'AMPDBHOST');
		
		$dbhandle = connect_db($dbhost, $username, $password, $dbname);
		$res_q 	  = query_db($dbhandle, "SELECT * FROM cel WHERE id>".$offset." ORDER BY id ASC LIMIT ".$limit);
		if(!$res_q){
			echo 'Error query.';
			exit;
		}
		$_data = fetch_assoc($res_q);
		$xml_output = "<?xml version=\"1.0\"?>\n"; 
		$xml_output.= "<cel-table>\n"; 
		while ($_data) {
			$atributs = '';
			foreach($_data as $tmp_key => $tmp_val){
				$atributs.=str_replace("_", '', $tmp_key)."=\"".urlencode($tmp_val)."\" ";
			}
			$xml_output.= "<cel-row $atributs />\n"; 
			$_data = fetch_assoc($res_q);
		}
		close_db($dbhandle);
		$xml_output .= "</cel-table>"; 
		echo "$xml_output";	
	}
}else {
	echo ("<pre>Variable 'limit' and 'offset' must be numeric.</pre>");
}
?>