<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
if (!$conn) die("No connection");

$stmt = $conn->prepare(
    "INSERT INTO deceased_records (full_name, date_of_birth, date_of_death, age, is_archived) VALUES (?,?,?,?,0)"
);

function calcAge($dob, $dod) {
    if (!$dob || !$dod) return null;
    return (int)(new DateTime($dob))->diff(new DateTime($dod))->y;
}

$records = [
    ['Santiago Flores Bueno',   '1927-05-01', '2008-02-18'],
    ['Maria Gina Bueno',        '1925-12-15', '2020-11-02'],
    ['Elenor Garpa Bueno',      '1949-04-25', '2022-02-22'],
    ['Romeo O. Garis',          '1951-03-06', '2024-02-08'],
    ['Guillermo M. Berguera',   '1965-06-25', '2025-11-02'],
    ['Unknown',                 null,         null],
    ['Mario J. Gad',            '1942-09-12', '2024-01-23'],
    ['Merced G. Magnaye',       '1955-09-24', '2021-10-08'],
    ['Epifania Talicuran',      '1926-02-16', '2021-07-06'],
];

$inserted = 0;
$skipped  = 0;

foreach ($records as [$name, $dob, $dod]) {
    $age = calcAge($dob, $dod);
    try {
        $stmt->execute([$name, $dob, $dod, $age]);
        $inserted++;
    } catch (Exception $e) {
        echo "<p style='color:orange;font-family:sans-serif;'>Skipped <b>$name</b>: " . $e->getMessage() . "</p>";
        $skipped++;
    }
}

echo "<p style='color:green;font-family:sans-serif;padding:20px;font-size:16px;'>✓ Done — <b>$inserted</b> records inserted, <b>$skipped</b> skipped.</p>";
echo "<a href='public/burial-records.php' style='font-family:sans-serif;'>Go to Burial Records</a>";
