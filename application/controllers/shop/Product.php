<?php
namespace App\Controllers;

use Controller;

defined('BASEPATH') or exit('No direct script access allowed');

class Product extends Controller
{
    public function __construct()
    {
        parent::__construct();

        // CI3의 $this->load->model() → MiniCI 파사드 호출
        model(['member_model', 'product_model']);
    }

    public function index(): void
    {
        $this->list();
    }

    public function list(?int $id = null): void
    {
        $data = [
            'header'  => [
                'title' => '상품리스트',
                'group' => 'product',
            ],
            'product' => model('product_model')->get_product_all($id),
        ];

        view('shop/product_list', $data);
    }

    public function item(?int $id = null): void
    {
        $data = [
            'header'  => [
                'title' => '상품보기',
                'group' => 'product',
            ],
            'product' => model('product_model')->get_product_id($id),
        ];

        view('shop/product_item', $data);
    }
}
