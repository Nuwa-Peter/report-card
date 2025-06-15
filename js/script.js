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
    const commonSubjects = document.querySelectorAll('.common-subject');
    const p1p3Subjects = document.querySelectorAll('.p1p3-subject');
    const p4p7Subjects = document.querySelectorAll('.p4p7-subject');

    // Helper to set visibility and requirement for a group of subject blocks
    function setFields(elements, display, required) {
        elements.forEach(block => {
            block.style.display = display ? '' : 'none';
            const inputs = block.querySelectorAll('input[type="file"], input[type="text"]');
            inputs.forEach(input => {
                if (input.id === 'kiswahili_file' || input.id === 'kiswahili_initials') { // Kiswahili is special
                    if (selectedClass && selectedClass.startsWith('P') && parseInt(selectedClass.substring(1)) >= 4) { // P4-P7
                        input.required = false; // Kiswahili file and initials are optional for P4-P7
                    } else { // P1-P3 or no class selected
                         input.required = required && display;
                    }
                } else {
                     input.required = required && display;
                }
            });
        });
    }

    if (selectedClass.startsWith('P1') || selectedClass.startsWith('P2') || selectedClass.startsWith('P3')) {
        setFields(commonSubjects, true, true); // English, MTC
        setFields(p1p3Subjects, true, true);   // RE, Lit1, Lit2, Local Lang
        setFields(p4p7Subjects, false, false); // Science, SST, Kiswahili
    } else if (selectedClass.startsWith('P4') || selectedClass.startsWith('P5') || selectedClass.startsWith('P6') || selectedClass.startsWith('P7')) {
        setFields(commonSubjects, true, true); // English, MTC
        setFields(p1p3Subjects, false, false);
        setFields(p4p7Subjects, true, true);   // Science, SST, Kiswahili (Kiswahili requirement handled in setFields)
    } else { // No class selected or unknown class
        setFields(commonSubjects, true, false); // Show common but don't require initially
        setFields(p1p3Subjects, false, false);
        setFields(p4p7Subjects, false, false);
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
