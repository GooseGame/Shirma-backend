<?php
namespace app\Controllers;
use core\AccessController;
class CharactersController extends AccessController
{
	public function get()
	{
		$charsStmt = $this->db->prepare('SELECT content FROM characters WHERE user_id = :id');
		$charsStmt->bindValue(':id', $this->decoded->id);
		$charsStmt->execute();
		$characters = $charsStmt->fetchAll();

		if (count($characters) === 0) {
			echo json_encode([]);
			die;
		}
		$charsSimplified = array_column($characters, 'content');
		echo json_encode(['characters'=>$charsSimplified]);
	}

	public function save()
	{
		$maxSize = 82400; // ~80KB
		$data = json_decode(file_get_contents('php://input'), true);
		if (strlen($data) > $maxSize) {
			http_response_code(413);
			die(json_encode(['error' => 'JSON слишком большой']));
		}
		$character = $data['character'] ?? null;

		if (!$character || empty(trim($character))) {
			http_response_code(400);
			die(json_encode(['error' => 'no character to save']));
		}

		$stmt = $this->db->prepare("SELECT COUNT(*) FROM characters WHERE user_id = :id");
		$stmt->bindValue(':id', $this->decoded->id);
			$stmt->execute();
		if ($stmt->fetchColumn() >= 20) {
			http_response_code(429);
			die(json_encode(['error' => 'Достигнут лимит в 20 объектов']));
		}

		$cleanCharacter = strip_tags($data['character']);

		try {
			$stmt = $this->db->prepare("INSERT INTO characters (user_id, content) VALUES (?, ?)");
			
			$stmt->execute([$data['user_id'], $cleanCharacter]);
			
			echo json_encode(['success' => true]);
		} catch (\PDOException $e) {
			http_response_code(500);
			die(json_encode(['error' => 'DB error']));
		}
	}
}