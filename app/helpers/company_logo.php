<?php
// app/helpers/company_logo.php
// Hỗ trợ xử lý upload logo doanh nghiệp

if (!defined('COMPANY_LOGO_UPLOAD_DIR')) {
    define('COMPANY_LOGO_UPLOAD_DIR', 'uploads/company-logos');
}
if (!defined('COMPANY_LOGO_UPLOAD_PATH')) {
    define('COMPANY_LOGO_UPLOAD_PATH', __DIR__ . '/../../public/' . COMPANY_LOGO_UPLOAD_DIR);
}
if (!defined('COMPANY_LOGO_MAX_SIZE')) {
    define('COMPANY_LOGO_MAX_SIZE', 3 * 1024 * 1024);
}
if (!defined('COMPANY_LOGO_ALLOWED_MIME')) {
    define('COMPANY_LOGO_ALLOWED_MIME', serialize(['image/png','image/jpeg','image/gif','image/webp','image/pjpeg','image/jpg']));
}
if (!defined('COMPANY_LOGO_MAX_WIDTH')) {
    define('COMPANY_LOGO_MAX_WIDTH', 600);
}
if (!defined('COMPANY_LOGO_MAX_HEIGHT')) {
    define('COMPANY_LOGO_MAX_HEIGHT', 600);
}

function employer_get_allowed_logo_mimes(): array {
    $value = COMPANY_LOGO_ALLOWED_MIME;
    if (is_string($value)) {
        $decoded = @unserialize($value);
        if ($decoded !== false) {
            return (array)$decoded;
        }
    }
    return (array)$value;
}

function employer_logo_upload_error_message(int $code): string {
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Tệp logo vượt quá kích thước cho phép.';
        case UPLOAD_ERR_PARTIAL:
            return 'Tệp logo tải lên chưa hoàn tất.';
        case UPLOAD_ERR_NO_FILE:
            return 'Vui lòng chọn tệp logo.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Máy chủ thiếu thư mục tạm để xử lý logo.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Không thể ghi tệp logo lên máy chủ.';
        case UPLOAD_ERR_EXTENSION:
            return 'Tiện ích mở rộng PHP đã chặn tải logo.';
        default:
            return 'Không thể tải lên logo doanh nghiệp.';
    }
}

function employer_resize_logo(string $filePath): bool {
    if (!file_exists($filePath)) {
        return false;
    }
    $info = @getimagesize($filePath);
    if (!$info) {
        return false;
    }
    [$width, $height] = $info;
    $mime = $info['mime'] ?? '';
    $ratio = min(1, min(COMPANY_LOGO_MAX_WIDTH / $width, COMPANY_LOGO_MAX_HEIGHT / $height));
    if ($ratio >= 1) {
        return true;
    }
    $newW = (int)($width * $ratio);
    $newH = (int)($height * $ratio);

    switch ($mime) {
        case 'image/jpeg':
        case 'image/pjpeg':
        case 'image/jpg':
            if (!function_exists('imagecreatefromjpeg')) {
                return true;
            }
            $src = imagecreatefromjpeg($filePath);
            break;
        case 'image/png':
            if (!function_exists('imagecreatefrompng')) {
                return true;
            }
            $src = imagecreatefrompng($filePath);
            break;
        case 'image/gif':
            if (!function_exists('imagecreatefromgif')) {
                return true;
            }
            $src = imagecreatefromgif($filePath);
            break;
        case 'image/webp':
            if (!function_exists('imagecreatefromwebp')) {
                return true;
            }
            $src = imagecreatefromwebp($filePath);
            break;
        default:
            return false;
    }

    $dst = imagecreatetruecolor($newW, $newH);
    if (in_array($mime, ['image/png', 'image/gif'], true)) {
        imagecolortransparent($dst, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);
    $result = false;
    switch ($mime) {
        case 'image/jpeg':
        case 'image/pjpeg':
        case 'image/jpg':
            $result = function_exists('imagejpeg') ? imagejpeg($dst, $filePath, 85) : true;
            break;
        case 'image/png':
            $result = function_exists('imagepng') ? imagepng($dst, $filePath) : true;
            break;
        case 'image/gif':
            $result = function_exists('imagegif') ? imagegif($dst, $filePath) : true;
            break;
        case 'image/webp':
            $result = function_exists('imagewebp') ? imagewebp($dst, $filePath) : true;
            break;
    }

    imagedestroy($src);
    imagedestroy($dst);
    return $result;
}

function employer_handle_logo_upload(array $file, ?string &$error = null) {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Vui lòng chọn tệp logo.';
        return false;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = employer_logo_upload_error_message((int)$file['error']);
        return false;
    }
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $error = 'Không tìm thấy tệp logo tải lên.';
        return false;
    }
    if ((int)$file['size'] <= 0) {
        $error = 'Tệp logo không hợp lệ.';
        return false;
    }
    if ((int)$file['size'] > COMPANY_LOGO_MAX_SIZE) {
        $error = 'Logo vượt quá dung lượng tối đa cho phép (3MB).';
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
    if (!$mime) {
        $info = @getimagesize($file['tmp_name']);
        if ($info && !empty($info['mime'])) {
            $mime = $info['mime'];
        }
    }
    if (!$mime) {
        $error = 'Không xác định được định dạng logo.';
        return false;
    }

    $mime = strtolower($mime);
    if ($mime === 'image/pjpeg' || $mime === 'image/jpg') {
        $mime = 'image/jpeg';
    }

    $allowed = employer_get_allowed_logo_mimes();
    if (!in_array($mime, $allowed, true)) {
        $error = 'Định dạng logo không được hỗ trợ. Vui lòng dùng PNG, JPG, GIF hoặc WEBP.';
        return false;
    }

    $ext = match ($mime) {
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        default => ''
    };
    if ($ext === '') {
        $error = 'Không hỗ trợ định dạng logo này.';
        return false;
    }

    $uploadDir = rtrim(COMPANY_LOGO_UPLOAD_PATH, '/\\');
    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            $error = 'Không thể tạo thư mục lưu logo.';
            return false;
        }
    }

    $filename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destination = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        if (!@rename($file['tmp_name'], $destination)) {
            $error = 'Không thể lưu logo trên máy chủ.';
            return false;
        }
    }

    employer_resize_logo($destination);

    return rtrim(COMPANY_LOGO_UPLOAD_DIR, '/\\') . '/' . $filename;
}

function employer_remove_logo(?string $relativePath): void {
    if (!$relativePath) {
        return;
    }
    $relativePath = ltrim($relativePath, '/\\');
    $fullPath = rtrim(__DIR__ . '/../../public', '/\\') . DIRECTORY_SEPARATOR . $relativePath;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}


