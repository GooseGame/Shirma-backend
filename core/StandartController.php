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

		$this->db = createPDO();
		} catch (\Exception $e) {
			echo $e->getMessage();
		}
	}
}