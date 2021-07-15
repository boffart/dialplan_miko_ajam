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

use MikoPBX\Core\Other\AGI;
use MikoPBX\Core\System\BeanstalkClient;
use mysqli;

class MikoCallRouting{

    private $timeout      = 10;
    private $mohClass     = 'default';

    private $ringing = true;
    private $maxwait = 60;
    private $db;
    private $failOverDest = '';
    private $agi;
    private $context;
    private $dialOptions;

    private $queueAgent;
    private $PID;


    /**
     * MikoCallRouting constructor.
     */
    public function __construct()
    {
        $this->PID = getmypid();
        $this->initQueueAgent();
    }

    /**
     * Инициализация коннектора beanstalk.
     */
    private function initQueueAgent()
    {
        $this->queueAgent = new BeanstalkClient('MikoCallRoutingRequest');
    }

    /**
     * Получение настроек очереди.
     */
    private function initQueueConf()
    {
        global $settingsQueue;
        if(!$this->db){
            return;
        }
        $sql    = "select dest,maxwait,ringing from ".$settingsQueue["AMPDBNAME"].".queues_config WHERE extension='".$settingsQueue["QUEUE_NUMBER"]."';";
        $result = mysqli_query($this->db, $sql);
        if(!$result){
            return;
        }
        while ($row = $result->fetch_assoc()) {
            $this->failOverDest = $row['dest'];
            $this->maxwait      = $row['maxwait'];
            $this->ringing      = ($row['ringing'] === "2");
        }
    }

    /**
     * Получения длительности звонка агенту очереди.
     */
    private function initTimeout()
    {
        global $settingsQueue;
        if(!$this->db){
            return;
        }
        $sql    = "SELECT keyword,data FROM ".$settingsQueue["AMPDBNAME"].".queues_details WHERE id='".$settingsQueue["QUEUE_NUMBER"]."' keyword IN ('retry','timeout','music');";
        $result = mysqli_query($this->db, $sql);
        if(!$result){
            return;
        }
        while ($row = $result->fetch_assoc()) {
            if('timeout' === $row['keyword']){
                $this->timeout = $row['data'];
            }elseif ('music' === $row['keyword']){
                $this->mohClass = $row['data'];
            }
        }
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
        }
        $this->db = $dbHandle;
    }

    /**
     * Начало работы AGI скрипта.
     */
    public function start(){
        $this->initDb();
        $this->initTimeout();
        $this->initQueueConf();

        $timeStart = time();
        $this->agi = new AGI();
        $this->context     = $this->agi->get_variable('VMX_CONTEXT', true);
        $this->dialOptions = $this->agi->get_variable('TRUNK_OPTIONS', true);

        $this->agi->exec('NoCDR', '');
        $this->agi->exec('Ringing', '');
        if(!$this->ringing) {
            $this->agi->exec('Answer', '');
            $this->dialOptions .= 'm('.$this->mohClass.')';
        }
        $status = '';
        while ($status !== 'ANSWER'){
            if($this->maxwait > 0 &&  (time() - $timeStart)>$this->maxwait) {
                if(empty($this->failOverDest)) {
                    $this->agi->hangup();
                }else{
                    $this->agi->exec_goto($this->failOverDest);
                }
                break;
            }
            $number = $this->getNextAgent();
            if(!empty($number)){
                $status = $this->dial($number);
            }
            usleep(500000);
        }
    }

    /**
     * Получение свободного агента от сервера маршрутизации.
     * @return mixed|string
     */
    public function getNextAgent()
    {
        $request= [
            'Action'   => 'GetNextAgent',
            'ActionID' => $this->PID
        ];
        $data   = $this->queueAgent->request($request, 2);
        $result = json_decode($data, true);
        if(isset($result['Agent']) > 0 && !empty($result['Agent'])){
            return $result['Agent'];
        }
        return '';
    }

    /**
     * Изменение статуса пира.
     * @param $dst
     * @param $status
     */
    public function changeStatus($dst, $status){
        $data = [
            'DIALSTATUS' => $status,
            'DST' => $dst,
            'TIME' => microtime(true)
        ];
        $this->queueAgent->publish($data, 'MikoCallRoutingChangeStatus', 2);
    }

    public function getListAgents()
    {
        $request= [
            'Action'   => 'ListAgents'
        ];
        $data   = $this->queueAgent->request($request, 2);
        return json_decode($data, true);
    }

    /**
     * Направление вызова на Агента и информирование сервера маршрутизации о результате.
     * @param $dst
     * @return mixed
     */
    private function dial($dst){
        $this->agi->set_variable('M_DIALEDPEERNUMBER', $dst);
        $this->agi->exec_dial('Local', $dst.'@'.$this->context.'/n', $this->timeout, $this->dialOptions);
        $DIAL_STATUS = $this->agi->get_variable('DIALSTATUS', true);
        $this->agi->noop("DIALSTATUS -> $DIAL_STATUS");
        $this->changeStatus($dst, $DIAL_STATUS);
        return $DIAL_STATUS;
    }
}
