<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Product extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model(['product_model']);
        admin_logincheck();
    }

    public function list()
    {

        $data['product'] = $this->product_model->get_product_all($id = null);

        $data['header'] = ['title' => '상품 리스트', 'group' => 'product'];
        view('admin/product_list', $data);
    }

    public function view($id = null)
    {

        $data['header']  = ['title' => $id ? '상품 수정' : '상품 등록', 'group' => 'product'];
        $data['product'] = $this->product_model->get_product_id($id);
        $data['product_id'] = $data['product']['product_id'] ?? '';

        view('admin/product_view', $data);
    }

    public function save()
    {
        if ($this->input->post()) {

            $data = get_table('products');

            //이미지 업로드 처리
            $image_fields = ['image_main', 'image_sub1', 'image_sub2', 'image_sub3', 'image_detail'];
            $upload_path  = FCPATH . 'data/product/';

            library('UploadSimple', [
                'upload_path' => $upload_path,
                'resize'      => true
            ]);


            $upload = $this->uploadsimple;

            foreach ($image_fields as $field) {
                if (!empty($_FILES[$field]['name'])) {
                    if ($upload->handle($field)) {
                        $info         = $upload->data();
                        $data[$field] = str_replace(FCPATH, '/', $info['file_path']) . $info['file_name'];
                    } else {
                        $error_msg = $upload->error();
                    }
                }
            }
            // 필수값 검증
            $this->form->set_rules('name', '상품명', 'required');
            $this->form->set_rules('price', '가격', 'required|numeric');

            if ($this->form->run() === true) {


                // 타겟 ID 확인 (수정 모드 판별용)
                $target_id = $this->input->post('product_id');

                // 저장 실행 (save_table 활용)
                $save_id = save_table('products', $data, 'product_id', $target_id);

                if ($save_id) {
                    alert("저장이 완료 되었습니다", '/admin/product/view/' . $save_id);
                } else {
                    echo "<div style='background: red; color: white; padding: 10px; margin: 10px;'>✗ 저장 실패</div>";
                }
            } else {
                echo $this->form->all_errors();
            }
        }
    }

    /**
     * 상품 삭제
     * @param int $id 상품 ID
     */
    public function delete($id)
    {
        // 상품이 존재하는지 확인
        $product = $this->product_model->get_product_id($id);
        if (!$product) {
            show_error('존재하지 않는 상품입니다.');
            return;
        }

        // 2) 이미지 파일 삭제
        $image_fields = ['image_main', 'image_sub1', 'image_sub2', 'image_sub3', 'image_detail'];
        foreach ($image_fields as $field) {
            if (!empty($product[$field])) {
                $file_path = BASEPATH . ltrim($product[$field], '/'); // '/data/product/xxx.jpg' → 실제 경로
                if (file_exists($file_path)) {
                    unlink($file_path); // 파일 삭제
                }
            }
        }

        // 삭제 처리
        $this->db->delete('products', ['product_id' => $id]);
        redirect('/admin/product/list');
    }

    public function delete_image()
    {
        header('Content-Type: application/json');

        $input = $this->input->json();

        $product_id = $input['product_id'] ?? null;
        $field      = $input['field'] ?? null;

        if (!$product_id || !$field) {
            echo json_encode(['success' => false, 'message' => '잘못된 요청', $this->csrf->get_csrf_token_name() => $this->csrf->get_csrf_hash()]);
            return;
        }

        //get_product
        $product = $this->product_model->get_product_id($product_id);

        if (!isset($product[$field]) || empty($product[$field])) {
            // 이미 DB에 없으므로 성공으로 간주하고, 메시지만 전달
            echo json_encode([
                'success'                          => true,
                'message'                          => 'DB 기록된 이미지 파일이 없습니다',
                $this->csrf->get_csrf_token_name() => $this->csrf->get_csrf_hash(),
            ]);
            return;
        }

        // 서버 파일 삭제
        $file_path = '.' . $product[$field];
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        // DB 컬럼 초기화
        $this->db->where('product_id', $product_id);
        $this->db->update('products', [$field => null]);

        echo json_encode([
            'success'                          => true,
            'message'                          => '이미지가 삭제되었습니다.',
            $this->csrf->get_csrf_token_name() => $this->csrf->get_csrf_hash(),
        ]);
    }
}
