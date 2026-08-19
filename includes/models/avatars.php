<?php
declare(strict_types=1);

/**
 * Profile picture upload, processing, and display for people.avatar_path.
 *
 * Storage: uploads/avatars/<random>.jpg, outside of includes/config/database
 * (those are the only folders .htaccess denies direct access to) — avatars
 * are meant to be publicly viewable by any logged-in user, so serving them
 * as plain static files is intentional, not an oversight. Only the
 * filename is stored in the database; the path is assembled from it here so
 * the storage location could move without a data migration.
 *
 * Processing: every upload is re-encoded through GD (imagecreatefromstring()
 * + imagecopyresampled()), never stored as the raw upload — this both
 * shrinks the file (the actual ask: "reduce the size... to save space on
 * the server") and strips anything in the original file GD doesn't
 * understand as pixel data, which incidentally also closes off a class of
 * "upload a .jpg that's secretly something else" attacks. A center-cropped
 * square keeps circular display (border-radius:50%) from ever squashing or
 * off-centering a rectangular photo.
 */

const AF_AVATAR_DIR = __DIR__ . '/../../uploads/avatars';
// 256px is plenty for a profile picture shown at a few dozen CSS pixels,
// even accounting for high-DPI screens — comfortably "not that big", per
// the original ask, while still looking sharp everywhere it's used.
const AF_AVATAR_SIZE_PX = 256;
const AF_AVATAR_JPEG_QUALITY = 82;
const AF_AVATAR_MAX_UPLOAD_BYTES = 5 * 1024 * 1024; // 5MB raw upload cap, before processing
const AF_AVATAR_ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

/** Full public URL for a stored avatar filename, or null if none/blank. */
function avatar_url(?string $avatarPath): ?string
{
    if (!$avatarPath) {
        return null;
    }
    return base_url('uploads/avatars/' . rawurlencode($avatarPath));
}

/**
 * Renders either a circular <img> (if $avatarPath is set) or the existing
 * initials-circle fallback (same .af-avatar look already used in the
 * topbar), so every call site gets a consistent circular avatar either way
 * without needing to branch on whether a photo exists.
 */
function avatar_html(?string $avatarPath, string $name, int $sizePx = 28, string $extraClass = ''): string
{
    $class = trim('af-avatar-photo ' . $extraClass);
    $url = avatar_url($avatarPath);
    if ($url) {
        return sprintf(
            '<img src="%s" alt="%s" class="%s" style="width:%dpx;height:%dpx" loading="lazy">',
            e($url),
            e($name),
            e($class),
            $sizePx,
            $sizePx
        );
    }
    $initialsClass = trim('af-avatar ' . $extraClass);
    $fontPx = max(10, (int)round($sizePx * 0.4));
    return sprintf(
        '<span class="%s" style="width:%dpx;height:%dpx;font-size:%dpx">%s</span>',
        e($initialsClass),
        $sizePx,
        $sizePx,
        $fontPx,
        e(mb_substr($name, 0, 1))
    );
}

/**
 * Validates and processes an uploaded photo ($_FILES['avatar']-shaped array),
 * writing a resized, center-cropped square JPEG to uploads/avatars/ and
 * returning its filename (not yet saved to the database — see
 * set_person_avatar()). Throws InvalidArgumentException with a
 * user-presentable message on any validation failure.
 */
function process_avatar_upload(array $file): string
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new InvalidArgumentException('Choose a photo to upload.');
    }
    if ($error !== UPLOAD_ERR_OK) {
        $tooBig = in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
        throw new InvalidArgumentException(
            $tooBig ? 'That photo is too large to upload.' : 'The upload failed. Please try again.'
        );
    }
    $tmpPath = $file['tmp_name'] ?? '';
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new InvalidArgumentException('The upload failed. Please try again.');
    }
    if ((int)($file['size'] ?? 0) > AF_AVATAR_MAX_UPLOAD_BYTES) {
        throw new InvalidArgumentException('That photo is too large — please use one under 5MB.');
    }

    // Never trust the client-supplied MIME type or file extension alone —
    // getimagesize() actually parses the file's header/pixel data, so a
    // renamed non-image file is rejected here rather than trusted.
    $info = @getimagesize($tmpPath);
    if ($info === false || empty($info['mime']) || !in_array($info['mime'], AF_AVATAR_ALLOWED_MIME, true)) {
        throw new InvalidArgumentException('Please upload a JPG, PNG, GIF, or WEBP image.');
    }

    $raw = file_get_contents($tmpPath);
    $source = $raw !== false ? @imagecreatefromstring($raw) : false;
    if ($source === false) {
        throw new InvalidArgumentException('That image could not be read. Please try a different file.');
    }

    $srcW = imagesx($source);
    $srcH = imagesy($source);
    $cropSize = min($srcW, $srcH);
    $cropX = (int)(($srcW - $cropSize) / 2);
    $cropY = (int)(($srcH - $cropSize) / 2);

    $dest = imagecreatetruecolor(AF_AVATAR_SIZE_PX, AF_AVATAR_SIZE_PX);
    // Flatten onto white before the resample: the source may have
    // transparency (PNG/GIF/WEBP), but the output is always JPEG (no alpha
    // channel) for the smallest reliable file size across every browser.
    $white = imagecolorallocate($dest, 255, 255, 255);
    imagefill($dest, 0, 0, $white);
    imagecopyresampled(
        $dest, $source,
        0, 0, $cropX, $cropY,
        AF_AVATAR_SIZE_PX, AF_AVATAR_SIZE_PX, $cropSize, $cropSize
    );
    imagedestroy($source);

    if (!is_dir(AF_AVATAR_DIR)) {
        mkdir(AF_AVATAR_DIR, 0755, true);
    }
    $filename = bin2hex(random_bytes(16)) . '.jpg';
    $ok = imagejpeg($dest, AF_AVATAR_DIR . '/' . $filename, AF_AVATAR_JPEG_QUALITY);
    imagedestroy($dest);
    if (!$ok) {
        throw new InvalidArgumentException('Could not save the resized photo. Please try again.');
    }

    return $filename;
}

/** Deletes a stored avatar file if it exists. Never throws — a missing/already-gone file is a no-op, not an error. */
function delete_avatar_file(?string $avatarPath): void
{
    if (!$avatarPath) {
        return;
    }
    // basename() guards against a stray "../" ever making it into a stored
    // avatar_path (it shouldn't — process_avatar_upload() always generates
    // the filename itself — but this keeps deletion confined to the avatars
    // directory regardless).
    $path = AF_AVATAR_DIR . '/' . basename($avatarPath);
    if (is_file($path)) {
        @unlink($path);
    }
}

/** Saves a newly-processed avatar for a person, replacing (and deleting) any previous one. */
function set_person_avatar(int $personId, string $avatarPath): void
{
    $before = get_person($personId);
    db()->prepare('UPDATE people SET avatar_path = ? WHERE id = ?')->execute([$avatarPath, $personId]);
    if ($before && $before['avatar_path']) {
        delete_avatar_file($before['avatar_path']);
    }
    audit_log('person', $personId, 'avatar_updated');
}

/** Clears a person's avatar (back to initials) and deletes the stored file. */
function remove_person_avatar(int $personId): void
{
    $before = get_person($personId);
    db()->prepare('UPDATE people SET avatar_path = NULL WHERE id = ?')->execute([$personId]);
    if ($before && $before['avatar_path']) {
        delete_avatar_file($before['avatar_path']);
    }
    audit_log('person', $personId, 'avatar_removed');
}
