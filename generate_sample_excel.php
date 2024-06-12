<?php
require_once 'vendor/autoload.php'; // PhpSpreadsheetのオートロード

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function generate_sample_excel() {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // ヘッダー行の設定
    $sheet->setCellValue('A1', '建物名');
    $sheet->setCellValue('B1', '部屋名');
    $sheet->setCellValue('C1', '日付');
    $sheet->setCellValue('D1', '利用可能開始時間');
    $sheet->setCellValue('E1', '利用可能終了時間');

    // サンプルデータの追加
    $sampleData = [
        ['Building A', 'Room 101', '2024-07-01', '08:00', '18:00'],
        ['Building A', 'Room 102', '2024-07-01', '09:00', '17:00'],
        ['Building B', 'Room 201', '2024-07-01', '08:30', '17:30'],
        ['Building B', 'Room 202', '2024-07-02', '08:00', '18:00'],
        ['Building C', 'Room 301', '2024-07-03', '09:00', '19:00']
    ];

    $row = 2;
    foreach ($sampleData as $data) {
        $sheet->setCellValue('A' . $row, $data[0]);
        $sheet->setCellValue('B' . $row, $data[1]);
        $sheet->setCellValue('C' . $row, $data[2]);
        $sheet->setCellValue('D' . $row, $data[3]);
        $sheet->setCellValue('E' . $row, $data[4]);
        $row++;
    }

    $writer = new Xlsx($spreadsheet);

    // ブラウザに出力するためのヘッダー設定
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="sample_facility_data.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer->save('php://output');
}

generate_sample_excel();