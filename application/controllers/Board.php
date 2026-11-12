<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Board Controller
 */
class Board extends Controller
{
    public function __construct()
    {
        parent::__construct();
        model('board_model');
        $this->skin_base = 'board/skins';

        $segments = explode('/', ltrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
        $this->route_uri = $segments[0] ?? 'board'; // 없으면 기본값 board
    }


    /**
     * 게시판 목록
     */

    public function list($board_id = 'notice')
    {
        $data['header'] = '게시판 목록';

        // 1. 리소스 로드
        [$board] = $this->_load_resource($board_id);

        // 2. 파라미터 및 페이지네이션 설정
        // [변경] 검색어(keyword), 검색대상(field), 카테고리(category) 받기
        $keyword  = $this->input->get('keyword');
        $field    = $this->input->get('field'); // 기존 search_type에서 변경됨
        $category = $this->input->get('category');

        $page = $this->_get_page(); // 기존 private 함수 사용

        // 게시판 설정에 목록 수(list_count)가 있으면 사용, 없으면 기본 9개
        $per_page = (int)($board['list_count'] ?? 9);
        $offset   = ($page - 1) * $per_page;

        // 3. DB 데이터 조회 (검색 파라미터 전달)
        // 모델의 get_posts와 count_posts에 검색 조건($keyword, $field, $category)을 전달합니다.
        $raw_posts = $this->board_model->get_posts($board_id, $per_page, $offset, $keyword, $field, $category);
        $total     = $this->board_model->count_posts($board_id, $keyword, $field, $category);

        // 4. 페이지네이션 HTML 생성
        // 검색 조건이 유지되도록 _make_pagination_html 내부에서 파라미터를 처리해야 함 (기존 로직 유지)
        $total_pages = ($total > 0) ? (int) ceil($total / $per_page) : 1;
        $pager_html  = raw($this->_make_pagination_html($page, $total_pages));

        // 5. 게시글 데이터 가공
        $posts = [];
        foreach ($raw_posts as $row) {
            // (1) 링크
            $row['link'] = "/{$this->route_uri}/{$board_id}/view/{$row['post_id']}";

            // (2) 날짜 (당일이면 시간, 아니면 날짜 표시 등 커스텀 가능하지만 여기선 날짜로 통일)
            $row['date'] = date('Y.m.d', strtotime($row['created_at']));

            // (3) 썸네일
            $row['thumb_url'] = '';
            // 목록에서 썸네일을 위해 파일 정보를 가져오는 것은 쿼리량이 많아질 수 있으므로
            // 추후 성능 이슈 발생 시 별도 썸네일 컬럼이나 캐싱 고려 필요
            $files = $this->board_model->get_files($row['post_id']);
            if (!empty($files)) {
                foreach ($files as $file) {
                    // 이미지 확장자 체크 로직이 있다면 더 정확함 (간단히 파일 경로 유무만 체크)
                    if (!empty($file['file_path'])) {
                        $row['thumb_url'] = $file['file_path'];
                        break; // 첫 번째 이미지만 사용
                    }
                }
            }

            // (4) 댓글 수
            $row['comment_html'] = '';
            if (!empty($row['comment_count']) && $row['comment_count'] > 0) {
                $row['comment_html'] = raw('<span class="comment-cnt">[' . $row['comment_count'] . ']</span>');
            }

            // (5) 새 글 / 비밀글 아이콘
            $is_new = (time() - strtotime($row['created_at'])) < (24 * 3600);
            $row['new_icon'] = $is_new ? raw('<span class="new-icon"></span>') : '';

            $row['secret_icon'] = '';
            if (isset($row['is_secret']) && $row['is_secret'] == 1) {
                $row['secret_icon'] = raw('<i class="fas fa-lock icon-secret"></i>');
            }

            $posts[] = $row;
        }

        // 6. 뷰에 전달할 링크 생성
        $links = [
            'write' => "/{$this->route_uri}/{$board_id}/write",
            'list'  => "/{$this->route_uri}/{$board_id}/list",
        ];

        // 7. 뷰 데이터 구성
        $skin_name = $board['skin'] ?? 'basic';

        $data = [
            'board'       => $board,
            'posts'       => $posts,
            'pager'       => $pager_html,
            'total'       => $total,

            // [중요] 검색 폼 상태 유지를 위한 데이터 전달
            'keyword'     => $keyword,
            'field'       => $field,    // 뷰의 <select> 에서 selected 처리에 사용
            'category'    => $category,

            'skin_dir'    => "/views/{$this->skin_base}/{$skin_name}",
            // 쓰기 권한 체크 (세션 레벨 >= 게시판 설정 레벨)
            'can_write'   => (int)$this->session->get('level') >= (int)($board['write_level'] ?? 0),
            'links'       => $links,
        ];

        view("{$this->skin_base}/{$skin_name}/list", $data);
    }


    /**
     * 게시글 보기
     */
    public function view($board_id = '', $post_id = 0)
    {
        // 1. 리소스 로드
        [$board, $post] = $this->_load_resource($board_id, $post_id);

        // =========================================================
        // [비밀글 접근 권한 체크]
        // =========================================================
        if (isset($post['is_secret']) && $post['is_secret'] == 1) {

            // A. 관리자나 작성자 본인(로그인)인 경우 통과
            $is_admin = (int)$this->session->get('level') >= 10;
            $is_owner = $this->session->get('is_login') && ($post['member_id'] == $this->session->get('member_id'));

            if (!$is_admin && !$is_owner) {

                // [변경] B. 회원이 작성한 글인 경우 -> 즉시 차단 (비번 입력 기회 없음)
                if (!empty($post['member_id'])) {
                    alert('작성자와 관리자만 볼 수 있는 비밀글입니다.');
                    return; // 실행 중단
                }

                // C. 비회원(손님)이 작성한 글인 경우 -> 비밀번호 확인 시도
                if (!empty($post['password'])) {

                    // 세션 확인 (이미 인증했는지)
                    $sess_key = 'guest_verified_' . $post_id;
                    if (!$this->session->get($sess_key)) {

                        // POST로 비밀번호가 넘어왔는지 확인
                        $input_pass = $this->input->post('password_check');
                        if ($input_pass) {
                            if (password_verify($input_pass, $post['password'])) {
                                // 인증 성공 -> 세션 생성 후 새로고침
                                Container::get('session')->set($sess_key, true);
                                redirect("/{$this->route_uri}/{$board_id}/view/{$post_id}");
                            } else {
                                echo "<script>alert('비밀번호가 일치하지 않습니다.'); history.back();</script>";
                                exit;
                            }
                        }

                        // 인증 안 됐으면 비밀번호 입력 폼 출력
                        $this->_show_password_form($board, $post_id, 'view');
                        exit;
                    }
                } else {
                    // 비밀번호도 없는 비회원 글 (이론상 없지만 예외처리)
                    alert('조회 권한이 없습니다.');
                }
            }
        }
        // =========================================================


        // 2. 조회수 증가
        $this->board_model->increase_view_count($post_id);

        // 3. 기본 데이터
        $skin_name = $board['skin'] ?? 'basic';
        $post['content'] = raw($post['content']);

        // [가공 1] 뷰에서 보여줄 날짜 포맷
        $post['date_view'] = date('Y-m-d', strtotime($post['created_at']));

        // 4. 파일 처리
        $files = [];
        if (!empty($board['use_file'])) {
            $raw_files = $this->board_model->get_files($post_id);
            $files     = $this->_process_files($raw_files);
        }

        // 5. 권한 및 링크 생성
        $sess_level = (int) $this->session->get('level');
        $is_login   = $this->session->get('is_login');
        $is_admin   = ($sess_level >= 10);

        $is_owner = $is_login
            ? ($post['member_id'] == $this->session->get('member_id'))
            : empty($post['member_id']);

        $can_modify = ($is_admin || $is_owner);

        // [가공 2] 링크 생성
        $links = [
            'list'   => "/{$this->route_uri}/{$board_id}/list",
            'modify' => "/{$this->route_uri}/{$board_id}/write/{$post_id}",
            'delete' => "/{$this->route_uri}/{$board_id}/delete/{$post_id}",
            'write'  => "/{$this->route_uri}/{$board_id}/write",
        ];

        // 6. 이전/다음 글
        $raw_prev = $this->board_model->get_prev_post($board_id, $post_id);
        $raw_next = $this->board_model->get_next_post($board_id, $post_id);
        $prev_post = $this->_process_nav_item($raw_prev, $board_id, 'prev');
        $next_post = $this->_process_nav_item($raw_next, $board_id, 'next');

        $data = [
            'board'      => $board,
            'post'       => $post,
            'files'      => $files,
            'skin_dir'   => "/views/{$this->skin_base}/{$skin_name}",
            'links'      => $links,
            'prev_post'  => $prev_post,
            'next_post'  => $next_post,
            'can_modify' => $can_modify,
        ];

        view("{$this->skin_base}/{$skin_name}/view", $data);
    }

    /**
     * 네비게이션 아이템 데이터 가공 헬퍼
     * 뷰 로직을 컨트롤러로 이동시킴
     */
    private function _process_nav_item($post, $board_id, $type)
    {
        if (empty($post)) {
            return null; // 글이 없으면 null 반환
        }

        return [
            'link'      => "/{$this->route_uri}/{$board_id}/view/{$post['post_id']}",
            'title'     => htmlspecialchars($post['title']), // XSS 방지 처리 미리 적용
            'date'      => date('Y-m-d', strtotime($post['created_at'])),
            'type'      => $type, // 'prev' or 'next'
            'label'     => ($type === 'next') ? '다음글' : '이전글',
            // 아이콘을 구분하고 싶다면 여기서 정의
            'icon_cls'  => ($type === 'next') ? 'fa-chevron-up' : 'fa-chevron-down'
        ];
    }

    /**
     * 파일 목록 가공 (아이콘 매핑, 크기 포맷팅, 보안 처리)
     */
    private function _process_files($files)
    {
        if (empty($files)) return [];

        $icon_map = [
            'pdf'  => ['fa-file-pdf', 'pdf'],
            'jpg'  => ['fa-file-image', 'img'],
            'jpeg' => ['fa-file-image', 'img'],
            'png'  => ['fa-file-image', 'img'],
            'gif'  => ['fa-file-image', 'img'],
            'webp' => ['fa-file-image', 'img'],
            'xls'  => ['fa-file-excel', 'xls'],
            'xlsx' => ['fa-file-excel', 'xls'],
            'csv'  => ['fa-file-excel', 'xls'],
            'doc'  => ['fa-file-word', 'doc'],
            'docx' => ['fa-file-word', 'doc'],
            'ppt'  => ['fa-file-powerpoint', 'ppt'],
            'pptx' => ['fa-file-powerpoint', 'ppt'],
            'zip'  => ['fa-file-archive', 'zip'],
            'rar'  => ['fa-file-archive', 'zip'],
            '7z'   => ['fa-file-archive', 'zip'],
            'txt'  => ['fa-file-alt', 'txt'],
            'hwp'  => ['fa-file-word', 'hwp'],
            'hwpx' => ['fa-file-word', 'hwp'],
        ];

        $processed = [];
        foreach ($files as $file) {
            // [수정] 한글 파일명 호환을 위해 pathinfo 대신 점(.) 기준으로 확장자 추출
            $parts = explode('.', $file['origin_name']);
            $ext = strtolower(end($parts));

            // 아이콘 및 색상 클래스 매핑 (없으면 기본값)
            [$icon, $color] = $icon_map[$ext] ?? ['fa-file', 'default'];

            // 뷰(View)에서 바로 사용할 수 있도록 변수에 담기
            $file['ext']         = $ext;   // 확장자
            $file['icon_class']  = $icon;  // 아이콘 (fa-file-...)
            $file['color_class'] = $color; // 색상 클래스 (img, xls, zip ...)

            $file['size_text']   = round($file['file_size'] / 1024, 1) . ' KB';

            // 파일명 줄임 처리
            $origin_name = $file['origin_name'];
            if (mb_strlen($origin_name) > 30) {
                // 확장자를 제외한 이름 부분만 자르고 다시 확장자 붙이기
                $name_part = mb_substr($origin_name, 0, mb_strrpos($origin_name, '.'));
                $origin_name = mb_substr($name_part, 0, 15) . '...' . $ext;
            }
            $file['safe_name'] = htmlspecialchars($origin_name);

            $processed[] = $file;
        }
        return $processed;
    }

    /**
     * 게시글 작성 및 수정
     */
    public function write($board_id = '', $post_id = 0)
    {
        // 1. 리소스 로드
        [$board, $post] = $this->_load_resource($board_id, $post_id);
        $mode = ($post_id > 0) ? 'modify' : 'write';

        // 2. 권한 및 접근 검증
        $this->_validate_access($mode, $board, $post);

        // 3. 폼 검증 실행 (저장/수정 로직)
        if ($this->_run_form_validation($mode)) {
            if ($mode === 'modify') {
                $this->_update($post_id, $board_id);
            } else {
                $this->_save($board_id);
            }
            return;
        }


        // ==========================================
        // [View를 위한 데이터 준비]
        // ==========================================

        // (1) 에디터 내용 준비 (입력 실패 시 입력값 복구 > DB 값 > 빈 값)
        $content_val = $this->input->post('content') ?? ($post['content'] ?? '');

        // (2) 폼 입력값 기본 설정 (수정 시 DB값, 아니면 빈 값)
        $form_defaults = [
            'title'     => $post['title'] ?? '',
            'writer'    => $post['writer'] ?? '',
            'is_secret' => $post['is_secret'] ?? 0,
            'category'  => $post['category'] ?? '',
            'content'   => raw($content_val),
        ];

        // 카테고리 리스트 가공
        $category_list = [];
        if (!empty($board['category_list'])) {
            $category_list = explode('|', $board['category_list']);
        }


        // (3) 화면 구성 설정 (스킨의 조건문 대체)
        $config = [
            'mode'        => $mode,
            'title'  => ($mode === 'modify') ? '게시글 수정' : '게시글 작성',
            'action_url'  => "/{$this->route_uri}/{$board_id}/write" . ($post_id > 0 ? "/{$post_id}" : ""),
            'is_login'    => (bool)$this->session->get('is_login'),
            'use_secret'  => isset($board['use_secret']) && $board['use_secret'] == 1,
            'use_file'  => isset($board['use_file']) && $board['use_file'] == 1,
            'submit_text' => ($mode === 'modify') ? '수정하기' : '등록하기',
            'use_editor'  => $board['use_editor'] ?? 'minici'
        ];

        // (4) 첨부파일 가공 (수정 모드일 때만 조회)
        $files = [];
        if ($post_id > 0) {
            $raw_files = $this->board_model->get_files($post_id);
            // 기존에 만들어둔 파일 가공 함수(_process_files) 재사용
            $files = $this->_process_files($raw_files);
        }

        // 4. 뷰 출력
        $skin_name = $board['skin'] ?? 'basic';

        $data = [
            'board'          => $board,
            'config'         => $config,         // 화면 설정값 (제목, 버튼명, 권한 등)
            'categories'     => $category_list, // 카테고리 목록
            'files'          => $files,          // 파일 목록
            'form'           => $form_defaults,  // 폼 기본값
            'skin_dir'       => "/views/{$this->skin_base}/{$skin_name}",
        ];

        view("{$this->skin_base}/{$skin_name}/write", $data);
    }

    /**
     * 게시글 삭제
     */
    public function delete($board_id = '', $post_id = 0)
    {
        $is_ajax = $this->input->is_ajax();

        // 1. 게시글 확인
        [$board, $post] = $this->_load_resource($board_id, $post_id);

        // 2. 권한 확인 (비회원 비밀번호 확인 로직 포함)
        // 이 함수 내부에서 비회원일 경우 비밀번호 폼을 출력하고 exit 합니다.
        $this->_validate_access('delete', $board, $post);

        // 3. 삭제 실행 (위 검증을 통과했다면 실행됨)
        if ($this->board_model->delete_post($post_id)) {
            // 연관된 파일 삭제 처리 등은 모델 내부 혹은 여기서 처리

            if ($is_ajax) {
                $this->response->ajax(['ok' => true, 'message' => '삭제되었습니다.']);
            } else {
                alert('삭제되었습니다.', "/{$this->route_uri}/{$board_id}/list");
            }
        } else {
            $this->_response_error('삭제 실패');
        }
    }

    /**
     * 파일 삭제 (Ajax)
     */
    public function delete_file()
    {
        if (!$this->input->is_ajax()) {
            exit('No direct script access allowed');
        }

        $file_id = (int) $this->input->post('file_id');
        if (empty($file_id)) {

            $this->response->ajax(false, '잘못된 요청입니다.', [], 400);
            return;
        }

        $file = $this->board_model->get_file($file_id);
        if (!$file) {

            $this->response->ajax(false, '파일이 존재하지 않습니다.', [], 404);
            return;
        }

        // 권한 확인: 세션 레벨이 10 이상이면 삭제 가능
        $post = $this->board_model->get_post($file['post_id']);
        $sess_level = (int) $this->session->get('level');
        if ($sess_level < 10 && !$this->_check_owner($post)) {
            $this->response->ajax(false, '삭제 권한이 없습니다.', [], 403);
            return;
        }

        // 물리 파일 삭제
        $full_path = BASEPATH . ltrim($file['file_path'], '/');
        if (file_exists($full_path)) {
            @unlink($full_path);
        }

        // DB 삭제
        if ($this->board_model->delete_file($file_id)) {

            $this->response->ajax(true, '삭제되었습니다.');
        } else {
            $this->response->ajax(false, 'DB 삭제 실패', [], 500);
        }
    }

    /**
     * 에디터 이미지 업로드
     */
    public function upload_image()
    {
        if (empty($_FILES['file']['name'])) {
            $this->response->ajax(['error' => '파일이 없습니다.'], 400);
            return;
        }

        $upload_path = BASEPATH . 'data/board/upload_image/';
        if (!is_dir($upload_path)) {
            @mkdir($upload_path, 0755, true);
        }

        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $file_name   = $_FILES['file']['name'];
        $ext         = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_ext)) {
            $this->response->ajax(['error' => '허용되지 않는 파일 형식입니다.'], 400);
            return;
        }

        if (!$this->_verify_image_mime($_FILES['file']['tmp_name'])) {
            $this->response->ajax(['error' => '유효한 이미지 파일이 아닙니다.'], 400);
            return;
        }

        $new_name = date('YmdHis') . '_' . uniqid() . '.' . $ext;
        $full_path = $upload_path . $new_name;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $full_path)) {
            // payload 배열 안에 'url'을 담아서 보냅니다.
            $this->response->ajax(true, '업로드 성공', [
                'url' => '/data/board/upload_image/' . $new_name
            ]);
        } else {
            $this->response->ajax(false, '파일 저장 실패', [], 500);
        }
    }

    /**
     * 파일 다운로드
     */
    public function download($file_id = 0)
    {
        // 1. 유효성 검사
        if (empty($file_id)) {
            show_404();
            return;
        }

        // 2. DB에서 파일 정보 조회
        $file = $this->board_model->get_file($file_id);
        if (!$file) {
            show_error('파일 정보를 찾을 수 없습니다.', 404);
            return;
        }

        // 3. 실제 파일 경로 확인
        $filepath = BASEPATH . ltrim($file['file_path'], '/');

        // Response 서비스 내부에도 파일 체크가 있지만,
        // 사용자에게 친절한 에러 메시지(show_error)를 보여주기 위해 여기서 먼저 체크하는 것이 좋습니다.
        if (!file_exists($filepath) || !is_file($filepath)) {
            show_error('서버에 실제 파일이 존재하지 않습니다.', 404);
            return;
        }

        // (선택사항) 권한 체크 로직
        /*
        $post = $this->board_model->get_post($file['post_id']);
        if (!$this->_check_read_auth($post)) {
            show_error('다운로드 권한이 없습니다.', 403);
            return;
        }
        */

        // 4. Response 서비스를 통해 다운로드 실행
        // 첫 번째 인자: 파일의 절대 경로
        // 두 번째 인자: 사용자가 다운로드 받을 때 볼 파일명 (DB에 저장된 원본명)
        $this->response->download($filepath, $file['origin_name']);
    }



    // =========================================================================
    // Private Methods
    // =========================================================================

    /**
     * 리소스(게시판, 게시글) 로드 및 유효성 검사
     * @return array [$board, $post]
     */
    private function _load_resource($board_id, $post_id = 0)
    {
        if (empty($board_id)) show_404();

        $board = $this->board_model->get_board($board_id);
        if (!$board) show_404();

        $post = [];
        if ($post_id > 0) {
            $post = $this->board_model->get_post($post_id);
            // 게시글이 없거나 현재 게시판의 글이 아니면 404
            if (!$post || $post['board_id'] !== $board_id) {
                show_404();
            }
        }

        return [$board, $post];
    }

    /**
     * 통합 권한 검증 (레벨, 수정/삭제 권한, 비밀번호 확인)
     */

    private function _validate_access($type, $board, $post = [])
    {
        // 1. 작성 권한 체크 (기존 동일)
        if ($type === 'write') {
            if ((int)$this->session->get('level') < (int)$board['write_level']) {
                alert('글 작성 권한이 없습니다.');
            }
            return;
        }

        // 2. 수정/삭제 권한 체크
        if ($type === 'modify' || $type === 'delete') {
            if (empty($post)) show_error('잘못된 접근입니다.');

            // A. 회원일 경우
            if ($this->session->get('is_login')) {
                $is_admin = (int)$this->session->get('level') >= 10;
                $is_owner = ($post['member_id'] == $this->session->get('member_id'));

                // 관리자도 아니고(AND), 작성자 본인도 아니면 권한 없음
                if (!$is_admin && !$is_owner) {
                    $msg = ($type === 'delete') ? '삭제' : '수정';
                    show_error("{$msg} 권한이 없습니다.");
                }
            }
            // B. 비회원일 경우 (기존 동일)
            else {
                $p_id = $post['post_id'] ?? $post['posts_id'];
                $sess_key = 'guest_verified_' . $p_id;

                if (!$this->session->get($sess_key)) {
                    $input_pass = $this->input->post('password_check');

                    if ($input_pass) {
                        if (password_verify($input_pass, $post['password'])) {
                            Container::get('session')->set($sess_key, true);

                            if ($type === 'modify') {
                                redirect(current_url());
                            } else {
                                return;
                            }
                        } else {
                            // 비밀번호 불일치 시 뷰 페이지로 이동
                            $view_url = "/{$this->route_uri}/{$board['board_id']}/view/{$p_id}";
                            echo "<script>alert('비밀번호가 일치하지 않습니다.'); location.href='{$view_url}';</script>";
                            exit;
                        }
                    } else {
                        $this->_show_password_form($board, $p_id, $type);
                        exit;
                    }
                }
            }
        }
    }

    /**
     * 단순 소유권 확인 (true/false) - Ajax, Delete 등에서 사용
     */
    private function _check_owner($post)
    {
        if (!$post) return false;

        if ($this->session->get('is_login')) {
            return $post['member_id'] == $this->session->get('member_id');
        }

        // 비회원 게스트 세션 확인
        $p_id = $post['post_id'] ?? $post['posts_id'];
        $sess_key = 'guest_verified_' . $p_id;
        return (bool) $this->session->get($sess_key);
    }

    /**
     * 폼 유효성 검사 규칙 설정 및 실행
     */
    private function _run_form_validation($mode)
    {
        $this->form->set_rules('title', '제목', 'required|min_length[2]|max_length[100]')
            ->set_rules('content', '내용', 'required|min_length[2]');

        if (!$this->session->get('is_login')) {
            $this->form->set_rules('writer', '작성자', 'required|min_length[2]|max_length[20]');
            // 수정 모드일 때는 비밀번호 변경이 필수가 아닐 수 있으므로 상황에 맞게 조정
            if ($mode === 'write') {
                $this->form->set_rules('password', '비밀번호', 'required|min_length[4]|max_length[20]');
            }
        }

        return $this->form->run();
    }

    /**
     * DB 저장
     */
    /**
     * DB 저장
     */
    private function _save($board_id)
    {
        $data = [
            'board_id'   => $board_id,
            'title'      => $this->input->post('title'),
            'category'   => $this->input->post('category'),
            'content'    => $this->security->purify($this->input->post('content')),
            'is_secret'  => (int) $this->input->post('is_secret', 0),
            'created_at' => date('Y-m-d H:i:s'),
            'ip_address' => $this->input->ip_address(),
        ];

        if ($this->session->get('is_login')) {
            $data['member_id'] = $this->session->get('member_id');
            $data['writer']    = $this->session->get('name');
        } else {
            $data['writer'] = $this->input->post('writer');
            $pass = $this->input->post('password');
            if ($pass) {
                $data['password'] = password_hash($pass, PASSWORD_DEFAULT);
            }
        }

        $insert_id = $this->board_model->save_post($data);

        if ($insert_id) {
            $this->_handle_file_upload($insert_id);

            // 비회원일 경우, 방금 쓴 글은 바로 볼 수 있도록 인증 세션 생성
            if (!$this->session->get('is_login')) {
                $sess_key = 'guest_verified_' . $insert_id;
                // 세션에 인증 완료 표시 (view 함수에서 체크하는 키와 동일)
                Container::get('session')->set($sess_key, true);
            }

            alert("저장되었습니다", "/{$this->route_uri}/{$board_id}/view/{$insert_id}");
        } else {
            show_error('게시글 등록에 실패했습니다.');
        }
    }

    /**
     * DB 수정
     */
    private function _update($post_id, $board_id)
    {
        $data = [
            'title'      => $this->input->post('title'),
            'category'   => $this->input->post('category'),
            'content'    => $this->security->purify($this->input->post('content')),
            'is_secret'  => (int) $this->input->post('is_secret', 0),
            'updated_at' => date('Y-m-d H:i:s'),
            'ip_address' => $this->input->ip_address(),
        ];

        // 비회원이 비밀번호 변경을 원할 경우 처리 로직 추가 가능
        // (현재는 로직 간소화를 위해 생략, 필요시 추가)

        $result = $this->board_model->update_post($post_id, $data);

        if ($result) {
            $this->_handle_file_upload($post_id);
            alert("수정되었습니다", "/{$this->route_uri}/{$board_id}/view/{$post_id}");
        } else {
            show_error('게시글 수정에 실패했습니다.');
        }
    }

    /**
     * 에러 응답 처리 (Ajax/HTML 분기)
     */
    private function _response_error($msg)
    {
        if ($this->input->is_ajax()) {
            $this->response->ajax(['ok' => false, 'message' => $msg]);
        } else {
            alert($msg); // 혹은 history.back()
        }
    }

    /**
     * 파일 업로드 핸들러
     */
    private function _handle_file_upload($post_id)
    {
        if (empty($_FILES['file_upload']['name'][0])) return;

        $upload_path = BASEPATH . 'data/board/file';
        $web_path    = '/data/board/file/';

        library('UploadSimple', [
            'upload_path'  => $upload_path,
            'resize'       => true,
            'encrypt_name' => true
        ]);

        $upload = $this->uploadsimple;
        $files  = $this->_rearrange_files($_FILES['file_upload']);
        $files_db_batch = [];

        foreach ($files as $file) {
            $_FILES['_temp_upload'] = $file;
            if ($upload->handle('_temp_upload')) {
                $info = $upload->data();
                $files_db_batch[] = [
                    'post_id'     => $post_id,
                    'origin_name' => $info['client_name'],
                    'file_path'   => $web_path . $info['file_name'],
                    'mime_type'   => $info['file_type'],
                    'file_size'   => $info['file_size'],
                    'created_at'  => date('Y-m-d H:i:s')
                ];
            }
        }

        if (!empty($files_db_batch)) {
            $this->board_model->insert_files($files_db_batch);
        }
    }

    /**
     * $_FILES 배열 재정렬
     */
    private function _rearrange_files($file_post)
    {
        $files = [];
        if (!isset($file_post['name'])) return $files;

        $file_count = count($file_post['name']);
        $file_keys  = array_keys($file_post);

        for ($i = 0; $i < $file_count; $i++) {
            foreach ($file_keys as $key) {
                $files[$i][$key] = $file_post[$key][$i];
            }
        }
        return $files;
    }

    /**
     * 이미지 MIME 검사
     */
    private function _verify_image_mime($tmp_name)
    {
        if (!is_file($tmp_name)) return false;
        if (!function_exists('finfo_open')) return true;

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmp_name);
        finfo_close($finfo);

        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        return in_array($mime, $allowed, true);
    }

    /**
     * 페이지 번호 가져오기
     */
    private function _get_page()
    {
        $page = (int) $this->input->get('page');
        return ($page > 0) ? $page : 1;
    }

    /**
     * 비회원 비밀번호 입력 폼 출력
     */
    /**
     * 비회원 비밀번호 입력 폼 출력
     */
    private function _show_password_form($board, $post_id, $mode = 'modify')
    {
        // 1. 폼 전송 주소(Action) 생성
        // view 모드일 때는 자기 자신(view 페이지)으로 POST 전송
        if ($mode === 'view') {
            $action = "/{$this->route_uri}/{$board['board_id']}/view/{$post_id}";
        } else {
            $method_segment = ($mode === 'delete') ? 'delete' : 'write';
            $action = "/{$this->route_uri}/{$board['board_id']}/{$method_segment}/{$post_id}";
        }

        // 2. 모드에 따른 텍스트 설정
        $title_text = '비밀번호 확인';
        $desc_text = '비밀번호를 입력해주세요.';

        if ($mode === 'delete') {
            $title_text = '게시글 삭제';
            $desc_text = '게시글 삭제를 위해 비밀번호를 입력해주세요.';
        } elseif ($mode === 'modify') {
            $title_text = '게시글 수정';
            $desc_text = '게시글 수정을 위해 비밀번호를 입력해주세요.';
        } elseif ($mode === 'view') {
            $title_text = '비밀글 확인';
            $desc_text = '보호된 글입니다. 비밀번호를 입력해주세요.';
        }

        $data = [
            'action'     => $action,
            'title_text' => $title_text,
            'desc_text'  => $desc_text
        ];

        // 3. 뷰 파일 로드
        view('board/password_check', $data, false);
    }


    /**
     * 페이지네이션 HTML 생성기 (Private Helper)
     */
    private function _make_pagination_html($current_page, $total_pages)
    {
        if ($total_pages <= 1) return '';

        // 현재 URL 파라미터 유지 (검색어 등)
        $params = $_GET;
        unset($params['page']); // 기존 페이지 번호 제거
        $query_string = http_build_query($params);
        $base_url = '?' . ($query_string ? $query_string . '&' : '') . 'page=';

        $html = '';

        // [이전] 버튼 (필요시 활성화)
        // if ($current_page > 1) {
        //     $html .= '<a href="' . $base_url . ($current_page - 1) . '" class="page-link"><i class="fas fa-angle-left"></i></a>';
        // }

        // 표시할 페이지 범위 설정 (현재 페이지 기준으로 앞뒤 2개씩)
        $start = max(1, $current_page - 2);
        $end   = min($total_pages, $current_page + 2);

        // 범위 보정 (시작이 1이면 끝을 늘림, 끝이 마지막이면 시작을 줄임)
        if ($start === 1) $end = min($total_pages, 5);
        if ($end === $total_pages) $start = max(1, $total_pages - 4);

        // 첫 페이지로 이동
        if ($start > 1) {
             $html .= '<a href="' . $base_url . '1" class="page-link">1</a>';
             if ($start > 2) $html .= '<span class="page-link dot">...</span>';
        }

        // 페이지 번호 루프
        for ($i = $start; $i <= $end; $i++) {
            $active = ($i === $current_page) ? 'active' : '';
            $html .= '<a href="' . $base_url . $i . '" class="page-link ' . $active . '">' . $i . '</a>';
        }

        // 마지막 페이지로 이동
        if ($end < $total_pages) {
            if ($end < $total_pages - 1) $html .= '<span class="page-link dot">...</span>';
            $html .= '<a href="' . $base_url . $total_pages . '" class="page-link">' . $total_pages . '</a>';
        }

        // [다음] 버튼 (필요시 활성화)
        // if ($current_page < $total_pages) {
        //     $html .= '<a href="' . $base_url . ($current_page + 1) . '" class="page-link"><i class="fas fa-angle-right"></i></a>';
        // }

        return $html;
    }

}
