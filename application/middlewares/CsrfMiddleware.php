<?php

declare(strict_types=1);

/**
 * application/middlewares/CsrfMiddleware.php
 * - MiddlewareInterface 구현
 * - 보호 메서드(POST/PUT/PATCH/DELETE)에서만 검증
 * - 예외 경로: 정확/와일드카드(*) 지원
 * - 폼 필드 우선, 없으면 헤더(X-CSRF-Token)
 */

class CsrfMiddleware implements Middleware
{
    /** @var string[] */
    protected array $methods = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** @var string[] */
    protected array $except = ['/webhook/*', '/api/*'];

    /** @var string */
    protected string $headerKey = 'X-CSRF-Token';

    public function __construct(array $options = [])
    {
        if (isset($options['methods']) && is_array($options['methods'])) {
            $this->methods = $options['methods'];
        }
        if (isset($options['except']) && is_array($options['except'])) {
            $this->except = $options['except'];
        }
        if (isset($options['header']) && is_string($options['header']) && $options['header'] !== '') {
            $this->headerKey = $options['header'];
        }
    }

    /**
     * 파이프라인 표준 진입점 (대부분의 구현이 이 메서드를 호출)
     * @param array    $request
     * @param callable $next
     * @return mixed
     */
    public function handle(array $request, callable $next)
    {
        $method = strtoupper($request['method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri    = (string) (parse_url($request['uri'] ?? ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');

        if (!$this->shouldProtect($method, $uri)) {
            return $next($request);
        }

        $csrf = $this->resolveCsrf();
        if (!$csrf) {
            $this->forbidden($request, 'CSRF service unavailable');
            return null;
        }

        $fieldName = $csrf->get_csrf_token_name(); // CI3 호환 필드명

        $headers = $this->collectHeaders();
        // 1) POST 필드 우선
        $token = $request['post'][$fieldName] ?? null;

        // 2) JSON body 내 토큰 탐색 (fetch에서 body: JSON.stringify()로 보냈을 때)
        if ($token === null && isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            if ($raw && ($json = json_decode($raw, true))) {
                if (isset($json[$fieldName])) {
                    $token = $json[$fieldName];
                }
            }
        }

        // 3) 헤더 (X-CSRF-Token 또는 config에서 지정한 key)
        if ($token === null) {
            $token = $this->readHeader($headers, $this->headerKey);
        }

        if (!is_string($token) || $token === '' || !$csrf->verify_csrf($token)) {
            $this->forbidden($request, 'Invalid or missing CSRF token');
            return null;
        }

        return $next($request);
    }

    /**
     * 일부 파이프라인이 __invoke를 호출하는 경우 대비
     * @param array    $request
     * @param callable $next
     * @return mixed
     */
    public function __invoke(array $request, callable $next)
    {
        return $this->handle($request, $next);
    }

    /**
     * 일부 구현체가 process()를 호출하는 경우 대비
     * @param array    $request
     * @param callable $next
     * @return mixed
     */
    public function process(array $request, callable $next)
    {
        return $this->handle($request, $next);
    }

    /* ==================== 내부 유틸 ==================== */

    protected function shouldProtect(string $method, string $uri): bool
    {
        if (!in_array($method, $this->methods, true)) {
            return false;
        }
        foreach ($this->except as $pat) {
            if ($this->matchPath($uri, $pat)) {
                return false;
            }
        }
        return true;
    }

    protected function resolveCsrf()
    {
        if (function_exists('app')) {
            try {
                $svc = app('csrf');
                if ($svc) {
                    return $svc;
                }
            } catch (\Throwable $e) {
            }
        }
        if (class_exists('Container') && method_exists('Container', 'has') && method_exists('Container', 'get')) {
            try {
                if (\Container::has('csrf')) {
                    return \Container::get('csrf');
                }
            } catch (\Throwable $e) {
            }
        }
        return null;
    }

    protected function collectHeaders(): array
    {
        if (function_exists('getallheaders')) {
            $h = getallheaders();
            return is_array($h) ? $h : [];
        }
        // FPM/CLI 폴백
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (strpos($k, 'HTTP_') === 0) {
                $key           = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
                $headers[$key] = $v;
            } elseif (in_array($k, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $key           = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $k))));
                $headers[$key] = $v;
            }
        }
        return $headers;
    }

    protected function readHeader(array $headers, string $key): ?string
    {
        if (isset($headers[$key])) {
            return $headers[$key];
        }

        foreach ($headers as $k => $v) {
            if (strcasecmp($k, $key) === 0) {
                return $v;
            }
        }
        return null;
    }

    protected function wantsJson(array $headers, array $get): bool
    {
        if (isset($get['format']) && $get['format'] === 'json') {
            return true;
        }

        $accept = $this->readHeader($headers, 'Accept');
        if (is_string($accept) && stripos($accept, 'application/json') !== false) {
            return true;
        }

        $xr = $this->readHeader($headers, 'X-Requested-With');
        if (is_string($xr) && strtolower($xr) === 'xmlhttprequest') {
            return true;
        }

        $ct = $this->readHeader($headers, 'Content-Type');
        if (is_string($ct) && stripos($ct, 'application/json') !== false) {
            return true;
        }

        return false;
    }
    protected function forbidden(array $request, string $message): void
    {
        // 1. 보안 헤더
        header('X-Frame-Options: SAMEORIGIN');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        $headers = $this->collectHeaders();
        $isJson  = $this->wantsJson($headers, $request['get'] ?? []);

        http_response_code(403);

        // 2. 새 토큰 생성 (다음 요청을 위해 갱신은 해둡니다)
        $newToken = null;
        $csrf     = $this->resolveCsrf();
        if ($csrf && method_exists($csrf, 'regenerate')) {
            try {
                $newToken = $csrf->regenerate();
            } catch (Throwable $e) {
                log_message('error', sprintf('[MiniCI] CSRF regenerate error: %s', $e->getMessage()));
            }
        }

        // 3. JSON 요청일 경우 (AJAX)
        if ($isJson) {
            header('Content-Type: application/json; charset=UTF-8');
            $payload = [
                'success' => false,
                'message' => '보안 토큰이 만료되었습니다. 새로고침 후 다시 시도해주세요.',
                'errors'  => ['csrf' => $message],
            ];
            if ($newToken !== null && $csrf) {
                $payload['token'] = [
                    'name'  => $csrf->get_csrf_token_name(),
                    'value' => $newToken,
                ];
                header('X-CSRF-Token: ' . $newToken);
            }
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        // 4. 일반 접속일 경우 안내 페이지 (HTML)

        // 세션 저장 (갱신된 토큰 저장)
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // 돌아갈 URL 설정 (이전 페이지가 없으면 메인으로)
        $backUrl = $_SERVER['HTTP_REFERER'] ?? '/';
        // 만약 Referer가 현재 요청 주소와 같다면(무한 루프 방지) 메인으로
        if (strpos($backUrl, $request['uri'] ?? 'unknown') !== false) {
            $backUrl = '/';
        }

        header('Content-Type: text/html; charset=UTF-8');

        echo '<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>보안 세션 만료</title>
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
            --bg-color: #f3f4f6;
            --card-bg: #ffffff;
            --text-main: #1f2937;
            --text-sub: #6b7280;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--bg-color);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: var(--card-bg);
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        .icon-box {
            background-color: #fee2e2; /* Red 100 */
            color: #ef4444; /* Red 500 */
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        .icon-box svg {
            width: 32px;
            height: 32px;
        }
        h1 {
            color: var(--text-main);
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 0 12px;
        }
        p {
            color: var(--text-sub);
            margin-bottom: 32px;
            line-height: 1.6;
            font-size: 1rem;
            word-break: keep-all;
        }
        button {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            background-color: var(--primary-color);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
            text-decoration: none;
        }
        button:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
        }
        a { text-decoration: none; display: block; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon-box">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </div>

        <h1>페이지가 만료되었습니다</h1>
        <p>보안을 위해 접속이 만료되었습니다.<br>이전 페이지로 돌아가서 다시 시도해주세요.</p>

        <a href="' . htmlspecialchars($backUrl, ENT_QUOTES) . '">
            <button type="button">페이지로 돌아가기</button>
        </a>
    </div>
</body>
</html>';
        exit;
    }

    protected function matchPath(string $uri, string $pattern): bool
    {
        if ($pattern === $uri) {
            return true;
        }

        if (strpos($pattern, '*') !== false) {
            $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/i';
            return (bool) preg_match($regex, $uri);
        }

        return false;
    }
}
