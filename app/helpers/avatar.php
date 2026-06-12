<?php
// app/helpers/avatar.php
// Reusable helpers for avatar upload/resizing

if (!defined('AVATAR_UPLOAD_DIR')) {
    // fallback defaults (should be defined in config.php)
    // This is the web-relative directory under `public/`, e.g. 'uploads/avatars'
    define('AVATAR_UPLOAD_DIR', 'uploads/avatars');
}
if (!defined('AVATAR_UPLOAD_PATH')) {
    define('AVATAR_UPLOAD_PATH', __DIR__ . '/../../public/uploads/avatars');
}
if (!defined('AVATAR_MAX_SIZE')) {
    define('AVATAR_MAX_SIZE', 2 * 1024 * 1024);
}
if (!defined('AVATAR_ALLOWED_MIME')) {
    define('AVATAR_ALLOWED_MIME', serialize(['image/jpeg','image/png','image/gif','image/webp','image/pjpeg','image/jpg']));
}
if (!defined('AVATAR_MAX_WIDTH')) define('AVATAR_MAX_WIDTH', 800);
if (!defined('AVATAR_MAX_HEIGHT')) define('AVATAR_MAX_HEIGHT', 800);
if (!defined('AVATAR_THUMB_SIZE')) define('AVATAR_THUMB_SIZE', 150);

function get_allowed_mimes() {
    $v = AVATAR_ALLOWED_MIME;
    return is_string($v) ? @unserialize($v) ?: (array)$v : (array)$v;
}

function resize_image($filePath, $maxW, $maxH) {
    if (!file_exists($filePath)) return false;
    $info = getimagesize($filePath);
    if (!$info) return false;
    list($w, $h) = $info;
    $mime = $info['mime'];
    $ratio = min(1, min($maxW / $w, $maxH / $h));
    $newW = (int)($w * $ratio);
    $newH = (int)($h * $ratio);
    if ($newW === $w && $newH === $h) return true;
    switch ($mime) {
        case 'image/jpeg':
        case 'image/pjpeg':
        case 'image/jpg':
            if (!function_exists('imagecreatefromjpeg')) return true;
            $src = imagecreatefromjpeg($filePath);
            break;
        case 'image/png':
            if (!function_exists('imagecreatefrompng')) return true;
            $src = imagecreatefrompng($filePath);
            break;
        case 'image/gif':
            if (!function_exists('imagecreatefromgif')) return true;
            $src = imagecreatefromgif($filePath);
            break;
        case 'image/webp':
            if (!function_exists('imagecreatefromwebp')) return true;
            $src = imagecreatefromwebp($filePath);
            break;
        default:
            return false;
    }
    $dst = imagecreatetruecolor($newW, $newH);
    if ($mime === 'image/png' || $mime === 'image/gif') {
        imagecolortransparent($dst, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }
    imagecopyresampled($dst, $src, 0,0,0,0, $newW, $newH, $w, $h);
    $ok = false;
    switch ($mime) {
        case 'image/jpeg':
        case 'image/pjpeg':
        case 'image/jpg':
            $ok = function_exists('imagejpeg') ? imagejpeg($dst, $filePath, 85) : true;
            break;
        case 'image/png':
            $ok = function_exists('imagepng') ? imagepng($dst, $filePath) : true;
            break;
        case 'image/gif':
            $ok = function_exists('imagegif') ? imagegif($dst, $filePath) : true;
            break;
        case 'image/webp':
            $ok = function_exists('imagewebp') ? imagewebp($dst, $filePath) : true;
            break;
        default:
            $ok = false;
    }
    imagedestroy($src); imagedestroy($dst);
    return $ok;
}

function handle_avatar_upload($file, &$err = null) {
    if (!isset($file) || !is_array($file)) { $err = 'Không có tệp tải lên'; return false; }
    if (!empty($file['error'])) {
        $err = avatar_upload_error_message($file['error']);
        return false;
    }
    if ($file['size'] <= 0) { $err = 'Tệp không hợp lệ'; return false; }
    if ($file['size'] > AVATAR_MAX_SIZE) { $err = 'Tệp vượt quá giới hạn cho phép'; return false; }
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
    if (!$mime) {
        $info = @getimagesize($file['tmp_name']);
        if ($info && !empty($info['mime'])) {
            $mime = $info['mime'];
        }
    }
    if (!$mime) { $err = 'Không xác định được loại tệp'; return false; }
    $mime = strtolower($mime);
    if ($mime === 'image/pjpeg' || $mime === 'image/jpg') {
        $mime = 'image/jpeg';
    }
    $allowed = get_allowed_mimes();
    if (!in_array($mime, $allowed)) { $err = 'Định dạng tệp không được hỗ trợ'; return false; }
    $ext = '';
    if ($mime === 'image/png') $ext = 'png';
    elseif ($mime === 'image/jpeg') $ext = 'jpg';
    elseif ($mime === 'image/gif') $ext = 'gif';
    elseif ($mime === 'image/webp') $ext = 'webp';
    else { $err = 'Không hỗ trợ định dạng ảnh này'; return false; }
    $uploadPath = AVATAR_UPLOAD_PATH;
    if (!is_dir($uploadPath)) {
        if (!@mkdir($uploadPath, 0755, true)) { $err = 'Không thể tạo thư mục lưu trữ'; return false; }
    }
    $filename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = rtrim($uploadPath, '/\\') . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        if (!@rename($file['tmp_name'], $dest)) {
            $err = 'Không thể lưu tệp lên máy chủ';
            return false;
        }
    }
    // Resize main
    @resize_image($dest, AVATAR_MAX_WIDTH, AVATAR_MAX_HEIGHT);
    // Create thumb
    $thumbDest = rtrim($uploadPath, '/\\') . DIRECTORY_SEPARATOR . pathinfo($filename, PATHINFO_FILENAME) . '_thumb.' . $ext;
    if (@copy($dest, $thumbDest)) {
        @resize_image($thumbDest, AVATAR_THUMB_SIZE, AVATAR_THUMB_SIZE);
    }
    // return web-relative path (consistent with earlier code)
    return rtrim(AVATAR_UPLOAD_DIR, '/\\') . '/' . $filename;
}

function remove_avatar_file(?string $relativePath): void {
    if (empty($relativePath)) {
        return;
    }

    $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');
    if ($normalized === '') {
        return;
    }

    $allowedPrefix = trim(str_replace('\\', '/', AVATAR_UPLOAD_DIR), '/');
    if ($allowedPrefix !== '' && strpos($normalized, $allowedPrefix) !== 0) {
        return;
    }

    $publicRoot = dirname(__DIR__, 2) . '/public/';
    $fullPath = $publicRoot . $normalized;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }

    $thumbPath = preg_replace('/(\.[^\/\.]+)$/', '_thumb$1', $fullPath);
    if ($thumbPath && $thumbPath !== $fullPath && is_file($thumbPath)) {
        @unlink($thumbPath);
    }
}

function avatar_upload_error_message($code) {
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Tệp vượt quá kích thước cho phép';
        case UPLOAD_ERR_PARTIAL:
            return 'Tệp tải lên chưa hoàn tất';
        case UPLOAD_ERR_NO_FILE:
            return 'Không có tệp nào được chọn';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Thiếu thư mục tạm trên máy chủ';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Không thể ghi tệp lên đĩa';
        case UPLOAD_ERR_EXTENSION:
            return 'Tiện ích mở rộng PHP đã chặn tải lên';
        default:
            return 'Không thể tải lên tệp';
    }
}


