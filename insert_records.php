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
    ['Quilao',                          null,         null],
    ['Mauricia R. Camacho',             '1910-09-10', '2007-07-29'],

    ['Doroteo S. Camacho',              '1909-12-02', '2005-11-21'],
    ['Renato R. Camacho',               '1952-02-05', '2021-10-06'],
    ['Rogelio D. De Guzman',            '1961-12-01', '2022-05-11'],

    ['Concordia M. De Guzman',          '1950-05-12', '2005-11-29'],
    ['Randy M. De Guzman',              '1989-03-18', '2023-04-06'],
    ['Benedicto T. Magnaye',            '1934-02-19', '2009-04-07'],

    ['Aquilina M. Magnaye',             '1985-06-20', '2005-12-01'],
    ['Lucia M. Magnaye',                '1961-03-04', '1995-09-26'],
    ['Harrold D. Garcia',               '1979-06-16', '2005-12-16'],
    ['Barbara R. Bueno',                '1932-12-04', '2007-01-27'],
    ['Leonardo D. Bueno',               '1929-10-24', '1999-09-03'],
    ['Remegio P. Datinguinoo',          '1942-10-01', '2005-12-29'],
    ['Helaria P. Datinguinoo',          '1944-01-14', '2025-03-25'],
    ['Cristina Flores Agleron',         '1928-12-15', '2006-01-13'],

    ['Roy F. Agleron Sr.',              '1953-09-23', '2006-05-17'],
    ['Roy B. Agleron Jr.',              '1981-10-16', '2019-10-23'],

    ['Servando C. Tomas',               '1936-10-23', '2006-01-17'],
    ['Pedro A. Tomas',                  '1911-05-06', '1999-11-13'],
    ['Enriqueta C. Tomas',              '1935-06-15', '2014-01-31'],

    ['Cerilo M. Revadavia',             '1922-08-27', '2006-02-16'],
    ['Resurreccion P. Revadavia',       '1932-03-27', '1921-10-07'], // dates as written; likely source error
    ['Pamfilo G. De Chavez',            '1919-09-06', '2006-04-16'],
    ['Dionisia R. De Chavez',           '1926-12-06', '2012-03-29'],
    ['Gregorio Amboy Castillo',         '1939-03-12', '2006-04-24'],
    ['Norma Albo Castillo',             '1942-04-16', '2024-11-20'],
    ['Cesar J. Perucho',                '1932-02-24', '2003-09-15'],
    ['Ursula H. Matibag',               '1940-01-20', null],        // "200." — year unreadable
    ['Teofilo B. Matibag',              '1939-01-08', '2009-04-27'],
    ['Leodegario O. Garis Sr.',         '1952-10-02', '2007-01-06'],
    ['Petronilo D. Aguba',              '1978-05-31', '2021-03-24'],

    ['Marieta D. Aguba',                '1945-01-14', '2006-11-25'],
    ['Timoteo A. Aguba',                '1940-08-22', '2008-03-02'],

    ['Rosita D. Casapao',               '1929-08-16', '1983-09-29'],
    ['Albina C. Barte',                 '1966-02-05', '2013-05-12'],
    ['Severo D. Casapao',               '1934-11-08', '2007-03-30'],
    ['Victor D. Casapao',               '1922-09-10', '2006-10-17'],
    ['Felicisima M. Casapao',           '1927-08-12', '2016-12-03'],
    ['Lynel C. Magnaye',                '1988-02-04', '2007-02-19'],
    ['Nemisio G. Magnaye',              '1957-10-31', '1995-08-31'],

    ['Simeon M. Casapao',               '1942-10-28', '2021-07-31'],
    ['Maria H. Casapao',                '1939-02-16', '2008-09-13'],
    ['Marcel M. Atienza',               '1973-01-15', '2024-06-12'],
    ['Crispulo A. De Castro',           '1966-06-10', '2009-05-04'],
    ['Rosalina B. Sobremontes',         '1945-07-21', '2009-03-30'],

    ['Crisipin C. Sobremontes',         '1968-12-05', '2025-12-25'],
    ['Juan R. Sobremontes',             '1942-07-11', '2003-09-28'],
    ['John Patrick C. Sobremontes',     '1987-10-22', '1989-02-27'],
    ['Maximo S. Magnaye',               '1972-04-14', '2009-03-20'],
    ['Cristina M. Magnaye',             '1965-02-14', '2018-05-28'],
    ['Gina M. Delen',                   '1969-01-06', '2017-09-17'],

    ['Paulino Marquez',                 '1899-10-03', '1970-04-22'],
    ['Ana Marquez',                     '1901-07-20', '2004-11-27'],
    ['Regina Marquez',                  '1929-09-14', '1980-11-14'],
    ['Francisco Marquez',               '1931-06-22', '2012-02-14'],
    ['Boy A. Marquez',                  '1955-04-02', '2025-12-02'],

    ['Lanie M. Delen',                  '1956-04-20', '2024-03-22'],
    ['Lance Raven A. Delen',            '2011-09-29', '2012-04-08'],
    ['Katrina G. Marquez',              '1994-10-03', '1995-11-14'],
    ['Loreto M. Delen Jr.',             '1973-04-21', '2021-07-17'],

    ['Loreto Delen Sr.',                '1923-01-01', '1996-03-23'],
    ['Romeo M. Delen',                  '1954-01-07', '2009-10-18'],
    ['Joseph Pepe Delen',               '1962-01-09', '2018-12-06'],

    ['Dante Delen',                     '1960-07-07', '2012-05-18'],
    ['Efren Delen',                     '1967-10-10', '2025-10-04'],
    ['Rosalinda A. Delen',              '1978-07-06', '2015-07-03'],
    ['Antonio P. Dudas',                '1988-07-18', '2009-06-12'],
    ['Venancio C. Dudas',               '1948-05-18', '2011-06-18'],

    ['Monica M. Magboo',                '1946-05-04', '2022-02-07'],
    ['Josefa Hernandez',                null,         '2010-06-26'],
    ['Jacinto Matibag',                 null,         '1969-08-20'],
    ['Martina M. Castillo',             '1950-01-30', '2025-11-28'],
    ['Gregoria R. Escalona',            '1973-05-08', '2012-08-18'],
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
