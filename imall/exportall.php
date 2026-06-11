<?php
require 'vendor/autoload.php'; // Include the PhpSpreadsheet library

// Create a new Excel spreadsheet
$spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set column headers
$columns = array(
    'Company',
    'Contract #',
    'Stall #',
    'Date',
    'CheckNumber',
    'Total',
    'Payment Type#',
    'Transaction#',
    'Paid By',
    'Month1',
    'Charges1',
    'Amount1',
    'Month2',
    'Charges2',
    'Amount2',
    'Month3',
    'Charges3',
    'Amount3'
);

$columnIndex = 1;
foreach ($columns as $column) {
    $sheet->setCellValueByColumnAndRow($columnIndex, 1, $column);
    $columnIndex++;
}

// Fetch data from the database
include 'config.php';
$qry = $conn->query("SELECT * from `ar` order by (`transaction_id`) asc ");
$rowIndex = 2;

while ($row = $qry->fetch_assoc()) {
    $columnIndex = 1;
    foreach ($row as $value) {
        $sheet->setCellValueByColumnAndRow($columnIndex, $rowIndex, $value);
        $columnIndex++;
    }
    $rowIndex++;
}

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="AllCreatedAR.xlsx"');
header('Cache-Control: max-age=0');

// Save the Excel file to output
$writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
