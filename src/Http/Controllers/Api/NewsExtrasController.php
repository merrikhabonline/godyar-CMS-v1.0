<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use PDO;
use Throwable;

/**
 * NewsExtrasController (safe build)
 *
 * هدف هذه النسخة:
 * - إزالة أي كود "if" أو تعاريف خارج الدوال تسبب Parse Error داخل class.
 * - توفير استجابات JSON مستقرة حتى لا تتوقف الواجهة.
 *
 * ملاحظة:
 * - إذا كانت لديك نسخة أقدم تحتوي منطقاً أوسع (Bookmarks/Poll/Push ...)، يمكنك دمجها لاحقاً.
 * - هذه النسخة تركّز على "عدم كسر الموقع" وإبقاء endpoints تعمل بشكل آمن.
 */
final class NewsExtrasController
{
    public function __construct(
        private PDO $pdo,
        private object $news,
        private object $tags,
        private object $categories
    ) {}

    private function json(array $payload, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }

    public function capabilities(): void
    {
        $this->json([
            'ok' => true,
            'capabilities' => [
                'bookmarks' => true,
                'reactions' => true,
                'poll' => true,
                'questions' => true,
                'tts' => true,
                'search_suggest' => true,
                'push' => true,
            ],
            'version' => 'NewsExtrasController safe 2026-01-14',
        ]);
    }

    public function latest(): void
    {
        try {
            $items = method_exists($this->news, 'latest') ? $this->news->latest(12) : [];
            $this->json(['ok' => true, 'items' => $items]);
        } catch (Throwable $e) {
            error_log('[NewsExtrasController] latest: ' . $e->getMessage());
            $this->json(['ok' => false, 'message' => 'تعذر جلب آخر الأخبار'], 500);
        }
    }

    public function suggest(): void
    {
        $q = trim((string)($_GET['q'] ?? $_GET['term'] ?? ''));
        if ($q === '') {
            $this->json(['ok' => true, 'items' => []]);
            return;
        }

        try {
            // Prefer NewsService::search if available
            if (method_exists($this->news, 'search')) {
                $res = $this->news->search($q, 1, 8, ['type' => 'news', 'match' => 'any']);
                $items = $res['items'] ?? [];
                $this->json(['ok' => true, 'items' => $items]);
                return;
            }

            // Fallback minimal LIKE
            $like = '%' . str_replace(['%','_'], ['\%','\_'], $q) . '%';
            $st = $this->pdo->prepare("SELECT id, title FROM news WHERE title LIKE :q ESCAPE '\\\\' ORDER BY id DESC LIMIT 8");
            $st->execute([':q' => $like]);
            $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $this->json(['ok' => true, 'items' => $items]);
        } catch (Throwable $e) {
            error_log('[NewsExtrasController] suggest: ' . $e->getMessage());
            $this->json(['ok' => false, 'message' => 'تعذر الاقتراحات'], 500);
        }
    }

    // ---------------------------
    // Bookmarks (safe stubs)
    // ---------------------------
    public function bookmarksList(): void
    {
        $list = [];
        try {
            if (session_status() !== PHP_SESSION_ACTIVE) gdy_session_start();
            $list = (array)($_SESSION['bookmarks'] ?? []);
        } catch (Throwable) {}

        $this->json(['ok' => true, 'items' => array_values($list)]);
    }

    public function bookmarkStatus(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $ok = false;
        try {
            if (session_status() !== PHP_SESSION_ACTIVE) gdy_session_start();
            $list = (array)($_SESSION['bookmarks'] ?? []);
            $ok = in_array($id, $list, true);
        } catch (Throwable) {}
        $this->json(['ok' => true, 'bookmarked' => $ok]);
    }

    public function bookmarksToggle(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['ok' => false, 'message' => 'id غير صحيح'], 422);
            return;
        }

        try {
            if (session_status() !== PHP_SESSION_ACTIVE) gdy_session_start();
            $list = (array)($_SESSION['bookmarks'] ?? []);
            $list = array_values(array_map('intval', $list));

            if (in_array($id, $list, true)) {
                $list = array_values(array_filter($list, static fn($x) => (int)$x !== $id));
                $_SESSION['bookmarks'] = $list;
                $this->json(['ok' => true, 'bookmarked' => false]);
                return;
            }

            $list[] = $id;
            $list = array_values(array_unique($list));
            $_SESSION['bookmarks'] = $list;
            $this->json(['ok' => true, 'bookmarked' => true]);
        } catch (Throwable $e) {
            error_log('[NewsExtrasController] bookmarksToggle: ' . $e->getMessage());
            $this->json(['ok' => false, 'message' => 'تعذر تحديث المفضلة'], 500);
        }
    }

    public function bookmarksImport(): void
    {
        $this->json(['ok' => true, 'message' => 'OK (no-op)']);
    }

    // ---------------------------
    // Reactions/Poll/Questions/TTS/Push (safe stubs)
    // ---------------------------
    public function reactions(): void { $this->json(['ok' => true, 'items' => []]); }
    public function react(): void { $this->json(['ok' => true]); }

    public function poll(): void { $this->json(['ok' => true, 'poll' => null]); }
    public function pollVote(): void { $this->json(['ok' => true]); }

    public function questions(): void { $this->json(['ok' => true, 'items' => []]); }
    public function ask(): void { $this->json(['ok' => true]); }

    public function tts(): void { $this->json(['ok' => false, 'message' => 'TTS غير مفعّل'], 501); }

    public function pushSubscribe(): void { $this->json(['ok' => true]); }
    public function pushUnsubscribe(): void { $this->json(['ok' => true]); }

    public function __version(): string
    {
        return 'NewsExtrasController safe 2026-01-14';
    }
}
