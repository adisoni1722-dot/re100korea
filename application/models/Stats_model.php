<?php
class Stats_model extends Model
{

    // 총 포인트 합계(보너스)
    public function total_point(): float
    {
        $query = $this->select_sum('amount', 'total_point')
            ->where('source_type', 'bonus')
            ->get('points');

        $row = $query->row_array();
        return (float) ($row['total_point'] ?? 0);
    }

    // 총 매출액
    public function total_in(): float
    {
        $query = $this->select_sum('final_price', 'price_in')
            ->get('orders');

        $row = $query->row_array();
        return (float) ($row['price_in'] ?? 0);
    }

    // 총 회원수
    public function total_members()
    {
        return $this->count_all('members') - 1; // 관리자 계정 제외
    }

    // 월간 매출 데이터 가져오기 (1~12월)
    public function get_monthly_sales_data(): array
    {
        $year = date('Y');

        $sql = "
        SELECT
            MONTH(created_at) AS month,
            SUM(final_price) AS total_price
        FROM orders
        WHERE YEAR(created_at) = ?
          AND status != 'pending'
        GROUP BY MONTH(created_at)
        ORDER BY MONTH(created_at)
    ";

        $query = $this->query($sql, [$year]);
        $rows  = $query->result_array();

        $data = array_fill(1, 12, 0);
        $max  = 0;

        foreach ($rows as $row) {
            $month        = (int) $row['month'];
            $value        = (float) $row['total_price'];
            $data[$month] = $value;
            $max          = max($max, $value);
        }

        // 0~200 범위 정규화
        if ($max > 0) {
            foreach ($data as $m => $v) {
                $data[$m] = round(($v / $max) * 200);
            }
        }

        return array_values($data);
    }

    public function get_monthly_sales_percent(): float
    {
        $year = date('Y');

        // 가장 최근 매출이 있는 달 찾기
        $sql_latest = "
        SELECT MAX(MONTH(created_at)) AS latest_month
        FROM orders
        WHERE YEAR(created_at) = ?
          AND status != 'pending'
    ";
        $latest_month = (int) ($this->query($sql_latest, [$year])->row()->latest_month ?? 0);

        if ($latest_month === 0) {
            return 0; // 데이터 없음
        }

        // 최근 달 매출
        $sql_month = "
        SELECT SUM(final_price) AS total
        FROM orders
        WHERE YEAR(created_at) = ?
          AND MONTH(created_at) = ?
          AND status != 'pending'
    ";
        $month_total = (float) $this->query($sql_month, [$year, $latest_month])->row()->total;

        // 연 매출
        $sql_year = "
        SELECT SUM(final_price) AS total
        FROM orders
        WHERE YEAR(created_at) = ?
          AND status != 'pending'
    ";
        $year_total = (float) $this->query($sql_year, [$year])->row()->total;

        return $year_total > 0 ? round(($month_total / $year_total) * 100, 1) : 0;
    }
}
