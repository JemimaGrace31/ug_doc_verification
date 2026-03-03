<?php

class NriCertificateExtractor
{
    public static function extract(string $text): array
    {
        $fields = ['document_detected' => true];

        $originalText = $text;
        $text = strtoupper($text);
        $text = preg_replace('/\s+/', ' ', $text);

        /* ISSUING AUTHORITY */
        if (preg_match('/EMBASSY\s+OF\s+INDIA/', $text)) {
            $fields['issuing_authority'] = 'EMBASSY OF INDIA';
        }
        elseif (preg_match('/HIGH\s+COMMISSION/', $text)) {
            $fields['issuing_authority'] = 'HIGH COMMISSION';
        }
        elseif (preg_match('/CONSULATE/', $text)) {
            $fields['issuing_authority'] = 'CONSULATE';
        }

        /*  ISSUE DATE */
        $headerText = substr($text, 0, 800);
        
        if (preg_match('/(\d{1,2})(ST|ND|RD|TH)?\s+(JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)\s+(\d{4})/', $headerText, $m)) {

            $day = (int)$m[1];
            $month = self::monthToNumber($m[3]);
            $year = (int)$m[4];

            $fields['issue_date'] = sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        // Format: 23-05-2025
        elseif (preg_match('/DATED[:\s]+(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})/', $headerText, $m)) {
            $fields['issue_date'] = self::normalizeDate($m[1]);
        }

        // Generic numeric date 
        elseif (preg_match('/(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})/', $headerText, $m)) {
            $fields['issue_date'] = self::normalizeDate($m[1]);
        }

        /*  NAME EXTRACTION */

        if (preg_match('/CERTIFY\s+THAT\s+MR\.?\s+([A-Z\s]{5,60})/', $text, $m)) {
            $fields['name'] = trim(preg_replace('/\s+/', ' ', $m[1]));
        }
        elseif (preg_match('/CERTIFY\s+THAT\s+([A-Z\s]{5,60})/', $text, $m)) {

            $possibleName = trim(preg_replace('/\s+/', ' ', $m[1]));

            // Prevent long sentences
            if (strlen($possibleName) <= 60) {
                $fields['name'] = $possibleName;
            }
        }

        /*  COUNTRY DETECTION*/

        if (preg_match('/UNITED\s+ARAB\s+EMIRATES|UAE/', $text)) {
            $fields['country'] = 'UAE';
        }
        elseif (preg_match('/QATAR/', $text)) {
            $fields['country'] = 'QATAR';
        }
        elseif (preg_match('/SAUDI/', $text)) {
            $fields['country'] = 'SAUDI ARABIA';
        }

        /*  VALID DOCUMENT FLAG */

        if (count($fields) > 1) {
            $fields['_document_valid'] = true;
        }

        return $fields;
    }


    /* DATE NORMALIZATION */

    private static function normalizeDate(string $date): string
    {
        $date = str_replace(['.', '/'], '-', $date);
        $parts = explode('-', $date);

        if (count($parts) === 3) {
            return sprintf(
                '%04d-%02d-%02d',
                (int)$parts[2],
                (int)$parts[1],
                (int)$parts[0]
            );
        }

        return $date;
    }


    private static function monthToNumber(string $month): int
    {
        $months = [
            'JANUARY' => 1,
            'FEBRUARY' => 2,
            'MARCH' => 3,
            'APRIL' => 4,
            'MAY' => 5,
            'JUNE' => 6,
            'JULY' => 7,
            'AUGUST' => 8,
            'SEPTEMBER' => 9,
            'OCTOBER' => 10,
            'NOVEMBER' => 11,
            'DECEMBER' => 12
        ];

        return $months[$month] ?? 1;
    }
}