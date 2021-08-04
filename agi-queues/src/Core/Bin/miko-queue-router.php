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
use MikoPBX\Core\Workers\MikoCallRoutingServer;

require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/../../../settings.php';
// php -f /usr/src/dialplan-miko-ajam/agi-queues/src/Core/Bin/miko-queue-router.php start
if(isset($argv[1]) && ($argv[1] === 'start' || $argv[1] === 'restart')){
    $activeProcesses = MikoCallRoutingServer::getPidOfProcess();
    if(count($activeProcesses) > 0){
        if($argv[1] === 'restart'){
            exec("kill ".implode(' ', $activeProcesses)." 2>&1");
        }else{
            echo 'Found process '. implode(',', $activeProcesses) ;
            exit(1);
        }
    }
    $server = new MikoCallRoutingServer();
    $server->start();
}


