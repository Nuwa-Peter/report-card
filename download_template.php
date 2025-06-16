<?php
ob_start(); // Start output buffering at the very beginning

// Ensure vendor/autoload.php exists
if (!file_exists('vendor/autoload.php')) {
    if (ob_get_length()) ob_end_clean();
    header("Content-Type: text/plain; charset=UTF-8");
    die('CRITICAL ERROR: Composer dependencies (vendor/autoload.php) not found. Please run "composer install". Template generation cannot proceed.');
}
require 'vendor/autoload.php';

// Corrected USE statements (single backslashes)
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

try {
    $spreadsheet = new Spreadsheet();

    // Ensure we are working with a clean, single sheet
    if ($spreadsheet->getSheetCount() > 0) {
        $spreadsheet->removeSheetByIndex(0); // Remove default sheet(s)
    }
    $worksheet = $spreadsheet->createSheet(); // Create our specific sheet
    $worksheet->setTitle('Subject_Template');

    // --- Populate the single sheet ---
    $subjectDisplayNameForCellA1 = 'SUBJECT NAME (e.g., ENGLISH)';
    $worksheet->getCell('A1')->setValue($subjectDisplayNameForCellA1);
    $worksheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

    $worksheet->getCell('B1')->setValue('BOT');
    $worksheet->getCell('C1')->setValue('MOT');
    $worksheet->getCell('D1')->setValue('EOT');
    $worksheet->getStyle('B1:D1')->getFont()->setBold(true);

    $exampleStudentNames = [
        'STUDENT NAME 1 (ALL CAPS)',
        'STUDENT NAME 2 (ALL CAPS)',
        'STUDENT NAME 3 (ALL CAPS)'
    ];
    $row = 2; // Data starts from row 2
    foreach ($exampleStudentNames as $name) {
        $worksheet->getCell('A' . $row)->setValue($name);
        $row++;
    }

    $worksheet->getCell('E1')->setValue('NOTE: Enter student names in ALL CAPS. Replace "SUBJECT NAME" in A1 with actual subject.');
    $worksheet->getStyle('E1')->getFont()->setBold(true)->getColor()->setARGB('FF808080');

    $worksheet->getColumnDimension('A')->setWidth(35);
    $worksheet->getColumnDimension('B')->setWidth(12);
    $worksheet->getColumnDimension('C')->setWidth(12);
    $worksheet->getColumnDimension('D')->setWidth(12);
    $worksheet->getColumnDimension('E')->setWidth(60);

    $worksheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
    $worksheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
    $worksheet->getPageMargins()->setTop(0.75);
    $worksheet->getPageMargins()->setRight(0.7);
    $worksheet->getPageMargins()->setLeft(0.7);
    $worksheet->getPageMargins()->setBottom(0.75);
    // --- End populating single sheet ---

    $spreadsheet->setActiveSheetIndex(0); // Ensure our created sheet is active

    if (ob_get_length()) ob_end_clean(); // Clean any potential stray output before headers

    $filename = "Single_Subject_Marks_Entry_Template_" . date('Y-m-d') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    if (ob_get_length()) ob_end_clean();
    header("Content-Type: text/plain; charset=UTF-8");
    error_log("Error generating single sheet Excel template: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\nStack Trace:\n" . $e->getTraceAsString());
    die("ERROR: Could not generate the Excel template due to an internal server error. Please contact the administrator. Error details have been logged. Message: " . htmlspecialchars($e->getMessage()));
}
?>
