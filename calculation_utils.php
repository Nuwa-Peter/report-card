<?php // calculation_utils.php

// Ensure this file is not accessed directly if it's meant to be included
// defined('APP_RAN') or define('APP_RAN', true); // Example guard

// It's assumed these functions are already present at the top of this file from previous steps.
// For clarity, I'll re-state them here. If they ARE already in calculation_utils.php,
// then just append the new remark functions.

if (!function_exists('getGradeFromScoreUtil')) {
    function getGradeFromScoreUtil($score) {
        if ($score === null || $score === '' || $score === 'N/A' || !is_numeric($score)) return 'N/A';
        $score = floatval($score);
        if ($score > 100 || $score < 0) return 'N/A';
        if ($score >= 90) return 'D1';
        if ($score >= 80) return 'D2';
        if ($score >= 70) return 'C3';
        if ($score >= 60) return 'C4';
        if ($score >= 55) return 'C5';
        if ($score >= 50) return 'C6';
        if ($score >= 45) return 'P7';
        if ($score >= 40) return 'P8';
        return 'F9';
    }
}

if (!function_exists('getPointsFromGradeUtil')) {
    function getPointsFromGradeUtil($grade, $gradingScalePointsMap) {
        // Ensure N/A grade maps to 0 points if not already handled by map
        if ($grade === 'N/A' && !isset($gradingScalePointsMap['N/A'])) return 0;
        return isset($gradingScalePointsMap[$grade]) ? $gradingScalePointsMap[$grade] : 0;
    }
}

if (!function_exists('calculateDivisionAndAggregateP4P7Util')) {
    function calculateDivisionAndAggregateP4P7Util($studentSubjectsWithDetails, $coreSubjectKeysP4_P7, $gradingScalePointsMap) {
        $aggregatePoints = 0;
        $coreEOTMissingOrInvalidCount = 0;
        $validCoreEOTScoresExist = false;

        foreach ($coreSubjectKeysP4_P7 as $coreSubKey) {
            $subjectInfo = $studentSubjectsWithDetails[$coreSubKey] ?? null;
            // Use eot_score which is the raw score
            if ($subjectInfo && isset($subjectInfo['eot_score']) && $subjectInfo['eot_score'] !== 'N/A' && is_numeric($subjectInfo['eot_score'])) {
                $validCoreEOTScoresExist = true;
                $eotGrade = getGradeFromScoreUtil($subjectInfo['eot_score']);
                $aggregatePoints += getPointsFromGradeUtil($eotGrade, $gradingScalePointsMap);
            } else {
                $coreEOTMissingOrInvalidCount++;
            }
        }

        if (!$validCoreEOTScoresExist && $coreEOTMissingOrInvalidCount === count($coreSubjectKeysP4_P7)) {
            return ['p4p7_division' => 'Division X', 'p4p7_aggregate_points' => 0];
        }

        if ($aggregatePoints >= 35 && $aggregatePoints <= 36) return ['p4p7_division' => 'Grade U', 'p4p7_aggregate_points' => $aggregatePoints];
        if ($aggregatePoints >= 30 && $aggregatePoints <= 34) return ['p4p7_division' => 'Division Four', 'p4p7_aggregate_points' => $aggregatePoints];
        if ($aggregatePoints >= 24 && $aggregatePoints <= 29) return ['p4p7_division' => 'Division Three', 'p4p7_aggregate_points' => $aggregatePoints];
        if ($aggregatePoints >= 13 && $aggregatePoints <= 23) return ['p4p7_division' => 'Division Two', 'p4p7_aggregate_points' => $aggregatePoints];
        if ($aggregatePoints >= 4 && $aggregatePoints <= 12) return ['p4p7_division' => 'Division One', 'p4p7_aggregate_points' => $aggregatePoints];

        return ['p4p7_division' => 'Ungraded', 'p4p7_aggregate_points' => $aggregatePoints];
    }
}


/**
 * Generates automatic class teacher remarks.
 * @param array $studentOverallPerformance An array containing student's performance data.
 *        For P4-P7: expects 'p4p7_division', 'p4p7_aggregate_points'.
 *        For P1-P3: expects 'average_score_p1p3'.
 * @param bool $isP4_P7 Flag if student is in P4-P7.
 * @return string The generated remark.
 */
function generateClassTeacherRemarkUtil($studentOverallPerformance, $isP4_P7) {
    // MOT vs EOT improvement logic is deferred as MOT data isn't currently aggregated for this function.
    // User's provided logic: "if they improved in eot, you can can write; Class teacher's remarks; Great improvement, keep focused, or your's a promising student, first grade needed, if they are near to the first grade."

    if ($isP4_P7) {
        $division = $studentOverallPerformance['p4p7_division'] ?? 'Ungraded';
        $aggregate = $studentOverallPerformance['p4p7_aggregate_points'] ?? 0; // Aggregate of 4 core subjects

        switch ($division) {
            case 'Division One':
                if ($aggregate <= 6) return "Outstanding performance! A true star. Maintain this excellent standard and continue to inspire those around you.";
                return "Excellent work! You are a dedicated and focused student. Keep aiming for the very top.";
            case 'Division Two':
                // Proximity to First Grade (12 points is upper limit for Div 1)
                if ($aggregate >= 13 && $aggregate <= 15) {
                    return "Very good effort, you are on the cusp of achieving a First Grade! A little more focused effort will get you there. Yours is a promising future!";
                }
                return "Good performance. Continue to work hard and you can achieve even better results next term.";
            case 'Division Three':
                return "A fair performance this term. Consistent hard work and more concentration in class are essential for noticeable improvement.";
            case 'Division Four':
                return "There is significant room for improvement. Please apply more dedicated effort to all your studies and seek help where needed.";
            case 'Grade U':
                return "This performance needs substantial improvement. Please seek guidance from your teachers and dedicate more time to consistent study.";
            case 'Division X':
                return "Missed End of Term examinations. It's important to participate fully to assess your progress and learning.";
            default: // Ungraded or other
                return "Performance requires more focused attention. Please concentrate on all subjects to achieve a commendable grade.";
        }
    } else { // P1-P3
        $average = $studentOverallPerformance['average_score_p1p3'] ?? 0;
        if ($average >= 85) return "Excellent work this term! You are a shining example to your classmates with such wonderful results.";
        if ($average >= 70) return "Very good performance. Keep up the commendable hard work and enthusiasm for learning!";
        if ($average >= 60) return "Good effort shown. Continue to apply yourself diligently and you will see even more progress.";
        if ($average >= 50) return "Satisfactory performance. With increased focus and consistent effort, you can achieve better results.";
        return "More focus and consistent effort are needed to improve your grades. Please try harder next term and don't hesitate to ask for help.";
    }
}

/**
 * Generates automatic head teacher remarks.
 * @param array $studentOverallPerformance (Similar to class teacher's function)
 * @param bool $isP4_P7 Flag if student is in P4-P7.
 * @return string The generated remark.
 */
function generateHeadTeacherRemarkUtil($studentOverallPerformance, $isP4_P7) {
    // User's provided logic: "headtaecher's remarks can be; keep it up. or you have the potential for the best first grade! or they should be based on those in first grade, thanking them from both the class teacher and the head teacher, then the ones in second grade should be advised to add in more effort with something encouraging, and so on for the lower classes."

    if ($isP4_P7) {
        $division = $studentOverallPerformance['p4p7_division'] ?? 'Ungraded';
        $aggregate = $studentOverallPerformance['p4p7_aggregate_points'] ?? 0;

        switch ($division) {
            case 'Division One':
                 return ($aggregate <= 6) ? "An exemplary performance that brings great pride to the school. Continue to be a role model for your peers!" : "Congratulations on achieving a First Grade! Your hard work and dedication are highly commendable. Thank you for your efforts.";
            case 'Division Two':
                 if ($aggregate >= 13 && $aggregate <= 15) { // Near first grade
                    return "A very promising result! You clearly have the potential for the best First Grade. Keep striving for excellence with unwavering determination!";
                 }
                return "Well done on this Second Grade. Consistent effort and heeding your teachers' advice will certainly elevate you to greater heights. Add in more effort!";
            case 'Division Three':
                return "A fair result. The school encourages you to redouble your efforts and seek guidance when needed for a better division next term.";
            case 'Division Four':
                return "Improvement is certainly needed. The school expects more dedication towards your academic work for a brighter future. Work harder.";
            case 'Grade U':
                return "This performance is below the school's expectations. Please commit to serious improvement with support from teachers and parents.";
            case 'Division X':
                return "Examinations are a crucial part of the learning and assessment process. The school expects full participation in all academic activities.";
            default:
                return "Please work diligently across all subjects to earn a commendable grade. The school is here to support your academic growth and success.";
        }
    } else { // P1-P3
        $average = $studentOverallPerformance['average_score_p1p3'] ?? 0;
        if ($average >= 85) return "Outstanding academic achievement! The school is very proud of your excellent efforts and results. Keep it up!";
        if ($average >= 70) return "A very strong and commendable performance. Continue to apply yourself with such diligence and aim even higher. Well done.";
        if ($average >= 50) return "A satisfactory result. Keep working hard to unlock your full potential; we believe in your ability to improve further.";
        return "You are encouraged to improve your overall performance next term. Consistent effort and active participation make a significant difference. Add in more effort.";
    }
}

?>
