<?php
ini_set("log_errors", 1);
ini_set("error_log", "internal/php-errors.log");
define('requestBody', 'requestBody');
require_once("utils.php");

try {
	//error_log("[INFO] Adresse de la demande: ip = " . $_SERVER['REMOTE_ADDR'] . "; port = " . $_SERVER['REMOTE_PORT']."");

	// Création de la base de données et des clés de chiffrement si nécessaire.
	$db = new LiberDB();

	// Récupérer le contenu du fichier téléversé si la requête est transmise par fichier.
	if(empty($_POST) && count($_FILES) == 1) {
		$keys = array_keys($_FILES);
		$key = $keys[0];
		parse_str(utils_get_uploaded($key), $_POST);
	}

	// Le contenu doit être chiffré.
	if(count($_POST) != 1 || !isset($_POST[requestBody]) || !$_POST[requestBody])
		throw RequestException::REQUEST_ERROR_NO_REQUEST();
	$pieces = explode(";", $_POST[requestBody]);
	if(count($pieces) != 2)
		throw RequestException::REQUEST_ERROR_FORMAT();
	$encryptedAESKey = utils_base64url_decode($pieces[0]);
	$AESKey = utils_decrypt_data($encryptedAESKey);
	if(!$AESKey)
		throw RequestException::REQUEST_ERROR_FORMAT();
	$aesPieces = explode(";", $AESKey);
	if(count($aesPieces) != 2)
		throw RequestException::REQUEST_ERROR_FORMAT();
	$encryptedAESContent = utils_base64url_decode($pieces[1]);
	$decrypted = utils_aes_decrypt(
		$encryptedAESContent,
		utils_base64url_decode($aesPieces[0]), // Clé AES.
		utils_base64url_decode($aesPieces[1])  // Vecteur d'initiation (IV).
	);
	$_POST = array();
	parse_str($decrypted, $_POST);

	if(count($_POST) == 0)
		throw RequestException::REQUEST_ERROR_NO_REQUEST();
	$requestName = utils_s_post('request', false);
	if(is_string($requestName)) $requestName = trim($requestName);
	if(!is_string($requestName) || strlen($requestName) < 1)
		throw RequestException::REQUEST_ERROR_NAME_MISSING();
	$requestClassname = strtoupper($requestName[0]) . substr($requestName, 1) . "Request";
	if(!class_exists($requestClassname) || !in_array('Request', class_parents($requestClassname)))
		throw RequestException::REQUEST_ERROR_UNKNOWN_REQUEST();
	$request = new $requestClassname($_POST);
	$response = $request->manage($db);
	$response->show();
} catch(RequestException $e) {
	$request = Response::fromException($e);
	$request->show();
} catch(Exception $e) {
	error_log("[EXCEPTION]: ".$e."\r\n");
	$request = Response::SERVER_INTERNAL_ERROR();
	$request->show();
}
?>