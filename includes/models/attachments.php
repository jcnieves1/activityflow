<?php
declare(strict_types=1);

/**
 * Supporting documents uploaded to a project or a task ("Edit Project" /
 * "Edit Activity" dialogs). Polymorphic via entity_type + entity_id, mirroring
 * the audit_logs table's own entity_type/entity_id pattern (see
 * database/schema.sql's attachments table).
 *
 * Unlike avatars and description_images (intentionally public-to-any-logged-in
 * -user, stored under the web-accessible uploads/ folder), an attachment must
 * respect its parent project/task's own visibility rules — a restricted
 * Employee who can't see a project shouldn't be able to fetch its files by a
 * guessable URL either. So these are stored under storage/attachments/, a
 * folder outside uploads/ and denied ALL direct web access by its own
 * .htaccess (see that folder), and are only ever readable through
 * api/attachments.php's 'download' action, which re-checks can_view_project()
 * / activity_is_visible() before streaming a single byte — see
 * stream_attachment_download() below.
 *
 * Validation is a strict allow-list by file extension (never a block-list):
 * common office/document formats and common image formats only — nothing
 * that could plausibly be executed by a server or a browser (no .php/.js/
 * .html/.svg/.exe/.zip/etc). Both the extension AND the file's actual
 * sniffed content type (via fileinfo, since these aren't all images that GD
 * could validate the way avatars/description images are) must agree before
 * a file is accepted — see AF_ATTACHMENT_ALLOWED_EXTENSIONS and
 * process_attachment_upload(). Office formats get a deliberately widened set
 * of acceptable sniffed MIME types (see that constant's own comment) because
 * legacy binary Office files and modern zip-based Office/OpenDocument files
 * are not always fingerprinted precisely by every server's fileinfo/libmagic
 * database — the extension allow-list is what actually keeps dangerous file
 * types out; the MIME check is a secondary sanity check on top of it, not
 * the only line of defense.
 */

const AF_ATTACHMENT_DIR = __DIR__ . '/../../storage/attachments';
// Documents can legitimately run bigger than a pasted screenshot — generous
// compared to AF_DESC_IMAGE_MAX_UPLOAD_BYTES, but still bounded to protect
// server space per the feature request.
const AF_ATTACHMENT_MAX_UPLOAD_BYTES = 25 * 1024 * 1024; // 25MB per file
// Defensive cap on a single upload request, independent of PHP's own
// max_file_uploads ini setting — keeps one request from looping over an
// unreasonable number of files.
const AF_ATTACHMENT_MAX_FILES_PER_UPLOAD = 20;
const AF_ATTACHMENT_FILENAME_PATTERN = '/^[a-f0-9]{32}\.[a-z0-9]{1,5}$/';

/**
 * Extension => list of acceptable fileinfo-sniffed MIME types. An upload is
 * rejected unless its extension is a key here AND its sniffed content type is
 * one of the listed values for that key — see process_attachment_upload().
 *
 * 'application/zip' and 'application/octet-stream'/'application/vnd.ms-office'
 * are allowed ONLY for the specific Office/OpenDocument extensions that are
 * legitimately zip-based (docx/xlsx/pptx/odt/ods/odp) or older OLE-compound
 * binary formats (doc/xls/ppt) that some fileinfo databases don't fingerprint
 * more specifically than that. This does not widen what CAN be uploaded —
 * the extension itself is still checked strictly against this same
 * allow-list first, so a generic .zip (or any other extension not listed
 * below, including no extension at all) is still rejected outright.
 */
const AF_ATTACHMENT_ALLOWED_EXTENSIONS = [
    // Documents
    'pdf'  => ['application/pdf'],
    'doc'  => ['application/msword', 'application/vnd.ms-office', 'application/octet-stream'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    'xls'  => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/octet-stream'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    'ppt'  => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/octet-stream'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
    'odt'  => ['application/vnd.oasis.opendocument.text', 'application/zip'],
    'ods'  => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
    'odp'  => ['application/vnd.oasis.opendocument.presentation', 'application/zip'],
    'rtf'  => ['application/rtf', 'text/rtf'],
    'txt'  => ['text/plain'],
    'csv'  => ['text/plain', 'text/csv', 'application/csv'],
    // Images
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
    'gif'  => ['image/gif'],
    'webp' => ['image/webp'],
    'bmp'  => ['image/bmp', 'image/x-ms-bmp'],
];

/** All attachments for a project or task, newest first, with the uploader's name joined in. */
function list_attachments(string $entityType, int $entityId): array
{
    $stmt = db()->prepare(
        'SELECT a.*, u.full_name AS uploaded_by_name FROM attachments a
         LEFT JOIN users u ON u.id = a.uploaded_by
         WHERE a.entity_type = ? AND a.entity_id = ? ORDER BY a.created_at DESC'
    );
    $stmt->execute([$entityType, $entityId]);
    return $stmt->fetchAll();
}

function get_attachment(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM attachments WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Reshapes a $_FILES['field'] entry uploaded via a multi-file
 * <input type="file" multiple> (PHP's native "parallel arrays" shape —
 * name[], type[], tmp_name[], error[], size[], all indexed the same) into a
 * flat list of ordinary single-file arrays, so upload handling code can loop
 * over one file at a time regardless of whether the browser sent one file or
 * several. A single (non-array) entry is passed through unchanged, wrapped
 * in a one-element list, so the caller's loop is the same either way.
 */
function normalize_uploaded_files_array(array $filesEntry): array
{
    if (!isset($filesEntry['name'])) {
        return [];
    }
    if (!is_array($filesEntry['name'])) {
        return [$filesEntry];
    }
    $out = [];
    foreach (array_keys($filesEntry['name']) as $i) {
        $name = $filesEntry['name'][$i] ?? '';
        $error = $filesEntry['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($name === '' && $error === UPLOAD_ERR_NO_FILE) {
            continue; // an empty slot (browser sent fewer files than the input allowed)
        }
        $out[] = [
            'name' => $name,
            'type' => $filesEntry['type'][$i] ?? '',
            'tmp_name' => $filesEntry['tmp_name'][$i] ?? '',
            'error' => $error,
            'size' => $filesEntry['size'][$i] ?? 0,
        ];
        if (count($out) >= AF_ATTACHMENT_MAX_FILES_PER_UPLOAD) {
            break;
        }
    }
    return $out;
}

/**
 * Validates and stores one uploaded file ($_FILES-shaped array) for a given
 * project/task, and records it in the attachments table. Throws
 * InvalidArgumentException with a user-presentable message on any validation
 * failure — see api/attachments.php's 'upload' action, which calls this once
 * per file and collects any per-file errors rather than failing the whole
 * batch for one bad file.
 */
function process_attachment_upload(array $file, string $entityType, int $entityId, ?int $uploadedByUserId): array
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new InvalidArgumentException('No file was received.');
    }
    if ($error !== UPLOAD_ERR_OK) {
        $tooBig = in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
        throw new InvalidArgumentException($tooBig ? 'That file is too large to upload.' : 'The upload failed. Please try again.');
    }
    $tmpPath = $file['tmp_name'] ?? '';
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new InvalidArgumentException('The upload failed. Please try again.');
    }
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0) {
        throw new InvalidArgumentException('That file appears to be empty.');
    }
    if ($size > AF_ATTACHMENT_MAX_UPLOAD_BYTES) {
        $maxMb = (int)round(AF_ATTACHMENT_MAX_UPLOAD_BYTES / 1024 / 1024);
        throw new InvalidArgumentException("That file is too large — please use one under {$maxMb}MB.");
    }

    $originalName = trim((string)($file['name'] ?? ''));
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === '' || !isset(AF_ATTACHMENT_ALLOWED_EXTENSIONS[$ext])) {
        $allowed = implode(', ', array_keys(AF_ATTACHMENT_ALLOWED_EXTENSIONS));
        throw new InvalidArgumentException("That file type isn't allowed. Allowed types: $allowed.");
    }

    // Never trust the client-supplied MIME type or the extension alone —
    // fileinfo inspects the file's actual content (same defense pattern as
    // avatars/description images, which use getimagesize() instead since GD
    // only understands image formats).
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
    $detectedMime = $finfo ? finfo_file($finfo, $tmpPath) : false;
    if ($finfo) {
        finfo_close($finfo);
    }
    if ($detectedMime === false || $detectedMime === '') {
        throw new InvalidArgumentException('That file could not be read. Please try a different file.');
    }
    if (!in_array($detectedMime, AF_ATTACHMENT_ALLOWED_EXTENSIONS[$ext], true)) {
        throw new InvalidArgumentException("That file's content doesn't match a ." . $ext . ' file. Please check the file and try again.');
    }

    if (!is_dir(AF_ATTACHMENT_DIR)) {
        mkdir(AF_ATTACHMENT_DIR, 0755, true);
    }
    $storedFilename = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($tmpPath, AF_ATTACHMENT_DIR . '/' . $storedFilename)) {
        throw new InvalidArgumentException('Could not save the file. Please try again.');
    }

    // original_filename is display-only (always escaped via e() when shown,
    // and stripped of CR/LF/quotes before going into the Content-Disposition
    // header on download — see stream_attachment_download()) — it is never
    // used to build a filesystem path.
    $displayName = mb_substr($originalName !== '' ? $originalName : $storedFilename, 0, 255);

    $stmt = db()->prepare(
        'INSERT INTO attachments (entity_type, entity_id, original_filename, stored_filename, mime_type, size_bytes, uploaded_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$entityType, $entityId, $displayName, $storedFilename, $detectedMime, $size, $uploadedByUserId]);
    $id = (int)db()->lastInsertId();

    audit_log($entityType, $entityId, 'attachment_added', null, ['filename' => $displayName]);

    return get_attachment($id);
}

/** Deletes a stored attachment's file if present. Never throws — a missing file on disk shouldn't block removing the DB row. */
function _delete_attachment_file(string $storedFilename): void
{
    if (!preg_match(AF_ATTACHMENT_FILENAME_PATTERN, $storedFilename)) {
        return;
    }
    $path = AF_ATTACHMENT_DIR . '/' . basename($storedFilename);
    if (is_file($path)) {
        @unlink($path);
    }
}

/** Permanently deletes one attachment (file + row). Caller is responsible for the permission check. */
function delete_attachment(int $id): bool
{
    $attachment = get_attachment($id);
    if (!$attachment) {
        return false;
    }
    _delete_attachment_file($attachment['stored_filename']);
    db()->prepare('DELETE FROM attachments WHERE id = ?')->execute([$id]);
    audit_log($attachment['entity_type'], (int)$attachment['entity_id'], 'attachment_removed', ['filename' => $attachment['original_filename']], null);
    return true;
}

/**
 * Deletes every attachment (files + rows) for a given project or task.
 * Called from delete_project() and delete_activity() so a permanently
 * deleted project/task never leaves orphaned files behind — see those
 * functions in includes/models/projects.php / activities.php.
 */
function delete_attachments_for_entity(string $entityType, int $entityId): void
{
    foreach (list_attachments($entityType, $entityId) as $attachment) {
        delete_attachment((int)$attachment['id']);
    }
}

/**
 * Streams a validated attachment's file to the browser as a forced download
 * and exits. Caller (api/attachments.php's 'download' action) must already
 * have loaded the attachment's parent project/task and confirmed the current
 * user can view it — this function itself does no permission check, only
 * filesystem/integrity checks.
 */
function stream_attachment_download(array $attachment): void
{
    $storedFilename = $attachment['stored_filename'];
    if (!preg_match(AF_ATTACHMENT_FILENAME_PATTERN, $storedFilename)) {
        http_response_code(404);
        exit('File not found.');
    }
    $path = AF_ATTACHMENT_DIR . '/' . basename($storedFilename);
    if (!is_file($path)) {
        http_response_code(404);
        exit('File not found.');
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: ' . $attachment['mime_type']);
    header('Content-Length: ' . (string)filesize($path));
    // The original filename came from the uploader, not from us — strip
    // anything that could break out of the quoted header value or inject
    // additional headers, and always force a download (never inline) so the
    // browser never tries to render arbitrary uploaded content in this
    // app's own origin, even if something slipped past the extension/MIME
    // allow-list.
    $safeName = preg_replace('/[\r\n"]/', '_', (string)$attachment['original_filename']);
    header('Content-Disposition: attachment; filename="' . $safeName . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=0, must-revalidate');
    readfile($path);
    exit;
}
