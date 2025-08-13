<?php
namespace app\Controllers;
use core\AccessController;
class PresetsController extends AccessController
{
	public function get()
	{
		$presetsStmt = $this->db->prepare('SELECT content FROM presets');
		$presetsStmt->execute();
		$presets = $presetsStmt->fetchAll();

		if (count($presets) === 0) {
			echo json_encode([]);
			die;
		}
		$presetsSimplified = array_column($presets, 'content');
		echo json_encode(['presets' => $presetsSimplified]);
	}

	public function count()
	{
		$presetsStmt = $this->db->prepare('SELECT COUNT(*) FROM presets');
		$presetsStmt->execute();
		$presetsCount = $presetsStmt->fetch(\PDO::FETCH_ASSOC);
		echo json_encode($presetsCount);
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

		$cleanCharacter = strip_tags($data['character']);

		try {
			$stmt = $this->db->prepare("INSERT INTO presets (content) VALUES (?)");
			
			$stmt->execute([$cleanCharacter]);
			
			echo json_encode(['success' => true]);
		} catch (\PDOException $e) {
			http_response_code(500);
			die(json_encode(['error' => 'Ошибка базы данных']));
		}
	}
};