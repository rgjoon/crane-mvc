<?php

interface ResponseGenerator {
	public function generate();
}

class ErrorHandler {
	private $exception;

	public function __construct($exception) {
		$this->exception = $exception;
	}

	public function handleError() {
		echo "<pre>Error: \n";
		print_r($this->exception);
		echo '</pre>';
	}
}

class RequestHandler {
	private $config;

	public function __construct($config) {
		$this->config = $config;
	}

	private function findMethodName($controller, $http_method, $action) {
		if( method_exists($controller, $http_method . '_' . $action) ) {
			$methodName = $http_method . '_' . $action;
		}
		else
		if( method_exists($controller, $action) ) {
			$methodName = $action;
		}
		else
		if( method_exists($controller, $method) ) {
			$methodName = $http_method;
		}
		else {
			throw new Exception('MethodNotFound');
		}

		return $methodName;
	}

	private function invokeMethod($controller, $methodName) {
		$reflector = new ReflectionMethod($controller, $methodName);
		$parameters = $reflector->getParameters();
		$arguments = array();
		$data = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;

		foreach($parameters as $parameter) {
			$arguments[] = $data[$parameter->getName()];
		}

		return $reflector->invokeArgs($controller, $arguments);
	}

	private function addResponseHeaders() {
		foreach($this->config['headers'] as $key => $value) {
			header($key . ':' . $value);
		}
	}

	public function handleRequest() {
		$method = strtolower($_SERVER['REQUEST_METHOD']);
		list($group, $action) = explode(".", $_GET['q']);

		$className = strtoupper($group[0]) . substr($group, 1) . 'Controller';
		$classPath = $this->config['path']['controller'] . '/' . $group . '/' . $className . '.php';

		include_once($classPath);

		$controller = new $className;
		$this->addResponseHeaders();

		try {
			$methodName = $this->findMethodName($controller, $method, $action);
			list($className, $data) = $this->invokeMethod($controller, $methodName);

			$classPath = $this->config['path']['generator'] . '/' . $className . '.php';

			include_once($classPath);

			$generator = new $className($data);
			$generator->generate();
		} catch( Exception $e ) {
			$error = new ErrorHandler($e);
			$error->handleError();
		}
	}
}
