<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Card Generator - Maria Owembabazi Primary School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <?php
    session_start(); // Ensure session is started
    if (isset($_SESSION['error_message']) && !empty($_SESSION['error_message'])) {
        echo '<div class="container mt-3"><div class="alert alert-danger" role="alert">' . htmlspecialchars($_SESSION['error_message']) . '</div></div>';
        unset($_SESSION['error_message']); // Clear the message after displaying
    }
    if (isset($_SESSION['success_message']) && !empty($_SESSION['success_message'])) {
        echo '<div class="container mt-3"><div class="alert alert-success" role="alert">' . htmlspecialchars($_SESSION['success_message']) . '</div></div>';
        unset($_SESSION['success_message']); // Clear the message after displaying
    }
    // PDF Download button from previous implementation
    if (isset($_SESSION['report_data']) && !empty($_SESSION['report_data']['students'])) {
        echo '<div class="container mt-3 mb-3 text-center">';
        echo '<a href="generate_pdf.php" class="btn btn-success btn-lg me-2">Download All Report Cards as PDF</a>';
        // Add link to summary sheet here later (Step 8)
        // echo '<a href="summary_sheet.php" class="btn btn-info btn-lg">View Summary Sheet</a>';
        echo '</div>';
    }
    ?>
    <div class="container mt-5">
        <div class="text-center mb-4">
            <img src="images/logo.png" alt="School Logo" style="width: 100px; display:none;" id="logoPreview">
            <h2>Maria Owembabazi Primary School</h2>
            <h4>Report Card Generator</h4>
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
            <p class="text-muted">Upload one .xlsx file per subject. Row 1 must be Subject Name, Row 2 Headers (Name, BOT, MOT, EOT).</p>

            <!-- Common Subjects -->
            <div class="row mb-2 subject-input-row common-subject" id="english-block">
                <div class="col-md-5"><label for="english_file" class="form-label">English Results (.xlsx):</label><input type="file" class="form-control" id="english_file" name="subject_files[english]" accept=".xlsx"></div>
                <div class="col-md-3"><label for="english_initials" class="form-label">English Teacher Initials:</label><input type="text" class="form-control" id="english_initials" name="teacher_initials[english]" placeholder="e.g., J.D."></div>
            </div>
            <div class="row mb-2 subject-input-row common-subject" id="mtc-block">
                <div class="col-md-5"><label for="mtc_file" class="form-label">MTC (Math) Results (.xlsx):</label><input type="file" class="form-control" id="mtc_file" name="subject_files[mtc]" accept=".xlsx"></div>
                <div class="col-md-3"><label for="mtc_initials" class="form-label">MTC Teacher Initials:</label><input type="text" class="form-control" id="mtc_initials" name="teacher_initials[mtc]" placeholder="e.g., A.B."></div>
            </div>

            <!-- P1-P3 Specific Subjects -->
            <div class="row mb-2 subject-input-row p1p3-subject" id="re-block" style="display:none;">
                <div class="col-md-5"><label for="re_file" class="form-label">R.E Results (.xlsx):</label><input type="file" class="form-control" id="re_file" name="subject_files[re]" accept=".xlsx"></div>
                <div class="col-md-3"><label for="re_initials" class="form-label">R.E Teacher Initials:</label><input type="text" class="form-control" id="re_initials" name="teacher_initials[re]" placeholder="e.g., S.P."></div>
            </div>
            <div class="row mb-2 subject-input-row p1p3-subject" id="lit1-block" style="display:none;">
                <div class="col-md-5"><label for="lit1_file" class="form-label">Literacy One Results (.xlsx):</label><input type="file" class="form-control" id="lit1_file" name="subject_files[lit1]" accept=".xlsx"></div>
                <div class="col-md-3"><label for="lit1_initials" class="form-label">Literacy One Initials:</label><input type="text" class="form-control" id="lit1_initials" name="teacher_initials[lit1]" placeholder="e.g., K.L."></div>
            </div>
            <div class="row mb-2 subject-input-row p1p3-subject" id="lit2-block" style="display:none;">
                <div class="col-md-5"><label for="lit2_file" class="form-label">Literacy Two Results (.xlsx):</label><input type="file" class="form-control" id="lit2_file" name="subject_files[lit2]" accept=".xlsx"></div>
                <div class="col-md-3"><label for="lit2_initials" class="form-label">Literacy Two Initials:</label><input type="text" class="form-control" id="lit2_initials" name="teacher_initials[lit2]" placeholder="e.g., M.N."></div>
            </div>
             <div class="row mb-2 subject-input-row p1p3-subject" id="local_lang-block" style="display:none;">
                <div class="col-md-5"><label for="local_lang_file" class="form-label">Local Language Results (.xlsx):</label><input type="file" class="form-control" id="local_lang_file" name="subject_files[local_lang]" accept=".xlsx"></div>
                <div class="col-md-3"><label for="local_lang_initials" class="form-label">Local Language Initials:</label><input type="text" class="form-control" id="local_lang_initials" name="teacher_initials[local_lang]" placeholder="e.g., O.P."></div>
            </div>

            <!-- P4-P7 Specific Subjects -->
            <div class="row mb-2 subject-input-row p4p7-subject" id="science-block" style="display:none;">
                <div class="col-md-5"><label for="science_file" class="form-label">Science Results (.xlsx):</label><input type="file" class="form-control" id="science_file" name="subject_files[science]" accept=".xlsx"></div>
                <div class="col-md-3"><label for="science_initials" class="form-label">Science Teacher Initials:</label><input type="text" class="form-control" id="science_initials" name="teacher_initials[science]" placeholder="e.g., C.E."></div>
            </div>
            <div class="row mb-2 subject-input-row p4p7-subject" id="sst-block" style="display:none;">
                <div class="col-md-5"><label for="sst_file" class="form-label">SST Results (.xlsx):</label><input type="file" class="form-control" id="sst_file" name="subject_files[sst]" accept=".xlsx"></div>
                <div class="col-md-3"><label for="sst_initials" class="form-label">SST Teacher Initials:</label><input type="text" class="form-control" id="sst_initials" name="teacher_initials[sst]" placeholder="e.g., F.G."></div>
            </div>
            <div class="row mb-3 subject-input-row p4p7-subject" id="kiswahili-block" style="display:none;">
                <div class="col-md-5"><label for="kiswahili_file" class="form-label">Kiswahili Results (.xlsx) <small class='text-muted'>(Optional for P4-P7)</small>:</label><input type="file" class="form-control" id="kiswahili_file" name="subject_files[kiswahili]" accept=".xlsx"></div>
                <div class="col-md-3"><label for="kiswahili_initials" class="form-label">Kiswahili Teacher Initials:</label><input type="text" class="form-control" id="kiswahili_initials" name="teacher_initials[kiswahili]" placeholder="e.g., H.I."></div>
            </div>

            <h5 class="mt-4">General Remarks</h5>
            <div class="mb-3"><label for="class_teacher_remarks" class="form-label">Class Teacher's Remarks:</label><textarea class="form-control" id="class_teacher_remarks" name="class_teacher_remarks" rows="3"></textarea></div>
            <div class="mb-3"><label for="head_teacher_remarks" class="form-label">Head Teacher's Remarks:</label><textarea class="form-control" id="head_teacher_remarks" name="head_teacher_remarks" rows="3"></textarea></div>

            <div class="d-grid gap-2 col-6 mx-auto mt-4 mb-5"><button type="submit" class="btn btn-primary">Generate Report Cards</button></div>
        </form>
    </div>
    <footer class="text-center mt-5 mb-3"><p>&copy; <span id="currentYear"></span> Maria Owembabazi Primary School - Good Christian, Good Citizen</p></footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>
