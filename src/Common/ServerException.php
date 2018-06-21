<?php
namespace Notify\Common;

class ServerException extends \Exception {
	private $details;

	public function __construct($message, $details = '', int $errorCode = 500) {
		$this->details = $details;
		parent::__construct($message, $errorCode);
	}

	public function getDetails() {
		return $this->details;
	}
}
