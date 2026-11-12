<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * UploadSimple (MiniCI / PHP 7.4 호환)
 * -------------------------------------------------------------
 * 기능:
 * - 일반 파일 및 이미지 업로드 지원
 * - encrypt_name 옵션 지원 (파일명 암호화)
 * - 이미지일 경우에만 리사이즈 수행
 * - 컨트롤러와 데이터 키 통일 (client_name, file_size 등)
 */
class UploadSimple
{
    protected $config = [
        'upload_path'   => './data/',
        // 일반 파일도 허용하도록 확장자 목록 확장
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'zip', 'txt', 'hwp', 'hwpx', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'],
        'max_size_kb'   => 20480, // 20MB
        'file_name'     => null,
        'encrypt_name'  => true,  // [추가] 파일명 암호화 여부

        // 리사이즈 설정
        'resize'        => false,
        'max_width'     => 1200,
        'quality'       => 85,
    ];

    protected $file_data = [];
    protected $error     = '';

    public function __construct(array $config = [])
    {
        // MiniCI library() 호출 시 2중 배열 평탄화
        if (isset($config[0]) && is_array($config[0])) {
            $config = $config[0];
        }

        $this->config = array_merge($this->config, $config);

        // 절대경로 보정 + 자동 생성
        $path = $this->config['upload_path'];

        // 폴더가 없으면 생성
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }

        // realpath는 경로가 존재해야 작동함
        $real = realpath($path);

        if ($real) {
            $this->config['upload_path'] = rtrim($real, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        } else {
            // realpath 실패 시(권한 등) 원본 경로 사용하되 구분자 정리
            $this->config['upload_path'] = rtrim($path, '/') . '/';
        }
    }

    /** -----------------------------------------------------------
     * 업로드 처리
     * ----------------------------------------------------------*/
    public function handle($field)
    {
        $this->file_data = [];
        $this->error     = '';

        if (!isset($_FILES[$field])) {
            $this->error = "업로드된 파일이 없습니다: {$field}";
            return false;
        }

        $file = $_FILES[$field];

        // 업로드 에러 코드 확인
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->error = '파일 업로드 에러 코드: ' . $file['error'];
            return false;
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            $this->error = '유효하지 않은 업로드 파일입니다.';
            return false;
        }

        // 사이즈 체크
        $size_kb = (int) round($file['size'] / 1024);
        if ($this->config['max_size_kb'] > 0 && $size_kb > $this->config['max_size_kb']) {
            $this->error = '파일 크기가 허용된 최대 용량을 초과했습니다.';
            return false;
        }

        $orig_name = $file['name'];
        $ext       = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

        if (!in_array($ext, $this->config['allowed_types'], true)) {
            $this->error = '허용되지 않은 파일 형식입니다. (' . $ext . ')';
            return false;
        }

        // ---------------------------------------------------------
        // [변경] 파일명 생성 로직 (encrypt_name 지원)
        // ---------------------------------------------------------
        if (!empty($this->config['encrypt_name']) && $this->config['encrypt_name'] === true) {
            // 랜덤한 파일명 생성 (충돌 방지)
            $base_name = date('YmdHis') . '_' . bin2hex(random_bytes(8));
        } else {
            // 원본 파일명 유지 (특수문자 제거)
            $base_name = $this->config['file_name']
                ? pathinfo($this->config['file_name'], PATHINFO_FILENAME)
                : pathinfo($orig_name, PATHINFO_FILENAME);

            // 한글/영문/숫자 외 제거
            $base_name = preg_replace('/[^\x{AC00}-\x{D7A3}a-zA-Z0-9-_]/u', '_', $base_name);
            // 중복 방지를 위해 시간 추가
            $base_name .= '_' . time();
        }

        $final_name  = $base_name . '.' . $ext;
        $destination = $this->config['upload_path'] . $final_name;

        // 이동
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $this->error = '파일 이동(저장) 실패. 폴더 권한을 확인하세요.';
            return false;
        }

        // MIME 타입 확인
        $mime = mime_content_type($destination) ?: $file['type'];
        $is_image = (strpos($mime, 'image/') === 0);

        // ---------------------------------------------------------
        // [수정] 이미지가 아닐 경우 삭제하던 로직 제거 -> 일반 파일도 허용
        // 리사이즈는 이미지일 때만 수행
        // ---------------------------------------------------------
        if ($is_image && !empty($this->config['resize'])) {
            $this->resizeImageWidthOnly($destination, $mime, $this->config['max_width']);
        }

        // ---------------------------------------------------------
        // [수정] 컨트롤러와 키 맞춤 (client_name, file_size 등)
        // ---------------------------------------------------------
        $this->file_data = [
            'file_name'    => $final_name,       // 저장된 파일명
            'client_name'  => $orig_name,        // 사용자가 올린 원본명 (DB origin_name용)
            'file_type'    => $mime,             // MIME 타입
            'file_path'    => $this->config['upload_path'], // 서버 절대 경로
            'full_path'    => $destination,      // 전체 경로
            'file_ext'     => '.' . $ext,
            'file_size'    => $file['size'],     // 바이트 단위 (DB file_size용)
            'is_image'     => $is_image
        ];

        return true;
    }

    /** -----------------------------------------------------------
     * 가로(width) 기준 리사이즈
     * ----------------------------------------------------------*/
    protected function resizeImageWidthOnly($path, $mime, $max_w)
    {
        // 이미지 정보 가져오기 실패 시 리턴
        $size = @getimagesize($path);
        if (!$size) return false;

        list($width, $height) = $size;

        // 이미 작으면 패스
        if ($width <= $max_w) {
            return true;
        }

        // 비율 계산
        $ratio = $max_w / $width;

        $new_w = $max_w;
        $new_h = (int)($height * $ratio);

        $src = $this->loadImage($path, $mime);
        if (!$src) {
            return false;
        }

        $dst = imagecreatetruecolor($new_w, $new_h);

        // PNG/WebP 투명도 처리
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_w, $new_h, $width, $height);

        $this->saveImage($dst, $path, $mime);

        imagedestroy($src);
        imagedestroy($dst);
        return true;
    }

    /** -----------------------------------------------------------
     * 이미지 읽기 (PHP 7.4 switch 방식)
     * ----------------------------------------------------------*/
    protected function loadImage($path, $mime)
    {
        switch ($mime) {
            case 'image/jpeg': return @imagecreatefromjpeg($path);
            case 'image/png':  return @imagecreatefrompng($path);
            case 'image/gif':  return @imagecreatefromgif($path);
            case 'image/webp': return @imagecreatefromwebp($path);
        }
        return null;
    }

    /** -----------------------------------------------------------
     * 이미지 저장 (PHP 7.4 switch 방식)
     * ----------------------------------------------------------*/
    protected function saveImage($res, $path, $mime)
    {
        switch ($mime) {
            case 'image/jpeg': imagejpeg($res, $path, $this->config['quality']); break;
            case 'image/png':  imagepng($res, $path); break;
            case 'image/gif':  imagegif($res, $path); break;
            case 'image/webp': imagewebp($res, $path, $this->config['quality']); break;
        }
    }

    public function data()
    {
        return $this->file_data;
    }

    public function error()
    {
        return $this->error;
    }
}
