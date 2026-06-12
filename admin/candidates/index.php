<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/app/models/Candidate.php';

if (!isset($_SESSION['user_id']) || (int)($_SESSION['role_id'] ?? 0) !== 1) {
    header('Location: ' . BASE_URL . '/403.php');
    exit;
}

$candidateModel = new Candidate();

$filters = [
    'keyword' => trim($_GET['keyword'] ?? ''),
    'location' => trim($_GET['location'] ?? ''),
    'skill' => trim($_GET['skill'] ?? ''),
    'cv_status' => trim($_GET['cv_status'] ?? '')
];

if (!in_array($filters['cv_status'], ['has', 'missing'], true)) {
    $filters['cv_status'] = '';
}

$where = ['u.role_id = 3'];
$params = [];
$types = '';

if ($filters['keyword'] !== '') {
    $like = '%' . $filters['keyword'] . '%';
    $where[] = '(u.name LIKE ? OR u.email LIKE ? OR c.headline LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

if ($filters['location'] !== '') {
    $where[] = "COALESCE(c.location, '') LIKE ?";
    $params[] = '%' . $filters['location'] . '%';
    $types .= 's';
}

if ($filters['skill'] !== '') {
    $where[] = "COALESCE(c.skills, '') LIKE ?";
    $params[] = '%' . $filters['skill'] . '%';
    $types .= 's';
}

if ($filters['cv_status'] === 'has') {
    $where[] = "c.cv_path IS NOT NULL AND c.cv_path <> ''";
} elseif ($filters['cv_status'] === 'missing') {
    $where[] = "(c.cv_path IS NULL OR c.cv_path = '')";
}

$sql = "
    SELECT
        u.id AS user_id,
        u.name AS full_name,
        u.email,
        u.phone,
        u.created_at AS user_created_at,
        c.id AS candidate_id,
        c.headline,
        c.location,
        c.skills,
        c.experience,
        c.cv_path,
        c.updated_at AS cv_updated_at
    FROM users u
    LEFT JOIN candidates c ON c.user_id = u.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY c.updated_at DESC, u.created_at DESC
";

$result = null;
$queryError = null;

if (!empty($params)) {
    $stmt = $candidateModel->conn->prepare($sql);
    if ($stmt === false) {
        $queryError = $candidateModel->conn->error;
    } else {
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
        } else {
            $queryError = $stmt->error;
        }
    }
} else {
    $result = $candidateModel->conn->query($sql);
    if ($result === false) {
        $queryError = $candidateModel->conn->error;
    }
}

$candidates = [];
if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $candidates[] = $row;
    }
    $result->free();
}
if (isset($stmt) && $stmt instanceof mysqli_stmt) {
  $stmt->close();
}

$totalCandidates = count($candidates);
$withCv = 0;
$withoutCv = 0;
$recentCv = 0;
$recentThreshold = strtotime('-30 days');

foreach ($candidates as $row) {
    $hasCv = !empty($row['cv_path']);
    if ($hasCv) {
        $withCv++;
        $updatedSource = $row['cv_updated_at'] ?: $row['user_created_at'];
        $updatedTs = $updatedSource ? strtotime($updatedSource) : false;
        if ($updatedTs && $updatedTs >= $recentThreshold) {
            $recentCv++;
        }
    } else {
        $withoutCv++;
    }
}

ob_start();
?>
<div class="pagetitle">
  <h1>Quản lý ứng viên</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/dashboard.php">Trang chủ</a></li>
      <li class="breadcrumb-item active">Ứng viên</li>
    </ol>
  </nav>
</div>

<section class="section">
  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
          <div class="text-muted small">Tổng ứng viên</div>
          <div class="fs-4 fw-semibold"><?= $totalCandidates ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
          <div class="text-muted small">Ứng viên có CV</div>
          <div class="fs-4 fw-semibold text-success"><?= $withCv ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
          <div class="text-muted small">CV cập nhật 30 ngày gần đây</div>
          <div class="fs-4 fw-semibold text-primary"><?= $recentCv ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <form class="row gy-2 gx-3 align-items-end mb-4" method="get">
        <div class="col-sm-6 col-lg-3">
          <label class="form-label" for="filterKeyword">Từ khóa</label>
          <input type="text" id="filterKeyword" name="keyword" class="form-control" placeholder="Tên, email hoặc tiêu đề" value="<?= htmlspecialchars($filters['keyword']) ?>">
        </div>
        <div class="col-sm-6 col-lg-3">
          <label class="form-label" for="filterLocation">Địa điểm</label>
          <input type="text" id="filterLocation" name="location" class="form-control" placeholder="Ví dụ: Hà Nội" value="<?= htmlspecialchars($filters['location']) ?>">
        </div>
        <div class="col-sm-6 col-lg-3">
          <label class="form-label" for="filterSkill">Kỹ năng</label>
          <input type="text" id="filterSkill" name="skill" class="form-control" placeholder="Ví dụ: PHP" value="<?= htmlspecialchars($filters['skill']) ?>">
        </div>
        <div class="col-sm-6 col-lg-2">
          <label class="form-label" for="filterCvStatus">Trạng thái CV</label>
          <select id="filterCvStatus" name="cv_status" class="form-select">
            <option value="" <?= $filters['cv_status'] === '' ? 'selected' : '' ?>>Tất cả</option>
            <option value="has" <?= $filters['cv_status'] === 'has' ? 'selected' : '' ?>>Đã có CV</option>
            <option value="missing" <?= $filters['cv_status'] === 'missing' ? 'selected' : '' ?>>Thiếu CV</option>
          </select>
        </div>
        <div class="col-sm-12 col-lg-1 text-lg-end">
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-filter"></i>
          </button>
        </div>
        <div class="col-sm-12 col-lg-12 text-lg-end">
          <a href="<?= ADMIN_URL ?>/candidates/index.php" class="btn btn-light btn-sm">Đặt lại</a>
        </div>
      </form>

      <?php if ($queryError): ?>
        <div class="alert alert-danger">
          Không thể tải danh sách ứng viên. Chi tiết: <?= htmlspecialchars($queryError) ?>
        </div>
      <?php endif; ?>

      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle" id="candidatesTable">
          <thead class="table-light">
            <tr>
              <th scope="col">ID</th>
              <th scope="col">Ứng viên</th>
              <th scope="col">Tiêu đề</th>
              <th scope="col">Địa điểm</th>
              <th scope="col">Kỹ năng nổi bật</th>
              <th scope="col">Kinh nghiệm</th>
              <th scope="col">CV</th>
              <th scope="col">Cập nhật</th>
              <th scope="col" class="text-end">Hành động</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($candidates)): ?>
              <tr>
                <td colspan="9" class="text-center text-muted">Chưa có ứng viên phù hợp bộ lọc.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($candidates as $candidate): ?>
                <?php
                  $skillsList = [];
                  if (!empty($candidate['skills'])) {
                      $decodedSkills = json_decode($candidate['skills'], true);
                      if (is_array($decodedSkills)) {
                          foreach ($decodedSkills as $skillItem) {
                              $skillText = trim((string)$skillItem);
                              if ($skillText !== '') {
                                  $skillsList[] = $skillText;
                              }
                          }
                      }
                  }
                  $experienceList = [];
                  if (!empty($candidate['experience'])) {
                      $decodedExperience = json_decode($candidate['experience'], true);
                      if (is_array($decodedExperience)) {
                          $experienceList = $decodedExperience;
                      }
                  }
                  $experienceCount = count($experienceList);
                  $cvUrl = '';
                  if (!empty($candidate['cv_path'])) {
                      $cvUrl = BASE_URL . '/' . ltrim($candidate['cv_path'], '/');
                  }
                  $updatedLabel = '';
                  if (!empty($candidate['cv_updated_at'])) {
                      $ts = strtotime($candidate['cv_updated_at']);
                      $updatedLabel = $ts ? date('d/m/Y H:i', $ts) : $candidate['cv_updated_at'];
                  } elseif (!empty($candidate['user_created_at'])) {
                      $ts = strtotime($candidate['user_created_at']);
                      $updatedLabel = $ts ? date('d/m/Y', $ts) : $candidate['user_created_at'];
                  } else {
                      $updatedLabel = '—';
                  }
                  $headline = $candidate['headline'] ?: 'Chưa cập nhật';
                  $location = $candidate['location'] ?: 'Chưa cập nhật';
                  $fullName = $candidate['full_name'] ?: 'Ứng viên JobFind';
                ?>
                <tr>
                  <td><?= $candidate['candidate_id'] ? (int)$candidate['candidate_id'] : '—' ?></td>
                  <td>
                    <div class="fw-semibold"><?= htmlspecialchars($fullName) ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($candidate['email']) ?></div>
                  </td>
                  <td><?= htmlspecialchars($headline) ?></td>
                  <td><?= htmlspecialchars($location) ?></td>
                  <td>
                    <?php if (!empty($skillsList)): ?>
                      <?php foreach (array_slice($skillsList, 0, 4) as $skill): ?>
                        <span class="badge bg-light text-dark me-1 mb-1"><?= htmlspecialchars($skill) ?></span>
                      <?php endforeach; ?>
                      <?php if (count($skillsList) > 4): ?>
                        <span class="badge bg-secondary">+<?= count($skillsList) - 4 ?></span>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="text-muted small">Chưa cập nhật</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($experienceCount > 0): ?>
                      <span class="badge bg-info text-dark"><?= $experienceCount ?> vị trí</span>
                    <?php else: ?>
                      <span class="text-muted small">Chưa cập nhật</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($cvUrl): ?>
                      <a href="<?= htmlspecialchars($cvUrl) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-file-earmark-arrow-down"></i> Tải
                      </a>
                    <?php else: ?>
                      <span class="badge bg-secondary">Không có</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($updatedLabel) ?></td>
                  <td class="text-end">
                    <div class="btn-group">
                      <a href="<?= BASE_URL ?>/candidate/profile.php?user=<?= (int)$candidate['user_id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Xem hồ sơ">
                        <i class="bi bi-eye"></i>
                      </a>
                      <a href="mailto:<?= htmlspecialchars($candidate['email']) ?>" class="btn btn-sm btn-outline-success" title="Gửi email">
                        <i class="bi bi-envelope"></i>
                      </a>
                      <a href="<?= ADMIN_URL ?>/candidates/delete_candidate.php?user_id=<?= (int)$candidate['user_id'] ?>" class="btn btn-sm btn-outline-danger" title="Xóa ứng viên">
                        <i class="bi bi-trash"></i>
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout.php';
