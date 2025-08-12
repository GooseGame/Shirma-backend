<?php
namespace app\Controllers;

use core\StandartController;
use Google\Client;
use Firebase\JWT\JWT;

class GoogleLoginController extends StandartController {
	public function google() {
		try {
			// 1. Получаем Google ID Token от фронтенда
			$data = json_decode(file_get_contents('php://input'), true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				http_response_code(400);
				die(json_encode(['error' => 'Invalid JSON']));
			}
			$googleToken = $data['googleToken'] ?? null;

			if (!$googleToken) {
				http_response_code(400);
				die(json_encode(['error' => 'Токен не предоставлен']));
			}

			// 2. Проверяем токен Google
			$client = new Client(['client_id' => GOOGLE_CLIENT_ID]);
			$payload = $client->verifyIdToken($googleToken);

			if (!$payload) {
				http_response_code(401);
				die(json_encode(['error' => 'Неверный Google токен']));
			}
			if ($payload['aud'] !== $_ENV['GOOGLE_CLIENT_ID']) {
				throw new \Exception('Token audience mismatch');
			}

			if ($payload['exp'] < time()) {
				throw new \Exception('Token expired');
			}

			// 3. Ищем или создаём пользователя
			$googleId = $payload['sub'];
			$email = $payload['email'];

			$stmt = $this->db->prepare('SELECT id FROM users WHERE google_id = :google_id');
			$stmt->bindValue(':google_id', $googleId);
			$stmt->execute();
			$result = $stmt->fetch(\PDO::FETCH_ASSOC);
			$isNew = false;

			if (!$result || empty($result)) {
				$stmt = $this->db->prepare('INSERT INTO users (google_id, email) VALUES (:google_id, :email)');
				$stmt->bindValue(':google_id', $googleId);
				$stmt->bindValue(':email', $email);
				$stmt->execute();
				$userId = $this->db->lastInsertId();
				$isNew = true;
			} else {
				$userId = $result['id'];
			}

			// 4. Генерируем JWT и refresh-токен
			$accessToken = JWT::encode(['id' => $userId], JWT_ACCESS_SECRET, 'HS256');
			$refreshToken = bin2hex(random_bytes(32));  // Случайная строка
			$expiresAt = date('Y-m-d H:i:s', time() + 7 * 24 * 60 * 60);  // Через 7 дней

			$stmt = $this->db->prepare('INSERT INTO refresh_tokens (token, user_id, expires_at) VALUES (:token, :user_id, :expires_at) on DUPLICATE KEY UPDATE token = :token, expires_at = :expires_at');
			$stmt->bindValue(':token', $refreshToken);
			$stmt->bindValue(':user_id', $userId);
			$stmt->bindValue(':expires_at', $expiresAt);
			$stmt->execute();

			// 5. Отправляем токены фронтенду
			echo json_encode([
				'accessToken' 	=> $accessToken,
				'refreshToken' 	=> $refreshToken,
				'isNew'			=> $isNew
			]);
		} catch (\Exception $e) {
			http_response_code(401);
			die(json_encode(['error' => $e->getMessage()]));
		}
	}
}