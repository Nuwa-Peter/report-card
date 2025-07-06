<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // No session message here, just prevent access or trigger download of an error.
    // Or, redirect to login, but that might interrupt a direct link click.
    header('HTTP/1.1 403 Forbidden');
    echo "Access denied. You must be logged in to download data.";
    exit;
}

require_once 'vendor/autoload.php'; // For PhpSpreadsheet
require_once 'db_connection.php';   // Provides $pdo
require_once 'dal.php';             // Provides DAL functions

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$batch_id = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);
$format = strtolower(trim(filter_input(INPUT_GET, 'format', FILTER_SANITIZE_STRING) ?? 'excel'));

if (!$batch_id) {
    // Handle invalid batch ID - perhaps redirect with error or output error
    header('HTTP/1.1 400 Bad Request');
    echo "Invalid or missing Batch ID.";
    exit;
}

if ($format !== 'excel' && $format !== 'pdf') {
    header('HTTP/1.1 400 Bad Request');
    echo "Invalid format specified. Only 'excel' or 'pdf' are supported.";
    exit;
}

$batchSettings = getReportBatchSettings($pdo, $batch_id);
if (!$batchSettings) {
    header('HTTP/1.1 404 Not Found');
    echo "Batch ID " . htmlspecialchars($batch_id) . " not found.";
    exit;
}

$studentsWithScores = getStudentsWithScoresForBatch($pdo, $batch_id);
$uniqueSubjectCodesInBatch = [];
if (!empty($studentsWithScores)) {
    uasort($studentsWithScores, function($a, $b) { // Sort students by name
        return strcmp($a['student_name'], $b['student_name']);
    });
    foreach ($studentsWithScores as $student) {
        if (!empty($student['subjects'])) {
            foreach ($student['subjects'] as $subjectCode => $details) {
                if (!isset($uniqueSubjectCodesInBatch[$subjectCode])) {
                    $uniqueSubjectCodesInBatch[$subjectCode] = $details['subject_name_full'];
                }
            }
        }
    }
    ksort($uniqueSubjectCodesInBatch); // Sort subjects by code
}

$filename_base = "Batch_" . $batch_id . "_" . preg_replace('/[^a-z0-9_]/i', '_', $batchSettings['class_name']) . "_" . $batchSettings['year_name'] . "_Term_" . $batchSettings['term_name'];

if ($format === 'excel') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Raw Scores');

    // Set Header Info
    $sheet->setCellValue('A1', 'Batch Data Export');
    $sheet->mergeCells('A1:E1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue('A2', 'Class: ' . htmlspecialchars($batchSettings['class_name']));
    $sheet->setCellValue('A3', 'Year: ' . htmlspecialchars($batchSettings['year_name']));
    $sheet->setCellValue('A4', 'Term: ' . htmlspecialchars($batchSettings['term_name']));

    // Table Headers - Row 6 & 7
    $headerRow1 = 6;
    $headerRow2 = 7;
    $sheet->setCellValueByColumnAndRow(1, $headerRow1, '#');
    $sheet->mergeCellsByColumnAndRow(1, $headerRow1, 1, $headerRow2); // Merge # cell
    $sheet->setCellValueByColumnAndRow(2, $headerRow1, 'Student Name');
    $sheet->mergeCellsByColumnAndRow(2, $headerRow1, 2, $headerRow2);
    $sheet->setCellValueByColumnAndRow(3, $headerRow1, 'LIN NO.');
    $sheet->mergeCellsByColumnAndRow(3, $headerRow1, 3, $headerRow2);

    $col = 4; // Start subject columns
    foreach ($uniqueSubjectCodesInBatch as $subjectCode => $subjectFullName) {
        $sheet->setCellValueByColumnAndRow($col, $headerRow1, htmlspecialchars($subjectFullName));
        $sheet->mergeCellsByColumnAndRow($col, $headerRow1, $col + 2, $headerRow1);
        $sheet->getStyleByColumnAndRow($col, $headerRow1)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValueByColumnAndRow($col, $headerRow2, 'BOT');
        $sheet->setCellValueByColumnAndRow($col + 1, $headerRow2, 'MOT');
        $sheet->setCellValueByColumnAndRow($col + 2, $headerRow2, 'EOT');
        $col += 3;
    }
    $lastHeaderCol = $col -1;

    // Style headers
    $headerStyleArray = [
        'font' => ['bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];
    $sheet->getStyle('A'.$headerRow1.':'.Coordinate::stringFromColumnIndex($lastHeaderCol).$headerRow2)->applyFromArray($headerStyleArray);


    // Populate Data
    $rowNum = $headerRow2 + 1;
    $count = 0;
    if (!empty($studentsWithScores)) {
        foreach ($studentsWithScores as $studentId => $studentData) {
            $count++;
            $sheet->setCellValueByColumnAndRow(1, $rowNum, $count);
            $sheet->setCellValueByColumnAndRow(2, $rowNum, htmlspecialchars($studentData['student_name']));
            $sheet->setCellValueByColumnAndRow(3, $rowNum, htmlspecialchars($studentData['lin_no'] ?? ''));

            $col = 4;
            foreach ($uniqueSubjectCodesInBatch as $subjectCode => $subjectFullName) {
                $scores = $studentData['subjects'][$subjectCode] ?? null;
                $sheet->setCellValueByColumnAndRow($col,     $rowNum, $scores['bot_score'] ?? '');
                $sheet->setCellValueByColumnAndRow($col + 1, $rowNum, $scores['mot_score'] ?? '');
                $sheet->setCellValueByColumnAndRow($col + 2, $rowNum, $scores['eot_score'] ?? '');
                $col += 3;
            }
            // Apply borders to data row
            $sheet->getStyle('A'.$rowNum.':'.Coordinate::stringFromColumnIndex($lastHeaderCol).$rowNum)
                  ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $rowNum++;
        }
    } else {
        $sheet->setCellValue('A'.$rowNum, 'No student scores found for this batch.');
        $sheet->mergeCells('A'.$rowNum.':E'.$rowNum);
    }

    // Auto-size columns
    for ($i = 1; $i <= $lastHeaderCol; $i++) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
    }

    // Log activity
     if (function_exists('logActivity')) {
        logActivity($pdo, $_SESSION['user_id'], $_SESSION['username'], 'DATA_EXPORTED_EXCEL', "Exported raw scores for batch ID $batch_id to Excel.", 'batch', $batch_id);
    }


    // Output to browser
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename_base . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} elseif ($format === 'pdf') {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-L', // A4 Landscape
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 25, // Increased top margin for header
        'margin_bottom' => 10,
        'margin_header' => 5,
        'margin_footer' => 5
    ]);

    // PDF Header
    $pdfHeaderHtml = '
    <div style="text-align: center; font-size: 14pt; font-weight: bold;">Raw Scores Data Export</div>
    <div style="text-align: center; font-size: 10pt;">
        Class: ' . htmlspecialchars($batchSettings['class_name']) . ' |
        Year: ' . htmlspecialchars($batchSettings['year_name']) . ' |
        Term: ' . htmlspecialchars($batchSettings['term_name']) . '
    </div>
    <hr>';
    $mpdf->SetHTMLHeader($pdfHeaderHtml);
    $mpdf->SetHeader($pdfHeaderHtml); // Ensure it appears on first page too

    // PDF Footer
    $mpdf->SetFooter('{PAGENO}/{nbpg}');


    // Construct HTML for the table
    $html = '<style>
                table { border-collapse: collapse; width: 100%; font-size: 8pt; }
                th, td { border: 1px solid #ddd; padding: 4px; text-align: center; }
                th { background-color: #f2f2f2; font-weight: bold; }
                .student-name { text-align: left; }
                .no-data { text-align: center; font-style: italic; padding: 10px; }
            </style>';

    $html .= '<table>';
    // Table Headers
    $html .= '<thead><tr>';
    $html .= '<th rowspan="2">#</th>';
    $html .= '<th rowspan="2" class="student-name">Student Name</th>';
    $html .= '<th rowspan="2">LIN NO.</th>';
    foreach ($uniqueSubjectCodesInBatch as $subjectCode => $subjectFullName) {
        $html .= '<th colspan="3">' . htmlspecialchars($subjectFullName) . '</th>';
    }
    $html .= '</tr><tr>';
    foreach ($uniqueSubjectCodesInBatch as $subjectCode => $subjectFullName) {
        $html .= '<th>BOT</th><th>MOT</th><th>EOT</th>';
    }
    $html .= '</tr></thead>';

    // Table Body
    $html .= '<tbody>';
    if (!empty($studentsWithScores)) {
        $count = 0;
        foreach ($studentsWithScores as $studentId => $studentData) {
            $count++;
            $html .= '<tr>';
            $html .= '<td>' . $count . '</td>';
            $html .= '<td class="student-name">' . htmlspecialchars($studentData['student_name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($studentData['lin_no'] ?? '') . '</td>';
            foreach ($uniqueSubjectCodesInBatch as $subjectCode => $subjectFullName) {
                $scores = $studentData['subjects'][$subjectCode] ?? null;
                $html .= '<td>' . ($scores['bot_score'] ?? '-') . '</td>';
                $html .= '<td>' . ($scores['mot_score'] ?? '-') . '</td>';
                $html .= '<td>' . ($scores['eot_score'] ?? '-') . '</td>';
            }
            $html .= '</tr>';
        }
    } else {
        $numCols = 3 + (count($uniqueSubjectCodesInBatch) * 3);
        $html .= '<tr><td colspan="' . $numCols . '" class="no-data">No student scores found for this batch.</td></tr>';
    }
    $html .= '</tbody></table>';

    $mpdf->WriteHTML($html);

    // Log activity
    if (function_exists('logActivity')) {
        logActivity($pdo, $_SESSION['user_id'], $_SESSION['username'], 'DATA_EXPORTED_PDF', "Exported raw scores for batch ID $batch_id to PDF.", 'batch', $batch_id);
    }

    $mpdf->Output($filename_base . '.pdf', 'D'); // D for download
    exit;
}
?>
