<?php

class TransferCertificateExtractor
{
    public static function extract(string $text): array
    {
        $fields = ['document_detected' => true];
        $text = strtoupper($text);

        // Extract admission number
        if (preg_match('/(ADMISSION\\s*NO|ADMISSION\\s*NUMBER)[^\\d]{0,20}(\\d+)/', $text, $m)) {
            $fields['admission_no'] = trim($m[2]);
        }

        // Extract student name
        if (preg_match('/(NAME OF THE STUDENT|STUDENT NAME|NAME)\\s*[:\\-]?\\s*([A-Z][A-Z\\s\\.]{4,60})/', $text, $m)) {
            $name = trim($m[2]);
            if (!preg_match('/(FATHER|MOTHER|SCHOOL|CLASS)/', $name)) {
                $fields['student_name'] = $name;
            }
        }

        // Extract school/institution name - use specific keyword first
        if (preg_match('/(NAME OF THE SCHOOL|SCHOOL NAME)\\s*[:\\-]?\\s*([A-Z][A-Z\\s\\.\\-]{5,80})/', $text, $m)) {
            $fields['institution'] = trim($m[2]);
        }
        elseif (preg_match('/(SCHOOL|INSTITUTION|COLLEGE)\\s*[:\\-]?\\s*([A-Z][A-Z\\s\\.]{5,80})/', $text, $m)) {
            $name = trim($m[2]);
            if (!preg_match('/(RESPONSIBLE|CORRECTNESS|ENTRIES)/', $name)) {
                $fields['institution'] = $name;
            }
        }

        // Extract issue date
        if (preg_match('/DATE[^\\d]{0,20}(\\d{1,2}[\\-\\/]\\d{1,2}[\\-\\/]\\d{2,4})/', $text, $m)) {
            $fields['issue_date'] = $m[1];
        }

        // Extract class/standard 
        if (preg_match('/(STANDARD|STD)[^\\dXI]{0,10}(\\d{1,2}|XII|XI|X)/', $text, $m)) {
            $fields['class'] = $m[2];
        }
        elseif (preg_match('/CLASS[^\\d]{0,10}(\\d{1,2}|XII|XI|X)/', $text, $m)) {
            $fields['class'] = $m[1];
        }

        return $fields;
    }
}
