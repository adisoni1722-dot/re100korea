<?php
defined('BASEPATH') or exit('No direct script access allowed');
/**
 * -------------------------------------------------------------
 * View (CI4-Style Native PHP Template Engine)
 * -------------------------------------------------------------
 */

class View
{
    private string $basePath;
    private array $viewData = [];

    // 레이아웃 상속용 프로퍼티
    private ?string $layout             = null;
    private array $sections             = [];
    private ?string $currentSectionName = null;

    /**
     * 뷰 기본 경로 설정
     */
    public function __construct()
    {
        $this->basePath = defined('VIEWPATH')
            ? rtrim(VIEWPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            : rtrim(BASEPATH, DIRECTORY_SEPARATOR) . '/views/';
    }


    /**
     * 파일을 $this 컨텍스트에서 렌더링하고 변수 주입 (extract)
     */
    private function renderFile(string $_file_, array $_data_): string
    {
        $renderer = function () use ($_file_, $_data_) {
            extract($_data_, EXTR_SKIP);

            ob_start();
            try {
                include $_file_;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }
            return ob_get_clean();
        };

        return $renderer->call($this);
    }

    /**
     * XSS 이스케이프가 적용된 데이터 준비
     */

    private function prepareData(array $data): array
    {
        $preparedData = [];

        foreach ($data as $key => $value) {
            // 1. RawString 객체면 -> 객체 그대로 유지! (문자열 변환 X)
            if ($value instanceof RawString) {
                // 객체 타입을 유지해야 나중에 include 할 때도 RawString으로 인식됨
                $preparedData[$key] = $value;
            }
            // 2. 그 외 -> 무조건 보안 처리 (Escape)
            else {
                $preparedData[$key] = esc($value);
            }
        }
        return $preparedData;
    }
    // --------------------------------------------------------------
    // Layout Methods (뷰 파일에서 $this->... 로 호출)
    // --------------------------------------------------------------

    public function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    public function section(string $name): void
    {
        if ($this->currentSectionName) {
            throw new RuntimeException("Section '{$this->currentSectionName}'이(가) 아직 끝나지 않았습니다.");
        }
        ob_start();
        $this->currentSectionName = $name;
    }

    public function endSection(): void
    {
        if (!$this->currentSectionName) {
            throw new RuntimeException("시작되지 않은 section을 endSection() 하려 했습니다.");
        }

        $this->sections[$this->currentSectionName] = ob_get_clean();
        $this->currentSectionName                  = null;
    }

    /**
     * [RENAMED] 레이아웃 파일에서 섹션 렌더링
     */
    public function slot(string $name, bool $escape = false): string
    {
        $content = $this->sections[$name] ?? '';
        return $escape ? esc($content) : $content;
    }

    public function include(string $file, array $data = array()): string
    {
        $path = find_file($this->basePath, $file, array('php', 'html'));
        if (!$path || !is_file($path)) {
            return "";
        }
        $partialData = array_merge($this->viewData, $this->prepareData($data));
        return $this->renderFile($path, $partialData);
    }

    // --------------------------------------------------------------
    // v1-Style Auto-Wrapping Method
    // --------------------------------------------------------------

    /**
     * 자동 래핑 레이아웃 파일 탐색 (v1 방식)
     */
    private function findLayout(string $view): ?string
    {
        $dir         = dirname($view);
        $searchPaths = [];

        if ($dir !== '.' && $dir !== '') {
            $searchPaths[] = $this->basePath . rtrim($dir, '/\\');
        }
        $searchPaths[] = rtrim($this->basePath, DIRECTORY_SEPARATOR);

        foreach ($searchPaths as $path) {
            foreach (['_layout.php', '_layout.html'] as $file) {
                $fullPath = $path . DIRECTORY_SEPARATOR . $file;
                if (is_file($fullPath)) {
                    return $fullPath;
                }
            }
        }
        return null;
    }

    // --------------------------------------------------------------
    // 렌더링 실행 (하이브리드 로직 + content 섹션 통일)
    // --------------------------------------------------------------

    /**
     * 메인 뷰 렌더링
     */
    public function render(string $view, array $data = array(), bool $useLayout = true): void
    {
        // 1. 뷰 이름 유효성 검사 및 경로 탐색
        if (strpos($view, '..') !== false || !preg_match('/\A[A-Za-z0-9_\-\.\/]+\z/', $view)) {
            throw new RuntimeException("Invalid view name: {$view}");
        }

        $dir  = dirname($view);
        $name = basename($view);
        $baseDir = ($dir === '.' || $dir === '') ? $this->basePath : $this->basePath . DIRECTORY_SEPARATOR . $dir;

        $file = find_file($baseDir, $name, ['php', 'html']); // find_file 호출 방식에 맞게 조정

        if ($file === null) {
             // 경로 재조정 시도 (기존 로직 유지)
             $file = find_file($this->basePath . DIRECTORY_SEPARATOR . $dir, $name, ['php', 'html']);
             if ($file === null) throw new RuntimeException("View not found: {$view}");
        }

        // 2. 데이터 준비
        $this->viewData = $this->prepareData($data);

        // Asset 로드 경로 설정
        $viewDir = dirname(str_replace($this->basePath, '', $file));
        if (class_exists('Container')) {
            Container::set('current_view_dir', $viewDir);
        }

        // 3. [핵심 변경] 뷰 렌더링 및 중첩 레이아웃 루프 처리

        // (1) 먼저 메인 뷰(알맹이)를 렌더링
        $content = $this->renderFile($file, $this->viewData);

        if (!$useLayout) {
            echo $content;
            $this->resetLayout();
            return;
        }

        // (2) 레이아웃 체이닝 (While Loop)
        // 레이아웃이 없을 때까지 계속 상위 레이아웃을 찾아서 렌더링함
        while ($this->layout !== null) {
            $layoutName = $this->layout;
            $this->layout = null; // 현재 레이아웃 초기화 (다음 레이아웃에서 extend 호출 감지 위해)

            $layoutFile = find_file($this->basePath, $layoutName, array('php', 'html'));

            if (!$layoutFile) {
                throw new RuntimeException("Layout file not found: {$layoutName}");
            }

            // 현재까지 만들어진 $content를 'content' 섹션에 저장 (기본값)
            // 주의: 레이아웃 파일 내부에서 $this->renderSection('content') 대신 $this->slot('content')를 쓰므로
            // 섹션 배열에 내용을 채워넣어야 함.
            if (!isset($this->sections['content'])) {
                 $this->sections['content'] = $content;
            }

            // 레이아웃 렌더링 -> 결과가 새로운 $content가 됨
            // 이 과정에서 레이아웃 파일 안에 또 $this->extend()가 있다면 루프가 다시 돔
            $content = $this->renderFile($layoutFile, $this->viewData);
        }

        // 4. (Legacy v1 지원) extend()가 한 번도 호출되지 않았다면 자동 _layout 탐색
        // 단, 이미 위의 루프를 돌았다면 이 단계는 건너뜀
        if (empty($this->sections) && $content) {
             $layoutFile = $this->findLayout($view);
             if ($layoutFile) {
                 $this->sections['content'] = $content;
                 $content = $this->renderFile($layoutFile, $this->viewData);
             }
        }

        // 5. 최종 출력
        echo $content;
        $this->resetLayout();
    }

    /**
     * 레이아웃 상태 초기화
     */
    private function resetLayout(): void
    {
        $this->layout             = null;
        $this->sections           = [];
        $this->currentSectionName = null;
    }

    /**
     * 재귀적 배열 병합 유틸
     */
    private function mergeRecursive(array $base, array $over): array
    {
        foreach ($over as $k => $v) {
            if (isset($base[$k]) && is_array($base[$k]) && is_array($v)) {
                $base[$k] = $this->mergeRecursive($base[$k], $v);
            } else {
                $base[$k] = $v;
            }
        }
        return $base;
    }
}
