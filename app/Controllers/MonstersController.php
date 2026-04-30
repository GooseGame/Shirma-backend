<?php
namespace app\Controllers;

use core\AccessController;

class MonstersController extends AccessController
{
	private function getCurrentUserRole()
	{
		$stmt = $this->db->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
		$stmt->bindValue(':id', $this->decoded->id);
		$stmt->execute();
		$user = $stmt->fetch(\PDO::FETCH_ASSOC);
		return isset($user['role']) ? (int) $user['role'] : 0;
	}

	public function get()
	{
		$id = isset($_GET['id']) ? trim((string) $_GET['id']) : null;
		$by = isset($_GET['by']) ? trim((string) $_GET['by']) : null;

		try {
			if ($id !== null && $id !== '') {
				$stmt = $this->db->prepare('SELECT content FROM monsters WHERE id = :monsterId AND (user_id IS NULL OR user_id = :userId) LIMIT 1');
				$stmt->bindValue(':monsterId', $id);
				$stmt->bindValue(':userId', $this->decoded->id);
				$stmt->execute();
				$row = $stmt->fetch(\PDO::FETCH_ASSOC);

				if (!$row) {
					http_response_code(404);
					die(json_encode(['error' => 'monster not found']));
				}

				$content = $row['content'];
				if (is_string($content)) {
					$decoded = json_decode($content, true);
					if (json_last_error() === JSON_ERROR_NONE) {
						echo json_encode($decoded);
						return;
					}
				}

				echo is_string($content) ? $content : json_encode($content);
				return;
			}

			if ($by === 'local') {
				$stmt = $this->db->prepare('SELECT id, user_id, name, avatar, challenge_rating, type_name, living_areas, size_names, alignment_short FROM monsters_preview WHERE user_id = :userId');
				$stmt->bindValue(':userId', $this->decoded->id);
				$stmt->execute();
			} elseif ($by === 'global' || $by === null || $by === '') {
				$stmt = $this->db->prepare('SELECT id, user_id, name, avatar, challenge_rating, type_name, living_areas, size_names, alignment_short FROM monsters_preview WHERE user_id IS NULL');
				$stmt->execute();
			} else {
				http_response_code(400);
				die(json_encode(['error' => 'invalid by parameter']));
			}

			$monsters = $stmt->fetchAll(\PDO::FETCH_ASSOC);
			echo json_encode(['monsters' => $monsters ?: []]);
		} catch (\PDOException $e) {
			http_response_code(500);
			die(json_encode(['error' => 'DB error']));
		}
	}

	public function save()
	{
		$raw = file_get_contents('php://input');
		$data = json_decode($raw ?: '', true);
		if (!is_array($data)) {
			http_response_code(400);
			die(json_encode(['error' => 'invalid JSON']));
		}

		$monster = $data['monster'] ?? null;
		$monsterPreview = $data['monster_preview'] ?? null;
		$id = isset($data['id']) ? trim((string) $data['id']) : '';

		if (!is_array($monster)) {
			http_response_code(400);
			die(json_encode(['error' => 'monster is required']));
		}
		if (!is_array($monsterPreview)) {
			http_response_code(400);
			die(json_encode(['error' => 'monster_preview is required']));
		}
		if ($id === '') {
			http_response_code(400);
			die(json_encode(['error' => 'id is required']));
		}

		$previewId = isset($monsterPreview['id']) ? trim((string) $monsterPreview['id']) : '';
		$name = isset($monsterPreview['name']) ? trim((string) $monsterPreview['name']) : '';
		$typeName = isset($monsterPreview['typeName']) ? trim((string) $monsterPreview['typeName']) : '';

		if ($previewId === '' || $name === '' || $typeName === '') {
			http_response_code(400);
			die(json_encode(['error' => 'monster_preview.id, monster_preview.name and monster_preview.typeName are required']));
		}

		if ($previewId !== $id) {
			http_response_code(400);
			die(json_encode(['error' => 'id and monster_preview.id must match']));
		}

		$avatar = isset($monsterPreview['avatar']) ? trim((string) $monsterPreview['avatar']) : null;
		$challengeRating = isset($monsterPreview['challengeRating']) ? (int) $monsterPreview['challengeRating'] : 0;
		$livingAreas = isset($monsterPreview['livingAreas']) ? trim((string) $monsterPreview['livingAreas']) : null;
		$sizeNames = isset($monsterPreview['sizeNames']) ? trim((string) $monsterPreview['sizeNames']) : null;
		$alignmentShort = isset($monsterPreview['alignmentShort']) ? trim((string) $monsterPreview['alignmentShort']) : null;

		$monsterJson = json_encode($monster, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($monsterJson === false) {
			http_response_code(400);
			die(json_encode(['error' => 'invalid monster payload']));
		}

		try {
			$role = $this->getCurrentUserRole();
			$isAdmin = $role === 1;
			$ownerId = $isAdmin ? null : (int) $this->decoded->id;

			if (!$isAdmin) {
				$countStmt = $this->db->prepare('SELECT COUNT(*) FROM monsters WHERE user_id = :userId');
				$countStmt->bindValue(':userId', $ownerId);
				$countStmt->execute();
				$count = (int) $countStmt->fetchColumn();

				$existsStmt = $this->db->prepare('SELECT 1 FROM monsters WHERE id = :monsterId AND user_id = :userId LIMIT 1');
				$existsStmt->bindValue(':monsterId', $id);
				$existsStmt->bindValue(':userId', $ownerId);
				$existsStmt->execute();
				$exists = (bool) $existsStmt->fetchColumn();

				if (!$exists && $count >= 20) {
					http_response_code(429);
					die(json_encode(['error' => 'Достигнут лимит в 20 монстров']));
				}
			}

			if ($isAdmin) {
				$monsterExistsStmt = $this->db->prepare('SELECT 1 FROM monsters WHERE id = :monsterId AND user_id IS NULL LIMIT 1');
				$monsterExistsStmt->bindValue(':monsterId', $id);
				$monsterExistsStmt->execute();
				$monsterExists = (bool) $monsterExistsStmt->fetchColumn();
			} else {
				$monsterExistsStmt = $this->db->prepare('SELECT 1 FROM monsters WHERE id = :monsterId AND user_id = :userId LIMIT 1');
				$monsterExistsStmt->bindValue(':monsterId', $id);
				$monsterExistsStmt->bindValue(':userId', $ownerId);
				$monsterExistsStmt->execute();
				$monsterExists = (bool) $monsterExistsStmt->fetchColumn();
			}

			if ($monsterExists) {
				if ($isAdmin) {
					$monsterStmt = $this->db->prepare('UPDATE monsters SET content = :content WHERE id = :monsterId AND user_id IS NULL');
					$monsterStmt->bindValue(':content', $monsterJson);
					$monsterStmt->bindValue(':monsterId', $id);
					$monsterStmt->execute();

					$previewStmt = $this->db->prepare('UPDATE monsters_preview SET name = :name, avatar = :avatar, challenge_rating = :challengeRating, type_name = :typeName, living_areas = :livingAreas, size_names = :sizeNames, alignment_short = :alignmentShort WHERE id = :previewId AND user_id IS NULL');
					$previewStmt->bindValue(':name', $name);
					$previewStmt->bindValue(':avatar', $avatar);
					$previewStmt->bindValue(':challengeRating', $challengeRating);
					$previewStmt->bindValue(':typeName', $typeName);
					$previewStmt->bindValue(':livingAreas', $livingAreas);
					$previewStmt->bindValue(':sizeNames', $sizeNames);
					$previewStmt->bindValue(':alignmentShort', $alignmentShort);
					$previewStmt->bindValue(':previewId', $previewId);
					$previewStmt->execute();
				} else {
					$monsterStmt = $this->db->prepare('UPDATE monsters SET content = :content WHERE id = :monsterId AND user_id = :userId');
					$monsterStmt->bindValue(':content', $monsterJson);
					$monsterStmt->bindValue(':monsterId', $id);
					$monsterStmt->bindValue(':userId', $ownerId);
					$monsterStmt->execute();

					$previewStmt = $this->db->prepare('UPDATE monsters_preview SET name = :name, avatar = :avatar, challenge_rating = :challengeRating, type_name = :typeName, living_areas = :livingAreas, size_names = :sizeNames, alignment_short = :alignmentShort WHERE id = :previewId AND user_id = :userId');
					$previewStmt->bindValue(':name', $name);
					$previewStmt->bindValue(':avatar', $avatar);
					$previewStmt->bindValue(':challengeRating', $challengeRating);
					$previewStmt->bindValue(':typeName', $typeName);
					$previewStmt->bindValue(':livingAreas', $livingAreas);
					$previewStmt->bindValue(':sizeNames', $sizeNames);
					$previewStmt->bindValue(':alignmentShort', $alignmentShort);
					$previewStmt->bindValue(':previewId', $previewId);
					$previewStmt->bindValue(':userId', $ownerId);
					$previewStmt->execute();
				}
			} else {
				$monsterStmt = $this->db->prepare('INSERT INTO monsters (id, content, user_id) VALUES (:monsterId, :content, :userId)');
				$monsterStmt->bindValue(':monsterId', $id);
				$monsterStmt->bindValue(':content', $monsterJson);
				$monsterStmt->bindValue(':userId', $ownerId);
				$monsterStmt->execute();

				$previewStmt = $this->db->prepare('INSERT INTO monsters_preview (id, user_id, name, avatar, challenge_rating, type_name, living_areas, size_names, alignment_short) VALUES (:previewId, :userId, :name, :avatar, :challengeRating, :typeName, :livingAreas, :sizeNames, :alignmentShort)');
				$previewStmt->bindValue(':previewId', $previewId);
				$previewStmt->bindValue(':userId', $ownerId);
				$previewStmt->bindValue(':name', $name);
				$previewStmt->bindValue(':avatar', $avatar);
				$previewStmt->bindValue(':challengeRating', $challengeRating);
				$previewStmt->bindValue(':typeName', $typeName);
				$previewStmt->bindValue(':livingAreas', $livingAreas);
				$previewStmt->bindValue(':sizeNames', $sizeNames);
				$previewStmt->bindValue(':alignmentShort', $alignmentShort);
				$previewStmt->execute();
			}

			echo json_encode(['success' => true]);
		} catch (\PDOException $e) {
			http_response_code(500);
			die(json_encode(['error' => 'DB error']));
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

		$id = isset($data['id']) ? trim((string) $data['id']) : '';
		if ($id === '') {
			http_response_code(400);
			die(json_encode(['error' => 'id is required']));
		}

		try {
			$role = $this->getCurrentUserRole();
			$isAdmin = $role === 1;

			if ($isAdmin) {
				$deleteMonsterStmt = $this->db->prepare('DELETE FROM monsters WHERE id = :monsterId');
				$deletePreviewStmt = $this->db->prepare('DELETE FROM monsters_preview WHERE id = :monsterId');
				$deleteMonsterStmt->bindValue(':monsterId', $id);
				$deletePreviewStmt->bindValue(':monsterId', $id);
				$deleteMonsterStmt->execute();
				$deletePreviewStmt->execute();
			} else {
				$ownerId = (int) $this->decoded->id;
				$deleteMonsterStmt = $this->db->prepare('DELETE FROM monsters WHERE id = :monsterId AND user_id = :userId');
				$deletePreviewStmt = $this->db->prepare('DELETE FROM monsters_preview WHERE id = :monsterId AND user_id = :userId');
				$deleteMonsterStmt->bindValue(':monsterId', $id);
				$deleteMonsterStmt->bindValue(':userId', $ownerId);
				$deletePreviewStmt->bindValue(':monsterId', $id);
				$deletePreviewStmt->bindValue(':userId', $ownerId);
				$deleteMonsterStmt->execute();
				$deletePreviewStmt->execute();
			}

			echo json_encode(['success' => true]);
		} catch (\PDOException $e) {
			http_response_code(500);
			die(json_encode(['error' => 'DB error']));
		}
	}
}
