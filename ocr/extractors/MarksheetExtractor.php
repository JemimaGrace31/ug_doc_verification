<?php

class MarksheetExtractor
{
    public static function extract(string $text): array
    {
        $fields = ['document_detected' => true];
        $originalText = $text;
        $text = strtoupper($text);
        $text = preg_replace('/\s+/', ' ', $text); // normalize spaces

        // Debug: always save what we received
        file_put_contents(__DIR__ . '/../../logs/marksheet_debug.txt', "Original:\n" . $originalText . "\n\nNormalized:\n" . $text, FILE_APPEND);


        /*  BOARD DETECTION */

        if (preg_match('/CENTRAL BOARD OF SECONDARY EDUCATION|CBSE/', $text)) {
            $fields['board'] = 'CBSE';
        }
        elseif (preg_match('/BOARD OF INTERMEDIATE AND SECONDARY EDUCATION.*DHAKA/', $text)) {
            $fields['board'] = 'BANGLADESH_DHAKA';
        }
        elseif (preg_match('/STATE BOARD OF SCHOOL EXAMINATIONS/', $text)) {
            $fields['board'] = 'STATE_BOARD_INDIA';
        }
        else {
            $fields['board'] = 'UNKNOWN';
        }


        /*  RESULT (PASS / FAIL) */
       if (preg_match('/RESULT\s*[:\-]?\s*(PASS|FAIL)/i', $text, $m)) {
            $fields['result'] = strtoupper($m[1]);
        }
        
        elseif (preg_match('/\b(PASS|FAIL)\b/', $text, $m)) {
            $fields['result'] = strtoupper($m[1]);
        }



        /* YEAR (WITH CONTEXT) */

        if (preg_match('/EXAMINATION[^0-9]{0,20}(20\d{2})/', $text, $m)) {
            $fields['year'] = $m[1];
        }


        /*  STUDENT NAME EXTRACTION */
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

        //  CBSE 10th pattern
        
        if (empty($fields['student_name'])) {

            if (preg_match(
                '/CERTIFY\s+THAT\s+([A-Z\s\.]{5,80})\s+ROLL\s*NO\.?/i',
                $text,
                $m
            )) {
                $fields['student_name'] = trim(preg_replace('/\s+/', ' ', $m[1]));
            }
        }

        if ($fields['board'] === 'BANGLADESH_DHAKA') {

            if (preg_match_all('/GPA\s*[:\-]?\s*(\d+\.\d+)/', $text, $gpaMatches)) {

                $gpa = end($gpaMatches[1]); // take last GPA
                $fields['gpa'] = (float)$gpa;

                // GPA-based result
                $fields['result'] = $gpa > 0 ? 'PASS' : 'FAIL';
            }
        }


        /*  PCM MARKS EXTRACTION */

        $pcm = [];

        $subjectPatterns = [
            'PHYSICS'     => 'physics',
            'CHEMISTRY'   => 'chemistry',
            'MATHEMATICS STANDARD' => 'mathematics',
            'MATHEMATICS BASIC' => 'mathematics',
            'MATHEMATICS' => 'mathematics',
            'MATHS'       => 'mathematics'
        ];

        foreach ($subjectPatterns as $label => $key) {

            /*
            Pattern Type 1:
            041 MATHEMATICS 050 020 070
            (code subject theory practical total)
            */
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

            /*
            Pattern Type 2:
            PHYSICS 031 030
            (theory practical only — HSC format)
            */
            elseif (preg_match('/' . $label . '[^\d]{0,20}(\d{2,3})\s+(\d{2,3})/', $text, $m)) {

                $theory = (int)$m[1];
                $practical = (int)$m[2];

                if ($theory <= 100 && $practical <= 100) {
                    $pcm[$key] = $theory + $practical;
                }
            }

            /*
            Pattern Type 3:
            PHYSICS 086
            (single total mark format)
            */
            elseif (preg_match('/' . $label . '[^\d]{0,25}(\d{2,3})\b/', $text, $m)) {

                $mark = (int)$m[1];

                if ($mark <= 200) {
                    $pcm[$key] = $mark;
                }
            }

            /*
            Pattern Type 4: Bangladesh format
            136 PHYSICS 043 025 025 093
            (code subject theory practical mcq total)
            Only runs if previous patterns didn't match
            */
            if (!isset($pcm[$key]) && preg_match('/\d{3}\s+' . $label . '\s+(\d{2,3})\s+(\d{2,3})\s+(\d{2,3})\s+(\d{2,3})/', $text, $m)) {
                $total = (int)$m[4];
                if ($total <= 200) {
                    $pcm[$key] = $total;
                }
            }
        }

        /* PCM TOTAL & PERCENTAGE */

        if (count($pcm) >= 2) {

            $fields['pcm_marks'] = $pcm;

            if (count($pcm) === 3) {

                $total = array_sum($pcm);
                $fields['pcm_total'] = $total;

                $fields['pcm_percentage'] = round(($total / 300) * 100, 2);
            }
        }
        return $fields;
    }
}
