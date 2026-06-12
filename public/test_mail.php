<?php
require_once __DIR__ . '/../config/config.php';

$to = isset($_GET['to']) && filter_var($_GET['to'], FILTER_VALIDATE_EMAIL)
    ? $_GET['to']
    : 'you@example.com';

$subject = '[JobFind] SMTP test ' . date('Y-m-d H:i:s');
$bodyLines = [
    'Xin chào,',
    '',
    'Đây là email test để kiểm tra cấu hình SMTP trong JobFind.',
    'Nếu bạn nhận được thư này, cấu hình đã thành công.',
    '',
    'Trân trọng,',
    'JobFind Bot'
];
$body = implode("\n", $bodyLines);

$headers = 'From: no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'jobfind.local') . "\r\n"
    . "MIME-Version: 1.0\r\n"
    . "Content-Type: text/plain; charset=UTF-8\r\n";

$result = mail($to, $subject, $body, $headers);

header('Content-Type: text/plain; charset=UTF-8');
echo $result ? "Mail đã được gửi tới {$to}" : 'Gửi mail thất bại. Kiểm tra cấu hình SMTP.';

echo "\n\nCó thể truyền email khác: /test_mail.php?to=user@domain.com\n";
