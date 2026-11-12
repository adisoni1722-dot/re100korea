<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Wallet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;700&display=swap');

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Noto Sans KR', sans-serif;
            color: #ffffff;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .content-container {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 24px; /* 요소 간 간격을 조금 줄여 로고와 더 잘 어울리게 조정 */
            padding: 40px 24px;
            box-sizing: border-box;
            min-height: 100vh;
        }

        /* === 1. 로고 (새로 추가) === */
        .company-logo {
            height: 36px; /* 로고 높이 지정 (SVG는 비율에 맞게 너비 자동 조절) */
            max-width: 120px; /* 로고가 너무 커지는 것을 방지 */
        }

        /* === 2. QR 코드 섹션 === */
        .qr-code-wrapper {
            background-color: rgba(255, 255, 255, 1);
            padding: 10px;
            border-radius: 28px;
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.15);
            transition: transform 0.3s ease;
        }
        .qr-code-wrapper:hover {
            transform: scale(1.03);
        }
        .qr-code-wrapper img {
            display: block;
            width: 150px;
            height: 150px;
            border-radius: 18px;
        }

        /* === 3. 사용자 정보 섹션 === */
        .user-info {
            text-align: center;
        }
        .user-info__name {
            margin: 0 0 8px 0;
            font-size: 2.2rem;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .user-info__helper {
            margin: 0;
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.8);
        }

        /* === 4. 주소 및 복사 버튼 섹션 === */
        .address-group {
            width: 100%;
            max-width: 380px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 10px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .address-group__string {
            font-family: 'Courier New', Courier, monospace;
            font-size: 1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-right: 16px;
        }
        .address-group__copy-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
            background-color: rgba(255, 255, 255, 0.4);
            border: none;
            border-radius: 10px;
            padding: 8px 14px;
            color: #fff;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        .address-group__copy-btn:hover {
            background-color: rgba(255, 255, 255, 0.6);
        }
    </style>
</head>
<body>
    <main class="content-container">
        <img class="company-logo" src="/assets/images/logo-white.svg" alt="Company Logo">
        <div class="qr-code-wrapper">
            <img src="<?= $qrcode ?>" alt="Wallet QR Code">
        </div>

        <div class="user-info">
            <h1 class="user-info__name"><?=session('name')?></h1>
            <p class="user-info__helper">QR 코드를 스캔하여 이체 하세요.</p>
        </div>

        <div class="address-group">
            <span class="address-group__string" id="walletAddress"><?=$my_addr?></span>
            <button class="address-group__copy-btn" onclick="copyAddress(this)">
                <i class="fa-regular fa-copy"></i>
                <span>복사</span>
            </button>
        </div>

    </main>

    <script>
        function copyAddress(buttonElement) {
            const addressText = document.getElementById('walletAddress').innerText;
            navigator.clipboard.writeText(addressText).then(() => {
                const originalIcon = buttonElement.innerHTML;
                buttonElement.innerHTML = `<i class="fa-solid fa-check"></i> <span>복사됨!</span>`;
                setTimeout(() => {
                    buttonElement.innerHTML = originalIcon;
                }, 2000);
            }).catch(err => {
                console.error('주소 복사에 실패했습니다.', err);
                alert('주소 복사에 실패했습니다.');
            });
        }
    </script>

</body>
</html>
