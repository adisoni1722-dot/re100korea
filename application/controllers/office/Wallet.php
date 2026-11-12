<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Wallet extends Controller
{
    public function __construct()
    {
        parent::__construct();
        model(['member_model']);
        $this->load->library('Point');
        loginCheck();
    }

    public function index()
    {
        $this->home();
    }

    public function home()
    {

        //누적 수신 and 누적 송신
        $data['summary'] = $this->point->get_summary($this->session->get('member_id'));

        //최근 이용 내역
        $data['tx_list'] = $this->point->get_tx_list($this->session->get('member_id'), 10);

        $data['header'] = ['title' => '마이월렛', 'group' => 'main'];

        view('office/wallet', $data);
    }

    public function qrcode($id = null) // QR 데이터로 사용할 ID 받기

    {
        library('ciqrcode');

        // $decrypt_id  정상적인 접근인지 확인용만
        $decrypt_id = decrypt_id($id);

        if ($decrypt_id === false) {
            show_error('잘못된 접근입니다.');
        }

        // QR코드 저장 폴더 경로
        $folder_path = FCPATH . 'data/qrcode/';
        if (!is_dir($folder_path)) {
            mkdir($folder_path, 0755, true);
        }

        // QR코드 이미지 파일명 (ID 기반)
        $filename  = ($id ? 'qrcode_' . $id : 'default') . '.png';
        $file_path = $folder_path . $filename;

        // QR코드에 담을 URL
        $qr_data = base_url('office/wallet/transfer/' . $id);


        if (file_exists($file_path)) {
            unlink($file_path);
        }


        // QR코드 생성
        $params['data']     = $qr_data;
        $params['level']    = 'H';
        $params['size']     = 10;
        $params['savename'] = $file_path;

        $this->ciqrcode->generate($params);


        // Base64로 변환하여 뷰에 전달
        $imageData      = base64_encode(file_get_contents($file_path));
        $data['qrcode'] = 'data:image/png;base64,' . $imageData;

        //뷰에 전달할 아이디
        $data['my_addr'] = $id;

        view('office/wallet_qrcode', $data, false);
    }

    public function transfer($id = null)
    {
        // 최근 이용 내역
        $data['tx_list'] = $this->point->get_tx_list($this->session->get('member_id'), 3);

        // $id가 없으면 빈 문자열 처리
        $safe_id            = $id ?? '';
        $data['action_url'] = '/office/wallet/transfer/' . urlencode($safe_id);

        // 암호화 ID인지 확인
        $decrypted_id = decrypt_id($id, 'pass');

        // 수신자 ID 결정
        if ($decrypted_id !== false) {
            $recipient_id = $decrypted_id;
        } else {
            // 평문 member_id인 경우 POST로 전달받음
            $recipient_id = $this->input->post('receiver_id', true);
        }

        // view 용 데이터
        $data['receiver_id'] = $id;
        $data['header']      = ['title' => '이체하기', 'group' => 'transfer'];

        // 폼 검증 규칙
        $this->form->set_rules('receiver_id', '받는 사람 ID', 'required|trim');
        $this->form->set_rules('amount', '보낼 포인트', 'required|integer|greater_than[0]');

        if ($this->form->run() === false) {
            // 검증 실패 시 폼 다시 보여주기
            view('office/wallet_transfer', $data);
            return;
        }

        // 검증 통과 후 처리
        $sender_id = $this->session->get('member_id');
        $amount    = (int) $this->input->post('amount', true);

        $transfer_result = (array) $this->point->transfer(
            $sender_id,
            $recipient_id,
            $amount,
            FEE,
            sprintf('%s → %s', $sender_id, $recipient_id),
            '지갑 전송'
        );

        // 안전한 결과 해석
        $success  = (bool) ($transfer_result['success'] ?? false);
        $message  = $transfer_result['message'] ?? ($success ? '포인트가 성공적으로 전송되었습니다.' : '이체에 실패했습니다.');
        $redirect = $success ? '/office/wallet' : null;

        // AJAX 응답
        $this->response->ajax($success, $message, ['redirect_url' => $redirect]);
    }


    public function bank()
    {
        $this->form->set_rules('bank_name', '입금 은행', 'required');
        $this->form->set_rules('account_holder', '예금주명', 'required|min_length[2]|max_length[50]');
        $this->form->set_rules('account_number', '계좌번호', 'required|min_length[6]|max_length[50]');
        $this->form->set_rules('contact_number', '연락처', 'required|min_length[8]|max_length[20]');
        $this->form->set_rules('amount', '인출 금액', 'required|integer|greater_than[9999]');

        if ($this->form->run() === false) {
            view('office/wallet_bank');
            return;
        }

        // 검증 통과 후 DB 처리
        $data = [
            'bank_name' => $this->input->post('bank_name'),
            'account_holder' => $this->input->post('account_holder'),
            'account_number' => $this->input->post('account_number'),
            'contact_number' => $this->input->post('contact_number'),
            'amount' => (int)$this->input->post('amount'),
        ];



        // 성공 시 redirect
        redirect('/wallet/complete');
    }
}
