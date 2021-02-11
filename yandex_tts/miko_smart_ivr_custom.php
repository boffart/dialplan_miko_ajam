#!/usr/bin/php
<?php
/**
 * Copyright © Boffart - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 2 2020
 */

require_once __DIR__.'/src/YandexTTS.php';
require_once __DIR__.'/src/Logger.php';

use MIKO\Modules\ModuleSmartIVR\Lib\YandexTTS;
use MIKO\Modules\Logger;

$settings = json_decode(file_get_contents(__DIR__.'/setting.json'), true);
if(!$settings){
    exit(3);
}
function get_rout_from_1C($url, $phone, $auth, $did, $id){
    global $logger;

    $result = null;
    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, 		  "{$url}/$phone?did=$did&linkedid=$id");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 	  10);
    curl_setopt($curl, CURLOPT_USERPWD, 	  $auth);

    $url_data = parse_url($url);
    $scheme   = 'http';
    if(isset($url_data['scheme'])){
        $scheme =  $url_data['scheme'];
    }

    if($scheme === 'https'){
        $t_false = $scheme !== 'https';
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $t_false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $t_false);
    }

    $server_output = curl_exec($curl);
    $code          = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    // В некоторых случаях нужно отсеч первый битый символ псевдо пробела.
    $server_output = substr($server_output, strpos($server_output,'{'));

    if($code !== 200){
        $logger->write('ERROR http code from 1c = '.$code."\n",LOG_NOTICE);
        $logger->write("{$url}/$phone?did=$did",LOG_NOTICE);
        $logger->write("$server_output",	LOG_NOTICE);
    }else{
        try{
	        $result = json_decode($server_output, true);
            if(!$result){
                $logger->write('Error format response: '.$server_output."\n", LOG_NOTICE);
            }
        } catch (Exception $e) {
            $logger->write('Error: '.$e->getMessage()."\n", LOG_NOTICE);
        }
    }

    return $result;
}

/**
 * Удаляет расширение файла.
 * @param        $filename
 * @param string $delimiter
 * @return string
 */
function trim_extension_file($filename, $delimiter='.'){
    // Отсечем расширение файла.
    $tmp_arr = explode("$delimiter", $filename);
    if(count($tmp_arr)>1){
        unset($tmp_arr[count($tmp_arr)-1]);
        $filename = implode("$delimiter", $tmp_arr);
    }
    return $filename;
}

$logfile = null;
$logger = new Logger('YandexTTS','ModuleSmartIVR');
if(!empty($settings['logfile']) ){
	$logger->setLogFile($settings['logfile']);
}

$IS_TEST    = $argv[1];
if($IS_TEST === '1'){
	class AGI{
		public function set_variable($var, $val){
			echo "SET $var $val\n";
		}
	}
    $logger->write('Start test'."\n", LOG_NOTICE);
	$agi      = new AGI();
    $did      = $argv[3]; // '73432374000';
    $phone    = $argv[2];
    $ivr_data = get_rout_from_1C($settings['url'], $phone, $settings['auth'], $did, $linkedid);
    if(isset($ivr_data['voice']) && !empty($ivr_data['voice'])){
        $voice    = $ivr_data['voice'];
    }else{
        $voice    = $settings['voice'];
    }
    $linkedid = 'test-linkedid';
}else{
	require_once __DIR__.'/src/phpagi.php';
	$agi      = new AGI();
    $did      = $agi->get_variable('FROM_DID',     true);
    $linkedid = $agi->get_variable('CDR(linkedid)',true);
    $phone    = $agi->request['agi_callerid'];
    try{
        $ivr_data = get_rout_from_1C($settings['url'], $phone, $settings['auth'], $did, $linkedid);
        if(isset($ivr_data['voice']) && !empty($ivr_data['voice'])){
            $voice    = $ivr_data['voice'];
        }else{
            $voice    = $settings['voice'];
        }
    }catch (Exception $e){
        $ivr_data = false;
		$agi->set_variable('M_IVR_FAIL_DST', $settings['fail_dst']);
    }
}

$logger->agi = $agi;
$y_tts  = new YandexTTS($settings['tts_dir'], $settings['token'], $logger);


if(!$ivr_data){
    $logger->write('Go to M_IVR_FAIL_DST : '.$settings['fail_dst']."\n", LOG_NOTICE);
    $logger->write(''.print_r($ivr_data, true)."\n", LOG_NOTICE);
	$agi->set_variable('M_IVR_FAIL_DST', $settings['fail_dst']);
    exit(0);
}
$tts_dir  = rtrim($settings['tts_dir'], '/');

$keys = array('m','t','x','1','2','3','4','5','6','7','8','9','0');
foreach ($keys as $indx) {
    $i = 1;
    if(!isset($ivr_data[$indx])){
        continue;
    }
    $prefix = strtoupper($indx);
    foreach ($ivr_data[$indx] as $key => $val){
        $app = '';
        $data= '';

        foreach ($val as $k => $v){
            $app = $k;
            $data= trim($v);
            continue;
        }
        if($app === 'Playback' || $app === 'PlaybackFile'){
            if($app === 'Playback'){
                // Если это НЕ файл, то это текст для генерации речи.
                $filename = $y_tts->Synthesize(explode('|', $data), $voice).".wav";
            }else{
                $filename = "{$tts_dir}/{$data}";
            }
            if(!file_exists($filename)){
                continue;
            }
            if($indx === 'm' && count($ivr_data[$indx]) === $key + 1){
                $agi->set_variable("{$prefix}_IVR_FILE",          trim_extension_file($filename));
            }else{
                $agi->set_variable("{$prefix}_ACTION_{$i}",       'playback');
                $agi->set_variable("{$prefix}_ACTION_DATA_{$i}",  trim_extension_file($filename));
            }
            $i++;
        }elseif ($app === 'GOTO_START'){
            $agi->set_variable("{$prefix}_ACTION_{$i}",         'GOTO_START');
            $agi->set_variable("{$prefix}_ACTION_DATA_{$i}",    '');
            $agi->set_variable("{$prefix}_ACTION_TIMEOUT_{$i}", '');
        }elseif ($app === 'GOTO_T'){
            $agi->set_variable("{$prefix}_ACTION_{$i}",         'GOTO_T');
            $agi->set_variable("{$prefix}_ACTION_DATA_{$i}",    '');
            $agi->set_variable("{$prefix}_ACTION_TIMEOUT_{$i}", '');
        }elseif ($app === 'GOTO_M'){
            $agi->set_variable("{$prefix}_ACTION_{$i}",         'GOTO_M');
            $agi->set_variable("{$prefix}_ACTION_DATA_{$i}",    '');
            $agi->set_variable("{$prefix}_ACTION_TIMEOUT_{$i}", '');
        }elseif ($app === 'GOTO_X'){
            $agi->set_variable("{$prefix}_ACTION_{$i}",         'GOTO_X');
            $agi->set_variable("{$prefix}_ACTION_DATA_{$i}",    '');
            $agi->set_variable("{$prefix}_ACTION_TIMEOUT_{$i}", '');
        }elseif ($app === 'Hangup'){
            $agi->set_variable("{$prefix}_ACTION_{$i}",         'Hangup');
            $agi->set_variable("{$prefix}_ACTION_DATA_{$i}",    '');
            $agi->set_variable("{$prefix}_ACTION_TIMEOUT_{$i}", '');
            $i++;
        }elseif (is_numeric($app)){
            $agi->set_variable("{$prefix}_ACTION_{$i}",         'dial');
            $agi->set_variable("{$prefix}_ACTION_DATA_{$i}",    $app);
            $agi->set_variable("{$prefix}_ACTION_TIMEOUT_{$i}", $data);
            $i++;
        }
    }
}

