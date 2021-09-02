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
use Pheanstalk\Exception;

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
        try {
            $this->queueAgent = new BeanstalkClient('MikoCallRoutingRequest');
        }catch (Exception $e){

        }
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
            $this->agi->noop("Fail connect to DB");
            return;
        }
        $sql    = "SELECT keyword,data FROM ".$settingsQueue["AMPDBNAME"].".queues_details WHERE id='".$settingsQueue["QUEUE_NUMBER"]."' AND keyword IN ('retry','timeout','music');";
        $result = mysqli_query($this->db, $sql);

        if(!$result){
            $this->agi->noop("Fail mysqli_query to DB".$this->db->error);
            return;
        }
        while ($row = $result->fetch_assoc()) {
            $this->agi->noop($row['keyword']);
            $this->agi->noop($row['data']);
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
        $this->agi = new AGI();
        $this->initDb();
        $this->initTimeout();
        $this->initQueueConf();

        $timeStart = time();
        $this->context     = $this->agi->get_variable('VMX_CONTEXT', true);
        $this->dialOptions = $this->agi->get_variable('TRUNK_OPTIONS', true);

        $this->agi->exec('NoCDR', '');
        $this->agi->exec('Ringing', '');

        $this->agi->set_variable('AGIEXITONHANGUP', 'yes');
        $this->agi->set_variable('AGISIGHUP', 'yes');
        $this->agi->set_variable('__ENDCALLONANSWER', 'yes');
        $this->agi->set_variable('CHANNEL(hangup_handler_wipe)', 'miko-custom-hangup-handler,s,1');

        if(!$this->ringing) {
            $this->agi->exec('Answer', '');
            $this->dialOptions .= 'm('.$this->mohClass.')';
        }
        $status = '';
        while ($status !== 'ANSWER'){
            $delta = time() - $timeStart;
            if($this->maxwait > 0 &&  ($delta)>$this->maxwait) {
                if(empty($this->failOverDest)) {
                    $this->agi->hangup();
                }else{
                    $this->agi->exec_goto($this->failOverDest);
                }
                break;
            }
            $number = $this->getNextAgent();
            $delta         = time() - $timeStart;
            $this->timeout = min($this->timeout, ($this->maxwait - $delta));
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
        $request= array(
            'Action'   => 'GetNextAgent',
            'ActionID' => $this->PID
        );
        try {
            $data   = $this->queueAgent->request($request, 2);
            $result = json_decode($data, true);
        }catch (\Exception $e){
            $result = '';
        }
        if(isset($result['Agent']) && !empty($result['Agent'])){
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
        $data = array(
            'DIALSTATUS' => $status,
            'DST' => $dst,
            'TIME' => microtime(true)
        );
        try {
            $this->queueAgent->publish($data, 'MikoCallRoutingChangeStatus', 2);
        }catch (\Exception $e){
        }
    }

    public function getListAgents()
    {
        $request= array(
            'Action'   => 'ListAgents'
        );
        $result = '';
        if(isset($this->queueAgent)){
            try {
                $data   = $this->queueAgent->request($request, 2);
                $result = json_decode($data, true);
            }catch (\Exception $e){
            }
        }
        return $result;
    }

    /**
     * Направление вызова на Агента и информирование сервера маршрутизации о результате.
     * @param $dst
     * @return mixed
     */
    private function dial($dst){
        $this->agi->set_variable('MASTER_CHANNEL(__M_DIALEDPEERNUMBER)', $dst);
        $this->agi->set_variable('__M_DIALEDPEERNUMBER', $dst);

        $linkedid = $this->agi->get_variable('CHANNEL(linkedid)',true);
        //$filename = __DIR__.'/../../../tmp/'.$linkedid;
        $filename = '/usr/src/dialplan-miko-ajam/agi-queues/tmp/'.$linkedid;
        @file_put_contents($filename, $dst);

        $this->agi->exec_dial('Local', $dst.'@'.$this->context.'/n', $this->timeout, $this->dialOptions);
        $DIAL_STATUS = $this->agi->get_variable('DIALSTATUS', true);
        $this->agi->noop("DIALSTATUS -> $DIAL_STATUS");

        $this->changeStatus($dst, $DIAL_STATUS);
        return $DIAL_STATUS;
    }
}
