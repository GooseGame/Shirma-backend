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
		$actualityStmt = $this->db->prepare('SELECT MAX(updated_at_timestamp) AS max_ts FROM characters WHERE user_id = :id');
		$actualityStmt->bindValue(':id', $this->decoded->id);
		$actualityStmt->execute();
		$row = $actualityStmt->fetch(\PDO::FETCH_ASSOC);
		$raw = ($row !== false && isset($row['max_ts'])) ? $row['max_ts'] : null;
		if ($raw === null || $raw === '') {
			$lastUpdated = 0;
		} elseif ($raw instanceof \DateTimeInterface) {
			$lastUpdated = (int) $raw->format('U');
		} elseif (is_numeric($raw)) {
			$lastUpdated = (int) $raw;
		} else {
			$parsed = strtotime((string) $raw);
			$lastUpdated = $parsed !== false ? $parsed : 0;
		}
		echo json_encode(['lastUpdated' => $lastUpdated]);
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

		$charId = $data['charId'] ?? null;
		if (is_int($charId) || is_float($charId)) {
			$charId = (string) $charId;
		}
		if (!is_string($charId)) {
			http_response_code(400);
			die(json_encode(['error' => 'no charId to save']));
		}
		$charId = trim($charId);
		if ($charId === '') {
			http_response_code(400);
			die(json_encode(['error' => 'no charId to save']));
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

		$rawTs = $data['timestamp'] ?? null;
		if (is_string($rawTs) && is_numeric($rawTs)) {
			$rawTs = 0 + $rawTs;
		}
		if (isset($rawTs) && is_numeric($rawTs)) {
			$n = (float) $rawTs;
			// JS Date.now() — миллисекунды; для TIMESTAMP в MySQL нужны секунды для FROM_UNIXTIME
			$unixSeconds = $n > 1e11 ? (int) floor($n / 1000) : (int) $n;
		} elseif (is_string($rawTs) && $rawTs !== '') {
			$parsed = strtotime($rawTs);
			$unixSeconds = $parsed !== false ? $parsed : time();
		} else {
			$unixSeconds = time();
		}

		try {
			$existsStmt = $this->db->prepare('SELECT 1 FROM characters WHERE user_id = ? AND char_id = ? LIMIT 1');
			$existsStmt->execute([$this->decoded->id, $charId]);
			$exists = (bool) $existsStmt->fetchColumn();

			if ($exists) {
				$stmt = $this->db->prepare('UPDATE characters SET content = ?, updated_at_timestamp = FROM_UNIXTIME(?) WHERE user_id = ? AND char_id = ?');
				$stmt->execute([$cleanCharacter, $unixSeconds, $this->decoded->id, $charId]);
			} else {
				$stmt = $this->db->prepare('SELECT COUNT(*) FROM characters WHERE user_id = :id');
				$stmt->bindValue(':id', $this->decoded->id);
				$stmt->execute();
				if ($stmt->fetchColumn() >= 20) {
					http_response_code(429);
					die(json_encode(['error' => 'Достигнут лимит в 20 объектов']));
				}
				$stmt = $this->db->prepare('INSERT INTO characters (user_id, content, char_id, updated_at_timestamp) VALUES (?, ?, ?, FROM_UNIXTIME(?))');
				$stmt->execute([$this->decoded->id, $cleanCharacter, $charId, $unixSeconds]);
			}

			echo json_encode(['success' => true]);
		} catch (\PDOException $e) {
			http_response_code(500);
			die(json_encode(['error' => 'DB error']));
		}
	}
}