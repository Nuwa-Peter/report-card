<?php
session_start();

// Check if Composer's autoloader exists
if (!file_exists('vendor/autoload.php')) {
    $_SESSION['error_message'] = 'Composer dependencies not installed. Please run "composer install". This is required for PDF generation.';
    header('Location: index.php');
    exit;
}
require 'vendor/autoload.php';

if (!isset($_SESSION['report_data']) || empty($_SESSION['report_data']['students'])) {
    $_SESSION['error_message'] = 'No report data found to generate PDF. Please process student data first.';
    header('Location: index.php');
    exit;
}

$reportData = $_SESSION['report_data'];
$allStudentsData = $reportData['students'];
$classInfo = $reportData['class_info'];
// Other data like teacherInitials, generalRemarks, etc., will be accessed within the buffered report_card.php

// Prepare mPDF
// Basic configuration, can be extended (e.g., for custom fonts)
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_left' => 10,
    'margin_right' => 10,
    'margin_top' => 10,
    'margin_bottom' => 10,
    'margin_header' => 5,
    'margin_footer' => 5,
    'default_font_size' => 10,
    'default_font' => 'arial' // Using a common font
]);

// It's often good to set a title for the PDF document
$pdfFileName = 'Report_Cards_' . htmlspecialchars($classInfo['class'] ?? 'Class') . '_' . htmlspecialchars($classInfo['term'] ?? 'Term') . '_' . htmlspecialchars($classInfo['year'] ?? 'Year') . '.pdf';
$mpdf->SetTitle('Report Cards - ' . htmlspecialchars($classInfo['class'] ?? '') . ' Term ' . htmlspecialchars($classInfo['term'] ?? '') . ' ' . htmlspecialchars($classInfo['year'] ?? ''));
$mpdf->SetAuthor('Maria Owembabazi Primary School');
// $mpdf->SetDisplayMode('fullpage'); // How the PDF opens

// Loop through each student and generate their report card HTML
foreach ($allStudentsData as $studentKey => $studentToDisplay) {
    // Set up a variable that report_card.php can use to identify the current student
    // This way, report_card.php doesn't need to know it's being called by generate_pdf.php
    // We simulate the $_GET variable or pass it in a way report_card.php expects.

    // To make report_card.php reusable, we'll pass the specific student data it needs.
    // We'll capture the output of report_card.php
    ob_start();

    // Make $studentToDisplay available to the included file.
    // Also, $reportData (containing classInfo, teacherInitials etc.) should be available.
    // report_card.php already uses $studentToDisplay, $classInfo, $teacherInitials etc. from $reportData.
    // We need to ensure report_card.php uses the *current* student in the loop.

    // Temporarily set a global or pass a variable that report_card.php can check
    // to know which student's data to use.
    // Let's modify report_card.php slightly to accept a student's data directly as a parameter
    // when included, rather than always picking the first from session.

    // For now, we'll assume report_card.php is modified to use a variable $_CURRENT_STUDENT_FOR_PDF
    // This is a bit of a hack. A cleaner way is to refactor report_card.php into a function.
    $_SESSION['current_student_for_pdf_key'] = $studentKey; // Pass the key

    include 'report_card.php'; // This will output the HTML for the current student

    $html = ob_get_clean();
    $mpdf->WriteHTML($html);

    // Add a page break after each student, unless it's the last one
    // mPDF handles page breaks with CSS (page-break-after: always in report_card.php style)
    // but an explicit AddPage can be used if needed for more control.
    // The CSS 'page-break-after: always' on the .report-card-container in report_card.php should handle this.
}
unset($_SESSION['current_student_for_pdf_key']); // Clean up

// Output the PDF
try {
    $mpdf->Output($pdfFileName, 'D'); // 'D' for download, 'I' for inline
    exit;
} catch (\Mpdf\MpdfException $e) {
    $_SESSION['error_message'] = "MPDF Error: " . $e->getMessage();
    // Log the error properly in a real application
    // error_log("MPDF Error: " . $e->getMessage());
    header('Location: index.php');
    exit;
}
?>
