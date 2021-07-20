<?php
//$whitelist = array(
//    '127.0.0.1',
//    '::1'
//);
//
//if(!in_array($_SERVER['REMOTE_ADDR'], $whitelist)){
//    echo 'only localhost connections are possible';
//    return;
//}

$filename = '/usr/src/dialplan-miko-ajam/agi-queues/agents.dump';
$bootstrap_settings['freepbx_auth']=false;

if(file_exists($filename)){
    echo file_get_contents($filename);
}else{
    echo "{}";
}
