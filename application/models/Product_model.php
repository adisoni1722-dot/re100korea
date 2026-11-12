<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Product_model extends Model
{

    protected $table  = 'products';
    protected $pk_key = 'product_id';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 전체 상품 조회
     * @return array
     */
    public function get_product_all($status = null)
    {
        if ($status !== null) {
            $this->where('status', $status); // status 조건 있을 경우만
        }

        $this->order_by('created_at', 'DESC'); // 최근 등록순 정렬
        $query  = $this->get($this->table);
        $result = $query->result_array();

        //가공
        $no = 1;
        foreach ($result as &$row) {
            $row['no']                    = $no++;
            $row['price_format']          = number_format((int) ($row['price'] ?? 0), 0, '.', ',');
            $row['delivery_fee_format']   = number_format((int) ($row['delivery_fee'] ?? 0), 0, '.', ',');
            $row['discount_price_format'] = number_format((int) ($row['discount_price'] ?? 0), 0, '.', ',');
        }
        unset($row);

        return $result;
    }

    /**
     * 단일 상품 조회
     */
    public function get_product_id($id)
    {
        return $this->get_where($this->table, [$this->pk_key => $id])->row_array();
    }
}
