<?php
/* LISTE DES REQUÊTES
---- from candidate -------------
	* getServerPlace
	* createAccount
	* captchaForCreation
	* login
---- from account ---------------
	* logout
	* deleteAccount
	* getCaptchaImageForCreation
	* getCaptchaImageForDeletion
	* captchaForDeletion
	* getNextPostedMessage
	* postedMessageReceived
---- from liberaddress ----------
	* postMessage
*/
abstract class Request {
	private $data = array();
	public function __construct($data, $needed = array()) {
		if(is_array($data)) {
			foreach($data as $key => $value) {
				if(!is_string($key))
					throw new Exception('ERROR_FIELD_NAME');
				if(!is_string($value) && !is_numeric($value))
					throw new Exception('ERROR_VALUE_FORMAT');
				$key = trim($key);
				if(is_string($value)) $value = trim($value);
				$this->data[$key] = $value;
			}
		} else throw new Exception("Unable to instanciate request: neither string nor array passed to constructor.");
		$this->check_request_name();
		$this->check_recipient();
		$this->check_sender();
		$this->check_needed();
	}
	public function add($key, $value) {
		$this->data[$key] = $value;
	}
	public function has($key) {
		return isset($this->data[$key]);
	}
	public function show() {
		foreach($this->data as $key => $value) echo "$key\t$value\r\n";
		echo "end\r\n";
	}
	public function __get($key) {
		return isset($this->data[$key]) ? $this->data[$key] : null;
	}
	public function __toString() {
		$str = '';
		foreach($this->data as $key => $value) $str .= "$key\t$value\r\n";
		$str .= "end\r\n";
		return $str;
	}
	public function check_request_name() {
		if(!isset($this->data['request']))
			throw RequestException::REQUEST_ERROR_NAME_MISSING();
		if($this->data['request'] != $this->get_valid_name())
			throw RequestException::ERROR_REQUEST_NAME();
	}
	public function check_sender() {
		if(!isset($this->data['sender']))
			throw RequestException::ERROR_SENDER_MISSING();
	}
	public function check_recipient() {
		if(!isset($this->data['recipient']))
			throw RequestException::ERROR_RECIPIENT_MISSING();
		if($this->data['recipient'] != server_http() . '/server.php')
			throw RequestException::ERROR_RECIPIENT();
	}
	public function check_needed() {
		$needed = $this->get_needed();
		if(is_array($needed)) foreach($needed as $field) if(!isset($this->data[$field]))
			throw RequestException::ERROR_FIELD_MISSING($field);
	}
	 public function get_valid_name() {
		$classname = get_class($this);
		if(strpos($classname, 'Request') == 0) return $classname;
		$rname = strtolower($classname[0]).substr($classname, 1, strlen($classname) - strlen('Request') - 1);
		return $rname;
	}
	// À surcharger.
	         public function get_needed() {return false;}
	abstract public function manage(LiberDB $db);
}
abstract class RequestFromCandidate extends Request {
	public function check_sender() {
		parent::check_sender();
		if($this->sender != 'candidate')
			throw RequestException::ERROR_SENDER();
	}
}
abstract class RequestFromLiberaddress extends Request {
	public function check_sender() {
		parent::check_sender();
		if(!utils_check_url($this->sender))
			throw RequestException::ERROR_SENDER();
	}
}
abstract class RequestFromAccount extends RequestFromLiberaddress {
	public function check_sender() {
		parent::check_sender();
		if($this->username === null)
			throw RequestException::ERROR_USERNAME_MISSING();
		if($this->password === null)
			throw RequestException::ERROR_PASSWORD_MISSING();
		if($this->sender != server_http().'/'.$this->username)
			throw RequestException::ERROR_ACCOUNT_REQUEST_FORMAT();
	}
	public function manage(LiberDB $db) {
		if(!$db->account_exists($this->username, $this->password))
			return Response::ERROR_ACCOUNT();
		return $this->manageAccount($db);
	}
	abstract public function manageAccount(LiberDB $db);
}
// Requêtes d'un candidat.
class GetServerPlaceRequest extends RequestFromCandidate {
	public function get_needed() {
		return array('username', 'publicIP');
	}
	public function manage(LiberDB $db) {
		$user = $db->get_user_infos($this->username);
		if(!$user)
			return Response::ERROR_USERNAME();
		$response = Response::OK();
		$ip = null;
		$port = null;
		if($this->publicIP == $user['public_ip']) {
			$ip = $user['private_ip'];
			$port = $user['private_port'];
		} else {
			$ip = $user['public_ip'];
			$port = $user['public_port'];
		}
		$response->add('ip', $ip);
		$response->add('port', $port);
		return $response;
	}
}
class GetPublicKeyRequest extends RequestFromCandidate {
	public function get_needed() {
		return array('username');
	}
	public function manage(LiberDB $db) {
		$publicKey = $db->get_public_key($this->username);
		if(!$publicKey) return Response::NO_KEY();
		$response = Response::OK();
		$response->add('publicKey', $publicKey);
		return $response;
	}
}
class CreateAccountRequest extends RequestFromCandidate {
	public function get_needed() {
		return array('username', 'password', 'publicIP', 'privateIP', 'publicPort', 'privatePort');
	}
	public function manage(LiberDB $db) {
		//error_log(print_r($this, true));
		if($db->username_exists($this->username))
			return Response::ERROR_USERNAME_TAKEN();
		if(strlen($this->password) < 8)
			return Response::ERROR_PASSWORD();
		if($this->publicIP == '')
			return Response::ERROR_PUBLIC_IP();
		if($this->privateIP == '')
			return Response::ERROR_PRIVATE_IP();
		if($this->publicIP == $this->privateIP)
			return Response::ERROR_SAME_IPS();
		$publicPort = intval($this->publicPort);
		$privatePort = intval($this->privatePort);
		if($publicPort <= 0) return Response::ERROR_PUBLIC_PORT();
		if($privatePort <= 0) return Response::ERROR_PRIVATE_PORT();
		$captcha = $db->create_account(
			$this->username, $this->password, $this->publicIP, $this->privateIP, $publicPort, $privatePort
		);
		$response = Response::OK();
		$response->add('captchaImage', $captcha['image']);
		$response->add('imageType', $captcha['type']);
		return $response;
	}
}
class LoginRequest extends RequestFromCandidate {
	public function get_needed() {
		return array('username', 'password', 'publicIP', 'privateIP', 'publicPort', 'privatePort');
	}
	public function manage(LiberDB $db) {
		$account_state = $db->get_account_state($this->username, $this->password);
		if(!$account_state)
			return Response::ERROR_ACCOUNT();
		if($account_state == 'TO_CONFIRM')
			return Response::ERROR_ACCOUNT_TO_CONFIRM();
		if($account_state == 'TO_DELETE')
			return Response::ERROR_ACCOUNT_TO_DELETE();
		if($this->publicIP == '')
			return Response::ERROR_PUBLIC_IP();
		if($this->privateIP == '')
			return Response::ERROR_PRIVATE_IP();
		if($this->publicIP == $this->privateIP)
			return Response::ERROR_SAME_IPS();
		$publicPort = intval($this->publicPort);
		$privatePort = intval($this->privatePort);
		if($publicPort <= 0) return Response::ERROR_PUBLIC_PORT();
		if($privatePort <= 0) return Response::ERROR_PRIVATE_PORT();
		$logged = $db->login($this->username, $this->password, $this->publicIP, $this->privateIP, $publicPort, $privatePort);
		return $logged ? Response::OK() : Response::ERROR_ACCOUNT();
	}
}
// Requête d'un utilisateur inscrit sur ce liberserveur.
class CaptchaForCreationRequest extends RequestFromAccount {
	public function get_needed() {
		return array('captcha');
	}
	public function manageAccount(LiberDB $db) {
		$account_state = $db->get_account_state($this->username, $this->password);
		if($account_state != 'TO_CONFIRM')
			return Response::ERROR_VALIDATION();
		$newCaptcha = $db->validate_account_creation($this->username, $this->password, $this->captcha);
		if($newCaptcha != null) {
			$response = Response::ERROR_CAPTCHA();
			$response->add('captchaImage', $newCaptcha['image']);
			$response->add('imageType', $newCaptcha['type']);
			return $response;
		}
		return Response::OK();
	}
}
class LogoutRequest extends RequestFromAccount {
	public function manageAccount(LiberDB $db) {
		$db->logout($this->username, $this->password);
		return Response::OK();
	}
}
class DeleteAccountRequest extends RequestFromAccount {
	public function manageAccount(LiberDB $db) {
		$captcha = $db->delete_account($this->username, $this->password);
		$response = Response::OK();
		$response->add('captchaImage', $captcha['image']);
		$response->add('imageType', $captcha['type']);
		return $response;
	}
}
class GetCaptchaImageForCreationRequest extends RequestFromAccount {
	public function manageAccount(LiberDB $db) {
		$infos = $db->get_captcha_info($this->username, $this->password);
		if($infos['account_state'] != 'TO_CONFIRM')
			return Response::ERROR_CAPTCHA_IMAGE();
		$response = Response::OK();
		$response->add('captchaImage', $infos['image']);
		$response->add('imageType', $infos['type']);
		return $response;
	}
}
class GetCaptchaImageForDeletionRequest extends RequestFromAccount {
	public function manageAccount(LiberDB $db) {
		$infos = $db->get_captcha_info($this->username, $this->password);
		if($infos['account_state'] != 'TO_DELETE')
			return Response::ERROR_CAPTCHA_IMAGE();
		$response = Response::OK();
		$response->add('captchaImage', $infos['image']);
		$response->add('imageType', $infos['type']);
		return $response;
	}
}
class CaptchaForDeletionRequest extends RequestFromAccount {
	public function get_needed() {
		return array('captcha');
	}
	public function manageAccount(LiberDB $db) {
		$account_state = $db->get_account_state($this->username, $this->password);
		if($account_state != 'TO_DELETE')
			return Response::ERROR_DELETION();
		$newCaptcha = $db->validate_account_deletion($this->username, $this->password, $this->captcha);
		if($newCaptcha != null) {
			$response = Response::ERROR_CAPTCHA();
			$response->add('captchaImage', $newCaptcha['image']);
			$response->add('imageType', $newCaptcha['type']);
			return $response;
		}
		return Response::OK();
	}
}
class NextPostedMessageReceivedRequest extends RequestFromAccount {
	public function manageAccount(LiberDB $db) {
		$db->delete_next_posted_message($this->username, $this->password);
		return Response::OK();
	}
}
class GetNextPostedRequest extends RequestFromAccount {
	public function manageAccount(LiberDB $db) {
		$nextMessage = $db->get_next_posted($this->username, $this->password);
		if($nextMessage == null) return Response::NO_POSTED();
		$response = Response::OK();
		$response->add('requestBody', $nextMessage['requestBody']);
		return $response;
	}
}
class GetNextPostedMessageRequest extends RequestFromAccount {
	public function manageAccount(LiberDB $db) {
		$nextMessage = $db->get_next_posted_message($this->username, $this->password);
		if($nextMessage == null) return Response::NO_MESSAGE();
		$response = Response::OK();
		$response->add('requestBody', $nextMessage['requestBody']);
		return $response;
	}
}
class NextPostedReceivedRequest extends RequestFromAccount {
	public function manageAccount(LiberDB $db) {
		$db->delete_next_posted($this->username, $this->password);
		return Response::OK();
	}
}
class SetPublicKeyRequest extends RequestFromAccount {
	public function get_needed() {
		return array('publicKey');
	}
	public function manageAccount(LiberDB $db) {
		$ok = $db->set_public_key($this->username, $this->password, $this->publicKey);
		return $ok ? Response::OK() : Response::ERROR_KEY();
	}
}
// Requête d'une liberadresse en genéral.
class PostLaterRequest extends RequestFromLiberaddress {
	public function get_needed() {
		return array('username', 'body');
	}
	public function manage(LiberDB $db) {
		if(!$db->username_exists($this->username))
			return Response::ERROR_USERNAME();
		if($this->body == '')
			return Response::ERROR_BODY();
		$db->post_later($this->username, $this->body); // revoir
		return Response::OK();
	}
}
class PostMessageRequest extends RequestFromLiberaddress {
	public function get_needed() {
		return array('username', 'body');
	}
	public function manage(LiberDB $db) {
		if(!$db->username_exists($this->username))
			return Response::ERROR_USERNAME();
		if($this->body == '')
			return Response::ERROR_BODY();
		$db->post_message($this->username, $this->body); // revoir
		return Response::OK();
	}
}
// CheckPostedMessage

?>