<?php
class RequestException extends Exception {
	public function __construct($errorCode) {
		parent::__construct($errorCode);
	}
	static public function REQUEST_ERROR_FORMAT          (){return new RequestException('REQUEST_ERROR_FORMAT');}
	static public function REQUEST_ERROR_NO_ENCRYPTED    (){return new RequestException('REQUEST_ERROR_NO_ENCRYPTED');}
	static public function REQUEST_ERROR_NAME_MISSING    (){return new RequestException('REQUEST_ERROR_NAME_MISSING');}
	static public function REQUEST_ERROR_NO_REQUEST      (){return new RequestException('REQUEST_ERROR_NO_REQUEST');}
	static public function REQUEST_ERROR_UNKNOWN_REQUEST (){return new RequestException('REQUEST_ERROR_UNKNOWN_REQUEST');}
	static public function ERROR_ACCOUNT_REQUEST_FORMAT  (){return new RequestException('ERROR_ACCOUNT_REQUEST_FORMAT');}
	static public function ERROR_PASSWORD_MISSING        (){return new RequestException('ERROR_PASSWORD_MISSING');}
	static public function ERROR_RECIPIENT               (){return new RequestException('ERROR_RECIPIENT');}
	static public function ERROR_RECIPIENT_MISSING       (){return new RequestException('ERROR_RECIPIENT_MISSING');}
	static public function ERROR_REQUEST_NAME            (){return new RequestException('ERROR_REQUEST_NAME');}
	static public function ERROR_SENDER                  (){return new RequestException('ERROR_SENDER');}
	static public function ERROR_SENDER_MISSING          (){return new RequestException('ERROR_SENDER_MISSING');}
	static public function ERROR_USERNAME_MISSING        (){return new RequestException('ERROR_USERNAME_MISSING');}
	static public function ERROR_FIELD_MISSING     ($field){return new RequestException('ERROR_FIELD_MISSING('.$field.')');}
}
class Response {
	private $data = array();
	private function __construct($status = 'OK') {
		$this->data["status"] = $status;
		//$this->data['sender'] = server_http() . "/server.php";
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
	static public function OK                              () {return new Response();}
	static public function SERVER_INTERNAL_ERROR           () {return new Response("SERVER_ERROR_INTERNAL_ERROR");}
	static public function ERROR_ACCOUNT                   () {return new Response('ERROR_ACCOUNT');              }
	static public function ERROR_ACCOUNT_TO_CONFIRM        () {return new Response('ERROR_ACCOUNT_TO_CONFIRM');   }
	static public function ERROR_ACCOUNT_TO_DELETE         () {return new Response('ERROR_ACCOUNT_TO_DELETE');    }
	static public function ERROR_AUTHOR_FORMAT             () {return new Response('ERROR_AUTHOR_FORMAT');        }
	static public function ERROR_CAPTCHA                   () {return new Response('ERROR_CAPTCHA');              }
	static public function ERROR_CAPTCHA_IMAGE             () {return new Response('ERROR_CAPTCHA_IMAGE');        }
	static public function ERROR_DELETION                  () {return new Response('ERROR_DELETION');             }
	static public function ERROR_MESSAGE                   () {return new Response('ERROR_MESSAGE');              }
	static public function ERROR_BODY                      () {return new Response('ERROR_BODY');              }
	static public function ERROR_MESSAGE_NOT_FOUND         () {return new Response('ERROR_MESSAGE_NOT_FOUND');    }
	static public function ERROR_MICROTIME                 () {return new Response('ERROR_MICROTIME');            }
	static public function ERROR_MICROTIME_DUPLICATED      () {return new Response('ERROR_MICROTIME_DUPLICATED'); }
	static public function ERROR_MICROTIME_FORMAT          () {return new Response('ERROR_MICROTIME_FORMAT');     }
	static public function ERROR_PASSWORD                  () {return new Response('ERROR_PASSWORD');             }
	static public function ERROR_PUBLIC_PORT               () {return new Response('ERROR_PUBLIC_PORT');                 }
	static public function ERROR_PRIVATE_PORT              () {return new Response('ERROR_PRIVATE_PORT');                 }
	static public function ERROR_PRIVATE_IP                () {return new Response('ERROR_PRIVATE_IP');           }
	static public function ERROR_PUBLIC_IP                 () {return new Response('ERROR_PUBLIC_IP');            }
	static public function ERROR_SAME_IPS                  () {return new Response('ERROR_SAME_IPS');             }
	static public function ERROR_USERNAME                  () {return new Response('ERROR_USERNAME');             }
	static public function ERROR_USERNAME_TAKEN            () {return new Response('ERROR_USERNAME_TAKEN');       }
	static public function ERROR_VALIDATION                () {return new Response('ERROR_VALIDATION');           }
	static public function ERROR_KEY                       () {return new Response('ERROR_KEY');           }
	static public function NO_KEY                          () {return new Response('NO_KEY');                 }
	static public function NO_MESSAGE                      () {return new Response('NO_MESSAGE');                 }
	static public function NO_POSTED                       () {return new Response('NO_POSTED');                 }
	static public function fromException(RequestException $e) {return new Response($e->getMessage());}
}
?>