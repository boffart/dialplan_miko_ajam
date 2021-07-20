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

// Параметры доступа к MySQL (см. /etc/freepbx.conf).
$settingsQueue["AMPDBUSER"]     = "freepbxuser";
$settingsQueue["AMPDBPASS"]     = "Kv7sEPi8Q1fJ";
$settingsQueue["AMPDBHOST"]     = "127.0.0.1";
$settingsQueue["AMPDBNAME"]     = "asterisk";

// Параметры доступа к AMI (см. /etc/asterisk/manager_additional.conf)
$settingsQueue["AMIUSER"]       = "cxpanel";
$settingsQueue["AMIPASSORD"]    = "cxmanager*con";
$settingsQueue["AMIHOST"]       = "127.0.0.1";
$settingsQueue["ASTDBPATH"]     = "/var/lib/asterisk/astdb.sqlite3";

// Номер очереди.
$settingsQueue["QUEUE_NUMBER"]  = "90000999";

// Режим отладки. 
$settingsQueue["DEBUG"]  = "0";
