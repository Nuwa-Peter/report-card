<?php
// download_template.php

// Ensure vendor/autoload.php exists
if (!file_exists('vendor/autoload.php')) {
    // In a real app, you might want a more user-friendly error page or message.
    die('Composer dependencies not installed. Please run "composer install". This is required to generate the Excel template.');
}
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

$spreadsheet = new Spreadsheet();

// Remove the default sheet created
$spreadsheet->removeSheetByIndex(0);

$subjectsForTemplate = [
    'ENGLISH' => 'English',
    'MATHEMATICS' => 'Mathematics (MTC)', // Using full name for sheet, key for internal use
    'RELIGIOUS_EDUCATION' => 'Religious Education (R.E)',
    'LITERACY_I' => 'Literacy I',
    'LITERACY_II' => 'Literacy II',
    'LOCAL_LANGUAGE' => 'Local Language',
    'SCIENCE' => 'Science',
    'SOCIAL_STUDIES' => 'Social Studies (SST)',
    'KISWAHILI' => 'Kiswahili'
];

$exampleStudentNames = [
    'STUDENT NAME 1 (ALL CAPS)',
    'STUDENT NAME 2 (ALL CAPS)',
    'STUDENT NAME 3 (ALL CAPS)'
];

foreach ($subjectsForTemplate as $sheetTitleKey => $subjectDisplayNameForCellA1) {
    $worksheet = $spreadsheet->createSheet();
    // Ensure sheet titles are valid (max 31 chars, no invalid chars like * ? : \ / [ ])
    $safeSheetTitle = substr(preg_replace('/[*?:\\\/\[\]]/', '', $sheetTitleKey), 0, 31);
    $worksheet->setTitle($safeSheetTitle);

    // Set Subject Name in A1
    $worksheet->getCell('A1')->setValue($subjectDisplayNameForCellA1); // What process_excel expects in A1
    $worksheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

    // Set Headers in B1, C1, D1
    $worksheet->getCell('B1')->setValue('BOT');
    $worksheet->getCell('C1')->setValue('MOT');
    $worksheet->getCell('D1')->setValue('EOT');
    $worksheet->getStyle('B1:D1')->getFont()->setBold(true);

    // Add example student names
    $row = 2;
    foreach ($exampleStudentNames as $name) {
        $worksheet->getCell('A' . $row)->setValue($name);
        // Optionally add placeholder for marks e.g. $worksheet->getCell('B'.$row)->setValue(0);
        $row++;
    }

    // Add a note about student names in ALL CAPS in a prominent place, e.g., E1
    $worksheet->getCell('E1')->setValue('NOTE: Enter all student names in ALL CAPS.');
    $worksheet->getStyle('E1')->getFont()->setBold(true)->getColor()->setARGB('FF808080'); // Grey color

    // Set column widths
    $worksheet->getColumnDimension('A')->setWidth(35); // Student Name
    $worksheet->getColumnDimension('B')->setWidth(12); // BOT
    $worksheet->getColumnDimension('C')->setWidth(12); // MOT
    $worksheet->getColumnDimension('D')->setWidth(12); // EOT
    $worksheet->getColumnDimension('E')->setWidth(40); // Note column

    // Page setup (optional, but good for printing the template itself if needed)
    $worksheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
    $worksheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
    $worksheet->getPageMargins()->setTop(0.75);
    $worksheet->getPageMargins()->setRight(0.7);
    $worksheet->getPageMargins()->setLeft(0.7);
    $worksheet->getPageMargins()->setBottom(0.75);
}

// Set active sheet to the first one
if ($spreadsheet->getSheetCount() > 0) {
    $spreadsheet->setActiveSheetIndex(0);
} else {
    // This should not happen if $subjectsForTemplate is not empty
    $spreadsheet->createSheet()->setTitle('Instructions'); // Fallback sheet
    $spreadsheet->getActiveSheet()->getCell('A1')->setValue('Error: No subject sheets were created for the template.');
}


// Set HTTP headers for download
$filename = "Marks_Entry_Template_" . date('Y-m-d') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
// If you're serving to IE 9, then the following may be needed
header('Cache-Control: max-age=1');
// If you're serving to IE over SSL, then the following may be needed
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
header('Pragma: public'); // HTTP/1.0

$writer = new Xlsx($spreadsheet);
try {
    $writer->save('php://output');
    exit;
} catch (PhpOffice\PhpSpreadsheet\Exception $e) {
    // Log error
    error_log("Error generating Excel template: " . $e->getMessage());
    die("Error generating Excel template. Please check server logs. Message: " . $e->getMessage());
}

?>
