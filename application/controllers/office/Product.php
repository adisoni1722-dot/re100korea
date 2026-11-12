<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Product extends Controller
{
    public function __construct()
    {
        parent::__construct();
       model(['member_model', 'product_model', 'mlm_model']);
    }

    public function index()
    {
        //상품정보 가져오기
        $data['product'] = $this->product_model->get_product_all('dealer');

        $data['header'] = ['title' => ORDER, 'group' => 'product'];
        view('office/product_list', $data);
    }

    /**
     * 주문 생성 폼 보기
     */
    public function form($id = null)
    {
        loginCheck();

        $decrypt_id = decrypt_id($id);

        if ($decrypt_id === false) {
            show_error('잘못된 접근입니다.');
        }

        // PRODUCT_ID
        $product_id = $decrypt_id;

        // 값이 없으면 에러 처리
        if (empty($product_id)) {
            $data = ['success' => false, 'message' => '상품 정보가 전달되지 않았습니다.'];
            view('office/order_result', $data);
            return;
        }

        //Account 생성
        $data['new_id'] = $this->mlm_model->create_mlm_id($this->session->get('member_id'));

        //회원정보 에서 연락처 가져옴
        $member        = $this->db->get_where('members', ['member_id' => $this->session->get('member_id')])->row_array();
        $data['phone'] = format_phone($member['phone']);

        //ITEM_ID
        $data['product'] = $this->product_model->get_product_id($product_id);

        // Product ID 배열인지 확인
        if (! is_array($product_id)) {
            $product_id = [$product_id];
        }

        // items 배열 생성
        $items = [];
        foreach ($product_id as $index => $pid) {
            $items[$index] = [
                'product_id' => $pid,
                'quantity'   => 1, // 기본 수량 1
            ];
        }

        // items 을 세션에 담기
        $this->session->set_userdata('items', $items);

        $data['header'] = ['title' => ORDER, 'group' => 'order'];
        view('office/order_form', $data);
    }

}
