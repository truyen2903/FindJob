<?php
// app/models/Employer.php
require_once __DIR__ . '/Database.php';

class Employer extends Database {

    /** 
     * Lấy 1 employer theo user_id 
     */
    public function getByUserId($user_id) {
        $stmt = $this->conn->prepare("SELECT * FROM employers WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Lấy 1 employer theo id
     */
    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM employers WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Lấy toàn bộ danh sách employer (join với users)
     */
    public function getAll() {
        $sql = "SELECT e.*, u.email AS user_email 
                FROM employers e 
                LEFT JOIN users u ON u.id = e.user_id 
                ORDER BY e.id DESC";
        return $this->conn->query($sql);
    }

    public function getAdminDashboardData(array $filters = []): array {
        $conn = $this->conn;

        $keyword = trim($filters['keyword'] ?? '');
        $location = trim($filters['location'] ?? '');
        $status = trim($filters['status'] ?? '');

        $allowedStatuses = ['has_jobs', 'no_jobs'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        $baseSql = "SELECT 
                        e.id,
                        e.user_id,
                        e.company_name,
                        e.website,
                        e.address,
                        e.about,
                        e.logo_path,
                        u.email AS user_email,
                        u.phone AS user_phone,
                        u.name AS user_name,
                        u.created_at AS user_created_at,
                        COUNT(j.id) AS total_jobs,
                        SUM(CASE WHEN j.status = 'published' THEN 1 ELSE 0 END) AS published_jobs,
                        SUM(CASE WHEN j.status = 'draft' THEN 1 ELSE 0 END) AS draft_jobs,
                        MAX(COALESCE(j.updated_at, j.created_at)) AS last_job_activity
                    FROM employers e
                    LEFT JOIN users u ON u.id = e.user_id
                    LEFT JOIN jobs j ON j.employer_id = e.id
                    WHERE 1 = 1";

        $types = '';
        $params = [];

        if ($keyword !== '') {
            $baseSql .= " AND (e.company_name LIKE ? OR u.email LIKE ? OR u.name LIKE ?)";
            $like = '%' . $keyword . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types .= 'sss';
        }

        if ($location !== '') {
            $baseSql .= " AND (e.address LIKE ? OR e.about LIKE ?)";
            $like = '%' . $location . '%';
            $params[] = $like;
            $params[] = $like;
            $types .= 'ss';
        }

        $baseSql .= "
                    GROUP BY e.id, e.user_id, e.company_name, e.website, e.address, e.about, e.logo_path,
                             u.email, u.phone, u.name, u.created_at";

        $sql = "SELECT * FROM (" . $baseSql . ") AS employer_stats";

        $outerConditions = [];
        if ($status === 'has_jobs') {
            $outerConditions[] = 'employer_stats.published_jobs > 0';
        } elseif ($status === 'no_jobs') {
            $outerConditions[] = 'employer_stats.published_jobs = 0';
        }

        if (!empty($outerConditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $outerConditions);
        }

        $sql .= ' ORDER BY COALESCE(employer_stats.last_job_activity, employer_stats.user_created_at) DESC';

        $rows = [];
        $queryError = null;

        if ($types === '') {
            $result = $conn->query($sql);
            if ($result === false) {
                $queryError = $conn->error;
            } else {
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
                $result->free();
            }
        } else {
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                $queryError = $conn->error;
            } else {
                $stmt->bind_param($types, ...$params);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    while ($result && ($row = $result->fetch_assoc())) {
                        $rows[] = $row;
                    }
                    if ($result) {
                        $result->free();
                    }
                } else {
                    $queryError = $stmt->error;
                }
                $stmt->close();
            }
        }

        $stats = [
            'total_employers' => 0,
            'active_employers' => 0,
            'inactive_employers' => 0,
            'total_published_jobs' => 0,
            'recent_active_employers' => 0
        ];

        $recentBenchmark = strtotime('-30 days');

        foreach ($rows as &$row) {
            $row['published_jobs'] = (int)($row['published_jobs'] ?? 0);
            $row['total_jobs'] = (int)($row['total_jobs'] ?? 0);
            $row['draft_jobs'] = (int)($row['draft_jobs'] ?? 0);

            $stats['total_employers']++;
            $stats['total_published_jobs'] += $row['published_jobs'];

            if ($row['published_jobs'] > 0) {
                $stats['active_employers']++;
            } else {
                $stats['inactive_employers']++;
            }

            $lastActivity = $row['last_job_activity'] ?? $row['user_created_at'] ?? null;
            $row['last_job_activity'] = $lastActivity;
            if ($lastActivity && strtotime($lastActivity) >= $recentBenchmark) {
                $stats['recent_active_employers']++;
            }
        }
        unset($row);

        return [
            'rows' => $rows,
            'stats' => $stats,
            'query_error' => $queryError,
            'applied_filters' => [
                'keyword' => $keyword,
                'location' => $location,
                'status' => $status
            ]
        ];
    }

    public function countAll() {
        $sql = "SELECT COUNT(*) AS total FROM employers";
        $result = $this->conn->query($sql);
        if ($result && ($row = $result->fetch_assoc())) {
            return (int)$row['total'];
        }
        return 0;
    }

    public function getTopEmployersByJobs($limit = 6) {
        $limit = max(1, (int)$limit);
        $sql = "SELECT e.id, e.company_name, e.logo_path, COALESCE(COUNT(j.id), 0) AS job_count
                FROM employers e
                LEFT JOIN jobs j ON j.employer_id = e.id AND j.status = 'published'
                GROUP BY e.id
                ORDER BY job_count DESC, e.company_name ASC
                LIMIT $limit";
        $result = $this->conn->query($sql);
        if (!$result) {
            return [];
        }
        $employers = [];
        while ($row = $result->fetch_assoc()) {
            $employers[] = $row;
        }
        return $employers;
    }

    public function getDirectoryList(array $options = []) {
        $search = isset($options['search']) ? trim((string)$options['search']) : '';
        $location = isset($options['location']) ? trim((string)$options['location']) : '';
        $sort = isset($options['sort']) ? (string)$options['sort'] : 'featured';
        $limit = isset($options['limit']) ? (int)$options['limit'] : 0;

    $sql = "SELECT e.id, e.company_name, e.website, e.address, e.about, e.logo_path,
            COALESCE(SUM(CASE WHEN j.status = 'published' THEN 1 ELSE 0 END), 0) AS job_count,
            MAX(CASE WHEN j.status = 'published' THEN j.created_at ELSE NULL END) AS latest_job_at
                FROM employers e
                LEFT JOIN jobs j ON j.employer_id = e.id
                WHERE 1 = 1";

        $types = '';
        $params = [];

        if ($search !== '') {
            $sql .= " AND (e.company_name LIKE ? OR e.about LIKE ?)";
            $like = '%' . $search . '%';
            $types .= 'ss';
            $params[] = $like;
            $params[] = $like;
        }

        if ($location !== '') {
            $sql .= " AND e.address LIKE ?";
            $types .= 's';
            $params[] = '%' . $location . '%';
        }

        $sql .= " GROUP BY e.id";

        switch ($sort) {
            case 'recent':
                $sql .= " ORDER BY latest_job_at DESC, e.company_name ASC";
                break;
            case 'alphabet':
                $sql .= " ORDER BY e.company_name ASC";
                break;
            default:
                $sql .= " ORDER BY job_count DESC, e.company_name ASC";
                break;
        }

        if ($limit > 0) {
            $sql .= " LIMIT ?";
            $types .= 'i';
            $params[] = $limit;
        }

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return [];
        }

        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $companies = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $companies[] = $row;
            }
        }
        $stmt->close();
        return $companies;
    }

    public function getDirectoryPaginated(array $options = [], int $page = 1, int $perPage = 6): array {
        $search = isset($options['search']) ? trim((string)$options['search']) : '';
        $location = isset($options['location']) ? trim((string)$options['location']) : '';
        $sort = isset($options['sort']) ? (string)$options['sort'] : 'featured';

        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $baseSql = "SELECT e.id, e.company_name, e.website, e.address, e.about, e.logo_path,
                COALESCE(SUM(CASE WHEN j.status = 'published' THEN 1 ELSE 0 END), 0) AS job_count,
                MAX(CASE WHEN j.status = 'published' THEN j.created_at ELSE NULL END) AS latest_job_at
                FROM employers e
                LEFT JOIN jobs j ON j.employer_id = e.id
                WHERE 1 = 1";

        $types = '';
        $params = [];

        if ($search !== '') {
            $baseSql .= " AND (e.company_name LIKE ? OR e.about LIKE ?)";
            $like = '%' . $search . '%';
            $types .= 'ss';
            $params[] = $like;
            $params[] = $like;
        }

        if ($location !== '') {
            $baseSql .= " AND e.address LIKE ?";
            $types .= 's';
            $params[] = '%' . $location . '%';
        }

        $baseSql .= " GROUP BY e.id";

        $orderSql = '';
        switch ($sort) {
            case 'recent':
                $orderSql = " ORDER BY latest_job_at DESC, e.company_name ASC";
                break;
            case 'alphabet':
                $orderSql = " ORDER BY e.company_name ASC";
                break;
            default:
                $orderSql = " ORDER BY job_count DESC, e.company_name ASC";
                break;
        }

        $countSql = "SELECT COUNT(*) AS total FROM (" . $baseSql . ") AS employer_directory";
        $total = 0;
        $queryError = null;

        if ($types === '') {
            $countResult = $this->conn->query($countSql);
            if ($countResult instanceof mysqli_result) {
                $row = $countResult->fetch_assoc();
                $total = (int)($row['total'] ?? 0);
                $countResult->free();
            } else {
                $queryError = $this->conn->error;
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
        $rows = [];

        if ($queryError === null) {
            $dataSql = $baseSql . $orderSql . " LIMIT ? OFFSET ?";
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
                'search' => $search,
                'location' => $location,
                'sort' => $sort,
            ],
        ];
    }

    public function getDistinctLocations($limit = 12) {
        $limit = max(1, (int)$limit);
        $sql = "SELECT DISTINCT address FROM employers WHERE address IS NOT NULL AND address <> '' ORDER BY address ASC LIMIT ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return [];
        }
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $locations = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $locations[] = $row['address'];
            }
        }
        $stmt->close();
        return $locations;
    }

    /**
     * Thêm mới employer cho user
     */
    public function createForUser($user_id, $company_name, $website = null, $address = null, $about = null, $logo_path = null) {
        // kiểm tra trùng user_id
        $existing = $this->getByUserId($user_id);
        if ($existing) return false;

        $stmt = $this->conn->prepare("
            INSERT INTO employers (user_id, company_name, website, address, about, logo_path)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isssss", $user_id, $company_name, $website, $address, $about, $logo_path);
        if ($stmt->execute()) {
            $insertId = $this->conn->insert_id;
            $stmt->close();
            return $insertId;
        }
        $stmt->close();
        return false;
    }

    /**
     * Cập nhật employer theo id
     */
    public function update($id, $company_name, $website = null, $address = null, $about = null, $logo_path = null) {
        if (!empty($logo_path)) {
            $stmt = $this->conn->prepare("
                UPDATE employers 
                SET company_name = ?, website = ?, address = ?, about = ?, logo_path = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sssssi", $company_name, $website, $address, $about, $logo_path, $id);
        } else {
            $stmt = $this->conn->prepare("
                UPDATE employers 
                SET company_name = ?, website = ?, address = ?, about = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ssssi", $company_name, $website, $address, $about, $id);
        }
        return $stmt->execute();
    }

    /**
     * Xóa employer theo id
     */
    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM employers WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function updateProfileByUserId(int $userId, array $attributes): bool {
        $existing = $this->getByUserId($userId);
        if (!$existing) {
            return false;
        }

        $fields = [];
        $types = '';
        $params = [];
        foreach ($attributes as $column => $value) {
            if (!array_key_exists($column, $existing)) {
                continue;
            }
            $fields[] = $column . ' = ?';
            $types .= 's';
            $params[] = $value === '' ? null : $value;
        }

        if (empty($fields)) {
            return true;
        }

        if (array_key_exists('updated_at', $existing)) {
            $fields[] = 'updated_at = NOW()';
        }

        $sql = 'UPDATE employers SET ' . implode(', ', $fields) . ' WHERE user_id = ? LIMIT 1';
        $types .= 'i';
        $params[] = $userId;

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return false;
        }

        $stmt->bind_param($types, ...$params);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
}

