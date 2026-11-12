<?php
defined('BASEPATH') or exit('No direct script access allowed');

return [
    // 기본 컨트롤러를 여기서 지정합니다.
    'default_controller' => 'Start',

    // 404 발생 시 호출할 컨트롤러 (비워두면 기본 404 페이지)
    '404_override' => '',

    // ----------------------------------------------------
    // URL 라우팅 패턴
    // ----------------------------------------------------

    // 이미지 업로드 라우트 추가 (와일드카드 패턴보다 위에 있어야 함)
    'board/upload_image'             => 'board/upload_image',

    // 게시판 기본 동작
    'board/(:any)/list'              => 'board/list/$1',
    'board/(:any)/view/(:num)'       => 'board/view/$1/$2',

    // 글 쓰기 (신규)
    'board/(:any)/write'             => 'board/write/$1',

    // 글 수정 (숫자 세그먼트가 붙으면 수정 모드)
    'board/(:any)/write/(:num)'      => 'board/write/$1/$2',

    // 글 삭제
    'board/(:any)/delete/(:num)'     => 'board/delete/$1/$2',
];
