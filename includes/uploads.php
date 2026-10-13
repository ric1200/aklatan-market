<?php
/**
 * Safe image uploads.
 * Rules: real image bytes only (checked twice), strict size limit, strict
 * extension allow-list, random file name, never trust the client file name,
 * and the destination folder refuses to execute PHP.
 */

function upload_allowed_types(): array
{
    return [
        IMAGETYPE_JPEG => ['ext' => 'jpg', 'mime' => ['image/jpeg', 'image/pjpeg']],
        IMAGETYPE_PNG  => ['ext' => 'png', 'mime' => ['image/png', 'image/x-png']],
        IMAGETYPE_GIF  => ['ext' => 'gif', 'mime' => ['image/gif']],
        IMAGETYPE_WEBP => ['ext' => 'webp', 'mime' => ['image/webp']],
    ];
}

/**
 * @return array{0:bool,1:string,2:?string} ok, message/error, relative path
 */
function save_uploaded_image(array $file, string $subdir = 'products'): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return [false, 'That upload did not arrive correctly. Please try again.', null];
    }
    switch ((int) $file['error']) {
        case UPLOAD_ERR_OK: break;
        case UPLOAD_ERR_NO_FILE: return [false, 'No file was selected.', null];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE: return [false, 'That image is larger than ' . human_bytes((int) UPLOAD_MAX_BYTES) . '.', null];
        case UPLOAD_ERR_PARTIAL: return [false, 'The upload was interrupted. Please try again.', null];
        default: return [false, 'The image could not be uploaded (server error ' . (int) $file['error'] . ').', null];
    }

    if ((int) $file['size'] <= 0) { return [false, 'That file is empty.', null]; }
    if ((int) $file['size'] > (int) UPLOAD_MAX_BYTES) {
        return [false, 'That image is ' . human_bytes((int) $file['size']) . '. The limit is ' . human_bytes((int) UPLOAD_MAX_BYTES) . '.', null];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return [false, 'That upload failed a security check.', null];
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false || empty($info[2])) {
        return [false, 'That file is not a readable image. Please upload a JPG, PNG, GIF or WebP picture.', null];
    }
    $allowed = upload_allowed_types();
    $type = (int) $info[2];
    if (!isset($allowed[$type])) {
        return [false, 'Only JPG, PNG, GIF and WebP images are accepted.', null];
    }

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, $file['tmp_name']) : '';
        if ($finfo) { finfo_close($finfo); }
        if ($mime !== '' && !in_array(strtolower($mime), $allowed[$type]['mime'], true)) {
            return [false, 'The file content does not match its image type. Upload cancelled.', null];
        }
    }

    $dir = APP_ROOT . '/uploads/' . preg_replace('/[^a-z0-9_\-]/i', '', $subdir) . '/';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return [false, 'The upload folder is not writable. Set permissions on /uploads and try again.', null];
    }

    $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $allowed[$type]['ext'];
    $target = $dir . $name;
    if (!@move_uploaded_file($file['tmp_name'], $target)) {
        return [false, 'The image could not be saved. Please check folder permissions and try again.', null];
    }
    @chmod($target, 0644);

    return [true, 'Image uploaded.', 'uploads/' . basename($dir) . '/' . $name];
}

/** Remove an uploaded file, but only inside our own uploads folder. */
function delete_upload(?string $relativePath): void
{
    $relativePath = trim((string) $relativePath);
    if ($relativePath === '' || strpos($relativePath, '..') !== false) { return; }
    if (strpos(ltrim($relativePath, '/'), 'uploads/') !== 0) { return; }
    $full = APP_ROOT . '/' . ltrim($relativePath, '/');
    if (is_file($full)) { @unlink($full); }
}
