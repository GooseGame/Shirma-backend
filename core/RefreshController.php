<?php
namespace core;
class RefreshController extends StandartController
{
	public $refreshToken;

	public function __construct()
	{
		parent::__construct();
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			http_response_code(405);
			die(json_encode(['error' => 'Method Not Allowed']));
		}

		$data = json_decode(file_get_contents('php://input'), true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			http_response_code(400);
			die(json_encode(['error' => 'Invalid JSON']));
		}
		$this->refreshToken = $data['refreshToken'] ?? '';
		if ($this->refreshToken === '') {
			http_response_code(400);
			die(json_encode(['error' => 'Refresh-токен не предоставлен']));
		}
		if (!preg_match('/^[a-f0-9]{64}$/i', $this->refreshToken)) {
			http_response_code(400);
			die(json_encode(['error' => 'Invalid token format']));
		}
	}
}

