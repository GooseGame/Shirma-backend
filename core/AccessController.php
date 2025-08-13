<?php
namespace core;
use Firebase\JWT\Key;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\BeforeValidException;

class AccessController extends StandartController
{
	public $decoded;
	public $accessToken;

	public function __construct()
	{
		parent::__construct();

		$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
		if (empty($authHeader) && function_exists('apache_request_headers')) {
			$headers = apache_request_headers();
			$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
		}
		$this->accessToken = str_replace('Bearer ', '', $authHeader);

		try {
			$this->decoded = JWT::decode($this->accessToken, new Key(JWT_ACCESS_SECRET, 'HS256'));
			
			if ($this->decoded->exp < time()) {
				throw new \Exception('Token expired');
			}
		} catch (\InvalidArgumentException $e) {
			// Handle cases where the provided key/key-array is empty or malformed.
			http_response_code(401);
			die(json_encode(['error' => 'Key/key-array is empty or malformed.']));
		} catch (\DomainException $e) {
			// Handle unsupported algorithms, invalid keys, or issues with OpenSSL/libsodium.
			http_response_code(401);
			die(json_encode(['error' => 'Unsupported algorithms, invalid keys, or issues with OpenSSL/libsodium.']));
		} catch (SignatureInvalidException $e) {
			// Handle cases where the JWT signature verification failed.
			http_response_code(401);
			die(json_encode(['error' => 'JWT signature verification failed.']));
		} catch (BeforeValidException $e) {
			// Handle cases where the JWT is used before its 'nbf' or 'iat' claim.
			http_response_code(401);
			die(json_encode(['error' => 'JWT is used before its "nbf" or "iat" claim.']));
		} catch (ExpiredException $e) {
			// Handle cases where the JWT has expired (based on 'exp' claim).
			http_response_code(401);
			die(json_encode(['error' => 'JWT has expired (based on "exp" claim).']));
		} catch (\UnexpectedValueException $e) {
			// Handle malformed JWTs, missing/unsupported algorithms, or key/algorithm mismatches.
			http_response_code(401);
			die(json_encode(['error' => 'Malformed JWTs, missing/unsupported algorithms, or key/algorithm mismatches.']));
		} catch (\Exception $e) {
			http_response_code(401);
			die(json_encode(['error' => 'Invalid or expired token']));
		}
	}
}