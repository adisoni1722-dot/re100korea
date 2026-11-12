<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * MiniCI Cart Library (CI3 호환)
 * - 세션 기반 장바구니
 * - app('session') / app('security') 통합
 */
class Cart {
    /** @var string 세션 키 */
    protected string $cart_key = 'cart_contents';

    /** @var array 현재 장바구니 데이터 */
    protected array $contents = [];

    /** @var Session */
    protected $session;

    /** @var Security */
    protected $security;

    public function __construct() {
        $this->session  = class_exists('Container') && Container::has('session') ? app('session') : null;
        $this->security = class_exists('Container') && Container::has('security') ? app('security') : null;

        $this->contents = $this->session ? $this->session->userdata($this->cart_key) ?? [] : [];
        if (empty($this->contents) || !isset($this->contents['total_items'])) {
            $this->contents = ['cart_total' => 0, 'total_items' => 0];
        }
    }

    /** --------------------------------------------------------------
     * 장바구니 추가
     * -------------------------------------------------------------- */
    public function insert($items) {
        if (is_array($items) && isset($items['id'])) {
            return $this->insert_item($items);
        }
        foreach ($items as $item) {
            $this->insert_item($item);
        }
        $this->save_cart();
        return true;
    }

    protected function insert_item(array $item) {
        if (!isset($item['id'], $item['qty'], $item['price'], $item['name'])) {
            return false;
        }

        $id    = preg_replace('/[^A-Za-z0-9_-]/', '', $item['id']);
        $name  = $this->security ? $this->security->cleanString($item['name'], false) : htmlspecialchars($item['name']);
        $qty   = (int) $item['qty'];
        $price = (int) $item['price'];

        if ($qty <= 0 || $price < 0) {
            return false;
        }

        $options = isset($item['options']) && is_array($item['options']) ? $item['options'] : [];
        ksort($options);
        $rowid = md5($id . serialize($options));

        $old_qty = $this->contents[$rowid]['qty'] ?? 0;

        $this->contents[$rowid] = [
            'rowid'    => $rowid,
            'id'       => $id,
            'qty'      => $old_qty + $qty,
            'price'    => $price,
            'name'     => $name,
            'options'  => $options,
            'subtotal' => ($old_qty + $qty) * $price,
        ];

        return $rowid;
    }

    /** --------------------------------------------------------------
     * 장바구니 업데이트
     * -------------------------------------------------------------- */
    public function update(array $items) {
        if (isset($items['rowid'])) {
            $this->update_item($items);
        } else {
            foreach ($items as $item) {
                $this->update_item($item);
            }
        }
        $this->save_cart();
        return true;
    }

    protected function update_item(array $item) {
        if (!isset($item['rowid'], $item['qty'])) {
            return false;
        }

        $rowid = $item['rowid'];
        $qty   = (int) $item['qty'];

        if (!isset($this->contents[$rowid])) {
            return false;
        }

        if ($qty <= 0) {
            unset($this->contents[$rowid]);
            return true;
        }

        $this->contents[$rowid]['qty']      = $qty;
        $this->contents[$rowid]['subtotal'] = $this->contents[$rowid]['price'] * $qty;
        return true;
    }

    /** --------------------------------------------------------------
     * 항목 삭제 / 전체 비우기
     * -------------------------------------------------------------- */
    public function remove(string $rowid) {
        if (isset($this->contents[$rowid])) {
            unset($this->contents[$rowid]);
            $this->save_cart();
        }
    }

    public function destroy() {
        $this->contents = ['cart_total' => 0, 'total_items' => 0];
        $this->save_cart();
    }

    /** --------------------------------------------------------------
     * 내부 저장
     * -------------------------------------------------------------- */
    protected function save_cart(): bool {
        $total = 0;
        $items = 0;

        foreach ($this->contents as $key => $val) {
            if (!is_array($val) || !isset($val['price'], $val['qty'])) {
                continue;
            }

            $total += $val['price'] * $val['qty'];
            $items += $val['qty'];
            $this->contents[$key]['subtotal'] = $val['price'] * $val['qty'];
        }

        $this->contents['total_items'] = $items;
        $this->contents['cart_total']  = $total;

        if ($this->session) {
            $this->session->set_userdata($this->cart_key, $this->contents);
        }

        return true;
    }

    /** --------------------------------------------------------------
     * 데이터 접근
     * -------------------------------------------------------------- */
    public function contents(): array {
        $cart = $this->contents;
        unset($cart['total_items'], $cart['cart_total']);
        return $cart;
    }

    public function total(): float {
        return $this->contents['cart_total'] ?? 0;
    }

    public function total_items(): int {
        return $this->contents['total_items'] ?? 0;
    }

    public function has_options(string $rowid): bool {
        return !empty($this->contents[$rowid]['options']);
    }

    public function product_options(string $rowid): array {
        return $this->contents[$rowid]['options'] ?? [];
    }
}
