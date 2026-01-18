<?php
declare(strict_types=1);

/**
 * Godyar CMS - Comments API Endpoint (shared-hosting safe)
 * -------------------------------------------------------
 * Purpose:
 * - GET  : return approved comments for a given news_id (no name/email validation)
 * - POST : create a new comment (name/email required for guests)
 *
 * Notes:
 * - Designed to prevent the bug where GET requests were treated as POST validation.
 * - Returns JSON only and never outputs anything before headers.
 */

// Always JSON
header('Content-Type: application/json; charset=utf-8');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Basic preflight support (in case the frontend uses fetch from a different subpath)
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Start session (for logged-in user detection)
if (session_status() !== PHP_SESSION_ACTIVE) {
    gdy_session_start();
}

// Load bootstrap / DB access (best effort)
$ROOT_PATH = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);

// Try common bootstrap locations without fataling
$bootstrapCandidates = [
    $ROOT_PATH . '/includes/bootstrap.php',
    dirname(__DIR__, 1) . '/../includes/bootstrap.php',
    dirname(__DIR__, 2) . '/includes/bootstrap.php',
];
foreach ($bootstrapCandidates as $bf) {
    if (is_file($bf)) {
        require_once $bf;
        break;
    }
}

// Resolve PDO
$pdo = null;
try {
    if (class_exists('Godyar\\DB') && method_exists('Godyar\\DB', 'pdo')) {
        $pdo = \Godyar\DB::pdo();
    } elseif (function_exists('gdy_pdo_safe')) {
        $pdo = gdy_pdo_safe();
    } elseif (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof \PDO) {
        $pdo = $GLOBALS['pdo'];
    }
} catch (Throwable $e) {
    // ignore
}

if (!$pdo instanceof \PDO) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_unavailable']);
    exit;
}

// Helpers
function gdy_json_fail(int $code, string $error, array $extra = []): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $error] + $extra);
    exit;
}

function gdy_int($v, int $default = 0): int {
    if (is_int($v)) return $v;
    if (is_numeric($v)) return (int)$v;
    return $default;
}

try {
    if ($method === 'GET') {
        $newsId = gdy_int($_GET['news_id'] ?? $_GET['id'] ?? 0);
        if ($newsId <= 0) {
            gdy_json_fail(400, 'missing_news_id');
        }

        $page = max(1, gdy_int($_GET['page'] ?? 1, 1));
        $per  = gdy_int($_GET['per_page'] ?? 50, 50);
        if ($per <= 0) $per = 50;
        if ($per > 200) $per = 200;
        $offset = ($page - 1) * $per;

        // Fetch approved comments only (front-safe)
        $st = $pdo->prepare(
            "SELECT id, news_id, user_id, name, body, parent_id, status, score, created_at\n".
            "FROM news_comments\n".
            "WHERE news_id = :nid AND status = 'approved'\n".
            "ORDER BY created_at ASC\n".
            "LIMIT {$per} OFFSET {$offset}"
        );
        $st->execute([':nid' => $newsId]);
        $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Total
        $st2 = $pdo->prepare("SELECT COUNT(*) FROM news_comments WHERE news_id = :nid AND status = 'approved'");
        $st2->execute([':nid' => $newsId]);
        $total = (int)($st2->fetchColumn() ?: 0);

        echo json_encode([
            'ok' => true,
            'news_id' => $newsId,
            'page' => $page,
            'per_page' => $per,
            'total' => $total,
            'items' => $items,
        ]);
        exit;
    }

    if ($method === 'POST') {
        // Accept both application/x-www-form-urlencoded and JSON
        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        $payload = [];

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $payload = json_decode($raw ?: '[]', true);
            if (!is_array($payload)) $payload = [];
        } else {
            $payload = $_POST;
        }

        $newsId = gdy_int($payload['news_id'] ?? $payload['id'] ?? 0);
        $body   = trim((string)($payload['body'] ?? $payload['comment'] ?? ''));
        $parent = gdy_int($payload['parent_id'] ?? 0);

        // Logged-in user detection (best effort)
        $userId   = gdy_int($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
        $userRole = (string)($_SESSION['user_role'] ?? ($_SESSION['user']['role'] ?? ''));
        $name     = trim((string)($payload['name'] ?? ($_SESSION['user_name'] ?? '')));
        $email    = trim((string)($payload['email'] ?? ($_SESSION['user_email'] ?? ($_SESSION['user']['email'] ?? ''))));

        if ($newsId <= 0) {
            gdy_json_fail(400, 'missing_news_id');
        }
        if ($body === '') {
            gdy_json_fail(422, 'missing_body');
        }

        // Guests must provide name + email
        if ($userId <= 0) {
            if ($name === '' || $email === '') {
                gdy_json_fail(422, 'name_email_required');
            }
        } else {
            // For members: ensure name is set
            if ($name === '') $name = 'User';
        }

        // Basic email sanity (only if provided)
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            gdy_json_fail(422, 'invalid_email');
        }

        // Decide status: admins auto-approved, others pending (default)
        $status = (strtolower($userRole) === 'admin') ? 'approved' : 'pending';

        $st = $pdo->prepare(
            "INSERT INTO news_comments (news_id, user_id, name, email, body, parent_id, status, ip, user_agent, created_at)\n".
            "VALUES (:news_id, :user_id, :name, :email, :body, :parent_id, :status, :ip, :ua, NOW())"
        );

        $st->execute([
            ':news_id' => $newsId,
            ':user_id' => ($userId > 0 ? $userId : null),
            ':name' => ($name !== '' ? $name : null),
            ':email' => ($email !== '' ? $email : null),
            ':body' => $body,
            ':parent_id' => $parent,
            ':status' => $status,
            ':ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            ':ua' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
        ]);

        $newId = (int)$pdo->lastInsertId();

        echo json_encode([
            'ok' => true,
            'id' => $newId,
            'status' => $status,
            'message' => ($status === 'approved') ? 'تم نشر التعليق.' : 'تم إرسال التعليق للمراجعة.',
        ]);
        exit;
    }

    gdy_json_fail(405, 'method_not_allowed', ['allowed' => ['GET', 'POST']]);
} catch (Throwable $e) {
    error_log('[comments endpoint] ' . $e->getMessage());
    gdy_json_fail(500, 'server_error');
}
