<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * MLM 보너스 & 볼륨 처리 (DI 버전)
 * - DB 접근: app('db') 사용
 * - 전역 버퍼/상태: $GLOBALS 유지
 * - [수정] SO_bonus 버그 수정
 * - [수정] 보너스 함수가 총액을 return 하도록 변경
 * - [수정] add_volume L/R 배정 로직 안정화
 * - [수정] 매칭 보너스 지급 시에도 수령자(upline)의 status 체크
 */

/* ====================================================== */
/* ID 생성                                                */
/* ====================================================== */
function generate_id(): string {
    return sprintf(
        'C%s%s-%04d',
        substr(date('Y'), -1),
        date('m'),
        mt_rand(0, 9999)
    );
}

/* ====================================================== */
/* MLM 트리 캐시 (sponsor 관계 + 회원 정보 포함)          */
/* - 한 번만 로드 (static 캐시)                           */
/* - 필요 시 $db 주입 가능                                */
/* ====================================================== */
function get_mlm_cache($db = null, bool $force_refresh = false): array {
    static $tree = null, $parent_map = null, $member_info = null;

    // 강제 초기화 모드 지원
    if ($force_refresh) {
        $tree = $parent_map = $member_info = null;
    }

    if ($tree !== null) {
        return [$tree, $parent_map, $member_info];
    }

    $db = $db ?? app('db');

    $rows = $db
        ->select('m.*, o.status AS order_status')
        ->from('mlm_tree AS m')
        ->join('orders AS o', 'm.order_number = o.order_number', 'left')
        ->order_by('o.created_at', 'asc')
        ->get()
        ->result_array();

    $tree        = [];
    $parent_map  = [];
    $member_info = [];

    foreach ($rows as $r) {
        $tree[$r['sponsor_id']][]  = $r['mlm_id'];
        $parent_map[$r['mlm_id']]  = $r['sponsor_id'];
        $member_info[$r['mlm_id']] = [
            'mlm_id'    => $r['mlm_id'],
            'member_id' => $r['member_id'],
            'name'      => $r['name'],
            'status'    => $r['order_status'], // 'pending' 등 주문 상태
        ];
    }

    return [$tree, $parent_map, $member_info];
}

/* ====================================================== */
/* 상위 n단계 upline 구하기                               */
/* ====================================================== */
function get_upline(string $mlm_id, ?int $top_n = null): array {
    static $parent_map = null, $member_info = null;

    if ($parent_map === null) {
        [, $parent_map, $member_info] = get_mlm_cache();
    }

    $uplines = [];
    $current = $mlm_id;

    while (isset($parent_map[$current])) {
        $sponsor = $parent_map[$current];
        if (empty($sponsor)) {
            break;
        }

        $uplines[] = $member_info[$sponsor] ?? ['mlm_id' => $sponsor];
        $current   = $sponsor;
    }

    return $top_n ? array_slice($uplines, 0, $top_n) : $uplines;
}

/* ====================================================== */
/* 전역 버퍼/상태                                         */
/* ====================================================== */
$GLOBALS['_POINT_BUFFER']  = [];
$GLOBALS['_VOLUME_BUFFER'] = []; // DB 삽입 로그 버퍼
$GLOBALS['_VOLUME_STATE']  = []; // [mlm_id] => ['L'=>0,'R'=>0]

/**
 * 기존 볼륨 상태 메모리 로드 (스크립트 시작 시 1회 호출 권장)
 */
function preload_volume_state($db = null): void {
    $db                       = $db ?? app('db');
    $GLOBALS['_VOLUME_STATE'] = [];

    $rows = $db->query("
        SELECT
            mlm_id,
            SUM(CASE WHEN position = 'L' THEN volume ELSE 0 END) AS left_total,
            SUM(CASE WHEN position = 'R' THEN volume ELSE 0 END) AS right_total
        FROM mlm_volume
        GROUP BY mlm_id
    ")->result_array();

    foreach ($rows as $row) {
        $GLOBALS['_VOLUME_STATE'][$row['mlm_id']] = [
            'L' => (int) ($row['left_total'] ?? 0),
            'R' => (int) ($row['right_total'] ?? 0),
        ];
    }
}

/* ====================================================== */
/* 포인트 배치 버퍼                                       */
/* ====================================================== */
function add_point($event_id, $member_id, $mlm_id, $ref_id, $amount, $source_type, $message, $db = null): void {
    $buf = &$GLOBALS['_POINT_BUFFER'];

    if ($amount <= 0) {
        return;
    }

    $buf[] = [
        'sender_id'      => 'system',
        'receiver_id'    => $member_id,
        'type'           => 'earn',
        'amount'         => $amount,
        'status'         => 'completed',
        'pay_method'     => 'point',
        'source_type'    => 'bonus',
        'message'        => $message,
        'source_name'    => $mlm_id,
        'account_number' => $event_id,
        'account_holder' => $ref_id,
    ];

    if (count($buf) >= 500) {
        $db = $db ?? app('db');
        $db->insert_batch('points', $buf);
        $buf = [];
    }
}

function flush_point_batch($db = null): void {
    $db  = $db ?? app('db');
    $buf = &$GLOBALS['_POINT_BUFFER'];
    if (!empty($buf)) {
        $db->insert_batch('points', $buf);
        $buf = [];
    }
}

/* ====================================================== */
/* 볼륨 배치 버퍼                                         */
/* ====================================================== */
function add_volume($event_id, $ref_id, $amount, $uplines, $time, $db = null): void {
    [$tree] = get_mlm_cache();

    $buf   = &$GLOBALS['_VOLUME_BUFFER'];
    $state = &$GLOBALS['_VOLUME_STATE'];

    if (!isset($buf) || !is_array($buf)) {
        $buf = [];
    }

    if (!isset($state) || !is_array($state)) {
        $state = [];
    }

    $db = $db ?? app('db');

    $current_id = $ref_id;

    foreach ($uplines as $upline) {
        $upline_id = is_array($upline) ? ($upline['mlm_id'] ?? null) : $upline;
        if (empty($upline_id)) {
            continue;
        }

        $children = $tree[$upline_id] ?? [];
        if (!is_array($children)) {
            $children = [];
        }

        // [수정] L/R 배정 로직 안정화 (첫번째 자식=L, 그 외=R)
        $idx = array_search($current_id, $children, true);
        $pos = ($idx === 0) ? 'L' : 'R';

        if (!isset($state[$upline_id])) {
            $state[$upline_id] = ['L' => 0, 'R' => 0];
        }
        $state[$upline_id][$pos] += $amount;

        $buf[] = [
            'event_id'   => $event_id,
            'mlm_id'     => $upline_id,
            'ref_id'     => $ref_id,
            'position'   => $pos,
            'volume'     => $amount,
            'type'       => 'sale',
            'created_at' => $time,
        ];

        $current_id = $upline_id;

        if (count($buf) >= 1000) {
            $db->insert_batch('mlm_volume', $buf);
            $buf = [];
        }
    }
}

function flush_volume_batch($db = null): void {
    $db  = $db ?? app('db');
    $buf = &$GLOBALS['_VOLUME_BUFFER'];
    if (!empty($buf)) {
        $db->insert_batch('mlm_volume', $buf);
        $buf = [];
    }
}

/* ====================================================== */
/* 롤업 보너스                                            */
/* ====================================================== */
function rollup_bonus($event_id, $ref_id, $amount, array $uplines, $db = null): float {
    $total_bonus_percent = 10;
    $top_n               = 10;
    $source_type         = 'rollup';
    $source_name         = '롤업 보너스';
    $total_bonus_paid    = 0.0; // [추가] 지급 총액 추적

    if (empty($uplines)) {
        return $total_bonus_paid;
    }

    $receivers   = array_slice($uplines, 0, $top_n);
    $total_bonus = $amount * $total_bonus_percent / 100;
    $per_person  = ($top_n > 0) ? ($total_bonus / $top_n) : 0;

    if ($per_person <= 0) {
        return $total_bonus_paid;
    }

    foreach ($receivers as $upline) {
        $mlm_id    = $upline['mlm_id'] ?? null;
        $member_id = $upline['member_id'] ?? null;
        $status    = $upline['status'] ?? null;
        if (empty($mlm_id)) {
            continue;
        }

        // [수정] 주문 상태가 'pending' (결제대기 등)이거나 null 이면 스킵
        if ($status === 'pending' || $status === null) {
            continue;
        }

        add_point(
            $event_id,
            $member_id,
            $mlm_id,
            $ref_id,
            $per_person,
            $source_type,
            $source_name,
            $db
        );

        $total_bonus_paid += $per_person; // [추가]
    }

    return $total_bonus_paid; // [추가]
}

/* ====================================================== */
/* 소실적 + 매칭 보너스                                   */
/* ====================================================== */
function SO_bonus($event_id, $ref_id, $amount, $uplines, $db = null): float {
    $db               = $db ?? app('db');
    $state            = &$GLOBALS['_VOLUME_STATE'];
    $source_type      = 'volume';
    $total_bonus_paid = 0.0; // [추가] 지급 총액 추적

    if (empty($uplines)) {
        return $total_bonus_paid;
    }

    $volume_log = [];

    foreach ($uplines as $upline) {
        $mlm_id    = $upline['mlm_id'] ?? null;
        $member_id = $upline['member_id'] ?? null;
        $status    = $upline['status'] ?? null;
        if (empty($mlm_id) || !isset($state[$mlm_id])) {
            continue;
        }

        $left  = $state[$mlm_id]['L'] ?? 0;
        $right = $state[$mlm_id]['R'] ?? 0;
        if ($left <= 0 || $right <= 0) {
            continue;
        }

        $matched = min($left, $right);

        $percent = 30;
        // [심각한 버그 수정] $amount(주문액) -> $matched(매칭볼륨)
        $bonus = $matched * ($percent / 100);

        if ($bonus <= 0) {
            continue;
        }

        // [수정] 소실적 수령자 상태 체크
        if ($status === 'pending' || $status === null) {
            continue;
        }

        // 소실적 지급
        add_point($event_id, $member_id, $mlm_id, $ref_id, $bonus, $source_type, '소실적 30% 보너스', $db);
        $total_bonus_paid += $bonus; // [추가]

        // 볼륨 차감 (이월)
        $state[$mlm_id]['L'] -= $matched;
        $state[$mlm_id]['R'] -= $matched;

        // 차감 로그
        $now          = date('Y-m-d H:i:s');
        $volume_log[] = [
            'event_id' => $event_id, 'mlm_id'   => $mlm_id,
            'position' => 'L', 'volume'         => -$matched, 'type' => 'match',
            'ref_id'   => $ref_id, 'created_at' => $now,
        ];
        $volume_log[] = [
            'event_id' => $event_id, 'mlm_id'   => $mlm_id,
            'position' => 'R', 'volume'         => -$matched, 'type' => 'match',
            'ref_id'   => $ref_id, 'created_at' => $now,
        ];

        // 매칭 보너스 (1~2대)
        // get_upline은 캐시를 사용하므로 DB 부담 없음
        $uppers = get_upline($mlm_id, 2);

        // [수정] 매칭 1대 수령자 상태 체크 추가
        if (!empty($uppers[0])) {
            $upper_1_status = $uppers[0]['status'] ?? null;
            if ($upper_1_status !== 'pending' && $upper_1_status !== null) {
                $matching_1 = round($bonus * 0.2, 2);
                if ($matching_1 > 0) {
                    add_point($event_id, $uppers[0]['member_id'], $uppers[0]['mlm_id'], $ref_id, $matching_1, 'matching', "매칭 1대 ($mlm_id)", $db);
                    $total_bonus_paid += $matching_1; // [추가]
                }
            }
        }

        // [수정] 매칭 2대 수령자 상태 체크 추가
        if (!empty($uppers[1])) {
            $upper_2_status = $uppers[1]['status'] ?? null;
            if ($upper_2_status !== 'pending' && $upper_2_status !== null) {
                $matching_2 = round($bonus * 0.1, 2);
                if ($matching_2 > 0) {
                    add_point($event_id, $uppers[1]['member_id'], $uppers[1]['mlm_id'], $ref_id, $matching_2, 'matching', "매칭 2대 ($mlm_id)", $db);
                    $total_bonus_paid += $matching_2; // [추가]
                }
            }
        }
    }

    if (!empty($volume_log)) {
        // insert_batch는 버퍼가 아니므로 즉시 실행
        $db->insert_batch('mlm_volume', $volume_log);
    }

    return $total_bonus_paid; // [추가]
}

function referral_bonus($event_id, $ref_id, $referrer_id, $amount, $db = null): float {
    // [설정] 추천수당 비율 (10% -> 10)
    $referral_percent = 10;
    $source_type      = 'referral';
    $source_name      = '추천 보너스';
    $total_bonus_paid = 0.0;

    if (empty($referrer_id)) {
        return $total_bonus_paid;
    }

    $bonus = $amount * $referral_percent / 100;

    if ($bonus <= 0) {
        return $total_bonus_paid;
    }

    // 캐시에서 추천인 정보 가져오기 (DB 쿼리 X)
    // get_mlm_cache()는 static 캐시를 반환하므로 성능 저하 없음
    [, , $member_info] = get_mlm_cache();

    $referrer_info = $member_info[$referrer_id] ?? null;

    if (empty($referrer_info)) {
        // 추천인이 캐시에 없음 (탈퇴했거나 비활성 회원)
        return $total_bonus_paid;
    }

    $member_id = $referrer_info['member_id'] ?? null;
    $status    = $referrer_info['status'] ?? null;

    // 추천인의 상태 체크
    if ($status === 'pending' || $status === null) {
        return $total_bonus_paid;
    }

    add_point(
        $event_id,
        $member_id, // 추천인의 member_id
        $referrer_id, // 추천인의 mlm_id
        $ref_id, // 매출자의 mlm_id
        $bonus,
        $source_type,
        $source_name,
        $db
    );

    $total_bonus_paid += $bonus;

    return $total_bonus_paid;
}

/* ====================================================== */
/* 모든 배치 flush                                        */
/* ====================================================== */
function flush_all_batches($db = null): void {
    flush_point_batch($db);
    flush_volume_batch($db);
}

defined('BASEPATH') or exit('No direct script access allowed');


/**
 * -------------------------------------------------------------
 * MLM 후원 구조 통합 검증 헬퍼
 * -------------------------------------------------------------
 * get_closing_order_list() 결과 기반으로 MLM 구조의
 * 무결성, 순환, 시간, 포지션 등 종합 검증을 수행한다.
 *
 * @param array $list  MLM 회원 리스트 (mlm_id, sponsor_id, created_at, position 등 포함)
 * @return array [
 *     'self_sponsor'   => [...],  // 자기 자신 후원
 *     'missing_sponsor'=> [...],  // 존재하지 않는 후원인
 *     'duplicate_mlm'  => [...],  // 중복 MLM ID
 *     'cycle'          => [...],  // 순환 구조
 *     'date_reverse'   => [...],  // 후원인보다 빠른 등록
 *     'future_date'    => [...],  // 미래 시점 가입
 * ]
 */
if (!function_exists('check_line')) {
    function check_line(array $list): array {
        $members = [];
        $errors  = [
            'self_sponsor'    => [],
            'missing_sponsor' => [],
            'duplicate_mlm'   => [],
            'cycle'           => [],
            'date_reverse'    => [],
            'future_date'     => [],
        ];

        // 1) 회원 기본정보 맵핑
        foreach ($list as $row) {
            if (!isset($row['mlm_id'])) {
                continue;
            }

            $mlm_id = trim($row['mlm_id']);

            if (isset($members[$mlm_id])) {
                // 중복 MLM ID
                $errors['duplicate_mlm'][] = $mlm_id;
                continue;
            }

            $members[$mlm_id] = [
                'sponsor_id' => $row['sponsor_id'] ?? null,
                'created_at' => isset($row['created_at']) ? strtotime($row['created_at']) : null,
                'position'   => $row['position'] ?? null,
            ];
        }

        // 2) 검증 루프
        foreach ($members as $mlm_id => $info) {
            $sponsor_id = $info['sponsor_id'];
            $created_at = $info['created_at'];
            $position   = $info['position'];

            // 2-1) 자기 자신 후원
            if ($mlm_id && $sponsor_id && $mlm_id === $sponsor_id) {
                $errors['self_sponsor'][] = $mlm_id;
            }

            // 2-2) 존재하지 않는 후원인
            if (
                !empty($sponsor_id)
                && $sponsor_id !== 'admin'
                && !isset($members[$sponsor_id])
            ) {
                $errors['missing_sponsor'][] = [
                    'mlm_id'     => $mlm_id,
                    'sponsor_id' => $sponsor_id,
                ];
            }

            // 2-3) 등록일 역전 (후원인보다 더 빨리 가입)
            if (!empty($sponsor_id) && isset($members[$sponsor_id])) {
                $sponsor_join = $members[$sponsor_id]['created_at'];
                if ($sponsor_join !== null && $created_at !== null && $sponsor_join > $created_at) {
                    $errors['date_reverse'][] = [
                        'mlm_id'       => $mlm_id,
                        'sponsor_id'   => $sponsor_id,
                        'member_join'  => date('Y-m-d H:i:s', $created_at),
                        'sponsor_join' => date('Y-m-d H:i:s', $sponsor_join),
                    ];
                }
            }

            // 2-4) 미래 시점 가입
            if ($created_at !== null && $created_at > time()) {
                $errors['future_date'][] = [
                    'mlm_id'     => $mlm_id,
                    'created_at' => date('Y-m-d H:i:s', $created_at),
                ];
            }
        }

        // 3) 순환 구조 탐지 (DFS)
        $visited  = [];
        $recStack = [];

        $detectCycle = function ($node) use (&$detectCycle, &$members, &$visited, &$recStack, &$errors) {
            if (!isset($members[$node])) {
                return false;
            }

            if (!empty($recStack[$node])) {
                $errors['cycle'][] = $node;
                return true;
            }
            if (!empty($visited[$node])) {
                return false;
            }

            $visited[$node]  = true;
            $recStack[$node] = true;

            $sponsor = $members[$node]['sponsor_id'] ?? null;
            if ($sponsor && isset($members[$sponsor])) {
                $detectCycle($sponsor);
            }

            unset($recStack[$node]);
            return false;
        };

        foreach (array_keys($members) as $node) {
            $detectCycle($node);
        }


        // 4) 오류 없는 항목은 제거
        return array_filter($errors, fn($arr) => !empty($arr));
    }
}
