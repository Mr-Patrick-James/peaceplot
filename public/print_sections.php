<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$search      = trim($_POST['search']       ?? '');
$hasOccupied = !empty($_POST['has_occupied']);
$hasVacant   = !empty($_POST['has_vacant']);
$selBlocks   = array_values(array_filter(explode(',', strtolower($_POST['blocks']   ?? ''))));
$selSections = array_values(array_filter(explode(',', strtolower($_POST['sections'] ?? ''))));

// Build dynamic title
$titleParts = [];
if (!empty($selBlocks))   $titleParts[] = implode(', ', array_map('ucwords', $selBlocks));
if (!empty($selSections)) $titleParts[] = implode(', ', array_map('ucwords', $selSections));
if ($hasOccupied && !$hasVacant) $titleParts[] = 'Occupied';
if ($hasVacant   && !$hasOccupied) $titleParts[] = 'Vacant';
$title   = !empty($titleParts) ? implode(' · ', $titleParts) . ' — Section Summary' : 'Section Summary Report';
$nowTime = date('F d, Y \a\t h:i A');

$stmt = $conn->query("
    SELECT s.name as section, b.name as block_name,
        COUNT(cl.id) as total,
        SUM(CASE WHEN cl.status='Occupied' THEN 1 ELSE 0 END) as occupied,
        SUM(CASE WHEN cl.status='Vacant' THEN 1 ELSE 0 END) as vacant
    FROM sections s
    LEFT JOIN blocks b ON s.block_id = b.id
    LEFT JOIN cemetery_lots cl ON s.id = cl.section_id
    GROUP BY s.id ORDER BY b.name, s.name
");
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filtered = array_filter($sections, function($s) use ($search, $hasOccupied, $hasVacant, $selBlocks, $selSections) {
    if ($search && stripos($s['section'], $search) === false && stripos($s['block_name'] ?? '', $search) === false) return false;
    if ($hasOccupied && $s['occupied'] == 0) return false;
    if ($hasVacant   && $s['vacant']   == 0) return false;
    if (!empty($selBlocks)   && !in_array(strtolower($s['block_name'] ?? ''), $selBlocks))   return false;
    if (!empty($selSections) && !in_array(strtolower($s['section']), $selSections)) return false;
    return true;
});

$total = count($filtered);
$now   = date('F d, Y');
$perPage   = 35;
$pages     = array_chunk(array_values($filtered), $perPage);
$pageCount = max(1, count($pages));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Section Summary Report</title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { background:#525659; font-family:'Segoe UI',Arial,sans-serif; font-size:13px; color:#1e293b; min-height:100vh; }
    #toolbar { position:sticky; top:0; z-index:10; background:#3c3f41; display:flex; align-items:center; gap:12px; padding:12px 24px; box-shadow:0 2px 8px rgba(0,0,0,0.3); }
    #toolbar .title { color:#fff; font-size:14px; font-weight:600; flex:1; }
    #toolbar .meta  { color:#94a3b8; font-size:12px; }
    .btn-print { background:#3b82f6; color:#fff; border:none; padding:9px 20px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:7px; }
    .btn-print:hover { background:#2563eb; }
    .btn-close { background:rgba(255,255,255,0.12); color:#fff; border:none; padding:9px 16px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; }
    #pages { display:flex; flex-direction:column; align-items:center; padding:32px 16px 48px; gap:24px; }
    .page { background:#fff; width:210mm; min-height:297mm; padding:1.5cm 1.5cm 2cm; box-shadow:0 4px 24px rgba(0,0,0,0.35); position:relative; }
    .page-header { text-align:center; border-bottom:2px solid #1e293b; padding-bottom:14px; margin-bottom:22px; }
    .page-header .org { font-size:13px; color:#475569; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; }
    .page-header h1 { font-size:20px; font-weight:700; margin-top:4px; }
    .page-header .meta { font-size:11px; color:#94a3b8; margin-top:5px; }
    table { width:100%; border-collapse:collapse; }
    thead th { background:#f1f5f9; text-align:left; padding:10px 12px; font-size:9px; font-weight:700; text-transform:uppercase; color:#64748b; border-bottom:2px solid #e2e8f0; }
    tbody td { padding:10px 12px; border-bottom:1px solid #f1f5f9; font-size:11px; }
    tbody tr:last-child td { border-bottom:none; }
    tbody tr:nth-child(even) { background:#f8fafc; }
    .page-footer { position:absolute; bottom:1cm; left:1.5cm; right:1.5cm; display:flex; justify-content:space-between; font-size:10px; color:#94a3b8; border-top:1px solid #e2e8f0; padding-top:8px; }
    @media print {
      body { background:#fff; }
      #toolbar { display:none !important; }
      #pages { padding:0; gap:0; }
      .page { width:100%; box-shadow:none; padding:1.5cm 1.5cm 2cm; page-break-after:always; min-height:auto; }
      .page:last-child { page-break-after:avoid; }
      @page { margin:0; size:A4; }
    }
  </style>
</head>
<body>
<div id="toolbar">
  <span class="title"><?php echo htmlspecialchars($title); ?></span>
  <span class="meta"><?php echo $total; ?> section<?php echo $total !== 1 ? 's' : ''; ?></span>
  <button class="btn-print" onclick="window.print()">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
    Print
  </button>
  <button class="btn-close" onclick="window.close()">✕ Close</button>
</div>
<div id="pages">
<?php foreach ($pages as $pgIdx => $rows):
  $pg = $pgIdx + 1;
?>
  <div class="page">
    <div class="page-header">
      <div class="org">Barcenaga Holy Spirit Parish</div>
      <h1><?php echo htmlspecialchars($title); ?></h1>
      <div class="meta">Printed on <?php echo $nowTime; ?> &nbsp;|&nbsp; PeacePlot Cemetery Management System</div>
    </div>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Block</th>
          <th>Section</th>
          <th align="right">Total Lots</th>
          <th align="right">Occupied</th>
          <th align="right">Vacant</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $i => $s): $rowNum = $pgIdx * $perPage + $i + 1; ?>
          <tr>
            <td style="color:#94a3b8; font-size:11px;"><?php echo $rowNum; ?></td>
            <td><?php echo htmlspecialchars($s['block_name'] ?? '—'); ?></td>
            <td style="font-weight:600;"><?php echo htmlspecialchars($s['section']); ?></td>
            <td align="right"><?php echo $s['total']; ?></td>
            <td align="right" style="color:#f97316;"><?php echo $s['occupied']; ?></td>
            <td align="right" style="color:#10b981;"><?php echo $s['vacant']; ?></td>
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
