<?php
namespace app\Controllers;
use core\AccessController;

class LoginController extends AccessController
{
	public function getUser() {
		try {
			$stmt = $this->db->prepare('SELECT email, name FROM users WHERE id = :id');
			$stmt->bindValue(':id', $this->decoded->id);
			$stmt->execute();
			$user = $stmt->fetch(\PDO::FETCH_ASSOC);
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
		} catch (\PDOException $e) {
			http_response_code(500);
			die(json_encode(['error' => 'DB error']));
		} catch (\Exception $e) {
			http_response_code(500);
			die(json_encode(['error' => $e->getMessage()]));
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
			$stmt->bindValue(':id', $this->decoded->id);
			$stmt->execute();
		} catch (\PDOException $e) {
			http_response_code(500);
			die(json_encode(['error' => 'DB error']));
		}

		echo json_encode(['success' => true]);
	}
	public function deleteAccount() {
		try {
			$stmt = $this->db->prepare('DELETE FROM users WHERE user_id = :id');
			$stmt->bindValue(':id', $this->decoded->id);
			$stmt->execute();
		} catch (\PDOException $e) {
			http_response_code(500);
			die(json_encode(['error' => 'DB error']));
		}

		echo json_encode(['success' => true]);
	}
}