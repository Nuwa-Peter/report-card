<?php
session_start();
// Note: db_connection.php is not strictly needed here yet, but good to include if any DB interaction is planned for index.
// require_once 'db_connection.php';

$last_processed_batch_id = $_SESSION['last_processed_batch_id'] ?? null;
$current_teacher_initials_for_session = $_SESSION['current_teacher_initials'] ?? []; // For repopulating form if needed

// Clear report-specific session data that might conflict with DB-driven approach
// unset($_SESSION['report_data']); // process_excel.php no longer sets this with student details
// Let's be more targeted:
if(isset($_SESSION['report_data']) && !isset($_SESSION['last_processed_batch_id'])) {
    // If report_data exists from a very old session structure and no new batch processed, clear it.
    unset($_SESSION['report_data']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Card Generator - Maria Owembabazi Primary School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        body { background-color: #e0f7fa; /* Matching dashboard theme */ }
        .container.main-content {
            background-color: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-light bg-light sticky-top shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <img src="images/logo.png" alt="Logo" width="30" height="30" class="d-inline-block align-text-top me-2" onerror="this.style.display='none';">
                Maria Owembabazi P/S - Report System
            </a>
            <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </nav>

    <div class="container main-content">
        <?php
        if (isset($_SESSION['error_message']) && !empty($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
            unset($_SESSION['error_message']);
        }
        if (isset($_SESSION['success_message']) && !empty($_SESSION['success_message'])) {
            echo '<div class="alert alert-success" role="alert">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
            if ($last_processed_batch_id) {
                echo '<div class="mt-3">';
                // Link to a new page (view_processed_data.php) that will handle fetching details for this batch
                echo '<a href="view_processed_data.php?batch_id=' . htmlspecialchars($last_processed_batch_id) . '" class="btn btn-primary me-2"><i class="fas fa-eye"></i> View Details for Processed Batch ID: ' . htmlspecialchars($last_processed_batch_id) . '</a>';
                // The actual "Generate PDF" and "View Summary" for this batch will be on view_processed_data.php
                echo '</div>';
            }
            unset($_SESSION['success_message']);
            // Don't unset last_processed_batch_id here, might be needed if user navigates away and comes back.
            // Or, more robustly, view_processed_data.php should be the main interaction point for a batch.
        }
        ?>
        <div class="text-center mb-4">
            <!-- Logo removed from here as it's in navbar -->
            <h2>Report Card Data Entry</h2>
            <h4><?php echo htmlspecialchars($selectedClassValue ?? 'New Batch'); // Example, might not be set on initial load ?></h4>
        </div>

        <form action="process_excel.php" method="post" enctype="multipart/form-data">
            <h5 class="mt-4">School & Term Information</h5>
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="class_selection" class="form-label">Class:</label>
                    <select class="form-select" id="class_selection" name="class_selection" required>
                        <option value="" disabled selected>Select Class</option>
                        <optgroup label="Lower Primary">
                            <option value="P1">P1</option>
                            <option value="P2">P2</option>
                            <option value="P3">P3</option>
                        </optgroup>
                        <optgroup label="Upper Primary">
                            <option value="P4">P4</option>
                            <option value="P5">P5</option>
                            <option value="P6">P6</option>
                            <option value="P7">P7</option>
                        </optgroup>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="year" class="form-label">Year:</label>
                    <select class="form-select" id="year" name="year" required>
                        <option value="" disabled selected>Select Year</option>
                        <!-- JS will populate this -->
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="term" class="form-label">Term:</label>
                    <select class="form-select" id="term" name="term" required>
                        <option value="" disabled selected>Select Term</option>
                        <option value="I">Term I</option>
                        <option value="II">Term II</option>
                        <option value="III">Term III</option>
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                 <div class="col-md-4">
                    <label for="term_end_date" class="form-label">This Term Ended On:</label>
                    <input type="date" class="form-control" id="term_end_date" name="term_end_date" required>
                </div>
                <div class="col-md-4">
                    <label for="next_term_begin_date" class="form-label">Next Term Begins On:</label>
                    <input type="date" class="form-control" id="next_term_begin_date" name="next_term_begin_date" required>
                </div>
            </div>

            <h5 class="mt-4">Subject Excel Files & Teacher Initials</h5>
            <p class="text-muted">Upload one .xlsx file per subject. Cell A1=Subject Name, B1=BOT, C1=MOT, D1=EOT. Data from Row 2.</p>

            <!-- Common Subjects -->
            <div class="row mb-2 subject-input-row common-subject" id="english-block">
                <div class="col-md-5"><label for="english_file" class="form-label">English Results (.xlsx):</label><input type="file" class="form-control" id="english_file" name="subject_files[english]" accept=".xlsx"></div>
                <div class="col-md-3"><label for="english_initials" class="form-label">English Teacher Initials:</label><input type="text" class="form-control" id="english_initials" name="teacher_initials[english]" placeholder="e.g., J.D." value="<?php echo htmlspecialchars($current_teacher_initials_for_session['english'] ?? ''); ?>"></div>
            </div>
            <div class="row mb-2 subject-input-row common-subject" id="mtc-block">
                <div class="col-md-5"><label for="mtc_file" class="form-label">MTC (Math) Results (.xlsx):</label><input type="file" class="form-control" id="mtc_file" name="subject_files[mtc]" accept=".xlsx"></div>
                <div class="col-md-3"><label for="mtc_initials" class="form-label">MTC Teacher Initials:</label><input type="text" class="form-control" id="mtc_initials" name="teacher_initials[mtc]" placeholder="e.g., A.B." value="<?php echo htmlspecialchars($current_teacher_initials_for_session['mtc'] ?? ''); ?>"></div>
            </div>

            <!-- P1-P3 Specific Subjects -->
            <div class="row mb-2 subject-input-row p1p3-subject" id="re-block" style="display:none;">
                <div class="col-md-5"><label for="re_file" class="form-label">R.E Results (.xlsx):</label><input type="file" class="form-control" id="re_file" name="subject_files[re]" accept=".xlsx"></div>
                <div class="col-md-3"><label for="re_initials" class="form-label">R.E Teacher Initials:</label><input type="text" class="form-control" id="re_initials" name="teacher_initials[re]" placeholder="e.g., S.P." value="<?php echo htmlspecialchars($current_teacher_initials_for_session['re'] ?? ''); ?>"></div>
            </div>
            <div class="row mb-2 subject-input-row p1p3-subject" id="lit1-block" style="display:none;">
                <div class="col-md-5"><label for="lit1_file" class="form-label">Literacy I Results (.xlsx):</label><input type="file" class="form-control" id="lit1_file" name="subject_files[lit1]" accept=".xlsx"></div>
                <div class="col-md-3"><label for="lit1_initials" class="form-label">Literacy I Initials:</label><input type="text" class="form-control" id="lit1_initials" name="teacher_initials[lit1]" placeholder="e.g., K.L." value="<?php echo htmlspecialchars($current_teacher_initials_for_session['lit1'] ?? ''); ?>"></div>
            </div>
            <div class="row mb-2 subject-input-row p1p3-subject" id="lit2-block" style="display:none;">
                <div class="col-md-5"><label for="lit2_file" class="form-label">Literacy II Results (.xlsx):</label><input type="file" class="form-control" id="lit2_file" name="subject_files[lit2]" accept=".xlsx"></div>
                <div class="col-md-3"><label for="lit2_initials" class="form-label">Literacy II Initials:</label><input type="text" class="form-control" id="lit2_initials" name="teacher_initials[lit2]" placeholder="e.g., M.N." value="<?php echo htmlspecialchars($current_teacher_initials_for_session['lit2'] ?? ''); ?>"></div>
            </div>
             <div class="row mb-2 subject-input-row p1p3-subject" id="local_lang-block" style="display:none;">
                <div class="col-md-5"><label for="local_lang_file" class="form-label">Local Language Results (.xlsx):</label><input type="file" class="form-control" id="local_lang_file" name="subject_files[local_lang]" accept=".xlsx"></div>
                <div class="col-md-3"><label for="local_lang_initials" class="form-label">Local Language Initials:</label><input type="text" class="form-control" id="local_lang_initials" name="teacher_initials[local_lang]" placeholder="e.g., O.P." value="<?php echo htmlspecialchars($current_teacher_initials_for_session['local_lang'] ?? ''); ?>"></div>
            </div>

            <!-- P4-P7 Specific Subjects -->
            <div class="row mb-2 subject-input-row p4p7-subject" id="science-block" style="display:none;">
                <div class="col-md-5"><label for="science_file" class="form-label">Science Results (.xlsx):</label><input type="file" class="form-control" id="science_file" name="subject_files[science]" accept=".xlsx"></div>
                <div class="col-md-3"><label for="science_initials" class="form-label">Science Teacher Initials:</label><input type="text" class="form-control" id="science_initials" name="teacher_initials[science]" placeholder="e.g., C.E." value="<?php echo htmlspecialchars($current_teacher_initials_for_session['science'] ?? ''); ?>"></div>
            </div>
            <div class="row mb-2 subject-input-row p4p7-subject" id="sst-block" style="display:none;">
                <div class="col-md-5"><label for="sst_file" class="form-label">SST Results (.xlsx):</label><input type="file" class="form-control" id="sst_file" name="subject_files[sst]" accept=".xlsx"></div>
                <div class="col-md-3"><label for="sst_initials" class="form-label">SST Teacher Initials:</label><input type="text" class="form-control" id="sst_initials" name="teacher_initials[sst]" placeholder="e.g., F.G." value="<?php echo htmlspecialchars($current_teacher_initials_for_session['sst'] ?? ''); ?>"></div>
            </div>
            <div class="row mb-3 subject-input-row p4p7-subject" id="kiswahili-block" style="display:none;">
                <div class="col-md-5"><label for="kiswahili_file" class="form-label">Kiswahili Results (.xlsx) <small class='text-muted'>(Optional for P4-P7)</small>:</label><input type="file" class="form-control" id="kiswahili_file" name="subject_files[kiswahili]" accept=".xlsx"></div>
                <div class="col-md-3"><label for="kiswahili_initials" class="form-label">Kiswahili Teacher Initials:</label><input type="text" class="form-control" id="kiswahili_initials" name="teacher_initials[kiswahili]" placeholder="e.g., H.I." value="<?php echo htmlspecialchars($current_teacher_initials_for_session['kiswahili'] ?? ''); ?>"></div>
            </div>

            <h5 class="mt-4">General Remarks (Manual - to be replaced by auto-remarks later)</h5>
            <div class="mb-3"><label for="class_teacher_remarks" class="form-label">Class Teacher's Remarks:</label><textarea class="form-control" id="class_teacher_remarks" name="class_teacher_remarks" rows="3"></textarea></div>
            <div class="mb-3"><label for="head_teacher_remarks" class="form-label">Head Teacher's Remarks:</label><textarea class="form-control" id="head_teacher_remarks" name="head_teacher_remarks" rows="3"></textarea></div>

            <div class="d-grid gap-2 col-6 mx-auto mt-4 mb-5"><button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-cogs"></i> Process & Save Data</button></div>
        </form>
    </div>
    <footer class="text-center mt-5 mb-3"><p>&copy; <span id="currentYear"></span> Maria Owembabazi Primary School - <i>Good Christian, Good Citizen</i></p></footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script> <!-- js/script.js is assumed to be the same as before -->
</body>
</html>
