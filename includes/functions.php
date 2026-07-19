<?php
/**
 * functions.php — Shared helper library for Pet House (Core PHP).
 *
 * Security, formatting, CRUD helpers, CSRF, uploads, flash messages, etc.
 * Every function here is used across all role dashboards.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

/* ===============================================================
 *  IDS & STRINGS
 * =============================================================== */

/** Generate a unique 24-char hex id (CUID-like). */
function gen_id(string $prefix = ''): string
{
    return $prefix . bin2hex(random_bytes(12));
}

/** Escape HTML for output (XSS protection). */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** Strip tags + trim a string input. */
function clean(string $value): string
{
    return trim(strip_tags($value));
}

/** Generate a URL-friendly slug. */
function slugify(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    return trim($slug, '-') ?: 'item';
}

/** Format currency in INR. */
function money($amount): string
{
    return '₹' . number_format((float) $amount, 2);
}

/** Format a date string (YYYY-MM-DD) to e.g. "25 Jul 2025". */
function fmt_date(?string $date): string
{
    if (!$date) return '—';
    $ts = strtotime($date);
    return $ts ? date('j M Y', $ts) : htmlspecialchars($date);
}

/** Format a datetime string to "25 Jul 2025, 11:00 AM". */
function fmt_datetime(?string $dt): string
{
    if (!$dt) return '—';
    $ts = strtotime($dt);
    return $ts ? date('j M Y, g:i A', $ts) : htmlspecialchars($dt);
}

/** Relative "time ago" string. */
function time_ago(?string $dt): string
{
    if (!$dt) return '—';
    $ts = strtotime($dt);
    if (!$ts) return htmlspecialchars($dt);
    $diff = time() - $ts;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('j M Y', $ts);
}

/* ===============================================================
 *  CSRF PROTECTION
 * =============================================================== */

/** Get or create the CSRF token for the current session. */
function csrf_token(): string
{
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/** Render a hidden CSRF input for forms. */
function csrf_field(): string
{
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . csrf_token() . '">';
}

/** Verify the CSRF token from POST/GET; aborts on mismatch. */
function csrf_verify(): void
{
    $token = $_POST[CSRF_TOKEN_NAME] ?? $_GET[CSRF_TOKEN_NAME] ?? '';
    if (!hash_equals($_SESSION[CSRF_TOKEN_NAME] ?? '', $token)) {
        http_response_code(419);
        die('<div style="font-family:sans-serif;padding:40px;text-align:center;color:#7a1f1f;">'
            . '<h2>Security check failed (CSRF)</h2>'
            . '<p>Please go back, refresh the page and try again.</p></div>');
    }
}

/** Verify CSRF for AJAX requests (token sent in X-CSRF-Token header). */
function csrf_verify_ajax(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!hash_equals($_SESSION[CSRF_TOKEN_NAME] ?? '', $token)) {
        json_response(['error' => 'CSRF token mismatch'], 419);
    }
}

/* ===============================================================
 *  HTTP / JSON HELPERS
 * =============================================================== */

/** Send a JSON response and exit. */
function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Redirect to a URL and exit. */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Get the current full URL path. */
function current_url(): string
{
    return ($_SERVER['REQUEST_URI'] ?? '/');
}

/* ===============================================================
 *  FLASH MESSAGES (one-time session toasts)
 * =============================================================== */

function flash(string $type, string $message): void
{
    $_SESSION['__flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get_all(): array
{
    $flashes = $_SESSION['__flash'] ?? [];
    unset($_SESSION['__flash']);
    return $flashes;
}

/* ===============================================================
 *  QUERY HELPERS (prepared-statement wrappers)
 * =============================================================== */

/**
 * Run a prepared SELECT and return all rows.
 * @return array<int,array>
 */
function db_select(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Run a prepared SELECT and return one row (or null). */
function db_select_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row !== false ? $row : null;
}

/** Run a prepared SELECT and return a single scalar (or null). */
function db_scalar(string $sql, array $params = [])
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $val = $stmt->fetchColumn();
    return $val !== false ? $val : null;
}

/** Run an INSERT/UPDATE/DELETE; returns the affected row count. */
function db_execute(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/** Insert a row from an associative array; returns the new id (or rowcount). */
function db_insert(string $table, array $data): string
{
    $cols = array_keys($data);
    $placeholders = array_map(fn($c) => ':' . $c, $cols);
    $sql = sprintf(
        'INSERT INTO `%s` (`%s`) VALUES (%s)',
        $table,
        implode('`,`', $cols),
        implode(',', $placeholders)
    );
    db_execute($sql, $data);
    return $data['id'] ?? (string) db()->lastInsertId();
}

/* ===============================================================
 *  PAGINATION HELPER
 * =============================================================== */

/**
 * Paginate a SELECT query.
 * Returns ['rows'=>array,'total'=>int,'page'=>int,'per_page'=>int,'pages'=>int]
 */
function paginate(string $countSql, string $dataSql, array $params, int $perPage = 10): array
{
    $total = (int) db_scalar($countSql, $params);
    $page  = max(1, (int) ($_GET['page'] ?? 1));
    $pages = max(1, (int) ceil($total / $perPage));
    if ($page > $pages) $page = $pages;
    $offset = ($page - 1) * $perPage;

    $dataSql .= ' LIMIT ' . $perPage . ' OFFSET ' . $offset;
    $rows = db_select($dataSql, $params);

    return [
        'rows'     => $rows,
        'total'    => $total,
        'page'     => $page,
        'per_page' => $perPage,
        'pages'    => $pages,
    ];
}

/** Render Bootstrap pagination links. */
function pagination_links(array $pg, string $baseUrl): string
{
    if ($pg['pages'] <= 1) return '';
    $html = '<nav aria-label="Page navigation"><ul class="pagination pagination-sm justify-content-center">';
    $q = $_GET;
    for ($i = 1; $i <= $pg['pages']; $i++) {
        $q['page'] = $i;
        $url = $baseUrl . '?' . http_build_query($q);
        $active = $i === $pg['page'] ? ' active' : '';
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . e($url) . '">' . $i . '</a></li>';
    }
    $html .= '</ul></nav>';
    return $html;
}

/* ===============================================================
 *  FILE UPLOADS
 * =============================================================== */

/**
 * Upload a file from $_FILES.
 * @return array{ok:bool,url?:string,error?:string}
 */
function upload_file(string $field, string $category = 'document'): array
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No file uploaded'];
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed (code ' . $f['error'] . ')'];
    }
    if ($f['size'] > MAX_UPLOAD_SIZE) {
        return ['ok' => false, 'error' => 'File too large (max 5MB)'];
    }

    // ─── Extension whitelist (block executable disguises) ───────────
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $allowed = $category === 'profile_photo' || $category === 'pet_photo' || $category === 'delivery_proof'
        ? ALLOWED_IMAGE_TYPES : ALLOWED_FILE_TYPES;
    if (!in_array($ext, $allowed, true)) {
        return ['ok' => false, 'error' => 'File type not allowed'];
    }

    // ─── MIME verification (defense vs. renamed executables) ────────
    $allowedMimes = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
        'png' => ['image/png'], 'gif' => ['image/gif'],
        'webp' => ['image/webp'], 'pdf' => ['application/pdf'],
    ];
    if (!isset($allowedMimes[$ext])) {
        return ['ok' => false, 'error' => 'File type not allowed'];
    }
    $detectedMime = function_exists('mime_content_type')
        ? mime_content_type($f['tmp_name'])
        : ($f['type'] ?? '');
    if ($detectedMime && !in_array($detectedMime, $allowedMimes[$ext], true)) {
        return ['ok' => false, 'error' => 'File content does not match its extension'];
    }

    if (!is_dir(UPLOAD_DIR)) {
        @mkdir(UPLOAD_DIR, 0775, true);
    }
    $newName = $category . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = UPLOAD_DIR . $newName;
    if (!move_uploaded_file($f['tmp_name'], $dest)) {
        return ['ok' => false, 'error' => 'Could not save file'];
    }
    return ['ok' => true, 'url' => 'assets/uploads/' . $newName];
}

/* ===============================================================
 *  STORE SETTINGS
 * =============================================================== */

function setting(string $key, $default = null)
{
    $val = db_scalar('SELECT `value` FROM store_settings WHERE `key` = ? LIMIT 1', [$key]);
    return $val !== null ? $val : $default;
}

/* ===============================================================
 *  NOTIFICATIONS
 * =============================================================== */

function notify(string $userId, string $title, string $message, string $type = 'general'): void
{
    db_insert('notifications', [
        'id'      => gen_id('n_'),
        'user_id' => $userId,
        'title'   => $title,
        'message' => $message,
        'type'    => $type,
        'is_read' => 0,
    ]);
}

/* ===============================================================
 *  REWARDS
 * =============================================================== */

function reward_earn(string $userId, int $points, string $source, string $desc): void
{
    if ($points <= 0) return;
    db_execute(
        'UPDATE users SET reward_points = reward_points + ? WHERE id = ?',
        [$points, $userId]
    );
    db_insert('reward_transactions', [
        'id'          => gen_id('rt_'),
        'user_id'     => $userId,
        'points'      => $points,
        'type'        => 'earn',
        'source'      => $source,
        'description' => $desc,
    ]);
}

/* ===============================================================
 *  MISC
 * =============================================================== */

/** Decode a JSON string field safely (returns array). */
function json_array(?string $json): array
{
    if (!$json) return [];
    $arr = json_decode($json, true);
    return is_array($arr) ? $arr : [];
}

/** Pretty status badge HTML for any status string. */
function status_badge(string $status): string
{
    $map = [
        'pending'     => 'bg-warning text-dark',
        'confirmed'   => 'bg-info text-dark',
        'in_progress' => 'bg-primary',
        'active'      => 'bg-primary',
        'assigned'    => 'bg-info text-dark',
        'picked_up'   => 'bg-primary',
        'in_transit'  => 'bg-primary',
        'shipped'     => 'bg-primary',
        'completed'   => 'bg-success',
        'delivered'   => 'bg-success',
        'cancelled'   => 'bg-danger',
        'open'        => 'bg-warning text-dark',
        'closed'      => 'bg-success',
        'available'   => 'bg-success',
        'adopted'     => 'bg-info text-dark',
    ];
    $cls = $map[strtolower($status)] ?? 'bg-secondary';
    return '<span class="badge ' . $cls . '">' . e(ucfirst(str_replace('_', ' ', $status))) . '</span>';
}

/** Star rating HTML. */
function star_html($rating): string
{
    $r = round((float) $rating);
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= $r
            ? '<i class="fas fa-star text-warning"></i>'
            : '<i class="far fa-star text-muted"></i>';
    }
    return $html;
}

/** Initials avatar fallback. */
function initials(string $name): string
{
    $parts = explode(' ', trim($name));
    $out = '';
    foreach ($parts as $p) { if ($p !== '') $out .= strtoupper($p[0]); }
    return substr($out, 0, 2) ?: 'PH';
}

/** Get unread notification count for a user. */
function unread_count(string $userId): int
{
    return (int) db_scalar('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]);
}

/** Get cart item count for a user. */
function cart_count(string $userId): int
{
    return (int) db_scalar('SELECT COALESCE(SUM(quantity),0) FROM cart_items WHERE user_id = ?', [$userId]);
}

/** Validate a coupon code; returns [valid, discount_amount, error]. */
function validate_coupon(string $code, float $subtotal): array
{
    $code = strtoupper(trim($code));
    $row = db_select_one(
        'SELECT * FROM coupons WHERE code = ? AND active = 1 LIMIT 1',
        [$code]
    );
    if (!$row) return [false, 0, 'Invalid coupon code'];
    if ($row['expires_at'] && strtotime($row['expires_at']) < time()) {
        return [false, 0, 'Coupon has expired'];
    }
    if ($row['usage_limit'] && $row['usage_count'] >= $row['usage_limit']) {
        return [false, 0, 'Coupon usage limit reached'];
    }
    if ($subtotal < (float) $row['min_order']) {
        return [false, 0, 'Minimum order ' . money($row['min_order']) . ' required'];
    }
    $discount = $row['type'] === 'percentage'
        ? $subtotal * ($row['discount'] / 100)
        : (float) $row['discount'];
    if ($row['max_discount']) $discount = min($discount, (float) $row['max_discount']);
    return [true, round($discount, 2), null];
}
