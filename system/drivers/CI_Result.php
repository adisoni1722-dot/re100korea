<?php
defined('BASEPATH') or exit('No direct script access allowed');
/**
 * CI_Result 클래스
 * CI3의 DB 결과 객체와 유사한 기능을 제공합니다. (안정성 강화 버전)
 */
class CI_Result
{
    /** @var PDOStatement|mysqli_result|false */
    protected $result;
    protected ?array $result_array = null;
    protected ?int $row_count      = null;
    protected bool $is_fetched     = false;

    public function __construct($result)
    {
        // 쿼리 실패로 false가 들어올 경우를 대비
        if ($result === false || ! ($result instanceof mysqli_result || $result instanceof PDOStatement)) {
            $this->result = false;
            return;
        }
        $this->result = $result;
    }

    public function result(): array
    {
        $rows = $this->result_array();
        return array_map(fn($r) => (object)$r, $rows);
    }

    /**
     * 모든 결과를 배열로 반환합니다.
     */
    public function result_array(): array
    {
        if ($this->result === false) {
            return [];
        }

        if ($this->result_array !== null) {
            return $this->result_array;
        }

        try {
            $this->result_array = ($this->result instanceof PDOStatement)
                ? $this->result->fetchAll(PDO::FETCH_ASSOC)
                : $this->result->fetch_all(MYSQLI_ASSOC);
            $this->is_fetched = true;
            return $this->result_array ?? [];
        } catch (Exception $e) {
            return []; // 예외 발생 시 빈 배열 반환
        }
    }

    /**
     * 결과의 첫 번째 행을 배열로 반환합니다.
     */
    public function row_array(): ?array
    {
        if ($this->result === false) {
            return null;
        }

        // 먼저 전체 결과를 가져왔다면 그 중 첫 번째를 반환
        if ($this->result_array !== null) {
            return $this->result_array[0] ?? null;
        }

        try {
            // 아직 전체 결과를 가져오지 않았다면, 효율적으로 한 행만 가져옴
            $row = ($this->result instanceof PDOStatement)
                ? $this->result->fetch(PDO::FETCH_ASSOC)
                : $this->result->fetch_assoc();
            return $row ?: null;
        } catch (Exception $e) {
            return null; // 예외 발생 시 null 반환
        }
    }

    /**
     * 결과의 첫 번째 행을 객체로 반환합니다.
     */
    public function row()
    {
        $row = $this->row_array();
        return $row ? (object) $row : null;
    }

    /**
     * 전체 행의 개수를 반환합니다.
     */
    public function num_rows(): int
    {
        if ($this->result === false) {
            return 0;
        }

        if ($this->row_count !== null) {
            return $this->row_count;
        }

        // mysqli는 num_rows를 직접 사용, PDO는 결과 배열의 크기로 계산
        if ($this->result instanceof mysqli_result) {
            return $this->row_count = $this->result->num_rows;
        } else {
            return $this->row_count = count($this->result_array());
        }
    }

    /**
     * 결과 리소스 메모리를 해제합니다.
     */
    public function free_result(): void
    {
        if ($this->result instanceof mysqli_result) {
            $this->result->free();
        }
        $this->result       = false;
        $this->result_array = null;
        $this->row_count    = null;
    }

    public function __destruct()
    {
        $this->free_result();
    }
}
