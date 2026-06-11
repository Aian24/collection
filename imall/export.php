<?php
// Include your database connection file
ob_start(); // Start output buffering
include 'config.php';
session_start();

require 'vendor/autoload.php'; // Include the Composer autoloader

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


$showYesterday = isset($_GET['show_yesterday']) && $_GET['show_yesterday'] === 'true';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=imall", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($showYesterday) {
        // Fetch transactions for yesterday
        $stmt = $pdo->prepare("SELECT * FROM ar WHERE DATE(date) = CURDATE() - INTERVAL 1 DAY");
        $filename = 'Yesterday-Transaction.xlsx';
    } else {
        // Fetch transactions for today
        $stmt = $pdo->prepare("SELECT * FROM ar WHERE DATE(date) = CURDATE()");
        $filename = 'Today-Transaction.xlsx';
    }

    $stmt->execute();
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Create a new spreadsheet
    $spreadsheet = new Spreadsheet();

    // Add a worksheet
    $worksheet = $spreadsheet->getActiveSheet();

    // Add headers to the worksheet
    $headers = [ 'Company','Contract','Stall','Date','Checknumber','Total','Payment Method','Transaction','PaidBy','Month1', 'Charges1', 'Amount1', 'Month2', 'Charges2', 'Amount2', 'Month3', 'Charges3', 'Amount3'];
    $column = 'A';
    foreach ($headers as $header) {
        $worksheet->setCellValue($column . '1', $header);
        $column++;
    }

    // Add data to the worksheet
    $row = 2;
    foreach ($reports as $report) {
        $column = 'A';
        foreach ($report as $value) {
            $worksheet->setCellValue($column . $row, $value);
            $column++;
        }
        $row++;
    }

    // Create a writer and save the spreadsheet to a file
    $writer = new Xlsx($spreadsheet);
    $writer->save($filename);

    // Provide the download link for the Excel file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer->save('php://output');
    exit;
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
