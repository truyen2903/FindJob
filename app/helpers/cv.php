<?php
// app/helpers/cv.php
// Helper for CV uploads (PDF / DOCX / DOC)

if (!defined('CV_UPLOAD_DIR')) {
    define('CV_UPLOAD_DIR', 'uploads/cv'); // web-relative under public/
}
if (!defined('CV_UPLOAD_PATH')) {
    define('CV_UPLOAD_PATH', __DIR__ . '/../../public/uploads/cv');
}
if (!defined('CV_MAX_SIZE')) {
    define('CV_MAX_SIZE', 5 * 1024 * 1024); // 5MB
}
if (!defined('CV_ALLOWED_MIME')) {
    define('CV_ALLOWED_MIME', serialize([
        'application/pdf',
        'application/x-pdf',
        'application/acrobat',
        'application/msword',
        'application/vnd.ms-word',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/octet-stream',
        'application/zip',
        'application/x-zip-compressed'
    ]));
}

function get_allowed_cv_mimes() {
    $v = CV_ALLOWED_MIME;
    return is_string($v) ? @unserialize($v) ?: (array)$v : (array)$v;
}

function handle_cv_upload($file, &$err = null) {
    if (!isset($file) || !is_array($file)) {
        $err = 'No file uploaded';
        return false;
    }

    if (!empty($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
        $err = 'Không thể tải lên tệp CV (mã lỗi: ' . (int)$file['error'] . ')';
        return false;
    }

    if ($file['size'] <= 0) {
        $err = 'Tệp CV không hợp lệ';
        return false;
    }

    if ($file['size'] > CV_MAX_SIZE) {
        $err = 'File quá lớn, tối đa ' . (CV_MAX_SIZE / 1024 / 1024) . ' MB';
        return false;
    }

    $originalName = $file['name'] ?? '';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = ['pdf', 'doc', 'docx'];
    if (!in_array($extension, $allowedExtensions, true)) {
        $err = 'Chỉ hỗ trợ các định dạng PDF, DOC hoặc DOCX';
        return false;
    }

    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
    }

    if (!$mime && function_exists('mime_content_type')) {
        $mime = @mime_content_type($file['tmp_name']);
    }

    $allowed = get_allowed_cv_mimes();
    if ($mime && !in_array(strtolower($mime), array_map('strtolower', $allowed), true)) {
        $genericAllowed = ['application/octet-stream'];
        $docxFallback = ['application/zip', 'application/x-zip-compressed'];
        $isGeneric = in_array(strtolower($mime), $genericAllowed, true);
        $isDocxZip = in_array(strtolower($mime), $docxFallback, true) && $extension === 'docx';
        if (!$isGeneric && !$isDocxZip) {
            $err = 'Loại file không hợp lệ. Chỉ cho phép PDF/DOC/DOCX';
            return false;
        }
    }

    $ext = $extension !== '' ? $extension : 'pdf';

    $uploadPath = CV_UPLOAD_PATH;
    if (!is_dir($uploadPath)) {
        if (!@mkdir($uploadPath, 0755, true)) { $err = 'Không thể tạo thư mục upload CV'; return false; }
    }
    $filename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = rtrim($uploadPath, '/\\') . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) { $err = 'Không thể lưu file'; return false; }
    return rtrim(CV_UPLOAD_DIR, '/\\') . '/' . $filename;
}


