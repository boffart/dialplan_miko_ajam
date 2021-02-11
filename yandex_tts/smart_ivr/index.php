<?php
/*
 * Получение списка файлов:
 curl 'http://127.0.0.1/admin/1c/smart_ivr/index.php?action=list&' -L

 curl -F "file=@e8e06403b386ac797637a7a8ad153e1f.wav" \
        'http://127.0.0.1:80/admin/1c/smart_ivr/index.php?action=upload&description=test&'

 curl -F -v "file=@123̆.mp3" 'http://172.16.156.246:80/admin/1c/smart_ivr/index.php?action=upload&description=test&'

 curl -X POST -d '{"description": "Описание файла", "filename":"custom_403bbb9d0d628d0528ba12a7206aef87.wav"}' \
        'http://127.0.0.1/admin/1c/smart_ivr/index.php?action=set_description&' -L

 curl -X POST -d '{"filename":"custom_403bbb9d0d628d0528ba12a7206aef87.wav"}' \
        'http://127.0.0.1/admin/1c/smart_ivr/index.php?action=remove&' -L

 curl 'http://127.0.0.1/admin/1c/smart_ivr/index.php?action=download&filename=1custom_403bbb9d0d628d0528ba12a7206aef87.wav&'

 * */

/**
 * Удаляет расширение файла.
 * @param        $filename
 * @param string $delimiter
 * @return string
 */
function trim_extension_file($filename, $delimiter='.'){
    // Отсечем расширение файла.
    $tmp_arr = explode("$delimiter", $filename);
    if(count($tmp_arr)>1){
        unset($tmp_arr[count($tmp_arr)-1]);
        $filename = implode("$delimiter", $tmp_arr);
    }
    return $filename;
}

/**
 * Конвертация файла в wav 8000.
 * @param $filename
 * @return mixed
 */
function convert_audio_file($filename){
    $result = array();
    if(!file_exists($filename)){
        $result['result']  = 'Error';
        $result['message'] = "File '{$filename}' not found.";
        return $result;
    }
    $out = array();
    $tmp_filename = $filename;
    // Принудительно устанавливаем расширение файла в wav.
    $n_filename    = trim_extension_file($filename).".wav";
    // Конвертируем файл.
    $tmp_filename  = escapeshellcmd($tmp_filename);
    $n_filename    = escapeshellcmd(dirname($filename).'/custom_'.basename($n_filename));

    exec("/usr/sbin/asterisk -rx 'file convert {$tmp_filename} '{$n_filename}''",$out, $ret);
    $result_str    = implode('', $out);
    if($ret !== 0){
        // Ошибка выполнения конвертации.
        $result['result']  = 'Error';
        $result['message'] = $result_str;
        return $result;
    }

    if($filename != $n_filename){
        @unlink($filename);
    }

    $result = array(
        'result'    => 'Success',
        'filename'  => "$n_filename"
    );
    return $result;
}

if(!isset($_GET['action'])){
    echo 'Action not set.';
    exit(0);
}
$action = $_GET['action'];
$settings = json_decode(file_get_contents(__DIR__.'/../setting.json'), true);
$tts_dir  = rtrim($settings['tts_dir'], '/');
if($action === 'list'){
    $command = 'files=`ls '.$tts_dir.'/*.txt`; cat $files';
    exec($command, $out);
    $result = array();
    foreach ($out as $row){
        $data = json_decode($row, true);
        if(!$data){
            continue;
        }
        $desc_filename = $tts_dir . '/' . $data['filename'];
        if(!file_exists($desc_filename)){
            continue;
        }
        $result[] = $data;
    }
    echo(json_encode($result));
}elseif ($action === 'upload'){
    if(count($_FILES) === 1 && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $filename = basename($_FILES['file']['name']);
        $filetype = strtolower(substr(strrchr($filename, "."), 1));

        $resfile  = md5($_FILES['file']['name']);
        $full_filename = "{$tts_dir}/{$resfile}.{$filetype}";

        if(move_uploaded_file($_FILES['file']['tmp_name'], $full_filename)){
            $response = convert_audio_file($full_filename);
            if($response['result'] === 'Success'){
                $desc_file = trim_extension_file($response['filename']).'.txt';
                if(file_exists($desc_file)){
                    exit(0);
                }
                $desc = array(
                    'filename' => basename($response['filename']),
                    'description' => rawurlencode(trim_extension_file( basename($filename) ))
                );
                file_put_contents($desc_file, json_encode($desc)."\n");
                echo $desc['filename'];
            }else{
                echo 'Fail convert file.';
                http_response_code(500);
            }
        }else{
            echo 'Fail move file.';
            http_response_code(500);
        }
    }else{
        echo 'Fail upload fail.';
        http_response_code(500);
    }

}elseif ($action === 'set_description' && count($_FILES) === 0 ){
    $input_data = trim(file_get_contents('php://input'));
    $data       = explode('|', $input_data);
    if(count($data)!==2){
        echo "error filename not set";
        http_response_code(500);
    }
    $filename    = $data[1];
    $description = $data[0];
    $desc_filename = $tts_dir . '/' . trim_extension_file($filename). '.txt';
    if(!file_exists("{$desc_filename}")){
        echo "file not {$desc_filename} found";
        http_response_code(500);
        exit(0);
    }
    $data_desc = json_decode(file_get_contents($desc_filename), true);
    $data_desc['description'] = $description;

    file_put_contents($desc_filename, json_encode($data_desc)."\n");
}elseif ($action === 'remove' && count($_FILES) === 0 ){
    $input_data = trim(file_get_contents('php://input'));
    $data       = explode('|', $input_data);
    if(count($data)!==2){
        echo "error filename not set";
        http_response_code(500);
        exit(0);
    }

    $desc_filename = $tts_dir . '/' . $data[1];
    if(file_exists($desc_filename)){
        unlink($desc_filename);
        unlink(trim_extension_file($desc_filename).'.txt');
    }else{
        echo "file not {$desc_filename} found";
        http_response_code(500);
        exit(0);
    }
}elseif($action === 'download'){
    $tmp_view =  str_replace(' ', '+', $_GET['filename']);
    $tmp_wavfile = $tts_dir . '/' .$tmp_view;
    $tmp_wavfile = str_replace('//', '/',$tmp_wavfile);

    if(file_exists($tmp_wavfile)){
        $wavfile = $tmp_wavfile;
    }else{
        echo '<b>404 File not found!</b>';
        exit;
    }
    $size      = filesize($wavfile);
    $name      = basename($tmp_view);
    $extension = strtolower(substr(strrchr($name,"."),1));

    $ctype ='';
    switch( $extension ) {
        case "mp3": $ctype="audio/mpeg"; break;
        case "wav": $ctype="audio/x-wav"; break;
        case "gsm": $ctype="audio/x-gsm"; break;
        // not downloadable
        default: die("<b>404 File not found!</b>"); break ;
    }

    $fp=fopen($wavfile, "rb");
    if ($ctype && $fp) {
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Cache-Control: public");
        header("Content-Description: wav file");
        header("Content-Type: " . $ctype);
        header("Content-Disposition: attachment; filename=" . $name);
        header("Content-Transfer-Encoding: binary");
        header("Content-length: " . $size);
        ob_clean();
        fpassthru($fp);
    }else{
        echo '<b>404 File not found!</b>';
    }
}