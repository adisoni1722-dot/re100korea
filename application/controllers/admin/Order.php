<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Order extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model(['mlm_model']);
        admin_logincheck();
    }

    public function list()
    {
        // 전체 매출 리스트 가져오기
        $data['order'] = $this->mlm_model->get_order_list();

        $data['header'] = ['title' => '매출 리스트', 'group' => 'order'];
        view('admin/order_list', $data);
    }

    public function view($id)
    {
        $data['order']  = $this->mlm_model->get_order_item($id);

        // 뷰파일 전송 ID 설정
        $data['order_number'] = $data['order']['order_number'] ?? '';

        $data['header'] = ['title' => '매출 상세보기', 'group' => 'order'];
        view('admin/order_view', $data);
    }

    public function save()
    {
        if ($this->input->post()) {

            // 테이블 데이터 가져오기
            $data = get_table('orders');

            // 필수값 검증
            $this->form->set_rules('order_number', 'order_number', 'required');

            if ($this->form->run() === true) {


                // 타겟 ID 확인 (수정 모드 판별용)
                $target_id = $this->input->post('order_number');

                // 저장 실행 (save_table 활용)
                $save_id = save_table('orders', $data, 'order_number', $target_id);

                if ($save_id) {

                    alert("저장이 완료 되었습니다", '/admin/order/view/' . $save_id);
                } else {
                    echo "저장 실패";
                }
            } else {
                echo $this->form->all_errors();
            }
        }
    }

    public function delete($order_number)
    {
        $this->mlm_model->delete_order($order_number);
    }
}
