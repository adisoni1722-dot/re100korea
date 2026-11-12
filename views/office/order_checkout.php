<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>결제하기</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS 전체 코드 */
        button {
            font: inherit;
            border: none;
            background: none;
            cursor: pointer;
        }

        .btn-primary {
            background-color: var(--primary);
            color: var(--white);
            border-radius: 6px;
            padding: 8px 12px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-primary:hover {
            background-color: rgb(77, 15, 128);
        }

        .btn-secondary {
            background-color: #6c757d;
            color: var(--white);
            border-radius: 6px;
            padding: 8px 12px;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .payment-container {
            margin: 40px auto;
            padding: 32px;
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        .payment-alert {
            padding: 16px;
            margin-bottom: 24px;
            border-radius: 8px;
            background-color: rgba(23, 162, 184, 0.1);
            border: 1px solid rgba(23, 162, 184, 0.2);
            color: var(--info);
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .payment-alert i {
            font-size: 1.8rem;
        }

        .order-summary {
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border-color);
        }
        .summary-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 24px;
        }

        .product-item {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .product-item img {
            width: 70px;
            height: 70px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            object-fit: cover;
        }
        .product-details {
            flex-grow: 1;
        }
        .product-name {
            display: block;
            font-size: 1.6rem;
            font-weight: 600;
        }
        .product-quantity {
            display: block;
            font-size: 1.4rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }
        .product-price {
            font-size: 1.6rem;
            font-weight: 700;
        }

        .total-summary {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e9ecef;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1.5rem;
            color: var(--text-secondary);
        }
        .total-row + .total-row {
            margin-top: 8px;
        }
        .total-row.discount {
            color: var(--primary);
        }
        .total-row.final {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .payment-title {
            font-size: 2.4rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 32px;
        }
        .payment-method-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 32px;
        }
        @media (min-width: 768px) {
            .payment-method-list {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        .payment-method-item {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
        }
        .payment-method-item:hover {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-light);
        }
        .payment-method-item.is-active {
            border-color: var(--primary);
            background-color: var(--primary-light);
            font-weight: 700;
            color: var(--primary);
        }
        .payment-method-item.is-disabled {
            opacity: 0.6;
            cursor: not-allowed;
            background-color: #f8f9fa;
            color: var(--text-secondary);
        }
        .payment-method-item.is-disabled:hover {
            border-color: var(--border-color);
            box-shadow: none;
        }
        .payment-method-item i {
            font-size: 2.4rem;
            margin-bottom: 8px;
        }
        .payment-method-item span {
            font-size: 1.5rem;
        }

        .payment-submit-button:disabled {
            background-color: var(--text-secondary);
            cursor: not-allowed;
            opacity: 0.7;
        }

        .payment-details {
            display: none;
            padding: 20px;
            border-radius: 8px;
            background-color: var(--bg-light);
            border: 1px solid var(--border-color);
            margin-bottom: 32px;
        }
        .details-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .bank-account p, .point-info p {
            font-size: 1.5rem;
            line-height: 1.6;
            margin: 4px 0;
        }
        .bank-account strong, .point-info strong {
            min-width: 100px;
            display: inline-block;
        }
        .point-info p {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1.6rem;
            margin: 8px 0;
        }
        .point-info strong {
            font-weight: 600;
            color: var(--text-secondary);
        }
        .point-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--primary);
        }
        .details-notice {
            margin-top: 16px;
            font-size: 1.3rem;
            color: var(--text-secondary);
        }

        .payment-actions {
            display: none;
            gap: 12px;
        }
        .payment-actions.is-visible {
            display: flex;
        }

        .payment-submit-button,
        .payment-back-button {
            flex: 1;
            padding: 14px;
            font-size: 1.7rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?

        $user_points = context('get_balance'); // 사용자의 현재 포인트
        $total_payment_amount = $order['total_price'];
        $selected_method_from_db = $order['payment_method'];

        $alert_message = '';
        if ($selected_method_from_db === 'bank') {
            $alert_message = '현재 무통장 입금으로 선택되어 있습니다. 입금을 대기중 입니다.';
        }

        if ($selected_method_from_db === 'point') {
            $alert_message = '현재 포인트 결제로 선택되어 있습니다.';
        }
    ?>
    <div class="payment-container">
        <div class="order-summary">
            <h2 class="summary-title">주문 상품 정보</h2>
            <div class="product-item">
                <!-- <?= image_path($order['image_main']); ?> -->
                <div class="product-details">
                    <span class="product-name"><?=$order['product_name']?></span>
                    <span class="product-quantity">수량: 1개</span>
                </div>
                <span class="product-price"><?=number_format($order['product_price'])?>원</span>
            </div>
            <div class="total-summary">
                <div class="total-row"><span>총 상품 금액</span><span><?=number_format($order['product_price'])?>원</span></div>
                <div class="total-row discount"><span>-<?=number_format($order['total_discount'])?>원</span></div>
                <div class="total-row final"><span>최종 결제 금액</span><span><?=number_format($order['total_price'])?>원</span></div>
            </div>
        </div>

        <h1 class="payment-title">결제 수단 선택</h1>

        <? if (!empty($alert_message)): ?>
            <div class="payment-alert" id="payment-alert">
                <i class="fa-solid fa-circle-info"></i><span><?= $alert_message ?></span>
            </div>
        <? endif; ?>

        <div class="payment-method-list">
            <div class="payment-method-item <? if ($selected_method_from_db === 'bank') echo 'is-active'; ?>" data-method="bank">
                <i class="fa-solid fa-money-bill-wave"></i><span>무통장 입금</span>
            </div>
            <div class="payment-method-item <? if ($selected_method_from_db === 'point') echo 'is-active'; ?>" data-method="point">
                <i class="fa-solid fa-coins"></i><span>포인트 결제</span>
            </div>
            <div class="payment-method-item is-disabled" data-method="card">
                <i class="fa-regular fa-credit-card"></i><span>신용카드</span>
            </div>
            <div class="payment-method-item is-disabled" data-method="kakao">
                <i class="fa-solid fa-comment"></i><span>카카오페이</span>
            </div>
        </div>

        <div id="bank-info" class="payment-details" style="<? if ($selected_method_from_db === 'bank') echo 'display: block;'; ?>">
            <h2 class="details-title">입금 계좌 안내</h2>
            <div class="bank-account">
                <p><strong>은행명:</strong> 국민은행</p>
                <p><strong>계좌번호:</strong> 123-456-7890123</p>
                <p><strong>예금주:</strong> (주)당신의회사</p>
            </div>
        </div>

        <div id="point-info" class="payment-details" style="<? if ($selected_method_from_db === 'point') echo 'display: block;'; ?>">
            <h2 class="details-title">포인트 사용</h2>
            <div class="point-info">
                <p><strong>보유 포인트</strong> <span class="point-value"><?= number_format($user_points) ?> P</span></p>
                <p><strong>사용 포인트</strong> <span class="point-value"><?= number_format($total_payment_amount) ?> P</span></p>
            </div>
        </div>

        <div class="payment-actions <? if (!empty($selected_method_from_db)) echo 'is-visible'; ?>">
            <a href="javascript:history.back();" class="btn-secondary payment-back-button">뒤로가기</a>
            <button id="submit-button" class="btn-primary payment-submit-button">결제하기</button>
        </div>
    </div>

    <script>
        // 최종 수정된 JavaScript 전체 코드
        document.addEventListener('DOMContentLoaded', function () {
            // 1. 필요한 변수 및 HTML 요소 가져오기
            // ----------------------------------------------------
            const userPoints = <?= $user_points ?? 0 ?>;
            const totalAmount = <?= $total_payment_amount ?? 0 ?>;

            const paymentItems = document.querySelectorAll('.payment-method-item');
            const bankInfo = document.getElementById('bank-info');
            const pointInfo = document.getElementById('point-info');
            const submitButton = document.getElementById('submit-button');
            const paymentActions = document.querySelector('.payment-actions');
            const paymentAlert = document.getElementById('payment-alert');

            const initiallyActiveItem = document.querySelector('.payment-method-item.is-active');
            const initialMethod = initiallyActiveItem ? initiallyActiveItem.getAttribute('data-method') : null;


            // 2. UI 상태를 업데이트하는 핵심 함수 (로직 수정됨)
            // ----------------------------------------------------
            function updateUI(selectedMethod) {
                // --- 파트 1: 기본 UI 업데이트 (상세 정보창, 알림창) ---
                bankInfo.style.display = (selectedMethod === 'bank') ? 'block' : 'none';
                pointInfo.style.display = (selectedMethod === 'point') ? 'block' : 'none';

                const isInitialState = (selectedMethod === initialMethod);
                if (paymentAlert) {
                    paymentAlert.style.display = isInitialState ? 'flex' : 'none';
                }

                // --- 파트 2: 현재 선택에 따른 버튼 상태 결정 ---
                let buttonText = '';
                let isButtonDisabled = false;

                if (selectedMethod === 'bank') {
                    buttonText = '선택 완료';
                    isButtonDisabled = false;
                } else if (selectedMethod === 'point') {
                    if (totalAmount > userPoints) {
                        buttonText = '포인트 부족';
                        isButtonDisabled = true;
                    } else {
                        buttonText = '결제하기';
                        isButtonDisabled = false;
                    }
                } else {
                    buttonText = '결제 수단을 선택하세요';
                    isButtonDisabled = true;
                }

                // --- 파트 3: 페이지 첫 로드 상태일 경우, 버튼 상태 재정의 ---
                if (isInitialState) {
                    if (selectedMethod === 'bank') {
                        buttonText = '확인';
                        isButtonDisabled = true;
                    }
                }

                // --- 최종 적용 ---
                submitButton.textContent = buttonText;
                submitButton.disabled = isButtonDisabled;
            }


            // 3. 이벤트 리스너 설정
            // ----------------------------------------------------
            paymentItems.forEach(item => {
                item.addEventListener('click', function () {
                    if (this.classList.contains('is-disabled')) return;

                    paymentItems.forEach(el => el.classList.remove('is-active'));
                    this.classList.add('is-active');

                    const selectedMethod = this.getAttribute('data-method');
                    updateUI(selectedMethod);

                    paymentActions.classList.add('is-visible');
                });
            });

            submitButton.addEventListener('click', function() {
                if (this.disabled) return;
                const activeItem = document.querySelector('.payment-method-item.is-active');
                if (!activeItem) {
                    alert('결제 수단을 선택해주세요.');
                    return;
                }
                const currentMethod = activeItem.getAttribute('data-method');
                if (currentMethod === 'bank') {
                    alert('무통장입금으로 결제를 진행합니다.');
                    // window.location.href = '/office/payment/bank-process';
                } else if (currentMethod === 'point') {
                    // alert('포인트로 결제를 진행합니다.');
                    window.location.href = '/office/checkout/point/<?=$checkout?>';
                }
            });


            // 4. 페이지 첫 로드 시 UI 상태 초기화
            // ----------------------------------------------------
            updateUI(initialMethod);
        });
    </script>
</body>
</html>
