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
    ['Herminiano C. Mogol Sr.',  '1963-11-15', '2005-06-12'],
    ['Alfonso Aldevino',         '1939-08-13', '2002-12-03'],
    ['Noraida M. Ramos',         '1988-10-20', '2012-12-25'],
    ['Gerlimo Gonda',            '1957-04-02', '2024-05-07'],
    ['Jaoquim Gaslas',           '1927-10-12', '2002-11-12'],
    ['Unknown',                  null,         null],
    ['Lito Guiling',             '2004-08-28', '2012-10-31'],
    ['Maria M. Rayos',           '1977-06-14', null],        // death year missing
    ['Unknown',                  null,         null],
    ['Mario C. Alulod',          '1970-12-18', '2003-08-01'],
    ['Jose F. Enriquez',         '1960-02-09', '2022-12-15'],
    ['Sibiniano A. Cabase',      '1945-01-01', '2002-07-12'],
    ['Eva Oliba',                '1922-06-08', '2008-04-23'],
    ['Unknown',                  null,         null],
    ['Dhenmar Gayacan',          '1997-07-31', '2021-12-03'],
    ['Joseph Austria',           '1975-07-04', '2003-04-29'],
    ['Maxima G. Hernandez',      '1927-11-27', '2002-05-12'],
    ['Apolinar D. Bueno',        '1954-07-26', '2019-08-29'],
    ['Jacinta D. Bueno',         '1913-08-30', '2002-04-19'],
    ['Feliza Cayube Rudica',     '1936-05-28', '2001-04-15'],
    ['Alexander A. Pascua',      '1951-06-19', '2002-04-06'],
    ['Jose N. Eustaquio',        '1942-12-27', '2002-04-05'],
    ['Lucia L. Eustaquia',       '1952-12-13', '2019-05-10'],
    ['Fortunato Ascan',          '1967-06-11', '2002-03-28'],
    ['Victoria G. Cantos',       '1927-12-23', '2002-03-03'],
    ['Francisco Aday',           '1939-03-09', '2022-03-14'],
    ['Apolonia B. Aaniz',        '1940-03-08', '2022-05-13'],
    ['Nimpha C. Espuelas',       '1950-08-25', '2002-03-05'],
    ['Francisco M. Villanueva',  '1989-03-09', '2002-01-28'],
    ['Unknown',                  null,         null],
    ['Unknown',                  null,         null],
    ['Julian De Villa',          null,         null],
    ['Oliva D. Alulod',          '1930-03-23', '2001-11-20'],
    ['Marites M. Hatulan',       '1955-02-02', '2001-03-18'],
    ['Efren Sandol',             '1950-02-02', '2023-11-17'],
    ['Rolando Calese',           '1962-02-08', '2001-10-10'],
    ['Emilio A. Garcia',         '1934-08-03', '2001-10-07'],
    ['Asuncion Gaba',            '1926-05-15', null],        // death year missing
    ['Bonifacio G. Adao',        '1953-05-04', '2020-03-15'],
    ['Senando S. Casañas',       '1944-08-30', '2001-09-20'],
    ['Florencio Celes',          '1959-02-05', '2001-08-26'],
    ['Yvone',                    null,         null],
    ['Savino Hernandez',         '1931-02-28', '2001-08-22'], // Feb 29 1931 invalid, used Feb 28
    ['Trinidad L. Medrano',      '1972-02-05', '2001-08-03'],
    ['Angelita L. Medrano',      '2000-08-03', '2000-08-03'],
    ['Ernesto P. Aday',          '1956-08-28', '2001-07-30'],
    ['Esperanza Milliares',      null,         null],
    ['Simplicio M. Gillado',     '1954-03-04', '2022-04-10'],
    ['Juana M. Gillado',         '1910-03-28', '2000-11-04'],
    ['Analyn Riña',              '1991-08-17', '2000-10-26'],
    ['Bernandino C. Eleponga',   '1934-03-01', '2101-10-24'], // year 2101 as written
    ['Antonio Hernandez',        '1922-10-05', '2000-09-21'],
    ['Rustico B. Anadia',        '1938-03-23', '2013-09-27'],
    ['Unknown',                  null,         null],
    ['Cesar Ramirez',            '1953-05-10', '2012-09-01'],
    ['Violeta Ramirez',          '1909-10-30', null],
    ['Jovita Paglinawan',        '1951-01-24', '2022-06-24'],
    ['Alvaro Magnaye',           null,         null],
    ['Jerry Tresvalle',          null,         '2000-08-03'],
    ['Eugenia P. De Villa',      null,         null],
    ['Melecio De Claro',         '1950-12-04', '2000-06-16'],
    ['Ester Caringal',           '1961-09-21', '2025-05-20'],
    ['Juan C. Gonda',            null,         null],
    ['Demetria G. Gonda',        '1937-08-14', '2018-01-12'],
    ['Nicolasa E. Gayacan',      null,         null],
    ['Julia G. Marquinez',       '1933-03-01', '2021-05-13'],
    ['Igleceria L. Ola',         '1946-05-22', '2017-08-18'],
    ['Mario L. Ola',             '1954-05-05', '1998-07-23'],
    ['Unknown',                  null,         null],
    ['Bido B. Abay',             '1975-03-27', '1998-06-22'],
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
