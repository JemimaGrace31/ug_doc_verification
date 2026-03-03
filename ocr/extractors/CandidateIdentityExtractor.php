<?php

class CandidateIdentityExtractor
{
    public static function extract(string $text): array
    {
        $fields = ['document_detected' => true];
        $text = strtoupper($text);

      
          // 1. PASSPORT DETECTION (MRZ)
  
        if (preg_match('/P<([A-Z]{3})([A-Z<]+)/', $text, $m)) {

            // Extract name from MRZ
            $nameBlock = $m[2];
            $nameParts = explode('<<', $nameBlock);

            if (!empty($nameParts[0])) {
                $surname = str_replace('<', ' ', $nameParts[0]);
                $given   = $nameParts[1] ?? '';
                $given   = preg_replace('/<+.*/', '', $given);
                $given   = str_replace('<', ' ', $given);

                $fields['candidate_name'] = trim(
                    preg_replace('/\s+/', ' ', "$surname $given")
                );
            }

            // DOB from MRZ
            if (preg_match('/\d{6}[MF]\d{6}/', $text, $d)) {
                $mrz = $d[0];
                $fields['date_of_birth'] =
                    '19' . substr($mrz, 0, 2) . '-' .
                    substr($mrz, 2, 2) . '-' .
                    substr($mrz, 4, 2);
            }

            $fields['document_source'] = 'PASSPORT';
            return $fields; // Passport is authoritative
        }

        // 2. BIRTH CERTIFICATE MODE

        // For birth certificates, only confirm detection without extracting unreliable fields
        $fields['document_source'] = 'BIRTH_CERTIFICATE';
        return $fields;
    }

       //Helper: Normalize date

    private static function normalizeDate(string $date): string
    {
        $date = str_replace(['.', '/'], '-', $date);
        $parts = explode('-', $date);

        if (count($parts) === 3) {
            if (strlen($parts[2]) === 2) {
                $parts[2] = '19' . $parts[2];
            }
            return "{$parts[2]}-{$parts[1]}-{$parts[0]}";
        }
        return $date;
    }
}
