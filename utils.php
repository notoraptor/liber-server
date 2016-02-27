<?php
require_once('server_infos.php');
require_once('Request.php');
require_once('Response.php');
require_once('cool-php-captcha/captcha.php');
class Database {
	private $bdd = null;
	public function __construct() {
		try {
			//$this->verifier_existence_bdd();
			$pdo_options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
			$this->bdd = new PDO('mysql:host='.NOM_HOTE.';dbname='.NOM_BASE, NOM_UTILISATEUR, MOT_DE_PASSE, $pdo_options);
			$this->verifier_existence_tables();
		} catch(Exception $e) {
			throw new Exception('Erreur MySQL: ' . $e);
		}
	}
	protected function secure_query($requete, $parametres = array()) {
		try {
			$execution = $this->bdd->prepare($requete);
			$execution->execute($parametres);
			$donnees = array();
			while(($ligne = $execution->fetch())) {
				$donnees_ligne = array();
				foreach($ligne as $key => $value) {
					if(is_string($value)) $value = utils_unescape($value);	//revoir eventuellement.
					if(!is_int($key)) $donnees_ligne[$key] = $value;
				}
				$donnees[] = $donnees_ligne;
			}
			$execution->closeCursor();
			return $donnees;
		} catch(Exception $e) {
			throw new Exception('Erreur : requ&ecirc;te de s&eacute;lection : ' . $e);
		}
	}
	protected function secure_modif($requete, $parametres = array()) {
		try {
			$execution = $this->bdd->prepare($requete);
			$execution->execute($parametres);
		} catch(Exception $e) {
			throw new Exception('Erreur : requ&ecirc;te de modification: ' . $e);
		}
	}
	// (Pour le moment, NE PAS UTILISER LA FONCTION SUIVANTE DANS UN ENVIRONNEMENT DE PRODUCTION).
	private function verifier_existence_bdd() {
		// Connect to MySQL
		$link = mysqli_connect(NOM_HOTE, NOM_UTILISATEUR, MOT_DE_PASSE);
		if(!$link) {
			throw new Exception('Could not connect: ' . mysqli_error());
		}
		// Make my_db the current database
		$db_selected = mysqli_select_db($link, NOM_BASE);
		if(!$db_selected) {
			// If we couldn't, then it either doesn't exist, or we can't see it.
			//$sql = 'CREATE DATABASE '.NOM_BASE.' DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci;'; // avec utf-8 (actuellement problématique).
			$sql = 'CREATE DATABASE '.NOM_BASE.';';
			if(!mysqli_query($link, $sql)) {
				throw new Exception('Error creating database: ' . mysql_error());
			}
		}
		mysqli_close($link);
	}
	private function verifier_existence_tables() {
		$requetes = array(
			"CREATE TABLE IF NOT EXISTS `".DB_PREFIX."account` ("
				."`account_id` INTEGER NOT NULL AUTO_INCREMENT,"
				."`username` VARCHAR(512) NOT NULL UNIQUE,"
				."`password` text NOT NULL,"
				."`private_ip` varchar(512) DEFAULT NULL,"
				."`public_ip` varchar(512) DEFAULT NULL,"
				."`private_port` INTEGER DEFAULT NULL,"
				."`public_port` INTEGER DEFAULT NULL,"
				."`account_state` enum('TO_DELETE', 'TO_CONFIRM', 'CONFIRMED') NOT NULL DEFAULT 'TO_CONFIRM',"
				."`captcha` varchar(512) DEFAULT NULL,"
				."PRIMARY KEY (`account_id`)"
			.")",
			"CREATE TABLE IF NOT EXISTS `".DB_PREFIX."message` ("
				."`message_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,"
				."`account_id` INTEGER NOT NULL,"
				."`sender` VARCHAR(1024) NOT NULL,"
				."`microtime` VARCHAR(512) NOT NULL,"
				."`content` text NOT NULL,"
				."PRIMARY KEY (`message_id`),"
				."FOREIGN KEY (`account_id`) REFERENCES `".DB_PREFIX."account` (`account_id`) ON DELETE CASCADE"
			.")"
		);
		$compte = count($requetes);
		for($i = 0; $i < $compte; ++$i) $this->secure_modif($requetes[$i]);
		/*
		$requete = file_get_contents("internal/liber.sql");
		$requete = str_replace('`account`', '`'.DB_PREFIX.'account`', $requete);
		$requete = str_replace('`message`', '`'.DB_PREFIX.'message`', $requete);
		$this->secure_modif($requete);
		*/
		$this->alterer_tables();
	}
	private function alterer_tables() {
		$alterations = $this->alterations();
		$verifications = $this->verifications();
		$compte = count($alterations);
		for($i = 0; $i < $compte; ++$i) {
			$test = false;
			if($verifications[$i])
				if(is_string($verifications[$i])) {
					$donnees = $this->secure_query($verifications[$i]);
					$test = (count($donnees) > 0);
				} else $test = $verifications[$i];
			if(!$test) $this->secure_modif($alterations[$i]);
		}
	}
	// Fonction surchargeables.
	protected function alterations() {
		return array(
			//'ALTER TABLE '.DB_PREFIX.'produits ADD prix_de_base DECIMAL(14,2) DEFAULT 0',
		);
	}
	protected function verifications() {
		return array(
			//'SHOW COLUMNS FROM '.DB_PREFIX.'produits LIKE \'prix_de_base\'',
		);
	}
}
class LiberDB extends Database {
	private $account = null;
	private $message = null;
	public function __construct() {
		parent::__construct();
		$this->account = DB_PREFIX.'account';
		$this->message = DB_PREFIX.'message';
	}
	public function get_user_infos($username) {
		$donnees = $this->secure_query('SELECT private_ip, public_ip, private_port, public_port FROM '.$this->account.' WHERE username = ?', array($username));
		return count($donnees) == 1 ? $donnees[0] : false;
	}
	public function username_exists($username) {
		$donnees = $this->secure_query('SELECT COUNT(username) AS count FROM '.$this->account.' WHERE username = ?', array($username));
		//error_log(print_r(array($username), true));
		//error_log(print_r($donnees, true));
		return intval($donnees[0]['count']) != 0;
	}
	public function get_user_id($username) {
		$donnees = $this->secure_query('SELECT account_id FROM '.$this->account.' WHERE username = ?', array($username));
		return count($donnees) == 1 ? $donnees[0]['account_id'] : false;
	}
	public function create_account($username, $password, $public_ip, $private_ip, $public_port, $private_port) {
		//$captcha = mt_rand(10000, mt_getrandmax());
		$captcha = SimpleCaptcha::generate();
		$this->secure_modif('INSERT INTO '.$this->account.' (username, password, public_ip, private_ip, public_port, private_port, captcha) VALUES (?,?,?,?,?,?,?)', array(
			$username,
			md5($password),
			$public_ip,
			$private_ip,
			$public_port,
			$private_port,
			$captcha['captcha']
		));
		return $captcha;
	}
	public function get_account_state($username, $password) {
		$donnees = $this->secure_query('SELECT account_state FROM '.$this->account.' WHERE username = ? AND password = ?', array($username, md5($password)));
		return count($donnees) == 1 ? $donnees[0]['account_state'] : false;
	}
	public function validate_account_creation($username, $password, $captcha) {
		$donnees = $this->secure_query('SELECT captcha FROM '.$this->account.' WHERE username = ? AND password = ?', array(
			$username, md5($password)
		));
		if(count($donnees) != 1) throw new Exception('Unable to find an account that should exists.');
		$oldCaptchaCode = $donnees[0]['captcha'];
		$captchaReturned = null;
		if($oldCaptchaCode === $captcha) {
			$this->secure_modif('UPDATE '.$this->account.' SET account_state = ?, captcha = NULL WHERE username = ? AND password = ?', array(
				'CONFIRMED', $username, md5($password)
			));
		} else {
			$captchaReturned = SimpleCaptcha::generate();
			$this->secure_modif('UPDATE '.$this->account.' SET captcha = ? WHERE username = ? AND password = ?', array(
				$captchaReturned['captcha'], $username, md5($password)
			));
		}
		return $captchaReturned;
	}
	public function validate_account_deletion($username, $password, $captcha) {
		$donnees = $this->secure_query('SELECT captcha FROM '.$this->account.' WHERE username = ? AND password = ?', array(
			$username, md5($password)
		));
		if(count($donnees) != 1) throw new Exception('Unable to find an account that should exists.');
		$oldCaptchaCode = $donnees[0]['captcha'];
		$captchaReturned = null;
		if($oldCaptchaCode === $captcha) {
			$this->secure_modif('DELETE FROM '.$this->account.' WHERE username = ? AND password = ?', array(
				$username, md5($password)
			));
		} else {
			$captchaReturned = SimpleCaptcha::generate();
			$this->secure_modif('UPDATE '.$this->account.' SET captcha = ? WHERE username = ? AND password = ?', array(
				$captchaReturned['captcha'], $username, md5($password)
			));
		}
		return $captchaReturned;
	}
	public function login($username, $password, $public_ip, $private_ip, $public_port, $private_port) {
		$donnees = $this->secure_query('SELECT COUNT(account_id) AS count FROM '.$this->account.' WHERE username = ? AND password = ? and account_state = ?', array($username, md5($password), 'CONFIRMED'));
		$logged = false;
		if($donnees[0]['count'] === '1') {
			$this->secure_modif(
				'UPDATE '.$this->account.' SET public_ip = ?, private_ip = ?, public_port = ?, private_port = ? WHERE username = ? AND password = ?',
				array($public_ip, $private_ip, $public_port, $private_port, $username, md5($password))
			);
			$logged = true;
		}
		return $logged;
	}
	public function logout($username, $password) {
		$this->secure_modif('UPDATE '.$this->account.' SET public_ip = NULL, private_ip = NULL, public_port = NULL, private_port = NULL WHERE username = ? AND password = ?', array($username, md5($password)));
	}
	public function account_exists($username, $password) {
		$donnees = $this->secure_query("SELECT count(account_id) AS count FROM ".$this->account." WHERE username = ? AND password = ?", array($username, md5($password)));
		return $donnees[0]['count'] === '1';
	}
	public function delete_account($username, $password) {
		$captcha = null;
		$donnees = $this->secure_query('SELECT account_state, captcha FROM '.$this->account.' WHERE username = ? AND password = ?', array($username, md5($password)));
		if($donnees[0]['account_state'] == 'TO_DELETE') {
			$captcha = SimpleCaptcha::generate($donnees[0]['captcha']);
		} else {
			$captcha = SimpleCaptcha::generate();
			$this->secure_modif('UPDATE '.$this->account.' SET account_state = ?, captcha = ? WHERE username = ? AND password = ?', array('TO_DELETE', $captcha['captcha'], $username, md5($password)));
		}
		return $captcha;
	}
	public function get_captcha_info($username, $password) {
		$donnees = $this->secure_query('SELECT account_state, captcha FROM '.$this->account.' WHERE username = ? AND password = ?', array($username, md5($password)));
		$captcha = SimpleCaptcha::generate($donnees[0]['captcha']);
		return array(
			'account_state' => $donnees[0]['account_state'],
			'image' => $captcha['image'],
			'type' => $captcha['type']
		);
	}
	public function post_message($sender, $account_id, $microtime, $content) {
		$donnees = $this->secure_query('SELECT COUNT(message_id) AS count FROM '.$this->message.' WHERE sender = ? AND account_id = ? AND microtime = ?', array($sender, $account_id, $microtime));
		if($donnees[0]['count'] !== '0') return false;
		$this->secure_modif('INSERT INTO '.$this->message.' (sender, account_id, microtime, content) VALUES (?,?,?,?)', array($sender, $account_id, $microtime, $content));
		return true;
	}
	public function check_posted_message($sender, $account_id, $microtime) {
		$donnees = $this->secure_query('SELECT COUNT(message_id) AS count FROM '.$this->message.' WHERE sender = ? AND account_id = ? AND microtime = ?', array($sender, $account_id, $microtime));
		return ($donnees[0]['count'] !== '0');
	}
	public function get_next_posted_message($username, $password) {
		$donnees = $this->secure_query('SELECT m.sender AS sender, m.microtime AS microtime, m.content AS content FROM '.$this->message.' AS m JOIN '.$this->account.' AS a ON m.account_id = a.account_id WHERE a.username = ? AND a.password = ? ORDER BY m.message_id ASC LIMIT 0,1', array($username, md5($password)));
		return count($donnees) == 1 ? $donnees[0] : null;
	}
	public function delete_posted_message($username, $password, $sender, $microtime) {
		$donnees = $this->secure_query('SELECT account_id FROM '.$this->account.' WHERE username = ? AND password = ?', array($username, md5($password)));
		$account_id = $donnees[0]['account_id'];
		$donnees = $this->secure_query('SELECT COUNT(message_id) AS count FROM '.$this->message.' WHERE account_id = ? AND sender = ? AND microtime = ?', array($account_id, $sender, $microtime));
		if($donnees[0]['count'] !== '1') return false;
		$this->secure_modif('DELETE FROM '.$this->message.' WHERE account_id = ? AND sender = ? AND microtime = ?', array($account_id, $sender, $microtime));
		return true;
	}
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
?>