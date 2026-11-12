<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Member extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model(['member_model', 'mlm_model']);
        admin_logincheck();
    }

    public function list()
    {

        //전체 회원
        $data['member'] = $this->member_model->get_member_all();
        $data['header'] = ['title' => '회원 리스트', 'group' => 'member'];
        view('admin/member_list', $data);
    }

    public function view($id = null)
    {

        // 회원 정보 가져오기
        $data['member'] = $this->member_model->get_member_id($id);

        // 뷰파일 전송 ID 설정
        $data['id'] = $data['member']['id'] ?? '';

        // 주문리스트 가져오기
        $data['order']  = $this->mlm_model->get_order_list($data['member']['member_id']);

        $data['header'] = ['title' => '회원정보 수정', 'group' => 'member'];
        view('admin/member_view', $data);
    }

    public function save()
    {
        if ($this->input->post()) {


            // 필수값 검증
            $this->form->set_rules('id', 'id', 'required');

            if ($this->form->run() === true) {


                // 테이블 데이터 가져오기
                $data = get_table('members');

                // 타겟 ID 확인 (수정 모드 판별용)
                $target_id = $this->input->post('id');

                // 저장 실행 (save_table 활용)
                $save_id = save_table('members', $data, 'id', $target_id);

                if ($save_id) {
                    alert("저장이 완료 되었습니다", '/admin/member/view/' . $save_id);
                } else {
                    echo "저장 실패";
                }
            } else {
                echo validation_errors();
            }
        }
    }

    public function reset_password()
    {
        $member_id = $this->input->post('id', true);

        if (empty($member_id)) {
            echo json_encode(['status' => 'error', 'message' => '회원 ID가 없습니다.']);
            return;
        }
        // DB 업데이트
        $data = [
            'password' => password_hash('12345678', PASSWORD_DEFAULT),
        ];

        // ✅ CSRF 자동 첨부됨
        $this->response->ajax(
            true,
            '비밀번호가 초기화되었습니다.'
        );
    }

    public function delete($member_id)
    {

        // 1. order 테이블에 기록 있는지 확인
        $order_exists = $this->db
            ->from('orders')
            ->where('member_id', $member_id)
            ->count_all_results() > 0;

        if ($order_exists) {
            alert("삭제불가:: " . ACCOUNT_NAME . " 주문에 기록이 있습니다");
        }

        // 2. point 테이블에 기록 있는지 확인
        $point_exists = $this->db
            ->from('points')
            ->where('receiver_id', $member_id)
            ->count_all_results() > 0;

        if ($point_exists) {
            alert("삭제불가:: 잔여 포인트 기록이 존재 합니다");
        }

        // 3. 트랜잭션 시작
        $this->db->trans_start();

        // 4. 회원 삭제
        $this->db->where('member_id', $member_id);
        $this->db->delete('members');

        $this->db->trans_complete();

        redirect('/admin/member/list');
    }

    public function access()
    {
        $this->db->select('log.*, m.name');
        $this->db->from('member_access_log AS log');
        $this->db->join('members AS m', 'm.member_id = log.member_id', 'left');
        $this->db->order_by('log.created_at', 'DESC');
        $query       = $this->db->get();
        $result      = $query->result_array();


        // 브라우저 이름 추출
        foreach ($result as &$row) {
            $row['browser'] = detect_browser_os($row['user_agent']);
            $row['uri'] = short_end($row['uri'], 30);
        }
        $data['log'] = $result;
        $data['header'] = ['title' => '접속기록', 'group' => 'member'];
        view('admin/member_access', $data);
    }
}
