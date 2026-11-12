<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Bonus extends Controller
{
    public function __construct()
    {
        parent::__construct();
        model(['member_model', 'mlm_model']);
        loginCheck();
    }

    public function index()
    {
        $this->list();
    }

    public function dual($id)
    {

        $data['id'] = $id;

        $data['header'] = ['title' => '듀얼뷰', 'group' => 'main'];
        view('office/dual_view', $data, false);
    }

    public function list($mlm_id = null)
    {


        $decrypt_id = decrypt_id($mlm_id);
        $isMine     = false;

        // 관리자(level 1)는 항상 허용
        if ($this->session->get('level') === 10) {
            $isMine = true;
        } else {
            $row = $this->db->select('member_id')
                ->where('mlm_id', $decrypt_id)
                ->get('mlm_tree')
                ->row();

            // 일반회원은 본인 소유만 true
            if ($row && $row->member_id == $this->session->get('member_id')) {
                $isMine = true;
            }
        }

        // 결과
        if ($isMine) {


            // 회원 정보 가져오기
            $data['mlm'] = $this->mlm_model->get_mlm_id($decrypt_id);

            // 요약 (좌/우 인원수, 볼륨합계)
            $data['summary'] = $this->mlm_model->get_side_summary($decrypt_id);

            // 거래 내역 가져오기
            $data['bonus'] = $this->mlm_model->get_bonus_list($decrypt_id);

            $data['header'] = ['title' => '보너스', 'group' => 'point'];
            view('office/bonus_list', $data, false);
        } else {

            echo "❌ 접근 불가 (다른 회원의 정보는 볼수 없습니다)";
        }
    }

    public function tansfer()
    {
        $encrypt_id = $this->input->post('tans_id');

        // 폼검증
        $this->form->set_rules('tans_id', 'tans_id', 'trim|required|min_length[4]');

        if ($this->form->run() == false) {
            // [수정] ajax_fail -> $this->response->ajax (실패)
            return $this->response->ajax(
                false,
                '요청을 확인하세요.',
                ['errors' => $this->form->error_array()],
                422
            );
        } else {

            //복호화 검증
            $decrypt_id = decrypt_id($encrypt_id);

            // 거래 잔고
            $item   = $this->mlm_model->get_bonus_item($decrypt_id);
            $amount = $item['total_balance'];

            $point = library('point');

            $this->db->trans_start(); // 트랜잭션 시작

            // 1. transfer 갱신
            $this->db
                ->set('transfer', 1)
                ->where('mlm_id', $decrypt_id)
                ->where('status', 'completed')
                ->where('transfer', 0)
                ->update('mlm_bonus');

            $updated = $this->db->affected_rows(); // 변경된 행 수

            if ($updated > 0) {
                // 2. 실제 포인트 입금 처리
                $transfer_result = $this->point->deposit(
                    $this->session->get('member_id'),
                    $amount,
                    'point',
                    '수당 전환',
                    '지갑 전송'
                );

                if (isset($transfer_result['success']) && $transfer_result['success'] === true) {
                    $this->db->trans_complete(); // 정상 종료 (commit)

                    return $this->response->ajax(
                        true,
                        '포인트 전송 완료',
                        [
                            'from_balance' => 1000,
                            'to_balance'   => 1200,
                            'reload'       => true,
                        ],
                        200
                    );
                } else {
                    // deposit 실패 시 롤백
                    $this->db->trans_rollback();


                    return $this->response->ajax(
                        false,
                        $transfer_result['message'] ?? '포인트 전송 실패',
                        ['errors' => $this->form->error_array()],
                        422
                    );
                }
            } else {
                // 갱신된 행이 없을 경우
                $this->db->trans_complete();

                // [수정] ajax_fail -> $this->response->ajax (실패)
                return $this->response->ajax(
                    false,
                    '업데이트된 내역이 없습니다.',
                    [],
                    404
                );
            }
        }
    }
}
