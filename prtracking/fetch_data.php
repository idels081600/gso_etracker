<?php
require 'db.php';

$stmt = $pdo->query("
  SELECT Date, Supplier, Department, Amount, Status
  FROM Supplier
");

$rows = $stmt->fetchAll();

$context = "";
foreach ($rows as $r) {
    $context .=
        "Date: {$r['Date']}\n" .
        "Supplier: {$r['Supplier']}\n" .
        "Department: {$r['Department']}\n" .
        "Amount: {$r['Amount']}\n" .
        "Status: {$r['Status']}\n\n";
}
