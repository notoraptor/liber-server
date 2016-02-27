<?php
ini_set("log_errors", 1);
ini_set("error_log", "internal/php-errors.log");
require_once("utils.php");
try {
	//error_log("[INFO] Adresse de la demande: ip = " . $_SERVER['REMOTE_ADDR'] . "; port = " . $_SERVER['REMOTE_PORT']."");
	if(count($_POST) == 0)
		throw RequestException::REQUEST_ERROR_NO_REQUEST();
	$requestName = utils_s_post('request', false);
	if(is_string($requestName)) $requestName = trim($requestName);
	if(!is_string($requestName) || strlen($requestName) < 1)
		throw RequestException::REQUEST_ERROR_NAME_MISSING();
	$requestClassname = strtoupper($requestName[0]) . substr($requestName, 1) . "Request";
	if(!class_exists($requestClassname) || !in_array('Request', class_parents($requestClassname)))
		throw RequestException::REQUEST_ERROR_UNKNOWN_REQUEST();
	$db = new LiberDB();
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