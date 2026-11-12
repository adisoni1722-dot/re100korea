<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * print_r2
 */
function print_r2($data)
{
    echo '<pre>';
    print_r($data);
    echo '</pre>';
}

/**
 * 브라우저 캐시 방지 버전
 */
if (!function_exists('ver')) {
    function ver(?string $file = null): string
    {
        if (ENVIRONMENT === 'development') {
            return date("ymdHis");
        }
        if ($file) {
            $full_path = FCPATH . ltrim($file, '/');
            if (file_exists($full_path)) {
                return (string) filemtime($full_path);
            }
        }
        return date("ymd");
    }
}

/**
 * 화폐 변환 (money_format 대체: intl 확장 우선, 없으면 number_format)
 */
function kmoney($num)
{
    if (class_exists('NumberFormatter')) {
        $fmt = new NumberFormatter('ko_KR', NumberFormatter::CURRENCY);
    $out = $fmt->formatCurrency((int)$num, 'KRW');
        if ($out !== false) {
            return $out;
        }
    }
    // fallback
    return '₩' . number_format((int)$num);
}

/**
 * 특정 테이블에서 insert/update용 컬럼 배열 (제외 컬럼 처리)
 */
if (!function_exists('get_columns')) {
    function get_columns(string $table, array $exclude = ['updated_at', 'deleted_at']): array
    {
        $db      = app('db');
        $columns = $db->list_fields($table);
        $columns = array_diff($columns, $exclude);
        return array_values($columns);
    }
}

/**
 * 테이블 컬럼 기준으로 POST 데이터 정리
 */
if (!function_exists('get_table')) {
    function get_table(string $table, array $exclude = ['updated_at', 'deleted_at']): array
    {
        $db      = app('db');
        $input   = app('input');

        $columns = $db->list_fields($table);
        $columns = array_diff($columns, $exclude);

        $data = [];
        foreach ($columns as $col) {
            $value = $input->post($col);
            if ($value !== null) {
                $data[$col] = ($value === '') ? null : $value;
            }
        }
        return $data;
    }
}

/**
 * 특정 테이블 insert/update 자동 처리
 */
if (!function_exists('save_table')) {
    function save_table(string $table, array $data, string $keyField = 'id', $keyValue = null)
    {
        $db = app('db');

        // [UPDATE] 수정
        if (!empty($keyValue)) {
            // ... (기존 UPDATE 로직 동일) ...
            if(isset($data[$keyField])) unset($data[$keyField]);
            // $data['updated_at'] = date('Y-m-d H:i:s');

            $db->where($keyField, $keyValue);
            $updated = $db->update($table, $data);
            return $updated ? $keyValue : false;
        }

        // [INSERT] 신규 생성
        else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $result = $db->insert($table, $data);
            if (!$result) return false;

            // 1. DB가 생성한 번호 가져오기
            $insert_id = $db->insert_id();

            // [CASE A: 문자열 PK]
            // DB가 번호를 안 만들었으면(0) -> 우리가 입력한 ID($data['board_id'])를 반환
            if (empty($insert_id) && isset($data[$keyField])) {
                return $data[$keyField];
            }

            // [CASE B: 숫자형 Auto Increment]
            // DB가 번호를 만들었으면(10, 11...) -> 그 번호를 반환
            return $insert_id;
        }
    }
}

/**
 * 휴대폰 포맷팅
 */
if (!function_exists('format_phone')) {
    function format_phone($number)
    {
        if (empty($number)) return '';
        $number = preg_replace('/\D/', '', $number);
        if (preg_match('/^(01[016789])(\d{3,4})(\d{4})$/', $number, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }
        return $number;
    }
}

/**
 * 로그인 체크
 */
if (!function_exists('loginCheck')) {
    function loginCheck()
    {
        $session = app('session');

        if (!$session->get('is_login')) {
            $redirect_url = urlencode(current_url());
            redirect('member/login?redirect=' . $redirect_url);
            return;
        }

        $member_id = $session->get('member_id');
        $name      = $session->get('name');
        $level     = $session->get('level');

        if (empty($member_id) || empty($name) || empty($level)) {
            alert("로그인 정보가 존재 하지 않습니다", '/member/login');
        }
    }
}

/**
 * 관리자 로그인 체크
 */
if (!function_exists('admin_logincheck')) {
    function admin_logincheck()
    {
        $session = app('session');

        if (!$session->get('is_login')) {
            alert("로그인 후 이용 가능합니다.", "/member/login");
        }

        $level = $session->get('level');
        if (!$level || (int)$level !== 10) {
            alert("관리자 권한이 필요합니다.", "/");
        }
    }
}

/**
 * 로그인 세션 설정/해제
 */
if (!function_exists('set_login_session')) {
    function set_login_session(array $member): void
    {
        $session = app('session');
        $session->set([
            'member_id' => $member['member_id'],
            'name'      => $member['name'],
            'level'     => $member['level'],
            'email'     => $member['email'] ?? '',
            'is_login'  => true,
        ]);
    }
}

if (!function_exists('clear_login_session')) {
    function clear_login_session(): void
    {
        $session = app('session');
        $session->unset_userdata(['member_id','name','level','email','is_login']);
    }
}

/**
 * alert + 이동
 */
if (!function_exists('alert')) {
    function alert(string $msg = '', string $url = ''): void
    {
        $charset = cfg('charset', 'UTF-8');

        if ($msg === '') {
            $msg = '올바른 방법으로 이용해 주십시오.';
        }

        $safe_msg = htmlspecialchars($msg, ENT_QUOTES | ENT_HTML5, $charset);
        $safe_msg = str_replace(["\r", "\n"], ['', ''], $safe_msg);

        echo "<meta http-equiv=\"content-type\" content=\"text/html; charset={$charset}\">";
        echo "<script type='text/javascript'>";
        echo "alert('{$safe_msg}');";

        if ($url) {
            // 가능하면 내부 URL만 허용하는 로직을 프로젝트에 맞게 추가하세요.
            $safe_url = htmlspecialchars($url, ENT_QUOTES, $charset);
            echo "location.replace('{$safe_url}');";
        } else {
            echo "history.go(-1);";
        }
        echo "</script>";
        exit;
    }
}

/**
 * 로그인 전 리퍼러 저장
 */
if (!function_exists('referer_save')) {
    function referer_save(): void
    {
        $input   = app('input');
        $session = app('session');

        $referer     = $input->server('HTTP_REFERER');
        $login_url   = site_url('member/login');
        $redirectRaw = (string)$input->get('redirect');
        $redirect    = $redirectRaw ? urldecode($redirectRaw) : '';

        if ($redirect) {
            if (strpos($redirect, base_url()) === 0) {
                $session->set('return_url', $redirect);
            }
        } else {
            if ($referer && strpos($referer, $login_url) === false) {
                $session->set('return_url', $referer);
            }
        }
    }
}

/**
 * ID 암복호화
 */
if (!function_exists('encrypt_id')) {
    function encrypt_id(?string $id)
    {
        if (empty($id)) {
            show_error('잘못된 접근입니다.', 400);
        }

        $key = (string) cfg('encryption_key', '');
        if ($key === '') {
            show_error('암호화 키가 설정되지 않았습니다.', 500);
        }

        $method = 'AES-128-CBC';
        $iv     = substr(hash('sha256', $key), 0, 16);

        $encrypted = openssl_encrypt($id, $method, $key, 0, $iv);
        if ($encrypted === false) {
            show_error('암호화 실패', 500);
        }

        $safe = strtr(base64_encode($encrypted), '+/', '-_');
        return rtrim($safe, '=');
    }
}

if (!function_exists('decrypt_id')) {
    function decrypt_id(?string $encrypted_id = null, ?string $mode = null)
    {
        $key = (string) cfg('encryption_key', '');
        if ($key === '') {
            show_error('암호화 키가 설정되지 않았습니다.', 500);
        }

        $method = 'AES-128-CBC';
        $iv     = substr(hash('sha256', $key), 0, 16);

        if ($mode !== 'pass' && empty($encrypted_id)) {
            show_error('잘못된 접근입니다. 값이 없습니다.', 400);
        }

        $input = strtr($encrypted_id ?? '', '-_', '+/');
        $mod4  = strlen($input) % 4;
        if ($mod4) {
            $input .= substr('====', $mod4);
        }

        $decoded = base64_decode($input, true);
        if ($decoded === false) {
            return $mode === 'pass' ? false : show_error('디코딩 실패', 400);
        }

        $decrypted = openssl_decrypt($decoded, $method, $key, 0, $iv);
        if ($decrypted === false) {
            return $mode === 'pass' ? false : show_error('복호화 실패', 400);
        }

        return $decrypted;
    }
}

/**
 * 날짜/시간 포맷
 */
if (!function_exists('format_datetime')) {
    function format_datetime(string $datetime): array
    {
        try {
            $dt = new DateTime($datetime);
            return ['date' => $dt->format('m.d'), 'time' => $dt->format('H:i')];
        } catch (Exception $e) {
            return ['date' => '', 'time' => ''];
        }
    }
}

/**
 * datetime 유틸
 */
function datetime($datetime, $option = 'time', $day = 'Y-m-d', $time = 'H:i:s')
{
    if (empty($datetime)) {
        $datetime = date('Y-m-d H:i:s');
    }

    $ts = strtotime($datetime);
    $ret = '';

    switch ($option) {
        case 'day':
            $ret = (date('Ymd', $ts) == date('Ymd')) ? date($time, $ts) : date($day, $ts);
            break;

        case 'social':
            $diff = time() - $ts;
            if ($diff < 60)         $ret = $diff . '초 전';
            elseif ($diff < 3600)   $ret = floor($diff / 60) . '분 전';
            elseif ($diff < 86400)  $ret = floor($diff / 3600) . '시간 전';
            elseif ($diff < 2592000)$ret = floor($diff / 86400) . '일 전';
            else                    $ret = date($day, $ts);
            break;

        default:
            $ret = (strtotime('+1 day', $ts) >= time()) ? date($time, $ts) : date($day, $ts);
            break;
    }
    return $ret;
}

function day_only($date)
{
    $parts = explode(' ', $date);
    return $parts[0] ?? $date;
}

function thumbnail_img($content)
{
    $result = $img_tag = [];
    $image  = '';

    preg_match_all('/<img[^>]+>/i', $content, $img_tag);
    if (!empty($img_tag[0])) {
        preg_match_all('/src=("[^"]*")/i', $img_tag[0][0], $result);
        $path  = str_replace('"', '', $result[1][0] ?? '');
        $pos   = strpos($path, 'data');
        if ($pos !== false) {
            $image = substr($path, $pos);
        }
    }
    return $image;
}


if (!function_exists('short_text')) {
    function short_text(string $text, int $limit = 40): string
    {
        $len = strlen($text);
        if ($len <= $limit) return $text;

        $half = floor(($limit - 3) / 2);
        return substr($text, 0, $half) . '...' . substr($text, -$half);
    }
}


if (!function_exists('short_end')) {
    function short_end(string $text, int $limit = 40): string
    {
        $len = strlen($text);
        if ($len <= $limit) return $text;

        return substr($text, 0, $limit) . '...';
    }
}
