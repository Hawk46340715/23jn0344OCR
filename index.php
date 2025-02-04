<?php
// アップロード処理と結果表示、CSVダウンロードを一つのファイルに統合
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['receipts']['tmp_name'][0])) {
    // アップロードディレクトリ
    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // アップロードされたファイルを保存
    $file_paths = [];
    foreach ($_FILES['receipts']['tmp_name'] as $key => $tmp_name) {
        $file_name = basename($_FILES['receipts']['name'][$key]);
        $file_path = $upload_dir . $file_name;
        move_uploaded_file($tmp_name, $file_path);
        $file_paths[] = $file_path;
    }

    // OCR.pyを実行
   $output = shell_exec('python OCR.py ' . implode(' ', $file_paths) . ' 2>&1');
    file_put_contents('php_error.log', $output);


    $output = shell_exec('/usr/bin/python3 /home/site/wwwroot/OCR.py ' . implode(' ', $file_paths) . ' 2>&1');
    file_put_contents('php_error.log', $output);

    $results = json_decode($output, true); // JSON形式で結果を受け取る

    // OCRの結果を読みやすい形式でocr.logに書き込む
    $log_file = 'ocr.log';
    $log_content = "OCR解析結果:\n\n";
    foreach ($results as $result) {
        $log_content .= "ファイル名: " . $result['file'] . "\n";
        $log_content .= "結果: " . $result['result'] . "\n\n";
    }
    file_put_contents($log_file, $log_content);
    // index.php内のPOST処理部分に以下を追加

    // Azure SQLへの接続処理
    $serverName = "tcp:jn0344.database.windows.net,1433";
    $database = "jn230344db";
    $username = "jnsql";
    $password = 'Pa$$word1234';  // 実際のパスワードに変更

    try {
        // PDO接続
        $conn = new PDO("sqlsrv:server=$serverName;Database=$database", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // テーブル作成（初回のみ）
        $conn->exec("
        IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='ReceiptResults' AND xtype='U')
        CREATE TABLE ReceiptResults (
            id INT IDENTITY(1,1) PRIMARY KEY,
            file_name NVARCHAR(255),
            result NVARCHAR(MAX),
            created_at DATETIME DEFAULT GETDATE()
        )
    ");

        // トランザクション開始
        $conn->beginTransaction();

        // プリペアドステートメント
        $stmt = $conn->prepare("
        INSERT INTO ReceiptResults (file_name, result) 
        VALUES (:file_name, :result)
    ");

        // 結果をデータベースに保存
        foreach ($results as $result) {
            $stmt->execute([
                ':file_name' => basename($result['file']),
                ':result' => $result['result']
            ]);
        }

        // コミット
        $conn->commit();
    } catch (PDOException $e) {
        // エラー処理
        $conn->rollBack();
        die("Database error: " . $e->getMessage());
    } finally {
        // 接続閉じる
        unset($conn);
    }

    // 結果を表示
    echo "
    <!DOCTYPE html>
    <html lang='ja'>
    <head>
        <meta charset='UTF-8'>
        <title>レシート解析結果</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f4f4f4;
                margin: 0;
                padding: 20px;
            }
            h2 {
                color: #333;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            table, th, td {
                border: 1px solid #ddd;
            }
            th, td {
                padding: 12px;
                text-align: left;
            }
            th {
                background-color: #f8f8f8;
            }
            tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            tr:hover {
                background-color: #f1f1f1;
            }
            a {
                display: inline-block;
                padding: 10px 20px;
                background-color: #28a745;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                margin-right: 10px;
            }
            a:hover {
                background-color: #218838;
            }
            form {
                background-color: white;
                padding: 20px;
                border-radius: 5px;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                max-width: 500px;
                margin: 50px auto;
            }
            input[type='file'] {
                margin-bottom: 20px;
            }
            input[type='submit'] {
                background-color: #007bff;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
            }
            input[type='submit']:hover {
                background-color: #0056b3;
            }
            .file-preview {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin-top: 20px;
            }
            .file-preview img {
                max-width: 200px;
                max-height: 200px;
                border: 2px solid #ddd;
                border-radius: 5px;
            }
        </style>
    </head>
    <body>
        <h2>解析結果</h2>
        <table>
            <tr><th>ファイル名</th><th>結果</th></tr>";
    foreach ($results as $result) {
        echo "<tr><td>{$result['file']}</td><td>{$result['result']}</td></tr>";
    }
    echo "
        </table>
        <a href='download.php'>CSVをダウンロード</a>
        <a href='{$log_file}' download>ocr.logをダウンロード</a>
    </body>
    </html>";
} else {
    // アップロードフォームを表示
    echo "
    <!DOCTYPE html>
    <html lang='ja'>
    <head>
        <meta charset='UTF-8'>
        <title>レシートアップロード</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f4f4f4;
                margin: 0;
                padding: 20px;
            }
            form {
                background-color: white;
                padding: 20px;
                border-radius: 5px;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                max-width: 500px;
                margin: 50px auto;
            }
            input[type='file'] {
                margin-bottom: 20px;
            }
            input[type='submit'] {
                background-color: #007bff;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
            }
            input[type='submit']:hover {
                background-color: #0056b3;
            }
            .file-preview {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin-top: 20px;
            }
            .file-preview img {
                max-width: 200px;
                max-height: 200px;
                border: 2px solid #ddd;
                border-radius: 5px;
            }
        </style>
    </head>
    <body>
        <form action='' method='post' enctype='multipart/form-data'>
            <input type='file' name='receipts[]' multiple accept='image/*' onchange='previewFiles()'>
            <input type='submit' value='アップロード'>
        </form>
        <div class='file-preview' id='file-preview'></div>
        <script>
            function previewFiles() {
                const preview = document.getElementById('file-preview');
                preview.innerHTML = '';
                const files = document.querySelector('input[type=file]').files;
                for (let i = 0; i < files.length; i++) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        const img = document.createElement('img');
                        img.src = event.target.result;
                        preview.appendChild(img);
                    };
                    reader.readAsDataURL(files[i]);
                }
            }
        </script>
    </body>
    </html>";
}
