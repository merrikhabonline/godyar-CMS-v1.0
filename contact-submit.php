<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';



// CSRF protection
if (function_exists('csrf_verify_or_die')) { csrf_verify_or_die(); }
if (session_status() !== PHP_SESSION_ACTIVE) {
    gdy_session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contact.php');
    exit;
}

if (!function_exists('sanitize_value')) {
    function sanitize_value($v): string
    {
        return htmlspecialchars(trim((string)$v), ENT_QUOTES, 'UTF-8');
    }
}

$name    = sanitize_value($_POST['name']    ?? '');
$email   = sanitize_value($_POST['email']   ?? '');
$subject = sanitize_value($_POST['subject'] ?? '');
$message = trim((string)($_POST['message']  ?? ''));

if ($name === '' || $email === '' || $message === '') {
    header('Location: contact.php?status=error');
    exit;
}

try {
    $pdo = gdy_pdo_safe();

    if ($pdo instanceof \PDO) {
        $chk = function_exists('gdy_db_table_exists') ? (gdy_db_table_exists($pdo, 'contact_messages') ? 1 : 0) : 0;
        if ($chk && $chk->fetchColumn()) {
            $stmt = $pdo->prepare("
                INSERT INTO contact_messages
                    (name, email, subject, message, status, is_read, created_at)
                VALUES
                    (:name, :email, :subject, :message, 'new', 0, NOW())
            ");
            $stmt->execute([
                ':name'    => $name,
                ':email'   => $email,
                ':subject' => $subject,
                ':message' => $message,
            ]);
        }
    }
} catch (\Throwable $e) {
    error_log('[contact-submit] ' . $e->getMessage());
}

header('Location: contact.php?status=ok');
exit;
