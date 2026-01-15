<?php
namespace Godyar\Services;

use PDO;
use Godyar\DB;

/**
 * SettingsService
 *
 * Step 15:
 * - دعم Constructor Injection (المفضل): new SettingsService(PDO $pdo)
 * - الإبقاء على static methods للتوافق الخلفي.
 */
final class SettingsService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getValue(string $key, $default = null)
    {
        try {
            $st = $this->pdo->prepare("SELECT value FROM settings WHERE setting_key=:k");
            $st->execute([':k' => $key]);
            $val = $st->fetchColumn();
            if ($val === false) {
                return $default;
            }
            $decoded = json_decode((string)$val, true);
            return ($decoded === null && json_last_error() !== JSON_ERROR_NONE) ? $val : $decoded;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public function setValue(string $key, $value): void
    {
        $val = is_array($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
            : (string)$value;

        $now = date('Y-m-d H:i:s');
gdy_db_upsert(
            $this->pdo,
            'settings',
            [
                'setting_key' => $key,
                'value'       => $val,
                'updated_at'  => $now,
            ],
            ['setting_key'],
            ['value','updated_at']
        );
}

    /** @param array<string, mixed> $pairs */
    public function setMany(array $pairs): void
    {
        $this->pdo->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
foreach ($pairs as $k => $v) {
                $val = is_array($v)
                    ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
                    : (string)$v;

                gdy_db_upsert(
                    $this->pdo,
                    'settings',
                    [
                        'setting_key' => $k,
                        'value'       => $val,
                        'updated_at'  => $now,
                    ],
                    ['setting_key'],
                    ['value','updated_at']
                );
            }
$this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ------------------------
    // Backward-compatible static API
    // ------------------------
    public static function get(string $key, $default = null)
    {
        return (new self(DB::pdo()))->getValue($key, $default);
    }

    public static function set(string $key, $value): void
    {
        (new self(DB::pdo()))->setValue($key, $value);
    }

    /** @param array<string, mixed> $pairs */
    public static function many(array $pairs): void
    {
        (new self(DB::pdo()))->setMany($pairs);
    }
}
