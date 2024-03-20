<?php
namespace SMSpby\Lib\Smspby;

use SMSpby\Lib\Curl\Client;
class Smspby{
    /**
     * Sender.
     *
     * @var string Declare senderid of user.
     */
    private $sender;      
    /**
     * Route.
     *
     * @var string Declare route of user.
     */
    private $route;          
    /**
     * Url.
     *
     * @var string Define url.
     */
    private $url='https://cabinet.smsp.by/api';   
    /**
     * Authentication parameters.
     *
     * @var array Define Authparams. 
     */
    private $authParams=array();   
    /**
     * Prefix.
     *
     * @var string Prefix eg.91.
     */
	 private $prefix; 
	 /**
     * errors.
     *
     * @var array errors.
     */
    private $errors =  array(); 
	/**
    * additional options.
    * 
    * @var array options.
    */
    private $options = array();
    /*****************************************************************************************
    * Used to set username and apikey.
    * 
    * @param string $user - SMSp.by account username.
    * @param string $apikey  - SMSp.by account apikey.
    *
    * @return object 
    *****************************************************************************************/
    function authWithUserIdApiKey($user,$apikey)
    {
        $this->authParams = array('user'=>$user,'apikey'=>$apikey);
        return $this;
    }

    /*****************************************************************************************
    * Used to set route. 
    * 
    * @param string $route - route
    *
    * @return object
    *****************************************************************************************/
    public function setRoute($route)
    {
        $this->route     = $route;
        return $this;
    } 

    /*****************************************************************************************
    * Used to set sender id. 
    * 
    * @param string $sender - Sender ID.
    *
    * @return object
    *****************************************************************************************/
    public function setSender($sender)
    {
        $this->sender    = $sender;
        return $this;
    }
	
    /*****************************************************************************************
    * Set Params .
    * 
    * @param string $datas - additional parameter eg website, plugin version.
    *
    * @return object
    *****************************************************************************************/
    public function setOptions($datas=array())
    {
        if(!is_array($datas))
		{
			$this->options = array();
		}
		else
		{
			$this->options = $datas;
		}
		return $this;
	 }
	 
	/*****************************************************************************************
    * Get Params .
    * 
    * @param string $datas - additional parameter eg website, plugin version.
    *
    * @return object
    *****************************************************************************************/
    public function getOptions()
    {
		$default_options = array("plugin"=>'php-lib',"website"=>$_SERVER['HTTP_HOST']);
		$this->options=array_merge($default_options,$this->options);
		return $this->options;
	 }
	 
	 
    /*****************************************************************************************
    * Internal function for authenticate parameters to be used only for this class. 
    * 
    * @return object  
    *****************************************************************************************/
    private function getAuthParams()
    {
		if(array_search("", $this->authParams) !== false)
        {
			$this->errors[]="Missing parameters : Username & Apikey";
			return false;
		}else{
			return $this->authParams;
		}
    }
	
	/*****************************************************************************************
    * Internal function for Get Error Messages.
    * 
    * @return array
    *****************************************************************************************/
	private function get_errors()
	{
		if(!empty($this->errors))
		{
			return array('status'=>'error','description'=>$this->errors);
		}
		else{
			return false;
		}
	}
    
    /*****************************************************************************************
    * Internal function for format number to be used only for this class.   
    * 
    * @param string $mobileno - Mobile number
    *
    * @return string
    *****************************************************************************************/
    private function formatNumber($mobileno)
    {    
        $prefix = "";    
        if(substr($mobileno, 0, 1) != '+')
        {
            $prefix = $this->prefix;
        }
        $mobileno = explode(',', $mobileno);
        $nos = array_map(function($no) {
            return preg_replace('/[^0-9]/', '', $no);
        }, $mobileno);
        $valid_no = [];
        if(is_array($nos))
        {           
            foreach($nos as $no){
                $no = ltrim($no, '0');
                $no = (!empty($prefix) && substr($no, 0, strlen($prefix)) != $prefix) ? $prefix . $no : $no;
                $valid_no[] = $no;  
            }
        }
        return implode(',', $valid_no);  
    }

    /*****************************************************************************************
    * Set force prefix to be used only for this class.
    *
    * @param string $prefix - prefix eg.91.
    *
    * @return object
    *****************************************************************************************/
    public function setForcePrefix($prefix="")
    {    
       $this->prefix    = $prefix;
       return $this;
    }
    
    /*****************************************************************************************
    * Sending or scheduling sms.
   
    * @param string $mobileno  - (mandatory) Valid mobile number including country code without leading 0 or + symbol
    *                             multiple numbers can be sent seperated by comma(,).
    * @param string $text      - (mandatory) SMS content to be sent without encoded. 
    * @param string $schedule  - (optional) Schedule your messages eg : '2020-06-10 12:00:02'.
    * @param string $reference - (optional) Reference no. for delivered msg report eg : '125546'.
    * @param string $dlrurl    - (optional) Callback url for delivery notification(url encoded format) eg : http://www.test.com/dlr.php.
	* @param bool $shortenurl  - (optional) Convert your long url into short url.	
    *
    * @return array
    *****************************************************************************************/
    public function send($mobileno,$text,$schedule=null,$reference=null,$dlrurl=null,$shortenurl=false)
    {   
        if(empty($mobileno))
		{
			$this->errors[]='Mobile number is missing';
		}
		if(empty($text))
		{
			$this->errors[]='sms text is missing';
		}
		
		$url    = $this->url.'/send/sms';
        $params = array('sender'=>$this->sender,'msisdn'=>$this->formatNumber($mobileno),'text'=>$text);
		
		

		if($shortenurl){$params['shortenurl'] = 1;}		
        if(!empty($schedule))
        {   
            $params['send_on_datetime'] = $schedule; // for Schedule Sms 
        }
        if(!empty($this->route))
        {
            $params['route']    = $this->route; 
        }
        if(!empty($dlrurl)) // for push report
        {
            $dlrurl             = (parse_url($dlrurl, PHP_URL_HOST) == 'localhost') ? urlencode($dlrurl) : $dlrurl;
            $params['dlrurl']   = $dlrurl;
            if(!empty($reference))
            {
                $params['reference'] = $reference; 
            }
            else{
               $this->errors[]='you must use reference parameter to use DLR callback url';
			}   
        }
		$user_auth = $this->getAuthParams();
		if($this->get_errors())
		{
			return $this->get_errors();
		}
		$params   = array_merge($params,$user_auth,$this->getOptions());
		
        $client   = new Client();
        $response = $client->request('POST', $url, ['json' => $params, 'http_errors' => false]);
        $body     = json_decode($response->getBody(),TRUE); 
        return $body;
    }

    /*****************************************************************************************
    * Used to pull sms report.
    * 
    * @param int $batchid - (mandatory) Batch id received in response to every push request.
    *
    * @return array
    *****************************************************************************************/
    public function pullReport($batchid)
    {   
        return false;
        $url      = $this->url.'/api/pull.json';
        $params   = array('batchid'=>$batchid);
        $user_auth = $this->getAuthParams();
		if($this->get_errors()){
			return $this->get_errors();
		}
		$params   = array_merge($params,$user_auth,$this->getOptions());
        $client   = new Client();
        $response = $client->request('POST', $url, ['json' => $params, 'http_errors' => false]);
        $body     = json_decode($response->getBody(),TRUE); 
        return $body;   
    }

    /*****************************************************************************************
    * Used to check balance. 
    * 
    * @return array 
    *****************************************************************************************/
    public function balanceCheck()
    {
		$user_auth = $this->getAuthParams();
		if($this->get_errors()){
			return $this->get_errors();
		}
        $url      = $this->url.'/balances?user='.$user_auth['user'].'&apikey='.$user_auth['apikey'];
        $client   = new Client();
        $response = $client->request('GET', $url, ['http_errors' => false]);
        $body     = json_decode($response->getBody(),TRUE); 
        return $body;
    }

    /*****************************************************************************************
    * Used to generate otp.
    *
    * @param string $mobileno - (mandatory)valid mobile number including country code without leading 0 or + symbol
    *                            multiple numbers can be sent seperated by comma(,).
    * @param string $template - (mandatory)Template to be used for sending OTP, it is mandatory to include [otp] tag in 
    *                            template content.
    * 
    * @return array
    *****************************************************************************************/
    public function generateOtp($mobileno,$template)
    {
        return false;
        $url 	  = $this->url.'/api/mverify.json';
        $params   = array('sender'=>$this->sender,'mobileno'=>$this->formatNumber($mobileno),'template'=>$template);
        $user_auth = $this->getAuthParams();
		if($this->get_errors()){
			return $this->get_errors();
		}
        $params   = array_merge($params,$user_auth,$this->getOptions()); 
        $client   = new Client();
        $response = $client->request('POST', $url, ['json' => $params, 'http_errors' => false]);
        $body 	  = json_decode($response->getBody(),TRUE); 
        return $body;
    }

    /*****************************************************************************************
    * Used to validate otp.
    * 
    * @param string $mobileno - (mandatory) valid mobile number including country code without leading 0 or + symbol
    *                            multiple numbers can be sent seperated by comma(,).
    * @param string $otp      - (mandatory) OTP entered by the user.
    * 
    * @return array
    *****************************************************************************************/
    public function validateOtp($mobileno,$otp)
    {
        return false;
        $url      = $this->url.'/api/mverify.json';
        $params   = array('code'=>$otp,'mobileno'=>$this->formatNumber($mobileno));
        $user_auth = $this->getAuthParams();
		if($this->get_errors()){
			return $this->get_errors();
		}
        $params   = array_merge($params,$user_auth,$this->getOptions()); 
        $client   = new Client();
        $response = $client->request('POST', $url, ['json' => $params, 'http_errors' => false]);
        $body     = json_decode($response->getBody(),TRUE); 
        return $body;
    }	
}
?>
