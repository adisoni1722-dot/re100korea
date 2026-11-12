<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Check extends Controller
{

    public function __construct()
    {
        parent::__construct();
        loginCheck();

        //모델 로드
       model('mlm_model');
    }

    /**
     * 어카운트 아이디 중복 검사 (CI 검증 추가)
     */
    public function id()
    {
        header('Content-Type: application/json');

        // 1. 'mlm_id' 필드에 대한 검증 규칙 설정
            $this->form->set_rules('mlm_id', '어카운트 아이디', 'required|alpha_numeric');

        // 2. 검증 실행
            if ($this->form->run() == false) {
            // 3. 검증 실패 시: 에러 메시지를 JSON으로 응답
            $response = [
                'error'    => strip_tags(form_error('mlm_id')), // 생성된 에러 메시지
                'csrfHash' => $this->csrf->get_csrf_hash(),
            ];
        } else {
            // 4. 검증 성공 시: 기존 로직 실행
            $mlm_id       = $this->input->post('mlm_id');
            $is_duplicate = $this->mlm_model->check_mlm_exists($mlm_id);

            $response = [
                'isDuplicate' => $is_duplicate,
                'csrfHash'    => $this->csrf->get_csrf_hash(),
            ];
        }

        echo json_encode($response);
    }

    /**
     * 회원 존재 여부 확인 (CI 검증 추가)
     */
    public function find()
    {
        header('Content-Type: application/json');

        // 1. 'member_id' 필드에 대한 검증 규칙 설정
            $this->form->set_rules(
                'member_id',
                '회원 아이디',
               'required|min_length[3]'
            );

        // 2. 검증 실행
            if ($this->form->run() == false) {
            // 3. 검증 실패 시: 에러 메시지를 JSON으로 응답
            $response = [
                'error'    => strip_tags(form_error('member_id')),
                'csrfHash' => $this->csrf->get_csrf_hash(),
            ];
        } else {
            // 4. 검증 성공 시: 기존 로직 실행
            $member_id     = $this->input->post('member_id');
            $member_exists = $this->mlm_model->check_mlm_exists($member_id);

            $response = [
                'exists'   => $member_exists,
                'csrfHash' => $this->csrf->get_csrf_hash(),
            ];
        }

        echo json_encode($response);
    }
}
