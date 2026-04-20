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
    ['Sabina Moreno Abrigonda',      null,         '2021-03-17'], // birth day/year missing
    ['Luke Andrean Venice S. Manalo','2013-01-09', '2013-01-09'],
    ['Agustin Abanilla Abrigonda',   '1931-08-28', '2022-05-14'],
    ['Nakay Festin',                 null,         null],
    ['Rolando P. Andillo',           '1959-01-24', '2024-11-24'],
    ['Gulliermo Quinto',             '1956-04-07', '2003-01-20'],
    ['Selvina Quinto',               '1960-02-16', '2022-08-03'],
    ['Avelino Alvarez Aniel',        '1953-11-10', '2023-07-29'],
    ['Ariel Ordoña Aniel',           '1976-04-07', '2012-11-01'],
    ['Rosalinda B. Ordoña-Aniel',    '1952-06-13', '2021-11-13'],
    ['Renato De Villa Moreno',       '1969-12-10', '2025-10-05'],
    ['Paulo M. Moreno',              '1947-01-11', '2025-04-08'],
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
