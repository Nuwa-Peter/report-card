<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Optional: Set a flash message to explain why they are on the login page
    // $_SESSION['login_error_message'] = "You must be logged in to access this page.";
    header('Location: login.php');
    exit;
}
require_once 'db_connection.php'; // Provides $pdo
require_once 'dal.php';         // Provides DAL functions

if (!isset($_GET['batch_id']) || !filter_var($_GET['batch_id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid or missing batch ID.";
    header('Location: index.php'); // Or dashboard.php
    exit;
}
$batch_id = (int)$_GET['batch_id'];

$batchSettings = getReportBatchSettings($pdo, $batch_id);
if (!$batchSettings) {
    $_SESSION['error_message'] = "Could not find details for Batch ID: " . htmlspecialchars($batch_id);
    header('Location: index.php'); // Or dashboard.php
    exit;
}

// Fetch students and their raw scores for this batch
// getStudentsWithScoresForBatch returns students keyed by student_id, each with a 'subjects' array keyed by subject_code
$studentsWithScores = getStudentsWithScoresForBatch($pdo, $batch_id);

// Get a list of all unique subjects present in this batch for table headers
$uniqueSubjectCodesInBatch = [];
if (!empty($studentsWithScores)) {
    // Sort students by name for consistent display
    uasort($studentsWithScores, function($a, $b) {
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
    ksort($uniqueSubjectCodesInBatch); // Sort by subject code for consistent column order
}

// This map is for fallback if subject_name_full is not in the DB for some reason,
// or for consistent display if needed. The primary source should be $details['subject_name_full'].
$subjectDisplayNames = [
    'english' => 'English', 'mtc' => 'Mathematics (MTC)', 'science' => 'Science',
    'sst' => 'Social Studies (SST)', 'kiswahili' => 'Kiswahili',
    're' => 'Religious Education (R.E)', 'lit1' => 'Literacy I',
    'lit2' => 'Literacy II', 'local_lang' => 'Local Language'
];


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Processed Batch Data - <?php echo htmlspecialchars($batchSettings['class_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="images/logo.png">
    <link href="css/style.css" rel="stylesheet">
    <style>
        body { background-color: #e0f7fa; }
        .container.main-content {
            background-color: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .table th, .table td { vertical-align: middle; text-align:center;}
        .table .student-name-col { text-align:left; }
        .sticky-top { top:0; z-index: 1020;} /* Ensure navbar is on top */

        /* Compact table styles from previous step - ensure they are present */
        .table-compact th,
        .table-compact td {
            padding: 0.4rem;
            font-size: 0.875rem;
        }
        .table-compact .form-control.score-input,
        .table-compact .form-control {
            font-size: 0.875rem;
            padding: 0.25rem 0.5rem;
            height: auto;
        }
        .table-compact input[type="text"][size="3"] {
            width: 4em;
        }

        /* Live Search Results */
        #studentSearchResults {
            position: absolute; /* Positioned relative to its offset parent or containing block */
            background-color: white;
            border: 1px solid #ccc;
            border-top: none;
            z-index: 1050; /* Ensure it's above other elements, like table headers */
            width: 100%; /* Make it full width of its container */
            max-height: 200px;
            overflow-y: auto;
        }
        #studentSearchResults .list-group-item {
            cursor: pointer;
            padding: 0.5rem 0.75rem; /* Adjust padding for items */
            font-size: 0.875rem; /* Match compact style */
        }
        #studentSearchResults .list-group-item:hover {
            background-color: #f0f0f0;
        }
        .highlight-row td { /* Apply to TD for full row highlight */
            background-color: #fff3cd !important; /* Light yellow highlight */
            transition: background-color 0.3s ease-in-out;
        }
        /* Container for search to manage positioning of results */
        .search-container {
            position: relative; /* For absolute positioning of results */
        }
        #studentSearchInput:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        @keyframes flash-warning {
            0%, 100% { opacity: 1; /*background-color: #fff3cd;*/ }
            50% { opacity: 0.4; /*background-color: #ffe082; slightly darker yellow */ }
        }
        .flashing-warning {
            animation: flash-warning 1.5s infinite ease-in-out;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-light bg-light sticky-top shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"> <!-- Corrected -->
                <img src="images/logo.png" alt="Logo" width="30" height="30" class="d-inline-block align-text-top me-2" onerror="this.style.display='none';">
                Maria Owembabazi P/S - Report System
            </a>
            <div>
                <a href="data_entry.php" class="btn btn-outline-primary me-2"><i class="fas fa-plus-circle"></i> Import Another Batch</a>
                <a href="index.php" class="btn btn-outline-secondary me-2"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                <a href="logout.php" class="btn btn-outline-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="container main-content">
        <h2 class="mb-3">Processed Data for Batch ID: <?php echo htmlspecialchars($batch_id); ?></h2>

        <?php
        if (isset($_SESSION['error_message']) && !empty($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
            unset($_SESSION['error_message']);
        }
        if (isset($_SESSION['success_message']) && !empty($_SESSION['success_message'])) {
            // Add the flash-alert-red class for the animation
            echo '<div class="alert alert-success flash-alert-red" role="alert">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['info_message']) && !empty($_SESSION['info_message'])) { // Also handle info message just in case
            echo '<div class="alert alert-info" role="alert">' . htmlspecialchars($_SESSION['info_message']) . '</div>';
            unset($_SESSION['info_message']);
        }
        ?>

        <div class="card mb-4">
            <div class="card-header">
                <strong class="fs-5">Batch Details</strong>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Class:</strong> <?php echo htmlspecialchars($batchSettings['class_name']); ?></p>
                        <p><strong>Academic Year:</strong> <?php echo htmlspecialchars($batchSettings['year_name']); ?></p>
                        <p><strong>Term:</strong> <?php echo htmlspecialchars($batchSettings['term_name']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Term Ended On:</strong> <?php echo htmlspecialchars(date('d M Y', strtotime($batchSettings['term_end_date']))); ?></p>
                        <p><strong>Next Term Begins On:</strong> <?php echo htmlspecialchars(date('d M Y', strtotime($batchSettings['next_term_begin_date']))); ?></p>
                        <p><strong>Import Date:</strong> <?php echo htmlspecialchars(date('d M Y H:i:s', strtotime($batchSettings['import_date']))); ?></p>
                    </div>
                </div>
            </div>
            <div class="card-footer text-center">
                <button type="button" id="enableEditingBtn" class="btn btn-info btn-sm me-2"><i class="fas fa-edit"></i> Enable Editing / Add Student</button>
                <a href="run_calculations.php?batch_id=<?php echo $batch_id; ?>" class="btn btn-warning btn-sm me-2"><i class="fas fa-calculator"></i> Calculate Summaries & Auto-Remarks</a>
                <a href="generate_pdf.php?batch_id=<?php echo $batch_id; ?>" class="btn btn-danger btn-sm me-2"><i class="fas fa-file-pdf"></i> Generate Full Class PDF Report</a>
                <a href="summary_sheet.php?batch_id=<?php echo $batch_id; ?>" class="btn btn-success btn-sm me-2"><i class="fas fa-chart-bar"></i> View Class Summary Sheet</a>
            </div>
        </div>

        <?php
            $showRecalculateWarning = isset($_SESSION['batch_data_changed_for_calc'][$batch_id]) && $_SESSION['batch_data_changed_for_calc'][$batch_id] === true;
        ?>
        <div id="recalculate-warning" class="alert alert-warning text-center mt-3 <?php echo $showRecalculateWarning ? '' : 'd-none'; ?>" role="alert">
           <i class="fas fa-exclamation-triangle"></i> <strong>Important:</strong> Data has changed. Please re-run <strong>'Calculate Summaries & Auto-Remarks'</strong> to ensure reports and summaries are accurate.
        </div>

        <div class="row mt-3 mb-2">
            <div class="col-md-6 offset-md-3 search-container">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" id="studentSearchInput" class="form-control" placeholder="Search by student name in this batch...">
                </div>
                <div id="studentSearchResults" class="list-group" style="display: none; width: 100%;"></div>
            </div>
        </div>

        <h3 class="mt-4 mb-3">Student Raw Scores</h3>
        <form id="editMarksForm" action="handle_edit_marks.php" method="post">
            <input type="hidden" name="batch_id" value="<?php echo $batch_id; ?>">
            <div class="mb-3 text-center" id="editModeButtons" style="display: none;">
                <button type="submit" class="btn btn-primary btn-sm me-2"><i class="fas fa-save"></i> Save Changes</button>
                <button type="button" id="cancelEditingBtn" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Cancel Edits</button>
            </div>

            <?php if (!empty($studentsWithScores)): ?>
                <div class="table-responsive">
                    <table id="scoresTable" class="table table-bordered table-striped table-hover table-compact">
                        <thead class="table-light">
                            <tr>
                                <th rowspan="2" style="vertical-align:middle;">#</th>
                                <th rowspan="2" style="vertical-align:middle;" class="student-name-col">Student Name</th>
                                <th rowspan="2" style="vertical-align:middle;">LIN NO.</th>
                                <?php foreach ($uniqueSubjectCodesInBatch as $subjectCode => $subjectFullName): ?>
                                    <th colspan="3" class="text-center"><?php echo htmlspecialchars($subjectFullName); ?></th>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <?php foreach ($uniqueSubjectCodesInBatch as $subjectCode => $subjectFullName): ?>
                                    <th>BOT</th>
                                    <th>MOT</th>
                                    <th>EOT</th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $count = 0; foreach ($studentsWithScores as $studentId => $studentData): $count++; ?>
                                <tr data-student-id="<?php echo $studentId; ?>">
                                    <td><?php echo $count; ?>
                                        <input type="hidden" name="students[<?php echo $studentId; ?>][id]" value="<?php echo $studentId; ?>">
                                    </td>
                                    <td class="student-name-col">
                                        <span class="score-display"><?php echo htmlspecialchars($studentData['student_name']); ?></span>
                                        <input type="text" name="students[<?php echo $studentId; ?>][name]" class="form-control form-control-sm score-input" value="<?php echo htmlspecialchars($studentData['student_name']); ?>" style="display: none;">
                                    </td>
                                    <td>
                                        <span class="score-display"><?php echo htmlspecialchars($studentData['lin_no'] ?? 'N/A'); ?></span>
                                        <input type="text" name="students[<?php echo $studentId; ?>][lin_no]" class="form-control form-control-sm score-input" value="<?php echo htmlspecialchars($studentData['lin_no'] ?? ''); ?>" style="display: none;">
                                    </td>
                                    <?php foreach ($uniqueSubjectCodesInBatch as $subjectCode => $subjectFullName): ?>
                                        <?php
                                            $scores = $studentData['subjects'][$subjectCode] ?? null;
                                            $subject_id = $scores['subject_id'] ?? null; // Assuming subject_id is available
                                        ?>
                                        <td>
                                            <span class="score-display"><?php echo htmlspecialchars($scores['bot_score'] ?? '-'); ?></span>
                                            <input type="text" name="students[<?php echo $studentId; ?>][scores][<?php echo $subjectCode; ?>][bot]" class="form-control form-control-sm score-input" value="<?php echo htmlspecialchars($scores['bot_score'] ?? ''); ?>" style="display: none;" size="3">
                                        </td>
                                        <td>
                                            <span class="score-display"><?php echo htmlspecialchars($scores['mot_score'] ?? '-'); ?></span>
                                            <input type="text" name="students[<?php echo $studentId; ?>][scores][<?php echo $subjectCode; ?>][mot]" class="form-control form-control-sm score-input" value="<?php echo htmlspecialchars($scores['mot_score'] ?? ''); ?>" style="display: none;" size="3">
                                        </td>
                                        <td>
                                            <span class="score-display"><?php echo htmlspecialchars($scores['eot_score'] ?? '-'); ?></span>
                                            <input type="text" name="students[<?php echo $studentId; ?>][scores][<?php echo $subjectCode; ?>][eot]" class="form-control form-control-sm score-input" value="<?php echo htmlspecialchars($scores['eot_score'] ?? ''); ?>" style="display: none;" size="3">
                                            <?php if ($subject_id): ?>
                                                <input type="hidden" name="students[<?php echo $studentId; ?>][scores][<?php echo $subjectCode; ?>][subject_id]" value="<?php echo $subject_id; ?>">
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            <!-- Template row for new student -->
                            <tr id="newStudentTemplateRow" style="display: none;">
                                <td>New</td>
                                <td class="student-name-col">
                                    <input type="text" name="new_student[0][name]" class="form-control form-control-sm" placeholder="Student Name">
                                </td>
                                <td>
                                    <input type="text" name="new_student[0][lin_no]" class="form-control form-control-sm" placeholder="LIN No.">
                                </td>
                                <?php foreach ($uniqueSubjectCodesInBatch as $subjectCode => $subjectFullName): ?>
                                    <?php
                                        // Attempt to get a subject_id for the new student row.
                                        // This assumes all existing students might have this subject,
                                        // and we can pick one subject_id. This might need refinement
                                        // if subject_id isn't consistently available or if it's better to look up by code.
                                        $any_subject_id_for_code = null;
                                        if (!empty($studentsWithScores)) {
                                            $firstStudent = reset($studentsWithScores);
                                            if (isset($firstStudent['subjects'][$subjectCode]['subject_id'])) {
                                                $any_subject_id_for_code = $firstStudent['subjects'][$subjectCode]['subject_id'];
                                            }
                                        }
                                    ?>
                                    <td><input type="text" name="new_student[0][scores][<?php echo $subjectCode; ?>][bot]" class="form-control form-control-sm" placeholder="BOT" size="3"></td>
                                    <td><input type="text" name="new_student[0][scores][<?php echo $subjectCode; ?>][mot]" class="form-control form-control-sm" placeholder="MOT" size="3"></td>
                                    <td>
                                        <input type="text" name="new_student[0][scores][<?php echo $subjectCode; ?>][eot]" class="form-control form-control-sm" placeholder="EOT" size="3">
                                        <?php if ($any_subject_id_for_code): // Use subject_id if available for consistency ?>
                                            <input type="hidden" name="new_student[0][scores][<?php echo $subjectCode; ?>][subject_id]" value="<?php echo $any_subject_id_for_code; ?>">
                                        <?php else: // Fallback to sending subject_code if ID is not found (handle in backend) ?>
                                            <input type="hidden" name="new_student[0][scores][<?php echo $subjectCode; ?>][subject_code_fallback]" value="<?php echo $subjectCode; ?>">
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-2 mb-3" id="addStudentBtnContainer" style="display: none;">
                    <button type="button" id="addAnotherStudentBtn" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Add Another Student</button>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No student scores found for this batch. The import might have been empty or encountered issues with specific files. Please verify the uploaded files for this batch. You can add students once editing is enabled if this batch is meant to be populated manually.</div>
                <!-- Minimal new student row for initially empty batch -->
                 <table id="scoresTable" class="table table-bordered table-striped table-hover table-compact" style="display:none;"> <!-- Hidden initially if no students -->
                     <thead class="table-light">
                        <tr>
                            <th rowspan="2" style="vertical-align:middle;">#</th>
                            <th rowspan="2" style="vertical-align:middle;" class="student-name-col">Student Name</th>
                            <th rowspan="2" style="vertical-align:middle;">LIN NO.</th>
                            <?php
                            // If $uniqueSubjectCodesInBatch is empty (e.g. new batch from scratch),
                            // we might need a default set of subjects or a way to define them.
                            // For now, this will render no subject columns if the batch had no prior data.
                            // This scenario (editing a completely empty batch) needs more thought for subject selection.
                            // A possible solution: if empty, load all subjects for the class level from `subjects` table.
                            // For now, assuming $uniqueSubjectCodesInBatch is populated if any editing is meaningful.
                            foreach ($uniqueSubjectCodesInBatch as $subjectCode => $subjectFullName): ?>
                                <th colspan="3" class="text-center"><?php echo htmlspecialchars($subjectFullName); ?></th>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <?php foreach ($uniqueSubjectCodesInBatch as $subjectCode => $subjectFullName): ?>
                                <th>BOT</th>
                                <th>MOT</th>
                                <th>EOT</th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="newStudentTemplateRow" style="display: none;">
                                <td>New</td>
                                <td class="student-name-col">
                                    <input type="text" name="new_student[0][name]" class="form-control form-control-sm" placeholder="Student Name">
                                </td>
                                <td>
                                    <input type="text" name="new_student[0][lin_no]" class="form-control form-control-sm" placeholder="LIN No.">
                                </td>
                                <?php foreach ($uniqueSubjectCodesInBatch as $subjectCode => $subjectFullName): ?>
                                    <td><input type="text" name="new_student[0][scores][<?php echo $subjectCode; ?>][bot]" class="form-control form-control-sm" placeholder="BOT" size="3"></td>
                                    <td><input type="text" name="new_student[0][scores][<?php echo $subjectCode; ?>][mot]" class="form-control form-control-sm" placeholder="MOT" size="3"></td>
                                    <td>
                                        <input type="text" name="new_student[0][scores][<?php echo $subjectCode; ?>][eot]" class="form-control form-control-sm" placeholder="EOT" size="3">
                                        <input type="hidden" name="new_student[0][scores][<?php echo $subjectCode; ?>][subject_code_fallback]" value="<?php echo $subjectCode; ?>">
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                    </tbody>
                </table>
                 <div class="text-center mt-2 mb-3" id="addStudentBtnContainer" style="display: none;">
                    <button type="button" id="addAnotherStudentBtn" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Add Another Student</button>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <footer class="text-center mt-4 mb-3 p-3 bg-light">
        <p>&copy; <?php echo date('Y'); ?> Maria Owembabazi Primary School - <i>Good Christian, Good Citizen</i></p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const enableEditingBtn = document.getElementById('enableEditingBtn');
            const cancelEditingBtn = document.getElementById('cancelEditingBtn');
            const editModeButtons = document.getElementById('editModeButtons');
            const scoresTable = document.getElementById('scoresTable');
            const newStudentTemplateRow = document.getElementById('newStudentTemplateRow');
            const addAnotherStudentBtn = document.getElementById('addAnotherStudentBtn');
            const addStudentBtnContainer = document.getElementById('addStudentBtnContainer');
            const studentSearchInput = document.getElementById('studentSearchInput');
            const studentSearchResultsContainer = document.getElementById('studentSearchResults');
            let newStudentIndex = 0;
            let searchTimeout = null;


            if (!scoresTable && !enableEditingBtn) { // If table doesn't exist, editing makes no sense
                if(enableEditingBtn) enableEditingBtn.style.display = 'none';
                return;
            }
             if (!scoresTable && enableEditingBtn) { // Table might be missing if no students
                // Allow enabling editing to add first student
                 enableEditingBtn.addEventListener('click', function() {
                    // This case is tricky if $uniqueSubjectCodesInBatch is empty.
                    // The current PHP doesn't render subject columns if $uniqueSubjectCodesInBatch is empty.
                    // For a truly empty batch, we'd need to define subjects.
                    // This JS assumes subject columns ARE rendered or will be.
                    // For now, if table is missing, we can't do much.
                    // The PHP was modified to render the table even if no students, but hidden.
                    // So, this block might not be hit if that works.
                    alert("Cannot enable editing: student table not found. This might be an empty batch with no subjects defined yet.");
                });
                return;
            }

            function highlightRow(row) {
                // Remove highlight from other rows first
                const currentlyHighlighted = scoresTable.querySelector('tr.highlight-row');
                if (currentlyHighlighted) {
                    currentlyHighlighted.classList.remove('highlight-row');
                    // Apply to all TDs within that row
                    Array.from(currentlyHighlighted.cells).forEach(cell => cell.classList.remove('highlight-row'));
                }

                // Add highlight to current row (all its cells)
                Array.from(row.cells).forEach(cell => cell.classList.add('highlight-row'));

                setTimeout(() => {
                    Array.from(row.cells).forEach(cell => cell.classList.remove('highlight-row'));
                }, 2500); // Highlight for 2.5 seconds
            }

            function toggleEditMode(isEditing) {
                const displayElements = scoresTable.querySelectorAll('.score-display');
                const inputElements = scoresTable.querySelectorAll('.score-input');

                displayElements.forEach(el => el.style.display = isEditing ? 'none' : '');
                inputElements.forEach(el => el.style.display = isEditing ? '' : 'none');

                if (editModeButtons) editModeButtons.style.display = isEditing ? 'flex' : 'none';
                if (enableEditingBtn) enableEditingBtn.style.display = isEditing ? 'none' : '';
                if (newStudentTemplateRow) newStudentTemplateRow.style.display = isEditing ? '' : 'none';
                if (addStudentBtnContainer) addStudentBtnContainer.style.display = isEditing ? 'flex': 'none';

                 // If enabling editing and no students exist, make the table visible
                if (isEditing && scoresTable.style.display === 'none' && <?php echo empty($studentsWithScores) ? 'true' : 'false'; ?>) {
                    scoresTable.style.display = ''; // Show table
                    if (newStudentTemplateRow) newStudentTemplateRow.style.display = ''; // Ensure first new student row is visible
                }


                // Reset new student index when exiting edit mode
                if (!isEditing) {
                    newStudentIndex = 0;
                    // Remove dynamically added new student rows beyond the template
                    const dynamicNewRows = scoresTable.querySelectorAll('tr[id^="newStudentRow_"]');
                    dynamicNewRows.forEach(row => row.remove());
                    // Clear template row inputs
                    if (newStudentTemplateRow) {
                         newStudentTemplateRow.querySelectorAll('input[type="text"]').forEach(input => input.value = '');
                    }
                } else {
                    // If entering edit mode and the template row is the only one for new students, ensure its index is 0.
                    if (newStudentTemplateRow && newStudentTemplateRow.querySelectorAll('input[name^="new_student[0]"]').length > 0) {
                         newStudentIndex = 0; // Will be incremented to 1 by add new student function if button is clicked
                    }
                }
            }

            if(enableEditingBtn) {
                enableEditingBtn.addEventListener('click', function() {
                    toggleEditMode(true);
                    // If template is visible and it's for index 0, prime newStudentIndex for the *next* one
                    if (newStudentTemplateRow && newStudentTemplateRow.style.display !== 'none') {
                        const firstInputName = newStudentTemplateRow.querySelector('input[type="text"]')?.name;
                        if (firstInputName && firstInputName.startsWith('new_student[0]')) {
                            newStudentIndex = 0; // Ready for the first "add another" to be 1
                        }
                    }
                });
            }

            if(cancelEditingBtn) {
                cancelEditingBtn.addEventListener('click', function() {
                    // Potentially reset form fields to original values if changes were made
                    // For simplicity now, just toggle back. A full reset would require storing original values.
                    document.getElementById('editMarksForm').reset(); // Resets to initial HTML values
                    toggleEditMode(false);
                });
            }

            if(addAnotherStudentBtn && newStudentTemplateRow) {
                addAnotherStudentBtn.addEventListener('click', function() {
                    newStudentIndex++;
                    const newRow = newStudentTemplateRow.cloneNode(true);
                    newRow.id = `newStudentRow_${newStudentIndex}`;
                    newRow.style.display = ''; // Make it visible

                    newRow.querySelectorAll('input').forEach(input => {
                        input.name = input.name.replace(/new_student\[0\]/g, `new_student[${newStudentIndex}]`);
                        if (input.type === 'text') input.value = ''; // Clear cloned values
                    });
                    // Add a remove button to the new row
                    const removeBtnCell = document.createElement('td');
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'btn btn-danger btn-sm';
                    removeBtn.innerHTML = '<i class="fas fa-trash"></i>';
                    removeBtn.onclick = function() { newRow.remove(); /* Consider re-indexing or managing gaps if needed */ };
                    // Add remove button to the first cell (where '#' or 'New' is)
                    // Check if first cell exists
                    if(newRow.cells.length > 0) {
                        // If we want to replace "New" with the button or add it next to it.
                        // For simplicity, let's append it to the first cell's content.
                        // Or create a new cell specifically for actions.
                        // Let's add it to the first cell for now, replacing its content.
                        newRow.cells[0].innerHTML = ''; // Clear "New"
                        newRow.cells[0].appendChild(removeBtn);
                    }


                    // scoresTable.querySelector('tbody').appendChild(newRow); // Appends to end
                    // Insert before the template row if template is always last (it should be with current HTML)
                    scoresTable.querySelector('tbody').insertBefore(newRow, newStudentTemplateRow);
                });
            }

            // Live Search Logic
            if(studentSearchInput && studentSearchResultsContainer) {
                studentSearchInput.addEventListener('input', function() {
                    const searchTerm = this.value.trim();
                    clearTimeout(searchTimeout);

                    if (searchTerm.length < 2) {
                        studentSearchResultsContainer.innerHTML = '';
                        studentSearchResultsContainer.style.display = 'none';
                        return;
                    }

                    studentSearchResultsContainer.style.display = 'block';
                    studentSearchResultsContainer.innerHTML = '<div class="list-group-item disabled">Searching...</div>';


                    searchTimeout = setTimeout(() => {
                        fetch(`live_search_students.php?batch_id=<?php echo $batch_id; ?>&term=${encodeURIComponent(searchTerm)}`)
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error(`HTTP error! status: ${response.status}`);
                                }
                                return response.json();
                            })
                            .then(data => {
                                studentSearchResultsContainer.innerHTML = '';
                                if (data.error) {
                                     studentSearchResultsContainer.innerHTML = `<div class="list-group-item list-group-item-danger">${data.error}</div>`;
                                } else if (data.length > 0) {
                                    data.forEach(student => {
                                        const item = document.createElement('a');
                                        item.href = '#'; // Prevent page jump
                                        item.className = 'list-group-item list-group-item-action';
                                        item.textContent = student.student_name; // Assuming student_name is returned
                                        item.dataset.studentId = student.id;     // Assuming id is returned
                                        item.addEventListener('click', function(e) {
                                            e.preventDefault();
                                            const studentId = this.dataset.studentId;
                                            const targetRow = scoresTable.querySelector(`tr[data-student-id="${studentId}"]`);
                                            if (targetRow) {
                                                targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                                highlightRow(targetRow);
                                            }
                                            studentSearchInput.value = ''; // Clear search input
                                            studentSearchResultsContainer.innerHTML = ''; // Clear results
                                            studentSearchResultsContainer.style.display = 'none'; // Hide results
                                        });
                                        studentSearchResultsContainer.appendChild(item);
                                    });
                                } else {
                                    studentSearchResultsContainer.innerHTML = '<div class="list-group-item disabled">No students found matching your search in this batch.</div>';
                                }
                                studentSearchResultsContainer.style.display = 'block'; // Ensure it's visible
                            })
                            .catch(error => {
                                console.error('Error fetching search results:', error);
                                studentSearchResultsContainer.innerHTML = '<div class="list-group-item list-group-item-danger">Error loading results.</div>';
                                studentSearchResultsContainer.style.display = 'block'; // Ensure it's visible
                            });
                    }, 300); // Debounce requests by 300ms
                });

                // Hide search results if clicked outside
                document.addEventListener('click', function(event) {
                    if (studentSearchInput && studentSearchResultsContainer) { // Check if elements exist
                        if (!studentSearchInput.contains(event.target) && !studentSearchResultsContainer.contains(event.target)) {
                            studentSearchResultsContainer.style.display = 'none';
                        }
                    }
                });
            }

            // Flashing warning logic
            const recalculateWarningDiv = document.getElementById('recalculate-warning');
            if (recalculateWarningDiv && !recalculateWarningDiv.classList.contains('d-none')) {
                recalculateWarningDiv.classList.add('flashing-warning');
            }

            const calculateBtn = document.querySelector('a[href^="run_calculations.php?batch_id=<?php echo $batch_id; ?>"]');
            if (calculateBtn && recalculateWarningDiv && recalculateWarningDiv.classList.contains('flashing-warning')) {
                calculateBtn.addEventListener('click', function() {
                    // Stop flashing immediately on click, server will handle session flag on next load
                    recalculateWarningDiv.classList.remove('flashing-warning');
                    // Optionally, also hide it immediately, though server-side logic will handle it on reload
                    // recalculateWarningDiv.classList.add('d-none');
                });
            }
        });
    </script>
</body>
</html>
