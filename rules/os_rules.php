<?php

function runOsRules(array $allDocuments): array
{
    $flags = [];

    /*  Mandatory Documents */
    $mandatoryDocs = [
        'PHOTO'                    => 'Recently taken photograph',
        'SCHOOL TC'                => 'TC / Migration certificate',
        'PERMANENT RESIDENCE CERT' => 'Permanent Residence certificate'
    ];

    foreach ($mandatoryDocs as $docType => $label) {
        if (empty($allDocuments[$docType])) {
            $flags[] = [
                'type' => 'CRITICAL',
                'message' => "Missing mandatory document: $label"
            ];
        }
    }

    /* Permanent Residence Certificate Validation*/
    if (!empty($allDocuments['PERMANENT RESIDENCE CERT'])) {
        $cert = $allDocuments['PERMANENT RESIDENCE CERT'];

        if (empty($cert)) {
            $flags[] = [
                'type' => 'WARNING',
                'message' => 'Permanent Residence certificate uploaded but OCR extraction failed'
            ];
        } else {
            // Check if state is Tamil Nadu (should NOT be)
            if (!empty($cert['state'])) {
                $state = strtoupper($cert['state']);
                if (strpos($state, 'TAMIL NADU') !== false || strpos($state, 'TAMILNADU') !== false) {
                    $flags[] = [
                        'type' => 'CRITICAL',
                        'message' => 'Candidate must not be a native of Tamil Nadu (OS category)'
                    ];
                }
            } else {
                $flags[] = [
                    'type' => 'WARNING',
                    'message' => 'State information could not be extracted from Permanent Residence certificate'
                ];
            }
        }
    }

    return $flags;
}
