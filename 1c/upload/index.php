<?php
/*-----------------------------------------------------
// ООО "МИКО" - 2012-11-04 
// v.2.1 	  - Загрузка TIF / PDF файлов на Askozia
-------------------------------------------------------
FreePBX       - 2.11
PHP           - 5.1.6
Ghostscript   - 8.70 (2009-07-31)

// Пример загрузки файла на АТС:
curl -F "file=@SCAN.tif" 'http://172.16.32.176:80/1c/upload.php'
curl -F "file=@SCAN.tif" 'http://172.16.32.176:80/1c/upload.php'
curl -F "file=@SCAN.tif" 'http://10.0.1.22:80/1c/upload/'
-------------------------------------------------------
/var/www/html/1c/upload.php
-------------------------------------------------------*/

/*
require_once('/var/lib/asterisk/agi-bin/func/pt1c_ini_parser.php');
$file_cdr_mysql='/etc/asterisk/extensions.conf';
$ini = new pt1c_ini_parser();
$ini->read($file_cdr_mysql);
$ASTSPOOLDIR = $ini->get('directories', 'astspooldir');
*/
$ASTSPOOLDIR = '/var/spool/asterisk/';

$tmpdir = '/tmp/';
$faxdir = $ASTSPOOLDIR."fax/";

if(!is_dir($faxdir)) mkdir($faxdir);
 
if (is_uploaded_file($_FILES['file']['tmp_name'])) {
	$filename = str_replace(" ","_",$_FILES['file']['name']);
	
	$name 	  = basename($filename);
	$filetype = strtolower(substr(strrchr($name,"."),1) );
	
	if ($filetype=="pdf") {
		$tif_filename = $faxdir.basename($name,'.pdf').'.tif';
		// move file to asterisk music-on-hold directory on media storage
		$pdf_filename = $faxdir.$name;
		
		if(move_uploaded_file($_FILES['file']['tmp_name'], $pdf_filename)){
			set_time_limit(900);
			$gs_path = exec('which gs');
			$res_str = exec($gs_path.' -q -dNOPAUSE -dBATCH -sDEVICE=tiffg4 -sPAPERSIZE=a4 -g1680x2285 -sOutputFile=\''.escapeshellarg($tif_filename).'\' \''.escapeshellarg($pdf_filename).'\' > /dev/null 2>&1');			
		}else{
	  		echo "Fail copy file!\n";
		}
	  	
  		if(is_file($tif_filename)){
	  		echo "File $name convert success.\n";
  		}else{
	  		echo "File $name convert fail.\n";
  		}
    }elseif($filetype=="tif"){
        $tif_filename = ''.$faxdir.$name;
        if (move_uploaded_file($_FILES['file']['tmp_name'], $tif_filename)){
            echo "File $name upload success.\n";
        }else{
            echo ("File $name fail. $tif_filename\n");
        }
	}else{
		echo "Upload failed. Only PDF format!\n";
	}
}else{
	echo "Upload failed. File not found!\n";
}
?>
