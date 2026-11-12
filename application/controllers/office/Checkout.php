<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Checkout extends Controller
{

    public function __construct()
    {
        parent::__construct();
        model(['member_model', 'product_model', 'mlm_model']);
        library('Point');
        loginCheck();
    }

    public function point($id = null)
    {

        $decrypt_id = decrypt_id($id);
        if ($decrypt_id === false) {
            show_error('잘못된 접근입니다.');
        }

        // 주문 정보 조회
        $order = $this->mlm_model->get_order_item($decrypt_id);
        if (! $order) {
            show_error('주문 정보를 찾을 수 없습니다.');
        }

        // 사용자 포인트 잔액
        $user_points = $this->point->get_balance($this->session->get('member_id')) ?? 0;

        // 결제금액
        $total_price = $order['final_price'] ?? 0;

        if ($user_points < $total_price) {
            alert('포인트가 부족합니다.', '/office/order');
            return;
        }

        // 결제
        $this->db->trans_start();
        $result = $this->point->use($this->session->get('member_id'), $order['order_number'], $total_price, 0, $order['product_name'] . ' 구매',  $order['mlm_id']);

        $success = false;
        if ($result['success']) {
            $update = $this->mlm_model->update_status($order['order_number'], 'paid');
            if ($update) {
                $success = true;
            }
        }
        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE || !$success) {
            alert('결제 처리 실패:', '/office/order');
        } else {
            alert('포인트 결제가 완료되었습니다.', '/office/order');
        }
    }
}
