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
    // bukana row 1 front
    ['Fabiana V. Lopez',                '1962-01-20', '2024-08-29'],
    ['Jose M. Lopez',                   '1958-05-20', '2014-05-20'],
    ['Ericson C. Mendoza',              '1987-03-29', '2024-09-08'],
    ['Meleton C. Mendoza Jr.',          '1971-11-26', '2021-09-27'],
    ['Randy A. Marasigan',              '1970-07-25', '2024-10-20'],
    ['Virginia L. Fedelin',             '1936-02-10', '2024-10-30'],
    ['Florencia S. Bautista',           '1961-12-28', '2024-11-03'],
    ['Genielyn P. Godoy',               '1982-09-15', '2024-11-06'],
    ['Alejandro M. Dalisay',            '1970-05-18', '2024-12-06'],
    ['Ma. Yliaza P. Dalisay',           '2000-03-25', '2007-01-26'],
    ['Pelagia "Fe" A. Perez',           '1966-03-23', '2024-12-20'],
    ['Lina Andrade Axalan',             '1973-10-20', '2025-01-03'],
    ['Tomas G. Axalan',                 '1983-12-21', '1986-02-17'],
    ['Victoria A. Axalan',              '1935-02-10', '2012-02-10'],
    ['Onofre A. Axalan',                '1956-06-12', '2009-09-03'],
    ['Javier A. Axalan',                '1974-01-20', '2001-02-05'],

    // bukana row 1 likod
    ['Herminia B. Talicuran',           '1957-05-09', '2024-08-20'],
    ['Crispin B. Hardin',               '1975-05-05', '2023-10-20'],
    ['Teodora D. Baculo',               '1957-04-01', '2023-11-15'],
    ['Iluminado "Ado" Colis Manalo',    '1966-06-21', '2023-11-16'],
    ['Leodegario D. Anglo',             '1959-02-11', '2023-12-05'],
    ['Benigno M. Garcia',               '1958-11-20', '2023-12-13'],
    ['Russel D. Garcia',                '1983-01-25', '1983-01-25'],
    ['Wilfredo S. Collado',             '1962-05-09', '2023-12-23'],
    ['Lloyd Carpio Gavilan',            '1997-03-05', '2023-12-31'],
    ['Jose J. Solis',                   '1960-11-11', '2024-01-15'],
    ['Marcela L. Solis',                '1958-01-16', '2014-03-29'],
    ['Alvin D. Consaludo',              '1988-08-22', '2024-02-11'],

    // bukana row 2 front
    ['Pedro B. Delos Reyes',            '1956-05-15', '2023-09-26'],
    ['Flor E. Lafuente',                '1959-11-30', '2023-09-23'],
    ['Marcelino B. Marcellana',         '1947-06-01', '2023-09-23'],
    ['Marcelino T. Marcellana Sr.',     '1917-06-11', '1982-05-20'],
    ['Priscila B. Marcellana',          '1925-01-25', '2011-05-24'],
    ['Angelita A. Marcellana',          '1946-07-04', '1966-01-20'],
    ['Pedro B. Marcellana',             '1945-02-22', '2010-04-26'],
    ['Gaudencio Luci Luto',             '1942-08-16', '2023-09-15'],
    ['Leoniza G. Balbacal',             '1921-12-03', '2006-12-18'],
    ['Francisco M. Mañibo',             '1955-03-08', '2023-09-07'],
    ['Annabelle Acha Sevilla',          '1968-01-28', '2023-09-03'],
    ['Eugenio A. Matira Jr.',           '1963-06-24', '2023-09-02'],
    ['Eleodora M. Cadacio',             '1953-09-03', '2023-08-01'],
    ['Miguela D. Aldea',                '1950-05-22', '2023-07-31'],
    ['Felino G. Magmanlac',             '1939-06-01', '2023-08-14'],
    ['Khen Joshua Caringal',            '2008-01-08', '2024-08-13'],

    // bukana row 2 likod
    ['Rowena C. Chua',                  '1970-11-06', '2024-07-27'],
    ['Corazon A. Nokom',                '1947-05-24', '2023-06-25'],
    ['Liezel M. Formanes',              '1991-05-01', '2023-07-12'],
    ['Marenie F. Catapang',             '1967-05-01', '2023-07-13'],
    ['Elenor M. Balbacal',              '1947-11-04', '2023-07-20'],
    ['Teofilo S. Balbacal',             '1921-07-21', '1981-01-05'],
    ['Jovito Aceveda',                  '1948-02-14', '1978-07-29'],
    ['Allan M. Mampusti',               '1983-07-23', '2023-03-29'],
    ['Sevilla C. Gonzales',             '1987-04-04', '2023-03-16'],
    ['Isabel P. Garcia',                '1939-07-18', '2023-03-13'],
    ['Aniceto Loro Amargo',             '1949-03-21', '2024-07-18'],
    ['Shella May P. Reyes',             '1994-05-27', '2023-08-20'],
    ['Juvey P. Reyes',                  '1993-02-11', '2009-11-26'],

    // bukana row 1 likod pakanluran (4th column)
    ['Aida Camacho Marasigan',          '1954-05-02', '2023-06-22'],
    ['Leoning M. Dela Peña',            '1963-03-25', '2023-06-12'],
    ['Nherry D. Perez',                 '1992-08-18', '2023-04-12'],
    ['Rommel D. Perez',                 '1997-02-20', '2008-11-26'],
    ['Rufina Jandusav Lugmao',          '1913-10-10', '1988-11-06'],
    ['Jessabel R. Benitez',             '2003-07-04', '2023-04-07'],
    ['Ernesto M. Escalona',             '1948-06-15', '2023-04-19'],
    ['Adora M. Mampusti',               '1964-02-17', '1998-12-08'],
    ['Armen M. Mampusti',               '1987-05-11', '1992-02-22'],
    ['Arleen M. Mampusti',              '1985-06-07', '2024-03-12'],

    // bukana row 3 front
    ['Jocelyn M. Sapungan',             '1962-11-04', '2020-05-23'],
    ['Anecio Atienza Jara',             '1959-12-31', '2022-11-01'],
    ['Mark Anthony F. Jara',            '1986-10-21', '2002-02-16'],
    ['Rodel M. Gasic',                  '1957-01-25', '1999-03-27'],
    ['Perfecto A. Dalisay',             '1932-05-20', '2012-05-28'],
    ['Shirley D. Morillo',              '1965-05-09', '2025-06-07'],
    ['Bernabe P. Soriano',              '1990-06-04', '2021-12-16'],
    ['Josefina G. Dalisay',             '1932-11-17', '2022-09-28'],
    ['Gregorio D. Dimayuga',            '1932-05-25', '2022-09-28'],
    ['Primitiva M. De Chavez',          '1918-06-20', '1992-11-07'],
    ['Francisco B. Aldea',              '1920-02-08', '2002-12-15'],
    ['Antonio Roldan Camacho',          '1947-05-03', '2022-11-14'],
    ['Jerrymie D. Narsoles',            '1978-03-16', '1984-12-05'],
    ['Juliana A. Aldea',                '1932-06-04', '2000-01-25'],
    ['Pacifico J. Maranan',             '1948-05-01', '2022-11-17'],
    ['Anghelita D. Rodillas',           '1998-04-27', null],
    ['Luciano B. Santiago',             '1933-12-13', '2022-12-13'],
    ['Bobby C. Illaga',                 '1980-08-26', '2022-09-02'],
    ['John Paulo C. Illaga',            '1995-08-11', '1995-08-28'],
    ['Jeffrey R. Anglo',                '1995-11-19', '2022-12-18'],
    ['Ruperto A. Aldea',                '1964-03-27', '2023-02-10'],

    // bukana row 3 likod
    ['Erlinda T. Gasic',                '1959-08-15', '2021-12-18'],
    ['Mario D. Atienza',                '1953-05-03', '2022-10-04'],
    ['Rolando M. Tordecilla',           null,         '2021-12-18'],
    ['Erlinda B. Tordecilla',           '1956-02-02', null],

    // tapat ng puno (Sancho Barcibal family)
    ['Sancho M. Barcibal',              '1954-06-05', '2024-04-10'],
    ['Estelita B. Montalbo',            '1946-08-10', '2021-10-03'],
    ['Reynaldo B. Perez',               '1957-01-25', null],
    ['Rosalinda G. Cayube',             '1951-11-19', '2021-09-08'],
    ['Luciana P. Añonuevo',             '1953-01-07', '2021-08-31'],
    ['Timoteo C. Magnaye',              '1952-01-24', '2021-11-01'],
    ['Melchor M. Magnaye',              '1979-12-25', '2021-07-07'],
    ['Tagumpay C. Tolentino',           '1975-01-05', '2021-12-16'],
    ['Erlinda B. Tordecilla',           '1956-02-02', '2021-08-28'],
    ['Leonardo M. Castillo',            '1957-12-24', '2025-05-24'],
    ['Felix Melendrez Dudas',           '1958-01-20', '2021-05-31'],
    ['Bonifacio F. Casiano',            '1942-05-13', '2021-11-12'],
    ['Nelson F. Tiemsen',               '1963-02-11', '2024-03-23'],
    ['Donardo M. Fajardo',              '1959-01-06', '2021-11-30'],
    ['Gregorio A. Delen',               '1927-01-27', '1985-11-03'],
    ['Feliciano R. Camacho',            '1949-02-21', '2022-03-21'],
    ['Orlando H. Leuterio',             '1947-04-07', '2021-11-30'],

    // column 1
    ['Sofronio R. Vivas',               '1948-03-11', '2026-04-08'],
    ['Arturo S. Consaludo',             '1951-10-03', '2021-10-28'],
    ['Ismael M. Barola',                '1997-06-17', '2025-10-06'],
    ['Reynold V. Bensurto',             '1974-07-19', '2025-10-04'],
    ['Alberto R. De Chavez',            '1942-11-07', '2025-10-12'],

    // column 2
    ['Maria Estela M. Rosales',         '1967-01-10', '2026-01-04'],
    ['Sergio Sing Manalo',              '1957-10-07', '2025-11-12'],
    ['Conrado A. De Claro',             '1959-05-04', '2025-11-15'],
    ['Bernardo T. De Leon',             '1956-04-16', '2025-11-19'],
    ['Vicente Ramones Layson',          '1942-01-18', '2025-12-11'],
    ['Norma M. Pulhin',                 '1942-01-01', '2025-10-13'],
    ['Alfredo P. Pulhin',               null,         '1993-08-19'],
    ['Liezel R. Dela Cruz',             '1992-05-13', null],
    ['Pacita R. Dela Cruz',             '1967-03-17', '2025-09-02'],
    ['Reyniel A. Estose',               '2004-01-10', '2025-07-25'],
    ['Loriemar D. Magsino',             '1990-12-27', '2025-05-10'],
    ['Sherwin M. Del Ocampo',           '1981-10-11', '2025-07-08'],
    ['Reynaldo L. Estose',              '1966-02-17', '2021-08-26'],

    // column 3
    ['Virginia M. De Torres',           '1950-11-27', '2026-01-06'],
    ['Christin Aira A. Macariola',      '2004-12-10', '2025-10-25'],
    ['Josephine J. Rayos',              '1966-12-16', '2025-12-25'],

    // column 4
    ['Porfirio M. Sarcia',              '1949-02-02', '2026-01-22'],
    ['Arlene R. Panganiban',            '1986-09-26', '1998-02-09'],
    ['Eric R. Panganiban',              '1976-07-07', '2025-04-08'],
    ['Maria A. Delos Reyes',            '1957-01-02', '2025-03-28'],
    ['Estilita C. Barcelona',           '1940-09-03', '2025-03-15'],
    ['Eriberta Maribel M. Aguba',       '1977-03-17', '2025-03-05'],
    ['Juan D. De Guzman Jr.',           '1958-01-02', '2025-01-31'],
    ['Unknown',                         null,         null],
    ['Unknown',                         null,         null],

    // column 5
    ['Paulino A. Castillo',             '1949-06-12', '2026-01-28'],
    ['Antonio S. Capio',                '1966-02-17', '2025-04-28'],
    ['Agnes Q. Villena',                '1969-06-15', '2025-04-15'],
    ['Semion Barcelona',                null,         '2025-03-28'],
    ['Domingo C. Morales',              '1956-07-06', '2025-05-07'],
    ['Liza Maranan Moreno',             '1959-07-02', '2025-04-28'],
    ['Delfin C. Ramos',                 '1957-12-24', '2025-05-24'],
    ['Herminihildo C. Caudor',          '1961-04-13', '2025-06-09'],
    ['Natalia M. Enriquez',             '1962-12-01', '2025-06-26'],

    // column 6
    ['Menandro C. Ferranco',            '1960-03-09', '2026-03-28'],
    ['Remo M. Magboo',                  '1964-12-07', null],
    ['Florencio C. Mañibo',             '1957-02-04', '2026-04-07'],
    ['Felipe B. Visco',                 '1982-05-26', null],

    // named entries from bukana row 5 / other columns
    ['Mindalita Telesforo Fortunato',   '1952-07-28', '2022-10-21'],
    ['Reynel F. Labrador',              '1991-04-17', '2022-08-24'],
    ['Prudencio H. Roallos Sr.',        '1960-04-28', '2022-12-14'],
    ['Jeryme E. Buquid',                '1979-12-05', '2022-03-01'],
    ['Angelita B. Bagos',               '1939-05-17', '2022-08-15'],
    ['Teodulo C. Amparo',               '1980-03-23', '2023-01-13'],
    ['Elpidia G. Jacob',                '1962-11-16', '2022-03-21'],
    ['Nenita M. Mendoza',               '1957-06-04', '2022-08-03'],
    ['Alberto Manongsong Bacay',        '1957-04-16', '2023-02-06'],
    ['Judy Ann G. Jacob',               '1998-11-10', '2025-03-25'],
    ['Pacifico D. Tolentino',           '1945-09-24', '2025-12-06'],
    ['Josiah C. Maranan',               '2011-05-03', '2023-03-08'],
    ['Ansel Jomare M. Navarro',         '1998-10-22', '2022-04-03'],
    ['Moreno V. Tolentino',             '1970-07-18', '2022-07-16'],
    ['Avelino D. Magsisi',              '1948-01-26', '2006-07-28'],
    ['Ariel Marcellana Gubi',           '1973-04-01', '2023-03-20'],
    ['Zosimo R. Mortilla',              '1934-12-26', '2022-05-19'],
    ['Ambrocio Martinez Gubi',          '1933-02-13', '2004-12-14'],
    ['Lydia Marcellana Gubi',           '1943-06-23', '2024-01-09'],
    ['Apolinario G. Villena',           '1950-01-05', '2024-04-30'],
    ['Engilbarto R. Abanilla',          '1953-11-06', '2025-02-12'],
    ['Virgilio De Villa Fortunato',     '1938-01-21', '2022-01-02'],
    ['Ester Sapico',                    '1941-09-25', null],
    ['Cresencio Gardoce Guerra',        '1956-06-07', '2025-08-11'],
    ['Roberto A. Gonda',                null,         '2000-06-25'],
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
