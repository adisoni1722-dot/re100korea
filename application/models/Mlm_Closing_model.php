<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Closing_model extends Model {

    public function last_closing_day() {
        $this->select_max('end_day');
        $query  = $this->get('mlm_closing');
        $result = $query->row_array();

        if ($result && !empty($result['end_day'])) {
            return $result['end_day'];
        }
        //마감일이 하나도 없다면 2025년 1월 1일로
        return '2025-01-01 00:00:00';

    }


    // 마감 테이블에 추가
    public function insert_closing($event_id, $start, $end) {

        $data = [
            'event_id'   => $event_id,
            'start_day'  => $start,
            'end_day'    => $end,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        return $this->insert('mlm_closing', $data);

    }



    // 마감 기간 주문 리스트 가져오기
    public function closing_check_list() {
        $query = $this->select([
                'o.order_id',
                'o.member_id',
                'o.order_number',
                'o.final_price',
                'o.created_at',
                'mt.mlm_id',
                'mt.sponsor_id',
                'mt.referrer_id',
                'mt.depth',
            ])
            ->join('mlm_tree mt', 'mt.order_number = o.order_number', 'inner')
            ->order_by('o.created_at', 'ASC')
            ->get('orders o');

        return $query->result_array();

    }

    // 마감 기간 주문 리스트 가져오기
    public function get_closing_order_list($start, $end) {
        $query = $this->select([
                'o.order_id',
                'o.member_id',
                'o.order_number',
                'o.final_price',
                'o.created_at',
                'mt.mlm_id',
                'mt.sponsor_id',
                'mt.referrer_id',
                'mt.depth',
            ])
            ->join('mlm_tree mt', 'mt.order_number = o.order_number', 'inner')
            ->where('o.status !=', 'pending')
            ->where('o.created_at >', $start)
            ->where('o.created_at <=', $end)
            ->order_by('o.created_at', 'ASC')
            ->get('orders o');

        return $query->result_array();

    }

    public function get_closing_all() {

        $this->order_by('created_at', 'DESC');
        $query  = $this->get('mlm_closing');
        $result = $query->result_array();

        foreach ($result as &$row) {
            // status 한글 라벨 지정
            if (!empty($row['status']) && $row['status'] === 'pending') {
                $row['status_label']  = '미검수';
                $row['status_active'] = '';
            } else {
                $row['status_label']  = '검수확인';
                $row['status_active'] = 'active';
            }

        }
        unset($row);

        return $result;
    }

    // 마감 상세보기
    public function get_closing_item($event_id) {
        $query = $this->where('event_id', $event_id)
            ->get('mlm_closing');

        return $query->row_array();
    }

    // 마감 상세보기 에서  계정별 보너스 합계
    public function get_closing_list_member($event_id) {
        $query = $this->select('p.source_name AS mlm_id, p.receiver_id AS member_id, m.name, SUM(p.amount) AS total_amount')
            ->from('points AS p')
            ->join('members AS m', 'p.receiver_id = m.member_id', 'left')
            ->where('p.account_number', $event_id)
            ->where('p.source_type', 'bonus') // 💡 MLM 수당만 집계
            ->group_by(['p.source_name', 'p.receiver_id', 'm.name'])
            ->order_by('total_amount', 'DESC')
            ->get();

        return $query->result_array();
    }

    // 총 보너스 합계 계산 (points 테이블 기반)
    public function update_totals(string $event_id, float $total_income): bool {

        $row = $this->select_sum('amount', 'sum_bonus')
            ->where('account_number', $event_id)
            ->where('source_type', 'bonus')
            ->get('points')
            ->row();

        $total_bonus = (int) ($row->sum_bonus ?? 0);

        // 마감 테이블에 총합 업데이트
        return $this->set('total_income', $total_income)
            ->set('total_bonus', $total_bonus)
            ->where('event_id', $event_id)
            ->update('mlm_closing');
    }

}
