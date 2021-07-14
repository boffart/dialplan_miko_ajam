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

class MikoCallRoutingServer{
    const   PROCESS_NAME = 'miko-queue-router-server';
    private $dumpFileName;
    private $db;
    private $agents = [];
    private $refrashTime    = 60;
    private $lastUpdate     = 0;
    private $debug          = true;
    private $queueAgent;
    private $needRestart    = false;

    public function __construct()
    {
        global $settingsQueue;
        $this->dumpFileName = __DIR__.'/../../../agents.dump';
        if(isset($settingsQueue["DEBUG"])){
            $this->debug = ($settingsQueue["DEBUG"] === '1');
        }
        $this->reStoreAgents();
        $this->initDb();
        $this->initAgents();
        $this->initQueueAgent();
    }

    public static function getPidOfProcess()
    {
        $path_ps   = 'ps';
        $path_grep = 'grep';
        $path_awk  = 'awk';

        $name       = addslashes(self::PROCESS_NAME);
        $filter_cmd = '';
        $out = [];
        $command = "{$path_ps} -A -o 'pid,args' {$filter_cmd} | {$path_grep} '{$name}' | {$path_grep} -v grep | {$path_awk} ' {print $1} '";
        exec("$command 2>&1", $out);
        return $out;
    }

    /**
     * Сохраниение таблицы агентов.
     */
    private function dumpAgents()
    {
        file_put_contents($this->dumpFileName, json_encode($this->agents, JSON_PRETTY_PRINT));
    }

    /**
     * Восстановление таблицы агентов.
     */
    private function reStoreAgents(){
        if(!file_exists($this->dumpFileName)){
            return;
        }
        $data = json_decode(file_get_contents($this->dumpFileName),true);
        if(is_array($data)){
            $this->agents = $data;
        }
    }

    /**
     * Инициализация коннектора beanstalk.
     */
    private function initQueueAgent()
    {
        $this->queueAgent = new BeanstalkClient('MikoCallRoutingRequest');
        $this->queueAgent->subscribe('MikoCallRoutingRequest',      [$this, 'onMikoCallRoutingRequest']);
        $this->queueAgent->subscribe('MikoCallRoutingChangeStatus', [$this, 'onMikoCallRoutingChangeStatus']);
        $this->queueAgent->setErrorHandler([$this, 'errorHandler']);
    }

    /**
     * Вывод отладочных сообщений.
     * @param $data
     */
    private function verbose($data){
        if(!$this->debug){
            return;
        }
        if(is_string($data)){
            $data.=PHP_EOL;
        }
        $message = print_r($data, true);
        echo $message;
    }

    /**
     * Получение списка агентов очереди.
     */
    private function initAgents()
    {
        global $settingsQueue;
        if(!$this->db){
            return;
        }
        $sql    = "SELECT data FROM ".$settingsQueue['AMPDBNAME'].".queues_details WHERE id='".$settingsQueue['QUEUE_NUMBER']."' AND keyword='member';";
        $result = mysqli_query($this->db, $sql);
        $now    = microtime(true);
        while ($row = $result->fetch_assoc()) {
            $agent      = $row['data'];
            $posStart   = strpos($agent, '/')+1;
            $posEnd     = strpos($agent, '@');
            if($posEnd === false){
                $posEnd = strpos($agent, ',');
            }
            $offset = $posEnd - $posStart;

            $number         = substr($agent, $posStart, $offset);
            if(isset($this->agents[$number])){
                continue;
            }
            $this->agents[$number] = [
                'idle' => false,
                'end-last-call' => $now,
                'number' => $number,
                'penalty' => $now
            ];
        }
        $this->updateStatuses();
        $this->lastUpdate = time();

        $this->dumpAgents();
    }

    /**
     * Обновление информации по статусу пира (по данным hints).
     */
    function updateStatuses()
    {
        $out = [];
        $context = 'ext-local';
        exec("asterisk -rx\"core show hints\" | grep -v '^_' | grep '{$context}' | awk -F'([ ]*[:]?[ ]+)|@' ' {print $1 \"@.@\" $3 \"@.@\" $4 } '", $out);
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
            $state = false;
            if('State:Idle' === $row_data[2]){
                $state = true;
            }
            $this->agents[$row_data[0]]['idle'] = $state;
        }

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

        cli_set_process_title(self::PROCESS_NAME);
        $this->verbose("Start service...");
        while ($this->needRestart === false) {
            $this->queueAgent->wait();
        }
    }

    public function errorHandler()
    {

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
            $agent = $this->getNextAgent();
            $this->verbose("Start getNextAgent -> $agent");
            $result = ['Agent' => $agent, 'ActionID' => $data['ActionID']];
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
        $this->verbose($this->agents);
        $tube->reply(true);
        $this->dumpAgents();
    }

    /**
     * Определение следующего по очереди агента.
     * @return mixed|string
     */
    private function getNextAgent(){
        $this->updateStatuses();
        $resultAgent = [];
        foreach ($this->agents as $agent){
            if($agent['idle'] !== true){
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
        if(!empty($resultAgent)){
            $numberAgent = $resultAgent['number'];
            $this->agents[$numberAgent]['end-last-call'] = microtime(true);
            $this->agents[$numberAgent]['penalty']       = $this->agents[$numberAgent]['end-last-call'];
            return $numberAgent;
        }
        $this->verbose('After change... ');
        $this->dumpAgents();
        return '';
    }
}