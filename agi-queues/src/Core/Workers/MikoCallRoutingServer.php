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

namespace MikoPBX\Core\Workers;
use MikoPBX\Core\System\BeanstalkClient;
use mysqli;

class MikoCallRoutingServer
{
    const   PROCESS_NAME = 'miko-queue-router';
    private $dumpFileName;
    private $dumpHintFileName;
    private $logFileName;
    private $db;
    private $agents = array();
    private $refrashTime = 60;
    private $lastUpdate = 0;
    private $debug = true;
    private $queueAgent;
    private $needRestart = false;
    private $retryTimeout = 3;

    public function __construct()
    {
        global $settingsQueue;
        $this->dumpFileName = __DIR__ . '/../../../agents.dump';
        $this->dumpHintFileName = __DIR__ . '/../../../hints.dump';
        $this->logFileName = __DIR__ . '/../../../full.log';
        if (isset($settingsQueue["DEBUG"])) {
            $this->debug = ($settingsQueue["DEBUG"] === '1');
        }
        $this->reStoreAgents();
        $this->initDb();
        $this->initAgents();
        $this->initTimeout();
        $this->initQueueAgent();
    }

    /**
     * Получения длительности звонка агенту очереди.
     */
    private function initTimeout()
    {
        global $settingsQueue;
        if (!$this->db) {
            return;
        }
        $sql = "SELECT keyword,data FROM " . $settingsQueue["AMPDBNAME"] . ".queues_details WHERE id='" . $settingsQueue["QUEUE_NUMBER"] . "' AND keyword IN ('retry','timeout','music');";
        $result = mysqli_query($this->db, $sql);
        if (!$result) {
            return;
        }
        $localRetryTimeout = null;
        while ($row = $result->fetch_assoc()) {
            if ('retry' === $row['keyword']) {
                $localRetryTimeout = $row['data'];
            }
        }
        if (is_numeric($localRetryTimeout) && $localRetryTimeout >= 0) {
            $this->retryTimeout = $localRetryTimeout;
        }
    }

    public static function getPidOfProcess($name='')
    {
        $path_ps = 'ps';
        $path_grep = 'grep';
        $path_awk = 'awk';
        if(empty($name)){
            $name = addslashes(self::PROCESS_NAME);
        }
        $filter_cmd = "| {$path_grep} -v ".getmypid()." | {$path_grep} -v ".posix_getppid() ." | {$path_grep} -v check";
        $out = array();
        $command = "{$path_ps} -A -o 'pid,args' {$filter_cmd} | {$path_grep} '{$name}' | {$path_grep} -v grep | {$path_grep} -v /bin/sh | {$path_awk} ' {print $1} '";
        exec("$command 2>&1", $out);
        return $out;
    }

    /**
     * Сохраниение таблицы агентов.
     */
    private function dumpAgents($needLog = true)
    {
        if($needLog === true){
            $agentsTable = PHP_EOL;
            foreach ($this->agents as $agent){
                $agentsTable .= "{$agent['number']} idle:{$agent['idle']} user-idle:{$agent['user-idle']} unavailable:{$agent['unavailable']} penalty:{$agent['penalty']} end-last-call:{$agent['end-last-call']}".PHP_EOL;
            }
            $this->verbose($agentsTable);
        }
        $newData = json_encode($this->agents);
        $oldData = file_get_contents($this->dumpFileName);
        if($newData !== $oldData){
            file_put_contents($this->dumpFileName, $newData);
        }
    }

    /**
     * Восстановление таблицы агентов.
     */
    private function reStoreAgents()
    {
        if (!file_exists($this->dumpFileName)) {
            return;
        }
        $data = json_decode(file_get_contents($this->dumpFileName), true);
        if (is_array($data)) {
            $this->agents = $data;
        }
    }

    /**
     * Инициализация коннектора beanstalk.
     */
    private function initQueueAgent()
    {
        $this->queueAgent = new BeanstalkClient('MikoCallRoutingRequest');
        $this->queueAgent->subscribe('MikoCallRoutingRequest', array($this, 'onMikoCallRoutingRequest'));
        $this->queueAgent->subscribe('MikoCallRoutingChangeStatus', array($this, 'onMikoCallRoutingChangeStatus'));
        $this->queueAgent->setErrorHandler(array($this, 'errorHandler'));
    }

    /**
     * Вывод отладочных сообщений.
     * @param $data
     */
    private function verbose($data)
    {
        if (!$this->debug) {
            return;
        }
        if (is_string($data)) {
            $data .= PHP_EOL;
        }
        $message = print_r($data, true);
        file_put_contents($this->logFileName, date('Y-m-d H:i:s').' '.$message, FILE_APPEND);
    }

    /**
     * Получение списка агентов очереди.
     */
    private function initAgents()
    {
        global $settingsQueue;
        if (!$this->db) {
            return;
        }
        $sql = "SELECT data FROM " . $settingsQueue['AMPDBNAME'] . ".queues_details WHERE id='" . $settingsQueue['QUEUE_NUMBER'] . "' AND keyword='member';";
        $result = mysqli_query($this->db, $sql);

        // Сохраняем старый список агентов с отметками времени "penalty" и "end-last-call"
        $oldData = $this->agents;
        // Формируем актуальный список агентов.
        $this->agents = array();
        while ($row = $result->fetch_assoc()) {
            $agent = $row['data'];
            $posStart = strpos($agent, '/') + 1;
            $posEnd = strpos($agent, '@');
            if ($posEnd === false) {
                $posEnd = strpos($agent, ',');
            }
            $offset = $posEnd - $posStart;

            $number = substr($agent, $posStart, $offset);
            if (isset($this->agents[$number])) {
                continue;
            }
            $this->agents[$number] = array(
                'idle' => false,        // hint
                'user-idle' => true,    // AstDB
                'end-last-call' => 0,
                'number' => $number,
                'penalty' => 0,
            );
        }
        // Заполняем отметки времени в новом списке агентов.
        foreach ($oldData as $number => $oldRow){
            if(!isset($this->agents[$number])){
                continue;
            }
            $this->agents[$number]['end-last-call'] = $oldRow['end-last-call'];
            $this->agents[$number]['penalty']       = $oldRow['penalty'];
        }
        unset($oldData);
        $this->updateStatuses();
        $this->lastUpdate = time();
        $this->dumpAgents();
    }

    function updateStatusesAstDb()
    {
        global $settingsQueue;
        $filename = $settingsQueue["ASTDBPATH"];
        if(!file_exists($filename)){
            $this->updateStatusesAstDbCLI();
            return;
        }
        $dbConnector = new \SQLite3($filename);
        $dbConnector->busyTimeout(500);
        $dbConnector->enableExceptions(true);
        try {
            $results = $dbConnector->query('SELECT * FROM astdb WHERE key LIKE "/UserBuddyStatus/%"');
            while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
                $number = substr($row['key'], strrpos($row['key'], '/') + 1);
                if(isset($this->agents[$number])){
                    $this->agents[$number]['user-idle'] = ('0' === $row['value']);
                }
            }
        } catch (\Exception $e) {
            $this->verbose('Caught exception: ' . $e->getMessage());
            $this->updateStatusesAstDbCLI();
        }
        $dbConnector->close();
    }

    function updateStatusesAstDbCLI()
    {
        $out = array();
        exec("/usr/sbin/asterisk -rx 'database show UserBuddyStatus' | /bin/awk -F ':' '{ print $1 \"@.@\" $2 }'", $out);
        foreach ($out as $data){
            $row_data = explode('@.@', $data);
            if(count($row_data) <2){
                // Битая строка.
                continue;
            }
            $key    = trim($row_data[0]);
            $number = substr($key, strrpos($key, '/') + 1);
            if(!is_numeric($number)){
                continue;
            }
            if(isset($this->agents[$number])){
                $this->agents[$number]['user-idle'] = ('0' === trim($row_data[1]));
            }
        }
    }

    /**
     * Обновление информации по статусу пира (по данным hints).
     */
    function updateStatuses()
    {
        $out    = array();
        $hints  = array();
        exec("/usr/sbin/asterisk -rx'core show hints' | /bin/grep -v '^_' | /bin/grep 'ext-local' | /bin/awk -F'([ ]*[:]?[ ]+)|@' ' {print $1 \"@.@\" $3 \"@.@\" $4 } '", $out);
        foreach ($out as $hint_row){
            if(strpos($hint_row, '*') === 0){
                // Старкоды отсекаем.
                continue;
            }
            $row_data = explode('@.@', $hint_row);
            if(count($row_data) <3){
                // Битая строка.
                continue;
            }
            $this->normalizeHint($row_data[1]);
            if(strrpos($row_data[1], '/') === FALSE) {
                // Не корректное имя канала, это вероятно виртуальное устройство.
                continue;
            }
            if(!isset( $this->agents[$row_data[0]] )){
                // Этот хинт не относится к очереди.
                continue;
            }
            $hints[] = $row_data;
            $this->agents[$row_data[0]]['idle']        = ('State:Idle' === $row_data[2]);
            $this->agents[$row_data[0]]['unavailable'] = ('State:Unavailable' === $row_data[2]);
        }
        file_put_contents($this->dumpHintFileName, json_encode($hints));
        $this->updateStatusesAstDb();
    }
    function normalizeHint(&$str){
        $hint_val = '';
        $arr_val = explode('&', $str);
        foreach ($arr_val as $val){
            if( strrpos($val, 'SIP/') === FALSE &&
                strrpos($val, 'PJSIP/') === FALSE &&
                strrpos($val, 'IAX2/') === FALSE &&
                strrpos($val, 'DAHDI/') === FALSE){
                continue;
            }
            if($hint_val !== ''){
                $hint_val.='&';
            }
            $hint_val.=$val;
        }
        $str = $hint_val;
    }

    /**
     * Подключение к базе данных;
     */
    private function initDb()
    {
        global $settingsQueue;
        $dbHandle = new mysqli($settingsQueue["AMPDBHOST"], $settingsQueue["AMPDBUSER"], $settingsQueue["AMPDBPASS"], $settingsQueue["AMPDBNAME"]);
        if ($dbHandle->connect_errno) {
            $dbHandle = null;
            $this->verbose("Error connect to DB");
        }
        $this->db = $dbHandle;
    }

    /**
     * Запуск сервера.
     */
    public function start()
    {
        $this->verbose("Start service...");
        while ($this->needRestart === false) {
            $this->queueAgent->wait(3);
            $this->updateStatuses();
            $this->dumpAgents(false);
        }
    }

    public function errorHandler()
    {
        // TODO Пока ничего не делаем.
    }

    public function onMikoCallRoutingRequest($tube)
    {
        $data  = $tube->getBody();
        if(!isset($data['Action'])){
            return;
        }
        if((time() - $this->lastUpdate) > $this->refrashTime) {
            $this->initAgents();
        }
        if($data['Action'] === 'GetNextAgent'){
            $this->verbose("Start getNextAgent ... ");
            $agent = $this->getNextAgent();
            $this->verbose("Result getNextAgent -> $agent");
            $result = array('Agent' => $agent, 'ActionID' => $data['ActionID']);
        }elseif($data['Action'] === 'ListAgents'){
            $result = $this->agents;
        }
        $tube->reply(json_encode($result));
    }

    public function onMikoCallRoutingChangeStatus($tube)
    {
        $data      = $tube->getBody();
        if(!isset($this->agents[$data['DST']])){
            return;
        }
        $this->verbose("Update agent state -> {$data['DST']} to {$data['TIME']}");
        if($data['DIALSTATUS'] === 'ANSWER'){
            $this->agents[$data['DST']]['end-last-call'] = $data['TIME'];
        }
        $this->agents[$data['DST']]['penalty'] = $data['TIME'];
        $tube->reply(true);
        $this->dumpAgents();
    }

    /**
     * Определение следующего по очереди агента.
     * @return mixed|string
     */
    private function getNextAgent(){
        $this->updateStatuses();
        $resultAgent = array();
        foreach ($this->agents as $agent){
            if($agent['idle'] !== true || $agent['user-idle'] !== true){
                continue;
            }
            if(empty($resultAgent)){
                $resultAgent = $agent;
                continue;
            }
            if($resultAgent['penalty'] > $agent['penalty']){
                $resultAgent = $agent;
            }
        }

        if($resultAgent !== array() && $this->retryTimeout > (time() - $resultAgent['end-last-call']) ){
            // Повторный вызов должен пройти только спустя время таймаута.
            $this->verbose('Waiting timout...');
            $resultAgent = array();
        }

        $numberAgent = '';
        if(!empty($resultAgent)){
            $numberAgent = $resultAgent['number'];
            $this->agents[$numberAgent]['end-last-call'] = microtime(true);
            $this->agents[$numberAgent]['penalty']       = $this->agents[$numberAgent]['end-last-call'];
        }
        $this->dumpAgents();
        return $numberAgent;
    }
}