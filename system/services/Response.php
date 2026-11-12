<?php
defined('BASEPATH') or exit('No direct script access allowed');
/**
 * -------------------------------------------------------------
 * Responder 컴포넌트
 * -------------------------------------------------------------
 */
class Response
{
    public function json($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function ajax($ok, $msg = '', $data = array(), $status = 200)
    {
        $payload = [
            'success' => $ok,
            'message' => $msg,
        ];

        // 에러 응답일 때
        if (!$ok && isset($data['errors'])) {
            $payload['payload'] = null;
            $payload['errors']  = $data['errors'];
        } else {
            $payload['payload'] = $data;
            $payload['errors']  = null;
        }

        // CSRF 토큰 자동 추가
        if (class_exists('Container') && Container::has('csrf')) {
            $csrf = Container::get('csrf');
            if (is_object($csrf) && method_exists($csrf, 'get_csrf_token_name') && method_exists($csrf, 'get_csrf_hash')) {
                $payload['token'] = [
                    'name'  => $csrf->get_csrf_token_name(),
                    'value' => $csrf->get_csrf_hash(),
                ];
            }
        }

        $this->json($payload, $status);
    }

    public function html($html, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }


    public function redirect(string $uri = '', string $method = 'auto', ?int $code = null): void
    {
        // CRLF Injection 방지
        if (preg_match('/[\r\n]/', $uri)) {
            throw new InvalidArgumentException('Invalid redirect URL.');
        }

        // 상대 경로 -> 절대 경로 변환
        if (!preg_match('#^https?://#i', $uri)) {
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $uri  = ($host ? "//{$host}" : '') . '/' . ltrim($uri, '/');
        }

        $code ??= ($method === 'refresh') ? 200 : 302;

        if (!headers_sent()) {
            // 표준 HTTP 리디렉션
            if ($method === 'refresh') {
                header("Refresh:0;url={$uri}");
            } else {
                header("Location: {$uri}", true, $code);
            }
            exit;
        }

        // 헤더가 이미 전송된 경우 Javascript 폴백
        echo "<script>location.href='" . htmlspecialchars($uri, ENT_QUOTES, 'UTF-8') . "';</script>";
        exit;
    }


    /**
     * 파일 다운로드 강제 실행
     * @param string $filepath 파일의 절대 경로 (예: /var/www/html/data/file.zip)
     * @param string|null $filename 다운로드될 파일명 (null이면 원본 파일명 사용)
     */
    public function download(string $filepath, ?string $filename = null): void
    {
        // 1. 파일 존재 여부 확인
        if (!is_file($filepath)) {
            http_response_code(404);
            exit('File not found.');
        }

        // 2. 다운로드 파일명 설정 (지정 안 하면 경로의 파일명 사용)
        $filename ??= basename($filepath);

        // 3. 출력 버퍼 비우기 (매우 중요: 이전에 출력된 공백/에러가 파일에 섞이지 않게 함)
        if (ob_get_length()) {
            ob_clean();
        }

        // 4. 다운로드 헤더 설정
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        // rawurlencode: 한글/특수문자 파일명 깨짐 방지
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));

        // 5. 파일 전송 및 종료
        readfile($filepath);
        exit;
    }
}
