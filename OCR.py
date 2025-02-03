import sys
import json
import csv
from azure.core.credentials import AzureKeyCredential
from azure.ai.formrecognizer import DocumentAnalysisClient

# Azure Form Recognizer のエンドポイントとキー
endpoint = "https://receipt23jn0344.cognitiveservices.azure.com/"
key = "Cjtruihe35VAHUmMTBvgN3ONukMrbBBlJYiKNBLJUKCQ56t3E45LJQQJ99BAACi0881XJ3w3AAALACOGkCCw"

# DocumentAnalysisClient の作成
document_analysis_client = DocumentAnalysisClient(
    endpoint=endpoint, credential=AzureKeyCredential(key)
)

# 結果を保存するリスト
results = []

# コマンドライン引数から画像パスを取得
image_paths = sys.argv[1:]

for receipt_path in image_paths:
    # レシート画像の読み込み
    with open(receipt_path, "rb") as f:
        receipt_image = f.read()

    try:
        # レシート解析の実行
        poller = document_analysis_client.begin_analyze_document(
            model_id="prebuilt-receipt", document=receipt_image
        )
        result = poller.result()

        # 商品情報と合計金額を抽出
        items = []
        total_amount = None

        # 解析結果からドキュメントを取得
        for document in result.documents:
            # 商品情報の抽出
            if "Items" in document.fields:
                for item in document.fields["Items"].value:
                    description = item.value["Description"].value if "Description" in item.value else "不明"
                    price = item.value["TotalPrice"].value if "TotalPrice" in item.value else "不明"
                    description = description.replace("軽", "").strip()
                    if price != "不明":
                        price = f"¥{int(price)}"
                    items.append(f"{description} {price}")

            # 合計金額を直接取得
            if "Total" in document.fields:
                total_amount = int(document.fields['Total'].value)
            else:
                print("合計金額が検出されませんでした。")

        # 結果を保存
        if items:
            items_display = ", ".join(items)
            if total_amount is not None:
                items_display += f", 合計 ¥{total_amount}"
            results.append({"file": receipt_path, "result": items_display})
        else:
            results.append({"file": receipt_path, "result": "商品情報が検出されませんでした。"})

    except Exception as e:
        results.append({"file": receipt_path, "result": f"エラーが発生しました: {e}"})

# 結果をJSON形式で出力（PHPで受け取るため）
print(json.dumps(results))

# 結果をCSVに保存
with open('results.csv', 'w', newline='', encoding='utf-8-sig') as csvfile:
    csvwriter = csv.writer(csvfile)
    csvwriter.writerow(['ファイル名', '結果'])
    for result in results:
        csvwriter.writerow([result['file'], result['result']])