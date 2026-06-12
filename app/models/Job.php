<?php
// app/models/Job.php
require_once __DIR__ . '/Database.php';

class Job extends Database {
    private array $allowedStatuses = ['draft', 'published', 'closed'];

    private function normalizeOptionalString($value): ?string {
        if ($value === null) {
            return null;
        }
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private function normalizeQuantity($quantity): ?int {
        if ($quantity === null || $quantity === '') {
            return null;
        }
        $quantity = (int)$quantity;
        return $quantity > 0 ? $quantity : null;
    }

    private function normalizeDeadline($deadline): ?string {
        if ($deadline === null) {
            return null;
        }
        $deadline = trim((string)$deadline);
        if ($deadline === '') {
            return null;
        }
    $date = \DateTime::createFromFormat('Y-m-d', $deadline);
        if ($date === false) {
            return null;
        }
        return $date->format('Y-m-d');
    }

    private function normalizeLongText($value): ?string {
        if ($value === null) {
            return null;
        }
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    public static function isExpired(array $job): bool {
        $deadline = $job['deadline'] ?? null;
        if ($deadline === null || $deadline === '') {
            return false;
        }
        $timestamp = strtotime($deadline);
        if ($timestamp === false) {
            return false;
        }
        return $timestamp < strtotime('today');
    }

    public static function isActive(array $job): bool {
        return ($job['status'] ?? '') === 'published' && !self::isExpired($job);
    }

    public function recordView(int $jobId, ?string $viewerIp = null): bool {
        $jobId = (int)$jobId;
        if ($jobId <= 0) {
            return false;
        }

        $viewerIp = $viewerIp !== null ? trim($viewerIp) : null;
        if ($viewerIp === '') {
            $viewerIp = null;
        }
        if ($viewerIp !== null && strlen($viewerIp) > 45) {
            $viewerIp = substr($viewerIp, 0, 45);
        }

        $sql = "INSERT INTO job_views (job_id, viewer_ip, viewed_at) VALUES (?, ?, NOW())";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return false;
        }

        $jobIdParam = $jobId;
        $viewerIpParam = $viewerIp;
        $stmt->bind_param('is', $jobIdParam, $viewerIpParam);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    public function create($employer_id, $title, $description, $jobRequirements = null, $location = null, $salary = null, $employment_type = null, $status = 'draft', $quantity = null, $deadline = null) {
        $status = in_array($status, $this->allowedStatuses, true) ? $status : 'draft';
        $location = $this->normalizeOptionalString($location);
        $salary = $this->normalizeOptionalString($salary);
        $employment_type = $this->normalizeOptionalString($employment_type);
        $quantity = $this->normalizeQuantity($quantity);
        $deadline = $this->normalizeDeadline($deadline);
        $jobRequirements = $this->normalizeLongText($jobRequirements);

        $sql = "INSERT INTO jobs (employer_id, title, description, job_requirements, location, salary, employment_type, status, quantity, deadline, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?, NOW())";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return false;
        }
        $stmt->bind_param(
            'isssssssis',
            $employer_id,
            $title,
            $description,
            $jobRequirements,
            $location,
            $salary,
            $employment_type,
            $status,
            $quantity,
            $deadline
        );
        if ($stmt->execute()) {
            $insertId = $this->conn->insert_id;
            $stmt->close();
            return $insertId;
        }

        $stmt->close();
        return false;
    }

    public function getAllCategories(): array {
        $sql = "SELECT id, name, description FROM job_categories ORDER BY name ASC";
        $result = $this->conn->query($sql);
        if (!$result) {
            return [];
        }
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $categories[] = [
                    'id' => $id,
                    'name' => (string)($row['name'] ?? ''),
                    'description' => $row['description'] ?? null,
                ];
            }
        }
        $result->free();
        return $categories;
    }

    public function getCategoryIdsForJob(int $jobId): array {
        $jobId = (int)$jobId;
        if ($jobId <= 0) {
            return [];
        }
        $stmt = $this->conn->prepare("SELECT category_id FROM job_category_map WHERE job_id = ?");
        if ($stmt === false) {
            return [];
        }
        $stmt->bind_param('i', $jobId);
        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }
        $result = $stmt->get_result();
        $ids = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $categoryId = (int)($row['category_id'] ?? 0);
                if ($categoryId > 0) {
                    $ids[] = $categoryId;
                }
            }
            $result->free();
        }
        $stmt->close();
        return array_values(array_unique($ids));
    }

    public function getCategoriesForJob(int $jobId): array {
        $map = $this->getCategoriesForJobs([$jobId]);
        return $map[$jobId] ?? [];
    }

    public function getCategoriesForJobs(array $jobIds): array {
        if (empty($jobIds)) {
            return [];
        }
        $jobIds = array_values(array_unique(array_map('intval', $jobIds)));
        $jobIds = array_filter($jobIds, static function ($value) {
            return $value > 0;
        });
        if (empty($jobIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
        $types = str_repeat('i', count($jobIds));
        $sql = "SELECT m.job_id, c.id AS category_id, c.name
                FROM job_category_map m
                INNER JOIN job_categories c ON c.id = m.category_id
                WHERE m.job_id IN ($placeholders)
                ORDER BY c.name ASC";

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return [];
        }

        $stmt->bind_param($types, ...$jobIds);
        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }

        $result = $stmt->get_result();
        $map = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $jobId = (int)($row['job_id'] ?? 0);
                $categoryId = (int)($row['category_id'] ?? 0);
                $name = (string)($row['name'] ?? '');
                if ($jobId > 0 && $categoryId > 0) {
                    if (!isset($map[$jobId])) {
                        $map[$jobId] = [];
                    }
                    $map[$jobId][] = [
                        'id' => $categoryId,
                        'name' => $name,
                    ];
                }
            }
            $result->free();
        }
        $stmt->close();
        return $map;
    }

    public function syncCategories(int $jobId, array $categoryIds): bool {
        $jobId = (int)$jobId;
        if ($jobId <= 0) {
            return false;
        }

        $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));
        $categoryIds = array_filter($categoryIds, static function ($value) {
            return $value > 0;
        });

        $validIds = [];
        if (!empty($categoryIds)) {
            $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
            $types = str_repeat('i', count($categoryIds));
            $sql = "SELECT id FROM job_categories WHERE id IN ($placeholders)";
            $stmt = $this->conn->prepare($sql);
            if ($stmt === false) {
                return false;
            }
            $stmt->bind_param($types, ...$categoryIds);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $id = (int)($row['id'] ?? 0);
                        if ($id > 0) {
                            $validIds[] = $id;
                        }
                    }
                    $result->free();
                }
            }
            $stmt->close();
        }

        $deleteStmt = $this->conn->prepare("DELETE FROM job_category_map WHERE job_id = ?");
        if ($deleteStmt === false) {
            return false;
        }
        $deleteStmt->bind_param('i', $jobId);
        $deleteStmt->execute();
        $deleteStmt->close();

        if (empty($validIds)) {
            return true;
        }

        $insertStmt = $this->conn->prepare("INSERT INTO job_category_map (job_id, category_id) VALUES (?, ?)");
        if ($insertStmt === false) {
            return false;
        }
        $jobIdParam = $jobId;
        $categoryIdParam = 0;
        $insertStmt->bind_param('ii', $jobIdParam, $categoryIdParam);
        foreach ($validIds as $categoryId) {
            $categoryIdParam = $categoryId;
            if (!$insertStmt->execute()) {
                $insertStmt->close();
                return false;
            }
        }
        $insertStmt->close();
        return true;
    }

    public function update($job_id, $employer_id, $title, $description, $jobRequirements = null, $location = null, $salary = null, $employment_type = null, $status = 'draft', $quantity = null, $deadline = null) {
        $status = in_array($status, $this->allowedStatuses, true) ? $status : 'draft';
        $location = $this->normalizeOptionalString($location);
        $salary = $this->normalizeOptionalString($salary);
        $employment_type = $this->normalizeOptionalString($employment_type);
        $quantity = $this->normalizeQuantity($quantity);
        $deadline = $this->normalizeDeadline($deadline);
        $jobRequirements = $this->normalizeLongText($jobRequirements);

        $sql = "UPDATE jobs SET title = ?, description = ?, job_requirements = ?, location = ?, salary = ?, employment_type = ?, status = ?, quantity = ?, deadline = ?, updated_at = NOW() WHERE id = ? AND employer_id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return false;
        }
        $stmt->bind_param(
            'sssssssisii',
            $title,
            $description,
            $jobRequirements,
            $location,
            $salary,
            $employment_type,
            $status,
            $quantity,
            $deadline,
            $job_id,
            $employer_id
        );
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function getByEmployer($employer_id) {
        $stmt = $this->conn->prepare("SELECT * FROM jobs WHERE employer_id = ? ORDER BY updated_at DESC, created_at DESC");
        $stmt->bind_param("i", $employer_id);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getByEmployerPaginated(int $employerId, int $page = 1, int $perPage = 10): array {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $response = [
            'rows' => [],
            'total' => 0,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => 1,
            'query_error' => null,
        ];

        if ($employerId <= 0) {
            return $response;
        }

        $countSql = "SELECT COUNT(*) AS total FROM jobs WHERE employer_id = ?";
        $countStmt = $this->conn->prepare($countSql);
        if ($countStmt === false) {
            $response['query_error'] = $this->conn->error;
            return $response;
        }

        $countStmt->bind_param('i', $employerId);
        if ($countStmt->execute()) {
            $countResult = $countStmt->get_result();
            if ($countResult) {
                $row = $countResult->fetch_assoc();
                $response['total'] = (int)($row['total'] ?? 0);
                $countResult->free();
            }
        } else {
            $response['query_error'] = $countStmt->error;
        }
        $countStmt->close();

        $total = $response['total'];
        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;
        if ($totalPages < 1) {
            $totalPages = 1;
        }
        if ($total > 0 && $page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $response['page'] = $page;
        $response['total_pages'] = $totalPages;

        if ($response['query_error'] !== null) {
            return $response;
        }

        $dataSql = "SELECT *
                    FROM jobs
                    WHERE employer_id = ?
                    ORDER BY updated_at DESC, created_at DESC
                    LIMIT ? OFFSET ?";

        $dataStmt = $this->conn->prepare($dataSql);
        if ($dataStmt === false) {
            $response['query_error'] = $this->conn->error;
            return $response;
        }

        $dataStmt->bind_param('iii', $employerId, $perPage, $offset);
        if ($dataStmt->execute()) {
            $result = $dataStmt->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $response['rows'][] = $row;
                }
                $result->free();
            }
        } else {
            $response['query_error'] = $dataStmt->error;
        }
        $dataStmt->close();

        return $response;
    }

    public function getAdminList(array $filters = [], int $page = 1, int $perPage = 20): array {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $keyword = isset($filters['keyword']) ? trim((string)$filters['keyword']) : '';
        $status = isset($filters['status']) ? trim((string)$filters['status']) : '';
        $allowedStatuses = $this->allowedStatuses;
        if (!in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        $baseSql = "SELECT j.*, e.company_name, u.email AS employer_email
                FROM jobs j
                INNER JOIN employers e ON e.id = j.employer_id
                LEFT JOIN users u ON u.id = e.user_id
                WHERE 1 = 1";

        $types = '';
        $params = [];

        if ($keyword !== '') {
            $baseSql .= " AND (j.title LIKE ? OR e.company_name LIKE ? OR u.email LIKE ?)";
            $like = '%' . $keyword . '%';
            $types .= 'sss';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($status !== '') {
            $baseSql .= " AND j.status = ?";
            $types .= 's';
            $params[] = $status;
        }

        $baseSql .= " ORDER BY j.updated_at DESC, j.created_at DESC";

        $rows = [];
        $queryError = null;
        $total = 0;

        $countSql = "SELECT COUNT(*) AS total FROM (" . $baseSql . ") AS job_admin_count";

        if ($types === '') {
            $countResult = $this->conn->query($countSql);
            if ($countResult === false) {
                $queryError = $this->conn->error;
            } else {
                $row = $countResult->fetch_assoc();
                $total = (int)($row['total'] ?? 0);
                $countResult->free();
            }
        } else {
            $countStmt = $this->conn->prepare($countSql);
            if ($countStmt === false) {
                $queryError = $this->conn->error;
            } else {
                $countStmt->bind_param($types, ...$params);
                if ($countStmt->execute()) {
                    $countResult = $countStmt->get_result();
                    if ($countResult) {
                        $row = $countResult->fetch_assoc();
                        $total = (int)($row['total'] ?? 0);
                        $countResult->free();
                    }
                } else {
                    $queryError = $countStmt->error;
                }
                $countStmt->close();
            }
        }

        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;
        if ($totalPages < 1) {
            $totalPages = 1;
        }
        if ($total > 0 && $page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        if ($queryError === null) {
            $dataSql = $baseSql . ' LIMIT ? OFFSET ?';
            $dataTypes = $types . 'ii';
            $dataParams = $params;
            $dataParams[] = $perPage;
            $dataParams[] = $offset;

            $dataStmt = $this->conn->prepare($dataSql);
            if ($dataStmt === false) {
                $queryError = $this->conn->error;
            } else {
                $dataStmt->bind_param($dataTypes, ...$dataParams);
                if ($dataStmt->execute()) {
                    $result = $dataStmt->get_result();
                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            $rows[] = $row;
                        }
                        $result->free();
                    }
                } else {
                    $queryError = $dataStmt->error;
                }
                $dataStmt->close();
            }
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'query_error' => $queryError,
            'applied_filters' => [
                'keyword' => $keyword,
                'status' => $status,
            ],
        ];
    }

    public function updateStatus($job_id, $status) {
        $status = in_array($status, $this->allowedStatuses, true) ? $status : null;
        if (!$status) {
            return false;
        }
        $sql = "UPDATE jobs SET status = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return false;
        }
        $stmt->bind_param('si', $status, $job_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function countByStatus() {
        $sql = "SELECT status, COUNT(*) AS total FROM jobs GROUP BY status";
        $result = $this->conn->query($sql);
        $counts = ['draft' => 0, 'published' => 0, 'closed' => 0];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $status = $row['status'] ?? '';
                if (isset($counts[$status])) {
                    $counts[$status] = (int)$row['total'];
                }
            }
            $result->free();
        }
        return $counts;
    }

    public function countPublished() {
        $sql = "SELECT COUNT(*) AS total FROM jobs WHERE status = 'published' AND (deadline IS NULL OR deadline >= CURDATE())";
        $result = $this->conn->query($sql);
        if ($result && ($row = $result->fetch_assoc())) {
            return (int)$row['total'];
        }
        return 0;
    }

    public function getFeaturedJobs($limit = 8) {
        $limit = max(1, (int)$limit);
        $sql = "SELECT j.id, j.title, j.location, j.salary, j.employment_type, j.quantity, j.deadline, j.created_at,
                       e.company_name
                FROM jobs j
                INNER JOIN employers e ON e.id = j.employer_id
                WHERE j.status = 'published' AND (j.deadline IS NULL OR j.deadline >= CURDATE())
                ORDER BY j.created_at DESC
                LIMIT $limit";
        $result = $this->conn->query($sql);
        if (!$result) {
            return [];
        }
        $jobs = [];
        while ($row = $result->fetch_assoc()) {
            $jobs[] = $row;
        }
        return $jobs;
    }

    public function getTopCategories($limit = 6) {
        $limit = max(1, (int)$limit);
        $sql = "SELECT c.id, c.name,
                       COALESCE(SUM(CASE WHEN j.status = 'published' THEN 1 ELSE 0 END), 0) AS job_count
                FROM job_categories c
                LEFT JOIN job_category_map m ON m.category_id = c.id
                LEFT JOIN jobs j ON j.id = m.job_id
                GROUP BY c.id
                ORDER BY job_count DESC, c.name ASC
                LIMIT $limit";
        $result = $this->conn->query($sql);
        if (!$result) {
            return [];
        }
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        return $categories;
    }

    public function getPopularKeywords($limit = 6) {
        $limit = max(1, (int)$limit);
    $sql = "SELECT title, COUNT(*) AS total, MAX(created_at) AS latest_posted
        FROM jobs
        WHERE status = 'published'
        GROUP BY title
        HAVING title IS NOT NULL AND title <> ''
        ORDER BY total DESC, latest_posted DESC
        LIMIT $limit";
        $result = $this->conn->query($sql);
        if (!$result) {
            return [];
        }
        $keywords = [];
        while ($row = $result->fetch_assoc()) {
            $keywords[] = $row['title'];
        }
        return $keywords;
    }

    public function getHotJobs(int $limit = 12, array $options = []): array {
        $limit = max(1, $limit);
        $withinDays = isset($options['within_days']) ? max(0, (int)$options['within_days']) : 30;
        $publishedOnly = !isset($options['published_only']) || (bool)$options['published_only'];
        $employerId = isset($options['employer_id']) ? (int)$options['employer_id'] : 0;
        $excludeEmployerIds = [];
        if (isset($options['exclude_employer_id'])) {
            $rawExclude = $options['exclude_employer_id'];
            if (is_array($rawExclude)) {
                foreach ($rawExclude as $value) {
                    $value = (int)$value;
                    if ($value > 0) {
                        $excludeEmployerIds[] = $value;
                    }
                }
            } else {
                $value = (int)$rawExclude;
                if ($value > 0) {
                    $excludeEmployerIds[] = $value;
                }
            }
        }

        $joinConditions = 'v.job_id = j.id';
        $types = '';
        $params = [];

        if ($withinDays > 0) {
            $joinConditions .= ' AND v.viewed_at >= ?';
            $types .= 's';
            $params[] = date('Y-m-d H:i:s', strtotime('-' . $withinDays . ' days'));
        }

        $sql = "SELECT
                    j.id,
                    j.title,
                    j.location,
                    j.salary,
                    j.employment_type,
                    j.quantity,
                    j.deadline,
                    j.created_at,
                    e.company_name,
                    e.logo_path,
                    COUNT(v.id) AS view_count,
                    MAX(v.viewed_at) AS last_viewed_at
                FROM jobs j
                INNER JOIN employers e ON e.id = j.employer_id
                LEFT JOIN job_views v ON " . $joinConditions;

        $conditions = [];
        if ($publishedOnly) {
            $conditions[] = "j.status = 'published'";
            $conditions[] = "(j.deadline IS NULL OR j.deadline >= CURDATE())";
        }
        if ($employerId > 0) {
            $conditions[] = 'j.employer_id = ?';
            $types .= 'i';
            $params[] = $employerId;
        }
        if (!empty($excludeEmployerIds)) {
            $placeholders = implode(',', array_fill(0, count($excludeEmployerIds), '?'));
            $conditions[] = "j.employer_id NOT IN ($placeholders)";
            $types .= str_repeat('i', count($excludeEmployerIds));
            foreach ($excludeEmployerIds as $excludeId) {
                $params[] = $excludeId;
            }
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

    $sql .= "
        GROUP BY j.id, j.title, j.location, j.salary, j.employment_type, j.quantity, j.deadline, j.created_at, e.company_name, e.logo_path
                ORDER BY view_count DESC, last_viewed_at DESC, j.created_at DESC
                LIMIT ?";

        $types .= 'i';
        $params[] = $limit;

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return [];
        }

        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }

        $result = $stmt->get_result();
        $jobs = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['view_count'] = (int)($row['view_count'] ?? 0);
                $jobs[] = $row;
            }
            $result->free();
        }

        $stmt->close();
        return $jobs;
    }

    public function getPublishedByEmployer(int $employerId, int $limit = 0): array {
        if ($employerId <= 0) {
            return [];
        }

        $limit = max(0, $limit);

        $sql = "SELECT
                    j.id,
                    j.title,
                    j.location,
                    j.salary,
                    j.employment_type,
                    j.quantity,
                    j.deadline,
                    j.created_at,
                    j.updated_at,
                    COUNT(v.id) AS view_count,
                    MAX(v.viewed_at) AS last_viewed_at
                FROM jobs j
                LEFT JOIN job_views v ON v.job_id = j.id
                WHERE j.employer_id = ? AND j.status = 'published' AND (j.deadline IS NULL OR j.deadline >= CURDATE())
                GROUP BY j.id, j.title, j.location, j.salary, j.employment_type, j.quantity, j.deadline, j.created_at, j.updated_at
                ORDER BY COALESCE(j.updated_at, j.created_at) DESC, j.created_at DESC";

        $types = 'i';
        $params = [$employerId];

        if ($limit > 0) {
            $sql .= ' LIMIT ?';
            $types .= 'i';
            $params[] = $limit;
        }

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return [];
        }

        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }

        $result = $stmt->get_result();
        $jobs = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['view_count'] = (int)($row['view_count'] ?? 0);
                $jobs[] = $row;
            }
            $result->free();
        }

        $stmt->close();
        return $jobs;
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM jobs WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function delete($id, $employer_id) {
        $stmt = $this->conn->prepare("DELETE FROM jobs WHERE id = ? AND employer_id = ?");
        $stmt->bind_param("ii", $id, $employer_id);
        return $stmt->execute();
    }

    public function getApplicants($job_id) {
        $stmt = $this->conn->prepare("SELECT a.*, c.user_id, u.email FROM applications a JOIN candidates c ON c.id = a.candidate_id JOIN users u ON u.id = c.user_id WHERE a.job_id = ? ORDER BY a.applied_at DESC");
        $stmt->bind_param("i", $job_id);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getSmartRecommendations(array $context, int $limit = 6): array {
        $candidateId = (int)($context['candidate_id'] ?? 0);
        if ($candidateId <= 0) {
            return [];
        }

        $limit = max(1, min(20, $limit));
        $locationPatterns = is_array($context['location_patterns'] ?? null) ? $context['location_patterns'] : [];
        $skills = is_array($context['skills'] ?? null) ? $context['skills'] : [];
        $preferredCategories = is_array($context['preferred_categories'] ?? null) ? $context['preferred_categories'] : [];

        $scoreComponents = ['5'];
        $params = [];
        $types = '';

        if (!empty($locationPatterns)) {
            $likeParts = [];
            foreach ($locationPatterns as $pattern) {
                $likeParts[] = "COALESCE(j.location, '') LIKE ?";
                $params[] = $pattern;
                $types .= 's';
            }
            if (!empty($likeParts)) {
                $scoreComponents[] = 'CASE WHEN (' . implode(' OR ', $likeParts) . ') THEN 35 ELSE 0 END';
            }
        }

        $skillCount = 0;
        foreach ($skills as $skill) {
            $skill = trim((string)$skill);
            if ($skill === '') {
                continue;
            }
            $skillCount++;
            if ($skillCount > 6) {
                break;
            }
            $scoreComponents[] = 'CASE WHEN (COALESCE(j.title, \'\') LIKE ? OR COALESCE(j.description, \'\') LIKE ? OR COALESCE(j.job_requirements, \'\') LIKE ?) THEN 15 ELSE 0 END';
            $like = '%' . $skill . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types .= 'sss';
        }

        $categoryJoinSql = '';
        $categorySelect = '0 AS matched_categories';
        if (!empty($preferredCategories)) {
            $categoryIds = array_values(array_filter(array_map('intval', $preferredCategories), static function ($value) {
                return $value > 0;
            }));
            if (!empty($categoryIds)) {
                $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
                $categoryJoinSql = "LEFT JOIN (SELECT job_id, COUNT(*) AS matched_categories FROM job_category_map WHERE category_id IN ($placeholders) GROUP BY job_id) prefCats ON prefCats.job_id = j.id";
                foreach ($categoryIds as $categoryId) {
                    $params[] = $categoryId;
                    $types .= 'i';
                }
                $categorySelect = 'COALESCE(prefCats.matched_categories, 0) AS matched_categories';
                $scoreComponents[] = 'COALESCE(prefCats.matched_categories, 0) * 20';
            }
        }

        $scoreSql = implode(' + ', $scoreComponents);

        $sql = "SELECT
                    j.id,
                    j.employer_id,
                    j.title,
                    j.description,
                    j.job_requirements,
                    j.location,
                    j.salary,
                    j.employment_type,
                    j.quantity,
                    j.deadline,
                    j.created_at,
                    j.updated_at,
                    e.company_name,
                    $categorySelect,
                    $scoreSql AS relevance_score
                FROM jobs j
                INNER JOIN employers e ON e.id = j.employer_id
                $categoryJoinSql
                LEFT JOIN applications a ON a.job_id = j.id AND a.candidate_id = ? AND a.status != 'withdrawn'
                LEFT JOIN saved_jobs sj ON sj.job_id = j.id AND sj.candidate_id = ?
                WHERE j.status = 'published'
                  AND (j.deadline IS NULL OR j.deadline >= CURDATE())
                  AND a.id IS NULL
                  AND sj.id IS NULL
                ORDER BY relevance_score DESC, j.updated_at DESC, j.created_at DESC
                LIMIT ?";

        $params[] = $candidateId;
        $params[] = $candidateId;
        $params[] = $limit;
        $types .= 'iii';

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return [];
        }

        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }

        $result = $stmt->get_result();
        $jobs = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $jobs[] = $row;
            }
            $result->free();
        }
        $stmt->close();
        return $jobs;
    }

    public function getFallbackRecommendations(int $limit = 6): array {
        $limit = max(1, min(20, $limit));
        $sql = "SELECT
                    j.id,
                    j.employer_id,
                    j.title,
                    j.description,
                    j.job_requirements,
                    j.location,
                    j.salary,
                    j.employment_type,
                    j.quantity,
                    j.deadline,
                    j.created_at,
                    j.updated_at,
                    e.company_name,
                    0 AS matched_categories,
                    0 AS relevance_score
                FROM jobs j
                INNER JOIN employers e ON e.id = j.employer_id
                WHERE j.status = 'published'
                  AND (j.deadline IS NULL OR j.deadline >= CURDATE())
                ORDER BY COALESCE(j.updated_at, j.created_at) DESC
                LIMIT ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return [];
        }
        $stmt->bind_param('i', $limit);
        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }
        $result = $stmt->get_result();
        $jobs = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $jobs[] = $row;
            }
            $result->free();
        }
        $stmt->close();
        return $jobs;
    }
}

