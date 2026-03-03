<?php

class PassportExtractor
{
    public static function extract(string $text): array
    {
        $fields = ['document_detected' => true];

        // Normalize text
        $text = strtoupper(str_replace("\r", "", $text));

        /* =====================================================
           MRZ EXTRACTION (STRICT 2-LINE ICAO FORMAT)
        ===================================================== */

        if (preg_match_all('/P<[A-Z0-9<]{40,}/', $text, $mrzMatches)) {

            $mrzLines = array_values($mrzMatches[0]);

            if (count($mrzLines) >= 2) {

                // ICAO MRZ lines are exactly 44 characters
                $line1 = substr($mrzLines[0], 0, 44);
                $line2 = substr($mrzLines[1], 0, 44);

                /* =========================
                   LINE 1 → Country + Name
                   Format: P<CCCNAME<<GIVEN<<<<<<
                ========================== */

                if (preg_match('/^P<([A-Z]{3})([A-Z<]+)/', $line1, $m)) {

                    $fields['country_code'] = $m[1];
                    $fields['nationality'] = self::mapCountryCode($m[1]);

                    $nameBlock = $m[2];

                    if (preg_match('/^([A-Z]+)<<([A-Z<]+)/', $nameBlock, $n)) {
                        $fields['surname'] = str_replace('<', ' ', trim($n[1]));
                        $fields['given_names'] = str_replace('<', ' ', trim($n[2]));
                        $fields['full_name'] = trim($fields['given_names'] . ' ' . $fields['surname']);
                    }
                }

                /* =========================
                   LINE 2 → Core Data
                   Format:
                   PPPPPPPPP C NNN DDDDDD C S DDDDDD
                ========================== */

                if (preg_match('/^([A-Z0-9<]{9,10})([0-9])([A-Z]{3})([0-9]{6})([0-9])([MF<])([0-9]{6})/', $line2, $m)) {

                    // Passport Number (9-10 chars for different countries)
                    $fields['passport_number'] = str_replace('<', '', substr($m[1], 0, 9));

                    // Nationality from MRZ (Most reliable)
                    $fields['nationality_code'] = $m[3];
                    $fields['nationality'] = self::mapCountryCode($m[3]);

                    // Date of Birth (YYMMDD)
                    $yy = (int)substr($m[4], 0, 2);
                    $year = ($yy > 50) ? "19$yy" : "20$yy";
                    $fields['date_of_birth'] = "$year-" .
                        substr($m[4], 2, 2) . "-" .
                        substr($m[4], 4, 2);

                    // Sex
                    $fields['sex'] = ($m[6] === 'M')
                        ? 'MALE'
                        : (($m[6] === 'F') ? 'FEMALE' : 'UNSPECIFIED');

                    // Expiry Date (YYMMDD)
                    $expYY = (int)substr($m[7], 0, 2);
                    $expYear = ($expYY < 70) ? "20$expYY" : "19$expYY";
                    $fields['expiry_date'] = "$expYear-" .
                        substr($m[7], 2, 2) . "-" .
                        substr($m[7], 4, 2);
                }
            }
        }

        /* =====================================================
           FALLBACK TEXT EXTRACTION (If MRZ Not Found)
        ===================================================== */

        if (empty($fields['passport_number']) &&
            preg_match('/PASSPORT\s*(?:NO|NUMBER)[:\s]*([A-Z0-9]{6,12})/i', $text, $m)) {
            $fields['passport_number'] = $m[1];
        }

        if (empty($fields['nationality']) &&
            preg_match('/NATIONALITY[:\s]*([A-Z\s]+)/i', $text, $m)) {
            $fields['nationality'] = trim($m[1]);
        }

        if (preg_match('/PLACE\s*OF\s*BIRTH[:\s]*([A-Z\s,]+)/i', $text, $m)) {
            $fields['place_of_birth'] = trim($m[1]);
        }

        if (!empty($fields) && count($fields) > 1) {
            $fields['_document_valid'] = true;
        }

        return $fields;
    }

    /* =====================================================
       COUNTRY CODE MAPPING (ISO 3 LETTER → FULL NAME)
    ===================================================== */

    private static function mapCountryCode(string $code): string
    {
        $map = [

            'IND' => 'INDIAN',
            'USA' => 'UNITED STATES',
            'ARE' => 'UNITED ARAB EMIRATES',
            'SAU' => 'SAUDI ARABIA',
            'QAT' => 'QATAR',
            'OMN' => 'OMAN',
            'KWT' => 'KUWAIT',
            'GBR' => 'UNITED KINGDOM',
            'CAN' => 'CANADA',
            'AUS' => 'AUSTRALIA',
            'DEU' => 'GERMANY',
            'FRA' => 'FRANCE',
            'ITA' => 'ITALY',
            'SGP' => 'SINGAPORE',
            'MYS' => 'MALAYSIA',
            'NLD' => 'NETHERLANDS',
            'NZL' => 'NEW ZEALAND',
            'BGD' => 'BANGLADESHI',
            'NPL' => 'NEPAL',
            'LKA' => 'SRI LANKA'

        ];

        return $map[$code] ?? $code;
    }
}