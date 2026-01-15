<?php
namespace Godyar;



require_once dirname(__DIR__) . '/env.php';

class DB {
    private static $instance = null;
    private $connection;
    
    private function __construct()
    {
        $host = DB_HOST;
        $dbname = DB_NAME;
        $username = DB_USER;
        $password = DB_PASS;

        $drv = function_exists('gdy_db_driver') ? gdy_db_driver() : (defined('DB_DRIVER') ? strtolower((string)DB_DRIVER) : 'mysql');
        $dsn = (defined('DB_DSN') && is_string(DB_DSN) && DB_DSN !== '') ? DB_DSN : '';

        if ($dsn === '') {
            if ($drv === 'pgsql') {
                $port = defined('DB_PORT') && DB_PORT !== '' ? DB_PORT : '5432';
                $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $dbname);
            } else {
                $port = defined('DB_PORT') && DB_PORT !== '' ? DB_PORT : '3306';
                $charset = defined('DB_CHARSET') && DB_CHARSET !== '' ? DB_CHARSET : 'utf8mb4';
                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $dbname, $charset);
            }
        }

        try {
            $this->connection = new \PDO(
                $dsn,
                $username,
                $password,
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            if ($drv === 'pgsql') {
                // Ensure UTF-8 for PostgreSQL.
                $this->connection->exec("SET client_encoding TO 'UTF8'");
            }
        } catch (\PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
    
    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
/**
 * Return a PDO connection (compat helper for older code that expects DB::pdo()).
 */
public static function pdo(): \PDO
{
    return self::getInstance()->getConnection();
}
}