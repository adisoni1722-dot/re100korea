<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Board_model extends Model
{
    protected $table_boards   = 'boards';
    protected $table_posts    = 'posts';
    protected $table_comments = 'comments';
    protected $table_files    = 'post_files';

    public function __construct()
    {
        parent::__construct();
    }


    protected function _search_query($keyword, $field, $category)
    {
        // 1. 카테고리 필터
        if (!empty($category)) {
            $this->where('category', $category);
        }

        // 2. 검색어 필터
        if (!empty($keyword)) {
            // '제목+내용' 검색 (AND (title LIKE... OR content LIKE...))
            if ($field === 'all') {
                $this->group_start();
                $this->like('title', $keyword);
                $this->or_like('content', $keyword);
                $this->group_end();
            }
            // 단일 컬럼 검색
            elseif (in_array($field, ['title', 'content', 'writer'])) {
                $this->like($field, $keyword);
            }
            // 그 외 (기본은 제목 검색)
            else {
                $this->like('title', $keyword);
            }
        }
    }


    /**
     * 게시판 설정 가져오기 (배열)
     */
    public function get_board($board_id)
    {
        return $this->where('board_id', $board_id)
            ->get($this->table_boards)
            ->row_array();
    }

    /**
     * 게시물 목록 가져오기 (페이징)
     */
    public function get_list($board_id, $limit, $offset, $keyword = '', $field = '', $category = '')
    {
        // 게시판 ID 조건
        $this->where('board_id', $board_id);

        // 검색 조건 적용
        $this->_search_query($keyword, $field, $category);

        $this->order_by('post_id', 'DESC');
        $this->limit($limit, $offset);
        $query = $this->get($this->table_posts);
        return $query->result_array();
    }

    /**
     * 게시물 목록 가져오기 (별칭)
     */
    public function get_posts($board_id, $limit, $offset, $keyword = '', $field = '', $category = '')
    {
        return $this->get_list($board_id, $limit, $offset, $keyword, $field, $category);
    }

    /**
     * 총 게시물 수
     */
    public function count_posts($board_id, $keyword = '', $field = '', $category = '')
    {
        $this->where('board_id', $board_id);

        // 검색 조건 적용
        $this->_search_query($keyword, $field, $category);

        return $this->count_all_results($this->table_posts);
    }

    /**
     * 게시물 상세 보기
     */
    public function get_post($post_id)
    {
        return $this->get_where($this->table_posts, ['post_id' => $post_id])->row_array();
    }


    /**
     * 이전 게시글 조회 (post_id 기준)

     */
    public function get_prev_post(string $board_id, int $current_post_id): ?array
    {
        // 현재 글보다 ID가 작으면서 가장 큰 ID를 가진 글 = 이전 글
        return $this->where('board_id', $board_id)
            ->where('post_id <', $current_post_id)
            ->order_by('post_id', 'DESC')
            ->limit(1)
            ->get('posts')
            ->row_array();
    }

    /**
     * 다음 게시글 조회 (post_id 기준)
     */
    public function get_next_post(string $board_id, int $current_post_id): ?array
    {
        // 현재 글보다 ID가 크면서 가장 작은 ID를 가진 글 = 다음 글
        return $this->where('board_id', $board_id)
            ->where('post_id >', $current_post_id)
            ->order_by('post_id', 'ASC')
            ->limit(1)
            ->get('posts')
            ->row_array();
    }
    /**
     * 게시물 작성
     */
    public function save_post($data)
    {
        $this->insert($this->table_posts, $data);
        return $this->insert_id();
    }

    /**
     * 조회수 증가
     */
    public function increment_view($post_id)
    {
        $this->query("UPDATE {$this->table_posts} SET view_count = view_count + 1 WHERE post_id = ?", [$post_id]);
    }

    /**
     * 조회수 증가 (별칭)
     */
    public function increase_view_count($post_id)
    {
        return $this->increment_view($post_id);
    }

    /**
     * 최근 게시물
     */
    public function get_recent_posts($limit = 5, $board_id = null)
    {
        if ($board_id) {
            $this->where('board_id', $board_id);
        }
        $this->order_by('post_id', 'DESC');
        $this->limit($limit);
        $query = $this->get($this->table_posts);
        return $query->result_array();
    }

    // --- 첨부파일 관련 메서드 ---

    public function insert_files($data_batch)
    {
        if (empty($data_batch)) {
            return false;
        }
        return $this->insert_batch($this->table_files, $data_batch);
    }

    public function get_files($post_id)
    {
        $this->where('post_id', $post_id);
        $this->order_by('file_id', 'ASC');
        $query = $this->get($this->table_files);
        return $query->result_array();
    }

    // --- 댓글 관련 메서드 ---

    /**
     * 게시물의 댓글 목록 가져오기 (계층형 구조)
     */
    public function get_post_comments($post_id)
    {
        $this->where('post_id', $post_id);
        $this->order_by('comments_id', 'ASC');
        $query = $this->get($this->table_comments);
        $comments = $query->result();

        $tree = [];
        $refs = [];

        // 참조 배열 생성
        foreach ($comments as $c) {
            $c->replies = [];
            $refs[$c->comments_id] = $c;
        }

        // 트리 구조 조립
        foreach ($comments as $c) {
            if ($c->depth == 1 && $c->parent_id && isset($refs[$c->parent_id])) {
                $refs[$c->parent_id]->replies[] = $c;
            } else {
                $tree[] = $c;
            }
        }

        return $tree;
    }

    /**
     * 댓글 목록 가져오기 (별칭)
     */
    public function get_comments($post_id)
    {
        return $this->get_post_comments($post_id);
    }

    public function insert_comment($data)
    {
        return $this->insert($this->table_comments, $data);
    }

    public function get_comment($comments_id)
    {
        return $this->get_where($this->table_comments, ['comments_id' => $comments_id])->row_array();
    }

    public function delete_comment($comments_id)
    {
        return $this->where('comments_id', $comments_id)
            ->delete($this->table_comments);
    }

    /**
     * 게시글 수정
     */
    public function update_post($post_id, $data)
    {
        return $this->where('post_id', $post_id)
            ->update($this->table_posts, $data);
    }

    /**
     * 게시글 삭제
     */
    public function delete_post($post_id)
    {
        return $this->where('post_id', $post_id)
            ->delete($this->table_posts);
    }


    /**
     * 파일 ID로 단건 조회 (삭제 시 정보 확인용)
     */
    public function get_file($file_id)
    {
        return $this->get_where($this->table_files, ['file_id' => $file_id])->row_array();
    }

    /**
     * 파일 삭제 (DB)
     */
    public function delete_file($file_id)
    {
        return $this->where('file_id', $file_id)->delete($this->table_files);
    }


    /**
     * 최신 게시물 추출
     */
    public function get_latest_posts($board_id, $limit = 5)
    {
        // 1. 쿼리 빌더 설정
        $builder = $this->table('posts');

        $builder->select('*'); // 필요한 컬럼만 선택
        $builder->where('board_id', $board_id); //
        $builder->where('is_secret', 0);        // 비밀글 제외
        $builder->order_by('post_id', 'DESC');  // 최신순
        $builder->limit($limit);

        return $builder->get()->result_array();
    }


    // ==================================================================
    // [관리자용] 게시판 설정 관리 메서드 추가
    // ==================================================================

    /**
     * 모든 게시판 목록 가져오기 (관리자 리스트용)
     */
    public function get_all_boards()
    {
        $this->order_by('created_at', 'DESC');
        return $this->get($this->table_boards)->result_array();
    }

    /**
     * 게시판 설정 업데이트 (PK 수정 포함)
     * @param string $target_id  WHERE 조건에 들어갈 기존 ID
     * @param array  $data       SET 구문에 들어갈 변경 데이터
     */
    public function update_board($target_id, $data)
    {
        // 업데이트할 필드 정리
        $update_data = [
            'board_id'    => $data['board_id'],
            'title'       => $data['title'],
            'skin'        => $data['skin'] ?? 'basic',
            'read_level'  => (int)($data['read_level'] ?? 0),
            'write_level' => (int)($data['write_level'] ?? 0),
            'use_secret'  => (int)($data['use_secret'] ?? 0),
            'use_file'    => (int)($data['use_file'] ?? 0),
            'use_editor'  => $data['use_editor'] ?? 'minici',
        ];

        // WHERE board_id = '기존ID'
        return $this->where('board_id', $target_id)
            ->update($this->table_boards, $update_data);
    }

    /**
     * 새 게시판 생성
     */
    public function insert_board($data)
    {
        return $this->insert($this->table_boards, $data);
    }

    // Board_model.php 예시

    public function rename_board_table($old_board_id, $new_board_id)
    {
        // 테이블 접두사가 있다면 적용 (예: board_notice)
        $old_table = "board_{$old_board_id}";
        $new_table = "board_{$new_board_id}";

        // 1. 변경하려는 새 테이블명이 이미 존재하는지 확인
        // SHOW TABLES LIKE 쿼리를 사용하여 테이블 존재 여부 확인
        $check_sql = "SHOW TABLES LIKE '{$new_table}'";
        $exists = $this->db->query($check_sql)->fetch(); // 프레임워크 DB 방식에 맞게 수정

        if ($exists) {
            // 이미 테이블이 존재하면 특정 에러 코드나 false 반환
            return 'ALREADY_EXISTS';
        }

        // 2. 현재 테이블이 존재하는지 확인 (없으면 이름 변경 불가)
        $check_old_sql = "SHOW TABLES LIKE '{$old_table}'";
        $old_exists = $this->db->query($check_old_sql)->fetch();

        if (!$old_exists) {
            return 'OLD_TABLE_NOT_FOUND';
        }

        // 3. 테이블 이름 변경 실행
        // 트랜잭션 주의: DDL(RENAME TABLE)은 트랜잭션 롤백이 안 될 수 있으므로 주의
        $rename_sql = "RENAME TABLE `{$old_table}` TO `{$new_table}`";

        try {
            $this->db->execute($rename_sql);
            return true;
        } catch (Exception $e) {
            // 로그 기록 등
            return false;
        }
    }

    /**
     * 게시판 삭제
     */
    public function delete_board($board_id)
    {
        return $this->where('board_id', $board_id)
            ->delete($this->table_boards);
    }
}
