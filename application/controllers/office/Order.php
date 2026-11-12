<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Order extends Controller
{
    public function __construct()
    {
        parent::__construct();
        model(['member_model', 'product_model', 'mlm_model']);
        loginCheck();
    }

    public function index()
    {

        $this->list();
    }

    public function list()
    {

        $data['order'] = $this->mlm_model->get_order_list($this->session->get('member_id'));

        $data['header'] = ['title' => ORDER_LIST, 'group' => 'order'];
        view('office/order_list', $data);
    }

    /**
     * 주문 생성 처리
     */
    public function create()
    {


        // mlm_id 필수 확인
        $mlm_id = $this->input->post('mlm_id');
        if (empty($mlm_id)) {
            redirect('/office/product'); // 홈으로 이동
            exit;
        }

        // 주문 존재 확인
        if ($this->mlm_model->order_exists($this->input->post('mlm_id'))) {
            alert(ACCOUNT_NAME . '이미 등록된 주문 입니다', '/office/order');
        }

        $mlm = [
            'mlm_id'      => $this->input->post('mlm_id'),
            'sponsor_id'  => $this->input->post('sponsor_id'),
            'referrer_id' => $this->input->post('referrer_id'),
        ];

        $member_id = $this->session->get('member_id');
        $items     = $this->session->get('items');

        $recipient_info = [
            'name'    => $this->session->get('name'),
            'phone'   => $this->input->post('phone'),
            'address' => $this->input->post('pickup_location'),
        ];
        $payment_method = $this->input->post('payment_method') ?? 'cash';

        // 상품 없을 때
        if (!is_array($items) || empty($items)) {
            $data = ['success' => false, 'message' => '상품 정보가 없습니다.'];
            view('office/order_result', $data);
            return;
        }

        $order_id = $this->mlm_model->create_order($member_id, $items, $recipient_info, $mlm, $payment_method);

        // 모델에서 에러 메시지 배열을 리턴한 경우
        if (is_array($order_id) && isset($order_id['error'])) {
            $data = ['success' => false, 'message' => $order_id['error']];
            view('office/order_result', $data);
            return;
        }

        //주문 성공
        if ($order_id) {

            $order   = $this->db->get_where('orders', ['order_id' => $order_id])->row_array();
            $product = $this->product_model->get_product_id($items[0]['product_id']);

            // 뷰페이지 전달용
            $data = [
                'success' => true,
                'order'   => [
                    'order_number'         => $order['order_number'],
                    'name'                 => $order['recipient_name'],
                    'phone'                => $order['recipient_phone'],
                    'mlm_id'               => $member_id,
                    'pickup_location_name' => $order['recipient_address'],
                    'final_price'          => $order['final_price'],
                    'payment_method'       => $order['payment_method'],
                    'payment_method_name'  => $order['payment_method'] === 'cash' ? '무통장입금' : '포인트결제',
                ],
                'product' => $product,
            ];
            view('office/order_result', $data);
            return;
        }

        //기타 실패
        $data = ['success' => false, 'message' => '주문 생성 실패'];
        view('office/order_result', $data);
    }

    public function checkout($order_id = null)
    {

        $data['checkout'] = $order_id;

        if (empty($order_id)) {
            show_error('잘못된 접근입니다.'); // null, 빈 문자열 등 모두 처리
        }

        $order_id = decrypt_id($order_id);

        // 단일 상품 가져오기
        $data['order'] = $this->mlm_model->get_order_item($order_id);

        $data['header'] = ['title' => '결제하기', 'group' => 'order'];
        view('office/order_checkout', $data);
    }

    public function cancel($id = null)
    {

        if (empty($id)) {
            show_error('잘못된 접근입니다.');
        }

        $data['page'] = $id;

        $data['header'] = ['title' => '취소하기', 'group' => 'order'];
        view('office/order_cancel', $data);
    }

    public function delete($order_id)
    {
        $this->mlm_model->delete_order($order_id);
    }
}
