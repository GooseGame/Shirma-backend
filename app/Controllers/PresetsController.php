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
			echo json_encode(['presets' => []]);
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
		echo json_encode(['count' => $presetsCount]);
	}

	public function save()
	{
		$maxSize = 82400; // ~80KB
		$raw = file_get_contents('php://input');
		if ($raw === false) {
			http_response_code(400);
			die(json_encode(['error' => 'invalid request body']));
		}
		if (strlen($raw) > $maxSize) {
			http_response_code(413);
			die(json_encode(['error' => 'JSON слишком большой']));
		}
		$data = json_decode($raw, true);
		if (!is_array($data)) {
			http_response_code(400);
			die(json_encode(['error' => 'invalid JSON']));
		}

		$character = $data['character'] ?? null;

		if (is_string($character)) {
			$character = trim($character);
			if ($character === '') {
				http_response_code(400);
				die(json_encode(['error' => 'no character to save']));
			}
		} elseif (is_array($character)) {
			// keep as-is; will encode to JSON below
		} else {
			http_response_code(400);
			die(json_encode(['error' => 'no character to save']));
		}

		if (is_array($character)) {
			$cleanCharacter = json_encode($character, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			if ($cleanCharacter === false) {
				http_response_code(400);
				die(json_encode(['error' => 'invalid character payload']));
			}
		} else {
			$cleanCharacter = strip_tags($character);
		}

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