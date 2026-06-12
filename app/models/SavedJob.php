<?php
// app/models/SavedJob.php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Candidate.php';

class SavedJob extends Database {
	private Candidate $candidateModel;

	public function __construct() {
		parent::__construct();
		$this->candidateModel = new Candidate();
	}

	private function resolveCandidateId(int $user_id, bool $autoCreate = false): ?int {
		$candidate = $this->candidateModel->getByUserId($user_id);
		if ($candidate) {
			return (int)$candidate['id'];
		}
		if ($autoCreate) {
			$this->candidateModel->createOrUpdate($user_id, null, null, null, null, null);
			$candidate = $this->candidateModel->getByUserId($user_id);
			if ($candidate) {
				return (int)$candidate['id'];
			}
		}
		return null;
	}

	public function saveForUser(int $user_id, int $job_id): bool {
		$candidateId = $this->resolveCandidateId($user_id, true);
		if (!$candidateId) {
			return false;
		}
		$sql = "INSERT INTO saved_jobs (candidate_id, job_id) VALUES (?, ?) 
				ON DUPLICATE KEY UPDATE saved_at = CURRENT_TIMESTAMP";
		$stmt = $this->conn->prepare($sql);
		if ($stmt === false) {
			return false;
		}
		$stmt->bind_param('ii', $candidateId, $job_id);
		$result = $stmt->execute();
		$stmt->close();
		return $result;
	}

	public function removeForUser(int $user_id, int $job_id): bool {
		$candidateId = $this->resolveCandidateId($user_id, false);
		if (!$candidateId) {
			return false;
		}
		$stmt = $this->conn->prepare('DELETE FROM saved_jobs WHERE candidate_id = ? AND job_id = ?');
		if ($stmt === false) {
			return false;
		}
		$stmt->bind_param('ii', $candidateId, $job_id);
		$result = $stmt->execute();
		$stmt->close();
		return $result;
	}

	public function isSavedByUser(int $user_id, int $job_id): bool {
		$candidateId = $this->resolveCandidateId($user_id, false);
		if (!$candidateId) {
			return false;
		}
		$stmt = $this->conn->prepare('SELECT 1 FROM saved_jobs WHERE candidate_id = ? AND job_id = ? LIMIT 1');
		if ($stmt === false) {
			return false;
		}
		$stmt->bind_param('ii', $candidateId, $job_id);
		$stmt->execute();
		$stmt->store_result();
		$isSaved = $stmt->num_rows > 0;
		$stmt->free_result();
		$stmt->close();
		return $isSaved;
	}

	public function getSavedJobIdsForUser(int $user_id): array {
		$candidateId = $this->resolveCandidateId($user_id, false);
		if (!$candidateId) {
			return [];
		}
		$stmt = $this->conn->prepare('SELECT job_id FROM saved_jobs WHERE candidate_id = ?');
		if ($stmt === false) {
			return [];
		}
		$stmt->bind_param('i', $candidateId);
		$stmt->execute();
		$result = $stmt->get_result();
		$ids = [];
		if ($result) {
			while ($row = $result->fetch_assoc()) {
				$ids[] = (int)$row['job_id'];
			}
			$result->free();
		}
		$stmt->close();
		return $ids;
	}
}

