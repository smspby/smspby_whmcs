<?php
include("smspby/vendor/autoload.php");
class Sms {
    public $params;
    public $gsmnumber;
    public $message;
    public $userid;
    public $errors = array();
    public $logs = array();
	
    public function setGsmnumber($gsmnumber){
        $this->gsmnumber = $gsmnumber;
    }
	
    public function getGsmnumber(){
        return $this->gsmnumber;
    }

    public function setMessage($message){
        $this->message = $message;
    }

    public function getMessage(){
        return $this->message;
    }

    public function setUserid($userid){
        $this->userid = $userid;
    }

    public function getUserid(){
        return $this->userid;
    }
	
    public function getSettings(){
        $result = select_query("mod_Smspby_settings", "*", ['id' => 1]);
        return mysql_fetch_array($result);
    }
	
    public function getParams(){
        $settings = $this->getSettings();
        $params = json_decode($settings['apiparams'], true);
		return $params;
    }

    /**
     * @return SMSpby\Lib\Smspby\Smspby
     */
    public function createObject()
    {
        $params = $this->getParams();
        $username = $params['username'];
        $apikey = $params['apikey'];
        $senderId = $params['senderid'];

        $smspby = new SMSpby\Lib\Smspby\Smspby();
        $smspby->setOptions(array('plugin' => 'whmcs', 'website' => !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : ''))
            ->authWithUserIdApiKey($username, $apikey)
            ->setForcePrefix('+375')
            ->setSender($senderId);

        return $smspby;
    }

    public function getTemplateDetails($template = null){
        $where  = array("name" => $template);
        $result = select_query("mod_Smspby_templates", "*", $where);
        $data   = mysql_fetch_assoc($result);
        return $data;
    }

    public function getClientDetailsBy($clientId){
        $userSql = "SELECT `a`.`id`, `a`.`firstname`, `a`.`lastname`, `a`.`phonenumber` as `gsmnumber`, `a`.`country`
                    FROM `tblclients` as `a`
                    WHERE `a`.`id` = '".$clientId."'
                    LIMIT 1";
        return mysql_query($userSql);
    }

    function send() {
        $text       = $this->message;
        $to         = $this->getGsmnumber();
		$smspby     = $this->createObject();
		$smspRes     = $smspby->send($to, $text);
		$message_id = '';
		if ($smspRes['status']) {
            $log       = "Message sent!";
			$message_id  = $smspRes['message_id'];
		} else {
			$err_mesg  = is_array($smspRes['error']) ? $smspRes['error']['description'] : $smspRes['error'];
            $err_code  = is_array($smspRes['error']) ? $smspRes['error']['code'] : -100;
			$log     = "Error code by SMSp.by: {$err_code} Error: {$err_mesg}";
			$error   = "Error: $err_mesg";
		}
        
        $this->addLog($log);
		if (isset($error)) {
            $this->addError($error);
			$this->saveToDb($message_id, 'error', $this->getErrors(), $this->getLogs());
			return false;
		} else {
			$this->saveToDb($message_id, '', null, $this->getLogs());
			return true;
        }
    }

    function sendOtpDetails($userID = null, $resend = false)
	{	
	    $this->setUserid($userID);
		$client_query = $this->getClientDetailsBy($this->getUserid());
		$client = mysql_fetch_array($client_query);
        $phone = $this->cleanPhone($client['gsmnumber']);
        $this->setGsmnumber($phone);
		
        $otp_res = select_query("mod_Smspby_otp", "*", ['user_id' => $userID, 'phone' => $phone]);
		$otpData = mysql_fetch_array($otp_res);
        if (empty($otpData)) {
            $otp = rand(100000, 999999);
			$template = $this->getTemplateDetails('ClientAreaRegister_clientarea');
			$message = str_replace(['{firstname}' , '{lastname}', '{otp}'] , [$client['firstname'], $client['lastname'], $otp] , $template['template']);
			$this->setMessage($message);
			insert_query('mod_Smspby_otp', [
				'user_id' => $userID,
				'phone' => $phone,
                'otp' => $otp,
				'verify' => 0
                ]);
			if ($this->send()) {
				return ['status' => true];
			}
        } elseif ($resend) {
            $config = $this->getSettings();
            if ($otpData['resend_count'] >= ($config['sms_count_limit'] - 1) && $config['sms_count_limit'] > 0) {
                return ['status' => false, 'error' => 'limit_exceeded'];
            }
            $otp = $otpData['otp'];
            $template = $this->getTemplateDetails('ClientAreaRegister_clientarea');
			$message = str_replace(['{firstname}' , '{lastname}', '{otp}'] , [$client['firstname'], $client['lastname'], $otp] , $template['template']);
			$this->setMessage($message);
			if ($this->send()) {
                update_query(
                    'mod_Smspby_otp',
                    [
                        'resend_count' => $otpData['resend_count'] + 1
                    ],
                    [
                        'id' => $otpData['id']
                    ]);
                    return ['status' => true];
			}
		} elseif (!$otpData['verify']) {
            return ['status' => true];
        }
		return ['status' => false, 'error' => 'undefined error while sending otp'];
	}
	
	function verifyotp($userOTP) {
        $otp_res = select_query("mod_Smspby_otp", "*", ['user_id' => $this->getUserid(), 'phone' => $this->getGsmnumber()]);
        $data = mysql_fetch_array($otp_res);
        if ($data['otp'] == $userOTP) {
            update_query('mod_Smspby_otp', ['verify' => 1, 'verify_at' => date("Y-m-d H:i:s")], ['user_id' => $this->getUserid(), 'phone' => $this->getGsmnumber()]);
            return true;
        }
        return false;
    }

    // cleanPhone function is used to clean the phone number. Phone can content only numbers and + sign
    function cleanPhone($phone){
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        return $phone;
    }

    function getBalance(){
		$smspby = $this->createObject();
		$result   = $smspby->balanceCheck();
		if ($result['status']) {
			return $result['sms'];
		} else {
			return null;
		}
    }

    function getReport($messageID){
        if ($messageID) {
            $smspby = $this->createObject();
            $result = $smspby->pullReport($messageID);
            if ($result['status']){
                return $result['message_status']['name'];
            } else {
                return "error";
            }
        } else {
            return null;
        }
    }

    function getHooks(){
        if ($handle = opendir(dirname(__FILE__).'/hooks')) {
            while (false !== ($entry = readdir($handle))) {
                if(substr($entry,strlen($entry)-4,strlen($entry)) == ".php"){
                    $file[] = require_once('hooks/'.$entry);
                }
            }
            closedir($handle);
        }
        return $file;
    }

    function saveToDb($phid, $status, $errors = null, $logs = null){
		$params = $this->getParams();
        $table  = "mod_Smspby_messages";
        $values = array(
            "sender"   => $params['senderid'],
            "to"       => $this->getGsmnumber(),
            "text"     => $this->getMessage(),
            "phid"     => $phid,
            "status"   => $status,
            "errors"   => $errors,
            "logs"     => $logs,
            "user"     => $this->getUserid(),
        );
        insert_query($table, $values);
    }

    function addError($error){
        $this->errors[] = $error;
    }

    function addLog($log){
        $this->logs[] = $log;
    }

    function getErrors()
    {
        $res = '<pre><p><ul>';
        foreach($this->errors as $d){
            $res .= "<li>$d</li>";
        }
        $res .= '</ul></p></pre>';
        return $res;
    }

    function getLogs()
    {
        $res = '<pre><p><strong>Debug Result</strong><ul>';
        foreach($this->logs as $d){
            $res .= "<li>$d</li>";
        }
        $res .= '</ul></p></pre>';
        return $res;
    }

    function checkHooks($hooks = null){
        if($hooks == null){
            $hooks = $this->getHooks();
        }

        $i=0;
        foreach($hooks as $hook){
            $sql = "SELECT `id` FROM `mod_Smspby_templates` WHERE `name` = '".$hook['function']."' AND `type` = '".$hook['type']."' LIMIT 1";
            $result   = mysql_query($sql);
            $num_rows = mysql_num_rows($result);
            if($num_rows == 0){
                if($hook['type']){
                    $values = array(
                        "name"        => $hook['function'],
                        "type"        => $hook['type'],
                        "template"    => $hook['defaultmessage'],
                        "variables"   => $hook['variables'],
                        "extra"       => $hook['extra'],
                        "description" => json_encode(@$hook['description']),
                        "active"      => 1
                    );
                    insert_query("mod_Smspby_templates", $values);
                    $i++;
                }
            }else{
                $values = array(
                    "variables" => $hook['variables']
                );
                update_query("mod_Smspby_templates", $values, "name = '" . $hook['name']."'");
            }
        }
        return $i;
    }

    function changeDateFormat($date = null){
        $settings   = $this->getSettings();
        $dateformat = $settings['dateformat'];
        if(!$dateformat){
            return $date;
        }
        $date       = explode("-",$date);
        $year       = $date[0];
        $month      = $date[1];
        $day        = $date[2];
        $dateformat = str_replace(array("%d","%m","%y"),array($day,$month,$year),$dateformat);
        return $dateformat;
    }
	
    function getClientAndInvoiceDetailsBy($clientId){
        $userSql = "
        SELECT a.total,a.date,a.duedate,a.datepaid,b.id as userid,b.firstname,b.lastname,`b`.`country`,`b`.`phonenumber` as `gsmnumber` FROM `tblinvoices` as `a`
        JOIN tblclients as b ON b.id = a.userid
        WHERE a.id = '".$clientId."'
        LIMIT 1
    ";
        return mysql_query($userSql);
    }
}