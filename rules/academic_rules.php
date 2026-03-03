<?php

function runAcademicRules(array $allDocuments): array
{
    $flags = [];

    // Helper function to find document by any variation 
    function findAcademicDocument($allDocuments, $variations) {
        foreach ($variations as $key) {
            if (!empty($allDocuments[$key])) {
                return $allDocuments[$key];
            }
        }
        return null;
    }

    // Mandatory Documents 
    $mandatoryDocs = [
        '10th Marksheet' => ['TENTH_MARKSHEET', 'SSLC', 'SSLC_MARKSHEET', '10TH MARKSHEET', '10TH_MARKSHEET'],
        '12th Marksheet' => ['HSC_MARKSHEET', 'HSC', '12TH MARKSHEET', '12TH_MARKSHEET', 'TWELFTH_MARKSHEET']
    ];

    foreach ($mandatoryDocs as $label => $variations) {
        if (!findAcademicDocument($allDocuments, $variations)) {
            $flags[] = [
                'type' => 'CRITICAL',
                'message' => "$label is mandatory"
            ];
        }
    }

    $hscDoc = findAcademicDocument($allDocuments, ['HSC_MARKSHEET', 'HSC', '12TH MARKSHEET', '12TH_MARKSHEET', 'TWELFTH_MARKSHEET']);
    $tenthDoc = findAcademicDocument($allDocuments, ['TENTH_MARKSHEET', 'SSLC', 'SSLC_MARKSHEET', '10TH MARKSHEET', '10TH_MARKSHEET']);

    // Qualifying Exam Passed
    if ($hscDoc && !empty($hscDoc['result'])) {
        if (strtoupper($hscDoc['result']) !== 'PASS') {
            $flags[] = [
                'type' => 'CRITICAL',
                'message' => 'Candidate has not passed the qualifying examination'
            ];
        }
    } else {
        $flags[] = [
            'type' => 'WARNING',
            'message' => 'Pass/Fail status could not be detected from 12th marksheet'
        ];
    }

    //  Board / Exam Type (Advisory)
    if ($hscDoc && !empty($hscDoc['board'])) {
        $board = strtoupper($hscDoc['board']);
        $validHints = ['BOARD', 'CBSE', 'ICSE', 'STATE', 'HSC'];

        $matched = false;
        foreach ($validHints as $hint) {
            if (strpos($board, $hint) !== false) {
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            $flags[] = [
                'type' => 'WARNING',
                'message' => 'Qualifying examination board could not be confidently verified'
            ];
        }
    }

    // PCM Marks 
    if ($hscDoc && !empty($hscDoc['pcm_marks'])) {

        $pcm = $hscDoc['pcm_marks'];
        $required = ['physics', 'chemistry', 'mathematics'];
        $missing = [];

        foreach ($required as $sub) {
            if (empty($pcm[$sub])) {
                $missing[] = ucfirst($sub);
            }
        }

        if (!empty($missing)) {
            $flags[] = [
                'type' => 'WARNING',
                'message' => 'Incomplete PCM marks detected (' . implode(', ', $missing) . '). Manual verification required'
            ];
        }

        if ($hscDoc && !empty($hscDoc['pcm_percentage'])) {
            $pcmPct = $hscDoc['pcm_percentage'];
            if ($pcmPct < 45) {
                $flags[] = [
                    'type' => 'CRITICAL',
                    'message' => "PCM percentage is {$pcmPct}% (Minimum required: 45%)"
                ];
            }
        } else {
            $flags[] = [
                'type' => 'WARNING',
                'message' => 'PCM percentage could not be calculated automatically'
            ];
        }

    } else {
        $flags[] = [
            'type' => 'WARNING',
            'message' => 'PCM subject marks could not be reliably extracted; manual verification required'
        ];
    }

    /* Name Consistency (Advisory)
       Identity is primarily verified via passport/birth certificate */
    $names = [];

    if ($tenthDoc && !empty($tenthDoc['student_name'])) {
        $names[] = strtolower(trim($tenthDoc['student_name']));
    }

    if ($hscDoc && !empty($hscDoc['student_name'])) {
        $names[] = strtolower(trim($hscDoc['student_name']));
    }

    if (count(array_unique($names)) > 1) {
        $flags[] = [
            'type' => 'WARNING',
            'message' => 'Candidate name varies across academic documents'
        ];
    }

    return $flags;
}
