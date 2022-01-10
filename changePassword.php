<?php

namespace Aurora\Modules\PostfixadminChangePassword;

$loginUrl = 'url_postfix_admin';

class MyXML_Template{
	public $email;
	private $password;
	
	function __construct($email, $password){
		$this->email = $email;
                $this->password = $password;
	}
	

        //create template  xml loginn
	/*
	<?xml version="1.0" encoding="UTF-8"?>
	<methodCall><methodName>login.login</methodName><params><param><value><string>email</string></value></param><param><value><string>password</string></value></param></params></methodCall>	
	*/
        function create_xml_login(){
                $xml_template='<?xml version="1.0" encoding="UTF-8"?>';
                $xml_template.='<methodCall><methodName>login.login</methodName>';
                $xml_template.='<params>';

                $xml_template.='<param><value><string>';
                $xml_template.=$this->email;
                $xml_template.='</string></value></param>';

                $xml_template.='<param><value><string>';
                $xml_template.=$this->password;
                $xml_template.='</string></value></param>';

                $xml_template.='</params>';
                $xml_template.='</methodCall>';         

                return $xml_template;
        }


        //create template change password 
	/*
	<?xml version="1.0" encoding="UTF-8"?>
	<methodCall><methodName>user.changePassword</methodName><params><param><value><string>password</string></value></param><param><value><string>newpassword</string></value></param></params></methodCall>
	*/
        function create_xml_changePassword($newpassword){
                $xml_template='<?xml version="1.0" encoding="UTF-8"?>';
                $xml_template.='<methodCall><methodName>user.changePassword</methodName>';
                $xml_template.='<params>';

                $xml_template.='<param><value><string>';
                $xml_template.=$this->password;
                $xml_template.='</string></value></param>';

                $xml_template.='<param><value><string>';
                $xml_template.=$newpassword;
                $xml_template.='</string></value></param>';

                $xml_template.='</params>';
                $xml_template.='</methodCall>';         

                return $xml_template;
        }
	
	
}


class MyXML extends MyXML_Template{
	public $email, $url, $response;
	private $password, $cookie;	
	function __construct($email, $password, $url){
		parent::__construct($email, $password);
		
		$this->email = $email;
		$this->password = $password;
		$this->url=$url;
	
	}



	function login(){
		$xml_login=$this->create_xml_login();
		$send_context = stream_context_create(array(
		    'http' => array(
    		    'method' => 'POST',
		    'header' => 'Content-Type: application/xml; charset=utf-8',
		    'content' => $xml_login
        		)
			));

		$result_xml = strval(file_get_contents($this->url, true, $send_context));
		$response=$http_response_header;

		$this->result = $this ->getResult($result_xml);
		
		if ($this->result){
			$this->cookie = $this->getCookie($response);
		}		
	}
	


	function changePassword($newpassword){		
		$xml_change_password=$this->create_xml_changePassword($newpassword);
		$send_context = stream_context_create(array(
    			'http' => array(
    			'method' => 'POST',
    			'header' => array ('Connection: close' , 'Cookie:'.$this->cookie.';','Content-Type: application/xml; charset=utf-8'),
    			'content' => $xml_change_password
    					)
				));


		$result_xml = strval(file_get_contents($this->url, true, $send_context));
		
		return $this ->getResult($result_xml);

	}



	/*
	get Cookie from Header
	...
	Set-Cookie: postfixadmin_session=id_number; path=/
	...
	*/
	function getCookie($response){
		$cookie_aux=explode("Set-Cookie: ", $response[8])[1];
                $cookie=explode(";", $cookie_aux)[0];
		return $cookie;
	}


	/*
	get result  
	<?xml version="1.0" encoding="UTF-8"?>
	<methodResponse><params><param><value><boolean>0/1</boolean></value></param></params></methodResponse> 
	*/
	function getResult($result_xml){
		$result_xml = explode("<boolean>",$result_xml)[1];
                $result = explode("</boolean>",$result_xml)[0];
		if ($result == '1')
			return true;
		return false;
	}
}


class Module extends \Aurora\System\Module\AbstractModule
{

    public function init()
    {
        $this->subscribeEvent('Mail::Account::ToResponseArray', array($this, 'onMailAccountToResponseArray'));
        $this->subscribeEvent('Mail::ChangeAccountPassword', array($this, 'onChangeAccountPassword'));
    }


    protected function checkCanChangePassword($oAccount) {
        return true;
    }


    public function onMailAccountToResponseArray($aArguments, &$mResult) {
        $oAccount = $aArguments['Account'];
        if ($oAccount && $this->checkCanChangePassword($oAccount)) {
            if (!isset($mResult['Extend']) || !is_array($mResult['Extend'])) {
                $mResult['Extend'] = [];
            }
            $mResult['Extend']['AllowChangePasswordOnMailServer'] = true;
        }
    }

    public function onChangeAccountPassword($aArguments, &$mResult) {
        $oAccount = $aArguments['Account'];
        $canchange = $this->checkCanChangePassword($oAccount);
        $bPasswordChanged = false;
        $bBreakSubscriptions = false;
        $oAccount = $aArguments['Account'];
        if ($oAccount) {
            $bPasswordChanged = $this->changePassword($oAccount, $aArguments['NewPassword']);
        }
        if (is_array($mResult)) {
            $mResult['AccountPasswordChanged'] = $mResult['AccountPasswordChanged'] || $bPasswordChanged;
        }

        return $bBreakSubscriptions;
    }



    protected function changePassword($oAccount, $sPassword) {
        $this->ChangePasswordProcess($oAccount, $sPassword);
        return true;
    }

   protected function ChangePasswordProcess($oAccount, $sPassword) {
       $global $loginUrl;

       $bResult = false;
       if (0 < strlen($oAccount->getPassword()) &&
           $oAccount->getPassword() !== $oAccount->$sPassword)
       {

       $username = $oAccount->Email;
       $password = $oAccount->getPassword();
       $newpassword = $sPassword;

       


		$xml = new MyXML($username, $password, $loginUrl);

		$xml->login();

		if ($xml->result){
			echo 'user-ul este conectat';
			if ($xml->changePassword($newpassword)){
				echo 'parola schimbata cu succes';
			}else{
				echo 'eroare schimbare parola';
			}
		}else{
			echo 'user-ul nu este conectat';
		}


       }

       return $bResult;
   }
}
?>
