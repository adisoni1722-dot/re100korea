<!DOCTYPE html>
<html lang="ko">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>마이오피스 - <?= $header['title'] ?? '' ?></title>

  <?= load_asset('css/common.min.css') ?>
  <?= load_asset('css/layout.css') ?>
  <?= load_asset('css/auto') ?>
  <style>
    .tans-page {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }

    .card {
      width: 100%;
      background-color: var(--white);
      border-radius: 12px;
      border: 1px solid var(--border-color);
      padding: 16px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
    }

    .form-group {
      margin-bottom: 24px;
    }

    .form-label {
      display: block;
      font-size: 1.6rem;
      font-weight: 600;
      color: var(--text-primary);
      margin-bottom: 12px;
    }

    .input-group {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .form-control {
      width: 100%;
      padding: 14px 16px;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      font-size: 1.6rem;
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    .form-control:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(138, 0, 252, 0.1);
    }

    .btn-full {
      width: 100%;
      padding: 16px;
      font-size: 1.7rem;
      font-weight: 700;
    }

    /* 요약 영역 스타일 */
    .transfer-summary {
      background-color: var(--bg-light);
      border-radius: 8px;
      padding: 20px;
      margin-top: 24px;
      margin-bottom: 32px;
    }

    .summary-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 1.5rem;
    }

    .summary-item:not(:last-child) {
      margin-bottom: 12px;
    }

    .summary-item__label {
      color: var(--text-secondary);
    }

    .summary-item__value {
      font-weight: 600;
      color: var(--text-primary);
    }

    .summary-divider {
      border: none;
      border-top: 1px solid var(--border-color);
      margin: 12px 0;
    }

    .summary-total .summary-item__label {
      font-weight: 700;
      color: var(--text-primary);
    }

    .summary-total .summary-item__value {
      font-weight: 700;
      color: var(--primary);
      font-size: 2rem;
    }

    .form-actions {
      display: flex;
      gap: 12px;
      /* 버튼 사이 간격 */
    }

    .btn-secondary {
      background-color: var(--bg-light);
      border-color: var(--border-color);
      color: var(--text-primary);
    }
  </style>
</head>

<body>
  <div class="tans-page">
    <div class="card">
      <form action="<?= $action_url ?>" id="transfer-form" method="post">
        <?= csrf_field(); ?>
        <div class="form-group">
          <label for="receiver_id" class="form-label">받는 사람 ID</label>
          <input type="text" id="receiver_id" name="receiver_id" class="form-control" value="<?= htmlspecialchars($receiver_id ?? '', ENT_QUOTES) ?>" placeholder=" 받을 분의 ID 또는 암호화 주소 입력하세요">
        </div>

        <div class="form-group">
          <label for="transfer-amount" class="form-label">보낼 포인트</label>
          <div class="input-group">
            <input type="number" id="transfer-amount" name="amount" class="form-control" placeholder="0" />
          </div>
        </div>

        <div class="transfer-summary">
          <div class="summary-item">
            <span class="summary-item__label">보내는 포인트</span>
            <span id="amount-display" class="summary-item__value">0 P</span>
          </div>
          <div class="summary-item">
            <span class="summary-item__label">수수료</span>
            <span id="fee-display" class="summary-item__value">0 P</span>
          </div>
          <hr class="summary-divider" />
          <div class="summary-item summary-total">
            <span class="summary-item__label">총 차감 포인트</span>
            <span id="total-display" class="summary-item__value">0 P</span>
          </div>
        </div>
        <div class="form-actions">
          <button type="button" class="btn-secondary btn-full" onclick="history.back()">뒤로가기</button>
          <button type="submit" class="btn-primary btn-full">보내기</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // --- 설정 변수 ---
    const userTotalPoints = <?= context('get_balance') ?>;
    const transferFee = 500; // [수정] 이체 수수료
    const minTransferAmount = 1000; // [신규] 최소 전송 금액

    // --- DOM 요소 가져오기 ---
    const transferForm = document.getElementById('transfer-form');
    const recipientInput = document.getElementById('receiver_id');
    const amountInput = document.getElementById('transfer-amount');
    const amountDisplay = document.getElementById('amount-display');
    const feeDisplay = document.getElementById('fee-display');
    const totalDisplay = document.getElementById('total-display');

    // --- 함수 정의 ---
    function formatNumber(number) {
      return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function updateSummary() {
      let amount = parseInt(amountInput.value, 10) || 0;
      let currentFee = amount >= minTransferAmount ? transferFee : 0; // 최소 금액 이상일 때만 수수료 부과
      let totalDeducted = amount + currentFee;

      amountDisplay.textContent = formatNumber(amount) + ' P';
      feeDisplay.textContent = formatNumber(currentFee) + ' P';
      totalDisplay.textContent = formatNumber(totalDeducted) + ' P';
    }

    // --- 이벤트 리스너 ---
    amountInput.addEventListener('input', updateSummary);



    transferForm.addEventListener('submit', function(event) {
      event.preventDefault(); // 기본 제출을 막고, 검증 로직을 먼저 실행

      const receiver_id = recipientInput.value.trim();
      const amount = parseInt(amountInput.value, 10) || 0;
      const totalDeducted = amount + (amount >= minTransferAmount ? transferFee : 0);

      if (receiver_id === '') {
        alert('받는 사람 ID를 입력해주세요.');
        recipientInput.focus();
        return;
      }

      if (amount < minTransferAmount) {
        alert(`최소 전송 금액은 ${formatNumber(minTransferAmount)} P 입니다.`);
        amountInput.focus();
        return;
      }

      if (totalDeducted > userTotalPoints) {
        alert('보유 포인트가 부족합니다. (수수료 포함)');
        amountInput.focus();
        return;
      }

      // 모든 검증 통과 시, AJAX로 폼을 전송
      const formData = new FormData(transferForm);
      fetch(transferForm.action, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: formData,
        })
        .then(response => response.json())
        .then(data => {

          // CSRF 토큰 갱신 (name 또는 id가 data.token.name인 input 모두 갱신)
          if (data.token) {
            document.querySelectorAll('input[name="' + data.token.name + '"], input[id="' + data.token.name + '"]').forEach(function(csrfInput) {
              csrfInput.value = data.token.value;
            });
          }

          if (data.success) {
            alert(data.message || '이체가 완료되었습니다.');
            window.location.href = data.payload.redirect_url;
          } else {
            alert(data.message || '이체에 실패했습니다.');
          }
        })
        .catch((error) => {
          // 서버에서 JSON이 아닌 응답이 오거나 네트워크 오류일 때만 서버 오류 메시지 출력
          alert('서버 오류로 이체에 실패했습니다.');
        });
    });

    // 페이지 로드 시 초기 요약 상태 설정
    updateSummary();
  </script>
</body>

</html>
