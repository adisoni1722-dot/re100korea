<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Context
{
    /** @var array 원본 데이터/클로저 저장소 (절대 삭제되지 않음) */
    protected array $definitions = [];

    /** @var array 실행 결과 캐시 저장소 (reload 시 삭제됨) */
    protected array $cache = [];

    /**
     * 데이터 등록
     */
    public function set(string $key, $value): self
    {
        // 1. 원본 정의 저장
        $this->definitions[$key] = $value;

        // 2. 클로저가 아닌 일반 값이라면 캐시에도 바로 등록 (값 확정)
        //    클로저라면 캐시를 비워서 다음에 실행되도록 유도
        if (!($value instanceof Closure)) {
            $this->cache[$key] = $value;
        } else {
            unset($this->cache[$key]);
        }

        return $this;
    }

    /**
     * 데이터 가져오기
     */
    public function get(string $key, $default = null, bool $auto_escape = true)
    {
        // 1. 캐시(이미 계산된 값)가 있으면 즉시 반환 (가장 빠름)
        if (array_key_exists($key, $this->cache)) {
            $val = $this->cache[$key];
        }
        // 2. 캐시는 없지만 정의(클로저)는 있다면? -> 실행 후 캐싱
        elseif (array_key_exists($key, $this->definitions)) {
            $def = $this->definitions[$key];

            if ($def instanceof Closure) {
                $val = $def(); // 함수 실행 (DB 조회 등)
            } else {
                $val = $def;
            }

            // [중요] 결과를 캐시에 저장 (원본 $definitions는 건드리지 않음)
            $this->cache[$key] = $val;
        }
        // 3. 아무것도 없으면 기본값
        else {
            return $default;
        }

        // 4. 보안 (Auto Escape)
        if ($auto_escape && is_string($val)) {
            return function_exists('esc') ? esc($val) : htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
        }

        return $val;
    }

    /**
     * [신규 기능] 특정 데이터의 캐시를 삭제하여 재계산 유도
     * 사용법: app('context')->reload('get_balance_format');
     */
    public function reload(string $key): void
    {
        unset($this->cache[$key]);
    }

    /**
     * [신규 기능] 데이터 존재 여부 확인
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->cache) || array_key_exists($key, $this->definitions);
    }

    /**
     * 모든 데이터 반환 (디버깅용)
     */
    public function all(): array
    {
        $keys = array_unique(array_merge(array_keys($this->definitions), array_keys($this->cache)));
        $output = [];

        foreach ($keys as $key) {
            // get()을 호출하여 캐싱 로직과 이스케이프 로직을 태움
            $output[$key] = $this->get($key, null, false);
        }
        return $output;
    }
}
