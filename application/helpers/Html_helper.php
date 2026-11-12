<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * 이미지 경로 반환
 *
 * @param string|null $image_path 실제 이미지 경로
 * @param string $default 기본 이미지 경로
 * @return string 최종 이미지 경로
 */
if (!function_exists('image_path')) {
    function image_path($image_path, $default = '/views/errors/no_image.png') {
        if (!empty($image_path) && file_exists(FCPATH . ltrim($image_path, '/'))) {
            return $image_path;
        }
        return $default;
    }
}

if (!function_exists('is_avatar')) {
    function is_avatar($user_id = '', $size = 50) {
        if (empty($user_id)) {
            return '';
        }

        // 앞글자 추출 (한글 안전)
        $initial = strtoupper(mb_substr($user_id, 0, 1, 'UTF-8'));

        // 텍스트만 반환
        return htmlspecialchars($initial, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * 브라우저 이름 추출
 */
/**
 * 브라우저 + OS 탐지
 * 예시: Chrome (Windows 10), Samsung Internet (Android)
 */
if (!function_exists('detect_browser_os')) {
    /**
     * 브라우저 + OS 감지 (2025 최신 대응)
     * 정확도 우선 / iPadOS·macOS·Windows 11·Edge·Chromium 기반 브라우저 지원
     */
    function detect_browser_os(string $agent): string {
        $agent = strtolower($agent);

        /* --------------------------------------------------------------
         * 1) 브라우저 감지 (정확도 순서)
         * -------------------------------------------------------------- */
        if (strpos($agent, 'samsungbrowser') !== false) {
            $browser = 'Samsung Internet';
        } elseif (strpos($agent, 'edg') !== false) { // Microsoft Edge (Chromium)
            $browser = 'Edge';
        } elseif (strpos($agent, 'opr') !== false || strpos($agent, 'opera') !== false) {
            $browser = 'Opera';
        } elseif (strpos($agent, 'crios') !== false) { // iOS용 Chrome
            $browser = 'Chrome (iOS)';
        } elseif (strpos($agent, 'fxios') !== false) { // iOS용 Firefox
            $browser = 'Firefox (iOS)';
        } elseif (strpos($agent, 'chrome') !== false && strpos($agent, 'edg') === false && strpos($agent, 'opr') === false) {
            $browser = 'Chrome';
        } elseif (strpos($agent, 'safari') !== false && strpos($agent, 'chrome') === false && strpos($agent, 'crios') === false) {
            $browser = 'Safari';
        } elseif (strpos($agent, 'firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($agent, 'trident') !== false || strpos($agent, 'msie') !== false) {
            $browser = 'Internet Explorer';
        } else {
            $browser = 'Unknown Browser';
        }

        /* --------------------------------------------------------------
         * 2) OS 감지 (정규식 + iPadOS 대응)
         * -------------------------------------------------------------- */
        // iOS 계열
        if (preg_match('/iphone|ipod|ipad/i', $agent)) {
            $os = 'iOS';
        }
        // iPadOS 13+ (Macintosh + Mobile 포함)
        elseif (strpos($agent, 'macintosh') !== false && strpos($agent, 'mobile') !== false) {
            $os = 'iPadOS';
        }
        // Android
        elseif (strpos($agent, 'android') !== false) {
            $os = 'Android';
        }
        // Windows
        elseif (preg_match('/windows nt 10|windows nt 11/i', $agent)) {
            $os = 'Windows 10/11';
        } elseif (strpos($agent, 'windows nt 6.3') !== false) {
            $os = 'Windows 8.1';
        } elseif (strpos($agent, 'windows nt 6.2') !== false) {
            $os = 'Windows 8';
        } elseif (strpos($agent, 'windows nt 6.1') !== false) {
            $os = 'Windows 7';
        }
        // macOS (데스크탑 전용)
        elseif (strpos($agent, 'mac os x') !== false || strpos($agent, 'macintosh') !== false) {
            $os = 'macOS';
        }
        // Linux / 기타
        elseif (strpos($agent, 'linux') !== false) {
            $os = 'Linux';
        } else {
            $os = 'Unknown OS';
        }

        /* --------------------------------------------------------------
         * 3) 보정 (iPad Safari와 macOS Safari 구분)
         * -------------------------------------------------------------- */
        if ($browser === 'Safari' && $os === 'macOS' && strpos($agent, 'mobile') !== false) {
            $os = 'iPadOS';
        }

        /* --------------------------------------------------------------
         * 4) 결과 반환
         * -------------------------------------------------------------- */
        return "{$browser} ({$os})";
    }
}

if (!function_exists('perf_badge')) {
    /**
     * 상단 속도 배지 반환 (HTML만 호출하세요)
     * @param float|null $baseline_ms  비교 기준(ms) – 예: getenv('PERF_BASELINE_MS')로 넘김
     * @param int        $fade_ms      자동 숨김까지 ms (기본 8000)
     */
    function perf_badge(?float $baseline_ms = null, int $fade_ms = 8000): string {
        $start   = defined('MINICI_REQ_START') ? MINICI_REQ_START : ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
        $elapsed = (microtime(true) - $start) * 1000;

        // 공용 성능 헤더(프로덕션 포함)
        @header('X-Response-Time: ' . number_format($elapsed, 2) . 'ms', true);
        @header('Server-Timing: app;desc="MiniCI";dur=' . (int) round($elapsed), true);

        // 프로덕션에서는 배지 숨김(원하면 제거)
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
            return '';
        }

        $cmp = '';
        if (is_numeric($baseline_ms) && $baseline_ms > 0) {
            $diff  = $elapsed - $baseline_ms;
            $pct   = $baseline_ms ? ($diff / $baseline_ms * 100) : 0;
            $arrow = ($diff <= 0) ? '▲' : '▼'; // ▲: 개선, ▼: 악화
            $cmp   = sprintf(' | 기준대비: %s %.2fms (%.1f%%)', $arrow, abs($diff), abs($pct));
        }

        $ms  = number_format($elapsed, 2);
        $now = date('H:i:s');

        $badge = <<<HTML
<div id="minici-perf-badge" style="position:fixed;top:0;left:0;right:0;z-index:2147483647;background:rgba(0,0,0,.75);color:#fff;font:12px/1.4 system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;padding:6px 10px;text-align:center;pointer-events:none;">
  ⏱ {$ms}ms <small>({$now})</small>{$cmp}
</div>
<script>(function(){try{
  var b=document.getElementById('minici-perf-badge');
  var bd=document.body; if(b&&bd){ var h=b.getBoundingClientRect().height; if(h>0){ bd.style.paddingTop=(parseFloat(getComputedStyle(bd).paddingTop)||0)+h+'px'; } }
  setTimeout(function(){ if(!b)return; b.style.transition='opacity .4s ease'; b.style.opacity='0'; setTimeout(function(){ if(b&&b.parentNode) b.parentNode.removeChild(b); }, 450); }, {$fade_ms});
}catch(e){}})();</script>
HTML;
        return $badge;
    }
}

if (!function_exists('perf_headers')) {
    /** API/JSON에서만 써도 좋음: 헤더만 부착 */
    function perf_headers(): void {
        $start   = defined('MINICI_REQ_START') ? MINICI_REQ_START : ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
        $elapsed = (microtime(true) - $start) * 1000;
        @header('X-Response-Time: ' . number_format($elapsed, 2) . 'ms', true);
        @header('Server-Timing: app;desc="MiniCI";dur=' . (int) round($elapsed), true);
    }
}
