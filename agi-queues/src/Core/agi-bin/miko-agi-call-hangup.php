#!/usr/bin/php -q
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
use MikoPBX\Core\Other\AGI;
use MikoPBX\Core\Workers\MikoCallRouting;
require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/../../../settings.php';

$agi = new AGI();
//  php -f /usr/src/dialplan-miko-ajam/agi-queues/src/Core/agi-bin/miko-agi-call-routing.php
$dst    = $agi->get_variable('M_DIALEDPEERNUMBER', true);
$status = $agi->get_variable('DIALSTATUS', true);
if(!empty($dst) && !empty($status)){
    $callRouting = new MikoCallRouting();
    $callRouting->changeStatus($dst, $status);
}