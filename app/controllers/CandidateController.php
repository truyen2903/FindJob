<?php
// app/controllers/CandidateController.php
require_once __DIR__ . '/../models/Candidate.php';
require_once __DIR__ . '/../models/Job.php';
require_once __DIR__ . '/../models/Database.php';

class CandidateController {
    protected $candidateModel;
    protected $jobModel;

    public function __construct() {
        $this->candidateModel = new Candidate();
        $this->jobModel = new Job();
    }

    public function getProfile($user_id) {
        return $this->candidateModel->getByUserId($user_id);
    }

    public function saveProfile($user_id, $headline, $summary, $location, $skills = null, $experience = null) {
        return $this->candidateModel->createOrUpdate($user_id, $headline, $summary, $location, $skills, $experience);
    }

    public function applyToJob($user_id, $job_id, $cover_letter = null, $resume_snapshot = null) {
        // get candidate id, create if necessary
        $cand = $this->candidateModel->getByUserId($user_id);
        if (!$cand) {
            // create a basic candidate entry
            $this->candidateModel->createOrUpdate($user_id, null, null, null, null, null);
            $cand = $this->candidateModel->getByUserId($user_id);
        }
        $candidate_id = $cand['id'];
        $db = new Database();
        $stmt = $db->conn->prepare("INSERT INTO applications (job_id, candidate_id, cover_letter, resume_snapshot) VALUES (?,?,?,?)");
        $stmt->bind_param("iiss", $job_id, $candidate_id, $cover_letter, $resume_snapshot);
        if ($stmt->execute()) return $db->conn->insert_id;
        return false;
    }
}

