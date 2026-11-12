<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Member_model extends Model
{
    protected $table  = 'members';
    protected $pk_key = 'id';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 특정 회원 정보 가져오기
     */
    public function get_member_id($id)
    {
        // $id가 숫자면 PK, 문자열이면 member_id
        $where = is_numeric($id) ? [$this->pk_key => $id] : ['member_id' => $id];
        return $this->get_where($this->table, $where)->row_array();
    }

    /**
     * 전체 회원 조회
     * @return array
     */
    public function get_member_all()
    {
        // admin 제외 조건 추가
        $this->where('member_id !=', 'admin');
        $this->order_by('created_at', 'DESC');
        $query  = $this->get($this->table);
        $result = $query->result_array();

        // 순번 추가
        $no = 1;
        foreach ($result as &$row) {
            $row['no']    = $no++;
            $row['phone'] = ! empty($row['phone']) ? $row['phone'] : '-'; // phone 체크
        }

        return $result;
    }

    /**
     * 마지막 로그인 시간 & IP 업데이트
     * @return bool
     */
    public function update_last_login()
    {
        $member_id = $this->session->userdata('member_id');
        if (! $member_id) {
            return false;
        }

        $data = [
            'last_login_ip' => $this->input->ip_address(),
            'last_login_at' => date("Y-m-d H:i:s"),
        ];

        return $this
            ->where('member_id', $member_id)
            ->update($this->table, $data);
    }

    /**
     * 신규 회원 등록
     * @return bool
     */
    public function insert_member()
    {

        $clean_phone = preg_replace('/\D/', '', $this->input->post('phone'));

        $data = [
            'member_id'     => strtolower($this->input->post('member_id')),
            'name'          => $this->input->post('name'),
            'phone'         => $clean_phone,
            'password'      => password_hash($this->input->post('password'), PASSWORD_DEFAULT),
            'level'         => 1,
            'last_login_ip' => $this->input->ip_address(),
        ];
        return $this->insert($this->table, $data);
    }

    /**
     * 회원정보 업데이트
     */
    public function update_member($member_id)
    {
        $member_id = $this->input->post('member_id', true);
        $name      = $this->input->post('name', true);
        $phone     = preg_replace('/\D/', '', $this->input->post('phone'));
        $password  = $this->input->post('password', true);

        $data = [
            'name'  => $name,
            'phone' => $phone,
        ];

        if (! empty($password)) {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        return $this->update($this->table, $data, ['member_id' => $member_id]);
    }

    /**
     * 아이디중복 검사
     */
    public function check_member_exists($username)
    {
        return $this->where('member_id', $username)->count_all_results($this->table) > 0;
    }

    /**
     * 이메일 중복 검사
     */
    public function check_email_exists($email)
    {
        return $this->where('email', $email)->count_all_results($this->table) > 0;
    }

}
