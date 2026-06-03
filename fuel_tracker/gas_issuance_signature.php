<?php
declare(strict_types=1);

function fuelTrackerEnsureSignatureTable(mysqli $conn): void
{
    $sql = "
        CREATE TABLE IF NOT EXISTS gas_issuance_signatures (
            id INT AUTO_INCREMENT PRIMARY KEY,
            gas_issuance_id INT NOT NULL UNIQUE,
            driver_signature LONGTEXT NOT NULL,
            signed_by VARCHAR(150) NULL,
            signed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_gas_issuance_signatures_issuance
                FOREIGN KEY (gas_issuance_id) REFERENCES gas_issuances(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    if (!$conn->query($sql)) {
        throw new RuntimeException('Unable to prepare signature table: ' . $conn->error);
    }
}

function fuelTrackerNormalizeSignature(string $signature): string
{
    $signature = trim($signature);
    if ($signature === '') {
        return '';
    }

    if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,[A-Za-z0-9+\/=]+$/i', $signature)) {
        return '';
    }

    return $signature;
}

function fuelTrackerSaveDriverSignature(mysqli $conn, int $gasIssuanceId, string $signature, string $signedBy): void
{
    $signature = fuelTrackerNormalizeSignature($signature);
    if ($gasIssuanceId <= 0 || $signature === '') {
        return;
    }

    fuelTrackerEnsureSignatureTable($conn);

    $stmt = $conn->prepare("
        INSERT INTO gas_issuance_signatures
            (gas_issuance_id, driver_signature, signed_by, signed_at)
        VALUES
            (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            driver_signature = VALUES(driver_signature),
            signed_by = VALUES(signed_by),
            signed_at = NOW()
    ");
    $stmt->bind_param('iss', $gasIssuanceId, $signature, $signedBy);
    $stmt->execute();
    $stmt->close();
}

function fuelTrackerFetchDriverSignature(mysqli $conn, string $gasIssuanceRef): string
{
    $gasIssuanceRef = trim($gasIssuanceRef);
    if ($gasIssuanceRef === '') {
        return '';
    }

    fuelTrackerEnsureSignatureTable($conn);

    $stmt = $conn->prepare("
        SELECT sig.driver_signature
        FROM gas_issuance_signatures sig
        INNER JOIN gas_issuances gi ON gi.id = sig.gas_issuance_id
        WHERE gi.serial_no = ?
           OR CAST(gi.id AS CHAR) = ?
        LIMIT 1
    ");
    $stmt->bind_param('ss', $gasIssuanceRef, $gasIssuanceRef);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return is_array($row) ? (string) ($row['driver_signature'] ?? '') : '';
}

function fuelTrackerSignatureBinary(string $signature): string
{
    $signature = fuelTrackerNormalizeSignature($signature);
    if ($signature === '') {
        return '';
    }

    $commaPosition = strpos($signature, ',');
    if ($commaPosition === false) {
        return '';
    }

    $binary = base64_decode(substr($signature, $commaPosition + 1), true);
    return is_string($binary) ? $binary : '';
}

function fuelTrackerSignatureImageType(string $signature): string
{
    $signature = fuelTrackerNormalizeSignature($signature);
    if ($signature === '') {
        return '';
    }

    if (preg_match('/^data:image\/(png|jpeg|jpg);base64,/i', $signature, $matches) !== 1) {
        return '';
    }

    $type = strtolower($matches[1]);
    return $type === 'png' ? 'PNG' : 'JPEG';
}
