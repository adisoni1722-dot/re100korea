<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Member extends Controller
{
    public function __construct()
    {
        parent::__construct();
        // 공통 모델 로드
        model('member_model');
    }

    /**
     * 로그인
     */
    public function login()
    {
        // 1. 이미 로그인 상태면 리다이렉트
        if ($this->session->get('is_login')) {
            redirect($this->session->get('return_url') ?? '/');
        }

        $data['header'] = ['title' => '로그인', 'group' => 'member'];

        // 2. 폼 검증 규칙
        $this->form->set_rules('member_id', '회원 아이디', 'required|min_length[4]|max_length[30]');
        $this->form->set_rules('password', '회원 암호', 'required');

        // 3. 검증 실패 (또는 첫 진입) 시 뷰 로드 후 종료
        if ($this->form->run() === false) {
            referer_save(); // 이전 URL 저장
            view('member/login', $data);
            return;
        }


        $member = $this->member_model->get_member_id(strtolower($this->input->post('member_id')));

        // 4. 비활성 계정 체크
        if (isset($member['status']) && strtolower($member['status']) === 'inactive') {
            alert('비활성화된 계정입니다. 관리자에게 문의하세요.');
            return;
        }

        // 5. 로그인 검증 실패 처리
        if (! $member || ! password_verify($this->input->post('password'), $member['password'])) {
            $fail_count = ($this->session->get('login_fail_count') ?? 0) + 1;
            $this->session->set('login_fail_count', $fail_count);

            $data['error'] = ($fail_count >= 5)
                ? '로그인 여러번 실패! 잠시 후 다시 시도 하세요'
                : '아이디 또는 비밀번호가 잘못되었습니다.';

            view('member/login', $data);
            return;
        }

        // 6. 로그인 성공 처리
        $this->session->remove('login_fail_count');
        set_login_session($member);

        // 7. 로그인 후 이동할 URL 가져오기 및 세션에서 제거
        $return_url = $this->session->get('return_url');
        $this->session->remove('return_url');

        // 8. 마지막 로그인 시간 업데이트 및 리다이렉트
        $this->member_model->update_last_login();
        redirect($return_url);
    }

    /**
     * 로그아웃
     */
    public function logout()
    {
        clear_login_session();
        redirect('member/login');
    }

    /**
     * 회원가입
     */
    public function register()
    {
        // 1. 이미 로그인 상태면 리다이렉트
        if ($this->session->get('is_login')) {
            redirect($this->session->get('return_url') ?? '/');
        }

        $data['header'] = ['title' => '회원가입', 'group' => 'member'];

        // 2. 폼 검증 규칙
        $this->form->set_rules('member_id', '아이디', 'required|alpha_numeric|min_length[4]');
        $this->form->set_rules('name', '이름', 'required|korean|min_length[2]');
        $this->form->set_rules('password', '패스워드', 'required|min_length[6]');

        // 3. 검증 실패 (또는 첫 진입) 시 뷰 로드 후 종료
        if ($this->form->run() === false) {
            referer_save($this->input, $this->session); // 이전 URL 저장
            view('member/register', $data);
            return;
        }


        // 4. 중복 회원 체크
        if ($this->member_model->check_member_exists($this->input->post('member_id'))) {
            alert("이미 존재 하는 회원 입니다.", "/");
            return;
        }

        // 5. 회원 등록 및 세션 처리
        $this->member_model->insert_member();
        $member = $this->member_model->get_member_id($this->input->post('member_id'));

        if (! $member) {
            alert("오류가 발생 하였습니다");
            return;
        }

        set_login_session($member);
        alert("회원 가입이 완료 되었습니다", '/');

    }

    /**
     * 회원정보 수정
     */
    public function update()
    {
        loginCheck();

        $member_id = $this->session->get('member_id');
        $data['header'] = ['title' => '회원정보 수정', 'group' => 'member'];
        $data['member'] = $this->member_model->get_member_id($member_id);

        // 폼 검증 규칙
        $this->form->set_rules('name', '이름', 'required|korean|min_length[2]');
        if ($this->input->post('password')) {
            $this->form->set_rules('password', '패스워드', 'required|min_length[6]');
        }

        // 검증 실패 시 뷰 로드 후 종료
        if ($this->form->run() === false) {
            view('member/update', $data);
            return;
        }

        // 성공 처리
        $this->member_model->update_member($member_id);
        echo "정보가 수정 되었습니다";
    }

    /**
     * 아이디 중복 체크
     */
    public function checkId()
    {
        $username = $this->input->post('member_id', true);
        $exists   = $this->member_model->check_member_exists($username);

        $this->response->ajax(
            true,
            '중복 확인 완료',
            ['exists' => $exists]
        );
    }
}
