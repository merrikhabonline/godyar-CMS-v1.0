<?php
/**
 * مهمة النسخ الاحتياطي التلقائي
 * تشغيل: 0 3 * * 0 (كل أحد الساعة 3 صباحاً)
 */

define('CRON_MODE', true);
require_once '../includes/config.php';

class SystemBackup {
    private $backupDir;
    
    public function __construct() {
        $this->backupDir = '../backups/' . date('Y-m') . '/';
        
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    public function createBackup() {
        $timestamp = date('Y-m-d_H-i-s');
        $results = [];
        
        // نسخ قاعدة البيانات
        $results['database'] = $this->backupDatabase($timestamp);
        
        // نسخ الملفات المهمة
        $results['files'] = $this->backupImportantFiles($timestamp);
        
        // تنظيف النسخ القديمة
        $results['cleanup'] = $this->cleanupOldBackups();
        
        return $results;
    }
    
    private function backupDatabase($timestamp) {
        $backupFile = $this->backupDir . "/db-$timestamp.sql.gz";
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $sql = $this->dumpMySql($pdo);
            file_put_contents($backupFile, gzencode($sql, 9));
            return ['success' => true, 'file' => basename($backupFile)];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'DB backup failed: ' . $e->getMessage()];
        }
    }

    /**
     * Basic MySQL dump implemented in PHP to avoid executing shell commands (mysqldump/gzip).
     * This is intended for small/medium databases and admin emergency backups.
     */
    private function dumpMySql(PDO $pdo): string {
        $out = "-- Godyar CMS DB backup\n";
        $out .= "-- Generated at: " . date('c') . "\n\n";
        $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($tables as $table) {
            $table = (string)$table;
            if ($table === '') continue;
            $row = $pdo->query('SHOW CREATE TABLE `' . str_replace('`','``',$table) . '`')->fetch();
            $create = $row['Create Table'] ?? null;
            if (!$create) continue;
            $out .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $out .= $create . ";\n\n";
            $stmt = $pdo->query('SELECT * FROM `' . str_replace('`','``',$table) . '`');
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $cols = array_keys($r);
                $vals = [];
                foreach ($cols as $c) {
                    $v = $r[$c];
                    if ($v === null) {
                        $vals[] = 'NULL';
                    } else {
                        $vals[] = $pdo->quote((string)$v);
                    }
                }
                $colList = '`' . implode('`,`', array_map(fn($c)=>str_replace('`','``',$c), $cols)) . '`';
                $out .= "INSERT INTO `{$table}` ({$colList}) VALUES (" . implode(',', $vals) . ");\n";
            }
            $out .= "\n";
        }
        return $out;
    }
    
    private function backupImportantFiles($timestamp) {
        $backupFile = $this->backupDir . "files_{$timestamp}.zip";
        $filesToBackup = [
            '../includes/',
            '../admin/',
            '../frontend/templates/',
            '../assets/css/',
            '../assets/js/'
        ];
        
        $zip = new ZipArchive();
        if ($zip->open($backupFile, ZipArchive::CREATE) === TRUE) {
            foreach ($filesToBackup as $file) {
                if (is_dir($file)) {
                    $this->addFolderToZip($zip, $file);
                } elseif (is_file($file)) {
                    $zip->addFile($file, basename($file));
                }
            }
            
            $zip->close();
            
            return [
                'success' => true,
                'file' => $backupFile,
                'size' => filesize($backupFile)
            ];
        }
        
        return [
            'success' => false,
            'error' => 'فشل في إنشاء ملف النسخ الاحتياطي'
        ];
    }
    
    private function addFolderToZip($zip, $folder, $parentFolder = '') {
        $files = scandir($folder);
        
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') continue;
            
            $filePath = $folder . '/' . $file;
            $localPath = $parentFolder . $file;
            
            if (is_dir($filePath)) {
                $zip->addEmptyDir($localPath);
                $this->addFolderToZip($zip, $filePath, $localPath . '/');
            } else {
                $zip->addFile($filePath, $localPath);
            }
        }
    }
    
    private function cleanupOldBackups() {
        $deleted = 0;
        $backupDirs = glob('../backups/*', GLOB_ONLYDIR);
        $now = time();
        
        foreach ($backupDirs as $dir) {
            // حذف المجلدات الأقدم من 30 يوم
            if ($now - filemtime($dir) >= 2592000) {
                $this->deleteDirectory($dir);
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        
        rmdir($dir);
    }
}

// تنفيذ النسخ الاحتياطي
$backup = new SystemBackup();
$results = $backup->createBackup();

// تسجيل النتائج
$logMessage = date('Y-m-d H:i:s') . " - النسخ الاحتياطي:\n";
foreach ($results as $type => $result) {
    if (is_array($result) && isset($result['success'])) {
        $status = $result['success'] ? 'نجح' : 'فشل';
        $logMessage .= " - $type: $status\n";
        
        if ($result['success'] && isset($result['file'])) {
            $logMessage .= "   الملف: " . basename($result['file']) . "\n";
            $logMessage .= "   الحجم: " . round($result['size'] / 1024 / 1024, 2) . " MB\n";
        }
    } else {
        $logMessage .= " - $type: $result\n";
    }
}

file_put_contents('../logs/backup.log', $logMessage . "\n", FILE_APPEND);

echo "تم النسخ الاحتياطي بنجاح:\n";
print_r($results);
?>