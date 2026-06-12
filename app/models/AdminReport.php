<?php
require_once __DIR__ . '/Database.php';

class AdminReport extends Database
{
    public function getSummary(): array
    {
        $summary = [
            'total_jobs' => 0,
            'active_jobs' => 0,
            'total_applications' => 0,
            'applications_last_30_days' => 0,
            'shortlisted_applications' => 0,
            'hired_applications' => 0,
            'employer_count' => 0,
            'candidate_count' => 0,
            'interview_rate' => 0.0,
            'hire_rate' => 0.0,
        ];

        $queries = [
            'total_jobs' => "SELECT COUNT(*) AS total FROM jobs",
            'active_jobs' => "SELECT COUNT(*) AS total FROM jobs WHERE status = 'published'",
            'total_applications' => "SELECT COUNT(*) AS total FROM applications",
            'applications_last_30_days' => "SELECT COUNT(*) AS total FROM applications WHERE applied_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            'shortlisted_applications' => "SELECT COUNT(*) AS total FROM applications WHERE status = 'shortlisted'",
            'hired_applications' => "SELECT COUNT(*) AS total FROM applications WHERE status = 'hired'",
            'employer_count' => "SELECT COUNT(*) AS total FROM employers",
            'candidate_count' => "SELECT COUNT(*) AS total FROM candidates",
        ];

        foreach ($queries as $key => $sql) {
            $result = $this->conn->query($sql);
            if ($result && ($row = $result->fetch_assoc())) {
                $summary[$key] = (int)($row['total'] ?? 0);
            }
            if ($result instanceof mysqli_result) {
                $result->free();
            }
        }

        if ($summary['total_applications'] > 0) {
            $summary['interview_rate'] = round(($summary['shortlisted_applications'] / $summary['total_applications']) * 100, 1);
            $summary['hire_rate'] = round(($summary['hired_applications'] / $summary['total_applications']) * 100, 1);
        }

        return $summary;
    }

    public function getPipelineBreakdown(): array
    {
        $pipeline = [
            'applied' => 0,
            'viewed' => 0,
            'shortlisted' => 0,
            'rejected' => 0,
            'hired' => 0,
        ];

        $sql = "SELECT status, COUNT(*) AS total FROM applications GROUP BY status";
        $result = $this->conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $status = $row['status'] ?? '';
                if (isset($pipeline[$status])) {
                    $pipeline[$status] = (int)$row['total'];
                }
            }
            $result->free();
        }

        return $pipeline;
    }

    public function getMonthlyTimeline(int $months = 6): array
    {
        $months = max(1, min(24, $months));
        $startDate = new DateTime('first day of this month');
        $startDate->modify('-' . ($months - 1) . ' months');

        $timeline = [];
        $cursor = clone $startDate;
        for ($i = 0; $i < $months; $i++) {
            $key = $cursor->format('Y-m');
            $timeline[$key] = [
                'label' => $cursor->format('m/Y'),
                'jobs' => 0,
                'applications' => 0,
                'shortlisted' => 0,
                'hired' => 0,
            ];
            $cursor->modify('+1 month');
        }

        $startDateString = $startDate->format('Y-m-01');

        $mappingQueries = [
            'jobs' => [
                "SELECT DATE_FORMAT(created_at, '%Y-%m') AS period, COUNT(*) AS total FROM jobs WHERE created_at IS NOT NULL AND created_at >= ? GROUP BY period",
                'created_at',
            ],
            'applications' => [
                "SELECT DATE_FORMAT(applied_at, '%Y-%m') AS period, COUNT(*) AS total FROM applications WHERE applied_at IS NOT NULL AND applied_at >= ? GROUP BY period",
                'applied_at',
            ],
            'shortlisted' => [
                "SELECT DATE_FORMAT(applied_at, '%Y-%m') AS period, COUNT(*) AS total FROM applications WHERE applied_at IS NOT NULL AND applied_at >= ? AND status = 'shortlisted' GROUP BY period",
                'applied_at',
            ],
            'hired' => [
                "SELECT DATE_FORMAT(applied_at, '%Y-%m') AS period, COUNT(*) AS total FROM applications WHERE applied_at IS NOT NULL AND applied_at >= ? AND status = 'hired' GROUP BY period",
                'applied_at',
            ],
        ];

        foreach ($mappingQueries as $key => $config) {
            [$sql] = $config;
            $stmt = $this->conn->prepare($sql);
            if ($stmt === false) {
                continue;
            }
            $stmt->bind_param('s', $startDateString);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $period = $row['period'] ?? '';
                        if (isset($timeline[$period])) {
                            $timeline[$period][$key] = (int)$row['total'];
                        }
                    }
                    $result->free();
                }
            }
            $stmt->close();
        }

        return array_values($timeline);
    }

    public function getTopEmployers(int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        $sql = "
            SELECT
                e.id,
                e.company_name,
                COUNT(DISTINCT j.id) AS job_count,
                COUNT(a.id) AS application_count,
                SUM(CASE WHEN a.status = 'hired' THEN 1 ELSE 0 END) AS hired_count
            FROM employers e
            LEFT JOIN jobs j ON j.employer_id = e.id
            LEFT JOIN applications a ON a.job_id = j.id
            GROUP BY e.id, e.company_name
            ORDER BY application_count DESC, job_count DESC
            LIMIT ?
        ";

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return [];
        }
        $stmt->bind_param('i', $limit);
        $employers = [];
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $employers[] = [
                        'id' => (int)($row['id'] ?? 0),
                        'company_name' => $row['company_name'] ?? 'Doanh nghiệp',
                        'job_count' => (int)($row['job_count'] ?? 0),
                        'application_count' => (int)($row['application_count'] ?? 0),
                        'hired_count' => (int)($row['hired_count'] ?? 0),
                    ];
                }
                $result->free();
            }
        }
        $stmt->close();

        return $employers;
    }
}
