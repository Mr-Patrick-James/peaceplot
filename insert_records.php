<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
if (!$conn) die("No connection");

$records = [
    ['Florencio C. Mañibo',      '1957-02-04', '2021-09-07', 64],
    ['Reynaldo B. Perez',        '1954-10-05', '2021-09-08', 66],
    ['Luciana P. Añonuevo',      '1953-01-07', '2021-08-31', 68],
    ['Arturo S. Consaludo',      '1951-10-03', '2021-08-28', 69],
    ['Tagumpay C. Tolentino',    '1975-01-05', '2021-07-08', 46],
    ['Leonardo M. Castillo',     '1954-11-06', '2021-07-07', 66],
    ['Felix Melendrez Dudas',    '1958-01-20', '2021-05-31', 63],
    ['Nelson F. Tiemsen',        '1963-02-11', '2024-03-23', 61],
    ['Sancho M. Barcibal',       '1954-06-05', '2024-04-10', 69],
    ['Estelita B. Montalbo',     '1946-08-10', '2021-10-03', 75],
    ['Rosalinda G. Cayube',      '1951-11-19', '2021-10-17', 69],
    ['Rolando M. Tordecilla',    '1953-01-09', '2021-10-28', 68],
    ['Erlinda B. Tordecilla',    '1956-02-02', '2021-11-04', 65],
    ['Timoteo C. Magnaye',       '1952-01-24', '2021-11-01', 69],
    ['Melchor M. Magnaye',       '1979-12-25', '1997-12-23', 17],
    ['Bonifacio F. Casiano',     '1942-05-13', '2021-11-12', 79],
    ['Donardo M. Fajardo',       '1959-01-06', '2021-11-30', 62],
    ['Orlando H. Leuterio',      '1947-04-07', '2021-11-30', 74],
    ['Gregorio A. Delen',        '1927-01-27', '1985-11-03', 58],
    ['Feliciano R. Camacho',     '1949-02-21', '2022-03-21', 73],
];

$conn->beginTransaction();
$inserted = 0;
foreach ($records as $r) {
    $burial = date('Y-m-d', strtotime($r[2] . ' +8 days'));
    $conn->prepare("INSERT INTO deceased_records (full_name, date_of_birth, date_of_death, date_of_burial, age, is_archived) VALUES (?,?,?,?,?,0)")
         ->execute([$r[0], $r[1], $r[2], $burial, $r[3]]);
    echo "<p style='font-family:sans-serif;margin:2px 0;'>✓ {$r[0]} (age {$r[3]})</p>";
    $inserted++;
}
$conn->commit();

echo "<p style='color:green;font-family:sans-serif;padding:10px 0;font-size:15px;font-weight:600;'>Done — $inserted records inserted.</p>";
echo "<a href='public/burial-records.php' style='font-family:sans-serif;'>Go to Burial Records</a>";
