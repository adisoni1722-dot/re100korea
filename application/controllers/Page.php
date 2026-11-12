<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Page extends Controller
{
    // 최대 허용 세그먼트(루트 제외) — 너무 깊은 경로 차단
    protected int $max_segments = 6;

    // 뷰 베이스(캐시)
    protected string $viewsBase;

    public function __construct()
    {
        parent::__construct();
        $this->viewsBase = rtrim(FCPATH, '/\\') . '/views/';
    }

    public function _remap($method)
    {
        $segments = $this->uri->segment_array();
        // 첫 세그먼트('page') 제거
        array_shift($segments);

        // 빈 요청 처리 (예: /page)
        if (empty($segments)) {
            show_404();
            return;
        }

        // 세그먼트 수 제한
        if (count($segments) > $this->max_segments) {
            show_404();
            return;
        }

        // 1) 원본 조합: 슬래시 포함 경로
        $raw_path = implode('/', $segments);

        // 2) 정제: 허용 문자만 남김 (영숫자, _, -, /)
        $theme_path = preg_replace('/[^a-zA-Z0-9_\/-]/', '', $raw_path);

        // 3) 추가 차단: 널바이트, 상위경로, .php 직접 입력
        if (
            $theme_path === '' ||
            strpos($theme_path, '..') !== false ||
            strpos($theme_path, "\0") !== false ||
            preg_match('/\.php$/i', $theme_path)
        ) {
            show_404();
            return;
        }

        // 후보 뷰 목록 (우선순위: 슬래시 경로 -> 하이픈 연결 -> page/하이픈)
        $candidates = [
            $theme_path,
            str_replace('/', '-', $theme_path),
            'page/' . str_replace('/', '-', $theme_path),
        ];

        // 뷰 베이스 realpath (캐시)
        $real_views_base = realpath($this->viewsBase);
        if ($real_views_base === false) {
            // views 디렉터리 자체가 없으면 404
            show_404();
            return;
        }

        foreach ($candidates as $v) {
            // 안전하게 파일 경로 구성 (절대 경로)
            $htmlFile = $this->viewsBase . $v . '.html';
            $phpFile  = $this->viewsBase . $v . '.php';

            // 성능: is_file 먼저 검사 (존재하면 realpath 검사)
            if (is_file($htmlFile) && is_readable($htmlFile)) {
                $real = realpath($htmlFile);
                if ($real && strpos($real, $real_views_base) === 0) {
                    // 안전 확인 완료 — 전역 view() 헬퍼 사용
                    view($v);
                    return;
                }
                continue;
            }

            // .php 파일 허용 (단, 직접 .php 요청은 이미 차단됨)
            if (is_file($phpFile) && is_readable($phpFile)) {
                $real = realpath($phpFile);
                if ($real && strpos($real, $real_views_base) === 0) {
                    view($v);
                    return;
                }
                continue;
            }
        }

        // 찾지 못하면 404
        show_404();
    }
}
