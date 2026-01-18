<?php
declare(strict_types=1);

/**
 * Comments Endpoint (GET list / POST create) — Logged-in tolerant
 * --------------------------------------------------------------
 * - GET  /api/comments.php?news_id=1
 * - POST /api/comments.php  (body required; guest_name/guest_email required for guests only)
 *
 * Shared-hosting safe: no CLI, no shell.
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 1)); // default: /public_html/api -> /public_html
}

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

header('Content-Type: application/json; charset=utf-8');

// Ensure session (for logged-in users)
if (session_status() !== PHP_SESSION_ACTIVE) {
    gdy_session_start();
}

// Bootstrap PDO (best-effort; do not leak details)
$pdo = null;
try {
    if (is_file(ROOT_PATH . '/includes/bootstrap.php')) {
        require_once ROOT_PATH . '/includes/bootstrap.php';
    }
    if (class_exists('Godyar\\DB') && method_exists('Godyar\\DB', 'pdo')) {
        $pdo = \Godyar\DB::pdo();
    } elseif (function_exists('gdy_pdo_safe')) {
        $pdo = gdy_pdo_safe();
    }
} catch (\Throwable $e) {
    // ignore; handled below
}

if (!$pdo instanceof \PDO) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_unavailable'], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

function _gdy_logged_user(): array {
    $uid = 0;
    $email = '';
    $name = '';

    if (!empty($_SESSION['user_id'])) $uid = (int)$_SESSION['user_id'];
    elseif (!empty($_SESSION['user']['id'])) $uid = (int)$_SESSION['user']['id'];

    if (!empty($_SESSION['user_email'])) $email = (string)$_SESSION['user_email'];
    elseif (!empty($_SESSION['user']['email'])) $email = (string)$_SESSION['user']['email'];

    if (!empty($_SESSION['user_name'])) $name = (string)$_SESSION['user_name'];
    elseif (!empty($_SESSION['user']['display_name'])) $name = (string)$_SESSION['user']['display_name'];
    elseif (!empty($_SESSION['user']['username'])) $name = (string)$_SESSION['user']['username'];

    return ['id' => $uid, 'email' => $email, 'name' => $name];
}

function _gdy_json($code, $arr): void {
    http_response_code($code);
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

// -------- GET: list comments --------
if ($method === 'GET') {
    $newsId = (int)($_GET['news_id'] ?? $_GET['id'] ?? 0);
    if ($newsId <= 0) _gdy_json(400, ['ok' => false, 'error' => 'missing_news_id']);

    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 20);
    if ($perPage <= 0) $perPage = 20;
    if ($perPage > 100) $perPage = 100;
    $offset = ($page - 1) * $perPage;

    // Approved-only by default
    $status = (string)($_GET['status'] ?? 'approved');

    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM `comments` WHERE `news_id` = :nid AND `status` = :st");
        $st->execute([':nid' => $newsId, ':st' => $status]);
        $total = (int)($st->fetchColumn() ?: 0);

        $st = $pdo->prepare("
            SELECT `id`,`news_id`,`user_id`,`guest_name`,`body`,`status`,`parent_id`,`created_at`
            FROM `comments`
            WHERE `news_id` = :nid AND `status` = :st
            ORDER BY `created_at` DESC, `id` DESC
            LIMIT :lim OFFSET :off
        ");
        $st->bindValue(':nid', $newsId, \PDO::PARAM_INT);
        $st->bindValue(':st', $status, \PDO::PARAM_STR);
        $st->bindValue(':lim', $perPage, \PDO::PARAM_INT);
        $st->bindValue(':off', $offset, \PDO::PARAM_INT);
        $st->execute();

        $items = $st->fetchAll(\PDO::FETCH_ASSOC);
        _gdy_json(200, [
            'ok' => true,
            'items' => is_array($items) ? $items : [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / max(1, $perPage)),
        ]);
    } catch (\Throwable $e) {
        error_log('[comments] GET error: ' . $e->getMessage());
        _gdy_json(500, ['ok' => false, 'error' => 'list_failed']);
    }
}

// -------- POST: create comment --------
if ($method === 'POST') {
    $newsId = (int)($_POST['news_id'] ?? $_POST['id'] ?? 0);
    $body   = trim((string)($_POST['body'] ?? $_POST['comment'] ?? ''));
    $parent = (int)($_POST['parent_id'] ?? 0);

    if ($newsId <= 0) _gdy_json(400, ['ok' => false, 'error' => 'missing_news_id']);
    if ($body === '') _gdy_json(422, ['ok' => false, 'error' => 'body_required']);

    $u = _gdy_logged_user();
    $userId = (int)($u['id'] ?? 0);

    // Guest fields from POST (fallback)
    $guestName  = trim((string)($_POST['guest_name'] ?? $_POST['name'] ?? ''));
    $guestEmail = trim((string)($_POST['guest_email'] ?? $_POST['email'] ?? ''));

    // If logged-in: auto-fill guest_name/email from session (or DB as fallback) and DO NOT require POST fields
    if ($userId > 0) {
        if ($guestName === '') $guestName = (string)($u['name'] ?? '');
        if ($guestEmail === '') $guestEmail = (string)($u['email'] ?? '');

        // If still missing, try load from users table
        if (($guestName === '' || $guestEmail === '') ) {
            try {
                $dnCol = db_column_exists($pdo, 'users', 'display_name') ? 'display_name'
                    : (db_column_exists($pdo, 'users', 'name') ? 'name'
                    : (db_column_exists($pdo, 'users', 'full_name') ? 'full_name'
                    : (db_column_exists($pdo, 'users', 'fullName') ? 'fullName' : 'username')));
                $st = $pdo->prepare("SELECT `username`, `{$dnCol}` AS `display_name`, `email` FROM `users` WHERE `id` = :id LIMIT 1");
                $st->execute([':id' => $userId]);
                $row = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
                if ($guestName === '') {
                    $guestName = (string)($row['display_name'] ?? $row['username'] ?? 'User');
                }
                if ($guestEmail === '') {
                    $guestEmail = (string)($row['email'] ?? '');
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if ($guestName === '') $guestName = 'User';
        // guestEmail can be empty for logged-in if your schema allows; but safer to require if column is NOT NULL.
        if ($guestEmail === '') {
            // last resort: use placeholder to satisfy NOT NULL schemas
            $guestEmail = 'member@local.invalid';
        }
    } else {
        // Guest validation: require name+email
        if ($guestName === '' || $guestEmail === '') {
            _gdy_json(422, ['ok' => false, 'error' => 'name_email_required', 'message' => 'الاسم والبريد الإلكتروني مطلوبان.']);
        }
        if (!filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
            _gdy_json(422, ['ok' => false, 'error' => 'invalid_email']);
        }
    }

    $status = (string)($_POST['status'] ?? 'pending');
    if (!in_array($status, ['pending','approved','rejected'], true)) $status = 'pending';

    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);

    try {
        $st = $pdo->prepare("
            INSERT INTO `comments`
                (`news_id`,`user_id`,`guest_name`,`guest_email`,`body`,`status`,`parent_id`,`ip`,`user_agent`,`created_at`,`updated_at`)
            VALUES
                (:news_id,:user_id,:guest_name,:guest_email,:body,:status,:parent_id,:ip,:ua,NOW(),NOW())
        ");
        $st->execute([
            ':news_id'     => $newsId,
            ':user_id'     => ($userId > 0 ? $userId : null),
            ':guest_name'  => $guestName,
            ':guest_email' => $guestEmail,
            ':body'        => $body,
            ':status'      => $status,
            ':parent_id'   => $parent,
            ':ip'          => ($ip !== '' ? $ip : null),
            ':ua'          => ($ua !== '' ? $ua : null),
        ]);

        _gdy_json(200, ['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'status' => $status]);
    } catch (\Throwable $e) {
        error_log('[comments] POST error: ' . $e->getMessage());
        _gdy_json(500, ['ok' => false, 'error' => 'create_failed']);
    }
}

// Unsupported
_gdy_json(405, ['ok' => false, 'error' => 'method_not_allowed']);
