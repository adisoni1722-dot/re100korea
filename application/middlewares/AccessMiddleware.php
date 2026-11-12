<?php
defined('BASEPATH') or exit('No direct script access allowed');

class AccessMiddleware implements Middleware {
    /** @var string[] 로그 제외 대상 IP */
    protected array $excludedIps = ['1.224.142.201', '1.236.81.169'];

    public function handle(array $request, callable $next) {
        $memberId = session('member_id');

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        if (preg_match('~\.(ico|css|js|png|jpg|jpeg|gif|svg|webp|woff2?)$~i', $uri)) {
            return $next($request);
        }

        $start  = microtime(true);
        $result = $next($request);

        try {
            if (!empty($memberId)) {
                $db     = Container::get('db');
                $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
                $status = http_response_code() ?: 200;
                $ip     = $this->getClientIp();

                // 제외 IP 체크
                if (in_array($ip, $this->excludedIps, true)) {
                    return $result;
                }

                $ua       = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
                $referer  = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 255);
                $duration = (int) round((microtime(true) - $start) * 1000);

                $sql = "INSERT INTO member_access_log
            (member_id, method, uri, status_code, ip, user_agent, referer, duration_ms)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $params = [$memberId, $method, $uri, $status, $ip, $ua, $referer, $duration];

                $db->query($sql, $params);
            }
        } catch (Throwable $e) {
            log_message('error', sprintf(
                '[MiniCI][AccessLogMiddleware] Error(): %s @ %s:%d',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
        }

        return $result;
    }

    private function getClientIp(): string {
        $s  = $_SERVER;
        $ip = $s['HTTP_CF_CONNECTING_IP'] ?? $s['HTTP_X_FORWARDED_FOR'] ?? $s['REMOTE_ADDR'] ?? '0.0.0.0';
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        return trim($ip);
    }
}
