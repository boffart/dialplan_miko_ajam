#!/usr/bin/php
<?php
require_once __DIR__.'/src/phpagi.php';

/** @var AGI $agi */
$agi = new AGI();
$channel	= $agi->get_variable('MASTER_CHANNEL(M_TIMEOUT_CHANNEL)', 	true);
if(!empty($channel)){
    $AMPMGRUSER	= $agi->get_variable('AMPMGRUSER', 	true);
    $AMPMGRPASS	= $agi->get_variable('AMPMGRPASS', 	true);
    /** @var AGI_AsteriskManager $am */
    $am     = new AGI_AsteriskManager();
    $agi->noop("$AMPMGRUSER $AMPMGRPASS");
    $res    = $am->connect('127.0.0.1:5038', $AMPMGRUSER, $AMPMGRPASS, 'off');

    if($res){
        $agi->noop('Connect to AMI OK');
        $arr_res = $am->SetVar($channel, 'TIMEOUT(absolute)', '0');
        $agi->noop(json_encode($arr_res));
    }else{
        $agi->noop('Connect to AMI FALSE '.$res);
    }
}
