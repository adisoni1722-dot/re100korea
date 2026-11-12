<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Point {
    protected $db;

    public function __construct() {

        $this->db = app('db');

    }

    /** 회원 존재 여부 확인 */
    private function member_exists($member_id) {
        if (empty($member_id)) {
            return false;
        }

        $query = $this->db->get_where('members', ['member_id' => $member_id]);
        return $query->num_rows() > 0;
    }

    /** 잔액 조회 (단순 조회) */
    public function get_balance($member_id) {
        if (!$this->member_exists($member_id)) {
            return 0;
        }

        $sql = "
            SELECT COALESCE(SUM(
                CASE
                    WHEN receiver_id = ? AND status='completed' THEN amount
                    WHEN sender_id = ? AND status='completed' THEN -(amount + fee)
                    ELSE 0
                END
            ),0) AS balance
            FROM points
        ";
        $query = $this->db->query($sql, [$member_id, $member_id]);
        return $query->row_array()['balance'];
    }

    /** 잔액 조회 (트랜잭션 내, 락 O) */
    private function get_balance_for_update($member_id) {
        $sql = "
            SELECT COALESCE(SUM(
                CASE
                    WHEN receiver_id = ? AND status='completed' THEN amount
                    WHEN sender_id = ? AND status='completed' THEN -(amount + fee)
                    ELSE 0
                END
            ),0) AS balance
            FROM points
            FOR UPDATE
        ";
        $query = $this->db->query($sql, [$member_id, $member_id]);
        return $query->row_array()['balance'];
    }

    /** 시스템 입금 */
    public function deposit($receiver_id, $amount, $pay_method = 'points', $message = null, $source_name = null) {
        if (!$this->member_exists($receiver_id)) {
            return ['success' => false, 'message' => '입금 대상 회원이 존재하지 않습니다.'];
        }

        if ($amount <= 0) {
            return ['success' => false, 'message' => '입금 금액은 0보다 커야 합니다.'];
        }

        $this->db->trans_start();

        $data = [
            'sender_id'    => 'system',
            'receiver_id'  => $receiver_id,
            'type'         => 'earn',
            'amount'       => $amount,
            'fee'          => 0,
            'status'       => 'completed',
            'pay_method'   => $pay_method,
            'message'      => $message,
            'source_name'  => $source_name,
            'created_at'   => date('Y-m-d H:i:s'),
            'completed_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->insert('points', $data);
        $point_id = $this->db->insert_id();

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            return ['success' => false, 'message' => '입금 처리 실패'];
        }

        return ['success' => true, 'point_id' => $point_id];
    }

    /** 출금 (총액 차감, 한 줄 기록) */
    public function withdraw($sender_id, $amount, $fee_fixed = 0, $bank_name = null, $account_number = null, $account_holder = null, $message = null, $source_name = null) {
        if (!$this->member_exists($sender_id)) {
            return ['success' => false, 'message' => '출금 회원이 존재하지 않습니다.'];
        }

        if ($amount <= 0) {
            return ['success' => false, 'message' => '출금 금액은 0보다 커야 합니다.'];
        }

        $total_deduct = $amount + $fee_fixed;

        $this->db->trans_start();

        $balance = $this->get_balance_for_update($sender_id);
        if ($balance < $total_deduct) {
            $this->db->trans_complete();
            return ['success' => false, 'message' => '잔액 부족'];
        }

        $data = [
            'sender_id'       => $sender_id,
            'receiver_id'     => null,
            'type'            => 'withdraw',
            'amount'          => $amount, // 실제 인출 금액
            'fee'             => $fee_fixed, // 수수료
            'status'          => 'completed',
            'withdraw_status' => 'pending',
            'pay_method'      => 'bank',
            'bank_name'       => $bank_name,
            'account_number'  => $account_number,
            'account_holder'  => $account_holder,
            'message'         => $message,
            'source_name'     => $source_name,
            'created_at'      => date('Y-m-d H:i:s'),
            'completed_at'    => date('Y-m-d H:i:s'),
            'approved_at'     => date('Y-m-d H:i:s'),
        ];

        $this->db->insert('points', $data);
        $point_id = $this->db->insert_id();

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            return ['success' => false, 'message' => '출금 처리 실패'];
        }

        return [
            'success'        => true,
            'point_id'       => $point_id,
            'total_deducted' => $total_deduct,
            'fee'            => $fee_fixed,
        ];
    }

    /** 회원간 송금 (한 줄 기록, 실제 수신 금액) */
    public function transfer($sender_id, $receiver_id, $amount, $fee_fixed = 0, $message = null, $source_name = null) {
        $SYSTEM_ID = 'system';

        if ($sender_id !== $SYSTEM_ID && !$this->member_exists($sender_id)) {
            return ['success' => false, 'message' => '송금 회원이 존재하지 않습니다.'];
        }

        if (!$this->member_exists($receiver_id)) {
            return ['success' => false, 'message' => $receiver_id . '수신 회원이 존재하지 않습니다.'];
        }

        if ($sender_id == $receiver_id) {
            return ['success' => false, 'message' => '자기 자신에게는 송금할 수 없습니다.'];
        }

        if ($amount <= 0) {
            return ['success' => false, 'message' => '송금 금액은 0보다 커야 합니다.'];
        }

        $total_deduct = $amount + $fee_fixed;

        $this->db->trans_start();

        $balance = $this->get_balance_for_update($sender_id);
        if ($balance < $total_deduct) {
            $this->db->trans_complete();
            return ['success' => false, 'message' => '잔액 부족'];
        }

        $data = [
            'sender_id'    => $sender_id,
            'receiver_id'  => $receiver_id,
            'type'         => 'transfer',
            'amount'       => $amount, // 실제 수신 금액
            'fee'          => $fee_fixed, // 수수료
            'status'       => 'completed',
            'pay_method'   => 'wallet',
            'message'      => $message,
            'source_name'  => $source_name,
            'created_at'   => date('Y-m-d H:i:s'),
            'completed_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->insert('points', $data);
        $point_id = $this->db->insert_id();

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            return ['success' => false, 'message' => '송금 처리 실패'];
        }

        return [
            'success'         => true,
            'point_id'        => $point_id,
            'total_deducted'  => $total_deduct,
            'fee'             => $fee_fixed,
            'received_amount' => $amount,
        ];
    }

    /** 누적 입금/출금 조회 */
    public function get_summary($member_id) {
        if (!$this->member_exists($member_id)) {
            return ['deposit' => 0, 'withdraw' => 0];
        }

        $sql = "
            SELECT
                COALESCE(SUM(CASE WHEN receiver_id = ? AND status='completed' THEN amount ELSE 0 END), 0) AS total_in,
                COALESCE(SUM(CASE WHEN sender_id = ? AND status='completed' THEN amount + fee ELSE 0 END), 0) AS total_out
            FROM points
        ";

        $query = $this->db->query($sql, [$member_id, $member_id]);
        $row   = $query->row_array();

        return [
            'deposit'  => $row['total_in'],
            'withdraw' => $row['total_out'],
        ];
    }

    public function get_tx_list(string $member_id): array {
        if (!$this->member_exists($member_id)) {
            return [];
        }

        // 1. 전체 잔고 구하기
        $final_balance = $this->get_balance($member_id);

        // 2. 모든 거래 조회 (LIMIT 제거)
        $sql = "
        SELECT *,
            CASE
                WHEN sender_id = ? THEN -(amount + fee)
                WHEN receiver_id = ? THEN amount
                ELSE 0
            END AS signed_amount
        FROM points
        WHERE sender_id = ? OR receiver_id = ?
        ORDER BY created_at DESC, point_id DESC
    ";

        $query = $this->db->query($sql, [
            $member_id, $member_id,
            $member_id, $member_id,
        ]);

        $tx_list = $query->result_array();

        // 3. 보너스(source_type='bonus') 날짜별 합산
        $bonus_by_date = [];
        $filtered_tx   = [];

        foreach ($tx_list as $tx) {
            // 보너스만 날짜별로 묶기
            if ($tx['source_type'] === 'bonus' && $tx['receiver_id'] === $member_id) {
                $dateKey = substr($tx['created_at'], 0, 10); // YYYY-MM-DD
                if (!isset($bonus_by_date[$dateKey])) {
                    $bonus_by_date[$dateKey] = [
                        'type'          => 'earn',
                        'source_type'   => 'bonus',
                        'source_name'   => '적립 보너스',
                        'message'       => '보너스 합산 적립',
                        'amount'        => 0,
                        'fee'           => 0,
                        'signed_amount' => 0,
                        'created_at'    => $dateKey . ' 00:00:00',
                        'sender_id'     => null,
                        'receiver_id'   => $member_id,
                    ];
                }
                $bonus_by_date[$dateKey]['amount'] += (int) $tx['amount'];
                $bonus_by_date[$dateKey]['signed_amount'] += (int) $tx['amount'];
            } else {
                $filtered_tx[] = $tx;
            }
        }

        // 날짜별 보너스 집계 추가
        foreach ($bonus_by_date as $bonus_tx) {
            $filtered_tx[] = $bonus_tx;
        }

        // 4. 최신순 정렬 (created_at 기준)
        usort($filtered_tx, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));

        // 5. 최신 거래부터 역산하여 잔고 계산
        $balance = $final_balance;
        foreach ($filtered_tx as &$tx) {
            // message 기본값 보정
            $tx['message'] = $tx['message'] ?? ($tx['source_type'] === 'bonus' ? '적립 보너스' : '');

            $signed        = (int) ($tx['signed_amount'] ?? 0);
            $tx['balance'] = $balance;
            $balance -= $signed;

            // 숫자 포맷
            $tx['signed_amount_format'] = number_format($signed);
            $tx['balance_format']       = number_format($tx['balance']);

            // 수수료 표시
            $tx['fee_text'] = ($tx['sender_id'] == $member_id && $tx['fee'] > 0)
            ? number_format($tx['fee'])
            : '';

            // 날짜/시간 포맷
            $dt         = new DateTime($tx['created_at']);
            $tx['date'] = $dt->format('m.d');
            $tx['time'] = $dt->format('H:i');

            // 거래 타입 한글 변환
            switch ($tx['type']) {
                case 'earn':
                    $tx['type_text'] = ($tx['source_type'] === 'bonus') ? '적립 보너스' : '적립';
                    break;
                case 'use':
                    $tx['type_text'] = '사용';
                    break;
                case 'transfer':
                    $tx['type_text'] = '이체';
                    break;
                case 'withdraw':
                    $tx['type_text'] = '출금';
                    break;
                default:
                    $tx['type_text'] = $tx['type'];
            }

            // 표시용 금액
            $tx['display_amount'] = '+ ' . number_format($tx['amount']);
        }

        // 최신순 반환
        return $filtered_tx;
    }

    /** 포인트 결제 (사용 차감) */
    public function use ($member_id, $order_number, $amount, $fee_fixed = 0, $message = null, $source_name = null, $receiver_id = null) {
        if (!$this->member_exists($member_id)) {
            return ['success' => false, 'message' => '회원이 존재하지 않습니다.'];
        }

        if ($amount <= 0) {
            return ['success' => false, 'message' => '결제 금액은 0보다 커야 합니다.'];
        }

        $total_deduct = $amount + $fee_fixed;

        $this->db->trans_start();

        $balance = $this->get_balance_for_update($member_id);
        if ($balance < $total_deduct) {
            $this->db->trans_complete();
            return ['success' => false, 'message' => '포인트 잔액이 부족합니다.'];
        }

        $data = [
            'sender_id'    => $member_id,
            'receiver_id'  => null,
            'order_number' => $order_number,
            'type'         => 'use', // 거래 타입
            'amount'       => $amount,
            'fee'          => $fee_fixed,
            'status'       => 'completed',
            'pay_method'   => 'point', // 결제 수단
            'message'      => $message,
            'source_name'  => $source_name,
            'created_at'   => date('Y-m-d H:i:s'),
            'completed_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->insert('points', $data);
        $point_id = $this->db->insert_id();

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            return ['success' => false, 'message' => '결제 처리 실패'];
        }

        return [
            'success'        => true,
            'point_id'       => $point_id,
            'total_deducted' => $total_deduct,
            'fee'            => $fee_fixed,
            'used_amount'    => $amount,
            'balance_after'  => $this->get_balance($member_id),
        ];
    }

}
