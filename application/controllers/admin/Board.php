<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Board extends Controller
{
    public function __construct()
    {
        parent::__construct();
        model('Board_model');
    }

    /**
     * 게시판 관리 리스트 페이지
     */
    public function list()
    {
        // 1. 게시판 목록 가져오기 (기존 코드)
        $data['board_list'] = $this->Board_model->get_all_boards();

        // 2. 스킨 디렉토리 스캔 로직
        $skins = [];

        // VIEWPATH 상수가 있으면 사용, 없으면 DOCUMENT_ROOT 기반으로 경로 설정
        $skin_base_path = defined('VIEWPATH') ? VIEWPATH . 'board/skins/' : $_SERVER['DOCUMENT_ROOT'] . '/views/board/skins/';

        if (is_dir($skin_base_path)) {
            $dirs = scandir($skin_base_path);
            foreach ($dirs as $dir) {
                // ., .. 및 파일 제외하고 디렉토리만 스캔
                if ($dir === '.' || $dir === '..') continue;
                if (is_dir($skin_base_path . $dir)) {
                    $skins[$dir] = ucfirst($dir); // 첫 글자 대문자로 변환하여 표시 이름 설정
                }
            }
        }

        // 스킨이 하나도 없으면 기본값 설정
        if (empty($skins)) {
            $skins['basic'] = 'Basic';
        }

        // 3. 뷰로 데이터 전달 ($data 배열에 skins 추가)
        $data['skins'] = $skins;

        // 4. 뷰 로드
        view('admin/board_list', $data);
    }


    /**
     * [액션] 리스트에서 일괄 수정
     */
    public function update_list()
    {
        $chk = $this->input->post('chk');       // 체크된 원본 ID들
        $boards = $this->input->post('boards'); // 입력된 전체 데이터

        if (empty($chk) || !is_array($chk)) {
            alert('수정할 게시판을 선택해주세요.');
            return;
        }

        $count = 0;
        foreach ($chk as $original_id) {
            // $boards 배열에서 원본 ID 키를 가진 데이터를 찾음
            if (isset($boards[$original_id])) {
                $item = $boards[$original_id];

                // 체크박스 값 처리
                $item['use_secret'] = isset($item['use_secret']) ? 1 : 0;
                $item['use_file']   = isset($item['use_file']) ? 1 : 0;


                // [중요] ID가 변경되었는지 확인
                $new_id = trim($item['board_id']);
                $old_id = $item['original_board_id'];

                // 유효성 검사 (ID가 비어있으면 안됨)
                if (empty($new_id)) {
                    continue;
                }

                // 모델 호출: 첫 번째 인자는 '찾을 ID(구 ID)', 두 번째는 '바뀔 데이터(신 ID 포함)'
                $this->Board_model->update_board($old_id, $item);
                $count++;
            }
        }

        alert($count . '건의 게시판 정보를 수정했습니다.', '/admin/board/list');
    }


    public function view($board_id = null)
    {


        $data['board'] =  $this->Board_model->get_board($board_id);

        // 뷰파일 전송 ID 설정
        $data['board_id'] = $data['board']['board_id'] ?? '';

        $data['header'] = ['title' => ' 게시판 수정', 'group' => 'board'];
        view('admin/board_view', $data);
    }


    /**
     * [ACTION] 게시판 설정 저장 (생성/수정 통합)
     */
    public function save()
    {
        // 1. 유효성 검사 (Form Validation)
        $this->form->set_rules('board_id', '게시판 ID', 'required|alpha_dash');
        $this->form->set_rules('title', '게시판 제목', 'required');

        if ($this->form->run() === FALSE) {
            // 검증 실패 시 에러 메시지 출력 후 뒤로가기
            alert(validation_errors());
            return;
        }


        $data = get_table('boards');

        $target_id = $this->input->post('original_board_id');
        $result_id = save_table('boards', $data, 'board_id', $target_id);

        // 5. 결과 처리
        if ($result_id) {
            $msg = $target_id ? '게시판 정보가 수정되었습니다.' : '새 게시판이 생성되었습니다.';
            alert($msg, '/admin/board/list');
        } else {
            alert('저장에 실패했습니다.');
        }
    }


    public function delete($board_id = null)
    {
        // 1. 필수 파라미터 확인
        if (empty($board_id)) {
            alert('삭제할 게시판 ID가 전달되지 않았습니다.');
            return;
        }

        $result = $this->Board_model->delete_board($board_id);

        // 4. 결과 처리 및 이동
        if ($result) {
            alert('게시판이 삭제되었습니다.', '/admin/board/list');
        } else {
            alert('삭제 도중 오류가 발생했습니다. 다시 시도해주세요.');
        }
    }
}
