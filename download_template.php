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
    $worksheet->getCell('A1')->setValue('LIN');
    $worksheet->getCell('B1')->setValue('Names/Name');
    $worksheet->getCell('C1')->setValue('BOT');
    $worksheet->getCell('D1')->setValue('MOT');
    $worksheet->getCell('E1')->setValue('EOT');
    $worksheet->getStyle('A1:E1')->getFont()->setBold(true);

    // Add subject name placeholder in a noticeable position, e.g., A2, merged or styled
    $worksheet->mergeCells('A2:E2'); // Merge cells for the subject name instruction
    $worksheet->getCell('A2')->setValue('SUBJECT NAME (e.g., ENGLISH) - Replace this row with your first student\'s data or delete if not needed.');
    $worksheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
    $worksheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);


    $exampleStudentData = [
        ['LIN_EXAMPLE_1', 'STUDENT NAME 1 (ALL CAPS)'], // LIN, Name
        ['', 'STUDENT NAME 2 (ALL CAPS)'], // Empty LIN, Name
        ['LIN_EXAMPLE_3', 'STUDENT NAME 3 (ALL CAPS)']  // LIN, Name
    ];
    $row = 3; // Data starts from row 3
    foreach ($exampleStudentData as $student) {
        $worksheet->getCell('A' . $row)->setValue($student[0]); // LIN
        $worksheet->getCell('B' . $row)->setValue($student[1]); // Name
        // BOT, MOT, EOT will be empty for the template
        $row++;
    }

    $worksheet->getCell('F1')->setValue('NOTE: Enter student names in ALL CAPS. Ensure LIN is provided if available, otherwise leave blank. The row with "SUBJECT NAME" should be replaced or deleted.');
    $worksheet->getStyle('F1')->getFont()->setBold(true)->getColor()->setARGB('FF808080');

    $worksheet->getColumnDimension('A')->setWidth(20); // LIN
    $worksheet->getColumnDimension('B')->setWidth(35); // Names/Name
    $worksheet->getColumnDimension('C')->setWidth(12); // BOT
    $worksheet->getColumnDimension('D')->setWidth(12); // MOT
    $worksheet->getColumnDimension('E')->setWidth(12); // EOT
    $worksheet->getColumnDimension('F')->setWidth(70); // Note column

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
