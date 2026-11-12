<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Closing extends Controller
{
    public function __construct()
    {
        parent::__construct();
        helper('closing');
        admin_logincheck();
    }

    public function list()
    {

        $data['closing'] = model('mlm_closing')->get_closing_all();

        $data['header'] = ['title' => '마감 리스트', 'group' => 'order'];
        view('admin/closing_list', $data);
    }

    public function view($id)
    {

        $data['closing'] = model('mlm_closing')->get_closing_item($id);

        // 뷰 전송용 이벤트 ID 설정
        $data['event_id'] = $data['closing']['event_id'] ?? '';

        //회원별 보너스
        $data['member'] = model('mlm_closing')->get_closing_list_member($id);
        $data['header'] = ['title' => '마감 상세보기', 'group' => 'order'];
        view('admin/closing_view', $data);
    }

    public function bonus($mlm_id)
    {

        $mlm_id = decrypt_id($mlm_id);

        $data['bonus'] = model('mlm')->get_bonus_list($mlm_id);

        $data['header'] = ['title' => '보너스', 'group' => 'point'];
        view('/office/bonus_list', $data, false);
    }

    public function save()
    {
        if ($this->input->post()) {

            // DB 컬럼 목록
            $data = get_table('mlm_closing');

            // 필수값 검증
            $this->form->set_rules('event_id', 'event_id', 'required');

            if ($this->form->run() === true) {


                // 타겟 ID 확인 (수정 모드 판별용)
                $target_id = $this->input->post('event_id');

                // 저장 실행 (save_table 활용)
                $save_id = save_table('mlm_closing', $data, 'event_id', $target_id);

                if ($save_id) {
                    alert("저장이 완료 되었습니다", '/admin/closing/view/' . $save_id);
                } else {
                    echo "저장 실패";
                }
            } else {
                echo $this->form->all_errors();
            }
        }
    }

    public function wait()
    {
        // 마지막 마감 시간
        $data['lastday'] = model('mlm_closing')->last_closing_day();
        $data['no_layout'] = true; // 레이아웃 사용 여부

        $data['header'] = ['title' => '마감하기', 'group' => 'main'];
        view('admin/closing_wait', $data);
    }

    public function run()
    {

        // [추가] DB 트랜잭션 시작
        $this->db->trans_start();

        $start_all = microtime(true); // 전체 실행 시작 시점 기록

        // 마감 이벤트 넘버
        $event_id = generate_id(); // 헬퍼 함수

        // 마감 날짜 받아오기
        $start = model('mlm_closing')->last_closing_day();
        $end   = $this->input->post('end_day');


        preload_volume_state();
        get_mlm_cache();

        // 마감 리스트 가져오기
        $list = model('mlm_closing')->get_closing_order_list($start, $end);


        // 후원 구조 검증 실행 (후원인이 등록날짜 문제를 찾는다)
        $check_list = model('mlm_closing')->closing_check_list();
        $invalids = check_line($check_list);

        if (!empty($invalids)) {
            echo "<pre>후원 구조 오류 발견됨:\n";
            print_r($invalids);
            echo "</pre>";
            exit;
        } else {
            echo "모든 후원 관계가 정상입니다.";
        }

        // 마감 테이블 인서트
        model('mlm_closing')->insert_closing($event_id, $start, $end);

        $count_members = 0;
        $total_income  = 0; // 총수입 합계
        $total_bonus   = 0; // 지급수당 합계 (헬퍼 return 값으로 누적)

        // 수당 지급 처리
        foreach ($list as $item) {
            $count_members++;

            $uplines     = get_upline($item['mlm_id']); // 헬퍼 (캐시 사용)
            $ref_id      = $item['mlm_id'];
            $price       = (int) $item['final_price'];
            $referrer_id = $item['referrer_id'] ?? null;

            // 총수입 누적
            $total_income += $price;

            // 볼륨 적립
            add_volume($event_id, $ref_id, $price, $uplines, $item['created_at']);

            //추천 수당
            $bonus_referral = referral_bonus($event_id, $ref_id, $referrer_id, $price);

            // 롤업수당
            $bonus_rollup = rollup_bonus($event_id, $ref_id, $price, $uplines);

            // 소실적
            $bonus_so = SO_bonus($event_id, $ref_id, $price, $uplines);

            //반환된 보너스 총액 누적
            $total_bonus += $bonus_rollup;
            $total_bonus += $bonus_so;
            $total_bonus += $bonus_referral;
        }

        // 루프 종료 후, 버퍼에 남은 모든 데이터를 DB에 저장
        flush_all_batches(app('db'));

        // 마감 테이블에 총수입/총수당 업데이트
        model('mlm_closing')->update_totals($event_id, $total_income, $total_bonus);

        //DB 트랜잭션 종료
        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            echo "<b>마감 처리 중 심각한 오류가 발생하여 모든 작업이 취소(롤백)되었습니다.</b>";
            log_message('error', 'Closing run failed. Transaction rolled back. EventID: ' . $event_id);
            return;
        }

        // 전체 실행 시간
        $elapsed       = round((microtime(true) - $start_all) * 1000, 2);
        $total_queries = count($this->db->queries ?? []);

        echo "<hr>";
        echo "<b>[처리된 주문 건수]</b> {$count_members}건<br>";
        echo "<b>[총수입]</b> " . number_format($total_income) . "원<br>";
        echo "<b>[총수당]</b> " . number_format($total_bonus) . "원<br>";
        // echo "<b>[총 DB 쿼리]</b> {$total_queries}건<br>";
        echo "<b>[총 실행시간]</b> {$elapsed} ms<br>";
        echo "<b>마감 완료</b> (Event ID: {$event_id})<br>";
        echo "<hr>";
    }

    public function delete($id)
    {
        $decrypt_id = decrypt_id($id);

        $this->db->trans_start();

        // 마감 이벤트 삭제
        $this->db->where('event_id', $decrypt_id);
        $this->db->delete('mlm_closing');

        $this->db->where('account_number', $decrypt_id);
        $this->db->delete('points');

        // 볼륨 로그도 삭제
        $this->db->where('event_id', $decrypt_id);
        $this->db->delete('mlm_volume');

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            alert("삭제 처리 중 오류가 발생하여 롤백되었습니다.", '/admin/closing/list');
        } else {
            redirect('/admin/closing/list');
        }
    }
}
