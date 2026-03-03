<?php

function runCiwgcRules(array $allDocuments): array
{
    $flags = [];
    $cutoffDate = '2025-03-01';
    $today = date('Y-m-d');

    $gulfCountries = [
        'UAE',
        'SAUDI ARABIA',
        'QATAR',
        'KUWAIT',
        'OMAN',
        'BAHRAIN'
    ];

    /* Helper function to find document by any variation */
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
        'SCHOOL TC' => ['SCHOOL TC', 'TRANSFER CERTIFICATE', 'MIGRATION CERTIFICATE']
    ];

    foreach ($mandatoryDocs as $label => $variations) {
        if (!findDocument($allDocuments, $variations)) {
            $flags[] = [
                'type' => 'CRITICAL',
                'message' => "Missing mandatory document: $label"
            ];
        }
    }

    // Special check: Birth Certificate OR Candidate Passport (either one is acceptable)
    $candidateIdentity = findDocument($allDocuments, ['BIRTH CERTIFICATE', 'CANDIDATE PASSPORT', 'PASSPORT']);
    if (!$candidateIdentity) {
        $flags[] = [
            'type' => 'CRITICAL',
            'message' => 'Missing mandatory document: Birth certificate OR Candidate passport'
        ];
    }

    // NRI CERTIFICATE CHECK
   

    if (!empty($allDocuments['NRI CERTIFICATE'])) {

        foreach ($allDocuments['NRI CERTIFICATE'] as $nri) {

            if (empty($nri['issue_date']) || $nri['issue_date'] < $cutoffDate) {
                $flags[] = [
                    'type' => 'CRITICAL',
                    'message' => 'NRI certificate must be issued after 1 March 2025'
                ];
            }

            if (!empty($nri['issuing_authority'])) {
                $authority = strtoupper($nri['issuing_authority']);

                if (
                    stripos($authority, 'EMBAS') === false &&
                    stripos($authority, 'CONSUL') === false
                ) {
                    $flags[] = [
                        'type' => 'CRITICAL',
                        'message' => 'NRI certificate not issued by Indian Embassy/Consulate'
                    ];
                }
            }
        }
    }

    // EMPLOYMENT CHECK
   

    if (!empty($allDocuments['EMPLOYMENT CERTIFICATE'])) {

        foreach ($allDocuments['EMPLOYMENT CERTIFICATE'] as $emp) {

            if (empty($emp['issue_date']) || $emp['issue_date'] < $cutoffDate) {
                $flags[] = [
                    'type' => 'CRITICAL',
                    'message' => 'Employment certificate must be issued after 1 March 2025'
                ];
            }

            if (!empty($emp['country'])) {
                if (!in_array(strtoupper($emp['country']), $gulfCountries)) {
                    $flags[] = [
                        'type' => 'CRITICAL',
                        'message' => 'Parent not working in Gulf country'
                    ];
                }
            }

            // Self-employed check 
            if (!empty($emp['employment_type']) &&
                strtoupper($emp['employment_type']) === 'SELF EMPLOYED') {

                if (empty($emp['nature_of_business']) ||
                    empty($emp['annual_income']) ||
                    empty($emp['income_tax_paid'])) {

                    $flags[] = [
                        'type' => 'CRITICAL',
                        'message' => 'Self-employed parent must provide business details, income & tax proof'
                    ];
                }
            }
        }
    }

    // BANK CHECK (6 Months)
    

    if (!empty($allDocuments['NRI BANK STATEMENT'])) {

        foreach ($allDocuments['NRI BANK STATEMENT'] as $bank) {

            if (!empty($bank['statement_months']) &&
                $bank['statement_months'] < 6) {

                $flags[] = [
                    'type' => 'CRITICAL',
                    'message' => 'NRI bank statement must be at least 6 months'
                ];
            }
        }
    }

    // ACADEMIC ELIGIBILITY


    $hscDoc = findDocument($allDocuments, ['HSC_MARKSHEET', 'HSC', '12TH MARKSHEET', '12TH_MARKSHEET', 'TWELFTH_MARKSHEET']);

    if ($hscDoc) {

        foreach ($hscDoc as $hsc) {

            if (!empty($hsc['maths']) &&
                !empty($hsc['physics']) &&
                !empty($hsc['chemistry'])) {

                $avg = (
                    $hsc['maths'] +
                    $hsc['physics'] +
                    $hsc['chemistry']
                ) / 3;

                if ($avg < 45) {
                    $flags[] = [
                        'type' => 'CRITICAL',
                        'message' => 'Minimum 45% required in Maths, Physics, Chemistry together'
                    ];
                }
            }

            if (!empty($hsc['failed_subjects']) &&
                $hsc['failed_subjects'] > 0) {

                $flags[] = [
                    'type' => 'CRITICAL',
                    'message' => 'Candidate has failed subjects'
                ];
            }
        }
    }

    return $flags;
}