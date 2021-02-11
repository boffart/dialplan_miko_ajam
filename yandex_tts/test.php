<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 2 2020
 */

require_once __DIR__.'/src/YandexTTS.php';
require_once __DIR__.'/src/Logger.php';
require_once __DIR__.'/src/phpagi-debug.php';

use MIKO\Modules\ModuleSmartIVR\Lib\YandexTTS;
use MIKO\Modules\Logger;

$settings = json_decode(file_get_contents(__DIR__.'/setting.json'), true);
if(!$settings){
    exit(3);
}

$tts_dir = $settings['tts_dir'];
$token   = $settings['token'];
$url     = $settings['url'];
$auth    = $settings['auth'];

$phone   = '74952293025';

$logger  = new Logger('YandexTTS','ModuleSmartIVR');
$agi     = new _AGI();
$logger->agi = $agi;
$y_tts   = new YandexTTS($tts_dir, $token, $logger);
$voice   = $settings['voice']; // alena
// {"x":[{"104":"4"},{"105":"4"}],"m":[{"Playback":"Вы позвонили в компанию СТЕКЛОДОМ."},{"Playback":"Уважаемый клиент. Если вы хотите получить консультацию по нашим продуктам и услугам, пожалуйста, оставайтесь на линии. Если у вас вопрос по гарантии и сервису, нажмите 1."}],"t":[{"105":"4"}],"did":null,"number":"74952293025","voice":"alena"}
function get_rout_from_1C($url, $phone, $auth){
    global $logger;

    $result = null;
    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, 		  "{$url}/$phone");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 	  4);
    curl_setopt($curl, CURLOPT_USERPWD, 	  $auth);

    $url_data = parse_url($url);
    $scheme = 'http';
    if(isset($url_data['scheme'])){
        $scheme =  $url_data['scheme'];
    }

    if($scheme === 'https'){
        $t_false = $scheme !== 'https';
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $t_false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $t_false);
    }

    $server_output = trim(curl_exec($curl));
    $server_output = substr($server_output, strpos($server_output,'{'));
    $code          = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if($code !== 200){
        $logger->write('ERROR http code = '.$code."\n",LOG_NOTICE);
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

$ivr_data  = get_rout_from_1C($url, $phone, $auth);
$keys = ['m','t','x'];
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
        if($app === 'Playback'){
            $filename = $y_tts->Synthesize(explode('|', $data), $voice);
            if(!file_exists("$filename.wav")){
                continue;
            }
            if($indx === 'm' && count($ivr_data[$indx]) === $key + 1){
                $agi->set_variable("{$prefix}_IVR_FILE",          $filename);
            }else{
                $agi->set_variable("{$prefix}_ACTION_{$i}",       'playback');
                $agi->set_variable("{$prefix}_ACTION_DATA_{$i}",  $filename);
            }
            $i++;
        }elseif (is_numeric($app)){
            $agi->set_variable("{$prefix}_ACTION_{$i}",         'dial');
            $agi->set_variable("{$prefix}_ACTION_DATA_{$i}",    $app);
            $agi->set_variable("{$prefix}_ACTION_TIMEOUT_{$i}", $data);
            $i++;
        }
    }
}