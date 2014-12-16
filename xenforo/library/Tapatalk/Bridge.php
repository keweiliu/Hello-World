<?php

/**
* ControllerPublic + FrontController combo class :: Tapatalk API Bridge
*/
class Tapatalk_Bridge extends XenForo_ControllerPublic_Abstract {
	
	/**
	* Instance holder
	*
	* @var Tapatalk_Bridge
	*/
	private static $_instance;
	
	/**
	* Input cleaning class
	*
	* @var Tapatalk_Input
	*/
	public $_input;
	
	
	public $_request;
	public $_response;
	
	/**
	* Any errors go here for later output via xml-rpc
	*
	* @var string
	*/
	public $error;
	public $mobiquo_configuration;
	/**
	* @var Tapatalk_Dependencies_Public
	*/
	protected $_dependencies;
	
	private $_action;
	private $_session_timeout;
	
	public function __construct(){	
		$this->_dependencies = new Tapatalk_Dependencies_Public();
		$this->_dependencies->preLoadData();
		
		$this->_request = new Zend_Controller_Request_Http();
		$this->_response = new Zend_Controller_Response_Http();
		$this->_input = new Tapatalk_Input($this->_request);
		
		// not sure how reliable using dirname() like this is
		$this->_request->setBasePath(/*str_replace("/mobiquo", "/", $this->_request->getBasePath())*/ dirname($this->_request->getBasePath()));
		
		$requestPaths = XenForo_Application::getRequestPaths($this->_request);
		
		XenForo_Application::set('requestPaths', $requestPaths);
		
	}
	
	public function init(){

		$this->_prepareGetConfig();
		$this->_preDispatchFirst($this->_action);
		
		$this->_setupSession($this->_action);
		$this->_preTapatalkSetting();
		$this->_handlePost($this->_action);
		
		$this->_preDispatchType($this->_action);
		$this->_preDispatch($this->_action);
		
//		XenForo_CodeEvent::fire('controller_pre_dispatch', array($this, $this->_action));
		
		$this->_dependencies->preRenderViewWithDefaultStyle();
	}
	
	protected function _preTapatalkSetting()
	{
	    global $request_method_name, $mobiquo_config;
	    
        if ($request_method_name == 'get_config' || $request_method_name == 'login' || $request_method_name == 'sign_in' || $request_method_name == 'register' || $request_method_name == 'prefetch_account' || $request_method_name == 'forget_password' || $request_method_name == 'get_contact' || $request_method_name == 'set_api_key' || $request_method_name == 'sync_user')
        {
            $visitor = XenForo_Visitor::getInstance();
            $user_permissions = $visitor->getPermissions();
            if (!isset($user_permissions['general']['view']) || empty($user_permissions['general']['view']))
            {
                $user_permissions['general']['view'] = 1;
                $mobiquo_config['guest_okay'] = 0;
            }
            $visitor->offsetSet('permissions', $user_permissions);
        }
	}

	protected function _prepareGetConfig()
	{
	    global $mobiquo_config;
		$options = XenForo_Application::get('options');
		if($this->_action == 'get_config' && !$options->boardActive)
		{
			$options->boardActive =  1;
			XenForo_Application::set('options', $options);
			XenForo_Application::set('originBoardActive', 0);
		}
		else
			XenForo_Application::set('originBoardActive', 1);
		
		$register_setup = $options->registrationSetup;
        if(!isset($register_setup['enabled']) || empty($register_setup['enabled']))
        {
            $mobiquo_config['sign_in'] = 0;
            $mobiquo_config['inappreg'] = 0;
            
            $mobiquo_config['sso_signin'] = 0;
            $mobiquo_config['sso_register'] = 0;
            $mobiquo_config['native_register'] = 0;
        }
        if (!function_exists('curl_init') && !@ini_get('allow_url_fopen')) 
        {
            $mobiquo_config['sign_in'] = 0;
            $mobiquo_config['inappreg'] = 0;
            
            $mobiquo_config['sso_login'] = 0;
            $mobiquo_config['sso_signin'] = 0;
            $mobiquo_config['sso_register'] = 0;
        }
        if (isset($options->tapatalk_reg_type))
        {
            if ($options->tapatalk_reg_type == 1)
            {
                $mobiquo_config['sign_in'] = 0;
                $mobiquo_config['inappreg'] = 0;
                
                $mobiquo_config['sso_signin'] = 0;
                $mobiquo_config['sso_register'] = 0;
                $mobiquo_config['native_register'] = 0;
            }
        }
        $this->mobiquo_configuration = $mobiquo_config;
	}

	public function setAction($action){
		$this->_action = $action;
	}
	
	public function shutdown(){
		$this->postDispatch(new XenForo_ControllerResponse_Message(), 'Tapatalk_ControllerPublic_Tapatalk', $this->_action);
		$this->_response->sendHeaders();
	}

    public function setUserParams($key, $value){
        if($key == 'useragent')
        {
            if(strpos($value, 'Tapatalk') !== false)
                $value = 'tapatalk';
            else if(strpos($value, 'BYO') !== false)
                $value = 'byo';
            else
                $value = 'others';
        }
        $this->_request->setParam($key, $value);
    }
	public function renderPostPreview($message, $length=0){	
		$formatter = XenForo_BbCode_Formatter_Base::create('Tapatalk_BbCode_Formatter_ShortContent');
		$parser = new XenForo_BbCode_Parser($formatter);
		$rendered = $parser->render($message);
		$rendered = str_replace(array("\r", "\n"), " ", $rendered);
		return $length > 0 ? cutstr($rendered, $length) : $rendered;
	}
	
	/*
	* Bridge instance manager
	*
	* @return Tapatalk_Bridge
	*/
	public static final function getInstance()
	{
		if (!self::$_instance)
		{
			self::$_instance = new Tapatalk_Bridge();
		}

		return self::$_instance;
	}
	
	/**
	* @return Tapatalk_Dependencies_Public
	*/
	public function getDependencies(){
		return $this->_dependencies;
	}
	
	/**
	* Is user online?
	* @return boolean
	*/
	public function isUserOnline($user){
		
		$visitor = XenForo_Visitor::getInstance();
	
		if(!isset($user['view_date']) || empty($user['view_date']))
			$user['view_date'] = $user['last_activity'];
			
		if(
		($user['view_date'] > $this->_getSessionTimeout() && $user['visible']) ||
		($user['view_date'] > $this->_getSessionTimeout() && $user['visible'] == 0 && ($visitor['is_admin'] || $visitor['user_id'] == $user['user_id'])) ||
		($user['view_date'] > $this->_getSessionTimeout() && $user['visible'] == 0 && $user['is_admin'] && $visitor['is_moderator'])
		)
			return true;
		
		return false;
		
	}
	
	public function assertLoggedIn(){
		$visitor = XenForo_Visitor::getInstance();
		if(!$visitor['user_id']){
			$this->getErrorOrNoPermissionResponseException(new XenForo_Phrase('login_required'));
			return false;
		}
		return true;
	}
	
	
	public function cleanPost($post, $extraStates=array())
	{
		if (!isset($extraStates['states']['returnHtml']))
			$extraStates['states']['returnHtml'] = false;

		if ($extraStates['states']['returnHtml'])
		{
			$post = str_replace("&", '&amp;', $post);
			$post = str_replace("<", '&lt;', $post);
			$post = str_replace(">", '&gt;', $post);
			$post = str_replace("\r", '', $post);
			$post = str_replace("\n", '<br />', $post);
		}
		
		if(!$extraStates)
			$extraStates = array('states' => array());

		// replace code like content with quote
//		$post = preg_replace('/\[(CODE|PHP|HTML)\](.*?)\[\/\1\]/si','[quote]$2[/quote]',$post);

		$post = $this->processListTag($post);
		$bbCodeFormatter = new Tapatalk_BbCode_Formatter_Tapatalk((boolean)$extraStates['states']['returnHtml']);
		$bbCodeParser = new XenForo_BbCode_Parser($bbCodeFormatter);
		$post = $bbCodeParser->render($post, $extraStates['states']);
		$post = trim($post);


		$options = XenForo_Application::get('options');
		$custom_replacement = $options->tapatalk_custom_replacement;
		if(!empty($custom_replacement))
		{
			$replace_arr = explode("\n", $custom_replacement);
			foreach ($replace_arr as $replace)
			{
				preg_match('/^\s*(\'|")((\#|\/|\!).+\3[ismexuADUX]*?)\1\s*,\s*(\'|")(.*?)\4\s*$/', $replace,$matches);
				if(count($matches) == 6)
				{
					$temp_post = $post;
					$post = @preg_replace($matches[2], $matches[5], $post);
					if(empty($post))
					{
						$post = $temp_post;
					}
				}	
			}
		}
		return $post;
	}
	
	protected function processListTag($message)
	{
		$contents = preg_split('#(\[LIST=[^\]]*?\]|\[/?LIST\])#siU', $message, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

		$result = '';
		$status = 'out';
		foreach($contents as $content)
		{
			if ($status == 'out')
			{
				if ($content == '[LIST]')
				{
					$status = 'inlist';
				} elseif (strpos($content, '[LIST=') !== false)
				{
					$status = 'inorder';
				} else {
					$result .= $content;
				}
			} elseif ($status == 'inlist')
			{
				if ($content == '[/LIST]')
				{
					$status = 'out';
				} else
				{
					$result .= str_replace('[*]', '  * ', ltrim($content));
				}
			} elseif ($status == 'inorder')
			{
				if ($content == '[/LIST]')
				{
					$status = 'out';
				} else
				{
					$index = 1;
					$result .= preg_replace_callback('/\[\*\]/s',
                        array($this,'matchCount'),
                    ltrim($content));
				}
			}
		}
		return $result;
	}
    private function matchCount($matches){
        static $index = 1;
        return '  '.$index++.'. ';
    }

	protected function _getSessionTimeout()
	{
		if(!isset($this->_session_timeout))
		{
			$this->_session_timeout = XenForo_Model::create('XenForo_Model_Session')->getOnlineStatusTimeout();
		}

		return $this->_session_timeout;
	}
	
	/**
	 * @return XenForo_Model_Banning
	 */
	public function getBanningModel()
	{
		return $this->getModelFromCache('XenForo_Model_Banning');
	}
	/**
	 * @return XenForo_Model_Login
	 */
	public function getLoginModel()
	{
		return $this->getModelFromCache('XenForo_Model_Login');
	}
	
	/**
	 * @return XenForo_Model_User
	 */
	public function getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}

    /**
     * @return XenForo_Model_UserGroup
     */
    public function getUserGroupModel()
    {
        return $this->getModelFromCache('XenForo_Model_UserGroup');
    }

	/**
	 * @return XenForo_Model_Conversation
	 */
	public function getConversationModel()
	{
		return $this->getModelFromCache('XenForo_Model_Conversation');
	}
	
	/**
	 * @return XenForo_Model_Node
	 */
	public function getNodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Node');
	}

	/**
	 * @return XenForo_Model_NewsFeed
	 */
	public function getNewsFeedModel()
	{
		return $this->getModelFromCache('XenForo_Model_NewsFeed');
	}
	
	/**
	 * @return XenForo_Model_Forum
	 */
	public function getForumModel()
	{
		return $this->getModelFromCache('XenForo_Model_Forum');
	}
	
	/**
	 * @return XenForo_Model_Session
	 */
	public function getSessionModel()
	{
		return $this->getModelFromCache('XenForo_Model_Session');
	}
	
	/**
	 * @return XenForo_Model_Permission
	 */
	public function getPermissionModel()
	{
		return $this->getModelFromCache('XenForo_Model_Permission');
	}

	/**
	 * @return XenForo_Model_Permission
	 */
	public function getPrefixModel()
	{
		return $this->getModelFromCache('XenForo_Model_ThreadPrefix');
	}

	/**
	 * @return XenForo_Model_Search
	 */
	public function getSearchModel()
	{
		return $this->getModelFromCache('XenForo_Model_Search');
	}
	
	/**
	 * @return XenForo_Model_Like
	 */
	public function getLikeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Like');
	}
	
	/**
	 * @return XenForo_Model_Thread
	 */
	public function getThreadModel()
	{
		return $this->getModelFromCache('XenForo_Model_Thread');
	}
	
	/**
	 * @return XenForo_Model_Post
	 */
	public function getPostModel()
	{
		return $this->getModelFromCache('XenForo_Model_Post');
	}
	
	/**
	 * @return XenForo_Model_UserIgnore
	 */
	public function getIgnoreModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserIgnore');
	}
	
	/**
	 * @return Tapatalk_Model_Alert
	 */
	public function getAlertModel()
	{
		return $this->getModelFromCache('XenForo_Model_Alert');
	}
		
	/**
	 * @return XenForo_Model_UserProfile
	 */
	public function getUserProfileModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserProfile');
	}
		
	/**
	 * @return Tapatalk_Model_AddOn
	 */
	public function getAddOnModel()
	{
		return $this->getModelFromCache('XenForo_Model_AddOn');
	}
	
	/**
	 * @return XenForo_Model_UserProfile
	 */
	public function getUserConfirmationModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserConfirmation');
	}
	/**
	 * @return XenForo_Model_Attachment
	 */
	public function getAttachmentModel()
	{
		return $this->getModelFromCache('XenForo_Model_Attachment');
	}
		
	/**
	 * @return XenForo_Model_ThreadWatch
	 */
	public function getThreadWatchModel()
	{
		return $this->getModelFromCache('XenForo_Model_ThreadWatch');
	}

	/**
	 * @return XenForo_Model_ForumWatch
	 */
	public function getForumWatchModel()
	{
		return $this->getModelFromCache('XenForo_Model_ForumWatch');
	}
	
	/**
	 * @return XenForo_Model_InlineMod_Thread
	 */
	public function getInlineModThreadModel()
	{
		return $this->getModelFromCache('XenForo_Model_InlineMod_Thread');
	}
	
	/**
	 * @return XenForo_Model_InlineMod_Post
	 */
	public function getInlineModPostModel()
	{
		return $this->getModelFromCache('XenForo_Model_InlineMod_Post');
	}
	
	/**
	 * @return XenForo_Model_ThreadRedirect
	 */
	public function getThreadRedirectModel()
	{
		return $this->getModelFromCache('XenForo_Model_ThreadRedirect');
	}
	
	/**
	 * @return XenForo_Model_Report
	 */
	public function getReportModel()
	{
		return $this->getModelFromCache('XenForo_Model_Report');
	}
	
	/**
	 * @return XenForo_Model_SpamCleaner
	 */
	public function getSpamCleanerModel()
	{
		return $this->getModelFromCache('XenForo_Model_SpamCleaner');
	}

    /**
     * @return XenForo_Model_SpamPrevention
     */
    public function getSpamPreventionModel()
    {
        return $this->getModelFromCache('XenForo_Model_SpamPrevention');
    }
    
	/**
	 * @return Tapatalk_Model_TapatalkUser
	 */
	public function getTapatalkUserModel()
	{
		return $this->getModelFromCache('Tapatalk_Model_TapatalkUser');
	}
	
	/**
	 * @return XenForo_Model_ModerationQueue
	 */
	public function getModerationQueueModel()
	{
		return $this->getModelFromCache('XenForo_Model_ModerationQueue');
	}
	
    /**
     * @return XenForo_Model_Warning
     */
    public function getWarningModel()
    {
        return $this->getModelFromCache('XenForo_Model_Warning');
    }

	/**
	 * @return XenForo_Model_UserField
	 */
	public function _getFieldModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserField');
	}
	
	/**
	 * @return XenForo_Model_ThreadPrefix
	 */
	public function _getPrefixModel()
	{
		return $this->getModelFromCache('XenForo_Model_ThreadPrefix');
	}
	
	public function responseNoPermission(){
		return $this->responseError(new XenForo_Phrase('do_not_have_permission'));
	}
	
	
	/**
	* Controller response for when you want to throw an error and display it to the user.
	*
	* @param string|array  Error text to be use
	* @param integer An optional HTTP response code to output
	* @param array   Key-value pairs of parameters to pass to the container view
	*
	* @return XenForo_ControllerResponse_Error
	*/
	public function responseError($error, $responseCode = 200, array $containerParams = array())
	{
		$this->error = (string)$error;
		$controllerResponse = new XenForo_ControllerResponse_Error();
		$controllerResponse->errorText = $error;
		$controllerResponse->responseCode = $responseCode;
		$controllerResponse->containerParams = $containerParams;

		return $controllerResponse;
	}

	/**
	* Controller response for when you want to display a message to a user.
	*
	* @param string  Error text to be use
	* @param array   Key-value pairs of parameters to pass to the container view
	*
	* @return XenForo_ControllerResponse_Message
	*/
	public function responseMessage($message, array $containerParams = array())
	{
	   /* $controllerResponse = new XenForo_ControllerResponse_Message();
		$controllerResponse->message = $message;
		$controllerResponse->containerParams = $containerParams;

		return $controllerResponse;*/
		$this->error = $message;
	}

	/**
	 * Gets the exception object for controller response-style behavior. This object
	 * cannot be returned from the controller; an exception must be thrown with it.
	 *
	 * This allows any type of controller response to be invoked via an exception.
	 *
	 * @param XenForo_ControllerResponse_Abstract $controllerResponse Type of response to invoke
	 *
	 * @return XenForo_ControllerResponse_Exception
	 */
	public function responseException(XenForo_ControllerResponse_Abstract $controllerResponse, $responseCode = null)
	{
		//return new Exception();
		return new XenForo_ControllerResponse_Exception($controllerResponse);
	}
	
	public function responseErrorMessage(XenForo_ControllerResponse_Reroute $controllerResponse)
	{
        $controllerName = $controllerResponse->controllerName;
        $action = $controllerResponse->action;

        $controllerName = XenForo_Application::resolveDynamicClass($controllerName, 'controller');
        $error_controller = new $controllerName($this->_request, $this->_response, new XenForo_RouteMatch($controllerName, $action));
        return $error_controller->{'action' . $action}();
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
    public function getContentFromRemoteServer($url, $holdTime = 0, &$error_msg, $method = 'GET', $data = array())
    {
        //Validate input.
        $vurl = parse_url($url);
        if ($vurl['scheme'] != 'http')
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
        if(!empty($holdTime) && function_exists('file_get_contents') && $method == 'GET')
        {
            $response = file_get_contents($url);
        }
        else if (@ini_get('allow_url_fopen') && false)
        {
            if(empty($holdTime))
            {
                // extract host and path:
                $host = $vurl['host'];
                $path = $vurl['path'];
    
                if($method == 'POST')
                {
                    $fp = @fsockopen($host, 80, $errno, $errstr, 5);

                    if(!$fp)
                    {
                        $error_msg = 'Error: socket open time out or cannot connet.';
                        return false;
                    }
    
                    $data =  http_build_query($data);
    
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
                    $params = array('http' => array(
                        'method' => 'POST',
                        'content' => http_build_query($data, '', '&'),
                    ));
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
        elseif (function_exists('curl_init') && false)
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
}
