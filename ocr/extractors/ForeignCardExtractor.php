<?php

function extractForeignCard(string $ocrText): array
{
    $data = [
        'document_type'  => null,
        'card_number'    => null,
        'name'           => null,
        'nationality'    => null,
        'date_of_birth'  => null,
        'expiry_date'    => null
    ];

    $text = strtoupper($ocrText);

    /*  Detect Document Type */

    if (strpos($text, 'OVERSEAS CITIZEN OF INDIA') !== false ||
        strpos($text, 'OCI') !== false) {

        $data['document_type'] = 'OCI CARD';
    }

    if (strpos($text, 'PERSON OF INDIAN ORIGIN') !== false ||
        strpos($text, 'PIO') !== false) {

        $data['document_type'] = 'PIO CARD';
    }

    /*  Extract Card Number */

    if (preg_match('/(OCI|PIO)?\s*(NO|NUMBER)?[:\s]*([A-Z0-9]{6,})/', $text, $m)) {
        $data['card_number'] = $m[3];
    }

    /*  Extract Nationality*/

    if (preg_match('/NATIONALITY[:\s]*([A-Z ]+)/', $text, $m)) {
        $data['nationality'] = trim($m[1]);
    }

    /*  Extract Date of Birth */

    if (preg_match('/(DATE OF BIRTH|DOB)[:\s]*([0-9\/\-]+)/', $text, $m)) {
        $data['date_of_birth'] = normalizeDate($m[2]);
    }

    /*  Extract Expiry Date */

    if (preg_match('/(DATE OF EXPIRY|VALID TILL|EXPIRY)[:\s]*([0-9\/\-]+)/', $text, $m)) {
        $data['expiry_date'] = normalizeDate($m[2]);
    }

    /*Extract Name (Fallback) */

    if (preg_match('/NAME[:\s]*([A-Z\s]+)/', $text, $m)) {
        $data['name'] = trim($m[1]);
    }

    return [$data['document_type'] ?? 'FOREIGN CARD' => $data];
}


/*  UNIVERSAL DATE NORMALIZER */

function normalizeDate(string $date): ?string
{
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d', $timestamp);
}