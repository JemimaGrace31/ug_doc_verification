<?php

class MarksheetExtractor
{
    public static function extract(string $text): array
    {
        $fields = ['document_detected' => true];

        $originalText = $text;

        $text = strtoupper($text);
        $text = preg_replace('/\s+/', ' ', $text); // normalize spaces

        file_put_contents(
            __DIR__ . '/../../logs/marksheet_debug.txt',
            "Original:\n".$originalText."\n\nNormalized:\n".$text."\n\n====================\n\n",
            FILE_APPEND
        );

        /* BOARD DETECTION */

        if (preg_match('/CENTRAL BOARD OF SECONDARY EDUCATION|CBSE/', $text)) {
            $fields['board'] = 'CBSE';
        }
        elseif (preg_match('/BOARD OF INTERMEDIATE AND SECONDARY EDUCATION.*DHAKA/', $text)) {
            $fields['board'] = 'BANGLADESH_DHAKA';
        }
        elseif (preg_match('/STATE BOARD OF SCHOOL EXAMINATIONS/', $text)) {
            $fields['board'] = 'STATE_BOARD_INDIA';
        }
        elseif (preg_match('/CAMBRIDGE|ORDINARY LEVEL|GCE/', $text)) {
            $fields['board'] = 'CAMBRIDGE';
        }
        else {
            $fields['board'] = 'UNKNOWN';
        }

        /* RESULT (PASS / FAIL) */

        if (preg_match('/RESULT\s*[:\-]?\s*(PASS|FAIL)/i', $text, $m)) {
            $fields['result'] = strtoupper($m[1]);
        }
        elseif (preg_match('/\b(PASS|FAIL)\b/', $text, $m)) {
            $fields['result'] = strtoupper($m[1]);
        }

        /* YEAR */

        if (preg_match('/EXAMINATION[^0-9]{0,20}(20\d{2})/', $text, $m)) {
            $fields['year'] = $m[1];
        }

        /* STUDENT NAME */

        if (preg_match(
            '/(NAME OF THE CANDIDATE|CANDIDATE NAME|NAME OF STUDENT|STUDENT NAME)\s*[:\-]?\s*([A-Z][A-Z\s\.]{4,60})/',
            $text,
            $m
        )) {
            $name = trim($m[2]);

            if (
                stripos($name, 'FATHER') === false &&
                stripos($name, 'MOTHER') === false &&
                stripos($name, 'ROLL') === false
            ) {
                $fields['student_name'] = preg_replace('/\s+/', ' ', $name);
            }
        }

        if (empty($fields['student_name'])) {

            if (preg_match(
                '/CERTIFY\s+THAT\s+([A-Z\s\.]{5,80})\s+ROLL\s*NO/i',
                $text,
                $m
            )) {
                $fields['student_name'] = trim(preg_replace('/\s+/', ' ', $m[1]));
            }
        }

        /* BANGLADESH GPA */

        if ($fields['board'] === 'BANGLADESH_DHAKA') {

            if (preg_match_all('/GPA\s*[:\-]?\s*(\d+\.\d+)/', $text, $gpaMatches)) {

                $gpa = end($gpaMatches[1]);
                $fields['gpa'] = (float)$gpa;
                $fields['result'] = $gpa > 0 ? 'PASS' : 'FAIL';
            }
        }

        /* PCM MARK EXTRACTION */

        $pcm = [];

        $subjectPatterns = [

            'PHYSICS' => 'physics',
            'CHEMISTRY' => 'chemistry',

            'MATHEMATICS STANDARD' => 'mathematics',
            'MATHEMATICS BASIC' => 'mathematics',
            'MATHEMATICS' => 'mathematics',
            'MATHS' => 'mathematics',

        ];

        foreach ($subjectPatterns as $label => $key) {

            if (preg_match('/\d{3}\.?\s+' . $label . '\s+(\d{2,3})\s+(\d{2,3})\s+(\d{2,3})/', $text, $m)) {

                $theory = (int)$m[1];
                $practical = (int)$m[2];
                $totalColumn = (int)$m[3];

                if ($totalColumn >= $theory && $totalColumn <= 200) {
                    $pcm[$key] = $totalColumn;
                }
                else {
                    $pcm[$key] = $theory + $practical;
                }
            }

            elseif (preg_match('/' . $label . '[^\d]{0,20}(\d{2,3})\s+(\d{2,3})/', $text, $m)) {

                $theory = (int)$m[1];
                $practical = (int)$m[2];

                if ($theory <= 100 && $practical <= 100) {
                    $pcm[$key] = $theory + $practical;
                }
            }

            elseif (preg_match('/' . $label . '[^\d]{0,25}(\d{2,3})\b/', $text, $m)) {

                $mark = (int)$m[1];

                if ($mark <= 200) {
                    $pcm[$key] = $mark;
                }
            }

            if (!isset($pcm[$key]) && preg_match('/\d{3}\s+' . $label . '\s+(\d{2,3})\s+(\d{2,3})\s+(\d{2,3})\s+(\d{2,3})/', $text, $m)) {

                $total = (int)$m[4];

                if ($total <= 200) {
                    $pcm[$key] = $total;
                }
            }
        }

        /* NUMERIC PCM RESULT */

        if (count($pcm) >= 2) {

            $fields['pcm_marks'] = $pcm;

            if (count($pcm) === 3) {

                $total = array_sum($pcm);

                $fields['pcm_total'] = $total;
                $fields['pcm_percentage'] = round(($total / 300) * 100, 2);
            }
        }

        /* UNIVERSAL GRADE EXTRACTION (FOREIGN BOARDS) */

        if (!isset($fields['pcm_marks'])) {

            $gradeMap = [

                'A1'=>95,'A2'=>90,'A'=>90,
                'B'=>75,'C'=>65,'D'=>55,'E'=>45,'F'=>30,
                'DIST'=>95,

                'ONE'=>95,'TWO'=>85,'THREE'=>75,
                'FOUR'=>65,'FIVE'=>55,'SIX'=>45,
                'SEVEN'=>35,'EIGHT'=>25
            ];

            $grades = [];

            if (preg_match('/MATHEMATICS.{0,40}(A1|A2|A|B|C|D|E|F|DIST|ONE|TWO|THREE|FOUR|FIVE|SIX|SEVEN|EIGHT)/', $text, $m)) {

                $grades['mathematics'] = $gradeMap[$m[1]] ?? null;
            }

            if (preg_match('/PHYSICS.{0,40}(A1|A2|A|B|C|D|E|F|DIST|ONE|TWO|THREE|FOUR|FIVE|SIX|SEVEN|EIGHT)/', $text, $m)) {

                $grades['physics'] = $gradeMap[$m[1]] ?? null;
            }

            if (preg_match('/CHEMISTRY.{0,40}(A1|A2|A|B|C|D|E|F|DIST|ONE|TWO|THREE|FOUR|FIVE|SIX|SEVEN|EIGHT)/', $text, $m)) {

                $grades['chemistry'] = $gradeMap[$m[1]] ?? null;
            }

            if (preg_match('/SCIENCE.{0,40}(A1|A2|A|B|C|D|E|F|DIST|ONE|TWO|THREE|FOUR|FIVE|SIX|SEVEN|EIGHT)/', $text, $m)) {

                $score = $gradeMap[$m[1]] ?? null;

                if ($score) {
                    $grades['physics'] = $score;
                    $grades['chemistry'] = $score;
                }
            }

            if (count($grades) >= 2) {

                $fields['pcm_marks'] = $grades;

                if (count($grades) === 3) {

                    $total = array_sum($grades);

                    $fields['pcm_total'] = $total;
                    $fields['pcm_percentage'] = round(($total / 300) * 100, 2);
                }
            }
        }

        return $fields;
    }
}