<?php
declare (strict_types = 1);

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * -------------------------------------------------------------
 * MiniCI Middleware System (Refactored for find_class)
 * -------------------------------------------------------------
 * - Middleware 인터페이스 기반 체인 처리
 * - 문자열, callable, 객체 모두 등록 가능
 * - Container 및 find_class() 통합
 * - 파일명 대소문자 무시 (Linux/Windows 호환)
 */

/**
 * 모든 미들웨어는 handle(array $req, callable $next) 구현
 */
interface Middleware {
    /** @return mixed */
    public function handle(array $request, callable $next);
}

/**
 * callable을 Middleware 인터페이스로 감싸는 어댑터
 */
final class CallableMiddleware implements Middleware {
    private $fn;
    public function __construct(callable $fn) {$this->fn = $fn;}
    public function handle(array $request, callable $next) {
        $fn = $this->fn;
        return $fn($request, $next);
    }
}

/**
 * -------------------------------------------------------------
 * Pipeline: Middleware 체인 실행기
 * -------------------------------------------------------------
 */
final class Pipeline {
    /** @var array<int, Middleware|callable|string> */
    private array $stack = [];

    /** 미들웨어 추가 */
    public function add($middleware): self {
        if (
            $middleware instanceof Middleware ||
            is_callable($middleware) ||
            is_string($middleware)
        ) {
            $this->stack[] = $middleware;
            return $this;
        }

        $type = is_object($middleware) ? get_class($middleware) : gettype($middleware);
        throw new InvalidArgumentException("Unsupported middleware type: {$type}");
    }

    /** 여러 미들웨어 추가 */
    public function addMany(array $middlewares): self {
        foreach ($middlewares as $mw) {
            $this->add($mw);
        }
        return $this;
    }

    /** 파이프라인 실행 */
    public function run(array $request, callable $core) {
        $next = $core;

        // 뒤에서 앞으로 감싸며 체인 구축
        for ($i = count($this->stack) - 1; $i >= 0; $i--) {
            $mw   = $this->resolve($this->stack[$i]);
            $curr = $next;

            $next = static function (array $req) use ($mw, $curr) {
                return $mw->handle($req, $curr);
            };
        }

        return $next($request);
    }

    /**
     * -------------------------------------------------------------
     * 미들웨어 해석 (resolve)
     * -------------------------------------------------------------
     * - Container → find_class() 순서로 탐색
     * - 클래스명, callable, 인스턴스 모두 지원
     */
    private function resolve($mw): Middleware {
        // 1) 이미 Middleware 인스턴스
        if ($mw instanceof Middleware) {
            return $mw;
        }

        // 2) callable(클로저/함수/인보커블 객체)
        if (is_callable($mw) && !is_string($mw)) {
            return new CallableMiddleware($mw);
        }

        // 3) 문자열 기반 클래스명
        if (is_string($mw)) {
            $inst = null;

            // Container에서 이미 존재하면 사용
            if (class_exists('Container') && Container::has($mw)) {
                $inst = Container::get($mw);
            }

            // 없으면 find_class()로 탐색 + 로드
            if (!$inst && function_exists('find_class')) {
                try {
                    // ✅ 올바른 순서: (이름, [경로])
                    $cls  = find_class($mw, [APPPATH . 'middlewares' . DIRECTORY_SEPARATOR]);
                    $inst = new $cls();
                } catch (Throwable $e) {
                    throw new InvalidArgumentException("미들웨어 '{$mw}'를 찾을 수 없습니다.");
                }
            }
            // 존재하지 않으면 예외
            if (!$inst) {
                throw new InvalidArgumentException("미들웨어 '{$mw}'를 해석할 수 없습니다.");
            }

            // 인터페이스 직접 구현
            if ($inst instanceof Middleware) {
                return $inst;
            }

            // handle() 메서드 존재 시 어댑터 적용
            if (is_callable([$inst, 'handle'])) {
                return new CallableMiddleware(static function (array $req, callable $next) use ($inst) {
                    return $inst->handle($req, $next);
                });
            }

            // 순수 callable 객체
            if (is_callable($inst)) {
                return new CallableMiddleware($inst);
            }

            $type = is_object($inst) ? get_class($inst) : gettype($inst);
            throw new InvalidArgumentException("미들웨어 '{$mw}'는 지원되지 않는 타입입니다({$type}).");
        }

        $type = is_object($mw) ? get_class($mw) : gettype($mw);
        throw new InvalidArgumentException("Unsupported middleware type: {$type}");
    }
}
