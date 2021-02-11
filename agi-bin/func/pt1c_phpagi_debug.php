<?php
	
/**
*  Заглушка для отладки скрипта. 
*/
class AGI {
	public function exec($v1, $v2){
		echo "exec($v1, $v2)\n";	
	}
	public function answer(){
		echo "answer()\n";	
	}
	public function get_variable($_varName){
		
		$value = array();
		$value['result']=1;
		
		if('1C_Download.php' == PT1C_SKRIPTNAME){
			switch ($_varName) {
			    case 'v1':
					$value['data']='SIP/104';
			        break;
			    case 'v2':
					$value['data']='1608876687.510';
			        break;
			    case 'v3':
					$value['data']='';
			        break;
			    case 'v6':
					$value['data']='Records';
			        break;
			    case 'ASTSPOOLDIR':
					$value['data']='/var/spool/asterisk';
			        break;
			    default:
					$value['data']='';
			}	
		}else if('1C_CDR.php' == PT1C_SKRIPTNAME || '1C_CDR-debug.php' == PT1C_SKRIPTNAME){
			switch ($_varName) {
			    case 'v1':
					$value['data']='SIP/104';
			        break;
			    case 'v2':
					$value['data']='2020-12-25';
			        break;
			    case 'v3':
					$value['data']='2020-12-26';
			        break;
			    case 'v4':
					$value['data']='104';
			        break;
			    default:
					$value['data']='';
			}	
		}else if('1C_HistoryFax.php' == PT1C_SKRIPTNAME){
			switch ($_varName) {
			    case 'v1':
					$value['data']='SIP/1000';
			        break;
			    case 'v2':
					$value['data']='2017-01-01';
			        break;
			    case 'v3':
					$value['data']='2019-01-01';
			        break;
			    default:
					$value['data']='';
			}	
		}else if('1C_Playback.php' == PT1C_SKRIPTNAME){
			switch ($_varName) {
			    case 'chan':
					$value['data']='SIP/104';
			        break;
			    case 'uniqueid1c':
					$value['data']='1608876687.510';
			        break;
			    case 'ASTSPOOLDIR':
					$value['data']='/var/spool/asterisk';
			        break;
			    default:
					$value['data']='';
			}	
		}
		return $value;		
	}
}
	
?>