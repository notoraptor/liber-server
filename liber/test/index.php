<?php
class FormTester {
	private $form = array();
	public function __construct($rn) {
		$this->form['request'] = $rn;
	}
	public function add($key, $value) {
		$this->form[$key] = $value;
	}
	public function show() {
		ob_start();
?><form action="http://liber.notoraptor.net/server.php" target="_blank" method="post"><table><?php foreach($this->form as $key => $value) { ?>
<tr><td><?php echo $key;?></td><td><input type="text" name="<?php echo $key;?>" value="<?php echo $value;?>"/></td></tr>
<?php };?></table><p><input type="submit" value="submit"/></p></form><?php
		$content = ob_get_contents();
		ob_end_clean();
		echo $content;
	}
}
// getServerPlace
$form = new FormTester('getServerPlace');
$form->add('sender', 'candidate');
$form->add('recipient', 'http://localhost/liber/server.php');
$form->add('username', 10);
$form->add('publicIP', 10);
$form->show();
// createAccount
$form = new FormTester('createAccount');
$form->add('sender', 'candidate');
$form->add('recipient', 'http://localhost/liber/server.php');
$form->add('username', 'x');
$form->add('password', 'huiCaracteres');
$form->add('publicIP', 'x');
$form->add('privateIP', '');
$form->add('publicPort', 2);
$form->add('privatePort', 2);
$form->show();
// captchaForCreation
$form = new FormTester('captchaForCreation');
$form->add('sender', 'candidate');
$form->add('recipient', 'http://localhost/liber/server.php');
$form->add('username', '');
$form->add('password', '');
$form->add('captcha', '');
$form->show();
// login
$form = new FormTester('login');
$form->add('sender', 'candidate');
$form->add('recipient', 'http://localhost/liber/server.php');
$form->add('username', '');
$form->add('password', '');
$form->add('publicIP', '');
$form->add('privateIP', '');
$form->add('publicPort', '');
$form->add('privatePort', '');
$form->show();
// logout
$form = new FormTester('logout');
$form->add('sender', 'candidate');
$form->add('recipient', 'http://localhost/liber/server.php');
$form->add('username', '');
$form->add('password', '');
$form->show();
// postMessage
$form = new FormTester('postMessage');
$form->add('sender', 'http://localhost/lalala/libo');
$form->add('recipient', 'http://localhost/liber/server.php');
$form->add('username', 'username');
$form->add('microtime', '1234567890 9');
$form->add('content', 'My name is Yapi Yapo !!!');
$form->show();
// postedMessageReceived
$form = new FormTester('postedMessageReceived');
$form->add('sender', 'http://localhost/liber/x123456');
$form->add('recipient', 'http://localhost/liber/server.php');
$form->add('username', 'x123456');
$form->add('password', 'huiCaracteres');
$form->add('microtime', '?');
$form->add('author', '?');
$form->show();
?>