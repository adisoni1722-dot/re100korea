<?php

declare(strict_types=1);

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Input Service
 * -------------------------------------------------------------
 * - 요청 데이터(GET, POST, COOKIE, SERVER, HEADER) 처리
 * - XSS 방어는 뷰 출력 시(Common helper) 처리하는 것이 원칙
 * - CI3 호환 메서드 포함 (post_get, get_post, is_ajax_request)
 * -------------------------------------------------------------
 */
class Input
{
    protected Security $security;

    /** @var array|null 헤더 캐시 */
    protected ?array $cachedHeaders = null;

    /** @var string|null 원본 요청 본문 캐시 (php://input) */
    protected ?string $rawInputStream = null;

    // 필터 별칭 매핑
    protected array $filterMap = [
        'str'    => FILTER_SANITIZE_SPECIAL_CHARS, // HTML 엔티티 변환
        'int'    => FILTER_VALIDATE_INT,           // 정수
        'float'  => FILTER_VALIDATE_FLOAT,         // 실수
        'bool'   => FILTER_VALIDATE_BOOLEAN,       // Boolean
        'email'  => FILTER_SANITIZE_EMAIL,         // 이메일 정제
        'url'    => FILTER_SANITIZE_URL,           // URL 정제
        'ip'     => FILTER_VALIDATE_IP,            // IP 검증
        'raw'    => null                           // 필터 없음
    ];

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    // =========================================================================
    // Core Fetch Logic
    // =========================================================================

    protected function _resolveFilter($filter)
    {
        return is_string($filter) ? ($this->filterMap[$filter] ?? null) : $filter;
    }

    protected function _fetch(array $source, ?string $key, $default, $filter, int $flags)
    {
        $filterId = $this->_resolveFilter($filter);

        // 1. 전체 배열 반환
        if ($key === null) {
            if ($filterId !== null && $filterId !== 0 && $filterId !== false) {
                // 배열 전체에 필터 적용
                return filter_var_array($source, $filterId);
            }
            return $source;
        }

        // 2. 값 가져오기 (없으면 기본값)
        $value = $source[$key] ?? $default;

        // 3. 필터 적용
        if ($filterId !== null && $filterId !== 0 && $filterId !== false) {
            return filter_var($value, $filterId, $flags);
        }

        return $value;
    }

    // =========================================================================
    // Public Accessors
    // =========================================================================

    public function post($key = null, $default = null, $filter = null, int $flags = 0)
    {
        return $this->_fetch($_POST, $key, $default, $filter, $flags);
    }

    public function get($key = null, $default = null, $filter = null, int $flags = 0)
    {
        return $this->_fetch($_GET, $key, $default, $filter, $flags);
    }

    public function cookie($key = null, $default = null, $filter = null, int $flags = 0)
    {
        return $this->_fetch($_COOKIE, $key, $default, $filter, $flags);
    }

    public function server($key = null, $default = null, $filter = null, int $flags = 0)
    {
        return $this->_fetch($_SERVER, $key, $default, $filter, $flags);
    }

    /**
     * $_POST + $_GET 병합 데이터
     */
    public function var($key = null, $default = null, $filter = null, int $flags = 0)
    {
        // POST 우선, 없으면 GET (순서 중요)
        $source = array_merge($_GET, $_POST);
        return $this->_fetch($source, $key, $default, $filter, $flags);
    }

    /**
     * JSON Body 파싱
     */
    public function json(bool $assoc = true)
    {
        $raw = $this->raw_input_stream(); // 캐시된 스트림 사용
        $json = json_decode($raw, $assoc);
        return (json_last_error() === JSON_ERROR_NONE) ? $json : null;
    }

    /**
     * Raw Body (php://input) - 캐싱 기능 포함
     */
    public function raw_input_stream(): string
    {
        if ($this->rawInputStream === null) {
            $this->rawInputStream = file_get_contents('php://input') ?: '';
        }
        return $this->rawInputStream;
    }

    // =========================================================================
    // Utility & Compatibility
    // =========================================================================

    public function ip_address(): string
    {
        return $this->security->ip_address();
    }

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function is_ajax(): bool
    {
        // 1. X-Requested-With (jQuery 등 전통적 방식)
        if (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) {
            return true;
        }

        // 2. Accept Header (JSON을 원하는지 확인)
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (strpos($accept, 'application/json') !== false) {
            return true;
        }

        return false;
    }

    /** [CI3 Compat] Alias for is_ajax() */
    public function is_ajax_request(): bool
    {
        return $this->is_ajax();
    }

    /** [CI3 Compat] POST first, then GET */
    public function post_get($key, $default = null, $filter = null, int $flags = 0)
    {
        $val = $this->post($key, null, $filter, $flags);
        return $val ?? $this->get($key, $default, $filter, $flags);
    }

    /** [CI3 Compat] GET first, then POST */
    public function get_post($key, $default = null, $filter = null, int $flags = 0)
    {
        $val = $this->get($key, null, $filter, $flags);
        return $val ?? $this->post($key, $default, $filter, $flags);
    }

    // =========================================================================
    // Header Handling
    // =========================================================================

    public function header($key = null, $default = null)
    {
        $headers = $this->_collectHeaders();
        if ($key === null) return $headers;
        return $this->_readHeader($headers, (string)$key) ?? $default;
    }

    protected function _collectHeaders(): array
    {
        if ($this->cachedHeaders !== null) return $this->cachedHeaders;

        if (function_exists('getallheaders')) {
            $h = getallheaders();
            return $this->cachedHeaders = (is_array($h) ? $h : []);
        }

        // Nginx / FPM Fallback
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (strpos($k, 'HTTP_') === 0) {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
                $headers[$key] = $v;
            } elseif (in_array($k, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $k))));
                $headers[$key] = $v;
            }
        }
        return $this->cachedHeaders = $headers;
    }

    protected function _readHeader(array $headers, string $key): ?string
    {
        $key = strtolower($key);
        foreach ($headers as $k => $v) {
            if (strtolower($k) === $key) return $v;
        }
        return null;
    }
}
