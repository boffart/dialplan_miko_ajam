<?php 
/*-----------------------------------------------------
// ООО "МИКО" - 2012-11-04 
// v.2.1 	  - Загрузка TIF / PDF файлов на Askozia
-------------------------------------------------------
FreePBX       - 2.11
PHP           - 5.1.6
-------------------------------------------------------
/var/www/1c/download.php

http://10.0.1.22/1c/download/index.php?type=Records&view=2014-10/06/in_1001_2014-10-06-09-56-45.gsm
-------------------------------------------------------*/

$ASTSPOOLDIR = '/var/spool/asterisk/';
$tmpdir = 	   '/var/www/1c/tmp/'; 		// владелец - пользователь apache
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

	}elseif ($_GET['type']=="Records" && file_exists($recdir.$_GET['view']) ){
		$recordingfile = $recdir.$_GET['view'];
		
		$wavfile = $tmpdir.basename($recordingfile).'.wav';
		$name      = basename($_GET['view']);
	    $extension = strtolower(substr(strrchr($name,"."),1));

		if($extension == "wav"){
			system('cp '.$recordingfile.' '.$wavfile.' > /dev/null 2>&1');
		}else{
			system('sox '.$recordingfile.' -r 8000 -a '.$wavfile.' > /dev/null 2>&1');
		}
	    
	    
	    // This will set the Content-Type to the appropriate setting for the file
	    $ctype ='';
	    switch( $extension ) {
	      case "mp3": $ctype="audio/mpeg"; break;
	      case "wav": $ctype="audio/x-wav"; break;
	      case "Wav": $ctype="audio/x-wav"; break;
	      case "WAV": $ctype="audio/x-wav"; break;
	      case "gsm": $ctype="audio/x-gsm"; break;
	      // not downloadable
	      default: die("<b>404 File not found!</b>"); break ;
	    }
	    // need to check if file is mislabeled or a liar.
	    $fp=fopen($wavfile, "rb");
	    if ($ctype && $fp) {
			$size      = filesize($wavfile);
		    header("Pragma: public");
		    header("Expires: 0");
		    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		    header("Cache-Control: public");
		    header("Content-Description: wav file");
		    header("Content-Type: ".$ctype);
		    header("Content-Disposition: attachment; filename=".basename($wavfile));
		    header("Content-Transfer-Encoding: binary");
		    header("Content-length: ".$size);
		    ob_clean();
		    fpassthru($fp);
		}else{
			echo '<b>404 File not found!</b>';
		}
		unlink($wavfile);
	}else{
		echo '<b>404 File not found!</b>';
	}
	exit;
}else{
	echo '<b>404 File not found!</b>';
}
?>
