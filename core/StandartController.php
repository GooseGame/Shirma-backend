<?php
namespace core;
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

class StandartController
{
	public $db;

	public function __construct()
	{
		try {
		if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
			http_response_code(415);
			die(json_encode(['error' => 'Unsupported Media Type']));
		}
		$allowedOrigins = [
			"http://localhost:5173",
			"https://shirma.fun"
		];

		$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

		if (in_array($origin, $allowedOrigins)) {
			header("Access-Control-Allow-Origin: $origin");
		} else {
			header("Access-Control-Allow-Origin: *");
		}

		header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
		header('Access-Control-Allow-Headers: Content-Type, Authorization');
		header('Content-Type: application/json');

		if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
			exit(0);
		}
		$this->db = createPDO();
		} catch (\Exception $e) {
			echo $e->getMessage();
		}
	}
}