<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/drivers/CI_Result.php';
/**
 * CodeIgniter 3 스타일 베이스 모델 클래스 (통합 버전)
 *
 */
class Model
{
    /** @var DB_Mysqli|DB_Pdo 실제 DB 연결 인스턴스 */
    public $driver;
    public $db;

    /** @var array 쿼리 빌더 조각 */
    protected $query_parts = [];

    /** @var bool 트랜잭션 활성화 여부 */
    protected $trans_enabled = false;
    protected $trans_status  = true;

    /**
     * 모델 생성자
     */
    public function __construct($driver = null)
    {
        // Loader가 드라이버를 안 넘겨줬을 때, 스스로 컨테이너에서 찾도록 복구
        if ($driver === null) {
            if (class_exists('Container') && Container::has('database')) {
                $driver = Container::get('database');
            }
        }

        // 여전히 못 찾았으면 그때 에러 발생
        if ($driver === null) {
            throw new \RuntimeException('Model requires a DB driver instance.');
        }

        if (!is_object($driver) || !method_exists($driver, 'query')) {
            throw new \InvalidArgumentException('Model requires a valid DB driver with query().');
        }

        $this->driver = $driver;
        $this->db     = $this; // CI3 호환
        $this->resetQuery();
    }

    public function __get(string $name)
    {
        if (Container::has($name)) {
            return Container::get($name);
        }

        if (Container::has('controller')) {
            $controller = Container::get('controller');
            if (property_exists($controller, $name)) {
                return $controller->$name;
            }
        }

        // 레거시 CI3 호환 ($CI =& get_instance();)
        if (function_exists('get_instance')) {
            $CI = get_instance();
            if (property_exists($CI, $name)) {
                return $CI->$name;
            }
        }

        return null;
    }

    /**
     * 쿼리 빌더 상태를 초기화합니다.
     */
    protected function resetQuery()
    {
        $this->query_parts = [
            'select'   => '*',
            'from'     => [],
            'join'     => [],
            'where'    => [],
            'group_by' => [],
            'having'   => [], // HAVING 조건을 WHERE와 유사하게 배열로 저장
            'order_by' => [],
            'limit'    => null,
            'offset'   => null,
            'set'      => [],
        ];
        return $this;
    }

    // --------------------------------------------------------------------
    // CI3 스타일 쿼리 빌더 메서드
    // --------------------------------------------------------------------

    /**
     * SELECT 필드를 설정합니다. 여러 번 호출 시 누적됩니다.
     */
    public function select($select = '*'): self
    {
        $current_select = $this->query_parts['select'];

        // 현재 '*'이거나 비어있으면 덮어쓰기, 아니면 쉼표로 연결하여 추가 (누적)
        if ($current_select === '*' || empty($current_select)) {
            $this->query_parts['select'] = $select;
        } else {
            // 기존 select가 문자열이 아니면 문자열로 변환
            if (!is_string($current_select)) {
                $current_select = implode(', ', (array) $current_select);
            }
            // 추가할 select가 배열이면 문자열로 변환
            if (is_array($select)) {
                $select = implode(', ', $select);
            }
            $this->query_parts['select'] = $current_select . ', ' . $select;
        }

        return $this;
    }

    public function from($from)
    {
        $this->query_parts['from'][] = $from;
        return $this;
    }
    public function where($key, $value = null, $escape = null)
    {
        return $this->_where($key, $value, 'AND');
    }
    public function or_where($key, $value = null, $escape = null)
    {
        return $this->_where($key, $value, 'OR');
    }
    public function where_in($key = null, $values = null)
    {
        return $this->_where_in($key, $values);
    }
    public function or_where_in($key = null, $values = null)
    {
        return $this->_where_in($key, $values, false, 'OR');
    }
    public function where_not_in($key = null, $values = null)
    {
        return $this->_where_in($key, $values, true);
    }
    public function or_where_not_in($key = null, $values = null)
    {
        return $this->_where_in($key, $values, true, 'OR');
    }
    public function like($field, $match = '', $side = 'both')
    {
        return $this->_like($field, $match, 'AND', $side);
    }
    public function or_like($field, $match = '', $side = 'both')
    {
        return $this->_like($field, $match, 'OR', $side);
    }


    // --- [추가] 그룹 쿼리(괄호) 메서드 ---

    /**
     * 괄호 시작: AND (
     */
    public function group_start()
    {
        $this->query_parts['where'][] = ['type' => 'group_start', 'connector' => 'AND'];
        return $this;
    }

    /**
     * 괄호 시작: OR (
     */
    public function or_group_start()
    {
        $this->query_parts['where'][] = ['type' => 'group_start', 'connector' => 'OR'];
        return $this;
    }

    /**
     * 괄호 시작: NOT (
     */
    public function not_group_start()
    {
        $this->query_parts['where'][] = ['type' => 'group_start', 'connector' => 'AND', 'not' => true];
        return $this;
    }

    /**
     * 괄호 시작: OR NOT (
     */
    public function or_not_group_start()
    {
        $this->query_parts['where'][] = ['type' => 'group_start', 'connector' => 'OR', 'not' => true];
        return $this;
    }

    /**
     * 괄호 종료: )
     */
    public function group_end()
    {
        $this->query_parts['where'][] = ['type' => 'group_end'];
        return $this;
    }

    public function join($table, $cond, $type = 'inner')
    {
        $type = strtoupper(trim($type));
        if (!in_array($type, ['LEFT', 'RIGHT', 'OUTER', 'INNER', 'LEFT OUTER', 'RIGHT OUTER'])) {
            $type = 'INNER';
        }
        $this->query_parts['join'][] = $type . ' JOIN ' . Database::protect_identifier($table) . ' ON ' . $cond;
        return $this;
    }

    public function order_by($orderby, $direction = '')
    {
        $direction                       = strtoupper(trim($direction));
        $direction                       = in_array($direction, ['ASC', 'DESC'], true) ? ' ' . $direction : '';
        $this->query_parts['order_by'][] = $orderby . $direction;
        return $this;
    }

    public function limit($value, $offset = '')
    {
        $this->query_parts['limit'] = (int) $value;
        if ($offset !== '') {
            $this->query_parts['offset'] = (int) $offset;
        }

        return $this;
    }

    public function group_by($by)
    {
        if (is_array($by)) {
            $this->query_parts['group_by'] = array_merge($this->query_parts['group_by'], $by);
        } else {
            $this->query_parts['group_by'][] = $by;
        }
        return $this;
    }

    public function having($key, $value = null)
    {
        return $this->_having($key, $value, 'AND');
    }

    public function or_having($key, $value = null)
    {
        return $this->_having($key, $value, 'OR');
    }

    protected function _having($key, $value = null, $type = 'AND')
    {
        if (!is_array($key)) {
            $key = [$key => $value];
        }

        foreach ($key as $k => $v) {
            $this->query_parts['having'][] = ['type' => $type, 'key' => $k, 'value' => $v];
        }

        return $this;
    }

    public function set($key, $value = '')
    {
        $key = is_array($key) ? $key : [$key => $value];
        foreach ($key as $k => $v) {
            $this->query_parts['set'][$k] = $v;
        }
        return $this;
    }

    // --------------------------------------------------------------------
    // 쿼리 실행 메서드
    // --------------------------------------------------------------------

    public function get($table = '', $limit = null, $offset = null): CI_Result
    {
        if ($table !== '') {
            $this->from($table);
        }

        if ($limit !== null) {
            $this->limit($limit, $offset);
        }

        [$sql, $params] = $this->_compile_select();
        return $this->query($sql, $params);
    }

    public function get_where($table = '', $where = null, $limit = null, $offset = null): CI_Result
    {
        if ($where !== null) {
            $this->where($where);
        }

        return $this->get($table, $limit, $offset);
    }

    public function count_all_results($table = ''): int
    {
        if ($table !== '') {
            $this->from($table);
        }

        $temp_limit    = $this->query_parts['limit'];
        $temp_offset   = $this->query_parts['offset'];
        $temp_order_by = $this->query_parts['order_by'];

        $this->query_parts['limit']    = null;
        $this->query_parts['offset']   = null;
        $this->query_parts['order_by'] = [];

        $temp_select                 = $this->query_parts['select'];
        $this->query_parts['select'] = 'COUNT(*) as count';

        [$sql, $params] = $this->_compile_select();

        $this->query_parts['select']   = $temp_select;
        $this->query_parts['limit']    = $temp_limit;
        $this->query_parts['offset']   = $temp_offset;
        $this->query_parts['order_by'] = $temp_order_by;

        $result = $this->query($sql, $params);
        $this->resetQuery();

        if ($result->num_rows() === 0) {
            return 0;
        }

        $row = $result->row_array();
        return (int) ($row['count'] ?? 0);
    }

    public function insert($table = '', $set = null): bool
    {
        if ($set !== null) {
            $this->set($set);
        }

        if (empty($this->query_parts['set']) || $table === '') {
            $this->_trans_failure();
            return false;
        }

        $keys           = array_keys($this->query_parts['set']);
        $protected_keys = array_map(['Database', 'protect_identifier'], $keys);
        $sql            = "INSERT INTO " . Database::protect_identifier($table) . " (" . implode(', ', $protected_keys) . ") VALUES (" . implode(', ', array_fill(0, count($keys), '?')) . ")";

        return $this->query($sql, array_values($this->query_parts['set']), false);
    }

    public function update($table = '', $set = null, $where = null, $limit = null): bool
    {
        if ($table === '') {
            return false;
        }
        if ($set !== null) {
            $this->set($set);
        }
        if ($where !== null) {
            $this->where($where);
        }
        if ($limit !== null) {
            $this->limit($limit);
        }
        if (empty($this->query_parts['set'])) {
            $this->_trans_failure();
            return false;
        }
        [$sql, $params] = $this->_compile_update($table);
        return $this->query($sql, $params, false);
    }

    public function delete($table = '', $where = '', $limit = null): bool
    {
        if ($table === '') {
            return false;
        }
        if ($where !== '') {
            $this->where($where);
        }
        if ($limit !== null) {
            $this->limit($limit);
        }
        [$sql, $params] = $this->_compile_delete($table);
        return $this->query($sql, $params, false);
    }

    public function insert_batch($table, $set = null): bool
    {
        if ($table === '' || !is_array($set) || empty($set)) {
            return false;
        }

        $keys           = array_keys(reset($set));
        $protected_keys = array_map(['Database', 'protect_identifier'], $keys);
        $placeholders   = '(' . implode(',', array_fill(0, count($keys), '?')) . ')';
        $sql            = "INSERT INTO " . Database::protect_identifier($table) . " (" . implode(', ', $protected_keys) . ") VALUES " . implode(', ', array_fill(0, count($set), $placeholders));

        $params = [];
        foreach ($set as $row) {
            $params = array_merge($params, array_values($row));
        }
        return $this->query($sql, $params, false);
    }

    public function query($sql, $binds = [], $return_object = true)
    {

        // ----------------------------------------------------------------
        // 보안 검증: 바인딩 없이 WHERE/AND/OR가 포함된 쿼리 차단
        // ----------------------------------------------------------------
        if (empty($binds) && preg_match('/\b(where|and|or)\b\s+[\w.`]+\s*(=|!=|<>|<|>|<=|>=|LIKE|IN|NOT IN)\s*(NULL|[\'"]?[\w\d]+[\'"]?)/i', $sql)) {
            // 개발 환경: 즉시 중단 및 에러 표시
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                die("Security Error: Raw query with WHERE clause must use parameter binding.<br><br>Query: " . htmlspecialchars($sql));
            }

            // 운영 환경: 트랜잭션 실패 처리 + 로그 기록
            $this->_trans_failure();
            if (function_exists('log_message')) {
                log_message('error', 'Insecure raw query blocked: ' . $sql);
            }

            return $return_object ? new CI_Result(false) : false;
        }

        // 보호: UPDATE / DELETE 에서 WHERE가 없으면 차단
        if (preg_match('/^\s*(UPDATE|DELETE)\b/i', $sql) && stripos($sql, 'where') === false) {
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                throw new Exception("Unsafe write query without WHERE: " . $sql);
            } else {
                // 운영환경에서는 트랜잭션 실패 처리 + 로깅
                $this->_trans_failure();
                if (function_exists('log_message')) {
                    log_message('error', 'Blocked unsafe write query without WHERE: ' . $sql);
                }
                return $return_object ? new CI_Result(false) : false;
            }
        }

        // ----------------------------------------------------------------
        // 실제 쿼리 실행
        // ----------------------------------------------------------------
        try {
            $result = $this->driver->query($sql, $binds);
            $res    = $return_object ? new CI_Result($result) : true;
        } catch (Exception $e) {
            $this->_trans_failure();

            throw $e;
        }

        // ----------------------------------------------------------------
        // 쿼리 실행 후 항상 초기화
        // ----------------------------------------------------------------
        $this->resetQuery();

        return $res;
    }

    public function last_query(): string
    {
        return $this->driver->last_query() ?? '';
    }
    public function insert_id()
    {
        return $this->driver->insert_id() ?? 0;
    }
    public function affected_rows()
    {
        return $this->driver->affected_rows() ?? 0;
    }

    // ... (트랜잭션 메소드 등 나머지 코드는 이전과 동일)
    public function trans_begin(): bool
    {
        if ($this->trans_enabled) {
            return false;
        }

        $this->trans_enabled = true;
        $this->trans_status  = true;

        if (method_exists($this->driver, 'beginTransaction')) {
            try {
                $this->driver->beginTransaction();
                return true;
            } catch (Exception $e) {
                $this->trans_enabled = false;
                return false;
            }
        }
        return false;
    }

    public function trans_start(): void
    {
        $this->trans_begin();
    }

    public function trans_commit(): bool
    {
        if (!$this->trans_enabled) {
            return false;
        }

        try {
            if (method_exists($this->driver, 'commit')) {
                $this->driver->commit();
            }
            $this->trans_enabled = false;
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function trans_rollback(): bool
    {
        if (!$this->trans_enabled) {
            return false;
        }

        try {
            if (method_exists($this->driver, 'rollBack')) {
                $this->driver->rollBack();
            }
            $this->trans_enabled = false;
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function trans_complete(): bool
    {
        if (!$this->trans_enabled) {
            return false;
        }

        if ($this->trans_status === false) {
            $this->trans_rollback();
            return false;
        }

        return $this->trans_commit();
    }

    public function trans_status(): bool
    {
        return $this->trans_status;
    }

    protected function _trans_failure(): void
    {
        if ($this->trans_enabled) {
            $this->trans_status = false;
        }
    }

    // --------------------------------------------------------------------
    // 내부 헬퍼 메서드 (쿼리 컴파일러)
    // --------------------------------------------------------------------
    protected function _where($key, $value, $type)
    {
        if (!is_array($key)) {
            $key = [$key => $value];
        }
        foreach ($key as $k => $v) {
            $this->query_parts['where'][] = ['type' => $type, 'key' => $k, 'value' => $v];
        }
        return $this;
    }

    protected function _where_in($key, $values, $not = false, $type = 'AND')
    {
        $operator                     = $not ? 'NOT IN' : 'IN';
        $this->query_parts['where'][] = ['type' => $type, 'key' => "{$key} {$operator}", 'value' => $values];
        return $this;
    }

    protected function _like($field, $match, $type, $side)
    {
        if ($side === 'before') {
            $match = "%{$match}";
        } elseif ($side === 'after') {
            $match = "{$match}%";
        } else {
            $match = "%{$match}%";
        }

        $this->query_parts['where'][] = ['type' => $type, 'key' => "{$field} LIKE", 'value' => $match];
        return $this;
    }

    /**
     * [수정됨] select()에 배열이 전달되어도 오류가 발생하지 않도록 수정된 최종 함수
     */
    protected function _compile_select($override_select = null): array
    {
        $select_str = $override_select ?? $this->query_parts['select'];

        // [핵심 수정] $select_str이 배열일 경우 문자열로 변환하여 오류를 방지합니다.
        if (is_array($select_str)) {
            $select_str = implode(', ', $select_str);
        }

        // 이제 $select_str은 항상 문자열이므로 explode가 안전하게 작동합니다.
        $select_parts     = array_map('trim', explode(',', $select_str));
        $protected_select = implode(', ', array_map(['Database', 'protect_identifier'], $select_parts));

        $from = implode(', ', array_map(['Database', 'protect_identifier'], $this->query_parts['from']));
        $sql  = 'SELECT ' . $protected_select . ' FROM ' . $from;

        if (!empty($this->query_parts['join'])) {
            $sql .= ' ' . implode(' ', $this->query_parts['join']);
        }

        $where_result = $this->_compile_wheres();
        $where_sql    = (string) ($where_result[0] ?? '');
        $params       = (array) ($where_result[1] ?? []);
        $sql .= $where_sql;

        if (!empty($this->query_parts['group_by'])) {
            $protected_group_by = implode(', ', array_map(['Database', 'protect_identifier'], $this->query_parts['group_by']));
            $sql .= ' GROUP BY ' . $protected_group_by;
        }

        $having_result = $this->_compile_havings();
        $having_sql    = (string) ($having_result[0] ?? '');
        $having_params = (array) ($having_result[1] ?? []);
        $sql .= $having_sql;
        if (!empty($having_params)) {
            $params = array_merge($params, $having_params);
        }

        if (!empty($this->query_parts['order_by'])) {
            $order_by_parts = [];
            foreach ($this->query_parts['order_by'] as $orderby) {
                // 'field desc' 같은 문자열 처리
                $parts            = preg_split('/\s+/', trim($orderby));
                $field            = Database::protect_identifier($parts[0]);
                $direction        = isset($parts[1]) ? ' ' . strtoupper($parts[1]) : '';
                $order_by_parts[] = $field . $direction;
            }
            $sql .= ' ORDER BY ' . implode(', ', $order_by_parts);
        }

        if ($this->query_parts['limit'] !== null) {
            $sql .= ' LIMIT ' . (int) $this->query_parts['limit'];
            if ($this->query_parts['offset'] !== null) {
                $sql .= ' OFFSET ' . (int) $this->query_parts['offset'];
            }
        }

        return [$sql, $params];
    }

    protected function _compile_havings(): array
    {
        if (empty($this->query_parts['having'])) {
            return ['', []];
        }
        $sql    = ' HAVING';
        $params = [];
        $first  = true;
        foreach ($this->query_parts['having'] as $cond) {
            if (!$first) {
                $sql .= ' ' . $cond['type'];
            }
            $first = false;
            preg_match('/([\w\.`]+)\s*(.*)/', $cond['key'], $matches);
            $key      = Database::protect_identifier($matches[1]);
            $operator = !empty($matches[2]) ? trim($matches[2]) : '=';
            if (is_array($cond['value'])) {
                if (empty($cond['value'])) {
                    $sql .= " 1=0";
                    continue;
                }
                $placeholders = implode(',', array_fill(0, count($cond['value']), '?'));
                $sql .= " {$key} {$operator} ({$placeholders})";
                $params = array_merge($params, $cond['value']);
            } else {
                $sql .= " {$key} {$operator} ?";
                $params[] = $cond['value'];
            }
        }
        return [$sql, $params];
    }

    protected function _compile_update($table): array
    {
        $params      = [];
        $set_clauses = [];
        foreach ($this->query_parts['set'] as $key => $val) {
            $set_clauses[] = Database::protect_identifier($key) . ' = ?';
            $params[]      = $val;
        }
        $sql                            = 'UPDATE ' . Database::protect_identifier($table) . ' SET ' . implode(', ', $set_clauses);
        list($where_sql, $where_params) = $this->_compile_wheres();
        $sql .= $where_sql;
        $params = array_merge($params, $where_params);
        if ($this->query_parts['limit'] !== null) {
            $sql .= ' LIMIT ' . $this->query_parts['limit'];
        }
        return [$sql, $params];
    }

    protected function _compile_delete($table): array
    {
        $sql                      = 'DELETE FROM ' . Database::protect_identifier($table);
        list($where_sql, $params) = $this->_compile_wheres();
        $sql .= $where_sql;
        if ($this->query_parts['limit'] !== null) {
            $sql .= ' LIMIT ' . $this->query_parts['limit'];
        }
        return [$sql, $params];
    }

    protected function _compile_wheres(): array
    {
        if (empty($this->query_parts['where'])) {
            return ['', []];
        }

        $sql_clauses     = [];
        $params          = [];

        // 트릭: 괄호가 열린 직후에는 AND/OR를 붙이지 않도록 제어하는 플래그
        $is_first_clause = true;
        // 현재 괄호가 막 열렸는지 상태 체크 (중첩 괄호 대응)
        $just_opened_group = false;

        foreach ($this->query_parts['where'] as $cond) {

            // 1. 그룹 시작 (괄호 열기) 처리
            if (isset($cond['type']) && $cond['type'] === 'group_start') {
                $connector = ($is_first_clause || $just_opened_group) ? '' : ($cond['connector'] ?? 'AND');
                $not       = (isset($cond['not']) && $cond['not'] === true) ? 'NOT ' : '';

                // 예: " AND NOT (" 또는 " ("
                $sql_clauses[] = trim($connector . ' ' . $not . '(');

                $just_opened_group = true; // 방금 괄호 열었음 표시
                $is_first_clause   = false;
                continue;
            }

            // 2. 그룹 종료 (괄호 닫기) 처리
            if (isset($cond['type']) && $cond['type'] === 'group_end') {
                $sql_clauses[] = ')';
                $just_opened_group = false; // 괄호 닫혔으니 리셋
                continue;
            }

            // 3. 일반 조건 처리 (기존 로직 개선)
            if (!is_array($cond)) continue;

            $key   = (string) ($cond['key'] ?? '');
            $value = $cond['value'] ?? null;
            $type  = $cond['type'] ?? 'AND'; // AND, OR

            // 연결자 결정: 첫 번째 조건이거나, 방금 괄호가 열렸다면 연결자 없음
            $connector = ($is_first_clause || $just_opened_group) ? '' : $type;

            // 일반 조건 파싱
            preg_match('/([\w\.`]+)\s*(.*)/', $key, $matches);
            $field    = Database::protect_identifier((string) ($matches[1] ?? ''));
            $operator = !empty($matches[2]) ? trim($matches[2]) : '=';

            $current_clause = '';

            if ($value === null) {
                if ($operator === '=') {
                    $current_clause = "{$field} IS NULL";
                } elseif ($operator === '!=' || $operator === '<>') {
                    $current_clause = "{$field} IS NOT NULL";
                } else {
                    $current_clause = "{$field} {$operator} ?";
                    $params[]       = $value;
                }
            } elseif (is_array($value)) {
                if (empty($value)) {
                    $current_clause = "1=0"; // 빈 배열 IN 조건 방어
                } else {
                    $placeholders   = implode(',', array_fill(0, count($value), '?'));
                    $current_clause = "{$field} {$operator} ({$placeholders})";
                    $params         = array_merge($params, array_values($value));
                }
            } else {
                $current_clause = "{$field} {$operator} ?";
                $params[]       = $value;
            }

            if (!empty($current_clause)) {
                // 연결자와 조건을 합쳐서 추가 (예: "AND title = ?")
                // trim을 해주는 이유는 첫 조건일 때 connector가 빈 문자열이라 공백이 생길 수 있어서임
                $clause_str = trim($connector . ' ' . $current_clause);
                $sql_clauses[] = $clause_str;

                $is_first_clause = false;
                $just_opened_group = false; // 조건이 하나라도 들어갔으니 '방금 열림' 상태 해제
            }
        }

        if (empty($sql_clauses)) {
            return ['', []];
        }

        return [' WHERE ' . implode(' ', $sql_clauses), $params];
    }

    public function list_fields(string $table): array
    {
        $fields = [];
        if (empty($table)) {
            return $fields;
        }

        try {
            $table  = Database::protect_identifier($table);
            $result = $this->driver->query("DESCRIBE {$table}");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $fields[] = $row['Field'];
                }
                if (method_exists($result, 'free')) {
                    $result->free();
                }
            }
        } catch (Exception $e) {
            // log_message('error', 'list_fields error: ' . $e->getMessage());
        }
        return $fields;
    }

    // ... (집계 함수 헬퍼 메소드 등 나머지 코드는 이전과 동일)
    public function select_max(string $select = '', string $alias = ''): self
    {
        return $this->_max_min_avg_sum($select, $alias, 'MAX');
    }

    public function select_min(string $select = '', string $alias = ''): self
    {
        return $this->_max_min_avg_sum($select, $alias, 'MIN');
    }

    public function select_avg(string $select = '', string $alias = ''): self
    {
        return $this->_max_min_avg_sum($select, $alias, 'AVG');
    }

    public function select_sum(string $select = '', string $alias = ''): self
    {
        return $this->_max_min_avg_sum($select, $alias, 'SUM');
    }

    protected function _max_min_avg_sum(string $select, string $alias, string $type): self
    {
        if ($alias === '') {
            $alias = $select;
        }

        $field  = Database::protect_identifier($select);
        $clause = "{$type}({$field})";

        if ($alias !== '') {
            $clause .= " AS " . Database::protect_identifier($alias);
        }

        $this->select($clause);

        return $this;
    }

    public function count_all($table)
    {
        if (empty($table)) {
            throw new InvalidArgumentException('Table name is required for count_all().');
        }

        $sql   = "SELECT COUNT(*) AS cnt FROM {$table}";
        $query = $this->query($sql);
        $row   = $query->row();

        return isset($row->cnt) ? (int) $row->cnt : 0;
    }

    public function table(string $name): self
    {
        $this->resetQuery();
        $this->from($name);
        return $this;
    }

    public function error(): array
    {
        if (method_exists($this->driver, 'error')) {
            return $this->driver->error();
        }

        return ['code' => 0, 'message' => 'DB driver does not support error()'];
    }

    /**
     * 전체 결과를 배열로 반환 (기존 호환)
     * @return array<int, object>
     */
    public function fetch_all($query)
    {
        if (!$query) {
            return [];
        }

        // mysqli_result 타입 처리
        if ($query instanceof mysqli_result) {
            $rows = [];
            while ($row = $query->fetch_object()) {
                $rows[] = $row;
            }
            $query->free_result();
            return $rows;
        }

        // PDOStatement 타입 처리
        if ($query instanceof PDOStatement) {
            return $query->fetchAll(PDO::FETCH_OBJ);
        }

        // CI_Result 타입 처리
        if (is_object($query) && method_exists($query, 'result')) {
            return $query->result();
        }

        return [];
    }

    /**
     * Generator 기반 결과 반복 처리 (대용량에 유리)
     * @example foreach ($this->db->fetch_generator($query) as $row) { ... }
     */
    public function fetch_generator($query): Generator
    {
        if (!$query) {
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                trigger_error('fetch_generator() called with invalid query', E_USER_WARNING);
            }
            return;
        }

        // mysqli_result 타입 처리
        if ($query instanceof mysqli_result) {
            while ($row = $query->fetch_object()) {
                yield $row;
            }
            $query->free_result();
            return;
        }

        // PDOStatement 타입 처리
        if ($query instanceof PDOStatement) {
            while ($row = $query->fetch(PDO::FETCH_OBJ)) {
                yield $row;
            }
            return;
        }

        // CI_Result 타입 처리
        if (is_object($query) && method_exists($query, 'result')) {
            foreach ($query->result() as $row) {
                yield $row;
            }
        }
    }

    /**
     * 쿼리 결과 전부 반환 (CI3 호환 result)
     * @return array<int, object>
     */
    public function result(): array
    {
        [$sql, $params] = $this->_compile_select();
        $query          = $this->query($sql, $params);
        $rows           = $this->fetch_all($query);
        $this->resetQuery();
        return $rows;
    }

    /**
     * Generator 기반 결과 반환 (메모리 절약)
     * @return Generator<object>
     */
    public function resultGenerator(): Generator
    {
        [$sql, $params] = $this->_compile_select();
        $query          = $this->query($sql, $params);
        yield from $this->fetch_generator($query);
        $this->resetQuery();
    }
}
