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
		$presetsSimplified = array_map(function ($content) {
			return json_decode($content, true);
		}, $presetsSimplified);
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
		$presetId = $data['presetId'] ?? null;
		if (!$presetId) {
			http_response_code(400);
			die(json_encode(['error' => 'no presetId to save']));
		}
		if (!$character) {
			http_response_code(400);
			die(json_encode(['error' => 'no character to save']));
		}

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
			//get role first, if = 1, can save
			$stmt = $this->db->prepare('SELECT role FROM users WHERE id = :id');
			$stmt->bindValue(':id', $this->decoded->id);
			$stmt->execute();
			$user = $stmt->fetch(\PDO::FETCH_ASSOC);
			if ((int)$user['role'] !== 1) {
				http_response_code(403);
				die(json_encode(['error' => 'You are not authorized to save presets']));
			}
			//rewrite if presetId already exists
			$stmt = $this->db->prepare('SELECT 1 FROM presets WHERE preset_id = :presetId');
			$stmt->bindValue(':presetId', $presetId);
			$stmt->execute();
			if ($exists = $stmt->fetch(\PDO::FETCH_ASSOC)) {
				$stmt = $this->db->prepare('UPDATE presets SET content = :content WHERE preset_id = :presetId');
				$stmt->bindValue(':content', $cleanCharacter);
				$stmt->bindValue(':presetId', $presetId);
				$stmt->execute();
			} else {
				$stmt = $this->db->prepare("INSERT INTO presets (content, preset_id) VALUES (:content, :presetId)");
				$stmt->bindValue(':content', $cleanCharacter);
				$stmt->bindValue(':presetId', $presetId);
				$stmt->execute();
			}
			$stmt = $this->db->prepare('UPDATE update_presets_time SET updated_at_timestamp = NOW()');
			$stmt->execute();
			echo json_encode(['success' => true]);
		} catch (\PDOException $e) {
			http_response_code(500);
			die(json_encode(['error' => 'Ошибка базы данных']));
		}
	}

	public function getLastUpdatedTime()
	{
		try {
			$stmt = $this->db->prepare('SELECT MAX(updated_at_timestamp) as lastUpdatedTimestamp FROM update_presets_time');
			$stmt->execute();
			$lastUpdatedTimestamp = $stmt->fetch(\PDO::FETCH_ASSOC);
			echo json_encode(['lastUpdatedTimestamp' => $lastUpdatedTimestamp]);
		} catch (\PDOException $e) {
			http_response_code(500);
			die(json_encode(['error' => 'Ошибка базы данных']));
		}
	}

	public function delete()
	{
		$raw = file_get_contents('php://input');
		$data = json_decode($raw ?: '', true);
		if (!is_array($data)) {
			http_response_code(400);
			die(json_encode(['error' => 'invalid JSON']));
		}

		$presetId = $data['presetId'] ?? null;
		if (!$presetId || empty(trim($presetId))) {
			http_response_code(400);
			die(json_encode(['error' => 'no presetId to delete']));
		}
		try {
			$stmt = $this->db->prepare('SELECT role FROM users WHERE id = :id');
			$stmt->bindValue(':id', $this->decoded->id);
			$stmt->execute();
			$user = $stmt->fetch(\PDO::FETCH_ASSOC);
			if ((int)$user['role'] !== 1) {
				http_response_code(403);
				die(json_encode(['error' => 'You are not authorized to delete presets']));
			}
			$stmt = $this->db->prepare('DELETE FROM presets WHERE preset_id = :presetId');
			$stmt->bindValue(':presetId', $presetId);
			$stmt->execute();
			$stmt = $this->db->prepare('UPDATE update_presets_time SET updated_at_timestamp = NOW()');
			$stmt->execute();
			echo json_encode(['success' => true]);
		} catch (\PDOException $e) {
			http_response_code(500);
			die(json_encode(['error' => 'Ошибка базы данных']));
		}
	}
};