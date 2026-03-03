

<?php

function runNriRules(array $allDocuments): array
{
    $flags = [];
    $cutoffDate = '2025-03-01';
    $today = date('Y-m-d');

    // Helper function to find document by any variation 
    function findDocument($allDocuments, $variations) {
        foreach ($variations as $key) {
            if (!empty($allDocuments[$key])) {
                return $allDocuments[$key];
            }
        }
        return null;
    }

    // Mandatory Documents
    
    $mandatoryDocs = [
        'NRI CERTIFICATE' => ['NRI CERTIFICATE'],
        'EMPLOYMENT CERTIFICATE' => ['EMPLOYMENT CERTIFICATE'],
        'NRI BANK STATEMENT' => ['NRI BANK STATEMENT', 'BANK STATEMENT'],
        'PASSPORT' => ['PASSPORT', 'PARENT PASSPORT'],
        'VISA' => ['VISA', 'RESIDENCE PERMIT'],
        'BIRTH CERTIFICATE' => ['BIRTH CERTIFICATE', 'CANDIDATE PASSPORT', 'PASSPORT'],
        'SCHOOL TC' => ['SCHOOL TC', 'TRANSFER CERTIFICATE', 'MIGRATION CERTIFICATE']
    ];

    $labels = [
        'NRI CERTIFICATE' => 'Recent NRI status certificate',
        'EMPLOYMENT CERTIFICATE' => 'Employment certificate of NRI',
        'NRI BANK STATEMENT' => 'NRI bank account statement',
        'PASSPORT' => 'Valid Indian passport of parent/guardian',
        'VISA' => 'Valid visa / residence permit',
        'BIRTH CERTIFICATE' => 'Birth certificate / passport of candidate',
        'SCHOOL TC' => 'School TC / Migration certificate'
    ];

    foreach ($mandatoryDocs as $key => $variations) {
        if (!findDocument($allDocuments, $variations)) {
            $flags[] = [
                'type' => 'CRITICAL',
                'message' => "Missing mandatory document: {$labels[$key]}"
            ];
        }
    }

    //NRI CERTIFICATE
    if (!empty($allDocuments['NRI CERTIFICATE'])) {

        foreach ($allDocuments['NRI CERTIFICATE'] as $nri) {

            if (empty($nri)) {
                $flags[] = [
                    'type' => 'WARNING',
                    'message' => 'NRI certificate uploaded but OCR extraction failed'
                ];
                continue;
            }

            if (!empty($nri['issue_date']) && $nri['issue_date'] < $cutoffDate) {
                $flags[] = [
                    'type' => 'CRITICAL',
                    'message' => "NRI certificate issued before 1 March 2025 ({$nri['issue_date']})"
                ];
            }

            if (empty($nri['issue_date'])) {
                $flags[] = [
                    'type' => 'WARNING',
                    'message' => 'Unable to detect NRI certificate issue date'
                ];
            }
        }
    }

    // EMPLOYMENT
    if (!empty($allDocuments['EMPLOYMENT CERTIFICATE'])) {

        foreach ($allDocuments['EMPLOYMENT CERTIFICATE'] as $emp) {

            if (empty($emp)) {
                $flags[] = [
                    'type' => 'WARNING',
                    'message' => 'Employment certificate uploaded but OCR extraction failed'
                ];
                continue;
            }

            if (!empty($emp['issue_date']) && $emp['issue_date'] < $cutoffDate) {
                $flags[] = [
                    'type' => 'CRITICAL',
                    'message' => "Employment certificate issued before 1 March 2025 ({$emp['issue_date']})"
                ];
            }

            if (
                (!empty($emp['employment_type']) && $emp['employment_type'] === 'SEAFARER') ||
                (!empty($emp['designation']) && stripos($emp['designation'], 'SEAFARER') !== false)
            ) {
                if (empty($allDocuments['CDC'])) {
                    $flags[] = [
                        'type' => 'CRITICAL',
                        'message' => 'CDC is mandatory for seafarer parent/guardian'
                    ];
                }
            }
        }
    }

    // PASSPORT
    if (!empty($allDocuments['PASSPORT'])) {

        foreach ($allDocuments['PASSPORT'] as $pass) {

            if (empty($pass)) {
                $flags[] = [
                    'type' => 'WARNING',
                    'message' => 'Passport uploaded but OCR extraction failed'
                ];
                continue;
            }

            if (!empty($pass['nationality']) &&
                strtoupper($pass['nationality']) !== 'INDIAN') {

                $flags[] = [
                    'type' => 'CRITICAL',
                    'message' => "Passport nationality is not Indian ({$pass['nationality']})"
                ];
            }

            if (!empty($pass['expiry_date']) &&
                $pass['expiry_date'] !== '1970-01-01' &&
                $pass['expiry_date'] < $today) {

                $flags[] = [
                    'type' => 'CRITICAL',
                    'message' => "Passport expired on {$pass['expiry_date']}"
                ];
            }
        }
    }

    // VISA
    if (!empty($allDocuments['VISA'])) {

        foreach ($allDocuments['VISA'] as $visa) {

            if (empty($visa)) {
                $flags[] = [
                    'type' => 'WARNING',
                    'message' => 'Visa uploaded but OCR extraction failed'
                ];
                continue;
            }

            if (!empty($visa['expiry_date']) &&
                $visa['expiry_date'] !== '1970-01-01' &&
                $visa['expiry_date'] < $today) {

                $flags[] = [
                    'type' => 'CRITICAL',
                    'message' => "Visa / residence permit expired on {$visa['expiry_date']}"
                ];
            }
        }
    }

    // BANK
   
    $bankDoc = findDocument($allDocuments, ['NRI BANK STATEMENT', 'BANK STATEMENT']);
    
    if ($bankDoc) {
        foreach ($bankDoc as $bank) {

            if (empty($bank)) {
                $flags[] = [
                    'type' => 'WARNING',
                    'message' => 'Bank statement uploaded but OCR extraction failed'
                ];
                continue;
            }

            if (!empty($bank['account_type']) &&
                stripos($bank['account_type'], 'NRE') === false) {

                $flags[] = [
                    'type' => 'WARNING',
                    'message' => 'Bank account does not appear to be an NRE account'
                ];
            }
        }
    }

    return $flags;
}

