<?php
// app/controllers/JobController.php
require_once __DIR__ . '/../models/Employer.php';
require_once __DIR__ . '/../models/Job.php';

class JobController {
    protected $employerModel;
    protected $jobModel;

    public function __construct() {
        $this->employerModel = new Employer();
        $this->jobModel = new Job();
    }

    public function ensureEmployer($user_id) {
        $employer = $this->employerModel->getByUserId($user_id);
        if (!$employer) {
            // create a basic employer record using user's email as company name
            $company_name = 'Company of user ' . $user_id;
            $this->employerModel->createForUser($user_id, $company_name);
            return $this->employerModel->getByUserId($user_id);
        }
        return $employer;
    }

    public function listJobs($user_id, int $page = 1, int $perPage = 10) {
        $emp = $this->ensureEmployer($user_id);
        if (!$emp) {
            return [
                'rows' => [],
                'total' => 0,
                'page' => max(1, $page),
                'per_page' => max(1, $perPage),
                'total_pages' => 1,
                'query_error' => 'Không tìm thấy nhà tuyển dụng phù hợp.'
            ];
        }
        return $this->jobModel->getByEmployerPaginated($emp['id'], $page, $perPage);
    }

    public function createJob($user_id, $title, $description, $jobRequirements = null, $location = null, $salary = null, $employment_type = null, $status = 'draft', $quantity = null, $deadline = null, array $categoryIds = []) {
        $emp = $this->ensureEmployer($user_id);
        if (!$emp) {
            return false;
        }
        $jobId = $this->jobModel->create($emp['id'], $title, $description, $jobRequirements, $location, $salary, $employment_type, 'draft', $quantity, $deadline);
        if (!$jobId) {
            return false;
        }
        $this->jobModel->syncCategories((int)$jobId, $categoryIds);
        return $jobId;
    }

    public function updateJob($user_id, $job_id, $title, $description, $jobRequirements = null, $location = null, $salary = null, $employment_type = null, $status = 'draft', $quantity = null, $deadline = null, array $categoryIds = []) {
        $emp = $this->ensureEmployer($user_id);
        if (!$emp) {
            return false;
        }
        $job = $this->jobModel->getById($job_id);
        if (!$job || (int)$job['employer_id'] !== (int)$emp['id']) {
            return false;
        }
        $currentStatus = $job['status'] ?? 'draft';
        if ($status !== $currentStatus) {
            $status = $currentStatus;
        }
        $updated = $this->jobModel->update($job_id, $emp['id'], $title, $description, $jobRequirements, $location, $salary, $employment_type, $status, $quantity, $deadline);
        if ($updated) {
            $this->jobModel->syncCategories((int)$job_id, $categoryIds);
        }
        return $updated;
    }

    public function getCategories(): array {
        return $this->jobModel->getAllCategories();
    }

    public function getCategoryIdsForJob(int $jobId): array {
        return $this->jobModel->getCategoryIdsForJob($jobId);
    }

    public function getCategoriesForJobs(array $jobIds): array {
        return $this->jobModel->getCategoriesForJobs($jobIds);
    }

    public function getJobForEmployer($user_id, $job_id) {
        $emp = $this->ensureEmployer($user_id);
        if (!$emp) {
            return null;
        }
        $job = $this->jobModel->getById($job_id);
        if (!$job || (int)$job['employer_id'] !== (int)$emp['id']) {
            return null;
        }
        return $job;
    }

    public function deleteJob($user_id, $job_id) {
        $emp = $this->ensureEmployer($user_id);
        return $this->jobModel->delete($job_id, $emp['id']);
    }

    public function viewApplicants($user_id, $job_id) {
        $emp = $this->ensureEmployer($user_id);
        $job = $this->jobModel->getById($job_id);
        if (!$job || $job['employer_id'] != $emp['id']) return false;
        return $this->jobModel->getApplicants($job_id);
    }
}

