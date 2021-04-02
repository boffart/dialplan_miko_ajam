#!/usr/bin/php
<?php
/**
 * Copyright © Boffart - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 2 2020
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__.'/src/YandexTTS.php';
require_once __DIR__.'/src/SpeechProTTS.php';
require_once __DIR__.'/src/UtilFunctions.php';
require_once __DIR__.'/src/Logger.php';
require_once __DIR__.'/src/phpagi-asmanager.php';

use MIKO\Modules\ModuleSmartIVR\Lib\YandexTTS;
use MIKO\Modules\ModuleSmartIVR\Lib\SpeechProTTS;
use MIKO\Modules\ModuleSmartIVR\Lib\UtilFunctions;
use MIKO\Modules\Logger;

$filename = __DIR__.'/setting.json';
if(!file_exists($filename)){
    echo("File '$filename' not found.". PHP_EOL);
    exit(4);
}

$settings = json_decode(file_get_contents($filename), true);
if(!$settings){
    echo("File '$filename' is not JSON.". PHP_EOL);
    exit(3);
}

$logger  = new Logger($settings);
$settings['logger'] = $logger;

$phone    = $argv[2]??'79621753111';
$did      = $argv[3]??'74832320330';
$linkedid = 'test-linkedid-'.time();

print_r(UtilFunctions::getExtensionStatus('201', $settings));

echo("-----------------------". PHP_EOL);
echo("Start SOAP request to CRM.". PHP_EOL);
$ivr_data = UtilFunctions::post1cSoapRequest(['Number'=>$phone, 'Linkedid'=>$linkedid, 'DID'=>$did], $settings);
if(!$ivr_data){
    echo("Error get rout from 1C.". PHP_EOL);
}
// print_r($settings);
$settings = UtilFunctions::overrideConfigurationArray($settings, $ivr_data);
// print_r($settings);
print_r($ivr_data);

echo("-----------------------". PHP_EOL);
echo("Start request to Yandex TTS.". PHP_EOL);
$tts      = new YandexTTS($settings);
$filename = $tts->Synthesize(explode('|', 'Тест ' . time()), $settings['ya-voice']).".wav";
if(file_exists($filename)){
    $result = 'OK '.$filename;
}else{
    $result = 'FALSE';
}
echo("Result - {$result}". PHP_EOL);
echo("-----------------------". PHP_EOL);


$dst      = '100001';
$filename = $tts->Synthesize(explode('|', 'Сотрудник занят'), $settings['ya-voice']);
// $agi->exec('Playback', $filename);
$ttsDir   = $settings['tts-dir']??'';
$mohClass = "moh-{$did}";
$mohDir   = "{$ttsDir}{$did}/{$dst}";
$srcFile  = "{$filename}.wav";
$dstFile  = "{$mohDir}/".basename($srcFile);

if(file_exists($srcFile)) {
    exec("/usr/bin/mkdir -p {$mohDir}");
    exec("/usr/bin/cp {$srcFile} {$dstFile}");
}
// Добавим MOH class
$mohConfig = '/etc/asterisk/musiconhold_miko_custom.conf';
$mohCfgData= '';
if(file_exists($mohConfig)){
    $mohCfgData = file_get_contents($mohConfig);
}
if(strpos("", "[$mohClass]") === FALSE){
    $mohCfgData = PHP_EOL.
        "[$mohClass]". PHP_EOL.
        'mode=files'. PHP_EOL.
        "directory={$mohDir}";
    file_put_contents($mohConfig, $mohCfgData);
    exec("/usr/sbin/asterisk -rx 'moh reload'");
}


//**/

/**
echo("Start request to Speech Pro TTS.". PHP_EOL);
$tts      = new SpeechProTTS($settings);
$filename = $tts->Synthesize(explode('|', 'Тест ' . time()), $settings['sp-voice']).".wav";
if(file_exists($filename)){
    $result = 'OK '.$filename;
}else{
    $result = 'FALSE';
}
echo("Result - {$result}". PHP_EOL);
echo("-----------------------". PHP_EOL);
//**/