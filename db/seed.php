<?php
// db/seed.php - run from PHP CLI or include in browser for development seeding
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/Employer.php';
require_once __DIR__ . '/../app/models/Candidate.php';
require_once __DIR__ . '/../app/models/Job.php';

$userModel = new User();
$employerModel = new Employer();
$candidateModel = new Candidate();
$jobModel = new Job();

function ensureUser($email, $password, $role_id, $name = null) {
    global $userModel;
    $u = $userModel->findByEmail($email);
    if ($u) {
        echo "User exists: $email (id={$u['id']})\n";
        return $u['id'];
    }
    $id = $userModel->create($email, $password, $role_id, $name);
    echo "Created user: $email (id={$id})\n";
    return $id;
}

// Create admin, employer, candidate
$adminId = ensureUser('admin@local', 'admin123', 1, 'Admin');
$empUserId = ensureUser('employer@local', 'employer123', 2, 'Employer User');
$candUserId = ensureUser('candidate@local', 'candidate123', 3, 'Candidate User');

// Ensure employer profile
$emp = $employerModel->getByUserId($empUserId);
if (!$emp) {
    $empId = $employerModel->createForUser($empUserId, 'Example Company', 'https://example.com', 'Hanoi', 'About Example Company');
    echo "Created employer profile id=$empId\n";
} else {
    $empId = $emp['id'];
    echo "Employer profile exists id={$empId}\n";
}

// Ensure candidate profile
$cand = $candidateModel->getByUserId($candUserId);
if (!$cand) {
    $candidateModel->createOrUpdate($candUserId, 'Junior Developer', 'Enthusiastic dev', 'Da Nang', ['PHP','MySQL'], null);
    echo "Created candidate profile for user_id=$candUserId\n";
} else echo "Candidate profile exists id={$cand['id']}\n";

// Create a sample job
$jobs = $jobModel->getByEmployer($empId);
$hasJob = false;
while ($j = $jobs->fetch_assoc()) {
    if (stripos($j['title'], 'Example') !== false) { $hasJob = true; break; }
}
if (!$hasJob) {
    $jobId = $jobModel->create(
        $empId,
        'Example PHP Developer',
        "We need a PHP developer to join our team.",
        "2+ years of PHP/Laravel experience, solid MySQL knowledge, teamwork mindset.",
        'Hanoi',
        '10-20M',
        'Full-time',
        'published',
        3,
        date('Y-m-d', strtotime('+30 days'))
    );
    if ($jobId) {
        $seedCategories = $jobModel->getAllCategories();
        if (!empty($seedCategories)) {
            $firstCategoryId = (int)($seedCategories[0]['id'] ?? 0);
            if ($firstCategoryId > 0) {
                $jobModel->syncCategories((int)$jobId, [$firstCategoryId]);
            }
        }
    }
    echo "Created sample job id=$jobId\n";
} else echo "Sample job already exists\n";

echo "Seeding complete.\n";
