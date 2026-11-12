<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Mlm_model extends Model
{

    protected $table            = 'mlm_tree';
    protected $pk_key           = 'id';
    protected $table_order      = 'orders';
    protected $table_order_item = 'orders_item';
    protected $table_product    = 'products';
    protected $table_closing    = 'mlm_closing';
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 특정 회원 정보 가져오기
     */
    public function get_mlm_id($id)
    {
        // $id가 숫자면 PK, 문자열이면 member_id
        $where = is_numeric($id) ? [$this->pk_key => $id] : ['mlm_id' => $id];
        return $this->get_where($this->table, $where)->row_array();
    }

    //자동 아이디-1, -2 생성
    public function create_mlm_id($member_id)
    {
        // 1) member_id 자체가 존재하는지 확인
        $this->where('mlm_id', $member_id);
        $query = $this->get('mlm_tree');
        if ($query->num_rows() === 0) {
            return $member_id; // 원본 ID 사용
        }

        // 2) 기존 숫자 중 최대값 가져오기 (-숫자 접미사)
        $sql    = "SELECT MAX(CAST(SUBSTRING_INDEX(mlm_id, '-', -1) AS UNSIGNED)) AS max_num FROM mlm_tree WHERE mlm_id LIKE ?;";
        $params = [$member_id . '-%'];

        $row        = $this->query($sql, $params)->row_array();
        $max_number = isset($row['max_num']) ? (int) $row['max_num'] : 0;

        // 3) 새 번호 생성
        $new_number = $max_number + 1;
        return $member_id . '-' . $new_number;
    }

    /**
     * 어카운트 중복검사
     */
    public function check_mlm_exists($username)
    {
        return $this->where('mlm_id', $username)->count_all_results($this->table) > 0;
    }

    public function check_sponsor_line_exists($username)
    {
        return $this->where('sponsor_id', $username)->count_all_results($this->table) > 0;
    }

    /**
     * 주문 생성
     */
    public function create_order($member_id, $items, $recipient_info, $mlm, $payment_method = 'cash')
    {
        // 상품 배열 확인
        if (!is_array($items) || empty($items)) {
            log_message('error', 'CREATE ORDER FAILED: Items array is invalid or empty.');
            return false;
        }

        /**
         * 트랜잭션과 관계없이 회원 연락처 업데이트
         */
        $db_phone = preg_replace('/\D/', '', $recipient_info['phone'] ?? '');
        $this->update('members', ['phone' => $db_phone], ['member_id' => $member_id]);

        $this->trans_start();

        // 1. 주문 번호 생성 및 잠금 (SELECT FOR UPDATE)
        $table_order_protected = Database::protect_identifier($this->table_order);
        $lock_query            = "SELECT order_number FROM {$table_order_protected} ORDER BY order_id DESC LIMIT 1 FOR UPDATE";

        $last_order_row = $this->query($lock_query)->row_array();

        $last_order_number = ((int) ($last_order_row['order_number'] ?? 10047700)) + 1;

        /**
         * 쇼핑 주문 정보 처리 시작
         */
        $total_price    = 0;
        $total_discount = 0;
        $delivery_fee   = 0;

        // 가격 계산 및 상품 존재 검증
        foreach ($items as $item) {
            $product = $this->get_where($this->table_product, ['product_id' => $item['product_id']])->row_array();

            if (!$product) {
                $this->_trans_failure(); // Model.php의 헬퍼를 사용하여 실패 상태 설정
                log_message('error', 'CREATE ORDER FAILED: Product ID not found: ' . $item['product_id']);
                break;
            }

            $price = $product['discount_price'] ?? $product['price'];
            $total_price += $price * $item['quantity'];
            $total_discount += ($product['price'] - $price) * $item['quantity'];
        }

        // 상품 검증 실패로 trans_status가 false가 되었다면 바로 트랜잭션 종료
        if ($this->trans_status() === false) {
            log_message('error', 'CREATE ORDER FAILED: Trans status is FALSE after product verification.');
            $this->trans_complete();
            return false;
        }

        $final_price = $total_price + $delivery_fee;

        // 주문 저장
        $order_data = [
            'member_id'         => $member_id,
            'order_number'      => $last_order_number,
            'total_price'       => $total_price,
            'total_discount'    => $total_discount,
            'delivery_fee'      => $delivery_fee,
            'final_price'       => $final_price,
            'recipient_name'    => $recipient_info['name'] ?? '',
            'recipient_address' => $recipient_info['address'] ?? '',
            'recipient_phone'   => $db_phone,
            'payment_method'    => $payment_method,
            'status'            => 'pending',
            'type'              => 1,
        ];
        $insert_success = $this->insert($this->table_order, $order_data);
        $order_id       = $this->insert_id();

        //1: 주문 테이블 삽입 결과 확인
        // log_message('debug', 'DB INSERT SUCCESS: ' . ($insert_success ? 'TRUE' : 'FALSE') . ' | Last Query: ' . $this->last_query());
        // log_message('debug', 'ORDER ID AFTER INSERT: ' . $order_id . ' | Trans Status: ' . ($this->trans_status() ? 'TRUE' : 'FALSE'));

        // order_id가 없거나 삽입 자체에서 오류가 났다면 중단
        if ($order_id === 0 || !$insert_success || $this->trans_status() === false) {
            // log_message('error', 'CREATE ORDER FAILED: Order insertion or ID retrieval failed. ID: ' . $order_id);
            $this->_trans_failure(); // 혹시 모를 상황 대비 재설정
            $this->trans_complete();
            return false;
        }

        // 주문 상품 등록
        foreach ($items as $item) {
            // 이미 위에 트랜잭션 상태를 확인했으므로, 이 루프 내에서 DB 오류가 나면 자동으로 trans_status가 false가 됨
            $product = $this->get_where($this->table_product, ['product_id' => $item['product_id']])->row_array();
            $price   = $product['discount_price'] ?? $product['price'];

            $order_item_data = [
                'order_number'   => $last_order_number,
                'product_id'     => $item['product_id'],
                'product_name'   => $product['name'],
                'product_price'  => $product['price'],
                'discount_price' => $product['discount_price'],
                'quantity'       => $item['quantity'],
                'opt1'           => $item['opt1'] ?? null,
                'opt2'           => $item['opt2'] ?? null,
                'opt3'           => $item['opt3'] ?? null,
                'save_point'     => $product['save_point'] * $item['quantity'],
                'status'         => 'active',
            ];
            $this->insert($this->table_order_item, $order_item_data);
        }

        /**
         * MLM 로직 시작
         */
        if ($this->trans_status() === true) {
            // 1) 부모 sponsor_id 정보 조회
            $parent = $this->get_where('mlm_tree', ['mlm_id' => $mlm['sponsor_id']])->row_array();

            // 2) 부모 자식 수 검증 및 잠금
            $mlm_tree_protected = Database::protect_identifier('mlm_tree');
            $mlm_lock_query     = "SELECT COUNT(*) AS count FROM {$mlm_tree_protected} WHERE sponsor_id = ? FOR UPDATE";
            $child_row          = $this->query($mlm_lock_query, [$mlm['sponsor_id']])->row_array();
            $child_count        = (int) ($child_row['count'] ?? 0);

            if ($child_count >= 2) {
                $this->_trans_failure(); // 실패 상태 설정
                // log_message('error', 'CREATE ORDER FAILED: MLM child limit exceeded for sponsor ' . $mlm['sponsor_id']);
                $this->trans_complete();
                return [
                    'error' => $mlm['sponsor_id'] . ' 스폰서 하위에는 이미 2명이 존재합니다.',
                ];
            }

            // 3) 부모 sponsor_id depth 계산
            $new_depth = isset($parent['depth']) ? $parent['depth'] + 1 : 1;

            // 4) mlm 인서트
            $mlm_data = [
                'mlm_id'       => $mlm['mlm_id'],
                'member_id'    => $member_id,
                'name'         => $this->session->userdata('name'),
                'sponsor_id'   => $mlm['sponsor_id'],
                'referrer_id'  => $mlm['referrer_id'],
                'order_number' => $last_order_number,
                'depth'        => $new_depth,
            ];
            $this->insert('mlm_tree', $mlm_data);
        }
        // MLM 로직 종료

        // 전체 트랜잭션 완료. status가 false라면 rollback, true라면 commit.
        $this->trans_complete();

        // 🌟 디버깅 포인트 2: 최종 트랜잭션 상태 확인
        if ($this->trans_status() === false) {
            // log_message('error', 'CREATE ORDER FAILED: Final transaction status is FALSE. Order ID: ' . $order_id);
            return false;
        }

        // log_message('info', 'CREATE ORDER SUCCESS: Order ID ' . $order_id . ' created successfully.');
        return $order_id;
    }

    // 단일상품을 가져온다
    public function get_order_item($order_number)
    {
        $this->select('
        m.mlm_id,
        m.name,
        m.member_id,
        m.sponsor_id,
        m.referrer_id,
        o.order_number,
        o.total_price,
        o.total_discount,
        o.delivery_fee,
        o.final_price,
        o.status,
        o.type,
        o.recipient_name,
        o.recipient_phone,
        o.recipient_address,
        o.payment_method,
        o.tracking_number,
        o.created_at,
        oi.product_id,
        oi.product_name,
        oi.product_price,
        oi.discount_price
    ')
            ->from('mlm_tree m')
            ->join('orders o', 'm.order_number = o.order_number', 'inner')
            ->join('orders_item oi', 'o.order_number = oi.order_number', 'inner')
            ->where('o.order_number', $order_number)
            ->limit(1);

        $row = $this->get()->row_array();

        if ($row) {
            $row['product_price_format']  = number_format((int) ($row['product_price'] ?? 0), 0, '.', ',');
            $row['total_price_format']    = number_format((int) ($row['total_price'] ?? 0), 0, '.', ',');
            $row['total_discount_format'] = number_format((int) ($row['total_discount'] ?? 0), 0, '.', ',');
            $row['delivery_fee_format']   = number_format((int) ($row['delivery_fee'] ?? 0), 0, '.', ',');
            $row['final_price_format']    = number_format((int) ($row['final_price'] ?? 0), 0, '.', ',');
            $row['discount_price_format'] = number_format((int) ($row['discount_price'] ?? 0), 0, '.', ',');
        }

        return $row;
    }
    // 주문 상품 가져오기 order + oder_item + mlm_tree
    public function get_order_list($member_id = null)
    {
        $this->select('
        m.mlm_id,
        m.name,
        m.member_id,
        m.sponsor_id,
        m.referrer_id,
        o.order_number,
        o.total_price,
        o.total_discount,
        o.delivery_fee,
        o.final_price,
        o.status,
        o.type,
        o.recipient_name,
        o.recipient_phone,
        o.recipient_address,
        o.payment_method,
        o.tracking_number,
        o.created_at,
        oi.product_id,
        oi.product_name,
        oi.product_price,
        oi.discount_price
    ')
            ->from('mlm_tree m')
            ->join('orders o', 'm.order_number = o.order_number', 'inner')
            ->join('orders_item oi', 'o.order_number = oi.order_number', 'inner');

        if ($member_id) {
            $this->where('m.member_id', $member_id);
        }

        $this->order_by('o.created_at', 'DESC');

        $result = $this->get()->result_array();

        // ✅ status 한글 변환
        foreach ($result as &$row) {
            switch ($row['status']) {
                case 'pending':
                    $row['status_text'] = '결제대기';
                    break;
                case 'paid':
                    $row['status_text'] = '결제완료';
                    break;
                case 'shipped':
                    $row['status_text'] = '배송중';
                    break;
                case 'delivered':
                    $row['status_text'] = '배송완료';
                    break;
                case 'completed':
                    $row['status_text'] = '구매확정';
                    break;
                case 'cancelled':
                    $row['status_text'] = '취소';
                    break;
                case 'refunded':
                    $row['status_text'] = '환불';
                    break;
                default:
                    $row['status_text'] = $row['status'];
            }
        }

        // ✅ 포맷 및 번호 추가
        $no = 1;
        foreach ($result as &$row) {
            $row['no']                    = $no++;
            $row['total_price_format']    = number_format((int) ($row['total_price'] ?? 0));
            $row['total_discount_format'] = number_format((int) ($row['total_discount'] ?? 0));
            $row['delivery_fee_format']   = number_format((int) ($row['delivery_fee'] ?? 0));
            $row['final_price_format']    = number_format((int) ($row['final_price'] ?? 0));
            $row['product_price_format']  = number_format((int) ($row['product_price'] ?? 0));
            $row['discount_price_format'] = number_format((int) ($row['discount_price'] ?? 0));
        }
        unset($row);

        return $result;
    }

    /**
     * 지정된 ID의 하위 계보(후원 또는 추천)를 재귀적으로 조회합니다.
     *
     * @param string|int $id   조회를 시작할 최상위 회원 ID
     * @param string     $type 계보 타입 ('sponsor' 또는 'referrer')
     * @return array
     */
    public function my_line($id = null, $type = 'sponsor')
    {
        // [1] 안전한 JOIN 컬럼 결정
        $join_column = ($type === 'referrer') ? 'referrer_id' : 'sponsor_id';

        // [2] 쿼리
        $sql = "
    WITH RECURSIVE recursive_line AS (
        SELECT
            t.mlm_id,
            t.name,
            t.referrer_id,
            t.sponsor_id,
            t.depth,
            o.created_at AS order_created_at,
            o.status AS order_status
        FROM mlm_tree t
        LEFT JOIN orders o ON t.order_number = o.order_number
        WHERE t.mlm_id = ?

        UNION ALL

        SELECT
            m.mlm_id,
            m.name,
            m.referrer_id,
            m.sponsor_id,
            m.depth,
            o.created_at AS order_created_at,
            o.status AS order_status
        FROM mlm_tree m
        INNER JOIN recursive_line s ON m.{$join_column} = s.mlm_id
        LEFT JOIN orders o ON m.order_number = o.order_number
    )
    SELECT *
    FROM recursive_line
    ORDER BY order_created_at ASC
    ";

        $rows = $this->query($sql, [$id])->result_array();

        if (empty($rows)) {
            return [];
        }

        // [3] 후처리: enc(암호화 ID), display_ (원본)
        foreach ($rows as &$r) {
            // 원본 백업
            $r['display_mlm_id']      = $r['mlm_id'];
            $r['display_referrer_id'] = $r['referrer_id'];
            $r['display_sponsor_id']  = $r['sponsor_id'];

            // 실제 필드값은 암호화 버전으로 대체
            $r['mlm_id']      = isset($r['mlm_id']) ? encrypt_id($r['mlm_id']) : null;
            $r['referrer_id'] = isset($r['referrer_id']) && $r['referrer_id'] !== null ? encrypt_id($r['referrer_id']) : null;
            $r['sponsor_id']  = isset($r['sponsor_id']) && $r['sponsor_id'] !== null ? encrypt_id($r['sponsor_id']) : null;
        }

        $rootIndex = array_search($id, array_column($rows, 'display_mlm_id'));
        if ($rootIndex !== false) {
            if ($type === 'referrer') {
                $rows[$rootIndex]['referrer_id']         = null;
                $rows[$rootIndex]['display_referrer_id'] = null;
            } else {
                $rows[$rootIndex]['sponsor_id']         = null;
                $rows[$rootIndex]['display_sponsor_id'] = null;
            }
        }

        return $rows;
    }

    /**
     * 주문 존재 여부 확인
     */
    public function order_exists($mlm_id)
    {
        $query = $this->where('mlm_id', $mlm_id)
            ->get('mlm_tree'); // 테이블명 확인 필요

        return $query->num_rows() > 0;
    }

    /**
     * 주문 상태 변경
     */
    public function update_status($order_number, $status)
    {
        // order 테이블의 status 필드 변경
        $this->where('order_number', $order_number);
        return $this->update($this->table_order, ['status' => $status]);
    }

    /**
     * 주문 삭제 orders + orders_item + mlm_tree
     */
    public function delete_order($order_number)
    {
        $order_number = decrypt_id($order_number);

        if ($order_number === false) {
            show_error('잘못된 접근입니다.');
        }

        // 트랜잭션 시작
        $this->trans_begin();

        try {

            // 본인의 mlm_id 가져오기
            $this->select('mlm_id');
            $this->where('order_number', $order_number);
            $order_mlm = $this->get('mlm_tree')->row();

            if ($order_mlm) {
                $mlm_id = $order_mlm->mlm_id;

                // 1) bonus 포인트 존재 여부
                $bonus_point_exists = $this->db
                    ->get_where('points', [
                        'source_name' => $mlm_id,
                        'source_type' => 'bonus',
                    ])
                    ->num_rows() > 0;

                // 2) account_holder 존재 여부
                $account_holder_exists = $this->db
                    ->get_where('points', ['account_holder' => $mlm_id])
                    ->num_rows() > 0;

                if ($bonus_point_exists || $account_holder_exists) {
                    throw new Exception('마감이 완료된 상태 입니다 [삭제불가]');
                }

                // 하위라인 존재 여부 확인
                $this->where('sponsor_id', $mlm_id);
                $query = $this->get('mlm_tree');

                if ($query->num_rows() > 0) {
                    throw new Exception('하위에 ' . ACCOUNT_NAME . '가 존재 합니다 [삭제불가]');
                }

                // 하위라인 없으면 mlm_tree 삭제
                $this->where('order_number', $order_number);
                $this->delete('mlm_tree');
            }

            // point 삭제
            $this->where('order_number', $order_number);
            $this->delete('points');

            // orders 삭제
            $this->where('order_number', $order_number);
            $this->delete('orders');

            // 트랜잭션 상태 확인
            if ($this->trans_status() === false) {
                $this->trans_rollback();
                throw new Exception(ACCOUNT_NAME . ' 삭제 중 오류가 발생했습니다');
            }

            // 모든 작업 성공 시 커밋
            $this->trans_commit();
            redirect('/' . $this->uri->segment(1) . '/order/list');
        } catch (Exception $e) {
            // 예외 발생 시 롤백
            $this->trans_rollback();
            alert($e->getMessage());
        }
    }

    // 보너스 리스트
    public function get_bonus_list($mlm_id)
    {
        $this->select('*')
            ->from('points')
            ->where('source_name', $mlm_id)
            ->where('source_type', 'bonus') // 💡 MLM 수당만 조회
            ->where('status', 'completed')
            ->order_by('point_id', 'asc');

        $query  = $this->get();
        $result = $query->result_array();

        $total_all     = 0;
        $total_balance = 0;

        foreach ($result as &$row) {
            $amount = (int) ($row['amount'] ?? 0);
            $total_all += $amount;
            $total_balance += $amount;

            // 전부 플러스 지급형 표시
            $row['signed_amount']  = $amount;
            $row['display_sign']   = 'plus';
            $row['display_amount'] = '+ ' . number_format($amount);
        }

        return [
            'list'          => $result,
            'total_all'     => $total_all,
            'total_balance' => $total_balance,
        ];
    }



    /**
     * 특정 회원의 좌/우 인원 수 및 볼륨 합계 조회
     * mlm_tree에는 position이 없으므로, mlm_volume의 position 컬럼을 기준으로 계산
     */
public function get_side_summary(string $mlm_id): array
{
    // 음수 제외 (양수만 합산)
    $query = $this->select('position, ref_id, SUM(volume) AS total_volume')
        ->from('mlm_volume')
        ->where('mlm_id', $mlm_id)
        ->where('volume >', 0) // 🔸 음수 제외
        ->group_by(['position', 'ref_id'])
        ->get()
        ->result_array();

    $result = [
        'L' => ['members' => [], 'count' => 0, 'volume' => 0],
        'R' => ['members' => [], 'count' => 0, 'volume' => 0],
    ];

    foreach ($query as $row) {
        $side   = $row['position'];
        $ref_id = $row['ref_id'];

        if (!isset($result[$side])) continue;

        // 중복 방지 후 인원 추가
        if (!in_array($ref_id, $result[$side]['members'])) {
            $result[$side]['members'][] = $ref_id;
        }

        // 합산
        $result[$side]['volume'] += (int) $row['total_volume'];
    }

    $result['L']['count'] = count($result['L']['members']);
    $result['R']['count'] = count($result['R']['members']);

    return $result;
}

}
