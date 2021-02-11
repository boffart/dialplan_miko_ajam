<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 2 2020
 */

namespace MIKO\Modules;

class Logger {

    public $agi;
    public $logfile; // '/var/log/asterisk/smart.log'

    public function __construct($class_name, $module_id){

    }

	public function setLogFile($path){
		$this->logfile = $path;	
	}

    /**
     *
     * @param $p1
     * @param $p2
     */
    public function write($p1, $p2) {
	    if($this->logfile === null){
		    return;
	    }

        if (is_array($p1)){
            foreach ($p1 as $val){
				file_put_contents($this->logfile, '['.date('D M d H:i:s Y',time()).'] '. $val . "\n", FILE_APPEND);
            }
        }else{
			file_put_contents($this->logfile, '['.date('D M d H:i:s Y',time()).'] '. $p1. "\n", FILE_APPEND);
        }

    }
}
