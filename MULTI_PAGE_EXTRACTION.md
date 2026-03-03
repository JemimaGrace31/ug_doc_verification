# Multi-Page PDF Extraction Implementation

## Overview
Implemented multi-page PDF extraction to handle documents where multiple passports and visas are combined in a single PDF file (e.g., mother's passport, mother's visa, father's passport, father's visa all in one PDF).

## What Changed

### 1. run_ocr.php - Multi-Page Processing
**Before:** Only extracted data from first page of PDF
**After:** Processes EACH page separately

**Key Changes:**
- Loops through all pages in PDF
- Runs OCR on each page individually
- Detects document type per page (Passport, Visa, etc.)
- Routes each page to appropriate extractor
- Groups extracted data by detected document type

**Output Structure:**
```json
{
  "PASSPORT": [
    {
      "passport_number": "M1234567",
      "full_name": "Mother Name",
      "date_of_birth": "1980-01-15",
      ...
    },
    {
      "passport_number": "F7654321",
      "full_name": "Father Name",
      "date_of_birth": "1978-05-20",
      ...
    }
  ],
  "VISA": [
    {
      "visa_number": "V123456",
      "passport_number": "M1234567",
      ...
    },
    {
      "visa_number": "V789012",
      "passport_number": "F7654321",
      ...
    }
  ]
}
```

### 2. review.php - Updated Document Grouping
**Before:** Simple key-value mapping
**After:** Handles both single documents and arrays of documents

**Logic:**
- Detects if extracted_fields contains arrays (multi-page)
- Groups documents by detected type
- Passes all documents to rule engine for validation

### 3. Rule Engine Compatibility
**No changes needed!** Rule engines already support arrays:
- `nri_rules.php` can check multiple passports/visas
- `academic_rules.php` can validate multiple marksheets

## How It Works

### Step 1: PDF Upload
Student uploads one PDF containing:
- Page 1: Mother's Passport
- Page 2: Mother's Visa
- Page 3: Father's Passport
- Page 4: Father's Visa

### Step 2: OCR Processing
```
run_ocr.php processes:
├── Convert PDF to images (one per page)
├── For each page:
│   ├── Run Tesseract OCR
│   ├── Detect document type
│   ├── Route to appropriate extractor
│   └── Store extracted data
└── Group by document type
```

### Step 3: Data Storage
Stored in `ocr_extracted_data.extracted_fields` as JSON:
```json
{
  "PASSPORT": [
    {...mother's passport...},
    {...father's passport...}
  ],
  "VISA": [
    {...mother's visa...},
    {...father's visa...}
  ]
}
```

### Step 4: Rule Validation
Rule engine receives:
```php
$allDocuments = [
    'PASSPORT' => [
        [...mother's passport data...],
        [...father's passport data...]
    ],
    'VISA' => [
        [...mother's visa data...],
        [...father's visa data...]
    ]
];
```

Can validate:
- Both passports are valid
- Both visas are valid
- Visa passport numbers match passport documents
- All dates are within required ranges

## Benefits

1. **No Manual Splitting:** Students don't need to split PDFs
2. **Complete Extraction:** All documents in PDF are processed
3. **Automatic Detection:** System identifies document types automatically
4. **Rule Validation:** All documents validated against eligibility rules
5. **Staff Review:** Staff can see all extracted data for verification

## Testing

### Test Case 1: Single Document PDF
- Upload PDF with 1 page (passport)
- Expected: Single passport extracted
- Result: Works as before

### Test Case 2: Multi-Document PDF
- Upload PDF with 4 pages (2 passports + 2 visas)
- Expected: All 4 documents extracted and grouped
- Result: All extracted, grouped by type

### Test Case 3: Mixed Document PDF
- Upload PDF with passport + marksheet + visa
- Expected: Each detected and routed to correct extractor
- Result: All extracted with correct extractors

## Files Modified

1. `ocr/run_ocr.php` - Multi-page processing logic
2. `verification/review.php` - Document grouping for rule engine
3. Added batch mode support for JSON responses

## Next Steps

If you need to:
- **Add document owner tagging:** Implement Option 1 (add document_owner field)
- **Improve detection:** Enhance detectDocumentType() patterns
- **Handle more document types:** Add new extractors and detection patterns
