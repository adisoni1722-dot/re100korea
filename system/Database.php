<?php
declare(strict_types=1);

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * 데이터베이스 연결 팩토리 클래스
 * 드라이버(Mysqli, Pdo)를 동적으로 로드합니다.
 */

class Database
{

    public static array $queries        = [];
    public static array $debug_messages = [];

    public static function connect(array $dbconf): object
    {
        [$driver_class, $db_config] = self::_prepare_config($dbconf);
        self::_load_driver_file($driver_class);
        return new $driver_class($db_config);
    }

    private static function _prepare_config(array $dbconf): array
    {


        $driver    = $dbconf['driver'] ?? 'mysqli';
        $db_config = $dbconf['config'] ?? $dbconf;

        if ($driver === 'mysqli') {
            $driver_class = 'DB_Mysqli';
        } else {
            $driver_class = 'DB_Pdo';
            // PDO DSN이 없으면 자동으로 생성
            if (! isset($db_config['dsn'])) {
                $hostname         = $db_config['hostname'] ?? 'localhost';
                $database         = $db_config['database'] ?? '';
                $charset          = $db_config['charset'] ?? 'utf8mb4';
                $port     = isset($db_config['port']) ? (int) $db_config['port'] : 3306;
                $db_config['dsn'] = "mysql:host={$hostname};port={$port};dbname={$database};charset={$charset}";
            }
        }

        return [$driver_class, $db_config];
    }

    /**
     * 드라이버 클래스 파일을 로드합니다.
     */
    private static function _load_driver_file(string $driver_class): void
    {
        if (class_exists($driver_class, false)) {
            return;
        }

        $driver_file = SYSPATH . 'drivers/' . $driver_class . '.php';

        if (! file_exists($driver_file)) {

            throw new \RuntimeException("Database driver file not found: {$driver_file}");
        }

        require_once $driver_file;

        if (! class_exists($driver_class, false)) {
            throw new \RuntimeException("Database driver class '{$driver_class}' not found in file: {$driver_file}");
        }
    }

    /**
     * SQL 식별자(테이블명, 필드명)를 안전하게 보호합니다.
     *
     * Prepared Statement 기반 쿼리 실행 임을 먼저 염두에 두어야 합니다.
     */
    public static function protect_identifier(string $item): string
    {
        $item = trim($item);

        // 함수 호출 감지 (예: COUNT(*), IFNULL(a,b))
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*\s*\(.*\)$/i', $item)) {
            return $item;
        }

        // 이미 백틱이 있거나 '*'이면 그대로
        if (strpos($item, '`') !== false || $item === '*') {
            return $item;
        }

        // 예약어 + 식별자 (예: DISTINCT name)
        $reserved = ['DISTINCT', 'ALL', 'TOP'];
        if (preg_match('/^(' . implode('|', $reserved) . ')\s+(.*)$/i', $item, $matches)) {
            return strtoupper($matches[1]) . ' ' . self::protect_identifier($matches[2]);
        }

        // 'field AS alias'
        if (preg_match('/^(.*?)\s+AS\s+(.*)$/i', $item, $matches)) {
            return self::protect_identifier($matches[1]) . ' AS ' . self::protect_identifier($matches[2]);
        }

        // 'field alias' (AS 생략)
        if (preg_match('/^(.*?)\s+([a-zA-Z_][a-zA-Z0-9_]*)$/', $item, $matches)) {
            return self::protect_identifier($matches[1]) . ' AS ' . self::protect_identifier($matches[2]);
        }

        // table.field
        if (strpos($item, '.') !== false) {
            $parts = explode('.', $item);
            return implode('.', array_map(fn($p) => self::protect_identifier($p), $parts));
        }

        if (preg_match('/[()\']/', $item)) {
            $msg = "⚠️ protect_identifier(): 잘못된 SQL 식별자 감지됨 → {$item}";
            if (function_exists('log_message')) {
                log_message('error', $msg);
            }
        }

        // 그 외는 백틱으로 감싸기
        return '`' . $item . '`';
    }
}
