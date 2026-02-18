#!/usr/bin/env php
<?php
/**
 * Скрипт бэкапа базы данных в ZIP.
 * Запуск из cron: php /path/to/project/scripts/backup_db_zip.php
 *
 * Пример crontab (ежедневно в 3:00):
 * 0 3 * * * php /path/to/r-check.ru/scripts/backup_db_zip.php >> /path/to/r-check.ru/logs/backup_cron.log 2>&1
 */

// Только CLI
if (php_sapi_name() !== 'cli') {
    exit(1);
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/vendor/autoload.php';

use ReproCRM\Config\Config;

Config::load();

$dbType = strtolower($_ENV['DB_TYPE'] ?? 'sqlite');
$backupDir = $projectRoot . '/backups/';
$timestamp = date('Y-m-d_H-i-s');
$zipName = "reprocrm_backup_{$timestamp}.zip";
$zipPath = $backupDir . $zipName;

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$tempDir = sys_get_temp_dir() . '/reprocrm_backup_' . getmypid() . '_' . time();
mkdir($tempDir, 0700, true);

try {
    if ($dbType === 'sqlite') {
        $dbFile = $projectRoot . '/database/reprocrm.db';
        if (!file_exists($dbFile)) {
            throw new RuntimeException("Файл БД не найден: {$dbFile}");
        }
        $tempDb = $tempDir . '/reprocrm.db';
        if (!copy($dbFile, $tempDb)) {
            throw new RuntimeException("Не удалось скопировать БД во временную папку");
        }
        $filesToZip = [['path' => $tempDb, 'name' => 'reprocrm.db']];
    } else {
        // MySQL: дамп через mysqldump
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $dbname = $_ENV['DB_NAME'] ?? 'reprocrm_sales';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? '';
        $dumpFile = $tempDir . '/reprocrm.sql';
        $passArg = $pass !== '' ? ' -p' . escapeshellarg($pass) : '';
        $cmd = sprintf(
            'mysqldump -h %s -u %s%s %s > %s 2>/dev/null',
            escapeshellarg($host),
            escapeshellarg($user),
            $passArg,
            escapeshellarg($dbname),
            escapeshellarg($dumpFile)
        );
        exec($cmd, $out, $code);
        if ($code !== 0 || !file_exists($dumpFile) || filesize($dumpFile) === 0) {
            throw new RuntimeException("Ошибка mysqldump (код {$code}). Проверьте доступ к MySQL.");
        }
        $filesToZip = [['path' => $dumpFile, 'name' => 'reprocrm.sql']];
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Не удалось создать ZIP: {$zipPath}");
    }
    foreach ($filesToZip as $f) {
        $zip->addFile($f['path'], $f['name']);
    }
    $zip->close();

    echo date('Y-m-d H:i:s') . " OK: {$zipName}\n";
} catch (Throwable $e) {
    echo date('Y-m-d H:i:s') . " ERROR: " . $e->getMessage() . "\n";
    if (file_exists($zipPath)) {
        @unlink($zipPath);
    }
    exit(1);
} finally {
    // Удаляем временные файлы
    foreach (glob($tempDir . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($tempDir);
}
