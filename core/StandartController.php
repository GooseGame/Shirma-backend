<?php
require_once __DIR__ . '/../includes/db.php';
use Firebase\JWT\Key;
use Firebase\JWT\JWT;

class StandartController
{
	public $db;

	public function __construct()
	{
		$this->db = createPDO();
		if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
			http_response_code(415);
			die(json_encode(['error' => 'Unsupported Media Type']));
		}
		header('Content-Type: application/json');
	}
}

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

class AccessController extends StandartController
{
	public $decoded;
	public $accessToken;

	public function __construct()
	{
		parent::__construct();
		$headers = getallheaders();
		$authHeader = $headers['Authorization'] ?? '';
		$this->accessToken = str_replace('Bearer ', '', $authHeader);

		try {
			$this->decoded = JWT::decode($this->accessToken, new Key($_ENV['JWT_ACCESS_SECRET'], 'HS256'));
			
			if ($this->decoded->exp < time()) {
				throw new Exception('Token expired');
			}
		} catch (Exception $e) {
			http_response_code(401);
			die(json_encode(['error' => 'Invalid or expired token']));
		}
	}
}