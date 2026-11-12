<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Start extends Controller {
    public function __construct() {
        parent::__construct();
        $this->load->model(['stats_model']);
        $this->ensure_admin_account();
        admin_logincheck();

    }

    public function index() {

        // 포인트 합계 (단위 000 제외)
        $data['total_point'] = floor($this->stats_model->total_point() / 1000);

        // 충매출금
        $data['total_bonus'] = floor($this->stats_model->total_point());

        // 유통 포인트
        $data['total_point_progress'] = ($data['total_point'] / 10000000) * 100;

        // 총 인출
        $data['total_withdraw'] = 0;

        // 총 매출액
        $data['total_in'] = floor($this->stats_model->total_in());

        // 총 회원수
        $data['total_members'] = $this->stats_model->total_members();

        // 월간 매출 데이터
        $data['monthly_data'] = $this->stats_model->get_monthly_sales_data();

        // 매출 비율
        $data['monthly_sales_percent'] = $this->stats_model->get_monthly_sales_percent();

        $data['header'] = ['title' => '대시보드', 'group' => 'main'];
        view('admin/start', $data);
    }

    //관리자가 없다면 생성
    public function ensure_admin_account() {

        // 관리자 암호
        $hash = password_hash('123456', PASSWORD_DEFAULT);

        $query = $this->db->get_where('members', ['member_id' => 'admin']);
        if ($query->num_rows() == 0) {
            $data = [
                'id'        => 1,
                'member_id' => 'admin',
                'email'     => 'admin@admin.com',
                'password'  => $hash,
                'name'      => '관리자',

            ];
            $this->db->insert('members', $data);
        }
    }

}
