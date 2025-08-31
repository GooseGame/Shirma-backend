<?php
namespace app\Controllers;
use core\AccessController;
class CharactersController extends AccessController
{
	public function get()
	{
		$charsStmt = $this->db->prepare('SELECT content, updated_at_timestamp FROM characters WHERE user_id = :id');
		$charsStmt->bindValue(':id', $this->decoded->id);
		$charsStmt->execute();
		$characters = $charsStmt->fetchAll();

		if (count($characters) === 0) {
			echo json_encode(['characters'=>[]]);
			die;
		}
		$maxTimestamp = max(array_column($characters, 'updated_at_timestamp'));
		$charsSimplified = array_column($characters, 'content');
		echo json_encode(['characters'=>$charsSimplified, 'lastUpdateTimestamp' => $maxTimestamp]);
	}

	public function count()
	{
		$charsStmt = $this->db->prepare('SELECT COUNT(*) FROM characters WHERE user_id = :id');
		$charsStmt->bindValue(':id', $this->decoded->id);
		$charsStmt->execute();
		$charsCount = $charsStmt->fetch(\PDO::FETCH_ASSOC);
		echo json_encode(['count' => $charsCount]);
	}

	public function getLastUpdatedTime()
	{
		$actualityStmt = $this->db->prepare('SELECT MAX(updated_at_timestamp) FROM characters WHERE user_id = :id');
		$actualityStmt->bindValue(':id', $this->decoded->id);
		$actualityStmt->execute();
		$lastActual = $actualityStmt->fetch(\PDO::FETCH_ASSOC);
		echo json_encode(['lastUpdated' => count($lastActual) == 0 ? 0 : $lastActual[0]['updated_at_timestamp']]);
	}

	public function delete()
	{
		$data = json_decode(file_get_contents('php://input'), true);
		$charId = $data['id'] ?? null;
		if (!$charId || empty(trim($charId))) {
			die(json_encode(['error' => 'Нет id персонажа для удаления']));
		}
		try {
			$deleteStmt = $this->db->prepare('DELETE FROM characters WHERE char_id = :charId AND user_id = :id');
			$deleteStmt->bindValue(':charId', $charId);
			$deleteStmt->bindValue(':id', $this->decoded->id);
			$deleteStmt->execute();
			echo json_encode(['success' => true]);
		} catch (\PDOException $e) {
			http_response_code(500);
			die(json_encode(['error' => 'DB error']));
		}
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

		$charId = $data['charId'] ?? null;
		if (!$charId || empty(trim($charId))) {
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
		$currentTimestamp = $data['timestamp'] ?? time();

		try {
			$stmt = $this->db->prepare("INSERT INTO characters (user_id, content, char_id, updated_at_timestamp) VALUES (?, ?, ?, ?)");
			
			$stmt->execute([$data['user_id'], $cleanCharacter, $charId, $currentTimestamp]);
			
			echo json_encode(['success' => true]);
		} catch (\PDOException $e) {
			http_response_code(500);
			die(json_encode(['error' => 'DB error']));
		}
	}
}