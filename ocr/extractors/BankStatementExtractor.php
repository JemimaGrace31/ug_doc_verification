<?php

class BankStatementExtractor
{
    public static function extract(string $text): array
    {
        $fields = [
            'document_detected' => true,
            'account_type' => 'UNKNOWN',
            'bank_name' => null,
            'statement_months' => null,
            'is_nri_account' => false
        ];

        $text = strtoupper($text);

        // Detect NRE / NRO / FCNR
      
        if (preg_match('/\bNRE\b/', $text)) {
            $fields['account_type'] = 'NRE';
            $fields['is_nri_account'] = true;
        } 
        elseif (preg_match('/\bNRO\b/', $text)) {
            $fields['account_type'] = 'NRO';
            $fields['is_nri_account'] = true;
        } 
        elseif (preg_match('/\bFCNR\b/', $text)) {
            $fields['account_type'] = 'FCNR';
            $fields['is_nri_account'] = true;
        }

        // Detect Foreign Currency
       
        if (preg_match('/\bAED\b|\bUSD\b|\bEUR\b|\bGBP\b/', $text, $currencyMatch)) {
            $fields['currency'] = $currencyMatch[0];
        }
        // Detect bank name
       
        if (preg_match('/CANARA BANK|STATE BANK OF INDIA|HDFC BANK|ICICI BANK|AXIS BANK|PUNJAB NATIONAL BANK|ABU DHABI BANK|FIRST ABU DHABI/', $text, $m)) {
            $fields['bank_name'] = trim($m[0]);
        }
        // Extract statement period
       
        if (preg_match('/(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})\s*(?:TO|-)\s*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/', $text, $m)) {
            $startDate = strtotime(str_replace('/', '-', $m[1]));
            $endDate = strtotime(str_replace('/', '-', $m[2]));

            if ($startDate && $endDate) {
                $months = round(($endDate - $startDate) / (30 * 24 * 60 * 60));
                $fields['statement_months'] = max(1, $months);
            }
        }

        return $fields;
    }
}