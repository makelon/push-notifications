<?php
namespace Notify;

use FastRoute;
use Notify\Config;
use Notify\Common\Db;
use Notify\Common\InputException;
use Notify\PushClient;
use Notify\PushServer;
use Notify\WebPushWrapper;

class App {
	/**
	* Set up routing and call the request handler
	*
	* @return response data
	*/
	public function run() {
		$requestMethod = $_SERVER['REQUEST_METHOD'];
		$path = $_SERVER['PATH_INFO'] ?? '';

		$dispatcher = FastRoute\simpleDispatcher(function($router) {
			$router->addRoute('GET', '/{endpoint}', ['client', 'get']);
			$router->addRoute('POST', '/add', ['client', 'add']);
			$router->addRoute('POST', '/{platform}/delete', ['client', 'delete']);
			$router->addRoute('POST', '/delete', ['client', 'delete']);
			$router->addRoute('POST', '/test', ['server', 'test']);
			$router->addRoute('POST', '/{platform}/push', ['server', 'push']);
		});
		$routeInfo = $dispatcher->dispatch($requestMethod, $path);
		if ($routeInfo[0] !== FastRoute\Dispatcher::FOUND) {
			throw new InputException("No handler for method '$requestMethod:$path'", 404);
		}

		[$handlerType, $handlerMethod] = $routeInfo[1];
		if ($handlerType === 'client') {
			$requestHandler = new PushClient(new Db());
		}
		elseif ($handlerType === 'server') {
			$requestHandler = new PushServer(new Db(), new WebPushWrapper());
		}
		else {
			throw new \Exception("Unknown handler type '$handlerType'");
		}
		$requestHandler->setReqParams($routeInfo[2]);
		$requestHandler->setPostParams();
		return $requestHandler->$handlerMethod();
	}
}
