<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 2 2020
 */

namespace MIKO\Modules;

class Logger {

    public string $logfile = ''; // '/var/log/asterisk/smart.log'

    public function __construct(array $settings){
        // $class_name = 'TTS';
        // $module_id  = 'ModuleSmartIVR';
        $logFile = $settings['log-file']??'';
        if(!empty($logFile) ){
            $this->setLogFile($logFile);
        }
    }

	public function setLogFile($path):void{
		$this->logfile = $path;	
	}

    /**
     *
     * @param $p1
     * @param $p2
     */
    public function write($p1, $p2):void {
	    if(empty($this->logfile)){
		    return;
	    }
        if (is_array($p2)){
            foreach ($p2 as $val){
				file_put_contents($this->logfile, '['.date('D M d H:i:s Y',time()).'] '. $val . "\n", FILE_APPEND);
            }
        }else{
			file_put_contents($this->logfile, '['.date('D M d H:i:s Y',time()).'] '. $p1. ' '.$p2."\n", FILE_APPEND);
        }
    }

    public function writeInfo($text):void
    {
        $this->write('TTS: INFO', $text);
    }

    public function writeError($text):void
    {
        $this->write('TTS: ERROR', $text);
    }
}
