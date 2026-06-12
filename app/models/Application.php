<?php
require_once __DIR__ . '/Database.php';

class Application extends Database
{
    // include 'withdrawn' to allow candidates to cancel their applications
    private array $allowedStatuses = ['applied', 'viewed', 'shortlisted', 'rejected', 'hired', 'withdrawn'];
    private static bool $decisionNoteEnsured = false;

    private function ensureDecisionNoteColumn(): void
    {
        if (self::$decisionNoteEnsured) {
            return;
        }

        $dbResult = $this->conn->query("SELECT DATABASE() AS db_name");
        $dbName = null;
        if ($dbResult && ($row = $dbResult->fetch_assoc())) {
            $dbName = $row['db_name'] ?? null;
        }
        if ($dbResult) {
            $dbResult->free();
        }

        if (!$dbName) {
            self::$decisionNoteEnsured = true;
            return;
        }

        $dbEsc = $this->conn->real_escape_string($dbName);
        $sql = "SELECT column_name FROM information_schema.columns WHERE table_schema = '{$dbEsc}' AND table_name = 'applications' AND column_name = 'decision_note' LIMIT 1";
        $result = $this->conn->query($sql);
        $exists = false;
        if ($result && ($row = $result->fetch_assoc())) {
            $exists = !empty($row['column_name']);
        }
        if ($result) {
            $result->free();
        }

        if (!$exists) {
            try {
                $this->conn->query("ALTER TABLE applications ADD COLUMN decision_note TEXT DEFAULT NULL AFTER status");
            } catch (mysqli_sql_exception $e) {
                if ((int)$e->getCode() !== 1060) {
                    throw $e;
                }
            }
        }

        self::$decisionNoteEnsured = true;
    }

    /**
     * Ensure the `status` enum includes 'withdrawn' value to avoid invalid enum assignment.
     */
    private function ensureWithdrawnStatusEnum(): void
    {
        // check once per process
        static $ensured = false;
        if ($ensured) return;
        $ensured = true;

        $dbResult = $this->conn->query("SELECT DATABASE() AS db_name");
        $dbName = null;
        if ($dbResult && ($row = $dbResult->fetch_assoc())) {
            $dbName = $row['db_name'] ?? null;
        }
        if ($dbResult) $dbResult->free();
        if (!$dbName) return;

        $dbEsc = $this->conn->real_escape_string($dbName);
        $sql = "SELECT column_type FROM information_schema.columns WHERE table_schema = '{$dbEsc}' AND table_name = 'applications' AND column_name = 'status' LIMIT 1";
        $res = $this->conn->query($sql);
        $has = false;
        if ($res && ($row = $res->fetch_assoc())) {
            $colType = $row['column_type'] ?? '';
            if (strpos($colType, "'withdrawn'") !== false) {
                $has = true;
            }
        }
        if ($res) $res->free();

        if (!$has) {
            // alter enum to include withdrawn
            $alter = "ALTER TABLE applications MODIFY COLUMN status ENUM('applied','viewed','shortlisted','rejected','hired','withdrawn') DEFAULT 'applied'";
            // best-effort, ignore failure but log
            @$this->conn->query($alter);
        }
    }

    public function listForEmployer(int $employerId, array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $this->ensureDecisionNoteColumn();
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $response = [
            'rows' => [],
            'total' => 0,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => 1,
            'query_error' => null,
            'applied_filters' => [
                'job_id' => isset($filters['job_id']) ? (int)$filters['job_id'] : null,
                'status' => isset($filters['status']) ? (string)$filters['status'] : '',
                'keyword' => isset($filters['keyword']) ? trim((string)$filters['keyword']) : '',
            ],
        ];

        if ($employerId <= 0) {
            return $response;
        }

        $conditions = ['j.employer_id = ?'];
        $types = 'i';
        $params = [$employerId];

        $jobId = isset($filters['job_id']) ? (int)$filters['job_id'] : 0;
        if ($jobId > 0) {
            $conditions[] = 'a.job_id = ?';
            $types .= 'i';
            $params[] = $jobId;
        }

        $status = isset($filters['status']) ? trim((string)$filters['status']) : '';
        if ($status !== '' && in_array($status, $this->allowedStatuses, true)) {
            $conditions[] = 'a.status = ?';
            $types .= 's';
            $params[] = $status;
        }
        else {
            // By default, employers should not see withdrawn applications
            $conditions[] = "a.status != 'withdrawn'";
        }

        $keyword = isset($filters['keyword']) ? trim((string)$filters['keyword']) : '';
        if ($keyword !== '') {
            $conditions[] = '(u.email LIKE ? OR u.name LIKE ?)';
            $like = '%' . $keyword . '%';
            $types .= 'ss';
            $params[] = $like;
            $params[] = $like;
        }

        $whereSql = implode(' AND ', $conditions);

        $countSql = "SELECT COUNT(*) AS total
                     FROM applications a
                     INNER JOIN jobs j ON j.id = a.job_id
                     INNER JOIN candidates c ON c.id = a.candidate_id
                     INNER JOIN users u ON u.id = c.user_id
                     WHERE $whereSql";

        $countStmt = $this->conn->prepare($countSql);
        if ($countStmt === false) {
            $response['query_error'] = $this->conn->error;
            return $response;
        }

        $countStmt->bind_param($types, ...$params);
        if ($countStmt->execute()) {
            $countResult = $countStmt->get_result();
            if ($countResult && ($row = $countResult->fetch_assoc())) {
                $response['total'] = (int)($row['total'] ?? 0);
            }
            if ($countResult) {
                $countResult->free();
            }
        } else {
            $response['query_error'] = $countStmt->error;
        }
        $countStmt->close();

        $total = $response['total'];
        if ($total > 0) {
            $response['total_pages'] = (int)ceil($total / $perPage);
            if ($page > $response['total_pages']) {
                $page = $response['total_pages'];
                $response['page'] = $page;
            }
        }

        if ($response['query_error'] !== null || $total === 0) {
            return $response;
        }

        $offset = ($page - 1) * $perPage;

    $dataSql = "SELECT
                        a.id,
                        a.job_id,
                        a.candidate_id,
                        a.cover_letter,
                        a.resume_snapshot,
                        a.status,
            a.decision_note,
                        a.applied_at,
                        j.title AS job_title,
                        j.status AS job_status,
                        u.name AS candidate_name,
                        u.email AS candidate_email,
                        u.phone AS candidate_phone
                    FROM applications a
                    INNER JOIN jobs j ON j.id = a.job_id
                    INNER JOIN candidates c ON c.id = a.candidate_id
                    INNER JOIN users u ON u.id = c.user_id
                    WHERE $whereSql
                    ORDER BY a.applied_at DESC
                    LIMIT ? OFFSET ?";

        $dataStmt = $this->conn->prepare($dataSql);
        if ($dataStmt === false) {
            $response['query_error'] = $this->conn->error;
            return $response;
        }

        $dataTypes = $types . 'ii';
        $dataParams = array_merge($params, [$perPage, $offset]);

        $dataStmt->bind_param($dataTypes, ...$dataParams);
        if ($dataStmt->execute()) {
            $result = $dataStmt->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $row['status'] = $row['status'] ?? 'applied';
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

    public function getForEmployer(int $applicationId, int $employerId): ?array
    {
        $this->ensureDecisionNoteColumn();
        if ($applicationId <= 0 || $employerId <= 0) {
            return null;
        }

        $sql = "SELECT
                    a.id,
                    a.job_id,
                    a.candidate_id,
                    a.cover_letter,
                    a.resume_snapshot,
                    a.status,
                    a.decision_note,
                    a.applied_at,
                    j.title AS job_title,
                    j.location AS job_location,
                    j.employment_type,
                    j.salary,
                    j.description AS job_description,
                    c.user_id,
                    u.name AS candidate_name,
                    u.email AS candidate_email,
                    u.phone AS candidate_phone,
                    c.cv_path,
                    c.headline,
                    c.summary,
                    c.location AS candidate_location,
                    c.skills,
                    c.experience
                FROM applications a
                INNER JOIN jobs j ON j.id = a.job_id
                INNER JOIN candidates c ON c.id = a.candidate_id
                INNER JOIN users u ON u.id = c.user_id
                WHERE a.id = ? AND j.employer_id = ? AND a.status != 'withdrawn'
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return null;
        }

        $stmt->bind_param('ii', $applicationId, $employerId);
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }

        $result = $stmt->get_result();
        $data = $result ? $result->fetch_assoc() : null;
        if ($result) {
            $result->free();
        }
        $stmt->close();

        return $data ?: null;
    }

    /**
     * Get application details for a candidate by application id
     */
    public function getForCandidateById(int $applicationId, int $candidateId): ?array
    {
        if ($applicationId <= 0 || $candidateId <= 0) return null;

    $sql = "SELECT a.*, j.title AS job_title, j.location AS job_location, j.salary AS job_salary, j.description AS job_description,
               e.company_name AS employer_name, e.user_id AS employer_user_id, ue.email AS employer_email,
               u.email AS candidate_email, u.name AS candidate_name
        FROM applications a
        INNER JOIN jobs j ON j.id = a.job_id
        LEFT JOIN employers e ON e.id = j.employer_id
        LEFT JOIN users ue ON ue.id = e.user_id
        INNER JOIN candidates c ON c.id = a.candidate_id
        INNER JOIN users u ON u.id = c.user_id
        WHERE a.id = ? AND a.candidate_id = ?
        LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) return null;
        $stmt->bind_param('ii', $applicationId, $candidateId);
        if (!$stmt->execute()) { $stmt->close(); return null; }
        $res = $stmt->get_result();
        $data = $res ? $res->fetch_assoc() : null;
        if ($res) $res->free();
        $stmt->close();
        return $data ?: null;
    }

    /**
     * Allow candidate to withdraw their application (set status = 'withdrawn')
     */
    public function withdrawByCandidate(int $applicationId, int $candidateId): bool
    {
        if ($applicationId <= 0 || $candidateId <= 0) return false;
        // Ensure enum supports 'withdrawn'
        $this->ensureWithdrawnStatusEnum();
        $sql = "UPDATE applications SET status = 'withdrawn' WHERE id = ? AND candidate_id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) return false;
        $stmt->bind_param('ii', $applicationId, $candidateId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function updateStatus(int $applicationId, int $employerId, string $status, ?string $note = null): bool
    {
        $this->ensureDecisionNoteColumn();
        if (!in_array($status, $this->allowedStatuses, true)) {
            return false;
        }

        $sql = "UPDATE applications a
                INNER JOIN jobs j ON j.id = a.job_id
                SET a.status = ?, a.decision_note = ?, a.applied_at = a.applied_at
                WHERE a.id = ? AND j.employer_id = ?";

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return false;
        }

        $stmt->bind_param('ssii', $status, $note, $applicationId, $employerId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function markViewed(int $applicationId, int $employerId): void
    {
        $this->ensureDecisionNoteColumn();
        $sql = "UPDATE applications a
                INNER JOIN jobs j ON j.id = a.job_id
                SET a.status = CASE WHEN a.status = 'applied' THEN 'viewed' ELSE a.status END
                WHERE a.id = ? AND j.employer_id = ?";

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return;
        }
        $stmt->bind_param('ii', $applicationId, $employerId);
        $stmt->execute();
        $stmt->close();
    }

    public function getAllowedStatuses(): array
    {
        return $this->allowedStatuses;
    }

    /**
     * Return Vietnamese label for a status key
     */
    public function getStatusLabel(string $status): string
    {
        $map = [
            'applied' => 'Đã ứng tuyển',
            'viewed' => 'Nhà tuyển dụng đã xem',
            'shortlisted' => 'Đã chọn phỏng vấn',
            'rejected' => 'Bị từ chối',
            'hired' => 'Đã trúng tuyển',
            'withdrawn' => 'Đã rút đơn'
        ];
        return $map[$status] ?? ucfirst($status);
    }

    /**
     * Return all status labels as status => label
     */
    public function getStatusLabels(): array
    {
        $labels = [];
        foreach ($this->allowedStatuses as $s) {
            $labels[$s] = $this->getStatusLabel($s);
        }
        return $labels;
    }

    /**
     * Summary counters for admin monitoring dashboard
     */
    public function getAdminSummaryStats(): array
    {
        $this->ensureDecisionNoteColumn();
        $this->ensureWithdrawnStatusEnum();

        $stats = [
            'total' => 0,
            'last7_days' => 0,
            'last30_days' => 0,
            'awaiting_review' => 0,
            'shortlisted' => 0,
            'hired' => 0,
            'withdrawn' => 0,
        ];

        $queries = [
            'total' => "SELECT COUNT(*) AS total FROM applications",
            'last7_days' => "SELECT COUNT(*) AS total FROM applications WHERE applied_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'last30_days' => "SELECT COUNT(*) AS total FROM applications WHERE applied_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            'awaiting_review' => "SELECT COUNT(*) AS total FROM applications WHERE status IN ('applied','viewed')",
            'shortlisted' => "SELECT COUNT(*) AS total FROM applications WHERE status = 'shortlisted'",
            'hired' => "SELECT COUNT(*) AS total FROM applications WHERE status = 'hired'",
            'withdrawn' => "SELECT COUNT(*) AS total FROM applications WHERE status = 'withdrawn'",
        ];

        foreach ($queries as $key => $sql) {
            $result = $this->conn->query($sql);
            if ($result && ($row = $result->fetch_assoc())) {
                $stats[$key] = (int)($row['total'] ?? 0);
            }
            if ($result instanceof mysqli_result) {
                $result->free();
            }
        }

        return $stats;
    }

    /**
     * Distribution of applications per status for admin insights
     */
    public function getAdminStatusBreakdown(): array
    {
        $breakdown = [];
        foreach ($this->allowedStatuses as $status) {
            $breakdown[$status] = 0;
        }

        $sql = "SELECT status, COUNT(*) AS total FROM applications GROUP BY status";
        $result = $this->conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $status = $row['status'] ?? '';
                if ($status !== '' && isset($breakdown[$status])) {
                    $breakdown[$status] = (int)($row['total'] ?? 0);
                }
            }
            $result->free();
        }

        return $breakdown;
    }

    /**
     * Paginated list of applications for admin control centre
     */
    public function listForAdmin(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $this->ensureDecisionNoteColumn();
        $this->ensureWithdrawnStatusEnum();

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $appliedFilters = [
            'status' => isset($filters['status']) ? trim((string)$filters['status']) : '',
            'keyword' => isset($filters['keyword']) ? trim((string)$filters['keyword']) : '',
            'job_id' => isset($filters['job_id']) ? (int)$filters['job_id'] : null,
            'employer_id' => isset($filters['employer_id']) ? (int)$filters['employer_id'] : null,
            'date_from' => isset($filters['date_from']) ? trim((string)$filters['date_from']) : '',
            'date_to' => isset($filters['date_to']) ? trim((string)$filters['date_to']) : '',
        ];

        foreach (['date_from', 'date_to'] as $dateKey) {
            $value = $appliedFilters[$dateKey];
            if ($value !== '') {
                $dt = \DateTime::createFromFormat('Y-m-d', $value);
                if ($dt === false) {
                    $appliedFilters[$dateKey] = '';
                } else {
                    $appliedFilters[$dateKey] = $dt->format('Y-m-d');
                }
            }
        }

        $response = [
            'rows' => [],
            'total' => 0,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => 1,
            'query_error' => null,
            'applied_filters' => $appliedFilters,
        ];

        $conditions = ['1 = 1'];
        $types = '';
        $params = [];

        $status = $appliedFilters['status'];
        if ($status !== '' && in_array($status, $this->allowedStatuses, true)) {
            $conditions[] = 'a.status = ?';
            $types .= 's';
            $params[] = $status;
        }

        $jobId = $appliedFilters['job_id'];
        if ($jobId !== null && $jobId > 0) {
            $conditions[] = 'a.job_id = ?';
            $types .= 'i';
            $params[] = $jobId;
        }

        $employerId = $appliedFilters['employer_id'];
        if ($employerId !== null && $employerId > 0) {
            $conditions[] = 'j.employer_id = ?';
            $types .= 'i';
            $params[] = $employerId;
        }

        $keyword = $appliedFilters['keyword'];
        if ($keyword !== '') {
            $conditions[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR j.title LIKE ? OR e.company_name LIKE ?)';
            $like = '%' . $keyword . '%';
            $types .= 'sssss';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $dateFrom = $appliedFilters['date_from'];
        if ($dateFrom !== '') {
            $conditions[] = 'DATE(a.applied_at) >= ?';
            $types .= 's';
            $params[] = $dateFrom;
        }

        $dateTo = $appliedFilters['date_to'];
        if ($dateTo !== '') {
            $conditions[] = 'DATE(a.applied_at) <= ?';
            $types .= 's';
            $params[] = $dateTo;
        }

        $whereSql = implode(' AND ', $conditions);

        $baseSql = "FROM applications a
                    INNER JOIN jobs j ON j.id = a.job_id
                    INNER JOIN employers e ON e.id = j.employer_id
                    INNER JOIN candidates c ON c.id = a.candidate_id
                    INNER JOIN users u ON u.id = c.user_id
                    LEFT JOIN users ue ON ue.id = e.user_id
                    WHERE $whereSql";

        $countSql = "SELECT COUNT(*) AS total " . $baseSql;
        $countStmt = $this->conn->prepare($countSql);
        if ($countStmt === false) {
            $response['query_error'] = $this->conn->error;
            return $response;
        }

        if ($types !== '') {
            $countStmt->bind_param($types, ...$params);
        }

        if ($countStmt->execute()) {
            $countResult = $countStmt->get_result();
            if ($countResult && ($row = $countResult->fetch_assoc())) {
                $response['total'] = (int)($row['total'] ?? 0);
            }
            if ($countResult) {
                $countResult->free();
            }
        } else {
            $response['query_error'] = $countStmt->error;
        }
        $countStmt->close();

        $total = $response['total'];
        if ($total > 0) {
            $response['total_pages'] = (int)ceil($total / $perPage);
            if ($page > $response['total_pages']) {
                $page = $response['total_pages'];
                $response['page'] = $page;
            }
        }

        if ($response['query_error'] !== null || $total === 0) {
            return $response;
        }

        $offset = ($page - 1) * $perPage;

        $dataSql = "SELECT
                        a.id,
                        a.job_id,
                        a.candidate_id,
                        a.status,
                        a.decision_note,
                        a.applied_at,
                        j.title AS job_title,
                        j.status AS job_status,
                        j.employment_type,
                        j.location AS job_location,
                        e.company_name AS employer_name,
                        e.id AS employer_id,
                        ue.email AS employer_email,
                        u.name AS candidate_name,
                        u.email AS candidate_email,
                        u.phone AS candidate_phone,
                        c.location AS candidate_location,
                        c.headline AS candidate_headline
                    " . $baseSql . "
                    ORDER BY a.applied_at DESC
                    LIMIT ? OFFSET ?";

        $dataStmt = $this->conn->prepare($dataSql);
        if ($dataStmt === false) {
            $response['query_error'] = $this->conn->error;
            return $response;
        }

        $dataTypes = $types . 'ii';
        $dataParams = array_merge($params, [$perPage, $offset]);
        $dataStmt->bind_param($dataTypes, ...$dataParams);

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

    /**
     * Detailed application information for admin review
     */
    public function getAdminApplication(int $applicationId): ?array
    {
        if ($applicationId <= 0) {
            return null;
        }

        $this->ensureDecisionNoteColumn();
        $this->ensureWithdrawnStatusEnum();

        $sql = "SELECT
                    a.*,
                    j.title AS job_title,
                    j.location AS job_location,
                    j.salary AS job_salary,
                    j.employment_type,
                    j.description AS job_description,
                    j.status AS job_status,
                    e.company_name AS employer_name,
                    e.id AS employer_id,
                    ue.email AS employer_email,
                    c.user_id AS candidate_user_id,
                    c.headline,
                    c.summary,
                    c.location AS candidate_location,
                    c.skills,
                    c.experience,
                    c.cv_path,
                    u.name AS candidate_name,
                    u.email AS candidate_email,
                    u.phone AS candidate_phone
                FROM applications a
                INNER JOIN jobs j ON j.id = a.job_id
                INNER JOIN employers e ON e.id = j.employer_id
                INNER JOIN candidates c ON c.id = a.candidate_id
                INNER JOIN users u ON u.id = c.user_id
                LEFT JOIN users ue ON ue.id = e.user_id
                WHERE a.id = ?
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return null;
        }

        $stmt->bind_param('i', $applicationId);
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }

        $result = $stmt->get_result();
        $data = $result ? $result->fetch_assoc() : null;
        if ($result) {
            $result->free();
        }
        $stmt->close();

        return $data ?: null;
    }

    public function candidateHasApplied(int $candidateId, int $jobId): bool
    {
        if ($candidateId <= 0 || $jobId <= 0) {
            return false;
        }
        // Consider an application as 'applied' only if its status is not 'withdrawn'
        $stmt = $this->conn->prepare("SELECT id FROM applications WHERE candidate_id = ? AND job_id = ? AND (status != 'withdrawn' AND status IS NOT NULL) LIMIT 1");
        if ($stmt === false) {
            return false;
        }
        $stmt->bind_param('ii', $candidateId, $jobId);
        $stmt->execute();
        $result = $stmt->get_result();
        $has = $result && $result->num_rows > 0;
        if ($result) {
            $result->free();
        }
        $stmt->close();
        return $has;
    }

    /**
     * List applications for a candidate (used by candidate dashboard)
     * Returns array with rows, total, page, per_page, total_pages, query_error
     */
    public function listForCandidate(int $candidateId, array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $this->ensureDecisionNoteColumn();
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $response = [
            'rows' => [],
            'total' => 0,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => 1,
            'query_error' => null,
            'applied_filters' => [
                'job_id' => isset($filters['job_id']) ? (int)$filters['job_id'] : null,
                'status' => isset($filters['status']) ? (string)$filters['status'] : '',
                'keyword' => isset($filters['keyword']) ? trim((string)$filters['keyword']) : ''
            ],
        ];

        if ($candidateId <= 0) {
            return $response;
        }

        $conditions = ['a.candidate_id = ?'];
        $types = 'i';
        $params = [$candidateId];

        $jobId = isset($filters['job_id']) ? (int)$filters['job_id'] : 0;
        if ($jobId > 0) {
            $conditions[] = 'a.job_id = ?';
            $types .= 'i';
            $params[] = $jobId;
        }

        $status = isset($filters['status']) ? trim((string)$filters['status']) : '';
        if ($status !== '' && in_array($status, $this->allowedStatuses, true)) {
            $conditions[] = 'a.status = ?';
            $types .= 's';
            $params[] = $status;
        }

        $keyword = isset($filters['keyword']) ? trim((string)$filters['keyword']) : '';
        if ($keyword !== '') {
            $conditions[] = "(j.title LIKE ? OR e.company_name LIKE ?)";
            $like = '%' . $keyword . '%';
            $types .= 'ss';
            $params[] = $like;
            $params[] = $like;
        }

        $whereSql = implode(' AND ', $conditions);

        $countSql = "SELECT COUNT(*) AS total
                     FROM applications a
                     INNER JOIN jobs j ON j.id = a.job_id
                     LEFT JOIN employers e ON e.id = j.employer_id
                     WHERE $whereSql";

        $countStmt = $this->conn->prepare($countSql);
        if ($countStmt === false) {
            $response['query_error'] = $this->conn->error;
            return $response;
        }

        $countStmt->bind_param($types, ...$params);
        if ($countStmt->execute()) {
            $cr = $countStmt->get_result();
            if ($cr && ($row = $cr->fetch_assoc())) {
                $response['total'] = (int)($row['total'] ?? 0);
            }
            if ($cr) $cr->free();
        } else {
            $response['query_error'] = $countStmt->error;
        }
        $countStmt->close();

        $total = $response['total'];
        if ($total > 0) {
            $response['total_pages'] = (int)ceil($total / $perPage);
            if ($page > $response['total_pages']) {
                $page = $response['total_pages'];
                $response['page'] = $page;
            }
        }

        if ($response['query_error'] !== null || $total === 0) {
            return $response;
        }

        $offset = ($page - 1) * $perPage;

        $dataSql = "SELECT
                        a.id,
                        a.job_id,
                        a.cover_letter,
                        a.resume_snapshot,
                        a.status,
                        a.decision_note,
                        a.applied_at,
                        j.title AS job_title,
                        j.location AS job_location,
                        j.salary AS job_salary,
                        e.company_name AS employer_name
                    FROM applications a
                    INNER JOIN jobs j ON j.id = a.job_id
                    LEFT JOIN employers e ON e.id = j.employer_id
                    WHERE $whereSql
                    ORDER BY a.applied_at DESC
                    LIMIT ? OFFSET ?";

        $dataStmt = $this->conn->prepare($dataSql);
        if ($dataStmt === false) {
            $response['query_error'] = $this->conn->error;
            return $response;
        }

        $dataTypes = $types . 'ii';
        $dataParams = array_merge($params, [$perPage, $offset]);

        $dataStmt->bind_param($dataTypes, ...$dataParams);
        if ($dataStmt->execute()) {
            $res = $dataStmt->get_result();
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $response['rows'][] = $row;
                }
                $res->free();
            }
        } else {
            $response['query_error'] = $dataStmt->error;
        }
        $dataStmt->close();

        return $response;
    }

    public function createApplication(int $jobId, int $candidateId, ?string $coverLetter = null, ?string $resumeSnapshot = null)
    {
        if ($jobId <= 0 || $candidateId <= 0) {
            return false;
        }

        $this->ensureDecisionNoteColumn();

        $sql = "INSERT INTO applications (job_id, candidate_id, cover_letter, resume_snapshot, status, applied_at, decision_note)
                VALUES (?, ?, ?, ?, 'applied', NOW(), NULL)";

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return false;
        }

        $stmt->bind_param('iiss', $jobId, $candidateId, $coverLetter, $resumeSnapshot);
        if ($stmt->execute()) {
            $insertId = $this->conn->insert_id;
            $stmt->close();
            return $insertId;
        }
        $stmt->close();
        return false;
    }

    /**
     * Reactivate a withdrawn application: set status = 'applied', update cover_letter, resume_snapshot and applied_at
     */
    public function reactivateApplication(int $applicationId, int $candidateId, ?string $coverLetter = null, ?string $resumeSnapshot = null): bool
    {
        if ($applicationId <= 0 || $candidateId <= 0) return false;
        $this->ensureDecisionNoteColumn();
        $this->ensureWithdrawnStatusEnum();

        $sql = "UPDATE applications SET status = 'applied', cover_letter = ?, resume_snapshot = ?, applied_at = NOW(), decision_note = NULL WHERE id = ? AND candidate_id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            if (function_exists('error_log')) {
                error_log('JobFind: reactivateApplication prepare failed: ' . ($this->conn->error ?? 'unknown'));
            }
            return false;
        }
        $stmt->bind_param('ssii', $coverLetter, $resumeSnapshot, $applicationId, $candidateId);
        $ok = $stmt->execute();
        if (!$ok && function_exists('error_log')) {
            error_log('JobFind: reactivateApplication execute failed: ' . ($stmt->error ?? $this->conn->error));
        }
        $stmt->close();
        return (bool)$ok;
    }

    public function getForCandidate(int $jobId, int $candidateId): ?array
    {
        if ($jobId <= 0 || $candidateId <= 0) {
            return null;
        }

        $this->ensureDecisionNoteColumn();

        $sql = "SELECT a.*, j.title AS job_title
                FROM applications a
                INNER JOIN jobs j ON j.id = a.job_id
                WHERE a.job_id = ? AND a.candidate_id = ?
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return null;
        }

        $stmt->bind_param('ii', $jobId, $candidateId);
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }

        $result = $stmt->get_result();
        $data = $result ? $result->fetch_assoc() : null;
        if ($result) {
            $result->free();
        }
        $stmt->close();
        return $data ?: null;
    }
}
