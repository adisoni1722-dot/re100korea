<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Dummy extends Controller
{
    private $total_members = 50;
    private $batch_size    = 100;

    // ✅ [추가] 롤백 시 삭제하지 않을 (보호할) 회원 ID 목록
    // 여기에 'admin' 외에 보호하고 싶은 다른 ID를 추가하세요.
    private $preserved_members = ['admin', 'adisoni', 'moonkr', 'moonkr-1', 'moonkr-2'];

    private $last_names    = ['김', '이', '박', '최', '정', '윤', '조', '한', '오', '서', '신', '권', '황', '안', '송', '류', '홍', '전', '유', '문'];
    private $first_names   = ['민준', '서연', '도윤', '예진', '하은', '지후', '하율', '수아', '태윤', '지우', '서준', '지민', '유진', '하윤', '은서', '예준', '서진', '가은', '윤호', '채원', '다은', '민서', '현우', '시우', '유나', '예서', '지안', '민재', '하린', '수현', '연우', '지윤', '지호', '서우', '준호', '지수', '지오', '시현', '다현', '서하', '주원', '윤서', '세은', '은우', '지원', '수호', '민아', '하율', '은채', '소율'];
    private $eng_prefix    = ['sky', 'neo', 'nova', 'meta', 'blun', 'zen', 'xen', 'mori', 'rion', 'vale', 'ad', 'core', 'vex', 'lex', 'glim', 'nex', 'kron', 'air', 'zeta', 'yona', 'vion', 'mir', 'taron', 'flux', 'pion', 'elix', 'aero', 'lun', 'digi', 'sora', 'tek', 'myra', 'clon', 'hyra', 'quon', 'fira', 'lyon', 'tron', 'cyra', 'viron'];
    private $eng_suffix    = ['lab', 'hub', 'soft', 'sys', 'corp', 'tech', 'base', 'zone', 'wave', 'flow', 'line', 'net', 'data', 'link', 'byte', 'gate', 'nix', 'bit', 'ware', 'code', 'gen', 'dev', 'spark', 'grid', 'loop', 'cube', 'box', 'beam', 'ring', 'mind', 'dash', 'pad', 'form', 'node', 'fox', 'plus', 'mark', 'win', 'verse', 'key', 'core', 'link', 'path', 'view', 'unit', 'point', 'cast', 'mode', 'spin', 'stack', 'shift', 'craft', 'field', 'pulse', 'quest'];
    private $email_domains = ['naver.com', 'gmail.com', 'hanmail.net', 'daum.net', 'nate.com'];

    public function index()
    {

        // ✅ 안정성 확보
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '1024M');
        ignore_user_abort(true);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        while (ob_get_level()) {
            ob_end_flush();
        }

        ob_implicit_flush(true);

        // ✅ 날짜 범위 파라미터
        $start_date = $_GET['start'] ?? '2025-09-10';
        $end_date   = $_GET['end'] ?? '2025-10-19';

        // ✅ [수정] 루트 ID를 파라미터로 받기 (기본값 'moonkr')
        $root_id = $_GET['root'] ?? 'moonkr';

        // 순차 증가 날짜 계산
        $start_timestamp   = strtotime($start_date);
        $end_timestamp     = strtotime($end_date);
        $total_time_range  = $end_timestamp - $start_timestamp;
        $total_intervals   = ($this->total_members > 1) ? ($this->total_members - 1) : 1;
        $time_increment    = $total_time_range / $total_intervals;
        $current_timestamp = $start_timestamp;

        echo "<h2>MLM 대용량 랜덤 트리 더미 생성 시작...</h2>";
        echo "<p>📅 범위: {$start_date} ~ {$end_date} (순차 등록)</p>";

        // ✅ [수정] 루트 ID의 현재 상태 (Depth, 자식 수) DB에서 조회
        $start_depth       = 0;
        $start_child_count = 0;

        if ($root_id === 'admin') {
            // 'admin'은 항상 1대(depth 1)로 간주
            $start_depth = 1;
            // 'admin'의 현재 자식 수 조회
            $count_data        = $this->db->query("SELECT COUNT(*) as c FROM mlm_tree WHERE sponsor_id = ?", [$root_id])->row_array();
            $start_child_count = $count_data ? (int) $count_data['c'] : 0;
        } else {
            // 'admin'이 아닌 경우, mlm_tree에서 ID와 depth를 조회
            $root_data = $this->db->query("SELECT depth FROM mlm_tree WHERE mlm_id = ?", [$root_id])->row_array();
            if (! $root_data) {
                // 루트 ID가 mlm_tree에 없으면 중단
                echo "<hr><b>❌ 오류: 지정된 루트 ID '{$root_id}'가 mlm_tree 테이블에 존재하지 않습니다.</b>";
                echo "<p>생성을 시작할 루트 ID가 보호 목록(preserved_members)에 있고, DB(member 및 mlm_tree)에 실제로 존재하는지 확인하세요.</p>";
                return;
            }
            $start_depth = (int) $root_data['depth'];

            // 해당 루트의 현재 자식 수 조회
            $count_data        = $this->db->query("SELECT COUNT(*) as c FROM mlm_tree WHERE sponsor_id = ?", [$root_id])->row_array();
            $start_child_count = $count_data ? (int) $count_data['c'] : 0;
        }

        echo "<p>🌳 루트: <b>{$root_id}</b> (현재 Depth: {$start_depth}, 현재 자식 수: {$start_child_count}명)</p>";

        // ✅ [추가] 마지막 주문번호를 조회하여 순차적으로 증가시킬 준비
        $last_order_row    = $this->db->query("SELECT MAX(CAST(order_number AS UNSIGNED)) as max_num FROM orders")->row_array();
        $last_order_number = $last_order_row && $last_order_row['max_num'] ? (int) $last_order_row['max_num'] : 0;
        echo "<p>📦 마지막 주문번호: {$last_order_number} (이 번호부터 +1하여 생성)</p>";

        $created  = 0;
        $used_ids = [];

        // 랜덤 트리 생성을 위한 상태 변수 (조회한 값으로 초기화)
        $sponsor_pool = [];
        // 현재 자식 수가 2명 미만일 때만 스폰서 풀에 추가
        if ($start_child_count < 2) {
            $sponsor_pool[$root_id] = true;
        }

        // 조회한 현재 자식 수와 Depth로 메모리 맵 초기화
        $child_counts = [$root_id => $start_child_count];
        $depth_map    = [$root_id => $start_depth];

        $this->db->query("SET FOREIGN_KEY_CHECKS=0");
        $this->db->query("START TRANSACTION");

        $member_buf = [];
        $order_buf  = [];

        while ($created + 1 < $this->total_members && ! empty($sponsor_pool)) {
            $sponsor = array_rand($sponsor_pool);

            $member_id            = $this->generate_unique_id($used_ids);
            $used_ids[$member_id] = true;

            $name   = $this->random_korean_name();
            $email  = $this->random_email($member_id);
            $mobile = $this->random_phone();

            // ✅ [수정] 주문번호 생성 로직 변경: uniqid() 대신 순차 증가 번호 사용
            $last_order_number++;
            $order_number = $last_order_number;

            $depth = $depth_map[$sponsor] + 1;

            $current_timestamp += $time_increment;
            $final_timestamp = (int) ($current_timestamp > $end_timestamp ? $end_timestamp : $current_timestamp);
            $created_at      = date('Y-m-d H:i:s', $final_timestamp);

            $password = password_hash('1234', PASSWORD_BCRYPT);

            // 랜덤 추천인 선택 (모든 기존 회원 대상)
            $all_members = array_keys($child_counts);
            $referrer_id = $all_members[array_rand($all_members)];

            $member_buf[] = "('$member_id','$email','$password','$name',1,0,NULL,'$mobile','$created_at','active')";

            $order_buf[] = [
                'member_id'    => $member_id,
                'order_number' => $order_number,
                'created_at'   => $created_at,
                'depth'        => $depth,
                'name'         => $name,
                'sponsor_id'   => $sponsor,
                'referrer_id'  => $referrer_id,
            ];

            // 스폰서 풀 및 상태 업데이트
            $child_counts[$sponsor]++;
            $sponsor_pool[$member_id] = true;
            $child_counts[$member_id] = 0;
            $depth_map[$member_id]    = $depth;

            if ($child_counts[$sponsor] >= 2) {
                unset($sponsor_pool[$sponsor]);
            }

            $created++;

            // 💾 100명마다 commit
            if ($created % $this->batch_size == 0) {
                $this->flush_batch($member_buf, $order_buf);
                $this->db->query("COMMIT");
                $this->db->query("START TRANSACTION");
                flush();
            }
        }

        // 남은 버퍼 처리
        $this->flush_batch($member_buf, $order_buf);
        $this->db->query("COMMIT");
        $this->db->query("SET FOREIGN_KEY_CHECKS=1");

        echo "<hr><b>총 {$created}명 랜덤 트리 더미 데이터 생성 완료!</b>";
    }

    private function flush_batch(&$member_buf, &$order_buf)
    {
        if (empty($member_buf)) {
            return;
        }

        // member
        $this->db->query("INSERT INTO members (member_id,email,password,name,level,type,gender,mobile,created_at,status)
                         VALUES " . implode(',', $member_buf));

        // orders
        $values = [];
        foreach ($order_buf as $o) {
            // [수정] orders 테이블에 type 컬럼 추가 및 값 99 설정
            $values[] = "('{$o['member_id']}','{$o['order_number']}',1500000,1500000,'paid','card','{$o['created_at']}',99)";
        }
        // [수정] INSERT 쿼리의 컬럼 목록에 type 추가
        $this->db->query("INSERT INTO orders (member_id,order_number,total_price,final_price,status,payment_method,created_at,type)
                         VALUES " . implode(',', $values));

        // mlm_tree
        $tree_values = [];
        foreach ($order_buf as $o) {
            $tree_values[] = "('{$o['member_id']}','{$o['member_id']}','{$o['name']}','{$o['sponsor_id']}','{$o['referrer_id']}',{$o['depth']},'{$o['order_number']}','active')";
        }

        $this->db->query("INSERT INTO mlm_tree (mlm_id,member_id,name,sponsor_id,referrer_id,depth,order_number,status)
                         VALUES " . implode(',', $tree_values));

        // 버퍼 초기화
        $member_buf = [];
        $order_buf  = [];
    }

    // ---------------------------------------------
    // 롤백
    // ---------------------------------------------
    public function rollback()
    {
        ini_set('max_execution_time', 0);
        ignore_user_abort(true);

        echo "<h3>⚠️ MLM 더미 데이터 삭제 시작...</h3>";

        // [수정] 보호 대상 목록을 화면에 출력
        echo "<p><strong>보호 대상: </strong>" . implode(', ', $this->preserved_members) . "</p>";

        // [수정] $preserved_members 배열을 기반으로 SQL의 IN(...) 구문에 사용할 플레이스홀더 생성
        $placeholders = rtrim(str_repeat('?,', count($this->preserved_members)), ',');

        $this->db->query("DELETE FROM orders WHERE member_id NOT IN ($placeholders)", $this->preserved_members);

        $this->db->query("DELETE FROM members WHERE member_id NOT IN ($placeholders)", $this->preserved_members);

        if ($this->db->trans_status()) {
            echo "<hr><b>💥 더미 데이터 (보호 대상 제외) 삭제 완료!</b>";
        } else {
            echo "<hr><b>❌ 삭제 중 오류 발생 (트랜잭션 롤백됨)</b>";
        }
    }

    // ---------------------------------------------
    // 헬퍼 함수들
    // ---------------------------------------------
    private function generate_unique_id(&$used)
    {
        do {
            $prefix = $this->eng_prefix[array_rand($this->eng_prefix)];
            $suffix = $this->eng_suffix[array_rand($this->eng_suffix)];
            $id     = strtolower($prefix . $suffix);

            // [수정] DB 조회 전, 이미 보호된 ID인지 확인
            if (in_array($id, $this->preserved_members)) {
                $exists = true;
                continue;
            }

            $exists = $this->db->query("SELECT 1 FROM members WHERE member_id = ?", [$id])->row_array();
        } while ($exists || isset($used[$id]));
        return $id;
    }

    private function random_korean_name()
    {
        return $this->last_names[array_rand($this->last_names)] . $this->first_names[array_rand($this->first_names)];
    }

    private function random_email($id)
    {
        return $id . '@' . $this->email_domains[array_rand($this->email_domains)];
    }

    private function random_phone()
    {
        return '010' . str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
    }
}
