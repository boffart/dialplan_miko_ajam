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

namespace MIKO\Modules\ModuleSmartIVR\lib;

class UtilFunctions
{
    public static function get_rout_from_1C($url, $phone, $auth, $did, $id)
    {
        global $logger;
        $result = null;
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, 		  "{$url}/$phone?did=$did&linkedid=$id");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 	  4);
        curl_setopt($curl, CURLOPT_USERPWD, 	  $auth);

        $url_data = parse_url($url);
        $scheme   = $url_data['scheme'] ?? 'http';

        if($scheme === 'https'){
            $t_false = $scheme !== 'https';
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $t_false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $t_false);
        }

        $server_output = curl_exec($curl);
        $code          = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        // В некоторых случаях нужно отсеч первый битый символ псевдо пробела.
        $server_output = substr($server_output, strpos($server_output,'{'));

        if($code !== 200){
            $logger->write('ERROR http code from 1c = '.$code."\n",LOG_NOTICE);
            $logger->write("{$url}/$phone?did=$did",LOG_NOTICE);
            $logger->write($server_output,	LOG_NOTICE);
        }else{
            try{
                $result = json_decode($server_output, true);
                if(!$result){
                    $logger->write('Error format response: '.$server_output."\n", LOG_NOTICE);
                }
            } catch (\Exception $e) {
                $logger->write('Error: '.$e->getMessage()."\n", LOG_NOTICE);
            }
        }
        return $result;
    }

    /**
     * Получение данных из 1С по SOAP для панели телефонии 1
     *
     * @param      $numberData
     * @param      $settings
     * @param bool $relogin
     *
     * @return array
     */
    public static function post1cSoapRequest($numberData, $settings, $relogin = false): array
    {
        $wslink     = $settings['crm-url-soap']??'';
        $wsfunction = $settings['crm-function-soap']??'';
        $wsuri      = $settings['crm-uri-soap']??'';
        $wsAuth     = $settings['crm-http-auth']??'';

        $result = [];

        $params = '';
        foreach ($numberData as $key => $data){
            $params.= "<m:{$key}>{$data}</m:{$key}>";
        }

        $xmlDocumentTpl = '<?xml version="1.0" encoding="UTF-8"?>' .
            '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">' .
            '<soap:Body>' .
            '<m:%function% xmlns:m="%uri%">' .
            '%params%' .
            '</m:%function%>' .
            '</soap:Body>' .
            '</soap:Envelope>';

        $xmlDocument = str_replace(
            [
                '%function%',
                '%uri%',
                '%params%',
            ],
            [
                $wsfunction,
                $wsuri,
                $params,
            ],
            $xmlDocumentTpl
        );


        $curl = curl_init();
        $url = $wslink;
        /**
        if (strpos($url, 'https') !== false) {
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        }
         **/
        $ckfile = '/tmp/module_smart_ivr_1c_session_cookie.txt';

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $xmlDocument);
        curl_setopt($curl, CURLOPT_USERPWD, $wsAuth);
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);

        if ($relogin) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['IBSession: start']);
            curl_setopt($curl, CURLOPT_COOKIEJAR, $ckfile);
        } else {
            curl_setopt($curl, CURLOPT_COOKIEFILE, $ckfile);
        }
        $resultRequest = curl_exec($curl);
        $http_code     = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $have_error = false;
        if (0 === $http_code) {
            $have_error = true;
        } elseif ( ! $relogin && in_array($http_code, [400, 500], false)) {
            return post1cSoapRequest($numberData, $settings, true);
        } elseif (in_array($http_code, [401, 403], false)) {
            $have_error = true;
        } elseif ($http_code !== 200) {
            $have_error = true;
        }

        if ( !$have_error) {
            // Парсим SOAP ответ
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($resultRequest, null, null, 'http://schemas.xmlsoap.org/soap/envelope/');
            if ($xml !== false) {
                $ns                 = $xml->getNamespaces(true);
                $soap               = $xml->children($ns['soap']);
                $getAddressResponse = $soap->Body->children($ns['m']);
                $functionResultName = $wsfunction . 'Response';

                $responseString     = (string)$getAddressResponse->$functionResultName->children($ns['m'])->return;
                $responseString     = str_replace('\\', '', $responseString);
                $res                = json_decode($responseString, true);

                if(is_array($res)){
                    $result = $res;
                }
            }
        }
        return $result;
    }

    /**
     * @param $srcArray
     * @param $additionalArray
     * @return mixed
     */
    public static function overrideConfigurationArray($srcArray, $additionalArray)
    {
        foreach ($additionalArray as $key => $value) {
            if (!isset($srcArray[$key])) {
                continue;
            }
            $srcArray[$key] = $value;
        }

        return $srcArray;
    }

    /**
     * Проверяет, существует ли добавочный номер в системе и возвращает его статус.
     *
     * @param        $number
     * @param        $settings
     *
     * @return array
     */
    public static function getExtensionStatus($number, $settings): array
    {
        $result = ['extension-status' => -2];
        /**
         * -1 = Extension not found
         * 0 = Idle
         * 1 = In Use
         * 2 = Busy
         * 4 = Unavailable
         * 8 = Ringing
         * 16 = On Hold
         */
        $ami = new \AGI_AsteriskManager();
        $ami->connect('127.0.0.1', $settings['ami-user']??'', $settings['ami-secret']??'');
        if ( ! $ami->logged_in()) {
            return $result;
        }
        $resHint  = $ami->ExtensionState($number, $settings['context-hint']??'');
        $resExten = $ami->ExtensionState($number, $settings['context']??'');

        $result = [
            'extension-status'=> (int) ($resHint['Status']??-2),
        ];

        $ami->disconnect();

        return $result;
    }

    public static function trimExtensionForFile($filename, $delimiter = '.'): string
    {
        // Отсечем расширение файла.
        $tmp_arr = explode((string)$delimiter, $filename);
        if (count($tmp_arr) > 1) {
            unset($tmp_arr[count($tmp_arr) - 1]);
            $filename = implode((string)$delimiter, $tmp_arr);
        }

        return $filename;
    }

}