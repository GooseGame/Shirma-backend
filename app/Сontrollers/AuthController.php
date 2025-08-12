<?php
namespace app\Controllers;
use Firebase\JWT\JWT;
use core\RefreshController;

class AuthController extends RefreshController
{
	public function logout() {
		$stmt = $this->db->prepare('DELETE FROM refresh_tokens WHERE token = :token');
		$stmt->bindValue(':token', $this->refreshToken);
		$stmt->execute();

		echo json_encode(['success' => true]);
	}

	public function refresh() {
		$stmt = $this->db->prepare('SELECT user_id, expires_at FROM refresh_tokens WHERE token = :token');
		$stmt->bindValue(':token', $this->refreshToken);
		$tokenData = $stmt->fetch(\PDO::FETCH_ASSOC);

		if (!$tokenData || strtotime($tokenData['expires_at']) < time()) {
			http_response_code(401);
			die(json_encode(['error' => 'Неверный или истёкший refresh-токен']));
		}

		// Удаляем старый refresh-токен
		$this->db->exec('DELETE FROM refresh_tokens WHERE token = "' . $this->refreshToken . '"');

		// Генерируем новые токены
		$newAccessToken = JWT::encode(['id' => $tokenData['user_id']], JWT_ACCESS_SECRET, 'HS256');
		$newRefreshToken = bin2hex(random_bytes(32));
		$newExpiresAt = date('Y-m-d H:i:s', time() + 7 * 24 * 60 * 60);

		$stmt = $this->db->prepare('INSERT INTO refresh_tokens (token, user_id, expires_at) VALUES (:token, :user_id, :expires_at)');
		$stmt->bindValue(':token', $newRefreshToken);
		$stmt->bindValue(':user_id', $tokenData['user_id']);
		$stmt->bindValue(':expires_at', $newExpiresAt);
		$stmt->execute();

		echo json_encode([
			'accessToken' => $newAccessToken,
			'refreshToken' => $newRefreshToken
		]);
	}
}