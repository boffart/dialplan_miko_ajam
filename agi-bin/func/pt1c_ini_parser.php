<?php
class pt1c_ini_parser {
    protected $sections;
    protected $src_file;
    protected $dst_file;
    
    public function sections_exist($name){
	 	if(!is_array($this->sections)) return false;   
	    return array_key_exists($name, $this->sections);
    }
    
    public function find_section_by_key_val($key, $val){
	    if(!is_array($this->sections)) return '';
		// обход секций
		foreach($this->sections as $index => $section) {
	        // есть ли ключ в секции?
	        if(!array_key_exists($key, $section)) continue;
			
			// соответствует ли значение ключа искомому?
			if(is_array($section[$key]) && $section[$key]['value']==$val){
				return $index;	
			}
		}
		
		return '';	
    }
	
	// чтение файла конфигурации
	// построчный обход
    public function read($file) {
        if(!is_file($file)) return;
        $this->src_file = $file;
        $this->sections = array();
        
        $section = '';
        $key     = '';
        foreach(file($file) as $line) {
            // comment or whitespace
            if(preg_match('/^\s*(;.*)?$/', $line)) {
                if($section==''){
					// комментарий перед секцией
					if(array_key_exists('comment',$this->sections)){
						$this->sections['comment']=$this->sections['comment'].$line;
					}else{
						$this->sections['comment']=$line;
					}
                }elseif($key==''){
					// комментарий после секции
					if(array_key_exists($section, $this->sections) && array_key_exists('comment',$this->sections[$section])){
						$this->sections[$section]['comment']=$this->sections[$section]['comment'].$line;
					}else{
						$this->sections[$section]['comment']=$line;
					}
                }
            // section
            } elseif(preg_match('/\[(.*)\]/', $line, $match)) {
                $section = $match[1];
                
                if(!(is_array($this->sections) && array_key_exists($section, $this->sections)) ){
	                // это новая секция
	                $this->sections[$section] = array();
	                $key='';
                }
            // entry
            } elseif(preg_match('/^\s*(.*?)\s*((=>)|=)\s*(.*?)\s*(;.*)?$/', $line, $match)) {
                $key  =$match[1];
                $value=$match[4];
                
                $this->sections[$section][$key]= array('data' => $line, 'value' => $value);
            }
        }

    }

	// получение значения параметра для секции
    public function get($section, $_key) {
        if(!is_array($this->sections)){
	        // массив секций не определен
	        // echo('массив секций не определен');
	        return '';
        }
        
        if(!array_key_exists($section, $this->sections)){
	        // указанной секции не существует
	        // echo('указанной секции не существует');
	        return '';
        } 
        
        $arr_loc_keys = $this->sections[$section];
		if( !(is_array($arr_loc_keys)  && array_key_exists($_key, $this->sections[$section]) )) {
			// нет ключей в искомой секции
			// либо отсутствует ключ в секции
	        return '';
		}
	    return $arr_loc_keys[$_key]['value'];
    }
	
	// установка значения параметра для секции
    public function set($section, $_key, $value, $comment='',$separator='=', $postfix='') {
        if(!$comment=='') $comment='; '.$comment;
        
        $this->sections[$section][$_key]['value'] = $value;
        $this->sections[$section][$_key]['data']  = $_key.$separator.$value."$comment\n";
        
        if($postfix!=''){
			$this->sections[$section]['postfix']  = $postfix;
			
        }
    }

    public function write($file) {
        $this->dst_file = $file;

        $fp = fopen($file, 'w');
		// обход секций
		foreach($this->sections as $index => $section) {
            if(!is_array($section)) {
		        // комментарий
		        fwrite($fp, "$section");
	          	continue;  
            }else{
		        
		        if(array_key_exists('postfix', $section) )
			        fwrite($fp, "[$index](".$section['postfix'].")\n");
		        else
			        fwrite($fp, "[$index]\n");
			        
		        // комментарии после определения секции
		        if(array_key_exists('comment', $section)){
			        fwrite($fp, rtrim($section['comment']));
				}
		    }
		        
            // обход ключей секции
	        foreach($section as $vr_index  => $_key) {
	            if($vr_index=='postfix') continue;
	            
	            fwrite($fp, trim($_key['data'])."\n");
	        }
        }
        fclose($fp);
    }
    
}
?>