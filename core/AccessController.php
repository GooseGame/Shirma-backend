<?php
namespace core;
use Firebase\JWT\Key;
use Firebase\JWT\JWT;

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
				throw new \Exception('Token expired');
			}
		} catch (\Exception $e) {
			http_response_code(401);
			die(json_encode(['error' => 'Invalid or expired token']));
		}
	}
}