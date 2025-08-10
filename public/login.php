<?php
use Google\Client;
use Firebase\JWT\JWT;
require_once __DIR__ . '/../core/StandartController.php';

class login extends AccessController
{
	public function google() {
		try {
			// 1. Получаем Google ID Token от фронтенда
			$data = json_decode(file_get_contents('php://input'), true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				http_response_code(400);
				die(json_encode(['error' => 'Invalid JSON']));
			}
			$googleToken = $data['token'] ?? null;

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
				throw new Exception('Token audience mismatch');
			}

			if ($payload['exp'] < time()) {
				throw new Exception('Token expired');
			}

			// 3. Ищем или создаём пользователя
			$googleId = $payload['sub'];
			$email = $payload['email'];

			$stmt = $this->db->prepare('SELECT id FROM users WHERE google_id = :google_id');
			$stmt->bindValue(':google_id', $googleId);
			$result = $stmt->fetch(PDO::FETCH_ASSOC);
			$isNew = false;

			if (!$result) {
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

			$stmt = $this->db->prepare('INSERT INTO refresh_tokens (token, user_id, expires_at) VALUES (:token, :user_id, :expires_at)');
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
		} catch (Exception $e) {
			http_response_code(401);
			die(json_encode(['error' => $e->getMessage()]));
		}
	}

	public function getUser() {
		try {
			$stmt = $this->db->prepare('SELECT email, name, id FROM users WHERE user_id = :id');
			$stmt->bindValue(':id', $this->decoded['id']);
			$user = $stmt->fetch();
			if (!$user) {
				http_response_code(400);
				die(json_encode(['error' => 'No such user']));
			} else {
				echo json_encode([
					'email'	=> $user['email'],
					'name'	=> $user['name'],
					'id'	=> $user['id']
				]);
			}
		} catch (PDOException $e) {
			http_response_code(500);
			die(json_encode(['error' => 'DB error']));
		}
	}

	public function changeName() {
		$data = json_decode(file_get_contents('php://input'), true);
		if (!isset($data['name'])) {
			http_response_code(400);
			die(json_encode(['error' => 'No name']));
		}
		if (strlen($data['name']) > 200) {
			die(json_encode(['error' => 'Too long name']));
		}
		try {
			$stmt = $this->db->prepare('UPDATE USERS SET name = :name WHERE user_id = :id');
			$stmt->bindValue(':name', $data['name']);
			$stmt->bindValue(':id', $this->decoded['id']);
			$stmt->execute();
		} catch (PDOException $e) {
			http_response_code(500);
			die(json_encode(['error' => 'DB error']));
		}

		echo json_encode(['success' => true]);
	}
	public function deleteAccount() {
		try {
			$stmt = $this->db->prepare('DELETE FROM users WHERE user_id = :id');
			$stmt->bindValue(':id', $this->decoded['id']);
			$stmt->execute();
		} catch (PDOException $e) {
			http_response_code(500);
			die(json_encode(['error' => 'DB error']));
		}

		echo json_encode(['success' => true]);
	}
}