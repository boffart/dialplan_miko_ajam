<?php
/*
 * MikoPBX - free phone system for small business
 * Copyright © 2017-2021 Alexey Portnov and Nikolay Beketov
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace MikoPBX\Core\Bin;
use MikoPBX\Core\Workers\MikoCallRouting;
use MikoPBX\Core\Workers\MikoCallRoutingServer;

require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/../../../settings.php';

/**
 * Executes command exec() as background process.
 *
 * @param $command
 */
function mwExecBg($command)
{
    $noop_command = "/usr/bin/nohup {$command} > /dev/null 2>&1 &";
    exec($noop_command);
}

function startBeanstalk()
{
    $activeBeanstalk = MikoCallRoutingServer::getPidOfProcess('beanstalkd');
    if(count($activeBeanstalk) === 0){
        mwExecBg('/usr/bin/beanstalkd -l 127.0.0.1 -p 11300');
    }
}
// php -f /usr/src/dialplan-miko-ajam/agi-queues/src/Core/Bin/miko-queue-router.php check
if( !isset($argv[1]) ){
    exit(0);

}
echo "action $argv[1] ...".PHP_EOL;
if ($argv[1] === 'start'){
    startBeanstalk();
    $server = new MikoCallRoutingServer();
    $server->start();
}elseif ($argv[1] === 'check'){
    echo "Starting $argv[1] ...".PHP_EOL;
    $action = $argv[1];
    date_default_timezone_set('Europe/Moscow');
    startBeanstalk();
    $callRouting = new MikoCallRouting();
    $list = $callRouting->getListAgents();
    if(empty($list)){
        echo 'Need restart workers...'.PHP_EOL;
        $action = 'restart';
    }
    $activeProcesses = MikoCallRoutingServer::getPidOfProcess();
    if(count($activeProcesses) > 0){
        if($action === 'restart'){
            echo 'Kill beanstalkd...'.PHP_EOL;
            exec("/usr/bin/killall beanstalkd 2>&1");
            echo 'Kill MikoCallRoutingServer...'.PHP_EOL;
            exec("/bin/kill ".implode(' ', $activeProcesses)." 2>&1");
        }else{
            echo 'Found process '. implode(',', $activeProcesses).'. My PID: '.getmypid().'. My PPID: '.posix_getppid().PHP_EOL;
            exit(1);
        }
    }

    echo "Starting $argv[0] ...".PHP_EOL;
    mwExecBg("/usr/bin/php -f $argv[0] start");
}