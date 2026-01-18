<?php
declare(strict_types=1);

/**
 * godyar ajax/comments.php (PATCHED)
 * ---------------------------------
 * Fixes:
 * - Matches DB schema: comments(guest_name, guest_email, body, user_id, parent_id, ...)
 * - Logged-in users are NOT required to provide name/email in POST.
 * - Keeps list response compatible by returning `name`.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    gdy_session_start();
}

header('Content-Type: application/json; charset=utf-8');

function gdy_out(array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );
    exit;
}

$debug = isset($_GET['debug']) && ($_GET['debug'] === '1' || $_GET['debug'] === 'true');

try {
    /** @var PDO $pdo */
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('pdo_missing');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    gdy_out(['ok' => false, 'error' => 'db', 'detail' => $debug ? $e->getMessage() : null], 500);
}

$action = (string)($_GET['action'] ?? $_POST['action'] ?? 'list');

// ---------------------------
// Session user detection (robust)
// ---------------------------
$u = null;
if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
    $u = $_SESSION['user'];
} elseif (isset($_SESSION['member']) && is_array($_SESSION['member'])) {
    $u = $_SESSION['member'];
} elseif (isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user'])) {
    $u = $_SESSION['auth_user'];
}
if (!is_array($u)) $u = [];

$userId = (int)(
    $_SESSION['user_id']
    ?? $_SESSION['member_id']
    ?? $_SESSION['uid']
    ?? ($u['id'] ?? 0)
);

$email = trim((string)(
    $_SESSION['user_email']
    ?? $_SESSION['member_email']
    ?? $_SESSION['email']
    ?? ($u['email'] ?? '')
));

$name = trim((string)(
    $_SESSION['user_name']
    ?? $_SESSION['member_name']
    ?? ($u['display_name'] ?? ($u['username'] ?? ($u['name'] ?? '')))
));

$isLogged = ($userId > 0)
    || ($email !== '')
    || !empty($_SESSION['is_member_logged'])
    || !empty($_SESSION['logged_in'])
    || !empty($_SESSION['is_logged_in'])
    || !empty($u);

// Hydrate from DB if logged but missing fields
if ($isLogged && ($name === '' || $email === '' || $userId === 0)) {
    try {
        if ($userId > 0) {
            $dnCol = db_column_exists($pdo, 'users', 'display_name') ? 'display_name' : (db_column_exists($pdo, 'users', 'name') ? 'name' : (db_column_exists($pdo, 'users', 'full_name') ? 'full_name' : (db_column_exists($pdo, 'users', 'fullName') ? 'fullName' : 'username')));
            $st = $pdo->prepare("SELECT id, username, {$dnCol} AS display_name, email FROM users WHERE id = :id LIMIT 1");
            $st->execute([':id' => $userId]);
        } elseif ($email !== '') {
            $dnCol = db_column_exists($pdo, 'users', 'display_name') ? 'display_name' : (db_column_exists($pdo, 'users', 'name') ? 'name' : (db_column_exists($pdo, 'users', 'full_name') ? 'full_name' : (db_column_exists($pdo, 'users', 'fullName') ? 'fullName' : 'username')));
            $st = $pdo->prepare("SELECT id, username, {$dnCol} AS display_name, email FROM users WHERE email = :em LIMIT 1");
            $st->execute([':em' => $email]);
        } else {
            $st = null;
        }

        if ($st) {
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            if ($userId === 0 && !empty($row['id'])) $userId = (int)$row['id'];
            if ($name === '') $name = trim((string)($row['display_name'] ?? $row['username'] ?? ''));
            if ($email === '') $email = trim((string)($row['email'] ?? ''));
        }
    } catch (Throwable $e) {
        // ignore
    }
}

$me = $isLogged ? ['id' => $userId, 'name' => $name, 'email' => $email] : null;

// Optional CSRF check
function gdy_check_csrf(): void {
    $sess = $_SESSION['csrf_token'] ?? '';
    $post = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($sess !== '' || $post !== '') {
        if (!$sess || !$post || !hash_equals((string)$sess, (string)$post)) {
            gdy_out(['ok' => false, 'error' => 'csrf', 'msg' => 'انتهت الجلسة. حدّث الصفحة ثم حاول مرة أخرى.'], 403);
        }
    }
}

// Diagnostics
if ($action === 'diag') {
    try {
        $cols = [];
        try {
            $cols = gdy_db_stmt_columns($pdo, 'comments')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $cols = [];
        }

        gdy_out([
            'ok' => true,
            'table' => 'comments',
            'columns' => $cols,
            'me' => $me,
            'is_logged' => $isLogged,
        ]);
    } catch (Throwable $e) {
        gdy_out(['ok' => false, 'error' => 'db', 'detail' => $debug ? $e->getMessage() : null, 'me' => $me], 500);
    }
}

// ---------------------------
// LIST
// ---------------------------
if ($action === 'list') {
    $newsId = (int)($_GET['news_id'] ?? 0);
    if ($newsId <= 0) {
        gdy_out(['ok' => true, 'comments' => [], 'me' => $me]);
    }

    try {
        $st = $pdo->prepare(
            "SELECT id, news_id, parent_id, COALESCE(guest_name,'') AS name, body, created_at\n"
            . "FROM comments\n"
            . "WHERE news_id = :nid AND status = 'approved'\n"
            . "ORDER BY id ASC\n"
            . "LIMIT 500"
        );
        $st->execute([':nid' => $newsId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        gdy_out(['ok' => true, 'comments' => $rows, 'me' => $me]);
    } catch (Throwable $e) {
        gdy_out(['ok' => false, 'error' => 'db', 'detail' => $debug ? $e->getMessage() : null], 500);
    }
}

// ---------------------------
// ADD (supports replies)
// ---------------------------
if ($action === 'add') {
    gdy_check_csrf();

    $newsId = (int)($_POST['news_id'] ?? 0);
    $parentId = (int)($_POST['parent_id'] ?? 0);
    $body = trim((string)($_POST['body'] ?? ''));

    if ($newsId <= 0) {
        gdy_out(['ok' => false, 'error' => 'validation', 'msg' => 'معرّف الخبر غير صحيح.'], 400);
    }
    if ($body === '') {
        gdy_out(['ok' => false, 'error' => 'validation', 'msg' => 'نص التعليق مطلوب.'], 400);
    }

    if ($parentId > 0) {
        try {
            $st = $pdo->prepare('SELECT id FROM comments WHERE id = :pid AND news_id = :nid LIMIT 1');
            $st->execute([':pid' => $parentId, ':nid' => $newsId]);
            if (!$st->fetchColumn()) {
                gdy_out(['ok' => false, 'error' => 'validation', 'msg' => 'التعليق الذي تحاول الرد عليه غير موجود.'], 400);
            }
        } catch (Throwable $e) {
            gdy_out(['ok' => false, 'error' => 'db', 'detail' => $debug ? $e->getMessage() : null], 500);
        }
    }

    $guestName = '';
    $guestEmail = '';
    $uid = null;

    if ($isLogged) {
        $uid = ($userId > 0) ? $userId : null;

        // Logged in: derive name/email without requiring POST fields
        $guestName = $name;
        $guestEmail = $email;

        if ($guestName === '') {
            if ($guestEmail !== '' && strpos($guestEmail, '@') !== false) {
                $guestName = explode('@', $guestEmail)[0];
            } else {
                $guestName = 'Member';
            }
        }
        if ($guestEmail === '') {
            // To satisfy NOT NULL schemas, use a safe placeholder
            $guestEmail = 'member@local.invalid';
        }
    } else {
        // Guest requires name/email
        $guestName = trim((string)($_POST['guest_name'] ?? $_POST['name'] ?? ''));
        $guestEmail = trim((string)($_POST['guest_email'] ?? $_POST['email'] ?? ''));

        if ($guestName === '' || $guestEmail === '') {
            gdy_out(['ok' => false, 'error' => 'name_email_required', 'msg' => 'الاسم والبريد الإلكتروني مطلوبان.'], 400);
        }
        if (!filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
            gdy_out(['ok' => false, 'error' => 'email_invalid', 'msg' => 'البريد الإلكتروني غير صالح.'], 400);
        }
    }

    $status = $isLogged ? 'approved' : 'pending';
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    try {
        $st = $pdo->prepare(
            "INSERT INTO comments (news_id, user_id, guest_name, guest_email, body, status, parent_id, ip, user_agent, created_at, updated_at)\n"
            . "VALUES (:nid, :uid, :gname, :gemail, :body, :status, :pid, :ip, :ua, NOW(), NOW())"
        );
        $st->execute([
            ':nid' => $newsId,
            ':uid' => $uid,
            ':gname' => $guestName,
            ':gemail' => $guestEmail,
            ':body' => $body,
            ':status' => $status,
            ':pid' => $parentId,
            ':ip' => $ip,
            ':ua' => $ua,
        ]);

        gdy_out([
            'ok' => true,
            'id' => (int)$pdo->lastInsertId(),
            'status' => $status,
        ]);
    } catch (Throwable $e) {
        gdy_out(['ok' => false, 'error' => 'db', 'detail' => $debug ? $e->getMessage() : null], 500);
    }
}

gdy_out(['ok' => false, 'error' => 'bad_action'], 400);
