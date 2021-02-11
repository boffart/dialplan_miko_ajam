<?php 
/*-----------------------------------------------------
// ООО "МИКО" - 2017-11-09 
// v.2.6 	  - Загрузка TIF / PDF файлов на Askozia
-------------------------------------------------------
Asterisk     - 1.8 / 10 / 12 / 13
AGI          - Written for PHP 5.3 / 7.0.22
PHP          - 5.1.6+
-------------------------------------------------------
/var/www/1c/download.php

http://10.0.1.22/1c/download/index.php?type=Records&view=2014-10/06/in_1001_2014-10-06-09-56-45.gsm
-------------------------------------------------------*/
// Проверяет, существует ли файл с указанным именем
// 
function rec_file_exists($filename){
	if (@filetype($filename) == "file")
		return true;
	else
		return false;
}

$ASTSPOOLDIR = '/var/spool/asterisk/';
$tmpdir = 	   '/tmp/'; 		// владелец - пользователь apache
$faxdir = $ASTSPOOLDIR."fax/";
$recdir = $ASTSPOOLDIR."monitor/";

if ($_GET['view']) {
	if ($_GET['type']=="FAX") 
	{
		$filename = $faxdir.basename($_GET['view']);
		$fp=fopen($filename, "rb");
	    if ($fp) {
		    header("Pragma: public");
		    header("Expires: 0");
		    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		    header("Cache-Control: public");
			header("Content-Type: application/octet-stream"); 
			header("Content-Disposition: attachment; filename=".basename($_GET['view']));
		    ob_clean();
		    fpassthru($fp);
		}else{
			echo '<b>404 File lib not found!</b>';
		}

	}elseif ( $_GET['type']=="Records" ){
		$tmp_view =  str_replace(' ', '+', $_GET['view']);
		
		$tmp_wavfile = $recdir.$tmp_view;
		$tmp_wavfile = str_replace('//', '/',$tmp_wavfile);
		
		if(rec_file_exists($tmp_wavfile)){
			$recordingfile = $tmp_wavfile;
		}else if(rec_file_exists($tmp_view)){
			$recordingfile = $tmp_view;
		}else{
			echo '<b>404 File not found!</b>';
			exit;
		}

	    $extension = strtolower(substr(strrchr($recordingfile,"."),1));
		$wavfile   = $tmpdir.basename($recordingfile);
		if($extension == "wav"){
			system('cp '.$recordingfile.' '.$wavfile.' > /dev/null 2>&1');
		}else{
			$extension = "wav";
			$wavfile   = $wavfile.'.'.$extension;
			system('sox '.$recordingfile.' -r 8000 '.$wavfile.' > /dev/null 2>&1');
		}
		$name = basename($wavfile);
        $size = filesize($wavfile);
	    // This will set the Content-Type to the appropriate setting for the file
	    $ctype ='';
        switch (strtolower($extension)) {
            case "mp3":
                $ctype = "audio/mpeg";
                break;
            case "wav":
                $ctype = "audio/x-wav";
                break;
            case "gsm":
                $ctype = "audio/x-gsm";
                break;
	      // not downloadable
	      default: die("<b>404 File not found!</b>"); break ;
	    }
	    // need to check if file is mislabeled or a liar.
	    $fp=fopen($wavfile, "rb");
	    if ($ctype && $fp) {
		    header("Pragma: public");
		    header("Expires: 0");
		    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		    header("Cache-Control: public");
		    header("Content-Description: wav file");
		    header("Content-Type: " . $ctype);
		    header("Content-Disposition: attachment; filename=" . $name);
		    header("Content-Transfer-Encoding: binary");
		    header("Content-length: " . $size);
		    ob_clean();
		    fpassthru($fp);
		}else{
			echo '<b>404 File not found!</b>';
		}
	}else{
		echo '<b>404 File not found!</b>';
	}
	exit;
}else{
	echo '<b>404 File not found!</b>';
}
?>
