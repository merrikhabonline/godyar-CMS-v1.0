<?php
declare(strict_types=1);

/**
 * Content translation layer (Option 2)
 * - Translates Arabic news content to EN/FR using OpenAI (optional).
 * - Stores results in `news_translations`.
 * - Falls back to Arabic if translation missing.
 */

if (!function_exists('gdy_translation_enabled')) {
    function gdy_translation_enabled(): bool
    {
        // ✅ تم تعطيل الترجمة نهائياً
        return false;
    }
}

if (!function_exists('gdy_translation_auto_on_view')) {
    function gdy_translation_auto_on_view(): bool
    {
        // ✅ تم تعطيل الترجمة نهائياً
        return false;
    }
}

if (!function_exists('gdy_has_table')) {
    function gdy_has_table(PDO $pdo, string $table): bool
    {
        try {
            $st = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1");
            $st->execute([':t' => $table]);
            return (bool)$st->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('gdy_ensure_news_translations_table')) {
    function gdy_ensure_news_translations_table(PDO $pdo): void
    {
        if (gdy_has_table($pdo, 'news_translations')) return;

        // Create minimal translation table
                if (gdy_pdo_is_pgsql($pdo)) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS news_translations (
                    id BIGSERIAL PRIMARY KEY,
                    news_id BIGINT NOT NULL,
                    lang VARCHAR(10) NOT NULL,
                    title TEXT NOT NULL,
                    excerpt TEXT NULL,
                    content TEXT NULL,
                    seo_title TEXT NULL,
                    seo_description TEXT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE (news_id, lang)
                )
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS news_translations (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    news_id INT UNSIGNED NOT NULL,
                    lang VARCHAR(10) NOT NULL,
                    title TEXT NOT NULL,
                    excerpt TEXT,
                    content LONGTEXT,
                    seo_title VARCHAR(255),
                    seo_description VARCHAR(255),
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_news_lang (news_id, lang)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        }

    }
}

if (!function_exists('gdy_translate_openai')) {
    function gdy_translate_openai(string $targetLang, string $title, string $excerpt, string $contentHtml): ?array
    {
        $apiKey = getenv('OPENAI_API_KEY') ?: '';
        if ($apiKey === '') return null;
        if (!function_exists('curl_init')) return null;

        $model = getenv('OPENAI_MODEL_TRANSLATE') ?: (getenv('OPENAI_MODEL') ?: 'gpt-4.1-mini');
        $targetLang = strtolower(trim($targetLang));
        if (!in_array($targetLang, ['en','fr'], true)) return null;

        $langName = $targetLang === 'en' ? 'English' : 'French';

        $prompt = "Translate the following Arabic news content into {$langName}.\n"
            . "Rules:\n"
            . "- Keep meaning, do NOT add new facts.\n"
            . "- Preserve any HTML tags in the content (keep structure), but translate visible text.\n"
            . "- Output STRICT JSON with keys: title, excerpt, content. No markdown.\n\n"
            . "TITLE:\n{$title}\n\nEXCERPT:\n{$excerpt}\n\nCONTENT_HTML:\n{$contentHtml}\n";

        $payload = json_encode([
            'model' => $model,
            'temperature' => 0.2,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a professional news editor and translator.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 2000,
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT => (int)(getenv('OPENAI_TIMEOUT') ?: 25),
            CURLOPT_POSTFIELDS => $payload,
        ]);

        $res = curl_exec($ch);
        if ($res === false) {
            curl_close($ch);
            return null;
        }
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code < 200 || $code >= 300) return null;

        $data = json_decode($res, true);
        if (!is_array($data)) return null;
        $text = $data['choices'][0]['message']['content'] ?? '';
        if (!is_string($text) || trim($text) === '') return null;

        // Try parse JSON (may include leading text)
        $text = trim($text);
        $jsonStart = strpos($text, '{');
        $jsonEnd = strrpos($text, '}');
        if ($jsonStart === false || $jsonEnd === false || $jsonEnd <= $jsonStart) return null;
        $json = substr($text, $jsonStart, $jsonEnd - $jsonStart + 1);

        $out = json_decode($json, true);
        if (!is_array($out)) return null;

        $t = trim((string)($out['title'] ?? ''));
        $e = trim((string)($out['excerpt'] ?? ''));
        $c = (string)($out['content'] ?? '');

        if ($t === '' && $e === '' && trim(strip_tags($c)) === '') return null;

        return [
            'title' => $t,
            'excerpt' => $e,
            'content' => $c,
        ];
    }
}

if (!function_exists('gdy_get_news_translation')) {
    function gdy_get_news_translation(PDO $pdo, int $newsId, string $lang): ?array
    {
        $lang = strtolower(trim($lang));
        if ($lang === '' || $lang === 'ar') return null;
        if (!in_array($lang, ['en','fr'], true)) return null;
        if (!gdy_translation_enabled()) return null;

        static $cache = [];
        $key = $newsId . ':' . $lang;
        if (array_key_exists($key, $cache)) return $cache[$key];

        try {
            gdy_ensure_news_translations_table($pdo);
            $st = $pdo->prepare('SELECT title, excerpt, content, seo_title, seo_description FROM news_translations WHERE news_id = :nid AND lang = :lang LIMIT 1');
            $st->execute([':nid' => $newsId, ':lang' => $lang]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            $cache[$key] = $row;
            return $row;
        } catch (Throwable $e) {
            $cache[$key] = null;
            return null;
        }
    }
}

if (!function_exists('gdy_save_news_translation')) {
    function gdy_save_news_translation(PDO $pdo, int $newsId, string $lang, array $data): bool
    {
        $lang = strtolower(trim($lang));
        if ($lang === '' || $lang === 'ar') return false;
        if (!in_array($lang, ['en','fr'], true)) return false;

        $title = trim((string)($data['title'] ?? ''));
        $excerpt = trim((string)($data['excerpt'] ?? ''));
        $content = (string)($data['content'] ?? '');

        $seoTitle = trim((string)($data['seo_title'] ?? ''));
        $seoDesc  = trim((string)($data['seo_description'] ?? ''));

        try {
            gdy_ensure_news_translations_table($pdo);
                        gdy_db_upsert(
                $pdo,
                'news_translations',
                [
                    'news_id'          => $newsId,
                    'lang'             => $lang,
                    'title'            => $title,
                    'excerpt'          => $excerpt,
                    'content'          => $content,
                    'seo_title'        => $seoTitle,
                    'seo_description'  => $seoDescription,
                    'updated_at'       => date('Y-m-d H:i:s'),
                ],
                ['news_id','lang'],
                ['title','excerpt','content','seo_title','seo_description','updated_at']
            );

        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('gdy_translate_and_store_news')) {
    function gdy_translate_and_store_news(PDO $pdo, int $newsId, string $lang): bool
    {
        if (!gdy_translation_enabled()) return false;
        if (!in_array($lang, ['en','fr'], true)) return false;

        // Load Arabic base
        $st = $pdo->prepare('SELECT title, excerpt, content, seo_title, seo_description FROM news WHERE id = :id LIMIT 1');
        $st->execute([':id' => $newsId]);
        $base = $st->fetch(PDO::FETCH_ASSOC);
        if (!$base) return false;

        $t = (string)($base['title'] ?? '');
        $e = (string)($base['excerpt'] ?? '');
        $c = (string)($base['content'] ?? '');

        $tr = gdy_translate_openai($lang, $t, $e, $c);
        if (!$tr) return false;

        // SEO fallback
        $tr['seo_title'] = $tr['seo_title'] ?? ($base['seo_title'] ?? null);
        $tr['seo_description'] = $tr['seo_description'] ?? ($base['seo_description'] ?? null);

        return gdy_save_news_translation($pdo, $newsId, $lang, $tr);
    }
}

if (!function_exists('gdy_news_field')) {
    /**
     * Get localized news field (title|excerpt|content|seo_title|seo_description)
     */
    function gdy_news_field(PDO $pdo, array $newsRow, string $field): string
    {
        $field = (string)$field;
        $lang = function_exists('gdy_lang') ? gdy_lang() : 'ar';
        if ($lang === 'ar') return (string)($newsRow[$field] ?? '');

        $nid = (int)($newsRow['id'] ?? 0);
        if ($nid <= 0) return (string)($newsRow[$field] ?? '');

        $tr = gdy_get_news_translation($pdo, $nid, $lang);
        $val = '';
        if (is_array($tr)) {
            $val = (string)($tr[$field] ?? '');
        }

        if ($val === '' && gdy_translation_auto_on_view()) {
            // Best-effort (no exception)
            try {
                if (gdy_translate_and_store_news($pdo, $nid, $lang)) {
                    $tr2 = gdy_get_news_translation($pdo, $nid, $lang);
                    $val = is_array($tr2) ? (string)($tr2[$field] ?? '') : '';
                }
            } catch (Throwable $e) {
                $val = '';
            }
        }

        return $val !== '' ? $val : (string)($newsRow[$field] ?? '');
    }
}
