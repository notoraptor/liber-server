<?php
require_once('server_infos.php');
require_once('password.php');
require_once('encryption.php');
require_once('Request.php');
require_once('Response.php');
require_once('cool-php-captcha/captcha.php');
define('CONFIRMED' , 'CONFIRMED' );
define('TO_CONFIRM', 'TO_CONFIRM');
define('TO_DELETE' , 'TO_DELETE' );
define('sender'    , 'sender'    );
define('microtime' , 'microtime' );
class AccountInfo {
	public $password = null;
	public $public_ip = null;
	public $private_ip = null;
	public $public_port = null;
	public $private_port = null;
	public $account_state = null;
	public $captcha = null;
	public function read($key, $value) {
		$ok = true;
		switch($key) {
			case 'password':
				$this->password = $value;
				break;
			case 'public_ip':
				$this->public_ip = $value;
				break;
			case 'private_ip':
				$this->private_ip = $value;
				break;
			case 'public_port':
				$this->public_port = $value;
				break;
			case 'private_port':
				$this->private_port = $value;
				break;
			case 'account_state':
				$this->account_state = $value;
				break;
			case 'captcha':
				$this->captcha = $value;
				break;
			default:
				$ok = false;
				break;
		}
		return $ok;
	}
	public function is_valid() {
		return $this->password != null && strlen($this->password) > 0
			&& $this->account_state != null && strlen($this->account_state) > 0
			&& (
				$this->account_state == CONFIRMED
				|| (
					($this->account_state == TO_CONFIRM || $this->account_state == TO_DELETE)
					&& $this->captcha != null && strlen($this->captcha) > 0
				)
			);
	}
	public function save($filename) {
		$content = "";
		$content .= "password\t".$this->password."\r\n";
		$content .= "public_ip\t".$this->public_ip."\r\n";
		$content .= "private_ip\t".$this->private_ip."\r\n";
		$content .= "public_port\t".$this->public_port."\r\n";
		$content .= "private_port\t".$this->private_port."\r\n";
		$content .= "account_state\t".$this->account_state."\r\n";
		$content .= "captcha\t".$this->captcha."\r\n";
		file_put_contents($filename, $content);
	}
}
class Database {
	public function __construct(&$databasePath, &$accountTablePath) {
		$databasePath = server_dir().'/database';
		$accountTablePath = $databasePath.'/account';
		if(!file_exists($databasePath)) {
			if(!mkdir($databasePath))
				throw new Exception('Impossible de creer la base de données sur disque "database".');
		}
		if(!is_dir($databasePath))
			throw new Exception('Le chemin "database" ne mene pas vers un dossier.');
		if(!file_exists($accountTablePath)) {
			if(!mkdir($accountTablePath))
				throw new Exception('Impossible de creer la table de donnees sur disque "account".');
		}
		if(!is_dir($accountTablePath))
			throw new Exception('Le chemin "account" ne mene pas vers un dossier.');
		if(!utils_has_openssl_keys()) {
			if(!utils_generate_openssl_keys())
				throw new Exception("Impossible de générer les clés de chiffrement asymétriques avec OpenSSL.");
		}
	}
}
class LiberDB extends Database {
	private $database = null;
	private $account = null;
	public function __construct() {
		parent::__construct($this->database, $this->account);
		//$this->database = server_dir().'/database';
		//$this->account = $this->database.'/account';
	}
	private function encode_username($username) {
		return bin2hex($username);
	}
	private function get_account_path($username) {
		return $this->account.'/'.$this->encode_username($username);
	}
	private function get_public_key_file($username) {
		return $this->get_account_path($username).'/public.key';
	}
	private function get_account_file($username) {
		return $this->get_account_path($username).'/account.txt';
	}
	private function get_request_path($username) {
		return $this->get_account_path($username).'/request';
	}
	private function get_request_first_number_file($username) {
		return $this->get_request_path($username).'/first.txt';
	}
	private function get_request_last_number_file($username) {
		return $this->get_request_path($username).'/last.txt';
	}
	private function get_request_file($username, $number) {
		return $this->get_request_path($username).'/'.$number.'.request';
	}
	private function get_request_first_number($username) {
		return intval(file_get_contents($this->get_request_first_number_file($username)));
	}
	private function get_request_last_number($username) {
		return intval(file_get_contents($this->get_request_last_number_file($username)));
	}
	private function reset_request_numbers($username) {
		file_put_contents($this->get_request_first_number_file($username), "0");
		file_put_contents($this->get_request_last_number_file($username), "-1");
	}
	// Gestion de la base de données.
	private function get_message_path($username) {
		return $this->get_account_path($username).'/message';
	}
	private function get_message_microtime_path($username) {
		return $this->get_message_path($username).'/microtime';
	}
	private function get_message_first_number_file($username) {
		return $this->get_message_path($username).'/first.txt';
	}
	private function get_message_last_number_file($username) {
		return $this->get_message_path($username).'/last.txt';
	}
	private function get_message_file($username, $number) {
		return $this->get_message_path($username).'/'.$number.'.message';
	}
	private function get_message_first_number($username) {
		return intval(file_get_contents($this->get_message_first_number_file($username)));
	}
	private function get_message_last_number($username) {
		return intval(file_get_contents($this->get_message_last_number_file($username)));
	}
	private function reset_message_numbers($username) {
		file_put_contents($this->get_message_first_number_file($username), "0");
		file_put_contents($this->get_message_last_number_file($username), "-1");
	}
	private function get_account_data($username) {
		$account_file = $this->get_account_file($username);
		if(file_exists($account_file) && is_file($account_file)) {
			$file = @fopen($account_file, "r");
			$account = new AccountInfo();
			while(($line = fgets($file)) !== false) {
				$line =  trim($line);
				if(strlen($line) > 0) {
					$pieces = explode("\t", $line, 2);
					$key = $pieces[0];
					$value = count($pieces) == 2 ? $pieces[1] : null;
					if(!$account->read($key, $value)) {
						@fclose($file);
						return false;
					}
				}
			}
			@fclose($file);
			return $account->is_valid() ? $account : false;
		}
		return false;
	}
	private function get_account_infos($username, $password) {
		$account = $this->get_account_data($username);
		if($account) {
			$hash = $account->password;
			if(password_verify($password, $hash)) {
				return $account;
			}
		}
		return false;
	}
	public function get_user_infos($username) {
		$account = $this->get_account_data($username);
		return $account ?
			array(
				'private_ip' => $account->private_ip,
				'public_ip' => $account->public_ip,
				'private_port' => $account->private_port,
				'public_port' => $account->public_port
			) : false;
	}
	public function username_exists($username) {
		$accountPath = $this->get_account_path($username);
		return file_exists($accountPath) && is_dir($accountPath);
	}
	public function get_user_id($username) {
		return $this->username_exists($username) ? $this->encode_username($username) : false;
	}
	public function create_account($username, $password, $public_ip, $private_ip, $public_port, $private_port) {
		if($this->username_exists($username))
			throw new Exception("Tentative de creation d'un compte qui existe deja.");
		$account_path = $this->get_account_path($username);
		$request_path = $this->get_request_path($username);
		$message_path = $this->get_message_path($username);
		$message_microtime_path = $this->get_message_microtime_path($username);
		$account_file = $this->get_account_file($username);
		$hash = password_hash($password, PASSWORD_DEFAULT);
		$captcha = SimpleCaptcha::generate();
		if(!mkdir($account_path))
			throw new Exception('Impossible de creer un compte.');
		if(!mkdir($request_path))
			throw new Exception("Impossible de creer le dossier de reception des requetes d'un compte.");
		if(!mkdir($message_path))
			throw new Exception("Impossible de creer le dossier de reception des messages d'un compte.");
		if(!mkdir($message_microtime_path))
			throw new Exception("Impossible de creer le dossier de verification des horodatages des messages d'un compte.");
		$content = "";
		$content .= "password\t".$hash."\r\n";
		$content .= "public_ip\t".$public_ip."\r\n";
		$content .= "private_ip\t".$private_ip."\r\n";
		$content .= "public_port\t".$public_port."\r\n";
		$content .= "private_port\t".$private_port."\r\n";
		$content .= "account_state\t".TO_CONFIRM."\r\n";
		$content .= "captcha\t".$captcha['captcha']."\r\n";
		file_put_contents($account_file, $content);
		$this->reset_request_numbers($username);
		$this->reset_message_numbers($username);
		return $captcha;
	}
	public function get_account_state($username, $password) {
		$account = $this->get_account_infos($username, $password);
		return $account ? $account->account_state : false;
	}
	public function validate_account_creation($username, $password, $captcha) {
		$account = $this->get_account_infos($username, $password);
		if(!$account)
			throw new Exception('Impossible de trouver un compte qui devrait exister.');
		$oldCaptchaCode = $account->captcha;
		$captchaReturned = null;
		if($oldCaptchaCode === $captcha) {
			$account->account_state = CONFIRMED;
			$account->captcha = null;
			$account->save($this->get_account_file($username));
		} else {
			$captchaReturned = SimpleCaptcha::generate();
			$account->captcha = $captchaReturned['captcha'];
			$account->save($this->get_account_file($username));
		}
		return $captchaReturned;
	}
	public function validate_account_deletion($username, $password, $captcha) {
		$account = $this->get_account_infos($username, $password);
		if(!$account)
			throw new Exception('Impossible de trouver un compte qui devrait exister.');
		$oldCaptchaCode = $account->captcha;
		$captchaReturned = null;
		if($oldCaptchaCode === $captcha) {
			if(!delTree($this->get_account_path($username)))
				throw new Exception("Impossible de supprimer le dossier d'un compte.");
		} else {
			$captchaReturned = SimpleCaptcha::generate();
			$account->captcha = $captchaReturned['captcha'];
			$account->save($this->get_account_file($username));
		}
		return $captchaReturned;
	}
	public function login($username, $password, $public_ip, $private_ip, $public_port, $private_port) {
		$account = $this->get_account_infos($username, $password);
		$logged = false;
		if($account && $account->account_state == CONFIRMED) {
			$account->public_ip = $public_ip;
			$account->public_port = $public_port;
			$account->private_ip = $private_ip;
			$account->private_port = $private_port;
			$account->save($this->get_account_file($username));
			$logged = true;
		}
		return $logged;
	}
	public function logout($username, $password) {
		$account = $this->get_account_infos($username, $password);
		if($account) {
			$account->public_ip = null;
			$account->private_ip = null;
			$account->public_port = null;
			$account->private_port = null;
			$account->save($this->get_account_file($username));
		}
	}
	public function account_exists($username, $password) {
		$account = $this->get_account_infos($username, $password);
		return $account ? true : false;
	}
	public function delete_account($username, $password) {
		$captcha = null;
		$account = $this->get_account_infos($username, $password);
		if($account->account_state == TO_DELETE) {
			$captcha = SimpleCaptcha::generate($account->captcha);
		} else {
			$captcha = SimpleCaptcha::generate();
			$account->account_state = TO_DELETE;
			$account->captcha = $captcha['captcha'];
			$account->save($this->get_account_file($username));
		}
		return $captcha;
	}
	public function get_captcha_info($username, $password) {
		$account = $this->get_account_infos($username, $password);
		$captcha = SimpleCaptcha::generate($account->captcha);
		return array(
			'account_state' => $account->account_state,
			'image' => $captcha['image'],
			'type' => $captcha['type']
		);
	}
	public function post_later($username, $body) {
		$next = $this->get_request_last_number($username) + 1;
		$content = "";
		$content .= "requestBody\t$body\r\n";
		file_put_contents($this->get_request_file($username, $next), $content);
		file_put_contents($this->get_request_last_number_file($username), "".$next);
	}
	public function post_message($username, $body) {
		$next = $this->get_message_last_number($username) + 1;
		$content = "";
		$content .= "requestBody\t$body\r\n";
		file_put_contents($this->get_message_file($username, $next), $content);
		file_put_contents($this->get_message_last_number_file($username), "".$next);
	}
	public function get_next_posted($username, $password) {
		$account = $this->get_account_infos($username, $password);
		if($account) {
			$first = $this->get_request_first_number($username);
			$filename = $this->get_request_file($username, $first);
			if(file_exists($filename) && is_file($filename)) {
				$data = array();
				$file = @fopen($filename, "r");
				if($file !== false) {
					while(($line = fgets($file)) !== false) {
						$line = trim($line);
						if(strlen($line) > 0) {
							$pieces = explode("\t", $line, 2);
							$key = $pieces[0];
							$value = count($pieces) == 2 ? $pieces[1] : null;
							if($value != null) $data[$key] = $value;
						}
					}
					@fclose($file);
				}
				if(count($data) == 1 && isset($data['requestBody']) && $data['requestBody'] != null)
					return $data;
			}
			$last = $this->get_request_last_number($username);
			++$first;
			if($last - $first + 1 <= 0) {
				$this->reset_request_numbers($username);
			} else {
				file_put_contents($this->get_request_first_number_file($username), "".$first);
			}
			delTree($filename);
		}
		return null;
	}
	public function get_next_posted_message($username, $password) {
		$account = $this->get_account_infos($username, $password);
		if($account) {
			$first = $this->get_message_first_number($username);
			$filename = $this->get_message_file($username, $first);
			if(file_exists($filename) && is_file($filename)) {
				$data = array();
				$file = @fopen($filename, "r");
				if($file !== false) {
					while(($line = fgets($file)) !== false) {
						$line = trim($line);
						if(strlen($line) > 0) {
							$pieces = explode("\t", $line, 2);
							$key = $pieces[0];
							$value = count($pieces) == 2 ? $pieces[1] : null;
							if($value != null) $data[$key] = $value;
						}
					}
					@fclose($file);
				}
				if(count($data) == 1 && isset($data['requestBody']) && $data['requestBody'] != null)
					return $data;
			}
			$last = $this->get_message_last_number($username);
			++$first;
			if($last - $first + 1 <= 0) {
				$this->reset_message_numbers($username);
			} else {
				file_put_contents($this->get_message_first_number_file($username), "".$first);
			}
			delTree($filename);
		}
		return null;
	}
	public function delete_next_posted($username, $password) {
		$account = $this->get_account_infos($username, $password);
		if($account) {
			$first = $this->get_request_first_number($username);
			$filename = $this->get_request_file($username, $first);
			delTree($filename);
			++$first;
			$last = $this->get_request_last_number($username);
			if($last - $first + 1 <= 0) {
				$this->reset_request_numbers($username);
			} else {
				file_put_contents($this->get_request_first_number_file($username), "".$first);
			}
		}
	}
	public function delete_next_posted_message($username, $password) {
		$data = $this->get_next_posted_message($username, $password);
		if($data != null) {
			$first = $this->get_message_first_number($username);
			$filename = $this->get_message_file($username, $first);
			$last = $this->get_message_last_number($username);
			++$first;
			if($last - $first + 1 <= 0) {
				$this->reset_message_numbers($username);
			} else {
				file_put_contents($this->get_message_first_number_file($username), "".$first);
			}
			delTree($filename);
			return true;
		}
		return false;
	}
	public function set_public_key($username, $password, $publicKey) {
		$ok = false;
		$account = $this->get_account_infos($username, $password);
		if($account) {
			$ok = file_put_contents($this->get_public_key_file($username), utils_base64url_decode($publicKey));
		}
		return $ok;
	}
	public function get_public_key($username) {
		$account = $this->get_account_data($username);
		if($account) {
			$pkf = $this->get_public_key_file($username);
			if(file_exists($pkf) && is_file($pkf))
				return utils_base64url_encode(file_get_contents($pkf));
		}
		return null;
	}
}

function delTree($dir) {
	if(!file_exists($dir))
		return true;
	if(is_file($dir))
		return unlink($dir);
	$files = array_diff(scandir($dir), array('.','..'));
	foreach ($files as $file) {
		(is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
	}
	return rmdir($dir);
} 
function utils_check_url($url) {
	return preg_match('#^[A-Za-z]+\://[A-Za-z0-9/\._\$\#-]+$#', $url);
}
function utils_check_number_string($str) {
	return preg_match('#^[0-9]+$#', $str);
}
function utils_redirection($chemin) {
	header('Location: '.$chemin);
}
function utils_unescape($texte) {
	$texte = str_replace("\\\"","\"",$texte);
	$texte = str_replace("\\'","'",$texte);
	return $texte;
}
function utils_unescape_s_post($texte) {
	$texte = utils_unescape($texte);
	$texte = str_replace("\\\\","\\",$texte);
	return $texte;
}
function utils_has_s_post($key) {
	return isset($_POST[$key]);
}
function utils_s_post($key, $default = '') {
	return isset($_POST[$key]) ? $_POST[$key] : $default;
}
function utils_get_post($name, $alt = '') {
	return trim(utils_unescape_s_post(strip_tags(utils_s_post($name, $alt))));
}
function utils_get_uploaded($name) {
	// Testons si le fichier a bien été envoyé et s'il n'y a pas d'erreur
	if (isset($_FILES[$name]) AND $_FILES[$name]['error'] == UPLOAD_ERR_OK) {
		// Testons si le fichier n'est pas trop gros.
		$tailleMaximale = 50*1024*1024; // en Mo.
		if ($_FILES[$name]['size'] <= $tailleMaximale) {
			// Testons si l'extension est autorisée
			$infosfichier = pathinfo($_FILES[$name]['name']);
			$extension_upload = "";
			if(isset($infosfichier['extension'])) $extension_upload = strtolower($infosfichier['extension']);
			$extensions_autorisees = array('tmp');
			if (in_array($extension_upload, $extensions_autorisees)) {
				$content = file_get_contents($_FILES[$name]['tmp_name']);
				unlink($_FILES[$name]['tmp_name']);
				return $content;
			} else throw new Exception( "Extension non autorisée (.".$extension_upload."). ".( empty($extensions_autorisees) ? "" : "Extensions autorisées: .".implode(', .',$extensions_autorisees) ) );
		} else throw new Exception("Votre fichier est trop gros (".($tailleMaximale/1024/1024)." Mo au maximum).");
	} else throw new Exception("Erreur ".$_FILES[$name]['error']." lors de l'envoi de fichier : champ inexistant.");
	return false;
}
function utils_base64url_decode($data) {
    return base64_decode(str_replace(array('-', '_'), array('+', '/'), $data));
}
function utils_base64url_encode($data) {
	return str_replace(array('+', '/'), array('-', '_'), base64_encode($data));
}
?>