<?php
declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;

class Connection
{
    private static ?PDO $instance = null;

    public static function getInstance(?string $customPath = null): PDO
    {
        if ($customPath !== null) {
            return self::createConnection($customPath);
        }

        if (self::$instance === null) {
            $config = require __DIR__ . '/../../config/config.php';
            $dbPath = $config['database']['path'] ?? __DIR__ . '/../../database/bep_an_yen.db';
            self::$instance = self::createConnection($dbPath);
        }

        return self::$instance;
    }

    public static function setInstance(?PDO $pdo): void
    {
        self::$instance = $pdo;
    }

    public static function createConnection(string $path): PDO
    {
        $dir = dirname($path);
        if ($path !== ':memory:' && !is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
        ]);

        $pdo->exec('PRAGMA foreign_keys = ON;');
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec('PRAGMA synchronous = NORMAL;');

        return $pdo;
    }
}
