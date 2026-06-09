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
    ['Geronima R. Ramones',            '1944-09-30', '2008-03-23'],
    ['Anatorio A. Onda',               '1950-06-18', '2008-03-23'],
    ['Flerida M. Onda',                '1956-03-22', '2021-05-28'],
    ['Ana Maricel R. Mañibo',          '1988-08-07', '2008-04-09'],
    ['Ramon S. Esteleys',              '1957-01-11', '2008-05-03'],
    ['Rosario O. Abe',                 '1923-04-16', '2008-05-20'],
    ['Meguela E. Salazar',             null,         '2008-06-02'],
    ['Eufronio E. Salazar',            '1957-08-03', '2018-12-30'],
    ['Ponciano A. Villanueva',         '1924-06-01', '2008-08-03'],
    ['Remedio De Claro',               '1918-08-26', '2008-08-20'],
    ['Alejandro L. Gascon',            '1921-05-18', '2008-09-15'],
    ['Crisanto Mendoza',               '1954-10-05', '2019-08-18'],
    ['Francisco T. Galindo Jr.',       '1977-12-23', '2024-10-19'],
    ['Juanita O. Oliverio',            '1931-12-29', '2013-01-26'],
    ['Michael T. Dela Cruz',           '1982-03-19', '2009-11-10'],
    ['Josue M. Ilao',                  '1940-04-05', '2009-11-23'],
    ['Marina A. Ilao',                 '1940-07-18', '2020-11-03'],
    ['Merlyn A. Ilao',                 '1979-05-09', '1984-04-09'],
    ['Eufemia & Belen',                null,         null],
    ['Paulino C. Visto',               '1957-04-29', '2008-11-30'],
    ['Remedio C. Visto',               '1950-11-09', '2021-05-15'],
    ['Romy B. Ramirez Jr.',            '2005-03-27', '2008-12-16'],
    ['Miranda U. Quirol',              '1967-10-12', '2008-12-22'],
    ['Modesto De Villa',               '1999-11-04', '2009-03-17'],
    ['Nandrito J. Hernandez',          '1951-03-04', '2009-04-04'],
    ['Lheo A. Mañibo',                 null,         '2009-04-03'],
    ['Juliana C. Muyot',               '1961-02-16', '2009-04-04'],
    ['Josefina R. Nagutom',            '1943-02-04', '2013-01-03'],
    ['Cerelo M. Mendoza',              '1941-07-08', '2012-12-20'],
    ['Oliver Morillo',                 '1972-05-08', '2025-11-18'],
    ['Arturo R. Juan',                 '1955-02-17', '2025-02-20'],
    ['Mercrizer M. Gubi',              '1989-08-29', '2009-10-14'],
    ['Aquilino D. Untalan',            '1940-02-22', '2009-10-11'],
    ['Beatres S. Remigio',             '1967-09-09', '2009-09-18'],
    ['Alexander N. Corales',           '1969-02-22', '2025-06-13'],
    ['Enriquita M. Dapat',             '1929-07-15', '2009-07-31'],
    ['Ignacio C. Soriano Sr.',         '1947-02-17', '2009-07-08'],
    ['Jerven T. Juan',                 '1996-02-18', '2008-10-28'],
    ['Arnelyn M. Amado',               '1950-04-27', '2009-06-24'],
    ['Mariane B. Amado',               '2013-09-20', '2021-02-12'],
    ['Amando P. Caron',                '1952-09-13', '2012-08-04'],
    ['Leoncia A. Caron',               '1957-09-12', '2009-06-22'],
    ['Apolonio Baes',                  '1924-04-04', '2009-12-09'],
    ['Renato O. Tejada',               '1957-06-02', '2020-01-25'],
    ['Alex G. Amboy',                  '1978-01-12', '2010-01-04'],
    ['Reynaldo M. Hernandez',          '1946-11-30', '2010-01-20'],
    ['Maria M. Buenas',                '1969-08-21', '2010-01-23'],
    ['Marcelino M. Salazar',           '1937-04-25', '2010-02-11'],
    ['Catalina A. Gonda',              '1935-03-18', '2010-03-25'],
    ['Mary Angel Godoy',               '1999-09-01', '2010-05-13'],
    ['Wilfredo Manalo',                null,         '1930-05-01'],
    ['Antonio S. Teores Sr.',          '1932-05-05', '2010-06-30'],
    ['Allumindo D. Mia',               '1944-10-24', '2010-08-21'],
    ['Juanita M. Mia',                 '1948-03-10', '2025-01-17'],
    ['Epifania D. Deliro',             '1950-04-07', '2012-12-09'],
    ['Joan S. Mendoza',                '1988-08-10', '2012-11-28'],
    ['Jonnel M. Villena',              '1999-12-28', '2010-10-26'],
    ['Virgilio R. Villena',            '1971-12-04', '2019-05-23'],
    ['Marcela R. Rodriguez',           '1918-04-21', '2010-10-28'],
    ['Mylene L. De Castro',            '1991-05-20', '2010-11-23'],
    ['Lauriano T. De Castro',          '1963-07-04', '2022-03-06'],
    ['Vicente M. Labaguis',            '1937-09-10', '1978-10-03'],
    ['Gregoria R. Labaguis',           '1931-12-24', '2010-11-29'],
    ['Salome O. Turalba',              '1942-10-25', '2010-11-28'],
    ['Loreta V. Elano',                '1936-02-24', '2011-01-06'],
    ['Troadio G. Abante',              '1956-12-28', '2011-01-09'],
    ['Hipolito M. Gloria',             '1952-08-13', '2011-01-19'],
    ['Danilo Mañibo',                  '1952-08-28', '2011-01-22'],
    ['Lino A. Tojino',                 '1959-09-23', '2011-02-04'],
    ['Editha R. Bueno',                '1953-02-24', '2012-10-26'],
    ['Jovito D. Sarmiento',            '1945-07-10', '2023-08-17'],
    ['Arsenio R. Quirol',              '1936-03-07', '2011-03-18'],
    ['Gregorio De Dios',               '1960-05-25', '2011-03-16'],
    ['Andrew De Dios',                 '2006-04-05', '2020-07-06'],
    ['Wilme N. Mendoza',               '1953-11-06', '2011-04-08'],
    ['Agripino G. Dimaano',            '1936-11-09', '2011-04-08'],
    ['Claudine Carla M. Dimaano',      '2001-03-09', '2021-08-28'],
    ['Mercy T. Herrera',               '1956-01-03', '2011-04-29'],
    ['Karl Mario Gomprado',            '2002-11-19', '2011-05-01'],
    ['Florencio M. Bukid',             '1947-11-07', '2011-07-11'],
    ['Rommel M. Morata',               null,         null],
    ['Silvia T. Aldovino',             '1924-10-25', '2011-07-25'],
    ['Rosario A. Magboo',              '1966-09-17', '1991-02-13'],
    ['Juanito G. Magboo',              '1960-12-24', null],
    ['Melchor Gayoso',                 '1936-01-06', '2011-09-15'],
    ['Michael Q. Cadacio',             '1990-10-31', '2011-09-27'],
    ['Florentino Fardo',               '1936-10-16', '2011-10-18'],
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
