<?php
$formatNumber = static function (int $value): string {
  return number_format($value, 0, ',', '.');
};

$statusLabels = $statusLabels ?? [];
$statusColors = $statusColors ?? [
  'applied' => 'primary',
  'viewed' => 'info',
  'shortlisted' => 'warning',
  'rejected' => 'danger',
  'hired' => 'success',
];
$chartData = $chartData ?? [
  'pipeline' => ['labels' => [], 'values' => [], 'background' => [], 'border' => []],
  'timeline' => ['labels' => [], 'series' => ['jobs' => [], 'applications' => [], 'shortlisted' => [], 'hired' => []]],
];

$interviewRate = $summary['interview_rate'] ?? 0;
$hireRate = $summary['hire_rate'] ?? 0;
$totalApplications = $summary['total_applications'] ?? 0;
$applicationsLast30 = $summary['applications_last_30_days'] ?? 0;
$activeJobs = $summary['active_jobs'] ?? 0;
$totalJobs = $summary['total_jobs'] ?? 0;
$shortlistedCount = $summary['shortlisted_applications'] ?? 0;
$hiredCount = $summary['hired_applications'] ?? 0;
$employerCount = $summary['employer_count'] ?? 0;
$candidateCount = $summary['candidate_count'] ?? 0;
?>

<section class="section dashboard">
  <div class="row">
    <div class="col-xxl-3 col-md-6">
      <div class="card info-card">
        <div class="card-body">
          <h5 class="card-title">Tin tuyển dụng <span class="text-muted">| Tổng hợp</span></h5>
          <div class="d-flex align-items-center">
            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
              <i class="bi bi-briefcase"></i>
            </div>
            <div class="ps-3">
              <h6><?= $formatNumber($totalJobs) ?></h6>
              <span class="text-success small pt-1 fw-semibold"><?= $formatNumber($activeJobs) ?></span>
              <span class="text-muted small">tin đang hiển thị</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xxl-3 col-md-6">
      <div class="card info-card">
        <div class="card-body">
          <h5 class="card-title">Hồ sơ ứng tuyển <span class="text-muted">| 30 ngày</span></h5>
          <div class="d-flex align-items-center">
            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
              <i class="bi bi-file-earmark-person"></i>
            </div>
            <div class="ps-3">
              <h6><?= $formatNumber($totalApplications) ?></h6>
              <span class="text-primary small pt-1 fw-semibold">+<?= $formatNumber($applicationsLast30) ?></span>
              <span class="text-muted small">trong 30 ngày qua</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xxl-3 col-md-6">
      <div class="card info-card">
        <div class="card-body">
          <h5 class="card-title">Tỉ lệ phỏng vấn</h5>
          <div class="d-flex align-items-center">
            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
              <i class="bi bi-people"></i>
            </div>
            <div class="ps-3">
              <h6><?= htmlspecialchars(number_format($interviewRate, 1)) ?>%</h6>
              <span class="text-muted small"><?= $formatNumber($shortlistedCount) ?> hồ sơ được chọn</span>
            </div>
          </div>
          <div class="progress mt-3" style="height: 6px;">
            <div class="progress-bar bg-warning" role="progressbar" style="width: <?= min(100, max(0, $interviewRate)) ?>%" aria-valuenow="<?= htmlspecialchars($interviewRate) ?>" aria-valuemin="0" aria-valuemax="100"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xxl-3 col-md-6">
      <div class="card info-card">
        <div class="card-body">
          <h5 class="card-title">Tỉ lệ tuyển dụng</h5>
          <div class="d-flex align-items-center">
            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
              <i class="bi bi-check-circle"></i>
            </div>
            <div class="ps-3">
              <h6><?= htmlspecialchars(number_format($hireRate, 1)) ?>%</h6>
              <span class="text-muted small"><?= $formatNumber($hiredCount) ?> ứng viên trúng tuyển</span>
            </div>
          </div>
          <div class="progress mt-3" style="height: 6px;">
            <div class="progress-bar bg-success" role="progressbar" style="width: <?= min(100, max(0, $hireRate)) ?>%" aria-valuenow="<?= htmlspecialchars($hireRate) ?>" aria-valuemin="0" aria-valuemax="100"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-6">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Tổng quan nguồn lực</h5>
          <div class="row g-3">
            <div class="col-6">
              <div class="p-3 border rounded-3 text-center h-100">
                <div class="text-muted small">Nhà tuyển dụng</div>
                <div class="fw-bold fs-4 mb-1"><?= $formatNumber($employerCount) ?></div>
                <div class="text-muted small">doanh nghiệp đang hoạt động</div>
              </div>
            </div>
            <div class="col-6">
              <div class="p-3 border rounded-3 text-center h-100">
                <div class="text-muted small">Ứng viên</div>
                <div class="fw-bold fs-4 mb-1"><?= $formatNumber($candidateCount) ?></div>
                <div class="text-muted small">tài khoản tìm việc</div>
              </div>
            </div>
            <div class="col-12">
              <div class="alert alert-info mb-0">
                <div class="fw-semibold mb-1">Giám sát chất lượng tuyển dụng</div>
                <div class="small">Quản trị viên có thể theo dõi và liên hệ doanh nghiệp khi tỉ lệ phỏng vấn hoặc tuyển dụng giảm để đảm bảo trải nghiệm ứng viên.</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Trạng thái xử lý hồ sơ</h5>
          <div class="chart-container mb-4" style="min-height: 260px;">
            <canvas id="pipelineStatusChart" height="260" aria-label="Biểu đồ trạng thái xử lý hồ sơ"></canvas>
          </div>
          <div class="table-responsive">
            <table class="table table-borderless align-middle mb-0">
              <thead>
                <tr>
                  <th>Trạng thái</th>
                  <th class="text-center">Số lượng</th>
                  <th class="text-end">Tỉ lệ</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pipeline as $statusKey => $count): ?>
                  <tr>
                    <td>
                      <span class="badge bg-<?= htmlspecialchars($statusColors[$statusKey] ?? 'secondary') ?> bg-opacity-10 text-<?= htmlspecialchars($statusColors[$statusKey] ?? 'secondary') ?> fw-semibold">
                        <?= htmlspecialchars($statusLabels[$statusKey] ?? ucfirst($statusKey)) ?>
                      </span>
                    </td>
                    <td class="text-center fw-semibold"><?= $formatNumber((int)$count) ?></td>
                    <td class="text-end">
                      <?php $percent = $pipelinePercentages[$statusKey] ?? 0; ?>
                      <div class="d-flex align-items-center gap-2 justify-content-end">
                        <div class="progress flex-grow-1" style="height: 6px; max-width: 140px;">
                          <div class="progress-bar bg-<?= htmlspecialchars($statusColors[$statusKey] ?? 'secondary') ?>" role="progressbar" style="width: <?= min(100, max(0, $percent)) ?>%" aria-valuenow="<?= htmlspecialchars($percent) ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <span class="text-muted small"><?= htmlspecialchars(number_format($percent, 1)) ?>%</span>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if ($pipelineTotal === 0): ?>
            <div class="alert alert-light border mt-3 mb-0 small">Chưa có hồ sơ ứng tuyển nào trong hệ thống.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-xl-7">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Hoạt động theo tháng <span class="text-muted">6 tháng gần đây</span></h5>
          <div class="chart-container mb-4" style="min-height: 320px;">
            <canvas id="activityTimelineChart" height="320" aria-label="Biểu đồ hoạt động theo tháng"></canvas>
          </div>
          <div class="table-responsive">
            <table class="table table-striped align-middle">
              <thead class="table-light">
                <tr>
                  <th>Tháng</th>
                  <th>Tin đăng</th>
                  <th>Hồ sơ</th>
                  <th>Phỏng vấn</th>
                  <th>Tuyển dụng</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($timeline as $row): ?>
                  <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($row['label'] ?? '') ?></td>
                    <td><?= $formatNumber((int)($row['jobs'] ?? 0)) ?></td>
                    <td><?= $formatNumber((int)($row['applications'] ?? 0)) ?></td>
                    <td><?= $formatNumber((int)($row['shortlisted'] ?? 0)) ?></td>
                    <td><?= $formatNumber((int)($row['hired'] ?? 0)) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <p class="small text-muted mb-0">Sử dụng bảng số liệu để đánh giá hiệu quả chiến dịch tuyển dụng và xác định tháng có biến động bất thường.</p>
        </div>
      </div>
    </div>

    <div class="col-xl-5">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title">Doanh nghiệp nổi bật</h5>
          <?php if (!empty($topEmployers)): ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Doanh nghiệp</th>
                    <th class="text-center">Tin đăng</th>
                    <th class="text-center">Hồ sơ</th>
                    <th class="text-center">Tuyển dụng</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($topEmployers as $employer): ?>
                    <tr>
                      <td>
                        <div class="fw-semibold"><?= htmlspecialchars($employer['company_name'] ?? 'Doanh nghiệp') ?></div>
                        <div class="text-muted small">ID #<?= (int)($employer['id'] ?? 0) ?></div>
                      </td>
                      <td class="text-center fw-semibold"><?= $formatNumber((int)($employer['job_count'] ?? 0)) ?></td>
                      <td class="text-center fw-semibold"><?= $formatNumber((int)($employer['application_count'] ?? 0)) ?></td>
                      <td class="text-center">
                        <span class="badge bg-success bg-opacity-10 text-success fw-semibold">
                          <?= $formatNumber((int)($employer['hired_count'] ?? 0)) ?>
                        </span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="alert alert-light border mb-0">Chưa có dữ liệu doanh nghiệp đủ lớn để thống kê.</div>
          <?php endif; ?>
          <p class="text-muted small mt-3 mb-0">Quản trị viên có thể ưu tiên hỗ trợ các doanh nghiệp có lượng hồ sơ cao hoặc tỉ lệ tuyển dụng thấp để tối ưu hiệu quả.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<?php $chartPayload = json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
<script src="<?= BASE_URL ?>/assets/vendor/chart.js/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof Chart === 'undefined') {
    return;
  }

  const chartData = <?php echo $chartPayload ?: '{}'; ?>;

  const pipelineEl = document.getElementById('pipelineStatusChart');
  if (pipelineEl && chartData.pipeline && chartData.pipeline.values && chartData.pipeline.values.length) {
    new Chart(pipelineEl, {
      type: 'doughnut',
      data: {
        labels: chartData.pipeline.labels || [],
        datasets: [
          {
            data: chartData.pipeline.values,
            backgroundColor: chartData.pipeline.background,
            borderColor: chartData.pipeline.border,
            borderWidth: 2,
            hoverOffset: 8
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              usePointStyle: true
            }
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                const value = context.parsed || 0;
                return `${context.label}: ${value.toLocaleString('vi-VN')} hồ sơ`;
              }
            }
          }
        }
      }
    });
  }

  const timelineEl = document.getElementById('activityTimelineChart');
  if (timelineEl && chartData.timeline && chartData.timeline.labels) {
    const series = chartData.timeline.series || {};
    const timelineDatasets = [
      { key: 'jobs', label: 'Tin đăng', borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,0.12)' },
      { key: 'applications', label: 'Hồ sơ', borderColor: '#6610f2', backgroundColor: 'rgba(102,16,242,0.12)' },
      { key: 'shortlisted', label: 'Phỏng vấn', borderColor: '#ffc107', backgroundColor: 'rgba(255,193,7,0.18)' },
      { key: 'hired', label: 'Tuyển dụng', borderColor: '#198754', backgroundColor: 'rgba(25,135,84,0.18)' }
    ].map(function (dataset) {
      return Object.assign({}, dataset, {
        data: series[dataset.key] || [],
        tension: 0.35,
        fill: true,
        pointRadius: 3,
        borderWidth: 2
      });
    });

    new Chart(timelineEl, {
      type: 'line',
      data: {
        labels: chartData.timeline.labels,
        datasets: timelineDatasets
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          mode: 'index',
          intersect: false
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              precision: 0
            }
          }
        },
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              usePointStyle: true
            }
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                const label = context.dataset.label || '';
                const value = context.parsed.y;
                return `${label}: ${value.toLocaleString('vi-VN')} lượt`;
              }
            }
          }
        }
      }
    });
  }
});
</script>
