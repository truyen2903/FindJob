<?php
require_once __DIR__ . '/../models/Candidate.php';
require_once __DIR__ . '/../models/Job.php';

class JobRecommendationService {
    private Candidate $candidateModel;
    private Job $jobModel;

    public function __construct() {
        $this->candidateModel = new Candidate();
        $this->jobModel = new Job();
    }

    public function getRecommendationsForUser(int $userId, int $limit = 6): array {
        if ($userId <= 0) {
            return [];
        }

        $candidate = $this->candidateModel->getByUserId($userId);
        if (!$candidate) {
            return [];
        }

        $skills = $this->extractSkills(
            $candidate['skills'] ?? null,
            $candidate['headline'] ?? '',
            $candidate['summary'] ?? ''
        );
        $location = trim((string)($candidate['location'] ?? ''));
        $locationPatterns = $this->buildLocationPatterns($location);
        $preferredCategories = $this->getPreferredCategoryIds((int)($candidate['id'] ?? 0));
        if (empty($preferredCategories)) {
            $preferredCategories = $this->getTrendingCategoryIds(3);
        }

        $context = [
            'candidate_id' => (int)($candidate['id'] ?? 0),
            'location_patterns' => $locationPatterns,
            'skills' => $skills,
            'preferred_categories' => $preferredCategories,
        ];

        $jobs = $this->jobModel->getSmartRecommendations($context, $limit);
        $isFallback = false;
        if (empty($jobs)) {
            $jobs = $this->jobModel->getFallbackRecommendations($limit);
            $isFallback = true;
        }

        $jobs = $this->attachHighlights($jobs, $location, $skills, $preferredCategories, $isFallback);
        if ($isFallback) {
            foreach ($jobs as &$job) {
                $job['is_fallback'] = true;
            }
            unset($job);
        }

        return $jobs;
    }

    private function extractSkills($rawSkills, string $headline = '', string $summary = ''): array {
        $skills = [];
        if (!empty($rawSkills)) {
            if (is_string($rawSkills)) {
                $decoded = json_decode($rawSkills, true);
            } elseif (is_array($rawSkills)) {
                $decoded = $rawSkills;
            } else {
                $decoded = null;
            }

            if (is_array($decoded)) {
                foreach ($decoded as $entry) {
                    $skillName = null;
                    if (is_string($entry)) {
                        $skillName = $entry;
                    } elseif (is_array($entry)) {
                        if (isset($entry['name'])) {
                            $skillName = $entry['name'];
                        } elseif (isset($entry['skill'])) {
                            $skillName = $entry['skill'];
                        }
                    }
                    $skillName = trim((string)$skillName);
                    if ($skillName !== '') {
                        $skills[] = $skillName;
                    }
                }
            }
        }

        $extraKeywords = $this->extractKeywordsFromText($headline . ' ' . $summary);
        foreach ($extraKeywords as $keyword) {
            $skills[] = $keyword;
        }

        $skills = array_values(array_unique($skills));
        return array_slice($skills, 0, 6);
    }

    private function getPreferredCategoryIds(int $candidateId, int $limit = 5): array {
        if ($candidateId <= 0) {
            return [];
        }

        $sql = "SELECT category_id, SUM(total) AS total_hits
                FROM (
                    SELECT m.category_id, COUNT(*) AS total
                    FROM applications a
                    INNER JOIN job_category_map m ON m.job_id = a.job_id
                    WHERE a.candidate_id = ? AND a.status != 'withdrawn'
                    GROUP BY m.category_id
                    UNION ALL
                    SELECT m.category_id, COUNT(*) AS total
                    FROM saved_jobs s
                    INNER JOIN job_category_map m ON m.job_id = s.job_id
                    WHERE s.candidate_id = ?
                    GROUP BY m.category_id
                ) AS combined
                GROUP BY category_id
                ORDER BY total_hits DESC
                LIMIT ?";
        $stmt = $this->candidateModel->conn->prepare($sql);
        if ($stmt === false) {
            return [];
        }

        $stmt->bind_param('iii', $candidateId, $candidateId, $limit);
        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }

        $result = $stmt->get_result();
        $categoryIds = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $categoryId = (int)($row['category_id'] ?? 0);
                if ($categoryId > 0) {
                    $categoryIds[] = $categoryId;
                }
            }
            $result->free();
        }
        $stmt->close();
        return $categoryIds;
    }

    private function getTrendingCategoryIds(int $limit = 3): array {
        $limit = max(1, min(10, $limit));
        $sql = "SELECT m.category_id, COUNT(*) AS total
                FROM job_category_map m
                INNER JOIN jobs j ON j.id = m.job_id
                WHERE j.status = 'published' AND (j.deadline IS NULL OR j.deadline >= CURDATE())
                GROUP BY m.category_id
                ORDER BY total DESC
                LIMIT ?";
        $stmt = $this->candidateModel->conn->prepare($sql);
        if ($stmt === false) {
            return [];
        }
        $stmt->bind_param('i', $limit);
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
        return $ids;
    }

    private function attachHighlights(array $jobs, string $location, array $skills, array $preferredCategories, bool $isFallback): array {
        $normalizedLocation = $this->normalizeKeyword($location);
        $skillPatterns = [];
        foreach ($skills as $skill) {
            $skillPatterns[] = [
                'label' => $skill,
                'pattern' => '/' . preg_quote($skill, '/') . '/i'
            ];
        }

        foreach ($jobs as &$job) {
            $highlights = [];
            $jobLocation = trim((string)($job['location'] ?? ''));
            if ($normalizedLocation !== '' && $jobLocation !== '') {
                $jobLocationLower = $this->normalizeKeyword($jobLocation);
                if ($jobLocationLower !== '' && strpos($jobLocationLower, $normalizedLocation) !== false) {
                    $highlights[] = 'Địa điểm phù hợp: ' . $jobLocation;
                }
            }

            $jobText = trim((string)(($job['title'] ?? '') . ' ' . ($job['job_requirements'] ?? '') . ' ' . ($job['description'] ?? '')));
            if ($jobText !== '' && !empty($skillPatterns)) {
                foreach ($skillPatterns as $pattern) {
                    if (preg_match($pattern['pattern'], $jobText)) {
                        $highlights[] = 'Kỹ năng: ' . $pattern['label'];
                        if (count($highlights) >= 2) {
                            break;
                        }
                    }
                }
            }

            if (!empty($preferredCategories) && (int)($job['matched_categories'] ?? 0) > 0) {
                $highlights[] = 'Khớp ngành nghề bạn quan tâm';
            }

            if ($isFallback && empty($highlights)) {
                $highlights[] = 'Việc làm nổi bật tuần này';
            } elseif (empty($highlights)) {
                $highlights[] = 'Gợi ý dựa trên hoạt động gần đây';
            }

            $job['highlights'] = $highlights;
        }
        unset($job);

        return $jobs;
    }

    private function buildLocationPatterns(string $location): array {
        $location = trim($location);
        if ($location === '') {
            return [];
        }
        $patterns = ['%' . $location . '%'];
        $normalized = $this->normalizeKeyword($location);
        if ($normalized !== '' && $normalized !== $this->normalizeKeyword('')) {
            $patterns[] = '%' . $normalized . '%';
        }
        return array_values(array_unique($patterns));
    }

    private function extractKeywordsFromText(string $text): array {
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        $matches = [];
        preg_match_all('/[\p{L}0-9\+#\.]{3,}/u', $text, $matches);
        if (empty($matches[0])) {
            return [];
        }
        $keywords = [];
        foreach ($matches[0] as $word) {
            $word = trim($word);
            if ($word !== '') {
                $keywords[] = $word;
            }
        }
        return array_slice(array_values(array_unique($keywords)), 0, 4);
    }

    private function normalizeKeyword(string $value): string {
        $value = trim(mb_strtolower($value, 'UTF-8'));
        if ($value === '') {
            return '';
        }
        $map = [
            'à' => 'a','á' => 'a','ạ' => 'a','ả' => 'a','ã' => 'a',
            'â' => 'a','ầ' => 'a','ấ' => 'a','ậ' => 'a','ẩ' => 'a','ẫ' => 'a',
            'ă' => 'a','ằ' => 'a','ắ' => 'a','ặ' => 'a','ẳ' => 'a','ẵ' => 'a',
            'è' => 'e','é' => 'e','ẹ' => 'e','ẻ' => 'e','ẽ' => 'e',
            'ê' => 'e','ề' => 'e','ế' => 'e','ệ' => 'e','ể' => 'e','ễ' => 'e',
            'ì' => 'i','í' => 'i','ị' => 'i','ỉ' => 'i','ĩ' => 'i',
            'ò' => 'o','ó' => 'o','ọ' => 'o','ỏ' => 'o','õ' => 'o',
            'ô' => 'o','ồ' => 'o','ố' => 'o','ộ' => 'o','ổ' => 'o','ỗ' => 'o',
            'ơ' => 'o','ờ' => 'o','ớ' => 'o','ợ' => 'o','ở' => 'o','ỡ' => 'o',
            'ù' => 'u','ú' => 'u','ụ' => 'u','ủ' => 'u','ũ' => 'u',
            'ư' => 'u','ừ' => 'u','ứ' => 'u','ự' => 'u','ử' => 'u','ữ' => 'u',
            'ỳ' => 'y','ý' => 'y','ỵ' => 'y','ỷ' => 'y','ỹ' => 'y',
            'đ' => 'd'
        ];
        return strtr($value, $map);
    }
}

