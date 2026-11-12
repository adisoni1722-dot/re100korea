<?php

declare(strict_types=1);

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Form Service (Validator + Form Helper Integrated)
 * -------------------------------------------------------------
 * - CI3 Form_validation  호환
 * - 유효성 검사 로직과 폼 값 유지/에러 출력 기능
 * -------------------------------------------------------------
 */
class Form
{
    protected Input $input;
    protected Security $security;
    protected ?Csrf $csrf;

    // === Validator 속성 통합 ===
    protected array $rules           = [];
    protected array $errors          = [];
    protected array $custom_messages = [];
    protected array $data            = []; // 검증 대상 데이터 (기본 $_POST)

    protected array $allowed_rules = [
        'required',
        'min_length',
        'max_length',
        'valid_email',
        'is_numeric',
        'alpha_numeric',
        'korean',
        'regex_match',
        'trim', // trim은 전처리용이지만 룰에 포함 가능
    ];

    public function __construct(Input $input, Security $security, ?Csrf $csrf = null)
    {
        $this->input    = $input;
        $this->security = $security;
        $this->csrf     = $csrf;
    }

    /**
     * 규칙 설정 (CI3 호환)
     */
    public function set_rules(string $field, string $label, string $rules): self
    {
        // 규칙 문자열 파싱 (pipe 분리)
        $ruleArray = explode('|', $rules);

        $this->rules[] = [
            'field' => $field,
            'label' => $label,
            'rules' => $ruleArray,
        ];
        return $this;
    }

    /**
     * 커스텀 에러 메시지 설정
     */
    public function set_message(string $rule, string $message): self
    {
        $this->custom_messages[$rule] = $message;
        return $this;
    }

    /**
     * 검증 실행
     */
    public function run(?array $data = null): bool
    {

        $method = strtolower($this->input->method());
        if (!in_array($method, ['post', 'put', 'patch'], true)) {
            return false;
        }

        $this->errors = [];
        $data         = $data ?? $this->input->post();

        foreach ($this->rules as $item) {
            $field = $item['field'];
            $label = $item['label'];
            $value = $data[$field] ?? '';

            foreach ($item['rules'] as $ruleRaw) {
                $rule  = trim(strtolower($ruleRaw));
                $param = null;

                if (preg_match('/(.*?)\[(.*?)\]/', $rule, $m)) {
                    $rule  = trim(strtolower($m[1]));
                    $param = $m[2];
                }

                if (!in_array($rule, $this->allowed_rules, true) || !method_exists($this, $rule)) {
                    continue;
                }

                if (!$this->{$rule}($value, $param)) {
                    $this->set_error($field, $rule, $label, $param);
                    break;
                }
            }
        }

        return empty($this->errors);
    }

    /**
     * 에러 등록 (내부용)
     */
    protected function set_error(string $field, string $rule, string $label, $param = null): void
    {
        if (isset($this->errors[$field])) {
            return;
        }

        $message = $this->custom_messages[$rule] ?? $this->get_default_message($rule, $label, $param);
        $message = str_replace(['{field}', '{param}'], [$label, (string) $param], $message);
        $this->errors[$field] = $message;
    }

    protected function get_default_message(string $rule, string $label, $param = null): string
    {
        $messages = [
            'required'      => "{$label}은(는) 필수 입력 항목입니다.",
            'min_length'    => "{$label}은(는) 최소 {$param}자 이상이어야 합니다.",
            'max_length'    => "{$label}은(는) 최대 {$param}자까지 입력 가능합니다.",
            'valid_email'   => "{$label} 형식이 올바르지 않습니다.",
            'is_numeric'    => "{$label}은(는) 숫자만 입력 가능합니다.",
            'alpha_numeric' => "{$label}은(는) 영문과 숫자만 입력 가능합니다.",
            'regex_match'   => "{$label} 형식이 올바르지 않습니다.",
            'korean'        => "{$label}은(는) 한글만 입력 가능합니다.",
        ];
        return $messages[$rule] ?? "{$label}이(가) 올바르지 않습니다.";
    }

    // === Form Helper Methods ===

    /**
     * 입력값 유지 (set_value)
     */
    public function set_value(string $field, $default = ''): string
    {
        // 1. 검증 데이터($this->data)에 있으면 우선 사용 (run 실행 후)
        if (isset($this->data[$field])) {
            $val = $this->data[$field];
        } else {
            // 2. 없으면 POST에서 직접 조회
            $val = $this->input->post($field);
        }

        return htmlspecialchars((string) ($val ?? $default), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Select 박스 상태 유지
     * <option value="a" <?php echo set_select('menu', 'a'); ?>>A</option>
     */
    public function set_select(string $field, string $value = '', bool $default = false): string
    {
        return $this->_check_state($field, $value, $default, 'selected');
    }

    /**
     * Checkbox 상태 유지
     * <input type="checkbox" name="agree" value="yes" <?php echo set_checkbox('agree', 'yes'); ?>>
     */
    public function set_checkbox(string $field, string $value = '', bool $default = false): string
    {
        return $this->_check_state($field, $value, $default, 'checked');
    }

    /**
     * Radio 버튼 상태 유지
     * <input type="radio" name="gender" value="M" <?php echo set_radio('gender', 'M'); ?>>
     */
    public function set_radio(string $field, string $value = '', bool $default = false): string
    {
        return $this->_check_state($field, $value, $default, 'checked');
    }

    /**
     * 내부 상태 확인 로직
     */
    protected function _check_state(string $field, string $value, bool $default, string $attribute): string
    {
        // 1. 검증된 데이터($this->data)에 값이 있는지 확인 (set_rules 실행 후)
        if (isset($this->data[$field])) {
            $input = $this->data[$field];
        } else {
            // 2. 없으면 POST 데이터 직접 조회
            $input = $this->input->post($field);
        }

        // 3. 입력값이 아예 없는 경우 (페이지 첫 진입) -> $default(기본값) 사용
        if ($input === null) {
            return ($default === true) ? " {$attribute}=\"{$attribute}\"" : '';
        }

        // 4. 입력값과 현재 옵션값($value) 비교
        $value = (string) $value;

        // 4-1. 배열 입력 (예: name="options[]" multiple)
        if (is_array($input)) {
            $input = array_map('strval', $input); // 문자열로 변환하여 비교
            return in_array($value, $input, true) ? " {$attribute}=\"{$attribute}\"" : '';
        }

        // 4-2. 단일 입력
        return ((string) $input === $value) ? " {$attribute}=\"{$attribute}\"" : '';
    }

    /**
     * 단일 에러 메시지 반환 (form_error)
     */
    public function error(string $field, string $prefix = '', string $suffix = ''): string
    {
        $msg = $this->errors[$field] ?? '';
        if ($msg === '') {
            return '';
        }
        // XSS 방어
        $safeMsg = htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return $prefix . $safeMsg . $suffix;
    }

    /**
     * 전체 에러 배열 반환 (API 응답용)
     */
    public function error_array(): array
    {
        return $this->errors;
    }

    /**
     * 전체 에러 문자열 (validation_errors)
     */
    public function validation_errors(string $prefix = '', string $suffix = ''): string
    {
        $out = '';
        foreach ($this->errors as $err) {
            $safeMsg = htmlspecialchars($err, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $out .= $prefix . $safeMsg . $suffix . "\n";
        }
        return $out;
    }

    // === Validation Rules (Protected) ===

    protected function required($str): bool
    {
        return is_array($str) ? !empty($str) : trim((string) $str) !== '';
    }

    protected function min_length($str, $val): bool
    {
        return is_numeric($val) && mb_strlen((string) $str) >= (int) $val;
    }

    protected function max_length($str, $val): bool
    {
        return is_numeric($val) && mb_strlen((string) $str) <= (int) $val;
    }

    protected function valid_email($str): bool
    {
        return filter_var($str, FILTER_VALIDATE_EMAIL) !== false;
    }

    protected function is_numeric($str): bool
    {
        return is_numeric($str);
    }

    protected function alpha_numeric($str): bool
    {
        return ctype_alnum((string) $str);
    }

    protected function korean($str): bool
    {
        return (bool) preg_match('/^[가-힣]+$/u', (string) $str);
    }

    protected function regex_match($str, $pattern): bool
    {
        if ((string)$str === '') return true;
        if ((string)$pattern === '') return false;

        $norm = trim($pattern);
        if ($norm[0] !== '/') {
            $body = str_replace('/', '\/', $norm);
            $norm = "/{$body}/u";
        } else {
            // u 플래그 보정
            if (strpos(substr($norm, strrpos($norm, '/') + 1), 'u') === false) {
                $norm .= 'u';
            }
        }
        return (bool) preg_match($norm, (string)$str);
    }
}
