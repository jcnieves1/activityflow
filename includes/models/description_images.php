<?php
declare(strict_types=1);

/**
 * Images pasted from the clipboard into a rich text description (see the
 * paste handler + Font/Resize toolbar wiring in assets/js/app.js's
 * afInitRichText()). Unlike avatars, these aren't tracked in their own
 * database column — the <img> tag sanitize_html() lets through (src +
 * width only, see includes/functions.php) is embedded directly in the
 * description's stored HTML, the same way a pasted link or a heading is.
 * That means there's no single place that "owns" one of these files the way
 * people.avatar_path owns an avatar, so cleanup instead happens by scanning
 * a description's HTML for image URLs under our own uploads path — see
 * cleanup_removed_description_images() (called from update_activity()) and
 * delete_description_images_in_html() (called from delete_activity()).
 * An image that's copy/pasted into a task, then removed again before ever
 * saving, or a task that's abandoned without saving at all, can still leave
 * an orphaned file behind (there's no "cancel" hook to clean up after) —
 * accepted as a known, minor limitation rather than building a reference
 * counter for what is, in practice, a small number of small files.
 *
 * Storage: uploads/description_images/<random>.(jpg|png), same public,
 * script-execution-blocked folder pattern as uploads/avatars/ — see that
 * model's docblock for the reasoning.
 *
 * Processing: every upload is re-encoded through GD, same as avatars, both
 * to shrink it (the actual ask: "downscaled in bits ... to preserve server
 * space") and to strip anything GD doesn't understand as pixel data. Unlike
 * avatars this keeps the original aspect ratio (no cropping — a pasted
 * screenshot or diagram needs to stay legible) and only shrinks images
 * larger than AF_DESC_IMAGE_MAX_DIMENSION_PX on their longest side; a
 * already-small paste is stored as-is (never upscaled). Output format
 * follows the input: PNG/GIF in means PNG out (preserves transparency,
 * which screenshots occasionally have), JPEG/WEBP in means JPEG out (much
 * smaller for photographic content, and neither format needs transparency).
 */

const AF_DESC_IMAGE_DIR = __DIR__ . '/../../uploads/description_images';
// Long side cap. Big enough that a pasted screenshot/diagram is still fully
// legible at typical viewport widths; well past this, the file is almost
// always bigger than the description field will ever usefully display it.
const AF_DESC_IMAGE_MAX_DIMENSION_PX = 1400;
const AF_DESC_IMAGE_JPEG_QUALITY = 82;
const AF_DESC_IMAGE_PNG_COMPRESSION = 6; // GD scale is 0 (none) - 9 (max); 6 is a reasonable size/CPU tradeoff
// Screenshots (especially full-screen/retina ones) run bigger than a typical
// profile photo, so this cap is generous compared to AF_AVATAR_MAX_UPLOAD_BYTES.
const AF_DESC_IMAGE_MAX_UPLOAD_BYTES = 10 * 1024 * 1024; // 10MB raw upload cap, before processing
const AF_DESC_IMAGE_ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
// Kept in sync with sanitize_html()'s <img src> allow-list regex in
// includes/functions.php — both must agree on what a legitimate stored
// filename looks like.
const AF_DESC_IMAGE_FILENAME_PATTERN = '/^[a-f0-9]{32}\.(jpg|png)$/';

/** Full public URL for a stored description-image filename. */
function description_image_url(string $filename): string
{
    return base_url('uploads/description_images/' . rawurlencode($filename));
}

/**
 * Validates and processes a pasted image ($_FILES['image']-shaped array),
 * writing a downscaled, re-encoded copy to uploads/description_images/ and
 * returning its public URL. Throws InvalidArgumentException with a
 * user-presentable message on any validation failure — see
 * api/activities.php's upload_description_image action.
 */
function process_description_image_upload(array $file): string
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new InvalidArgumentException('No image was received.');
    }
    if ($error !== UPLOAD_ERR_OK) {
        $tooBig = in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
        throw new InvalidArgumentException(
            $tooBig ? 'That image is too large to upload.' : 'The upload failed. Please try again.'
        );
    }
    $tmpPath = $file['tmp_name'] ?? '';
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new InvalidArgumentException('The upload failed. Please try again.');
    }
    if ((int)($file['size'] ?? 0) > AF_DESC_IMAGE_MAX_UPLOAD_BYTES) {
        throw new InvalidArgumentException('That image is too large — please use one under 10MB.');
    }

    // Never trust the client-supplied MIME type alone — getimagesize() actually
    // parses the file's header/pixel data, so a renamed non-image file is
    // rejected here rather than trusted (same defense as avatars).
    $info = @getimagesize($tmpPath);
    if ($info === false || empty($info['mime']) || !in_array($info['mime'], AF_DESC_IMAGE_ALLOWED_MIME, true)) {
        throw new InvalidArgumentException('Please paste a JPG, PNG, GIF, or WEBP image.');
    }
    $preserveTransparency = in_array($info['mime'], ['image/png', 'image/gif'], true);

    $raw = file_get_contents($tmpPath);
    $source = $raw !== false ? @imagecreatefromstring($raw) : false;
    if ($source === false) {
        throw new InvalidArgumentException('That image could not be read. Please try a different file.');
    }

    $srcW = imagesx($source);
    $srcH = imagesy($source);
    $longest = max($srcW, $srcH);
    if ($longest > AF_DESC_IMAGE_MAX_DIMENSION_PX) {
        $scale = AF_DESC_IMAGE_MAX_DIMENSION_PX / $longest;
        $dstW = max(1, (int)round($srcW * $scale));
        $dstH = max(1, (int)round($srcH * $scale));
    } else {
        // Already small enough — never upscale a small paste just to hit the cap.
        $dstW = $srcW;
        $dstH = $srcH;
    }

    $dest = imagecreatetruecolor($dstW, $dstH);
    if ($preserveTransparency) {
        imagealphablending($dest, false);
        imagesavealpha($dest, true);
        $transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);
        imagefill($dest, 0, 0, $transparent);
    }
    imagecopyresampled($dest, $source, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
    imagedestroy($source);

    if (!is_dir(AF_DESC_IMAGE_DIR)) {
        mkdir(AF_DESC_IMAGE_DIR, 0755, true);
    }
    $basename = bin2hex(random_bytes(16));
    if ($preserveTransparency) {
        $filename = $basename . '.png';
        $ok = imagepng($dest, AF_DESC_IMAGE_DIR . '/' . $filename, AF_DESC_IMAGE_PNG_COMPRESSION);
    } else {
        $filename = $basename . '.jpg';
        $ok = imagejpeg($dest, AF_DESC_IMAGE_DIR . '/' . $filename, AF_DESC_IMAGE_JPEG_QUALITY);
    }
    imagedestroy($dest);
    if (!$ok) {
        throw new InvalidArgumentException('Could not save the image. Please try again.');
    }

    return description_image_url($filename);
}

/**
 * Pulls every uploads/description_images/<filename> reference out of a
 * chunk of (already-sanitized) description HTML. Used by both cleanup
 * functions below so they always agree on what "an image in this
 * description" means.
 */
function _description_image_filenames_in_html(?string $html): array
{
    if (!$html) {
        return [];
    }
    if (!preg_match_all('#uploads/description_images/([a-f0-9]{32}\.(?:jpg|png))#', $html, $matches)) {
        return [];
    }
    return array_values(array_unique($matches[1]));
}

/** Deletes a stored description-image file if it exists. Never throws. */
function _delete_description_image_file(string $filename): void
{
    // basename() + the filename pattern check keep this confined to the
    // description_images directory regardless of what a caller passes in.
    if (!preg_match(AF_DESC_IMAGE_FILENAME_PATTERN, $filename)) {
        return;
    }
    $path = AF_DESC_IMAGE_DIR . '/' . basename($filename);
    if (is_file($path)) {
        @unlink($path);
    }
}

/**
 * Called after an activity's description is edited: deletes any pasted
 * image files that were in the old description but aren't in the new one
 * (removed by the user, or replaced by a fresh paste) — see
 * update_activity() in includes/models/activities.php.
 */
function cleanup_removed_description_images(?string $oldHtml, ?string $newHtml): void
{
    $before = _description_image_filenames_in_html($oldHtml);
    if (!$before) {
        return;
    }
    $after = _description_image_filenames_in_html($newHtml);
    foreach (array_diff($before, $after) as $filename) {
        _delete_description_image_file($filename);
    }
}

/**
 * Called when an activity is permanently deleted: deletes every pasted
 * image file still referenced in its description — see delete_activity().
 */
function delete_description_images_in_html(?string $html): void
{
    foreach (_description_image_filenames_in_html($html) as $filename) {
        _delete_description_image_file($filename);
    }
}
