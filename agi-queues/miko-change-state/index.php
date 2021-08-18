<?php

$bootstrap_settings['freepbx_auth']=false;
if(!isset($_REQUEST['chan']) || empty($_REQUEST['chan'])){
    echo 'FAIL';
    exit(0);
}

$channel = $_REQUEST['chan'];

$data = 'Channel: Local/10000222@miko_ajam'.PHP_EOL.
        'Application: NoCDR'.PHP_EOL.
        "Setvar: chan=$channel".PHP_EOL.
        "Setvar: key=$channel".PHP_EOL.
        'Setvar: val=4'.PHP_EOL.
        'Setvar: command=put'.PHP_EOL.
        'Setvar: dbFamily=UserBuddyStatus'.PHP_EOL.
        '';
$base = ''.microtime(true).'.call';
$filename = '/tmp/'.$base;
file_put_contents($filename, $data);
shell_exec('/bin/mv -i '.$filename.' /opt/asterisk/outgoing/'.$base);