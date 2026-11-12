<?php
defined('BASEPATH') or exit('No direct script access allowed');

class ContextMiddleware implements Middleware
{
    public function handle(array $request, callable $next)
    {
        // 1. 컨테이너에서 Context 객체 가져오기
        $context = app('context');

        // 2. 초기 데이터(잔액 등) 세팅
        // (클로저를 넣어두면, 나중에 뷰에서 부를 때 실행됨)

        $context->set('get_balance', function () {
            $member_id = session('member_id');
            if (!$member_id) return 0;

            // 여기서 라이브러리 로드 & DB 조회
            return library('point')->get_balance($member_id);
        });

        $context->set('get_balance_format', function () use ($context) {
            // 다른 context 값을 재사용할 수도 있음
            return number_format($context->get('get_balance'));
        });

        return $next($request);
    }
}
