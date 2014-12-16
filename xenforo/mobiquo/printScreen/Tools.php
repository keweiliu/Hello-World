<?php
if (!defined('SCRIPT_ROOT')){
    define('SCRIPT_ROOT', empty($_SERVER['SCRIPT_FILENAME']) ? '../../' : dirname(dirname($_SERVER['SCRIPT_FILENAME'])).'/');
}
if (!defined('FORUM_ROOT')){
    if (DIRECTORY_SEPARATOR == '/'){
        define('FORUM_ROOT', 'http://'.$_SERVER['HTTP_HOST'].dirname(dirname($_SERVER['SCRIPT_NAME'])).'/');
    }else{
        define('FORUM_ROOT', 'http://'.$_SERVER['HTTP_HOST'].str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))).'/');
    }
}
include_once 'FileSums.php';
require_once 'ImplementFunctions.php';

class Tools{
    public static function print_screen()
    {
        $implement=new ImplementsFunctions();
        $mobiquo_config = Tools::get_mobiquo_config($implement->getTapatalkDirName());
        $current_plugin_version = $mobiquo_config['version'];
        $latest_tp_plugin_version = $implement::getLatestPluginVersion();
        $mobiquo_path = self::get_path();
        $check_upload_status = file_get_contents("http://".$mobiquo_path."/upload.php?checkAccess");
        $errors = self::checkFile(SCRIPT_ROOT, $implement->getTapatalkDirName());
        $check_api_key_status = self::checkAPIKey();
        $check_addon_version_status = self::checkAddonVersion();
        $table_exist = $implement->checkTapatalkUserTable();
        $ip =  self::do_post_request(array('ip' => 1), true);
        $push_slug = $implement->getPushSlug();
        $systemVersion = $implement::getSystemVersion();

        include_once 'Template.php';
    }

    /**
     * Compares the hashes of a list of files with what is actually on the disk.
     *
     * @param array $hashes [file] => hash
     * @param string $rootDir root directroy
     *
     * @return array List of errors, [file] => missing or mismatch
     */
    public static function compareHashes(array $hashes, $rootDir, $tapatalkDirName = 'mobiquo')
    {
        $cwd = getcwd();
        chdir($rootDir);

        $errors = array();

        foreach ($hashes AS $file => $hash)
        {
            if (!empty($tapatalkDirName) && $tapatalkDirName != AbstractFunctions::TP_DAFAULT_DIR_NAME){
                if (preg_match('/^'.AbstractFunctions::TP_DAFAULT_DIR_NAME.'\//', $file)){
                    $file = preg_replace('/^'.AbstractFunctions::TP_DAFAULT_DIR_NAME.'\//', $tapatalkDirName.'/', $file);
                }
            }
            if (file_exists($file))
            {
                if (abs(filesize($file) == 0)){
                    $errors[$file] = 'empty';
                }else if (self::getFileContentsHash(file_get_contents($file)) != $hash)
                {
                    $errors[$file] = 'mismatch';
                }
            }
            else
            {
                $errors[$file] = 'missing';
            }
        }

        chdir($cwd);

        return $errors;
    }

    /**
     * Hashes the content of a file in a line-ending agnostic way.
     *
     * @param string $contents Contents of file
     *
     * @return string Hash of contents
     */
    public static function getFileContentsHash($contents)
    {
        $contents = str_replace("\r", '', $contents);
        return md5($contents);
    }

    public static function checkFile($rootName, $tapatalkDirName = 'mobiquo'){
        if (class_exists('FileSums') && method_exists(FileSums, 'getHashes')){
            $hashes = FileSums::getHashes();
        }else{
            return array(substr(dirname(__FILE__), strlen(SCRIPT_ROOT)).DIRECTORY_SEPARATOR."FileSums.php"=>'missing');
        }

        $errors = self::compareHashes($hashes, $rootName, $tapatalkDirName);

        $viewParams = array(
            'errors' => $errors,
            'hashes' => $hashes,
        );

        return $errors;
    }

    public static function get_path()
    {
        $path =  '../';

        if (!empty($_SERVER['SCRIPT_NAME']) && !empty($_SERVER['HTTP_HOST']))
        {
            $path = $_SERVER['HTTP_HOST'];
            $path .= dirname($_SERVER['SCRIPT_NAME']);
        }
        return $path;
    }

    /**
     * Get content from remote server
     *
     * @param string $url      NOT NULL          the url of remote server, if the method is GET, the full url should include parameters; if the method is POST, the file direcotry should be given.
     * @param string $holdTime [default 0]       the hold time for the request, if holdtime is 0, the request would be sent and despite response.
     * @param string $error_msg                  return error message
     * @param string $method   [default GET]     the method of request.
     * @param string $data     [default array()] post data when method is POST.
     *
     * @exmaple: getContentFromRemoteServer('http://push.tapatalk.com/push.php', 0, $error_msg, 'POST', $ttp_post_data)
     * @return string when get content successfully|false when the parameter is invalid or connection failed.
     */
    public static function getContentFromRemoteServer($url, $holdTime = 0, &$error_msg, $method = 'GET', $data = array())
    {
        //Validate input.
        $vurl = parse_url($url);
        if ($vurl['scheme'] != 'http' && $vurl['scheme'] != 'https')
        {
            $error_msg = 'Error: invalid url given: '.$url;
            return false;
        }
        if($method != 'GET' && $method != 'POST')
        {
            $error_msg = 'Error: invalid method: '.$method;
            return false;//Only POST/GET supported.
        }
        if($method == 'POST' && empty($data))
        {
            $error_msg = 'Error: data could not be empty when method is POST';
            return false;//POST info not enough.
        }

        $response = '';

        if(!empty($holdTime) && function_exists('file_get_contents') && $method == 'GET')
        {
            $opts = array(
            $vurl['scheme'] => array(
                'method' => "GET",
                'timeout' => $holdTime,
            )
            );

            $context = stream_context_create($opts);
            $response = file_get_contents($url,false,$context);
        }
        else if (@ini_get('allow_url_fopen'))
        {
            if(empty($holdTime))
            {
                // extract host and path:
                $host = $vurl['host'];
                $path = $vurl['path'];

                if($method == 'POST')
                {
                    $fp = fsockopen($host, 80, $errno, $errstr, 5);

                    if(!$fp)
                    {
                        $error_msg = 'Error: socket open time out or cannot connet.';
                        return false;
                    }

                    $data = http_build_query($data, '', '&');

                    fputs($fp, "POST $path HTTP/1.1\r\n");
                    fputs($fp, "Host: $host\r\n");
                    fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
                    fputs($fp, "Content-length: ". strlen($data) ."\r\n");
                    fputs($fp, "Connection: close\r\n\r\n");
                    fputs($fp, $data);
                    fclose($fp);
                }
                else
                {
                    $error_msg = 'Error: 0 hold time for get method not supported.';
                    return false;
                }
            }
            else
            {
                if($method == 'POST')
                {
                    $params = array(
                    $vurl['scheme'] => array(
                        'method' => 'POST',
                        'content' => http_build_query($data, '', '&'),
                    )
                    );
                    $ctx = stream_context_create($params);
                    $old = ini_set('default_socket_timeout', $holdTime);
                    $fp = @fopen($url, 'rb', false, $ctx);
                }
                else
                {
                    $fp = @fopen($url, 'rb', false);
                }
                if (!$fp)
                {
                    $error_msg = 'Error: fopen failed.';
                    return false;
                }
                ini_set('default_socket_timeout', $old);
                stream_set_timeout($fp, $holdTime);
                stream_set_blocking($fp, 0);

                $response = @stream_get_contents($fp);
            }
        }
        elseif (function_exists('curl_init'))
        {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            if($method == 'POST')
            {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
            if(empty($holdTime))
            {
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT,1);
            }
            $response = curl_exec($ch);
            curl_close($ch);
        }
        else
        {
            $error_msg = 'CURL is disabled and PHP option "allow_url_fopen" is OFF. You can enable CURL or turn on "allow_url_fopen" in php.ini to fix this problem.';
            return false;
        }
        return $response;
    }

    public static function get_mobiquo_config($tapatalkDirName = 'mobiquo')
    {
        $mobiquo_config = $mobiquo_config = TTConfig::get_config();

        //      $options = XenForo_Application::get('options');
        //      $mobiquo_config['guest_okay'] = $options->guest_okay;
        //      $mobiquo_config['reg_url'] = isset($options->reg_url) && !empty($options->reg_url) ? $options->reg_url: 'index.php?register';
        //      $mobiquo_config['advanced_delete'] = $options->advanced_delete;

        return $mobiquo_config;
    }

    public static function checkAPIKey()
    {
        $implement = new ImplementsFunctions();

        $option_key = $implement->getLocalApiKey();

        if(!isset($option_key) || empty($option_key))
        {
            return 'missingApiKey';
        }
        $mobi_api_key=$implement->getRemoteApiKey();

        if ($option_key != $mobi_api_key){
            return 'mistakenApiKey';
        }else{
            return 'ok';
        }
    }

    public static function checkAddonVersion(){
        $implement = new ImplementsFunctions();
        $systemAddonVersion=$implement->getSystemAddonVersion();
        $config = self::get_mobiquo_config($implement->getTapatalkDirName());
        $localAddonVersion = $config['version'];
        if ($systemAddonVersion != $localAddonVersion){
            return 'mismatchAddonVersion';
        }
        return "ok";
    }

    public static function do_post_request($data, $pushTest = false)
    {
        $push_url = 'http://push.tapatalk.com/push.php';
        $res =  self::getContentFromRemoteServer($push_url, $pushTest ? 10 : 0, $error,'POST',$data);
        return $res;
    }
}