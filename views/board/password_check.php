<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>비밀번호 확인</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: #f5f7fa;
            font-family: -apple-system, sans-serif;
        }

        .pass-box {
            background: white;
            padding: 40px 30px;
            width: 100%;
            max-width: 400px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        h3 {
            margin-bottom: 10px;
            color: #333;
        }

        p {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
        }

        input {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
        }

        .btn-group {
            display: flex;
            gap: 10px;
        }

        button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-submit {
            background: #333;
            color: white;
        }

        .btn-cancel {
            background: #eee;
            color: #333;
        }
    </style>
</head>

<body>
    <div class="pass-box">
        <h3><?= $title_text ?></h3>
        <p><?= $desc_text ?></p>

        <form action="<?= $action ?>" method="POST">
            <?= csrf_field() ?>

            <input type="password" name="password_check" required placeholder="비밀번호" autofocus>

            <div class="btn-group">
                <button type="button" class="btn-cancel" onclick="history.back()">취소</button>
                <button type="submit" class="btn-submit">확인</button>
            </div>
        </form>
    </div>
</body>

</html>
