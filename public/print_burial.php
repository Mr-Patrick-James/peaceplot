<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

// Collect filters from POST
$search     = trim($_POST['search']     ?? '');
$dateFrom   = trim($_POST['date_from']  ?? '');
$dateTo     = trim($_POST['date_to']    ?? '');
$ageMin     = ($_POST['age_min'] ?? '') !== '' ? (int)$_POST['age_min'] : null;
$ageMax     = ($_POST['age_max'] ?? '') !== '' ? (int)$_POST['age_max'] : null;
$blocks     = array_values(array_filter(explode(',', strtolower($_POST['blocks']   ?? ''))));
$sections   = array_values(array_filter(explode(',', strtolower($_POST['sections'] ?? ''))));
$assignment = trim($_POST['assignment'] ?? '');
$sortBy     = $_POST['sort_by']    ?? 'date_of_burial';
$sortOrder  = strtoupper($_POST['sort_order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

$allowedSort = ['date_of_burial','full_name','date_of_death','age','lot_number'];
if (!in_array($sortBy, $allowedSort)) $sortBy = 'date_of_burial';

// Build dynamic title from active filters
$titleParts = [];
if (!empty($blocks)) {
    $titleParts[] = implode(', ', array_map('ucwords', $blocks));
}
if (!empty($sections)) {
    $titleParts[] = implode(', ', array_map('ucwords', $sections));
}
if ($dateFrom || $dateTo) {
    $range = '';
    if ($dateFrom && $dateTo) $range = date('M d, Y', strtotime($dateFrom)) . ' – ' . date('M d, Y', strtotime($dateTo));
    elseif ($dateFrom) $range = 'From ' . date('M d, Y', strtotime($dateFrom));
    else $range = 'Until ' . date('M d, Y', strtotime($dateTo));
    $titleParts[] = $range;
}
if ($ageMin !== null || $ageMax !== null) {
    $ageRange = 'Age ';
    if ($ageMin !== null && $ageMax !== null) $ageRange .= "$ageMin–$ageMax";
    elseif ($ageMin !== null) $ageRange .= "$ageMin+";
    else $ageRange .= "Up to $ageMax";
    $titleParts[] = $ageRange;
}
if ($assignment === 'assigned')   $titleParts[] = 'Assigned';
if ($assignment === 'unassigned') $titleParts[] = 'Unassigned';

$title = !empty($titleParts)
    ? implode(' · ', $titleParts) . ' — Burial Records'
    : 'Burial Records Report';

$now     = date('F d, Y');
$nowTime = date('F d, Y \a\t h:i A');

// Build query
$where = ['dr.is_archived = 0'];
$params = [];

if ($search !== '') {
    $where[] = 'LOWER(dr.full_name) LIKE :search';
    $params[':search'] = '%' . strtolower($search) . '%';
}
if ($dateFrom !== '') {
    $where[] = 'dr.date_of_burial >= :date_from';
    $params[':date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = 'dr.date_of_burial <= :date_to';
    $params[':date_to'] = $dateTo;
}
if ($ageMin !== null) {
    $where[] = 'dr.age >= :age_min';
    $params[':age_min'] = $ageMin;
}
if ($ageMax !== null) {
    $where[] = 'dr.age <= :age_max';
    $params[':age_max'] = $ageMax;
}
if (!empty($blocks)) {
    $ph = implode(',', array_map(fn($i) => ":blk$i", array_keys($blocks)));
    $where[] = "LOWER(b.name) IN ($ph)";
    foreach ($blocks as $i => $v) $params[":blk$i"] = $v;
}
if (!empty($sections)) {
    $ph = implode(',', array_map(fn($i) => ":sec$i", array_keys($sections)));
    $where[] = "LOWER(s.name) IN ($ph)";
    foreach ($sections as $i => $v) $params[":sec$i"] = $v;
}
if ($assignment === 'assigned')   $where[] = 'dr.lot_id IS NOT NULL';
if ($assignment === 'unassigned') $where[] = 'dr.lot_id IS NULL';

$sortCol = match($sortBy) {
    'full_name'      => 'dr.full_name',
    'date_of_death'  => 'dr.date_of_death',
    'age'            => 'dr.age',
    'lot_number'     => 'cl.lot_number',
    default          => 'dr.date_of_burial',
};

$sql = "
    SELECT dr.full_name, cl.lot_number, s.name as section, b.name as block,
           dr.date_of_burial, dr.age
    FROM deceased_records dr
    LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id
    LEFT JOIN sections s ON cl.section_id = s.id
    LEFT JOIN blocks b ON s.block_id = b.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY $sortCol $sortOrder
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total   = count($records);
$now     = date('F d, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?php echo htmlspecialchars($title); ?></title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }

    /* ── Screen: preview layout ── */
    body {
      background: #525659;
      font-family: 'Segoe UI', Arial, sans-serif;
      font-size: 13px;
      color: #1e293b;
      min-height: 100vh;
    }

    #toolbar {
      position: sticky;
      top: 0;
      z-index: 10;
      background: #3c3f41;
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 24px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    }
    #toolbar .title { color: #fff; font-size: 14px; font-weight: 600; flex: 1; }
    #toolbar .meta  { color: #94a3b8; font-size: 12px; }
    .btn-print {
      background: #3b82f6; color: #fff; border: none;
      padding: 9px 20px; border-radius: 8px; font-size: 13px;
      font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 7px;
    }
    .btn-print:hover { background: #2563eb; }
    .btn-close {
      background: rgba(255,255,255,0.12); color: #fff; border: none;
      padding: 9px 16px; border-radius: 8px; font-size: 13px;
      font-weight: 600; cursor: pointer;
    }
    .btn-close:hover { background: rgba(255,255,255,0.2); }

    #pages { display: flex; flex-direction: column; align-items: center; padding: 32px 16px 48px; gap: 24px; }

    .page {
      background: #fff;
      width: 210mm;
      min-height: 297mm;
      padding: 1.5cm 1.5cm 2cm;
      box-shadow: 0 4px 24px rgba(0,0,0,0.35);
      position: relative;
    }

    /* Header */
    .page-header { text-align: center; border-bottom: 2px solid #1e293b; padding-bottom: 14px; margin-bottom: 22px; }
    .page-header .org { font-size: 13px; color: #475569; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
    .page-header h1 { font-size: 20px; font-weight: 700; margin-top: 4px; }
    .page-header .meta { font-size: 11px; color: #94a3b8; margin-top: 5px; }

    /* Table */
    table { width: 100%; border-collapse: collapse; }
    thead th {
      background: #f1f5f9; text-align: left;
      padding: 10px 12px; font-size: 9px; font-weight: 700;
      text-transform: uppercase; color: #64748b;
      border-bottom: 2px solid #e2e8f0;
    }
    tbody td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; font-size: 11px; }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:nth-child(even) { background: #f8fafc; }

    /* Page footer */
    .page-footer {
      position: absolute; bottom: 1cm; left: 1.5cm; right: 1.5cm;
      display: flex; justify-content: space-between; align-items: center;
      font-size: 10px; color: #94a3b8;
      border-top: 1px solid #e2e8f0; padding-top: 8px;
    }

    /* ── Print: clean output ── */
    @media print {
      body { background: #fff; }
      #toolbar { display: none !important; }
      #pages { padding: 0; gap: 0; }
      .page {
        width: 100%; box-shadow: none;
        padding: 1.5cm 1.5cm 2cm;
        page-break-after: always;
        min-height: auto;
      }
      .page:last-child { page-break-after: avoid; }
      @page { margin: 0; size: A4; }
    }
  </style>
</head>
<body>

<div id="toolbar">
  <span class="title"><?php echo htmlspecialchars($title); ?></span>
  <span class="meta"><?php echo $total; ?> record<?php echo $total !== 1 ? 's' : ''; ?></span>
  <button class="btn-print" onclick="window.print()">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/>
    </svg>
    Print
  </button>
  <button class="btn-close" onclick="window.close()">✕ Close</button>
</div>

<div id="pages">
<?php
// Split records into pages of 35 rows each
$perPage   = 35;
$pages     = array_chunk($records, $perPage);
$pageCount = count($pages);
if ($pageCount === 0) $pageCount = 1;

foreach ($pages as $pageNum => $rows):
  $pg = $pageNum + 1;
?>
  <div class="page">
    <div class="page-header">
      <div class="org">Barcenaga Holy Spirit Parish</div>
      <h1><?php echo htmlspecialchars($title); ?></h1>
      <div class="meta">Printed on <?php echo $nowTime; ?> &nbsp;|&nbsp; PeacePlot Cemetery Management System &nbsp;|&nbsp; <?php echo $total; ?> total records</div>
    </div>

    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Full Name</th>
          <th>Lot</th>
          <th>Section</th>
          <th>Block</th>
          <th>Date of Burial</th>
          <th>Age</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $i => $r):
          $rowNum = $pageNum * $perPage + $i + 1;
        ?>
        <tr>
          <td style="color:#94a3b8; font-size:11px;"><?php echo $rowNum; ?></td>
          <td style="font-weight:600;"><?php echo htmlspecialchars($r['full_name']); ?></td>
          <td><?php echo htmlspecialchars($r['lot_number'] ?: '—'); ?></td>
          <td><?php echo htmlspecialchars($r['section'] ?: '—'); ?></td>
          <td><?php echo htmlspecialchars($r['block'] ?: '—'); ?></td>
          <td><?php echo $r['date_of_burial'] ? date('M d, Y', strtotime($r['date_of_burial'])) : '—'; ?></td>
          <td><?php echo $r['age'] ?: '—'; ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="page-footer">
      <span>© 2025 Barcenaga Holy Spirit Parish — PeacePlot Cemetery Management System</span>
      <span>Page <?php echo $pg; ?> of <?php echo $pageCount; ?></span>
    </div>
  </div>
<?php endforeach; ?>
</div>

</body>
</html>
