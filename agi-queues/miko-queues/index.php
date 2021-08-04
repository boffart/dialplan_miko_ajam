<?php

$filename = '/usr/src/dialplan-miko-ajam/agi-queues/agents.dump';
$bootstrap_settings['freepbx_auth']=false;

if(file_exists($filename)){
    echo file_get_contents($filename);
}else{
    echo "{}";
}
