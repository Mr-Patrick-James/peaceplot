<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
if (!$conn) die("No connection");

function calcAge($dob, $dod) {
    if (!$dob || !$dod) return null;
    try { return (int)(new DateTime($dob))->diff(new DateTime($dod))->y; }
    catch (Exception $e) { return null; }
}
function getLotId($conn, $lotNumber) {
    $stmt = $conn->prepare("SELECT id FROM cemetery_lots WHERE lot_number = ?");
    $stmt->execute([$lotNumber]);
    return $stmt->fetchColumn() ?: null;
}
function ensureLayer($conn, $lotId, $layerNum) {
    $stmt = $conn->prepare("INSERT OR IGNORE INTO lot_layers (lot_id, layer_number, is_occupied) VALUES (?,?,0)");
    $stmt->execute([$lotId, $layerNum]);
}
function findRecord($conn, $name) {
    $stmt = $conn->prepare("SELECT id FROM deceased_records WHERE full_name = ? AND lot_id IS NULL ORDER BY id ASC LIMIT 1");
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    if ($id) return $id;
    $stmt = $conn->prepare("SELECT id FROM deceased_records WHERE full_name = ? ORDER BY id ASC LIMIT 1");
    $stmt->execute([$name]);
    return $stmt->fetchColumn() ?: null;
}
function insertRecord($conn, $name, $dob, $dod) {
    $age = calcAge($dob, $dod);
    $stmt = $conn->prepare("INSERT INTO deceased_records (full_name, date_of_birth, date_of_death, age, is_archived) VALUES (?,?,?,?,0)");
    $stmt->execute([$name, $dob, $dod, $age]);
    return $conn->lastInsertId();
}
function assignRecord($conn, $recordId, $lotId, $layer) {
    ensureLayer($conn, $lotId, $layer);
    $conn->prepare("UPDATE deceased_records SET lot_id=?, layer=? WHERE id=?")->execute([$lotId, $layer, $recordId]);
    $conn->prepare("UPDATE lot_layers SET is_occupied=1, burial_record_id=? WHERE lot_id=? AND layer_number=?")->execute([$recordId, $lotId, $layer]);
    $conn->prepare("UPDATE cemetery_lots SET status='Occupied', updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$lotId]);
}

// -----------------------------------------------------------------------
// Parsed strictly from source, top to bottom.
// "2" before a group = that group shares ONE lot (layers 1,2,3...)
// No number = single burial = one lot
// UNKNOWN alone = one lot with one UNKNOWN record
// -----------------------------------------------------------------------
$assignments = [
    // 1
    'l-1998' => [
        ['Marcelino E. Castillo',   '1940-11-04', '2012-08-15', 1],
    ],
    // 2
    'l-1997' => [
        ['Romeo Aldovino Belen',    '1934-03-18', '2012-08-08', 1],
    ],
    // 3
    'l-1996' => [
        ['Alfredo O. Demiar',       '1955-12-24', '2024-09-08', 1],
    ],
    // 4 - Vicente single
    'l-1995' => [
        ['Vicente Petisa',          '1935-08-08', '2003-10-11', 1],
    ],
    // 5 - Unknown single
    'l-1994' => [
        ['UNKNOWN',                 null,         null,         1],
    ],
    // 6 - "2" prefix: Bienvenido + Angelita
    'l-1993' => [
        ['Bienvenido Sari',         '1952-03-01', '2023-01-09', 1],
        ['Angelita Aniel',          '1950-10-24', '2023-09-20', 2],
    ],
    // 7 - Ruperta single
    'l-1992' => [
        ['Ruperta B. Pulhin',       '1923-05-15', '2003-09-07', 1],
    ],
    // 8 - "2" prefix: Juan + Ligaya
    'l-1991' => [
        ['Juan Caretas Sr.',        '1924-06-24', '1972-06-22', 1],
        ['Ligaya G. Caretas',       '1925-05-15', '2003-08-23', 2],
    ],
    // 9 - Unknown single
    'l-1990' => [
        ['UNKNOWN',                 null,         null,         1],
    ],
    // 10 - "2" prefix: Protacio + Erlinda
    'l-1989' => [
        ['Protacio Bagsik Sr.',     '1951-01-03', '2013-05-08', 1],
        ['Erlinda Bagsik',          '1941-12-03', '2003-07-19', 2],
    ],
    // 11 - Unknown single
    'l-1988' => [
        ['UNKNOWN',                 null,         null,         1],
    ],
    // 12 - Nelson single
    'l-1987' => [
        ['Nelson M. Merhan',        '1945-06-13', '2003-07-31', 1],
    ],
    // 13 - "2" prefix: Elpido + Gloria
    'l-1986' => [
        ['Elpido Bacsa',            '1954-02-08', '2003-05-16', 1],
        ['Gloria V. Bacsa',         '1954-04-17', '2013-09-09', 2],
    ],
    // 14 - Luisa single
    'l-1985' => [
        ['Luisa I. Panganiban',     '1945-05-24', '2003-05-20', 1],
    ],
    // 15 - Eugenio single
    'l-1984' => [
        ['Eugenio G. Roldan',       '1929-09-06', '2008-11-20', 1],
    ],
    // 16 - Arturo single
    'l-1983' => [
        ['Arturo Tordecilla',       '1950-08-17', '2021-05-16', 1],
    ],
    // 17 - "2" prefix: Adolfo + Aracelie
    'l-1982' => [
        ['Adolfo R. Juarez',        '1939-02-02', '2003-12-22', 1],
        ['Aracelie P. Juarez',      '1939-03-05', '2019-08-03', 2],
    ],
    // 18 - "2" prefix: Venancio + Antonia
    'l-1981' => [
        ['Venancio A. Manibo',      '1927-04-01', '2014-12-11', 1],
        ['Antonia C. Manibo',       '1937-05-05', '2003-12-23', 2],
    ],
    // 19 - "2" prefix: Aniceto + Luzvininda
    'l-1980' => [
        ['Aniceto P. Marquez',      '1924-06-24', '2004-01-11', 1],
        ['Luzvininda A. Marquez',   '2015-05-25', '2013-08-11', 2],
    ],
    // 20 - Marcos single
    'l-1979' => [
        ['Marcos A. Dalisay',       '1944-11-04', '2004-01-26', 1],
    ],
    // 21 - Bonifacio single
    'l-1978' => [
        ['Bonifacio Y. Limbo',      '1924-06-05', '2004-02-17', 1],
    ],
    // 22 - Gregorio single
    'l-1977' => [
        ['Gregorio M. Mendoza',     '1970-05-25', '2004-03-31', 1],
    ],
    // 23 - Merlinda single
    'l-1976' => [
        ['Merlinda G. Panganiban',  '1965-11-08', '2004-04-16', 1],
    ],
    // 24 - "2" prefix: Barbara + Epifania
    'l-1975' => [
        ['Barbara M. Godoy',        '1935-03-10', '2004-05-02', 1],
        ['Epifania M. Godoy',       '1935-03-14', '2023-12-01', 2],
    ],
    // 25 - "2" prefix: Salud + Victor
    'l-1974' => [
        ['Salud M. Bacay',          '1932-02-05', '2004-05-07', 1],
        ['Victor M. Bacay',         '1929-08-25', '2018-01-04', 2],
    ],
    // 26 - Unknown single
    'l-1973' => [
        ['UNKNOWN',                 null,         null,         1],
    ],
    // 27 - Unknown single
    'l-1972' => [
        ['UNKNOWN',                 null,         null,         1],
    ],
    // 28 - "2" prefix: Emilio + Francisco
    'l-1971' => [
        ['Emilio Bugos',            '1947-07-04', '2023-07-27', 1],
        ['Francisco Bugos',         '1939-10-04', '2004-06-18', 2],
    ],
    // 29 - Roberto single
    'l-1970' => [
        ['Roberto M. Manibo',       '1963-09-16', '2004-08-27', 1],
    ],
    // 30 - Unknown single
    'l-1969' => [
        ['UNKNOWN',                 null,         null,         1],
    ],
    // 31 - Leonila single
    'l-1968' => [
        ['Leonila M. Aromin',       null,         '2004-10-02', 1],
    ],
    // 32 - Unknown single
    'l-1967' => [
        ['UNKNOWN',                 null,         null,         1],
    ],
    // 33 - "2" prefix: Eusebia + Prescilla
    'l-1966' => [
        ['Eusebia C. Raquem',       '1935-12-15', '2015-09-22', 1],
        ['Prescilla C. Raquem',     '1971-07-13', '2004-12-29', 2],
    ],
    // 34 - "2" prefix: Rafael + Felicidad
    'l-1965' => [
        ['Rafael I. Cause',         '1938-03-17', '2005-06-18', 1],
        ['Felicidad R. Cause',      '1942-07-14', '1989-06-01', 2],
    ],
    // 35 - Juanito P. Umandal single
    'l-1964' => [
        ['Juanito P. Umandal',      '1943-01-23', '2005-06-17', 1],
    ],
    // 36 - Juana V. De Leon single
    'l-1963' => [
        ['Juana V. De Leon',        '1933-06-24', '2005-07-19', 1],
    ],
    // 37 - Jane B. Sapaden single
    'l-1962' => [
        ['Jane B. Sapaden',         '1990-10-19', '2020-02-19', 1],
    ],
    // 38 - "2" prefix: Aniano + Juliana
    'l-1961' => [
        ['Aniano A. Delos Reyes',   '1957-05-16', '2024-02-16', 1],
        ['Juliana U. Delos Reyes',  '1956-02-07', '2005-08-06', 2],
    ],
    // 39 - Lorenzo single
    'l-1960' => [
        ['Lorenzo Castillo Casapao','1926-05-14', '2005-09-08', 1],
    ],
    // 40 - Alicia single
    'l-1959' => [
        ['Alicia C. Mortilla',      '1968-07-22', '2005-09-10', 1],
    ],
    // 41 - "2" prefix: Jose + Teofila
    'l-1958' => [
        ['Jose B. Carandang',       '1936-02-04', '1973-04-22', 1],
        ['Teofila T. Carandang',    '1936-05-18', '2005-09-22', 2],
    ],
    // 42 - "2" prefix: Juanito L. + Cosme
    'l-1957' => [
        ['Juanito L. Alvarez',      '1947-10-08', '2022-09-07', 1],
        ['Cosme L. Alvarez',        '1953-09-27', '2005-10-14', 2],
    ],
    // 43 - "2" prefix: Cezar + Juanito D.
    'l-1956' => [
        ['Cezar O. Maculit',        '1952-07-30', '2018-02-09', 1],
        ['Juanito D. Maculit',      '1980-02-27', '2007-01-10', 2],
    ],
    // 44 - "2" prefix: Felipe + Elsie
    'l-1955' => [
        ['Felipe Bermudez',         '1930-03-10', '1994-06-18', 1],
        ['Elsie M. Bermudez',       '1949-03-05', '2021-04-20', 2],
    ],
    // 45 - Renato single
    'l-1954' => [
        ['Renato B. Fabiano',       '1965-11-26', '2006-03-13', 1],
    ],
    // 46 - "2" prefix: Ricardo + Eulalia
    'l-1953' => [
        ['Ricardo O. Cudiamat',     '1966-09-26', '2017-05-02', 1],
        ['Eulalia O. Cudiamat',     '1950-05-08', '2012-03-22', 2],
    ],
    // 47 - Veronica single
    'l-1952' => [
        ['Veronica A. Cabase',      '1906-03-25', '2006-03-31', 1],
    ],
    // 48 - Maximino single
    'l-1951' => [
        ['Maximino U. Asilo',       '1964-10-07', '2006-04-05', 1],
    ],
    // 49 - Patricio single
    'l-1950' => [
        ['Patricio M. De Castro',   '1946-03-17', '2018-11-23', 1],
    ],
    // 50 - Roman single
    'l-1949' => [
        ['Roman Dela Roca',         '1928-05-14', '2020-09-30', 1],
    ],
    // 51 - Adela single
    'l-1948' => [
        ['Adela Javier',            '1942-06-30', '2002-10-26', 1],
    ],
    // 52 - Marlo single
    'l-1947' => [
        ['Marlo U. Asilo',          '1958-12-09', '2006-11-06', 1],
    ],
    // 53 - Cesar J. Gabor single
    'l-1946' => [
        ['Cesar J. Gabor',          '1957-09-29', '2006-11-11', 1],
    ],
    // 54 - "2" prefix: Canuto + Auria
    'l-1945' => [
        ['Canuto Viato',            '1920-02-10', '1979-04-10', 1],
        ['Auria Cabral',            '1923-08-24', '2007-06-19', 2],
    ],
    // 55 - Marciano + Floremie + Unknown (no "2" prefix on Marciano but Floremie follows, then Unknown)
    // Source: Marciano P. Delen ... Floremie G. Delen ... Unknown — then "2" prefix on next group
    'l-1944' => [
        ['Marciano P. Delen',       '1967-01-02', '2007-07-10', 1],
        ['Floremie G. Delen',       '1974-12-27', '2005-03-10', 2],
        ['UNKNOWN',                 null,         null,         3],
    ],
    // 56 - "2" prefix: Eufronio + Evelyn
    'l-1943' => [
        ['Eufronio A. Axalan',      '1963-08-03', '2007-08-22', 1],
        ['Evelyn C. Axalan',        '1961-06-10', '2021-09-16', 2],
    ],
    // 57 - Raymundo single
    'l-1942' => [
        ['Raymundo M. Gloria',      '1952-01-23', '2007-09-01', 1],
    ],
    // 58 - Alfonso single
    'l-1941' => [
        ['Alfonso Almazan Jr.',     '1968-07-07', '2007-09-09', 1],
    ],
    // 59 - "2" prefix: Arcenia + Felisa
    'l-1940' => [
        ['Arcenia A. Cudiamat',     null,         '2007-09-01', 1],
        ['Felisa C. Boquio',        '1939-06-19', '2025-01-23', 2],
    ],
    // 60 - Manolito single
    'l-1939' => [
        ['Manolito C. Dalangin',    '1942-05-03', '2007-11-15', 1],
    ],
    // 61 - Erlinda single
    'l-1938' => [
        ['Erlinda Alulod',          '1943-03-27', '2007-12-06', 1],
    ],
    // 62 - "2" prefix: Venancio + Vivencia
    'l-1937' => [
        ['Venancio S. Llobrera',    '1933-05-10', '1991-01-01', 1],
        ['Vivencia S. Llobrera',    '1943-10-14', '1990-08-21', 2],
    ],
];

$ok = 0; $skipped = 0;
echo "<pre style='font-family:monospace;font-size:12px;padding:20px;'>";

foreach ($assignments as $lotNumber => $burials) {
    $lotId = getLotId($conn, $lotNumber);
    if (!$lotId) {
        echo "⚠ LOT NOT FOUND: $lotNumber\n";
        $skipped++;
        continue;
    }
    foreach ($burials as [$name, $dob, $dod, $layer]) {
        if (strtoupper(trim($name)) === 'UNKNOWN' || strtoupper(trim($name)) === 'NULL/OPEN') {
            $recordId = insertRecord($conn, $name, $dob, $dod);
            assignRecord($conn, $recordId, $lotId, $layer);
            echo "✓ $lotNumber L$layer — inserted & assigned [$name]\n";
        } else {
            $recordId = findRecord($conn, $name);
            if (!$recordId) {
                $recordId = insertRecord($conn, $name, $dob, $dod);
                echo "✓ $lotNumber L$layer — inserted (not found) & assigned [$name]\n";
            } else {
                echo "✓ $lotNumber L$layer — found & assigned [$name] (id=$recordId)\n";
            }
            assignRecord($conn, $recordId, $lotId, $layer);
        }
        $ok++;
    }
}

echo "\nDone — $ok assigned, $skipped lots not found.\n";
echo "</pre>";
echo "<a href='public/burial-records.php' style='font-family:sans-serif;'>Go to Burial Records</a>";
