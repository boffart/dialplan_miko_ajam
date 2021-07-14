<?php
/*
 * MikoPBX - free phone system for small business
 * Copyright (C) 2017-2020 Alexey Portnov and Nikolay Beketov
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

namespace MikoPBX\Core\System;
use Pheanstalk\Pheanstalk;
use Exception;

class BeanstalkClient
{
    const INBOX_PREFIX = 'INBOX_';

    /** @var Pheanstalk */
    private $queue;
    private $connected = false;
    private $subscriptions = [];
    private $tube;
    private $reconnectsCount = 0;
    private $message;
    private $timeout_handler;
    private $error_handler;

    /**
     * BeanstalkClient constructor.
     *
     * @param string $tube
     * @param string $port
     */
    public function __construct($tube = 'default', $port = '')
    {
        $this->tube = str_replace("\\", '-', $tube);
        $this->port = $port;
        $this->reconnect();
    }

    /**
     * Recreates connection to the beanstalkd server
     */
    public function reconnect()
    {
        $this->queue = new Pheanstalk('127.0.0.1');
        $this->queue->useTube($this->tube);
        foreach ($this->subscriptions as $tube => $callback) {
            $this->subscribe($tube, $callback);
        }
        $this->connected = $this->queue->getConnection()->isServiceListening();
    }

    /**
     * Subscribe on new message in tube
     *
     * @param string           $tube     - listening tube
     * @param array | callable $callback - worker
     */
    public function subscribe($tube, $callback)
    {
        $tube = str_replace("\\", '-', $tube);
        $this->queue->watch($tube);
        $this->queue->ignore('default');
        $this->subscriptions[$tube] = $callback;
    }

    /**
     * Returns connection status
     *
     * @return bool
     */
    public function isConnected()
    {
        return $this->connected;
    }

    /**
     * Sends request and wait for answer from processor
     *
     * @param      $job_data
     * @param int  $timeout
     * @param int  $priority
     *
     * @return bool|string
     */
    public function request(
        $job_data,
        $timeout = 10,
        $priority = Pheanstalk::DEFAULT_PRIORITY
    ) {
        $this->message = false;
        $inbox_tube    = uniqid(self::INBOX_PREFIX, true);
        $this->queue->watch($inbox_tube);

        // Send message to backend worker
        $requestMessage = [
            $job_data,
            'inbox_tube' => $inbox_tube,
        ];
        $this->publish($requestMessage, null, $priority, 0, $timeout);

        // We wait until a worker process request.
        try {
            $job = $this->queue->reserve($timeout);
            if ($job) {
                $this->message = $job->getData();
                $this->queue->delete($job);
            }
        } catch (Exception $exception) {
            Util::sysLogMsg(__METHOD__, 'Exception: ' . $exception->getMessage(), LOG_ERR);
            if (isset($job)) {
                $this->queue->bury($job);
            }
        }
        $this->queue->ignore($inbox_tube);
        return $this->message;
    }

    /**
     * Puts a job in a beanstalkd server queue
     *
     * @param mixed   $job_data data to worker
     * @param ?string $tube     tube name
     * @param int     $priority Jobs with smaller priority values will be scheduled
     *                          before jobs with larger priorities. The most urgent priority is 0;
     *                          the least urgent priority is 4294967295.
     * @param int     $delay    delay before insert job into work query
     * @param int     $ttr      time to execute this job
     *
     * @return \Pheanstalk\Job
     */
    public function publish(
        $job_data,
        $tube = null,
        $priority = Pheanstalk::DEFAULT_PRIORITY,
        $delay = Pheanstalk::DEFAULT_DELAY,
        $ttr = Pheanstalk::DEFAULT_TTR
    ) {
        $tube = str_replace("\\", '-', $tube);
        // Change tube
        if ( ! empty($tube) && $this->tube !== $tube) {
            $this->queue->useTube($tube);
        }
        $job_data = serialize($job_data);
        // Send JOB to queue
        $result = $this->queue->put($job_data, $priority, $delay, $ttr);

        // Return original tube
        $this->queue->useTube($this->tube);

        return $result;
    }

    /**
     * Drops orphaned tasks
     */
    public function cleanTubes()
    {
        $tubes          = $this->queue->listTubes();
        $deletedJobInfo = [];
        foreach ($tubes as $tube) {
            try {
                $this->queue->useTube($tube);
                $queueStats = $this->queue->stats()->getArrayCopy();

                // Delete buried jobs
                $countBuried = $queueStats['current-jobs-buried'];
                while ($job = $this->queue->peekBuried()) {
                    $countBuried--;
                    if ($countBuried < 0) {
                        break;
                    }
                    $id = $job->getId();
                    Util::sysLogMsg(
                        __METHOD__,
                        "Deleted buried job with ID {$id} from {$tube} with message {$job->getData()}",
                        LOG_DEBUG
                    );
                    $this->queue->delete($job);
                    $deletedJobInfo[] = "{$id} from {$tube}";
                }

                // Delete outdated jobs
                $countReady = $queueStats['current-jobs-ready'];
                while ($job = $this->queue->peekReady()) {
                    $countReady--;
                    if ($countReady < 0) {
                        break;
                    }
                    $id                    = $job->getId();
                    $jobStats              = $this->queue->statsJob($job)->getArrayCopy();
                    $age                   = (int)$jobStats['age'];
                    $expectedTimeToExecute = (int)$jobStats['ttr'] * 2;
                    if ($age > $expectedTimeToExecute) {
                        Util::sysLogMsg(
                            __METHOD__,
                            "Deleted outdated job with ID {$id} from {$tube} with message {$job->getData()}",
                            LOG_DEBUG
                        );
                        $this->queue->delete($job);
                        $deletedJobInfo[] = "{$id} from {$tube}";
                    }
                }
            } catch (Exception $exception) {
                Util::sysLogMsg(__METHOD__, 'Exception: ' . $exception->getMessage(), LOG_ERR);
            }
        }
        if (count($deletedJobInfo) > 0) {
            Util::sysLogMsg(__METHOD__, "Delete outdated jobs" . implode(PHP_EOL, $deletedJobInfo), LOG_WARNING);
        }
    }

    /**
     * Job worker for loop cycles
     *
     * @param float $timeout
     *
     */
    public function wait($timeout = 5)
    {
        $this->message = null;
        $start         = microtime(true);
        try {
            $job = $this->queue->reserve((int)$timeout);
        } catch (Exception $exception) {
            Util::sysLogMsg(__METHOD__, 'Exception: ' . $exception->getMessage(), LOG_ERR);
        }

        if ( ! isset($job) || $job === false) {
            $workTime = (microtime(true) - $start);
            if ($workTime < $timeout) {
                usleep(100000);
                // Если время ожидания $worktime меньше значения таймаута $timeout
                // И задача не получена $job === null
                // Что то не то, вероятно потеряна связь с сервером очередей
                $this->reconnect();
            }
            if (is_array($this->timeout_handler)) {
                call_user_func($this->timeout_handler);
            }

            return;
        }

        // Processing job over callable function attached in $this->subscribe
        if (json_decode($job->getData(), true) !== null) {
            $mData = $job->getData();
        } else {
            $mData = unserialize($job->getData());
        }
        $this->message = $mData;

        $stats           = $this->queue->statsJob($job);
        $requestFormTube = $stats['tube'];
        $func            = $this->subscriptions[$requestFormTube];
        if ($func === null) {
            // Action not found
            $this->queue->bury($job);
        } else {
            try {
                if (is_array($func)) {
                    call_user_func($func, $this);
                } elseif (is_callable($func) === true) {
                    $func($this);
                }
                // Removes the job from the queue when it has been successfully completed
                $this->queue->delete($job);
            } catch (Throwable $e) {
                // Marks the job as terminally failed and no workers will restart it.
                $this->queue->bury($job);
                Util::sysLogMsg(__METHOD__ . '_EXCEPTION', $e->getMessage(), LOG_ERR);
            }
        }
    }

    /**
     * Gets request body
     *
     * @return string
     */
    public function getBody()
    {
        if (is_array($this->message)
            && isset($this->message['inbox_tube'])
            && count($this->message) === 2) {
            // Это поступил request, треует ответа. Данные были переданы первым параметром массива.
            $message_data = $this->message[0];
        } else {
            $message_data = $this->message;
        }

        return $message_data;
    }

    /**
     * Sends response to queue
     *
     * @param $response
     */
    public function reply($response)
    {
        if (isset($this->message['inbox_tube'])) {
            $this->queue->useTube($this->message['inbox_tube']);
            $this->queue->put($response);
            $this->queue->useTube($this->tube);
        }
    }

    /**
     *
     * @param $handler
     */
    public function setErrorHandler($handler)
    {
        $this->error_handler = $handler;
    }

    /**
     * @param $handler
     */
    public function setTimeoutHandler($handler)
    {
        $this->timeout_handler = $handler;
    }

    /**
     * @return int
     */
    public function reconnectsCount()
    {
        return $this->reconnectsCount;
    }

    /**
     * Gets all messages from tube and clean it
     *
     * @param string $tube
     *
     * @return array
     */
    public function getMessagesFromTube($tube = '')
    {
        if ($tube !== '') {
            $this->queue->useTube($tube);
        }
        $arrayOfMessages = [];
        while ($job = $this->queue->peekReady()) {
            if (json_decode($job->getData(), true) !== null) {
                $mData = $job->getData();
            } else {
                $mData = unserialize($job->getData(), [false]);
            }
            $arrayOfMessages[] = $mData;
            $this->queue->delete($job);
        }

        return $arrayOfMessages;
    }
}