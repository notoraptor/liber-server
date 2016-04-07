<?php
require_once('server_infos.php');
require_once('vendor/autoload.php');
function utils_public_key_filename() {
	return server_dir()."/public.key";
}
function utils_private_key_filename() {
	return server_dir()."/internal/private.key";
}
function utils_has_openssl_keys() {
	$public = utils_public_key_filename();
	$private = utils_private_key_filename();
	return file_exists($public) && is_file($public) && file_exists($private) && is_file($private);
}
function utils_generate_openssl_keys() {
	if(!utils_has_openssl_keys()) {
		$rsa = \phpseclib\Crypt\RSA::createKey(2048);
		// save private key and public key to disk.
		file_put_contents(utils_public_key_filename(), $rsa['publickey']);
		file_put_contents(utils_private_key_filename(), $rsa['privatekey']);
	}
	return true;
}
function utils_encrypt_data($data) {
	$encrypted = false;
	$pubKey = file_get_contents(utils_public_key_filename());
	if($pubKey) {
		$object = new \phpseclib\Crypt\RSA();
		if($object->load($pubKey)) {
			$encrypted = $object->encrypt($data, \phpseclib\Crypt\RSA::PADDING_PKCS1);
		} else error_log('Impossible de charger la cle publique.');
	} else error_log('no public key.');
	return $encrypted;
}
function utils_decrypt_data($encrypted) {
	$decrypted = false;
	$privKey = file_get_contents(utils_private_key_filename());
	if($privKey) {
		$object = new \phpseclib\Crypt\RSA();
		if($object->load($privKey)) {
			$decrypted = $object->decrypt($encrypted, \phpseclib\Crypt\RSA::PADDING_PKCS1);
		} else error_log('Impossible de charger la cle privee.');
	} else error_log('no private key.');
	return $decrypted;
}
function utils_aes_decrypt($encryptedAESContent, $AESKey, $iv) {
	$decrypted = false;
	$aes = new \phpseclib\Crypt\AES(\phpseclib\Crypt\AES::MODE_CBC);
	$aes->setKey($AESKey);
	$aes->setIV($iv);
	$decrypted = $aes->decrypt($encryptedAESContent);
	return $decrypted;
}
?>