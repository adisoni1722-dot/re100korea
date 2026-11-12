<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * -------------------------------------------------------------
 * Uri
 * -------------------------------------------------------------
 */
class Uri
{
    protected array $segments = [];
    protected array $rsegments = [];
    protected array $queryParams = [];

    protected ?Security $security = null;
    protected $input = null;

    public function __construct(?Router $router = null, ?Input $input = null, ?Security $security = null)
    {

        $this->input    = $input;
        $this->security = $security;

        if ($router) {
            // Router에서 필요한 데이터만 추출
            $this->segments  = $router->segments ?? [];
            $this->rsegments = $router->rsegments ?? [];
        }

        $this->queryParams = $_GET ?? [];
    }

    /* -------------------------------------------------------------
     * CI3 호환 메서드
     * ------------------------------------------------------------- */
    public function segment_array(): array
    {
        return $this->segments;
    }

    public function rsegment_array(): array
    {
        return $this->rsegments;
    }

    public function segment(int $n, ?string $default = null): ?string
    {
        return $this->segments[$n - 1] ?? $default;
    }

    public function rsegment(int $n, ?string $default = null): ?string
    {
        return $this->rsegments[$n - 1] ?? $default;
    }

    public function total_segments(): int
    {
        return count($this->segments);
    }

    public function total_rsegments(): int
    {
        return count($this->rsegments);
    }

    public function uri_string(): string
    {
        return implode('/', $this->segments);
    }

    public function ruri_string(): string
    {
        return implode('/', $this->rsegments);
    }

    public function slash_segment($n, string $where = 'trailing'): ?string
    {
        $segment = ($n === 'full')
            ? $this->uri_string()
            : ($this->segments[$n - 1] ?? null);

        return $segment === null ? null : $this->addSlashes($segment, $where);
    }

    public function slash_rsegment($n, string $where = 'trailing'): ?string
    {
        $segment = ($n === 'full')
            ? $this->ruri_string()
            : ($this->rsegments[$n - 1] ?? null);

        return $segment === null ? null : $this->addSlashes($segment, $where);
    }

    protected function addSlashes(string $segment, string $where): string
    {
        switch ($where) {
            case 'leading':
                return '/' . $segment;
            case 'both':
                return '/' . $segment . '/';
            default:
                return $segment . '/';
        }
    }

    /* -------------------------------------------------------------
     * 확장 기능 (GET, Query)
     * ------------------------------------------------------------- */
    public function query(string $key, $default = null)
    {
        if ($this->input && method_exists($this->input, 'get')) {
            return $this->input->get($key, true) ?? $default;
        }
        $val = $this->queryParams[$key] ?? $default;
        return $this->sanitize($val);
    }

    public function query_array(): array
    {
        $result = [];
        foreach ($this->queryParams as $k => $v) {
            $result[$k] = $this->sanitize($v);
        }
        return $result;
    }

    protected function sanitize($val)
    {
        if (!is_string($val)) {
            return $val;
        }
        $val = trim($val);

        return htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
    }

    /* -------------------------------------------------------------
     * URL 생성
     * ------------------------------------------------------------- */
    public function is_https(): bool
    {
        return (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            ($_SERVER['SERVER_PORT'] ?? '') == 443
        );
    }

    public function scheme(): string
    {
        return $this->is_https() ? 'https' : 'http';
    }


    public function base_url($path = ''): string
    {
        // 설정된 base_url 가져오기 (없으면 빈 문자열)
        $base = function_exists('cfg') ? rtrim((string) cfg('base_url', ''), '/') : '';

        $path = ltrim((string) $path, '/');
        return $path ? "{$base}/{$path}" : $base;
    }

    public function site_url($uri = ''): string
    {
        $url = $this->base_url();

        $index = '';
        if (function_exists('cfg')) {
            $index = trim((string) cfg('index_page', ''), '/');
        }
        if ($index !== '') {
            $url .= '/' . $index;
        }

        if (is_array($uri)) {
            $uri = implode('/', array_map('trim', $uri));
        }

        $uri = trim((string) $uri, '/');
        if ($uri !== '') {
            $url .= '/' . $uri;
        }

        return rtrim($url, '/') . '/';
    }

    public function current_url(): string
    {
        return $this->scheme() . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/' . ltrim($this->uri_string(), '/');
    }

    public function full_url(): string
    {
        $query = $_SERVER['QUERY_STRING'] ?? '';
        return $this->current_url() . ($query ? '?' . $query : '');
    }

    /* -------------------------------------------------------------
     * CI3 uri-to-assoc / assoc-to-uri 지원
     * ------------------------------------------------------------- */
    public function uri_to_assoc(int $n = 3): array
    {
        $segments = array_slice($this->segments, $n - 1);
        $out = [];
        for ($i = 0; $i < count($segments); $i += 2) {
            $key = $segments[$i];
            $val = $segments[$i + 1] ?? null;
            $out[$key] = $val;
        }
        return $out;
    }

    public function assoc_to_uri(array $assoc): string
    {
        $out = [];
        foreach ($assoc as $k => $v) {
            $out[] = $k;
            $out[] = $v;
        }
        return implode('/', $out);
    }

    /* -------------------------------------------------------------
     * Helper
     * ------------------------------------------------------------- */
    public function anchor(string $uri, string $title, array $attrs = []): string
    {
        $href = htmlspecialchars($this->site_url($uri), ENT_QUOTES, 'UTF-8');
        $attrStr = '';
        foreach ($attrs as $k => $v) {
            $attrStr .= sprintf(' %s="%s"', htmlspecialchars($k, ENT_QUOTES, 'UTF-8'), htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
        }
        return sprintf('<a href="%s"%s>%s</a>', $href, $attrStr, htmlspecialchars($title, ENT_QUOTES, 'UTF-8'));
    }
}
