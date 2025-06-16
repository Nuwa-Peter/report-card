<?php // calculation_utils.php

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
        if ($grade === 'N/A' && !isset($gradingScalePointsMap['N/A'])) return 0;
        return isset($gradingScalePointsMap[$grade]) ? (int)$gradingScalePointsMap[$grade] : 0;
    }
}

if (!function_exists('getSubjectEOTRemarkUtil')) {
    function getSubjectEOTRemarkUtil($eotScore, $remarksScoreMap) {
        if ($eotScore === null || $eotScore === '' || $eotScore === 'N/A' || !is_numeric($eotScore)) return 'N/A';
        $eotScore = floatval($eotScore);
        if ($eotScore < 0 || $eotScore > 100) return 'N/A';
        krsort($remarksScoreMap);
        foreach ($remarksScoreMap as $minScore => $remark) {
            if ($eotScore >= $minScore) {
                return $remark;
            }
        }
        return 'Fail';
    }
}

if (!function_exists('calculateP4P7OverallPerformanceUtil')) {
    // Note: $gradingScalePointsMap MUST be passed by the calling script (e.g., run_calculations.php)
    function calculateP4P7OverallPerformanceUtil($studentCoreSubjectsDataWithScores, $coreSubjectKeys, $gradingScalePointsMap) {
        $aggregatePoints = 0;
        $coreEOTMissingOrInvalidCount = 0;
        $validCoreEOTScoresExist = false;

        if (empty($gradingScalePointsMap)) { // Fallback, though caller should provide it.
            $gradingScalePointsMap = ['D1'=>1, 'D2'=>2, 'C3'=>3, 'C4'=>4, 'C5'=>5, 'C6'=>6, 'P7'=>7, 'P8'=>8, 'F9'=>9, 'N/A'=>0];
        }

        foreach ($coreSubjectKeys as $coreSubKey) {
            $subjectInfo = $studentCoreSubjectsDataWithScores[$coreSubKey] ?? null;
            if ($subjectInfo && isset($subjectInfo['eot_score']) && $subjectInfo['eot_score'] !== 'N/A' && is_numeric($subjectInfo['eot_score'])) {
                $validCoreEOTScoresExist = true;
                $eotGrade = getGradeFromScoreUtil($subjectInfo['eot_score']);
                $aggregatePoints += getPointsFromGradeUtil($eotGrade, $gradingScalePointsMap);
            } else {
                $coreEOTMissingOrInvalidCount++;
            }
        }

        if (!$validCoreEOTScoresExist && $coreEOTMissingOrInvalidCount === count($coreSubjectKeys)) {
            return ['p4p7_aggregate_points' => 0, 'p4p7_division' => 'X']; // MODIFIED
        }

        $division = 'Ungraded';
        if ($aggregatePoints >= 35 && $aggregatePoints <= 36) $division = 'U'; // MODIFIED
        else if ($aggregatePoints >= 30 && $aggregatePoints <= 34) $division = 'IV'; // MODIFIED
        else if ($aggregatePoints >= 24 && $aggregatePoints <= 29) $division = 'III'; // MODIFIED
        else if ($aggregatePoints >= 13 && $aggregatePoints <= 23) $division = 'II'; // MODIFIED
        else if ($aggregatePoints >= 4 && $aggregatePoints <= 12) $division = 'I'; // MODIFIED

        return ['p4p7_aggregate_points' => $aggregatePoints, 'p4p7_division' => $division];
    }
}


if (!function_exists('generateClassTeacherRemarkUtil')) {
    function generateClassTeacherRemarkUtil($performanceData, $isP4_P7) {
        if ($isP4_P7) {
            $division = $performanceData['p4p7_division'] ?? 'Ungraded';
            $aggregate = $performanceData['p4p7_aggregate_points'] ?? 0;
            switch ($division) { // MODIFIED cases
                case 'I': return ($aggregate <= 6) ? "Outstanding performance! A true star. Maintain this excellent standard and continue to inspire those around you." : "Excellent work! You are a dedicated and focused student. Keep aiming for the very top.";
                case 'II': return ($aggregate >= 13 && $aggregate <= 15) ? "Very good effort, you are on the cusp of achieving a First Grade! A little more focused effort will get you there. Yours is a promising future!" : "Good performance. Continue to work hard and you can achieve even better results next term.";
                case 'III': return "A fair performance this term. Consistent hard work and more concentration in class are essential for noticeable improvement.";
                case 'IV': return "There is significant room for improvement. Please apply more dedicated effort to all your studies and seek help where needed.";
                case 'U': return "This performance needs substantial improvement. Please seek guidance from your teachers and dedicate more time to consistent study.";
                case 'X': return "Missed End of Term examinations. It's important to participate fully to assess your progress and learning.";
                default: return "Performance requires more focused attention. Please concentrate on all subjects to achieve a commendable grade.";
            }
        } else {
            $average = $performanceData['p1p3_average_eot_score'] ?? 0;
            if ($average >= 85) return "Excellent work this term! You are a shining example to your classmates with such wonderful results.";
            if ($average >= 70) return "Very good performance. Keep up the commendable hard work and enthusiasm for learning!";
            if ($average >= 60) return "Good effort shown. Continue to apply yourself diligently and you will see even more progress.";
            if ($average >= 50) return "Satisfactory performance. With increased focus and consistent effort, you can achieve better results.";
            return "More focus and consistent effort are needed to improve your grades. Please try harder next term and don't hesitate to ask for help.";
        }
    }
}

if (!function_exists('generateHeadTeacherRemarkUtil')) {
    function generateHeadTeacherRemarkUtil($performanceData, $isP4_P7) {
        if ($isP4_P7) {
            $division = $performanceData['p4p7_division'] ?? 'Ungraded';
            $aggregate = $performanceData['p4p7_aggregate_points'] ?? 0;
            switch ($division) { // MODIFIED cases
                case 'I': return ($aggregate <= 6) ? "An exemplary performance that brings great pride to the school. Continue to be a role model for your peers!" : "Congratulations on achieving a First Grade! Your hard work and dedication are highly commendable. Thank you for your efforts.";
                case 'II': return ($aggregate >= 13 && $aggregate <= 15) ? "A very promising result! You clearly have the potential for the best First Grade. Keep striving for excellence with unwavering determination!" : "Well done on this Second Grade. Consistent effort and heeding your teachers' advice will certainly elevate you to greater heights. Add in more effort!";
                case 'III': return "A fair result. The school encourages you to redouble your efforts and seek guidance when needed for a better division next term.";
                case 'IV': return "Improvement is certainly needed. The school expects more dedication towards your academic work for a brighter future. Work harder.";
                case 'U': return "This performance is below the school's expectations. Please commit to serious improvement with support from teachers and parents.";
                case 'X': return "Examinations are a crucial part of the learning and assessment process. The school expects full participation in all academic activities.";
                default: return "Please work diligently across all subjects to earn a commendable grade. The school is here to support your academic growth and success.";
            }
        } else {
            $average = $performanceData['p1p3_average_eot_score'] ?? 0;
            if ($average >= 85) return "Outstanding academic achievement! The school is very proud of your excellent efforts and results. Keep it up!";
            if ($average >= 70) return "A very strong and commendable performance. Continue to apply yourself with such diligence and aim even higher. Well done.";
            if ($average >= 50) return "A satisfactory result. Keep working hard to unlock your full potential; we believe in your ability to improve further.";
            return "You are encouraged to improve your overall performance next term. Consistent effort and active participation make a significant difference. Add in more effort.";
        }
    }
}

?>
