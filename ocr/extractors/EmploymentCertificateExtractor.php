<?php

class EmploymentCertificateExtractor
{
    public static function extract(string $text): array
    {
        $fields = ['document_detected' => true];
        $text = strtoupper($text);

        /*
           EMPLOYMENT TYPE / DESIGNATION
           (SEAFARER detection is key)
         */
        if (preg_match(
            '/(SEAFARER|ABLE\s+SEAMAN|DECK\s+CADET|ENGINE\s+CADET|OILER|MASTER\s+MARINER|CHIEF\s+ENGINEER)/',
            $text,
            $m
        )) {
            $fields['employment_type'] = 'SEAFARER';
            $fields['designation'] = trim($m[1]);
        } else {
            $fields['employment_type'] = 'NON-SEAFARER';
        }

        /*EMPLOYER / COMPANY NAME
           (Weak extraction – informational only) */
        if (preg_match(
            '/(EMPLOYED\s+WITH|WORKING\s+WITH|EMPLOYMENT\s+WITH)\s+([A-Z0-9\s&.,()-]{5,60})/',
            $text,
            $m
        )) {
            $fields['employer_name'] = trim($m[2]);
        }

        /* ISSUE DATE (MANDATORY) */
        if (preg_match(
            '/(DATED|DATE)\s*[:\-]?\s*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})/',
            $text,
            $m
        )) {
            $fields['issue_date'] = self::normalizeDate($m[2]);
        }

        /*  COUNTRY OF EMPLOYMENT */
        if (preg_match(
            '/\b(UAE|UNITED\s+ARAB\s+EMIRATES|QATAR|SAUDI\s+ARABIA|OMAN|KUWAIT|BAHRAIN)\b/',
            $text,
            $m
        )) {
            $fields['country'] = str_replace('UNITED ARAB EMIRATES', 'UAE', $m[1]);
        }

        return $fields;
    }

    private static function normalizeDate(string $date): string
    {
        $date = str_replace('/', '-', $date);
        [$d, $m, $y] = explode('-', $date);
        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }
}
