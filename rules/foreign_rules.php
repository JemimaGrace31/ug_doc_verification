<?php
require_once __DIR__ . '/../config/ldc_sids_countries.php';

function runForeignRules(array $allDocuments): array
{
    $flags = [];
    $today = date('Y-m-d');

    // Mandatory Documents

    if (empty($allDocuments['PASSPORT'])) {
        $flags[] = [
            'type' => 'CRITICAL',
            'message' => 'Passport of candidate is mandatory'
        ];
    }

    if (
        empty($allDocuments['VISA']) &&
        empty($allDocuments['PIO CARD']) &&
        empty($allDocuments['OCI CARD'])
    ) {
        $flags[] = [
            'type' => 'CRITICAL',
            'message' => 'Valid Student Visa / PIO / OCI card is mandatory'
        ];
    }

    if (
        ( !isset($allDocuments['SCHOOL TC']) || empty($allDocuments['SCHOOL TC']) ) &&
        ( !isset($allDocuments['MIGRATION CERTIFICATE']) || empty($allDocuments['MIGRATION CERTIFICATE']) )
    ) {
        $flags[] = [
            'type' => 'CRITICAL',
            'message' => 'School TC / Migration certificate is mandatory'
        ];
    }

    // Nationality Check
  

    if (!empty($allDocuments['PASSPORT'])) {

        foreach ($allDocuments['PASSPORT'] as $pass) {
            $passportCountry = strtoupper(trim($pass['nationality'] ?? ''));

            if (!empty($passportCountry)) {

                if (in_array($passportCountry, $LDC_COUNTRIES)) {
                    $flags[] = [
                        'type' => 'INFO',
                        'message' => "Candidate belongs to LDC country ($passportCountry)"
                    ];
                }

                if (in_array($passportCountry, $SIDS_COUNTRIES)) {
                    $flags[] = [
                        'type' => 'INFO',
                        'message' => "Candidate belongs to SIDS country ($passportCountry)"
                    ];
                }
            }

            if (!empty($pass['nationality']) &&
                strtoupper($pass['nationality']) === 'INDIAN') {

                $flags[] = [
                    'type' => 'CRITICAL',
                    'message' => 'Candidate must be a foreign national (Indian passport detected)'
                ];
            }

            if (!empty($pass['expiry_date']) &&
                $pass['expiry_date'] < $today) {

                $flags[] = [
                    'type' => 'CRITICAL',
                    'message' => "Passport expired on {$pass['expiry_date']}"
                ];
            }
        }
    }

    
       // Visa / PIO / OCI Validity
    

    $visaDocs = ['VISA', 'PIO CARD', 'OCI CARD'];

    foreach ($visaDocs as $docType) {

        if (!empty($allDocuments[$docType])) {

            foreach ($allDocuments[$docType] as $doc) {

                if (!empty($doc['expiry_date']) &&
                    $doc['expiry_date'] < $today) {

                    $flags[] = [
                        'type' => 'CRITICAL',
                        'message' => "$docType expired on {$doc['expiry_date']}"
                    ];
                }
            }
        }
    }

    // Financial Support
   

    $hasBank = !empty($allDocuments['BANK STATEMENT']);
    $hasScholarship = !empty($allDocuments['SCHOLARSHIP LETTER']);

    if (!$hasBank && !$hasScholarship) {
        $flags[] = [
            'type' => 'CRITICAL',
            'message' => 'Proof of financial support required (Bank statement 6 months or Scholarship letter)'
        ];
    }

    if ($hasBank) {
        foreach ($allDocuments['BANK STATEMENT'] as $bank) {

            if (!empty($bank['statement_months']) &&
                $bank['statement_months'] < 6) {

                $flags[] = [
                    'type' => 'CRITICAL',
                    'message' => 'Bank statement must cover at least 6 months'
                ];
            }
        }
    }

    return $flags;
}