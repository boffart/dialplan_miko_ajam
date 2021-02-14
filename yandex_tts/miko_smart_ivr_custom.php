#!/usr/bin/php
<?php
/**
 * Copyright © Boffart - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 2 2020
 */

require_once __DIR__.'/src/YandexTTS.php';
require_once __DIR__.'/src/SpeechProTTS.php';
require_once __DIR__.'/src/Logger.php';

use MIKO\Modules\ModuleSmartIVR\Lib\YandexTTS;
use MIKO\Modules\ModuleSmartIVR\Lib\SpeechProTTS;
use MIKO\Modules\Logger;

$filename = __DIR__.'/setting.json';
if(!file_exists($filename)){
    exit(4);
}

$settings = json_decode(file_get_contents($filename), true);
if(!$settings){
    exit(3);
}

function get_rout_from_1C($url, $phone, $auth, $did, $id){
    global $logger;

    $result = null;
    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, 		  "{$url}/$phone?did=$did&linkedid=$id");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 	  4);
    curl_setopt($curl, CURLOPT_USERPWD, 	  $auth);

    $url_data = parse_url($url);
    $scheme   = $url_data['scheme'] ?? 'http';

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
        $logger->write($server_output,	LOG_NOTICE);
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

$logfile = null;
$logger  = new Logger('TTS','ModuleSmartIVR');
$settings['logger'] = $logger;
if(!empty($settings['log-file']) ){
    $settings['logger']->setLogFile($settings['log-file']);
}

$IS_TEST    = $argv[1];
if($IS_TEST === '1'){
    class AGI{
        public function set_variable($var, $val){
            echo "SET $var $val\n";
        }
    }
    $agi      = new AGI();
    $phone    = $argv[2]??'73432374000';
    $did      = $argv[3]??'79257184255';
    $linkedid = 'test-linkedid-'.time();

    $ivr_data = get_rout_from_1C($settings['url'], $phone, $settings['auth'], $did, $linkedid);

}else{
    require_once __DIR__.'/src/phpagi.php';
    $agi      = new AGI();
    $did      = $agi->get_variable('FROM_DID',     true);
    $linkedid = $agi->get_variable('CNAHHEL(linkedid)',true);
    $phone    = $agi->request['agi_callerid'];
    try{
        $ivr_data = get_rout_from_1C($settings['url'], $phone, $settings['auth'], $did, $linkedid);
    }catch (Exception $e){
        $ivr_data = false;
        $agi->set_variable('M_IVR_FAIL_DST', $settings['fail_dst']);
        exit(0);
    }
}

if(!$ivr_data){
    $logger->write('Go to M_IVR_FAIL_DST : '.$settings['fail_dst']."\n", LOG_NOTICE);
    $logger->write(''.print_r($ivr_data, true)."\n", LOG_NOTICE);
    $agi->set_variable('M_IVR_FAIL_DST', $settings['fail_dst']);
    exit(0);
}

$serviceTTS = $settings['tts-engine']??'ya';
if('ya' === $serviceTTS){
    $tts = new YandexTTS($settings);
}else{
    $tts = new SpeechProTTS($settings);
}

$voice = $ivr_data['voice']??'';
if(empty($voice)){
    $voice    = $settings[$serviceTTS.'-voice']??'';
}

$filename = $tts->Synthesize(explode('|', 'Тест '), $voice).".wav";


