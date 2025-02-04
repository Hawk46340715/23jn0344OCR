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
        if (!is_uploaded_file($tmp_name)) continue;
        
        $file_name = basename($_FILES['receipts']['name'][$key]);
        $file_path = $upload_dir . $file_name;
        move_uploaded_file($tmp_name, $file_path);
        $file_paths[] = $file_path;
    }

    // ファイルが1つもアップロードされていない場合、エラーメッセージを表示
    if (empty($file_paths)) {
        die("エラー: ファイルがアップロードされていません。");
    }

    // OCR.pyを実行
    $output = shell_exec('python OCR.py ' . implode(' ', $file_paths) . ' 2>&1');
    file_put_contents('php_error.log', $output);

    $results = json_decode($output, true);
    if ($results === null) {
        die("エラー: OCRの解析に失敗しました。");
    }

    // OCRの結果をログに記録
    $log_file = 'ocr.log';
    $log_content = "OCR解析結果:\n\n";
    foreach ($results as $result) {
        $log_content .= "ファイル名: " . $result['file'] . "\n";
        $log_content .= "結果: " . $result['result'] . "\n\n";
    }
    file_put_contents($log_file, $log_content);

    // データベースに保存
    try {
        $serverName = "tcp:jn0344.database.windows.net,1433";
        $database = "jn230344db";
        $username = "jnsql";
        $password = 'Pa$$word1234';
        
        $conn = new PDO("sqlsrv:server=$serverName;Database=$database", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $conn->exec("CREATE TABLE IF NOT EXISTS ReceiptResults (
            id INT IDENTITY(1,1) PRIMARY KEY,
            file_name NVARCHAR(255),
            result NVARCHAR(MAX),
            created_at DATETIME DEFAULT GETDATE()
        )");
        
        $conn->beginTransaction();
        $stmt = $conn->prepare("INSERT INTO ReceiptResults (file_name, result) VALUES (:file_name, :result)");
        
        foreach ($results as $result) {
            $stmt->execute([
                ':file_name' => basename($result['file']),
                ':result' => $result['result']
            ]);
        }
        $conn->commit();
    } catch (PDOException $e) {
        $conn->rollBack();
        die("Database error: " . $e->getMessage());
    } finally {
        unset($conn);
    }

    // 結果を表示
    echo "<h2>解析結果</h2><table border='1'><tr><th>ファイル名</th><th>結果</th></tr>";
    foreach ($results as $result) {
        echo "<tr><td>{$result['file']}</td><td>{$result['result']}</td></tr>";
    }
    echo "</table><a href='download.php'>CSVをダウンロード</a><a href='{$log_file}' download>ocr.logをダウンロード</a>";
} else {
    echo "<h2>ファイルをアップロードしてください</h2><form method='post' enctype='multipart/form-data'><input type='file' name='receipts[]' multiple><input type='submit' value='アップロード'></form>";
}
?>
