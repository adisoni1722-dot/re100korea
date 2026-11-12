<?php
/**
 * MiniCI Framework Entry Point
 * Minimum PHP Version: 7.4
 * moonkr@me.com
 */

defined('BASEPATH') or define('BASEPATH', __DIR__ . '/');
defined('APPPATH') or define('APPPATH', BASEPATH . 'application/');
defined('SYSPATH') or define('SYSPATH', BASEPATH . 'system/');
defined('VIEWPATH') or define('VIEWPATH', BASEPATH . 'views/');
defined('FCPATH') or define('FCPATH', BASEPATH);

/* --------------------------------------------------------------
 * 1) .env 로드
 * -------------------------------------------------------------- */
$envFile = BASEPATH . '.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        if (stripos($line, 'export ') === 0) {
            $line = trim(substr($line, 7));
        }

        if (!strpos($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
            continue;
        }

        if ($value !== '' && $value[0] !== '"' && $value[0] !== "'") {
            $value = trim(explode('#', $value, 2)[0]);
        } elseif (in_array($value[0], ['"', "'"])) {
            $quote = $value[0];
            if (substr($value, -1) === $quote) {
                $value = substr($value, 1, -1);
                if ($quote === '"') {
                    $value = stripcslashes($value);
                }

            }
        }

        $_ENV[$key] ??= $value;
        putenv("$key=$value");
    }
}


/* --------------------------------------------------------------
 * CLI 모드 감지: CLI 실행이면 웹 파이프라인을 완전히 건너뛴다
 * -------------------------------------------------------------- */
if (php_sapi_name() === 'cli') {
    // CLI 인자 재구성 (예: php minici tools migrate)
    $argv = $_SERVER['argv'] ?? [];
    array_shift($argv); // "index.php" 또는 "minici" 제거
    $_SERVER['PATH_INFO']   = '/' . implode('/', $argv);
    $_SERVER['REQUEST_URI'] = $_SERVER['PATH_INFO'];
    $_SERVER['REQUEST_METHOD'] = 'CLI';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] = 'MiniCI-CLI/1.0';

    // CLI 전용 커널로 전환 (웹 계층 비활성)
    $kernelPath = APPPATH . 'cli/Kernel.php';
    if (is_file($kernelPath)) {
        require_once $kernelPath;
        exit; // 웹 라우터로 진입하지 않음
    } else {
        fwrite(STDERR, "[MiniCI] CLI Kernel not found: {$kernelPath}\n");
        exit(1);
    }
}

/* --------------------------------------------------------------
 * 2) 환경 설정 / 에러 표시
 * -------------------------------------------------------------- */
define('ENVIRONMENT', $_ENV['ENVIRONMENT'] ?? 'development');
define('ALLOWED_IP', ['127.0.0.1', '1.224.142.201', '1.236.81.169']);

if (ENVIRONMENT === 'development') {
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
    ini_set('error_log', APPPATH . 'logs/php_error.log');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
}

if (php_sapi_name() === 'cli' && empty($_SERVER['PATH_INFO'])) {
    $argv = $_SERVER['argv'] ?? [];
    $_SERVER['PATH_INFO']   = '/' . implode('/', array_slice($argv, 1));
    $_SERVER['REQUEST_URI'] = $_SERVER['PATH_INFO'];
}

/* --------------------------------------------------------------
 * 3) 시스템 파일 로드
 * -------------------------------------------------------------- */

// 1. 상수
require_once APPPATH . 'config/constants.php';

// 2. 필수 공통/컨테이너 계층
require_once SYSPATH . 'Common.php';
require_once SYSPATH . 'Container.php';

// 3. 핵심 Core 계층 (보안·입력 제외)
require_once SYSPATH . 'Middleware.php';
require_once SYSPATH . 'Database.php';
require_once SYSPATH . 'Model.php';
require_once SYSPATH . 'Loader.php';
require_once SYSPATH . 'LegacyLoader.php';
require_once SYSPATH . 'Router.php';
require_once SYSPATH . 'Controller.php';


// 7. 시스템 서비스
foreach ([APPPATH . 'services/', SYSPATH . 'services/'] as $dir) {

    $servicesMap = _get_dir_cache($dir);

    if (isset($servicesMap['php'])) {
        $baseDir = realpath($dir) ?: rtrim($dir, DIRECTORY_SEPARATOR);

        foreach ($servicesMap['php'] as $entry) {
            // $entry는 'Security.php' 같은 파일명이므로 경로 조합
            $file = $baseDir . DIRECTORY_SEPARATOR . $entry;
            if (is_file($file)) {
                require_once $file;
            }
        }
    }
}

/* --------------------------------------------------------------
 * 4) 설정 로드
 * -------------------------------------------------------------- */
$configCache = require APPPATH . 'config/config.php';

if (empty($configCache['base_url'])) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

    $protocol = $is_https ? 'https://' : 'http://';
    $host     = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

    // 포트 처리
    $port = $_SERVER['SERVER_PORT'] ?? '';
    if ($port && !in_array($port, ['80', '443']) && strpos($host, ':') === false) {
        $host .= ':' . $port;
    }

    $configCache['base_url'] = $protocol . $host . '/';
}

// 컨테이너에 '배열' 상태로 등록
Container::set('config', $configCache);

/* --------------------------------------------------------------
 * 5) 앱 서비스(DI) 등록
 * -------------------------------------------------------------- */
require_once APPPATH . 'config/app.php';


/* --------------------------------------------------------------
 * 6) 오토로드 (헬퍼/라이브러리)
 * -------------------------------------------------------------- */
$autoloadFile  = APPPATH . 'config' . DIRECTORY_SEPARATOR . 'autoload.php';
$autoloadCache = is_file($autoloadFile) ? include $autoloadFile : [];

/* 6-1) helpers: 그대로 로드 */
if (!empty($autoloadCache['helpers'])) {
    foreach ($autoloadCache['helpers'] as $helper) {
        if ($helper) {
            helper($helper);
        }
    }
}

/* 6-2) libraries: 즉시 생성 대신 지연 싱글톤 바인딩 */
if (!empty($autoloadCache['libraries'])) {
    foreach ($autoloadCache['libraries'] as $lib) {
        if (!$lib) {
            continue;
        }
        Container::singletonIf($lib, static function () use ($lib) {
            return library($lib);
        });
    }
}

/* --------------------------------------------------------------
 * 7) 미들웨어 파이프라인 실행
 * -------------------------------------------------------------- */
$middlewares = $autoloadCache['middlewares'] ?? [];

// 항상 파이프라인 구성
$pipeline = new Pipeline();

/* 7-1) 코어 CSRF 미들웨어 선 장착 */
$coreCsrfMw = APPPATH . 'middlewares' . DIRECTORY_SEPARATOR . 'CsrfMiddleware.php';
if (is_file($coreCsrfMw)) {
    require_once $coreCsrfMw;

    if (class_exists('CsrfMiddleware')) {
        $csrfKey = 'mw.csrf';

        if (!Container::has($csrfKey) && !Container::bound($csrfKey)) {
            Container::singleton($csrfKey, fn() => new CsrfMiddleware());
        }

        // 문자열 키만 추가 (handle 시점에 Container::get('mw.csrf'))
        $pipeline->add($csrfKey);
    } else {
        log_message('error', '[MiniCI] CsrfCheck class not found in middlewares/');
    }
}

/* 7-2) autoload 미들웨어들 (지연 생성) */
if (!empty($middlewares) && is_array($middlewares)) {
    foreach ($middlewares as $mwName) {
        if (!is_string($mwName)) {
            log_message('error', '[MiniCI] Invalid middleware entry (not string).');
            continue;
        }

        if (!preg_match('/\A[A-Z][A-Za-z0-9_]*(\\\[A-Z][A-Za-z0-9_]*)*\z/', $mwName)) {
            log_message('error', "[MiniCI] Middleware '{$mwName}' rejected by name pattern.");
            continue;
        }

        $path = APPPATH . 'middlewares' . DIRECTORY_SEPARATOR
        . str_replace('\\', DIRECTORY_SEPARATOR, $mwName) . '.php';

        if (is_file($path)) {
            require_once $path;
        }

        if (!class_exists($mwName, false)) {
            log_message('error', "[MiniCI] Middleware class '{$mwName}' not found.");
            continue;
        }

        // 이름 정규화
        $short = (string) preg_replace('/^.*\\\\/', '', $mwName);
        $short = preg_replace(['/Middleware$/i', '/_middleware$/i'], '', $short);
        $short = strtolower($short);
        $mwId  = 'mw.' . $short;

        if (!Container::has($mwId) && !Container::bound($mwId)) {
            Container::singleton($mwId, static fn() => new $mwName());
        }

        $pipeline->add($mwId);
    }
}

/* --------------------------------------------------------------
 * 8) 라우팅 실행 및 응답 전송
 * -------------------------------------------------------------- */

// Request 스냅샷
$request = [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'uri'    => $_SERVER['REQUEST_URI'] ?? '/',
    'get'    => $_GET,
    'post'   => $_POST,
    'cookie' => $_COOKIE,
    'server' => $_SERVER,
];

// 파이프라인 실행 (스택이 비어 있어도 run은 core만 실행)
$response = $pipeline->run($request, function ($req) {
    return Container::get('router')->dispatch();
});

// 응답 전송(간단 핸들러)
// - 배열/객체: JSON으로 출력
// - 그 외: 문자열 캐스팅 출력
if ($response !== null) {
    if (is_array($response) || $response instanceof JsonSerializable) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    } else {
        echo (string) $response;
    }
}

/* --------------------------------------------------------------
 * 9) PHP-FPM 최적화
 * -------------------------------------------------------------- */
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

/* --------------------------------------------------------------
 * X) 공통 예외 응답자 (HTML/JSON 자동 협상)
 * -------------------------------------------------------------- */
function _http_default_message(int $code): string {
    switch ($code) {
        case 400:return '잘못된 요청입니다.';
        case 403:return '접근이 거부되었습니다.';
        case 404:return '요청하신 페이지를 찾을 수 없습니다.';
        default: return '서버 오류가 발생했습니다.';
    }
}

function respond_exception(Throwable $e, ?Router $router = null): void {
    $code = (int) $e->getCode();
    if ($code < 400 || $code > 599) {$code = 500;}

    if (!headers_sent()) {
        http_response_code($code);
    }

    $accept    = $_SERVER['HTTP_ACCEPT'] ?? '';
    $isXHR     = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    $wantsJson = $isXHR || strpos($accept, 'application/json') !== false || strpos($accept, 'text/json') !== false;

    $isDev = defined('ENVIRONMENT') && ENVIRONMENT === 'development';
    $msg   = $isDev ? $e->getMessage() : _http_default_message($code);

    // 항상 상세 로그
    error_log("[MiniCI] HTTP {$code} " . get_class($e) . ": {$e->getMessage()} @ {$e->getFile()}:{$e->getLine()}");

    if ($wantsJson) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $out = [
            'ok'      => false,
            'error'   => $code,
            'message' => $msg,
        ];
        if ($isDev) {
            $out['exception'] = get_class($e);
            $out['file']      = $e->getFile();
            $out['line']      = $e->getLine();
            $out['trace']     = array_slice(explode("\n", $e->getTraceAsString()), 0, 15);
            if ($router) {
                $out['route'] = [
                    'uri'       => $_SERVER['REQUEST_URI'] ?? '',
                    'segments'  => $router->segments ?? [],
                    'class'     => $router->class ?? null,
                    'method'    => $router->method ?? null,
                    'directory' => $router->directory ?? null,
                ];
            }
        }
        echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        return;
    }

    // HTML
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }

    $safe = function ($s): string {return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');};

    // 폰트 크기만 제어하는 변수
    $basePx = 18; // 본문(p)
    $monoPx = 15; // pre (스택/트레이스)
    $h1Px   = 30; // 404 코드
    $h2Px   = 22; // "Exception"
    $h3Px   = 18; // "Route"

    echo "<!doctype html><meta charset='utf-8'><title>{$code}</title>";
    echo "<h1 style='font-size: {$h1Px}px; margin: 0 0 12px'>{$code}</h1>"
        . "<p style='font-size: {$basePx}px; margin: 0 0 20px'>" . $safe($msg) . "</p>";

    if ($isDev) {
        // 예외 전체 문자열(메시지+트레이스)을 안전하게 출력
        echo "<h2 style='font-size: {$h2Px}px; margin: 16px 0 8px'>Exception</h2>"
            . "<pre style='white-space:pre-wrap; font-size: {$monoPx}px; line-height:1.6'>" . $safe((string) $e) . "</pre>";
        if ($router) {
            $dbg = [
                'uri'       => $_SERVER['REQUEST_URI'] ?? '',
                'segments'  => $router->segments ?? [],
                'class'     => $router->class ?? null,
                'method'    => $router->method ?? null,
                'directory' => $router->directory ?? null,
            ];
            echo "<h3 style='font-size: {$h3Px}px; margin: 16px 0 8px'>Route</h3>"
                . "<pre style='font-size: {$monoPx}px; line-height:1.6'>" . $safe(print_r($dbg, true)) . "</pre>";
        }
    }

}
