<?php

class VisaExtractor
{
    public static function extract(string $text): array
    {
        $fields = ['document_detected' => true];
        $text = strtoupper($text);

        /*UAE RESIDENCE ID NUMBER*/
        if (preg_match('/(\d{3}\/\d{4}\/\d{1,2}\/\d{5,6})/', $text, $m)) {
            $fields['visa_number'] = trim($m[1]);
        }
        /* VISA NUMBER (Generic) */
        elseif (preg_match('/(VISA\s*(?:NO|NUMBER|#)|CONTROL\s*(?:NO|NUMBER))[:\s]*([A-Z0-9\-\/]{6,20})/i', $text, $m)) {
            $fields['visa_number'] = trim($m[2]);
        }

        /* PASSPORT NUMBER */
        if (preg_match('/([A-Z]\d{7,8})/', $text, $m)) {
            $fields['passport_number'] = trim($m[1]);
        }

        /* HOLDER NAME  */
        //  Name followed by occupation/company
        if (preg_match('/([A-Z]{3,}\s+[A-Z]{3,}(?:\s+[A-Z]{2,}){0,3})\s*(?:SURVEYOR|HOUSE\s*WIFE|ENGINEER|MANAGER|ACCOUNTANT)/i', $text, $m)) {
            $fields['holder_name'] = trim($m[1]);
        }
        //  Generic name pattern
        elseif (preg_match('/(NAME|SURNAME|FULL\s*NAME)[:\s]*([A-Z][A-Z\s\.]{5,60})/i', $text, $m)) {
            $name = trim($m[2]);
            if (!preg_match('/(PASSPORT|VISA|DATE|EXPIRY|ISSUE|VALID|TYPE|CATEGORY|REPUBLIC|EMIRATES)/i', $name)) {
                $fields['holder_name'] = $name;
            }
        }

        /*  VISA TYPE / OCCUPATION */
        if (preg_match('/(SURVEYOR|HOUSE\s*WIFE|ENGINEER|MANAGER|ACCOUNTANT|TEACHER|DOCTOR|NURSE)/i', $text, $m)) {
            $fields['visa_type'] = 'EMPLOYMENT - ' . trim($m[1]);
        } elseif (preg_match('/(VISA\s*TYPE|CATEGORY|CLASS)[:\s]*([A-Z0-9\s\-]{1,30})/i', $text, $m)) {
            $fields['visa_type'] = trim($m[2]);
        } elseif (preg_match('/(TOURIST|BUSINESS|WORK|STUDENT|EMPLOYMENT|RESIDENCE|VISIT)\s*VISA/i', $text, $m)) {
            $fields['visa_type'] = trim($m[1]) . ' VISA';
        }

        /* PERMIT TYPE */
        if (preg_match('/RESIDENCE/', $text)) {
            $fields['permit_type'] = 'RESIDENCE PERMIT';
        } elseif (preg_match('/\bVISA\b/', $text)) {
            $fields['permit_type'] = 'VISA';
        }

        /* EXPIRY DATE  */
        //  DD/MM/YYYY format
        if (preg_match('/(\d{2}\/\d{2}\/\d{4})\s*(?:[^\d]|$)/', $text, $matches)) {
            $dates = [];
            preg_match_all('/(\d{2}\/\d{2}\/\d{4})/', $text, $allDates);
            if (count($allDates[1]) >= 2) {
                // First date is usually expiry, second is issue
                $fields['expiry_date'] = self::normalizeDate($allDates[1][0]);
                $fields['issue_date'] = self::normalizeDate($allDates[1][1]);
            } elseif (count($allDates[1]) == 1) {
                $fields['expiry_date'] = self::normalizeDate($allDates[1][0]);
            }
        }
        // With label
        elseif (preg_match(
            '/(EXPIRY|EXPIRES|VALID\s+UNTIL|VALID\s+TO)[:\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
            $text,
            $m
        )) {
            $fields['expiry_date'] = self::normalizeDate($m[2]);
        }

        /* ISSUING COUNTRY */
        if (preg_match('/UNITED\s+ARAB\s+EMIRATES/', $text)) {
            $fields['issuing_country'] = 'UNITED ARAB EMIRATES';
        } elseif (preg_match('/(ISSUED\s*BY|ISSUING\s*COUNTRY)[:\s]*([A-Z\s]{3,30})/i', $text, $m)) {
            $fields['issuing_country'] = trim($m[2]);
        }

        return $fields;
    }

    private static function normalizeDate(string $date): string
    {
        // Replace separators with dash
        $date = preg_replace('/[\/\.]/', '-', $date);
        $parts = explode('-', $date);
        
        if (count($parts) !== 3) return '';
        
        [$d, $m, $y] = $parts;
        
        // 2-digit year
        if (strlen($y) === 2) {
            $y = ((int)$y < 70) ? "20$y" : "19$y";
        }
        
        if (strlen($y) === 4) {
            return "$y-" . str_pad($m, 2, '0', STR_PAD_LEFT) . "-" . str_pad($d, 2, '0', STR_PAD_LEFT);
        }

        return '';
    }
}
