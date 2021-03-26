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
require_once __DIR__.'/src/UtilFunctions.php';
require_once __DIR__.'/src/Logger.php';
require_once __DIR__.'/src/phpagi.php';
require_once __DIR__.'/src/phpagi-asmanager.php';

use MIKO\Modules\ModuleSmartIVR\Lib\YandexTTS;
use MIKO\Modules\ModuleSmartIVR\Lib\SpeechProTTS;
use MIKO\Modules\ModuleSmartIVR\Lib\UtilFunctions;
use MIKO\Modules\Logger;

$filename = __DIR__.'/setting.json';
if(!file_exists($filename)){
    exit(4);
}

$settings = json_decode(file_get_contents($filename), true);
if(!$settings){
    exit(3);
}

$logger  = new Logger($settings);
$settings['logger'] = $logger;

$agi = new AGI();
$agi->exec('Ringing', '');
$agi->set_variable('AGIEXITONHANGUP', 'yes');
$agi->set_variable('AGISIGHUP', 'yes');
$agi->set_variable('__ENDCALLONANSWER', 'yes');

$did      = $agi->get_variable('FROM_DID',     true);
if(empty($did)){
    $did      = $agi->request['agi_extension'];
}
$linkedid = $agi->get_variable('CHANNEL(linkedid)',true);
$phone    = $agi->request['agi_callerid'];
try{
    $ivr_data = UtilFunctions::post1cSoapRequest(['Number'=>$phone, 'Linkedid'=>$linkedid, 'DID'=>$did], $settings);
    $settings = UtilFunctions::overrideConfigurationArray($settings, $ivr_data);
}catch (Exception $e){
    $ivr_data = false;
    $agi->set_variable('M_IVR_FAIL_DST', $settings['fail_dst']);
    exit(0);
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
$agi->Answer();
$agi->set_variable('MIKO_SMART_IVR_OK', '0');

$filename = $tts->Synthesize(explode('|', $ivr_data['greeting-text']??''), $voice).".wav";
if(!file_exists($filename)){
    $logger->write("Ошибка при генерации речи. Файл не был создан. \n", LOG_NOTICE);
    exit(0);
}
$filename = UtilFunctions::trimExtensionForFile($filename);
$timeoutWaiting = ($ivr_data['timeout-waiting]']??2)*1000;
$responsibleNumber = $ivr_data['responsible-number']??'';
$staff = $ivr_data['staff']??[];

$dst = '';
$dstBusy = false;
if(!empty($responsibleNumber)){
    // Соединяем с основным ответственным. Вес более 80%
    $agi->exec('Playback', $filename);
    $state = UtilFunctions::getExtensionStatus($responsibleNumber, $settings);
    if($state['extension-status'] >= 0){
        $agi->noop("responsibleNumber -> $responsibleNumber, state -> {$state['extension-status']}");
        $dst = $responsibleNumber;
    }
    $dstBusy = $state['extension-status'] > 0;
}elseif(count($staff)>0){
    $agi->noop('Count staff > 0');
    // Соединяем с первым по списку.
    // Есть возможность набрать добавочный.
    $result = $agi->get_data($filename, $timeoutWaiting, 3);
    $selectedNum = $result['result']??'';
    $staffNumber = '';
    foreach ($staff as $user => $number){
        $staffNumber =  $number;
        break;
    }
    $stateSelected  = UtilFunctions::getExtensionStatus($selectedNum, $settings);
    $stateStaff     = UtilFunctions::getExtensionStatus($staffNumber, $settings);
    if(!empty($selectedNum)){
        $agi->noop('Client enter number '. $stateSelected);
    }
    $agi->noop("stateSelected -> {$stateSelected['extension-status']}, stateStaff -> {$stateStaff['extension-status']}");
    if($stateSelected['extension-status'] >= 0){
        $agi->noop('Goto number '. $stateSelected);
        $dst = $stateSelected;
        $dstBusy = $stateSelected['extension-status'] > 0;
    }elseif($stateStaff['extension-status'] >= 0){
        $agi->noop('Goto number '. $staffNumber);
        $dst = $staffNumber;
        $dstBusy = $stateStaff['extension-status'] > 0;
    }
}else{
    // Проигрываем голосовое меню.
    // Есть возможность набрать добавочный.
    $result = $agi->get_data($filename, $timeoutWaiting, 3);
    $selectedNum = $result['result']??'';
    $agi->set_variable('MIKO_SMART_IVR_OK', '1');
    $state = UtilFunctions::getExtensionStatus($selectedNum, $settings);
    $agi->noop("stateSelected -> {$state['extension-status']} selectedNum -> {$selectedNum}");
    if($state['extension-status'] >= 0) {
        $dst = $selectedNum;
    }
    $dstBusy = $state['extension-status'] > 0;
}

if($dstBusy === true){
    $filename = $tts->Synthesize(explode('|', $ivr_data['busy-text']??''), $voice);
    $agi->exec('Playback', $filename);
    $agi->set_variable('MIKO_SMART_IVR_OK', '0');
    exit(0);
}

if(!empty($dst)){
    $agi->set_variable('MIKO_SMART_IVR_OK', '1');
    $agi->exec_goto($settings['context'], $dst, '1');
}