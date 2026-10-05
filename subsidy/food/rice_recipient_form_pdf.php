<?php

const RICE_RECIPIENT_FORM_MARGIN = 10;
const RICE_RECIPIENT_FORM_NO_WIDTH = 10;
const RICE_RECIPIENT_FORM_NAME_WIDTH = 65;
const RICE_RECIPIENT_FORM_SECTOR_WIDTH = 30;
const RICE_RECIPIENT_FORM_SIGNATURE_WIDTH = 43;
const RICE_RECIPIENT_FORM_ROW_HEIGHT = 7.5;

function riceRecipientFormPdfText($text)
{
    $text = trim((string) $text);
    if ($text === '') {
        return '';
    }

    $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
    return $converted !== false ? $converted : $text;
}

function riceRecipientFormCleanNamePart($value)
{
    return trim(preg_replace('/\s+/', ' ', (string) $value));
}

function riceRecipientFormComparableNamePart($value)
{
    return strtoupper(riceRecipientFormCleanNamePart($value));
}

function riceRecipientFormNormalizeCommaName($value)
{
    $parts = array_map('riceRecipientFormCleanNamePart', explode(',', (string) $value));
    $lastName = array_shift($parts);
    $givenNames = implode(' ', array_values(array_filter($parts, function ($part) {
        return $part !== '';
    })));

    return $lastName . ',' . ($givenNames !== '' ? ' ' . $givenNames : '');
}

function riceRecipientFormNameReviews()
{
    static $reviewedNames = null;

    if ($reviewedNames === null) {
        $reviewedNames = [];
        $reviewPath = __DIR__ . '/rice_likely_swapped_names_review_2026-09-15.csv';
        $handle = @fopen($reviewPath, 'r');
        if ($handle !== false) {
            $header = fgetcsv($handle);
            while (($values = fgetcsv($handle)) !== false) {
                if (!$header || count($header) !== count($values)) {
                    continue;
                }
                $review = array_combine($header, $values);
                $codeKey = strtoupper(trim((string) ($review['household_code'] ?? '')));
                if ($codeKey !== '') {
                    $reviewedNames[$codeKey] = $review;
                }
            }
            fclose($handle);
        }
    }

    return $reviewedNames;
}

function riceRecipientFormReviewedName(array $record)
{
    $reviewedNames = riceRecipientFormNameReviews();
    $codeKey = strtoupper(trim((string) ($record['household_code'] ?? '')));
    $review = $reviewedNames[$codeKey] ?? null;
    if (!$review) {
        return null;
    }

    $storedName = riceRecipientFormComparableNamePart(
        riceRecipientFormNormalizeCommaName($record['household_name'] ?? '')
    );
    $reviewedOriginal = riceRecipientFormComparableNamePart(
        riceRecipientFormNormalizeCommaName($review['current_name'] ?? '')
    );
    $reviewedSuggestion = riceRecipientFormComparableNamePart(
        riceRecipientFormNormalizeCommaName($review['suggested_name'] ?? '')
    );
    if (
        $storedName === ''
        || ($storedName !== $reviewedOriginal && $storedName !== $reviewedSuggestion)
    ) {
        return null;
    }

    return riceRecipientFormNormalizeCommaName($review['suggested_name'] ?? '');
}

function riceRecipientFormVerifiedNameOverride(array $record)
{
    // Individually checked edge cases that have no strong graph evidence of
    // their own. Match the stored source text as well as the code so a future
    // database correction is never silently overwritten.
    $overrides = [
        'HD492' => ['YORE,SUAREZ', 'SUAREZ, YORE'],
        'R1250' => ['YOLINDO,TICONG', 'TICONG, YOLINDO'],
        'R1252' => ['YUMI,ARGAO', 'ARGAO, YUMI'],
        'R1253' => ['YVANNE,LUMOTOS', 'LUMOTOS, YVANNE'],
        'R1254' => ['ZENAIDA,LOMOTOS', 'LOMOTOS, ZENAIDA'],
        'R1255' => ['ZITA,BUTAWAN', 'BUTAWAN, ZITA'],
        'R1256' => ['ZUBELIN,SARABIA', 'SARABIA, ZUBELIN'],
        'R1617' => ['ZOSIMA,YAP', 'YAP, ZOSIMA'],
        'R1979' => ['ZECLAIRE,COHITMINGAO', 'COHITMINGAO, ZECLAIRE'],
        'R1983' => ['ZECHARIAH,BONITE', 'BONITE, ZECHARIAH'],
        'IND458' => ['ZENAIDA,UMPAD', 'UMPAD, ZENAIDA'],
    ];

    $codeKey = strtoupper(trim((string) ($record['household_code'] ?? '')));
    $override = $overrides[$codeKey] ?? null;
    if (!$override) {
        return null;
    }

    $storedName = riceRecipientFormComparableNamePart($record['household_name'] ?? '');
    return in_array(
        $storedName,
        [
            riceRecipientFormComparableNamePart($override[0]),
            riceRecipientFormComparableNamePart($override[1]),
        ],
        true
    )
        ? $override[1]
        : null;
}

function riceRecipientFormHistoricalNameReviews()
{
    static $reviewedNames = null;

    if ($reviewedNames === null) {
        $reviewedNames = [];
        $reviewFiles = [
            __DIR__ . '/rice_name_swap_audit_2026-08-12.csv',
            __DIR__ . '/rice_name_swap_medium_audit_2026-08-12.csv',
        ];
        foreach ($reviewFiles as $reviewPath) {
            $handle = @fopen($reviewPath, 'r');
            if ($handle === false) {
                continue;
            }
            $header = fgetcsv($handle);
            while (($values = fgetcsv($handle)) !== false) {
                if (!$header || count($header) !== count($values)) {
                    continue;
                }
                $review = array_combine($header, $values);
                $codeKey = strtoupper(trim((string) ($review['household_code'] ?? '')));
                if ($codeKey !== '') {
                    $reviewedNames[$codeKey] = $review;
                }
            }
            fclose($handle);
        }
    }

    return $reviewedNames;
}

function riceRecipientFormHistoricalReviewedName(array $record)
{
    $codeKey = strtoupper(trim((string) ($record['household_code'] ?? '')));
    $review = riceRecipientFormHistoricalNameReviews()[$codeKey] ?? null;
    if (!$review) {
        return null;
    }

    $storedName = riceRecipientFormComparableNamePart(
        riceRecipientFormNormalizeCommaName($record['household_name'] ?? '')
    );
    $oldName = riceRecipientFormComparableNamePart(
        riceRecipientFormNormalizeCommaName($review['old_name'] ?? '')
    );
    $newName = riceRecipientFormComparableNamePart(
        riceRecipientFormNormalizeCommaName($review['new_name'] ?? '')
    );
    if ($storedName === '' || ($storedName !== $oldName && $storedName !== $newName)) {
        return null;
    }

    return riceRecipientFormNormalizeCommaName($review['new_name'] ?? '');
}

function riceRecipientFormPublicReviewedName(array $record)
{
    static $reviewedNames = null;

    if ($reviewedNames === null) {
        $reviewedNames = [];
        $reviewPath = __DIR__ . '/rice_public_name_role_review_2026-09-29.csv';
        $handle = @fopen($reviewPath, 'r');
        if ($handle !== false) {
            $header = fgetcsv($handle);
            while (($values = fgetcsv($handle)) !== false) {
                if (!$header || count($header) !== count($values)) {
                    continue;
                }
                $review = array_combine($header, $values);
                $codeKey = strtoupper(trim((string) ($review['household_code'] ?? '')));
                if ($codeKey !== '') {
                    $reviewedNames[$codeKey] = $review;
                }
            }
            fclose($handle);
        }
    }

    $codeKey = strtoupper(trim((string) ($record['household_code'] ?? '')));
    $review = $reviewedNames[$codeKey] ?? null;
    if (!$review) {
        return null;
    }

    $storedName = riceRecipientFormComparableNamePart(
        riceRecipientFormNormalizeCommaName($record['household_name'] ?? '')
    );
    $reviewedOriginal = riceRecipientFormComparableNamePart(
        riceRecipientFormNormalizeCommaName($review['current_name'] ?? '')
    );
    $reviewedSuggestion = riceRecipientFormComparableNamePart(
        riceRecipientFormNormalizeCommaName($review['suggested_name'] ?? '')
    );
    if (
        $storedName === ''
        || ($storedName !== $reviewedOriginal && $storedName !== $reviewedSuggestion)
    ) {
        return null;
    }

    return riceRecipientFormNormalizeCommaName($review['suggested_name'] ?? '');
}

function riceRecipientFormIsReliableSurnameFirstRecord(array $record)
{
    // Import batch 10015 onward was loaded from a source whose columns are
    // explicitly LAST NAME, FIRST NAME. Earlier batches contain mixed order.
    return isset($record['id']) && (int) $record['id'] >= 10015;
}

function riceRecipientFormIsUnreviewedMixedImportRecord(array $record)
{
    if (!isset($record['id'])) {
        return false;
    }

    $id = (int) $record['id'];
    if ($id < 2162 || $id >= 10015) {
        return false;
    }

    $codeKey = strtoupper(trim((string) ($record['household_code'] ?? '')));
    return $codeKey !== ''
        && !isset(riceRecipientFormNameReviews()[$codeKey])
        && !isset(riceRecipientFormHistoricalNameReviews()[$codeKey]);
}

function riceRecipientFormNameRoleEvidence(array $records)
{
    $givenNames = [];
    $surnames = [];
    $reviews = riceRecipientFormNameReviews();

    $addEvidence = function (&$bucket, $value, $weight) {
        $key = riceRecipientFormComparableNamePart($value);
        if ($key !== '') {
            $bucket[$key] = ($bucket[$key] ?? 0) + $weight;
        }
    };

    // Supporting evidence from Philippine Statistics Authority publications
    // on common registered baby names and surnames. These carry modest weight;
    // the reviewed local records and verified import batch remain authoritative.
    $psaGivenNames = [
        'NATHANIEL', 'GABRIEL', 'JACOB', 'EZEKIEL', 'ETHAN', 'NATHAN',
        'MATTHEW', 'LIAM', 'JAYDEN', 'NOAH', 'ALTHEA', 'ANGEL', 'ZOEY',
        'CHLOE', 'NATHALIE', 'SOFIA', 'ZIA', 'PRINCESS', 'SAMANTHA',
        'AYESHA', 'JAMES', 'FRANCIS', 'JOSHUA', 'ANGELO', 'DANIEL',
        'ALEXANDER', 'JOHN MARK', 'ASHLEY', 'SOPHIA', 'ANDREA', 'ANGELA',
        'JANINE', 'CHRISTIAN', 'ALEXA',
    ];
    $psaSurnames = [
        'DELA CRUZ', 'GARCIA', 'REYES', 'RAMOS', 'MENDOZA', 'FLORES',
        'FERNANDEZ', 'GONZALES', 'LOPEZ', 'PEREZ', 'SANCHEZ',
        'VILLANUEVA',
    ];
    foreach ($psaGivenNames as $name) {
        $addEvidence($givenNames, $name, 1);
    }
    foreach ($psaSurnames as $name) {
        $addEvidence($surnames, $name, 1);
    }

    foreach ($reviews as $review) {
        $parts = array_map('riceRecipientFormCleanNamePart', explode(',', (string) ($review['suggested_name'] ?? ''), 2));
        if (count($parts) === 2) {
            $addEvidence($surnames, $parts[0], 3);
            $addEvidence($givenNames, $parts[1], 3);
            foreach (explode(' ', $parts[1]) as $token) {
                if (strlen($token) > 2) {
                    $addEvidence($givenNames, $token, 0.5);
                }
            }
        }
    }

    foreach ($records as $record) {
        $storedName = riceRecipientFormCleanNamePart($record['household_name'] ?? '');
        $parts = array_map('riceRecipientFormCleanNamePart', explode(',', $storedName, 2));
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            continue;
        }

        $codeKey = strtoupper(trim((string) ($record['household_code'] ?? '')));
        $review = $reviews[$codeKey] ?? null;
        $reliableSurnameFirst = riceRecipientFormIsReliableSurnameFirstRecord($record);
        $alreadyCorrected = $review
            && riceRecipientFormComparableNamePart($review['current_name'] ?? '')
                !== riceRecipientFormComparableNamePart($storedName);
        $strongUnreviewedPattern = !$review
            && strpos($parts[0], ' ') === false
            && strpos($parts[1], ' ') !== false;

        if (!$reliableSurnameFirst && !$alreadyCorrected && !$strongUnreviewedPattern) {
            continue;
        }

        $weight = $reliableSurnameFirst ? 3 : 1;
        $addEvidence($surnames, $parts[0], $weight);
        $addEvidence($givenNames, $parts[1], $weight);
        foreach (explode(' ', $parts[1]) as $token) {
            if (strlen($token) > 2) {
                $addEvidence($givenNames, $token, $reliableSurnameFirst ? 0.75 : 0.25);
            }
        }
    }

    // Propagate only high-margin evidence through the mixed-order batches.
    // Each pass contributes weak evidence, so a single ambiguous name cannot
    // outweigh the reviewed and clean-import sources above.
    for ($pass = 0; $pass < 8; ++$pass) {
        $givenDelta = [];
        $surnameDelta = [];
        foreach ($records as $record) {
            $codeKey = strtoupper(trim((string) ($record['household_code'] ?? '')));
            if (isset($reviews[$codeKey]) || riceRecipientFormIsReliableSurnameFirstRecord($record)) {
                continue;
            }

            $parts = array_map('riceRecipientFormCleanNamePart', explode(',', (string) ($record['household_name'] ?? ''), 2));
            if (
                count($parts) !== 2
                || $parts[0] === ''
                || $parts[1] === ''
                || strpos($parts[0], ' ') !== false
                || strpos($parts[1], ' ') !== false
            ) {
                continue;
            }

            $left = riceRecipientFormComparableNamePart($parts[0]);
            $right = riceRecipientFormComparableNamePart($parts[1]);
            $keepScore = ($surnames[$left] ?? 0) + ($givenNames[$right] ?? 0);
            $reverseScore = ($givenNames[$left] ?? 0) + ($surnames[$right] ?? 0);

            if ($reverseScore >= 0.75 && $reverseScore >= 4 * max(0.25, $keepScore)) {
                $givenDelta[$left] = ($givenDelta[$left] ?? 0) + 0.20;
                $surnameDelta[$right] = ($surnameDelta[$right] ?? 0) + 0.20;
            } elseif ($keepScore >= 0.75 && $keepScore >= 4 * max(0.25, $reverseScore)) {
                $surnameDelta[$left] = ($surnameDelta[$left] ?? 0) + 0.20;
                $givenDelta[$right] = ($givenDelta[$right] ?? 0) + 0.20;
            }
        }

        foreach ($givenDelta as $key => $weight) {
            $givenNames[$key] = ($givenNames[$key] ?? 0) + min(1, $weight);
        }
        foreach ($surnameDelta as $key => $weight) {
            $surnames[$key] = ($surnames[$key] ?? 0) + min(1, $weight);
        }
    }

    return ['given' => $givenNames, 'surname' => $surnames];
}

function riceRecipientFormShouldReverseByRole(array $record, array $roleEvidence)
{
    if (riceRecipientFormIsReliableSurnameFirstRecord($record)) {
        return false;
    }

    $storedName = riceRecipientFormCleanNamePart($record['household_name'] ?? '');
    $parts = array_map('riceRecipientFormCleanNamePart', explode(',', $storedName, 2));
    if (
        count($parts) !== 2
        || $parts[0] === ''
        || $parts[1] === ''
        || strpos($parts[0], ' ') !== false
        || strpos($parts[1], ' ') !== false
    ) {
        return false;
    }

    $codeKey = strtoupper(trim((string) ($record['household_code'] ?? '')));
    if (isset(riceRecipientFormNameReviews()[$codeKey])) {
        return false;
    }

    $left = riceRecipientFormComparableNamePart($parts[0]);
    $right = riceRecipientFormComparableNamePart($parts[1]);
    $given = $roleEvidence['given'] ?? [];
    $surname = $roleEvidence['surname'] ?? [];
    $keepScore = 4 * (($surname[$left] ?? 0) + ($given[$right] ?? 0));
    $reverseScore = 4 * (($given[$left] ?? 0) + ($surname[$right] ?? 0));

    return $reverseScore >= 4 && $reverseScore >= (3 * max(1, $keepScore));
}

function riceRecipientFormFormattedName(array $record, array $roleEvidence = [])
{
    $reviewedName = riceRecipientFormReviewedName($record);
    if ($reviewedName !== null && $reviewedName !== ',') {
        return $reviewedName;
    }

    $verifiedOverride = riceRecipientFormVerifiedNameOverride($record);
    if ($verifiedOverride !== null) {
        return $verifiedOverride;
    }

    $historicalReviewedName = riceRecipientFormHistoricalReviewedName($record);
    if ($historicalReviewedName !== null && $historicalReviewedName !== ',') {
        return $historicalReviewedName;
    }

    $publicReviewedName = riceRecipientFormPublicReviewedName($record);
    if ($publicReviewedName !== null && $publicReviewedName !== ',') {
        return $publicReviewedName;
    }

    $storedName = riceRecipientFormCleanNamePart($record['household_name'] ?? '');
    $firstName = riceRecipientFormCleanNamePart($record['first_name'] ?? '');
    $lastName = riceRecipientFormCleanNamePart($record['last_name'] ?? '');

    if ($firstName === '') {
        $firstName = riceRecipientFormCleanNamePart($record['reference_first_name'] ?? '');
    }
    if ($lastName === '') {
        $lastName = riceRecipientFormCleanNamePart($record['reference_last_name'] ?? '');
    }

    if (strpos($storedName, ',') !== false) {
        $parts = array_map('riceRecipientFormCleanNamePart', explode(',', $storedName));
        $storedLastName = array_shift($parts);
        $storedGivenNames = implode(' ', array_values(array_filter($parts, function ($part) {
            return $part !== '';
        })));

        // A small set of imported rows stored FIRST,LAST. Structured fields
        // and the original text agree on both components, so reverse only
        // those exact two-part matches. Extra middle names and suffixes stay
        // in the comma-delimited LAST,FIRST form supplied by the source.
        if (
            $storedLastName !== ''
            && $storedGivenNames !== ''
            && $firstName !== ''
            && $lastName !== ''
            && riceRecipientFormComparableNamePart($storedLastName) === riceRecipientFormComparableNamePart($firstName)
            && riceRecipientFormComparableNamePart($storedGivenNames) === riceRecipientFormComparableNamePart($lastName)
        ) {
            return $lastName . ', ' . $firstName;
        }

        if ($storedLastName !== '') {
            return $storedLastName . ',' . ($storedGivenNames !== '' ? ' ' . $storedGivenNames : '');
        }
    }

    if ($lastName !== '' || $firstName !== '') {
        return $lastName . ',' . ($firstName !== '' ? ' ' . $firstName : '');
    }

    return $storedName;
}

function riceRecipientFormGroup($sector)
{
    $sector = trim((string) $sector);
    $normalizedSector = strtoupper(preg_replace('/\s+/', ' ', $sector));

    if ($normalizedSector === 'PWD') {
        return [
            'key' => 'PWD',
            'label' => 'PWD',
            'kind' => 'pwd',
        ];
    }

    $namedSectors = [
        'HONEST DRIVER' => ['key' => 'HONEST DRIVERS', 'label' => 'HONEST DRIVERS', 'kind' => 'honest_drivers'],
        'HONEST DRIVERS' => ['key' => 'HONEST DRIVERS', 'label' => 'HONEST DRIVERS', 'kind' => 'honest_drivers'],
        'PORTER' => ['key' => 'PORTER', 'label' => 'PORTER', 'kind' => 'porter'],
        'URBAN POOR' => ['key' => 'URBAN POOR', 'label' => 'URBAN POOR', 'kind' => 'urban_poor'],
    ];
    if (isset($namedSectors[$normalizedSector])) {
        return $namedSectors[$normalizedSector];
    }

    $lowIncomeSectors = [
        '',
        'TAGBI',
        'B2',
        'B3',
        'B4',
        'B5',
        'JO',
        'IND',
        'IND2',
        'INDIGENT',
        'INDIGENTS',
    ];

    if (in_array($normalizedSector, $lowIncomeSectors, true)) {
        return [
            'key' => 'LOW INCOME',
            'label' => 'LOW INCOME',
            'kind' => 'low_income',
        ];
    }

    return [
        'key' => 'BARANGAY:' . $normalizedSector,
        'label' => $normalizedSector,
        'kind' => 'barangay',
    ];
}

function riceRecipientFormGroupedRecords(array $records)
{
    $groups = [];
    $roleEvidence = riceRecipientFormNameRoleEvidence($records);

    foreach ($records as $record) {
        $group = riceRecipientFormGroup($record['sector_source'] ?? '');
        if (!isset($groups[$group['key']])) {
            $groups[$group['key']] = [
                'label' => $group['label'],
                'kind' => $group['kind'],
                'records' => [],
            ];
        }

        $record['sector'] = $group['label'];
        $record['formatted_name'] = riceRecipientFormFormattedName($record, $roleEvidence);
        $groups[$group['key']]['records'][] = $record;
    }

    foreach ($groups as &$group) {
        usort($group['records'], function ($left, $right) {
            return strnatcasecmp($left['formatted_name'], $right['formatted_name']);
        });
    }
    unset($group);

    uasort($groups, function ($left, $right) {
        $rank = [
            'barangay' => 0,
            'pwd' => 1,
            'honest_drivers' => 2,
            'porter' => 3,
            'urban_poor' => 4,
            'low_income' => 5,
        ];
        $leftRank = $rank[$left['kind']] ?? 3;
        $rightRank = $rank[$right['kind']] ?? 3;

        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }

        return strnatcasecmp($left['label'], $right['label']);
    });

    return $groups;
}

class RiceAssistanceRecipientFormPDF extends FPDF
{
    public $distributionDates = [];

    public function Header()
    {
        $this->SetTextColor(18, 52, 59);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 6, 'RICE ASSISTANCE RECIPIENT', 0, 1, 'C');
        $this->Ln(2);
        $this->drawTableHeader();
    }

    public function Footer()
    {
        $this->SetY(-9);
        $this->SetTextColor(90, 90, 90);
        $this->SetFont('Arial', 'I', 7);
        $this->Cell(0, 5, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    public function drawTableHeader()
    {
        $startX = RICE_RECIPIENT_FORM_MARGIN;
        $startY = $this->GetY();
        $mainHeight = 7;
        $subHeight = 10;
        $totalHeight = $mainHeight + $subHeight;

        $this->SetDrawColor(74, 95, 105);
        $this->SetFillColor(15, 118, 110);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 8);

        $this->SetXY($startX, $startY);
        $this->Cell(RICE_RECIPIENT_FORM_NO_WIDTH, $totalHeight, 'NO.', 1, 0, 'C', true);
        $this->Cell(RICE_RECIPIENT_FORM_NAME_WIDTH, $totalHeight, 'NAME', 1, 0, 'C', true);
        $this->Cell(RICE_RECIPIENT_FORM_SECTOR_WIDTH, $totalHeight, 'SECTOR', 1, 0, 'C', true);
        $signatureX = $this->GetX();
        $signatureWidth = RICE_RECIPIENT_FORM_SIGNATURE_WIDTH * 4;
        $this->Cell($signatureWidth, $mainHeight, 'SIGNATURE', 1, 1, 'C', true);

        $distributionLabels = ['1ST', '2ND', '3RD', '4TH'];
        $this->SetFont('Arial', 'B', 6.5);
        foreach ($distributionLabels as $index => $label) {
            $x = $signatureX + ($index * RICE_RECIPIENT_FORM_SIGNATURE_WIDTH);
            $date = trim((string) ($this->distributionDates[$index] ?? ''));
            $dateLine = $date !== '' ? $date : '______________';
            $this->SetXY($x, $startY + $mainHeight);
            $this->MultiCell(
                RICE_RECIPIENT_FORM_SIGNATURE_WIDTH,
                5,
                $label . " DISTRIBUTION\nDATE: " . riceRecipientFormPdfText($dateLine),
                1,
                'C',
                true
            );
        }

        $this->SetXY($startX, $startY + $totalHeight);
        $this->SetTextColor(0, 0, 0);
    }

    public function ensureSpace($height)
    {
        if ($this->GetY() + $height > $this->GetPageHeight() - 12) {
            $this->AddPage();
            return false;
        }

        return true;
    }

    public function addGroupHeader($label, $kind, $continued = false)
    {
        $prefix = $kind === 'barangay' ? 'BARANGAY' : 'SECTOR';
        $suffix = $continued ? ' (CONTINUED)' : '';

        $this->SetFillColor(224, 242, 241);
        $this->SetTextColor(17, 94, 89);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(277, 6, $prefix . ': ' . riceRecipientFormPdfText($label) . $suffix, 1, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
    }

    public function addRecipientRow($number, array $record)
    {
        $this->SetDrawColor(90, 100, 108);
        $this->SetFont('Arial', '', 7.5);
        $this->Cell(RICE_RECIPIENT_FORM_NO_WIDTH, RICE_RECIPIENT_FORM_ROW_HEIGHT, (string) $number, 1, 0, 'C');
        $this->fitCell(
            RICE_RECIPIENT_FORM_NAME_WIDTH,
            RICE_RECIPIENT_FORM_ROW_HEIGHT,
            riceRecipientFormPdfText($record['formatted_name'] ?? riceRecipientFormFormattedName($record)),
            7.5,
            6.2,
            'L'
        );
        $this->fitCell(
            RICE_RECIPIENT_FORM_SECTOR_WIDTH,
            RICE_RECIPIENT_FORM_ROW_HEIGHT,
            riceRecipientFormPdfText($record['sector'] ?? ''),
            7,
            6,
            'C'
        );

        for ($column = 0; $column < 4; $column++) {
            $this->Cell(RICE_RECIPIENT_FORM_SIGNATURE_WIDTH, RICE_RECIPIENT_FORM_ROW_HEIGHT, '', 1, $column === 3 ? 1 : 0);
        }
    }

    private function fitCell($width, $height, $text, $fontSize, $minimumFontSize, $alignment)
    {
        $size = $fontSize;
        $this->SetFont('Arial', '', $size);

        while ($size > $minimumFontSize && $this->GetStringWidth($text) > $width - 3) {
            $size -= 0.25;
            $this->SetFont('Arial', '', $size);
        }

        $this->Cell($width, $height, $text, 1, 0, $alignment);
    }
}

function riceRecipientFormRender(
    array $records,
    $destination = 'I',
    $filename = 'Rice_Assistance_Recipient_Form.pdf',
    array $distributionDates = []
)
{
    $groups = riceRecipientFormGroupedRecords($records);
    $pdf = new RiceAssistanceRecipientFormPDF('L', 'mm', 'A4');
    $pdf->distributionDates = $distributionDates;
    $pdf->AliasNbPages();
    $pdf->SetMargins(RICE_RECIPIENT_FORM_MARGIN, 8, RICE_RECIPIENT_FORM_MARGIN);
    // Pagination is handled before every group header and recipient row so a
    // table cell can never trigger an automatic break halfway across a row.
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();

    if (empty($groups)) {
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(277, 12, 'No rice assistance recipients found.', 1, 1, 'C');
    } else {
        $number = 0;
        foreach ($groups as $group) {
            $pdf->ensureSpace(6 + RICE_RECIPIENT_FORM_ROW_HEIGHT);
            $pdf->addGroupHeader($group['label'], $group['kind']);

            foreach ($group['records'] as $record) {
                if (!$pdf->ensureSpace(RICE_RECIPIENT_FORM_ROW_HEIGHT)) {
                    $pdf->addGroupHeader($group['label'], $group['kind'], true);
                }

                $pdf->addRecipientRow(++$number, $record);
            }
        }
    }

    $pdf->Output($destination, $filename);
}
