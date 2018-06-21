<?php
use Notify\Config;
use Notify\Common\InputException;
use Notify\Common\ServerException;
use Notify\App;

require(__DIR__ . '/../conf/config.php');
require(__DIR__ . '/../vendor/autoload.php');

if (!empty(Config::Cors)) {
	header('Access-Control-Allow-Origin: ' . Config::Cors);
}
header('Content-Type: application/json');

try {
	$app = new App();
	$response = $app->run();
}
catch (InputException $e) {
	$response = [
		'error' => $e->getDetails()
	];
	http_response_code($e->getCode());
}
catch (ServerException $e) {
	$response = [
		'error' => $e->getMessage()
	];
	if (Config::Debug) {
		$response['details'] = $e->getDetails();
	}
	http_response_code($e->getCode());
}
catch (\Exception $e) {
	$response = [
		'error' => 'An unknown error occurred'
	];
	if (Config::Debug) {
		$response['details'] = $e->getMessage();
	}
	http_response_code(500);
}
header('Cache-Control: no-cache, no-store');

echo json_encode($response);
?>
