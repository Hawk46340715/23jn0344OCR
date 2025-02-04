import sys
import json
import csv
import traceback
from azure.core.credentials import AzureKeyCredential
from azure.ai.formrecognizer import DocumentAnalysisClient

import os

endpoint = os.getenv("https://receipt23jn0344.cognitiveservices.azure.com/")
key = os.getenv("Cjtruihe35VAHUmMTBvgN3ONukMrbBBlJYiKNBLJUKCQ56t3E45LJQQJ99BAACi0881XJ3w3AAALACOGkCCw")

document_analysis_client = DocumentAnalysisClient(
    endpoint=endpoint, credential=AzureKeyCredential(key)
)

results = []
image_paths = sys.argv[1:]

for receipt_path in image_paths:
    try:
        with open(receipt_path, "rb") as f:
            receipt_image = f.read()

        poller = document_analysis_client.begin_analyze_document(
            model_id="prebuilt-receipt", document=receipt_image
        )
        result = poller.result()

        items = []
        total_amount = None

        for document in result.documents:
            if "Items" in document.fields:
                for item in document.fields["Items"].value:
                    description = item.value["Description"].value if "Description" in item.value else "不明"
                    price = item.value["TotalPrice"].value if "TotalPrice" in item.value else "不明"
                    items.append(f"{description} ¥{int(price) if price != '不明' else '不明'}")

            if "Total" in document.fields:
                total_amount = int(document.fields['Total'].value)

        items_display = ", ".join(items) if items else "商品情報なし"
        if total_amount is not None:
            items_display += f", 合計 ¥{total_amount}"

        results.append({"file": receipt_path, "result": items_display})

    except Exception as e:
        error_msg = f"エラー: {e}\n{traceback.format_exc()}"
        results.append({"file": receipt_path, "result": error_msg})
        with open('ocr_error.log', 'a', encoding='utf-8') as error_log:
            error_log.write(error_msg + "\n")

print(json.dumps(results))
