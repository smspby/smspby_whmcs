<?php
namespace SMSpby\Lib\Smspby;

use SMSpby\Lib\Curl\Client;
class Smspby{
    /**
     * Sender.
     * @var string Declare senderid of user.
     */
    private $sender;
    /**
     * Url.
     * @var string Define url.
     */
    private $url = 'https://cabinet.smsp.by/api';
    /**
     * Authentication parameters.
     * @var array Define Authparams. 
     */
    private $authParams = [];
    /**
     * Prefix.
     * @var string Prefix eg.375.
     */
	private $prefix;
	/**
    * Errors.
    * @var array errors.
    */
    private $errors = [];
	/**
    * Additional options.
    * @var array options.
    */
    private $options = [];
    /**
    * Used to set username and apikey.
    * 
    * @param string $user - SMSp.by account username.
    * @param string $apikey  - SMSp.by account apikey.
    *
    * @return object
    */
    function authWithUserIdApiKey($user, $apikey)
    {
        $this->authParams = [
            'user' => $user,
            'apikey' => $apikey
        ];
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
        $this->sender = $sender;
        return $this;
    }
	
    /*****************************************************************************************
    * Set Params .
    * 
    * @param string $options - additional parameter eg website, plugin version.
    *
    * @return object
    *****************************************************************************************/
    public function setOptions($options=[])
    {
        if(!is_array($options))
		{
			$this->options = [];
		}
		else
		{
			$this->options = $options;
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
		$default_options = [
            "plugin" => 'php-lib',
            "website" => $_SERVER['HTTP_HOST'] ?? ''
        ];
		$this->options = array_merge($default_options, $this->options);
		return $this->options;
	}
	 
	 
    /*****************************************************************************************
    * Internal function for authenticate parameters to be used only for this class. 
    * 
    * @return object  
    *****************************************************************************************/
    private function getAuthParams()
    {
		if (($this->authParams['user'] ?? '') === '' || ($this->authParams['apikey'] ?? '') === '')
        {
			$this->errors[] = "Missing parameters: Username & Apikey";
			return false;
		} else {
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
			return ['status' => false, 'error' => implode('; ', $this->errors)];
		} else {
			return false;
		}
	}
    
    /**
     * Format the mobile number by adding the prefix if necessary.
     *
     * @param string $mobileno - Mobile number
     *
     * @return string - Formatted mobile number
     */
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

    /**
     * Set force prefix to be used only for this class.
     *
     * @param string $prefix - prefix eg.375.
     *
     * @return object
     */
    public function setForcePrefix($prefix = "")
    {    
        $this->prefix = $prefix;
        return $this;
    }
    
    /**
     * Sending or scheduling sms.
     *
     * @param string $mobileno  - (mandatory) Valid mobile number including country code without leading 0 or + symbol
     *                             multiple numbers can be sent separated by comma(,).
     * @param string $text      - (mandatory) SMS content to be sent without encoded.
     *
     * @return array
     */
    public function send($mobileno, $text)
    {
        if (empty($mobileno)) {
            $this->errors[] = 'Mobile number is missing';
        }
        if (empty($text)) {
            $this->errors[] = 'SMS text is missing';
        }

        $url = $this->url . '/send/sms';
        $params = [
            'sender' => $this->sender,
            'msisdn' => $this->formatNumber((string) $mobileno),
            'text' => $text,
        ];
        $user_auth = $this->getAuthParams();
        if ($this->get_errors()) {
            return $this->get_errors();
        }
        $params = array_merge($params, $user_auth, $this->getOptions());

        $client = new Client();
        $response = $client->request('POST', $url, ['json' => $params, 'http_errors' => false]);
        $body = json_decode($response->getBody(), true);
        return $body;
    }

    /**
     * Used to check balance.
     *
     * @return array
     */
    public function balanceCheck()
    {
        $user_auth = $this->getAuthParams();
        if ($this->get_errors()) {
            return $this->get_errors();
        }
        $url = $this->url . '/balances?user=' . $user_auth['user'] . '&apikey=' . $user_auth['apikey'];
        $client = new Client();
        $response = $client->request('GET', $url, ['http_errors' => false]);
        $body = json_decode($response->getBody(), true);
        return $body;
    }

    /**
     * Used to pull sms report.
     *
     * @param int $messageID - (mandatory) Batch id received in response to every push request.
     *
     * @return array
     */
    public function pullReport($messageID)
    {
        $user_auth = $this->getAuthParams();
        if ($this->get_errors()) {
            return $this->get_errors();
        }
        $url = $this->url . '/status/sms?user=' . $user_auth['user'] . '&apikey=' . $user_auth['apikey'] . '&message_id=' . $messageID;
        $client = new Client();
        $response = $client->request('GET', $url, ['http_errors' => false]);
        $body = json_decode($response->getBody(), true);
        return $body;
    }

    /**
     * Used to pull multiple sms reports.
     *
     * @param array $phids - (mandatory) Array of message ids received in response to every push request.
     *
     * @return array
     * Example of ok JSON response:
     * {
     *   "status": true,
     *   "messages_status": {
     *     "message_id": 0,
     *     "code": 0, // Числовой код статуса
     *     "name": "string" // Наименование статуса
     *   }
     * }
     * Example of error JSON response:
     * {
     *   "status": false,
     *   "error": {
     *     "code": 4,
     *     "description": "Invalid user or apikey"
     *   }
     * }
     */
    public function pullReports($phids)
    {
        $user_auth = $this->getAuthParams();
        if ($this->get_errors()) {
            return $this->get_errors();
        }
        $url = $this->url . '/statusBulk/sms?user=' . $user_auth['user'] . '&apikey=' . $user_auth['apikey'] . '&message_ids=' . implode(',', $phids);
        $client = new Client();
        $response = $client->request('POST', $url, ['http_errors' => false]);
        return json_decode($response->getBody(), true);
    }
}