<?php
namespace Notify\Common;

class InputException extends \Exception {
	private $details;

	public function __construct($details, int $errorCode = 400) {
		$this->details = $details;
		parent::__construct('Input error', $errorCode);
	}

	public function getDetails() {
		return $this->details;
	}
}
