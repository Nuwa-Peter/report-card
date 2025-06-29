document.addEventListener('DOMContentLoaded', function() {
    // Populate Year Dropdown
    const yearSelect = document.getElementById('year');
    if (yearSelect) {
        const currentYear = new Date().getFullYear();
        for (let year = currentYear; year <= 2100; year++) {
            const option = document.createElement('option');
            option.value = year;
            option.textContent = year;
            yearSelect.appendChild(option);
        }
        // Pre-select current year by default
        if(yearSelect.options.length > 1 && !yearSelect.value) { // if placeholder shown
             yearSelect.value = currentYear;
        }
    }

    // Set current year in footer
    const currentYearSpan = document.getElementById('currentYear');
    if (currentYearSpan) {
        currentYearSpan.textContent = new Date().getFullYear();
    }

    // Class selection changes subject visibility and requirements
    const classSelection = document.getElementById('class_selection');
    if (classSelection) {
        classSelection.addEventListener('change', function() {
            const selectedClass = this.value;
            updateSubjectFields(selectedClass);
        });
        // Call once on load in case a class is pre-selected (e.g. by browser)
         if(classSelection.value) {
            updateSubjectFields(classSelection.value);
        }
    }
});

function updateSubjectFields(selectedClass) {
    // Selectors for the new teacher initial blocks
    const commonSubjectInitials = document.querySelectorAll('.common-subject-initials');
    const p1p3SubjectInitials = document.querySelectorAll('.p1p3-subject-initials');
    const p4p7SubjectInitials = document.querySelectorAll('.p4p7-subject-initials');
    const marksFileLabel = document.getElementById('marks_excel_file_label');
    const defaultMarksFileLabelText = "Marks Excel File (.xlsx):";

    // Update the main marks file label
    if (marksFileLabel) {
        if (selectedClass && selectedClass !== "") {
            marksFileLabel.textContent = selectedClass + " " + defaultMarksFileLabelText;
        } else {
            marksFileLabel.textContent = defaultMarksFileLabelText;
        }
    }

    // Helper to set visibility and requirement for a group of teacher initial blocks
    function setInitialFields(elements, display, required) {
        elements.forEach(block => {
            block.style.display = display ? '' : 'none';
            // Only target text inputs for teacher initials within these blocks
            const textInputs = block.querySelectorAll('input[type="text"]');
            textInputs.forEach(input => {
                // Kiswahili initials are optional for P4-P7
                if (input.id === 'kiswahili_initials') {
                    if (selectedClass && selectedClass.startsWith('P') && parseInt(selectedClass.substring(1)) >= 4) { // P4-P7
                        input.required = false;
                    } else { // P1-P3 or no class selected (though it would be hidden)
                         input.required = required && display;
                    }
                } else {
                     input.required = required && display;
                }
            });
        });
    }

    // The main file input is always visible and required, handled by HTML `required` attribute.
    // This function now only controls the visibility and requirement of teacher initial fields.

    if (selectedClass.startsWith('P1') || selectedClass.startsWith('P2') || selectedClass.startsWith('P3')) {
        setInitialFields(commonSubjectInitials, true, true); // English, MTC initials
        setInitialFields(p1p3SubjectInitials, true, true);   // RE, Lit1, Lit2, Local Lang initials
        setInitialFields(p4p7SubjectInitials, false, false); // Science, SST, Kiswahili initials
    } else if (selectedClass.startsWith('P4') || selectedClass.startsWith('P5') || selectedClass.startsWith('P6') || selectedClass.startsWith('P7')) {
        setInitialFields(commonSubjectInitials, true, true); // English, MTC initials
        setInitialFields(p1p3SubjectInitials, false, false);
        setInitialFields(p4p7SubjectInitials, true, true);   // Science, SST, Kiswahili initials (Kiswahili requirement handled in setInitialFields)
    } else { // No class selected or unknown class
        setInitialFields(commonSubjectInitials, true, false); // Show common but don't require initially
        setInitialFields(p1p3SubjectInitials, false, false);
        setInitialFields(p4p7SubjectInitials, false, false);
    }
}

// Call updateSubjectFields on page load if a class is already selected (e.g., form resubmission with errors)
const initialClass = document.getElementById('class_selection')?.value;
if (initialClass) {
    updateSubjectFields(initialClass);
} else {
    // Default state: hide class-specific subjects until a class is chosen
    updateSubjectFields('');
}
