<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$user = getUserInfo();
$userInitials = getInitials($user['full_name']);

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

require_once __DIR__ . '/includes/page_tracker.php';

$stats = [
    'total_lots' => 0,
    'available_lots' => 0,
    'occupied_lots' => 0,
    'total_sections' => 0,
    'total_blocks' => 0,
    'total_burials' => 0,
    'sections' => []
];

if ($conn) {
    try {
        $stats['total_lots']    = $conn->query("SELECT COUNT(*) FROM cemetery_lots")->fetchColumn();
        $stats['total_sections']= $conn->query("SELECT COUNT(*) FROM sections")->fetchColumn();
        $stats['total_blocks']  = $conn->query("SELECT COUNT(*) FROM blocks")->fetchColumn();
        $stats['total_burials'] = $conn->query("SELECT COUNT(*) FROM deceased_records WHERE is_archived=0")->fetchColumn();

        $occupancyQuery = "
            SELECT
                cl.id,
                s.name  AS section_name,
                b.name  AS block_name,
                CASE
                    WHEN dr_count.cnt  > 0 THEN 'Occupied'
                    WHEN ll_count.cnt  > 0 THEN 'Occupied'
                    ELSE cl.status
                END AS actual_status
            FROM cemetery_lots cl
            LEFT JOIN sections s ON cl.section_id = s.id
            LEFT JOIN blocks   b ON s.block_id    = b.id
            LEFT JOIN (SELECT lot_id, COUNT(*) AS cnt FROM deceased_records WHERE is_archived=0 GROUP BY lot_id) dr_count ON dr_count.lot_id = cl.id
            LEFT JOIN (SELECT lot_id, COUNT(*) AS cnt FROM lot_layers   WHERE is_occupied=1  GROUP BY lot_id) ll_count ON ll_count.lot_id = cl.id
        ";

        $row = $conn->query("
            SELECT
                SUM(CASE WHEN actual_status='Vacant'   THEN 1 ELSE 0 END) AS available,
                SUM(CASE WHEN actual_status='Occupied' THEN 1 ELSE 0 END) AS occupied
            FROM ($occupancyQuery) t
        ")->fetch(PDO::FETCH_ASSOC);

        $stats['available_lots'] = $row['available'] ?? 0;
        $stats['occupied_lots']  = $row['occupied']  ?? 0;

        $stmt = $conn->query("
            SELECT
                section_name AS section,
                block_name   AS block,
                COUNT(*)     AS total,
                SUM(CASE WHEN actual_status='Occupied' THEN 1 ELSE 0 END) AS occupied,
                SUM(CASE WHEN actual_status='Vacant'   THEN 1 ELSE 0 END) AS vacant
            FROM ($occupancyQuery) t
            WHERE section_name IS NOT NULL AND block_name IS NOT NULL
            GROUP BY section_name, block_name
            ORDER BY CAST(SUBSTR(block_name,   INSTR(block_name,   ' ')+1) AS INTEGER),
                     CAST(SUBSTR(section_name, INSTR(section_name, ' ')+1) AS INTEGER)
        ");
        $stats['sections'] = $stmt->fetchAll();

        $stmt = $conn->query("
            SELECT dr.*, cl.lot_number, s.name AS section_name
            FROM deceased_records dr
            LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id
            LEFT JOIN sections s ON cl.section_id = s.id
            WHERE dr.is_archived = 0
            ORDER BY dr.created_at DESC
            LIMIT 5
        ");
        $recent_burials = $stmt->fetchAll();

        $available_percent = $stats['total_lots'] > 0 ? round(($stats['available_lots'] / $stats['total_lots']) * 100, 1) : 0;
        $occupied_percent  = $stats['total_lots'] > 0 ? round(($stats['occupied_lots']  / $stats['total_lots']) * 100, 1) : 0;

    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}

// Greeting
$hour = (int)date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$firstName = explode(' ', trim($user['full_name']))[0];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PeacePlot Admin - Dashboard</title>
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* ── Base ─────────────────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; }
    body { font-family: 'Inter', system-ui, sans-serif; }

    /* ── Fade-in keyframes ───────────────────────────────────── */
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(18px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .fade-up { animation: fadeUp 0.5s ease both; }
    .fade-up-1 { animation-delay: 0.05s; }
    .fade-up-2 { animation-delay: 0.10s; }
    .fade-up-3 { animation-delay: 0.15s; }
    .fade-up-4 { animation-delay: 0.20s; }
    .fade-up-5 { animation-delay: 0.25s; }
    .fade-up-6 { animation-delay: 0.30s; }
    .fade-up-7 { animation-delay: 0.35s; }
    .fade-up-8 { animation-delay: 0.40s; }

    /* ── Dashboard header (search bar row) ───────────────────── */
    .dashboard-header {
      background: #fff;
      padding: 20px 28px;
      border-radius: 16px;
      margin-bottom: 24px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.04);
      display: flex;
      justify-content: space-between;
      align-items: center;
      border: 1px solid #f1f5f9;
    }
    .header-left .title {
      font-size: 22px;
      font-weight: 700;
      color: #0f172a;
      margin: 0 0 4px 0;
      letter-spacing: -0.02em;
    }
    .header-left .subtitle {
      font-size: 13px;
      color: #64748b;
      margin: 0;
    }

    /* ── Hero Banner ─────────────────────────────────────────── */
    .hero-banner {
      position: relative;
      background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0f172a 100%);
      border-radius: 20px;
      padding: 40px 44px;
      margin-bottom: 28px;
      overflow: hidden;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 24px;
    }
    .hero-banner::after {
      content: '';
      position: absolute;
      bottom: 0; left: 0; right: 0;
      height: 3px;
      background: linear-gradient(90deg, #3b82f6, #8b5cf6, #f43f5e, #f59e0b, #10b981);
    }
    .hero-svg-overlay {
      position: absolute;
      inset: 0;
      pointer-events: none;
      opacity: 0.04;
    }
    .hero-left { position: relative; z-index: 1; }
    .hero-greeting {
      font-size: 13px;
      font-weight: 500;
      color: #93c5fd;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      margin-bottom: 10px;
    }
    .hero-title {
      font-size: 34px;
      font-weight: 800;
      color: #fff;
      letter-spacing: -0.03em;
      line-height: 1.15;
      margin: 0 0 12px 0;
    }
    .hero-title span { color: #60a5fa; }
    .hero-subtitle {
      font-size: 15px;
      color: #94a3b8;
      margin: 0;
      font-weight: 400;
      max-width: 480px;
    }
    .hero-right {
      position: relative;
      z-index: 1;
      text-align: right;
      flex-shrink: 0;
    }
    .hero-date-label {
      font-size: 11px;
      font-weight: 600;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      margin-bottom: 6px;
    }
    .hero-date {
      font-size: 22px;
      font-weight: 700;
      color: #e2e8f0;
      letter-spacing: -0.02em;
    }
    .hero-time {
      font-size: 13px;
      color: #64748b;
      margin-top: 4px;
    }
    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(59,130,246,0.15);
      border: 1px solid rgba(59,130,246,0.3);
      color: #93c5fd;
      font-size: 12px;
      font-weight: 600;
      padding: 6px 14px;
      border-radius: 20px;
      margin-top: 16px;
    }
    .hero-badge-dot {
      width: 7px; height: 7px;
      background: #10b981;
      border-radius: 50%;
      animation: pulse-dot 2s infinite;
    }
    @keyframes pulse-dot {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.6; transform: scale(0.8); }
    }

    /* ── Stats Grid ──────────────────────────────────────────── */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 16px;
      margin-bottom: 24px;
    }
    @media (max-width: 1400px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 900px)  { .stats-grid { grid-template-columns: repeat(2, 1fr); } }

    .stat-card {
      background: #fff;
      border-radius: 16px;
      padding: 22px 20px 18px 20px;
      border: 1px solid #f1f5f9;
      box-shadow: 0 2px 12px rgba(0,0,0,0.03);
      position: relative;
      overflow: hidden;
      cursor: pointer;
      transition: transform 0.25s ease, box-shadow 0.25s ease;
      display: flex;
      flex-direction: column;
      gap: 0;
    }
    .stat-card::before {
      content: '';
      position: absolute;
      left: 0; top: 0; bottom: 0;
      width: 4px;
      border-radius: 16px 0 0 16px;
    }
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 16px 40px rgba(0,0,0,0.09);
    }
    .stat-card-top {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 14px;
    }
    .stat-label {
      font-size: 11px;
      font-weight: 600;
      color: #94a3b8;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }
    .stat-icon-circle {
      width: 40px; height: 40px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .stat-value {
      font-size: 36px;
      font-weight: 800;
      color: #0f172a;
      letter-spacing: -0.03em;
      line-height: 1;
      margin-bottom: 6px;
    }
    .stat-subtext {
      font-size: 12px;
      color: #64748b;
      font-weight: 400;
      margin-bottom: 12px;
    }
    .stat-subtext strong { font-weight: 700; }

    /* Progress bar inside stat cards */
    .stat-progress-wrap {
      margin-top: auto;
    }
    .stat-progress-bar {
      height: 5px;
      background: #f1f5f9;
      border-radius: 99px;
      overflow: hidden;
    }
    .stat-progress-fill {
      height: 100%;
      border-radius: 99px;
      transition: width 1s ease;
    }
    .stat-progress-label {
      font-size: 10px;
      color: #94a3b8;
      margin-top: 5px;
      font-weight: 500;
    }

    /* Card accent colors */
    .accent-blue::before   { background: #3b82f6; }
    .accent-indigo::before { background: #6366f1; }
    .accent-purple::before { background: #8b5cf6; }
    .accent-rose::before   { background: #f43f5e; }
    .accent-emerald::before{ background: #10b981; }
    .accent-amber::before  { background: #f59e0b; }

    .icon-bg-blue   { background: #eff6ff; color: #3b82f6; }
    .icon-bg-indigo { background: #eef2ff; color: #6366f1; }
    .icon-bg-purple { background: #f5f3ff; color: #8b5cf6; }
    .icon-bg-rose   { background: #fff1f2; color: #f43f5e; }
    .icon-bg-emerald{ background: #ecfdf5; color: #10b981; }
    .icon-bg-amber  { background: #fffbeb; color: #f59e0b; }

    .fill-blue   { background: #3b82f6; }
    .fill-indigo { background: #6366f1; }
    .fill-purple { background: #8b5cf6; }
    .fill-rose   { background: #f43f5e; }
    .fill-emerald{ background: #10b981; }
    .fill-amber  { background: #f59e0b; }

    /* ── Quick Actions ───────────────────────────────────────── */
    .quick-actions-row {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 14px;
      margin-bottom: 28px;
    }
    @media (max-width: 900px) { .quick-actions-row { grid-template-columns: repeat(2, 1fr); } }

    .quick-action-btn {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 18px 20px;
      background: #fff;
      border: 1px solid #f1f5f9;
      border-radius: 14px;
      text-decoration: none;
      color: #1e293b;
      font-weight: 600;
      font-size: 14px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.03);
      transition: all 0.22s ease;
      cursor: pointer;
    }
    .quick-action-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 28px rgba(0,0,0,0.08);
      border-color: transparent;
    }
    .quick-action-btn:hover .qa-icon { transform: scale(1.12) rotate(-6deg); }
    .qa-icon {
      width: 44px; height: 44px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      transition: transform 0.22s ease;
    }
    .qa-text { display: flex; flex-direction: column; gap: 2px; }
    .qa-label { font-size: 14px; font-weight: 700; color: #0f172a; }
    .qa-sub   { font-size: 11px; color: #94a3b8; font-weight: 400; }

    /* ── Two-column layout ───────────────────────────────────── */
    .two-col {
      display: grid;
      grid-template-columns: 3fr 2fr;
      gap: 20px;
      margin-bottom: 20px;
    }
    @media (max-width: 1100px) { .two-col { grid-template-columns: 1fr; } }

    /* ── Content Cards ───────────────────────────────────────── */
    .content-card {
      background: #fff;
      border-radius: 18px;
      border: 1px solid #f1f5f9;
      box-shadow: 0 2px 12px rgba(0,0,0,0.03);
      overflow: hidden;
    }
    .card-header {
      padding: 22px 26px;
      border-bottom: 1px solid #f8fafc;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .card-title {
      font-size: 16px;
      font-weight: 700;
      color: #0f172a;
      margin: 0;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .card-title-icon {
      width: 32px; height: 32px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .card-subtitle {
      font-size: 12px;
      color: #94a3b8;
      margin: 3px 0 0 0;
      font-weight: 400;
    }
    .btn-view-all {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 7px 14px;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      background: #fff;
      color: #475569;
      font-size: 12px;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.2s;
    }
    .btn-view-all:hover { background: #f8fafc; border-color: #cbd5e1; color: #0f172a; }

    /* ── Recent Burials Table ────────────────────────────────── */
    .burials-table { width: 100%; border-collapse: collapse; }
    .burials-table th {
      background: #f8fafc;
      padding: 12px 24px;
      text-align: left;
      font-size: 10.5px;
      font-weight: 700;
      color: #94a3b8;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      border-bottom: 1px solid #f1f5f9;
    }
    .burials-table td {
      padding: 15px 24px;
      border-bottom: 1px solid #f8fafc;
      font-size: 13.5px;
      color: #334155;
      vertical-align: middle;
    }
    .burials-table tr:last-child td { border-bottom: none; }
    .burials-table tbody tr {
      transition: background 0.15s ease;
    }
    .burials-table tbody tr:hover td { background: #f0f9ff; }

    .avatar-initials {
      width: 36px; height: 36px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      font-weight: 700;
      flex-shrink: 0;
      color: #fff;
    }
    .burial-name { font-weight: 600; color: #0f172a; font-size: 13.5px; }
    .burial-meta { font-size: 11px; color: #94a3b8; margin-top: 2px; }

    .lot-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      background: #f1f5f9;
      color: #475569;
      font-size: 12px;
      font-weight: 600;
      padding: 4px 10px;
      border-radius: 6px;
    }
    .section-tag {
      font-size: 11px;
      color: #94a3b8;
      margin-top: 3px;
    }
    .btn-view-row {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 5px 12px;
      background: #eff6ff;
      color: #3b82f6;
      border-radius: 7px;
      font-size: 12px;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.18s;
      border: 1px solid transparent;
    }
    .btn-view-row:hover { background: #3b82f6; color: #fff; }

    /* ── Donut Chart Card ────────────────────────────────────── */
    .donut-wrap {
      padding: 28px 24px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 20px;
    }
    .donut-canvas-wrap {
      position: relative;
      width: 190px; height: 190px;
    }
    .donut-center {
      position: absolute;
      top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      text-align: center;
      pointer-events: none;
    }
    .donut-center-num {
      font-size: 30px;
      font-weight: 800;
      color: #0f172a;
      letter-spacing: -0.03em;
      line-height: 1;
    }
    .donut-center-label {
      font-size: 10px;
      color: #94a3b8;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      margin-top: 3px;
    }
    .donut-legend {
      display: flex;
      gap: 28px;
      width: 100%;
      justify-content: center;
    }
    .legend-item { text-align: center; }
    .legend-num {
      font-size: 22px;
      font-weight: 800;
      letter-spacing: -0.02em;
      line-height: 1;
    }
    .legend-label {
      font-size: 11px;
      color: #94a3b8;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
      margin-top: 4px;
      font-weight: 500;
    }
    .legend-dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      display: inline-block;
    }
    .occupancy-bar-wrap {
      width: 100%;
      padding: 0 4px;
    }
    .occupancy-bar-track {
      height: 8px;
      background: #f1f5f9;
      border-radius: 99px;
      overflow: hidden;
      display: flex;
    }
    .occupancy-bar-fill-emerald {
      height: 100%;
      background: linear-gradient(90deg, #10b981, #34d399);
      border-radius: 99px 0 0 99px;
      transition: width 1.2s ease;
    }
    .occupancy-bar-fill-rose {
      height: 100%;
      background: linear-gradient(90deg, #f43f5e, #fb7185);
      border-radius: 0 99px 99px 0;
      transition: width 1.2s ease;
    }
    .occupancy-bar-labels {
      display: flex;
      justify-content: space-between;
      margin-top: 6px;
      font-size: 11px;
      color: #94a3b8;
      font-weight: 500;
    }

    /* ── Bar Chart Card ──────────────────────────────────────── */
    .bar-chart-card { margin-bottom: 0; }
    .bar-chart-wrap { padding: 20px 26px 24px; }

    /* ── Empty state ─────────────────────────────────────────── */
    .empty-state {
      text-align: center;
      padding: 52px 24px;
      color: #94a3b8;
    }
    .empty-state svg { opacity: 0.35; margin-bottom: 12px; }
    .empty-state p { font-size: 14px; margin: 0; }
  </style>
</head>

<body>
  <div class="app">
    <!-- ── Sidebar ──────────────────────────────────────────── -->
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-title">PeacePlot Admin</div>
        <div class="brand-sub">Cemetery Management</div>
      </div>

      <nav class="nav">
        <a href="dashboard.php" class="active">
          <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 13h8V3H3v10z" /><path d="M13 21h8V11h-8v10z" /><path d="M13 3h8v6h-8V3z" /><path d="M3 21h8v-6H3v6z" /></svg></span>
          <span>Dashboard</span>
        </a>
        <div class="dropdown">
          <a href="#" class="dropdown-toggle" onclick="this.parentElement.classList.toggle('active'); return false;">
            <div style="display:flex;align-items:center;">
              <span class="icon">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/>
                  <path d="M8 7v10"/><path d="M16 7v10"/>
                </svg>
              </span>
              <span>Lot Management</span>
            </div>
            <svg class="arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
          </a>
          <div class="dropdown-content">
            <a href="index.php"><span>Manage Lots</span></a>
            <a href="blocks.php"><span>Manage Blocks</span></a>
            <a href="sections.php"><span>Manage Sections</span></a>
            <a href="lot-availability.php"><span>Lots</span></a>
            <a href="map-editor.php"><span>Map Editor</span></a>
          </div>
        </div>
        <a href="cemetery-map.php">
          <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z"/><path d="M9 4v14"/><path d="M15 6v14"/></svg></span>
          <span>Cemetery Map</span>
        </a>
        <a href="burial-records.php">
          <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/><path d="M8 6h8"/><path d="M8 10h8"/></svg></span>
          <span>Burial Records</span>
        </a>
        <a href="reports.php">
          <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 14v4"/><path d="M11 10v8"/><path d="M15 6v12"/><path d="M19 12v6"/></svg></span>
          <span>Reports</span>
        </a>
        <a href="history.php">
          <span class="icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
          <span>History</span>
        </a>
      </nav>

      <div class="sidebar-footer">
        <div class="user" onclick="window.location.href='settings.php'" style="cursor:pointer;">
          <div class="avatar"><?php echo htmlspecialchars($userInitials); ?></div>
          <div class="user-info-text">
            <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
            <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
          </div>
        </div>
        <a class="logout" href="logout.php">
          <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg></span>
          <span>Logout</span>
        </a>
      </div>
    </aside>

    <!-- ── Main ─────────────────────────────────────────────── -->
    <main class="main">

      <!-- Search / Nav Header -->
      <header class="dashboard-header fade-up fade-up-1">
        <div class="header-left">
          <h1 class="title">Dashboard</h1>
          <p class="subtitle">Cemetery operations at a glance</p>
        </div>
        <div class="header-search">
          <div class="universal-search-wrapper">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" class="universal-search-input" id="universalSearch" placeholder="Global Search lots, deceased names...">
          </div>
          <div class="search-results-dropdown" id="searchResults"></div>
        </div>
      </header>

      <?php if (isset($error)): ?>
        <div style="background:#fff1f2;border:1px solid #fecdd3;color:#e11d48;padding:18px 24px;border-radius:14px;margin-bottom:24px;font-size:14px;">
          <strong>Error loading dashboard data:</strong> <?php echo htmlspecialchars($error); ?>
        </div>
      <?php else: ?>

      <!-- ── Hero Banner ──────────────────────────────────────── -->
      <div class="hero-banner fade-up fade-up-2">
        <!-- Decorative SVG pattern overlay -->
        <svg class="hero-svg-overlay" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">
          <defs>
            <pattern id="crossGrid" x="0" y="0" width="40" height="40" patternUnits="userSpaceOnUse">
              <line x1="20" y1="8" x2="20" y2="32" stroke="white" stroke-width="1.5"/>
              <line x1="8" y1="20" x2="32" y2="20" stroke="white" stroke-width="1.5"/>
              <circle cx="20" cy="20" r="1.5" fill="white"/>
            </pattern>
          </defs>
          <rect width="100%" height="100%" fill="url(#crossGrid)"/>
        </svg>

        <div class="hero-left">
          <div class="hero-greeting"><?php echo $greeting; ?></div>
          <h2 class="hero-title"><?php echo htmlspecialchars($firstName); ?>, <span>welcome back.</span></h2>
          <p class="hero-subtitle">Here's what's happening at the cemetery today. All systems are running normally.</p>
          <div class="hero-badge">
            <span class="hero-badge-dot"></span>
            System Online &mdash; <?php echo $stats['total_burials']; ?> active records
          </div>
        </div>

        <div class="hero-right">
          <div class="hero-date-label">Today</div>
          <div class="hero-date"><?php echo date('F j, Y'); ?></div>
          <div class="hero-time"><?php echo date('l'); ?></div>
        </div>
      </div>

      <!-- ── Stats Grid ────────────────────────────────────────── -->
      <div class="stats-grid">

        <!-- Total Lots -->
        <div class="stat-card accent-blue fade-up fade-up-3" onclick="window.location.href='index.php'" title="Manage Lots">
          <div class="stat-card-top">
            <div class="stat-label">Total Lots</div>
            <div class="stat-icon-circle icon-bg-blue">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            </div>
          </div>
          <div class="stat-value" data-count="<?php echo $stats['total_lots']; ?>">0</div>
          <div class="stat-subtext">Across all sections &amp; blocks</div>
          <div class="stat-progress-wrap">
            <div class="stat-progress-bar"><div class="stat-progress-fill fill-blue" style="width:100%"></div></div>
            <div class="stat-progress-label">All lots registered</div>
          </div>
        </div>

        <!-- Total Sections -->
        <div class="stat-card accent-indigo fade-up fade-up-3" onclick="window.location.href='sections.php'" title="Manage Sections">
          <div class="stat-card-top">
            <div class="stat-label">Sections</div>
            <div class="stat-icon-circle icon-bg-indigo">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/></svg>
            </div>
          </div>
          <div class="stat-value" data-count="<?php echo $stats['total_sections']; ?>">0</div>
          <div class="stat-subtext">Defined cemetery areas</div>
          <div class="stat-progress-wrap">
            <div class="stat-progress-bar"><div class="stat-progress-fill fill-indigo" style="width:100%"></div></div>
            <div class="stat-progress-label">All sections active</div>
          </div>
        </div>

        <!-- Total Blocks -->
        <div class="stat-card accent-purple fade-up fade-up-3" onclick="window.location.href='blocks.php'" title="Manage Blocks">
          <div class="stat-card-top">
            <div class="stat-label">Blocks</div>
            <div class="stat-icon-circle icon-bg-purple">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
            </div>
          </div>
          <div class="stat-value" data-count="<?php echo $stats['total_blocks']; ?>">0</div>
          <div class="stat-subtext">Categorized lot groups</div>
          <div class="stat-progress-wrap">
            <div class="stat-progress-bar"><div class="stat-progress-fill fill-purple" style="width:100%"></div></div>
            <div class="stat-progress-label">All blocks configured</div>
          </div>
        </div>

        <!-- Total Burials -->
        <div class="stat-card accent-rose fade-up fade-up-4" onclick="window.location.href='burial-records.php'" title="View Burial Records">
          <div class="stat-card-top">
            <div class="stat-label">Burial Records</div>
            <div class="stat-icon-circle icon-bg-rose">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/><path d="M8 6h8"/><path d="M8 10h8"/></svg>
            </div>
          </div>
          <div class="stat-value" data-count="<?php echo $stats['total_burials']; ?>">0</div>
          <div class="stat-subtext">Active registered records</div>
          <div class="stat-progress-wrap">
            <div class="stat-progress-bar"><div class="stat-progress-fill fill-rose" style="width:100%"></div></div>
            <div class="stat-progress-label">Non-archived records</div>
          </div>
        </div>

        <!-- Available Lots -->
        <div class="stat-card accent-emerald fade-up fade-up-4" onclick="window.location.href='lot-availability.php?status=Vacant'" title="View Available Lots">
          <div class="stat-card-top">
            <div class="stat-label">Available Lots</div>
            <div class="stat-icon-circle icon-bg-emerald">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </div>
          </div>
          <div class="stat-value" data-count="<?php echo $stats['available_lots']; ?>">0</div>
          <div class="stat-subtext"><strong style="color:#10b981"><?php echo $available_percent; ?>%</strong> availability rate</div>
          <div class="stat-progress-wrap">
            <div class="stat-progress-bar"><div class="stat-progress-fill fill-emerald" style="width:<?php echo $available_percent; ?>%"></div></div>
            <div class="stat-progress-label"><?php echo $available_percent; ?>% of total lots vacant</div>
          </div>
        </div>

        <!-- Occupied Lots -->
        <div class="stat-card accent-amber fade-up fade-up-4" onclick="window.location.href='lot-availability.php?status=Occupied'" title="View Occupied Lots">
          <div class="stat-card-top">
            <div class="stat-label">Occupied Lots</div>
            <div class="stat-icon-circle icon-bg-amber">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
          </div>
          <div class="stat-value" data-count="<?php echo $stats['occupied_lots']; ?>">0</div>
          <div class="stat-subtext"><strong style="color:#f59e0b"><?php echo $occupied_percent; ?>%</strong> occupancy rate</div>
          <div class="stat-progress-wrap">
            <div class="stat-progress-bar"><div class="stat-progress-fill fill-amber" style="width:<?php echo $occupied_percent; ?>%"></div></div>
            <div class="stat-progress-label"><?php echo $occupied_percent; ?>% of total lots occupied</div>
          </div>
        </div>

      </div><!-- /stats-grid -->

      <!-- ── Quick Actions ─────────────────────────────────────── -->
      <div class="quick-actions-row fade-up fade-up-5">

        <a href="burial-records.php" class="quick-action-btn" style="border-top: 3px solid #3b82f6;">
          <div class="qa-icon icon-bg-blue">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          </div>
          <div class="qa-text">
            <span class="qa-label">Add Burial Record</span>
            <span class="qa-sub">Register new deceased</span>
          </div>
        </a>

        <a href="cemetery-map.php" class="quick-action-btn" style="border-top: 3px solid #10b981;">
          <div class="qa-icon icon-bg-emerald">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z"/><path d="M9 4v14"/><path d="M15 6v14"/></svg>
          </div>
          <div class="qa-text">
            <span class="qa-label">View Cemetery Map</span>
            <span class="qa-sub">Interactive lot map</span>
          </div>
        </a>

        <a href="index.php" class="quick-action-btn" style="border-top: 3px solid #6366f1;">
          <div class="qa-icon icon-bg-indigo">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
          </div>
          <div class="qa-text">
            <span class="qa-label">Manage Lots</span>
            <span class="qa-sub">Lots, blocks &amp; sections</span>
          </div>
        </a>

        <a href="reports.php" class="quick-action-btn" style="border-top: 3px solid #f59e0b;">
          <div class="qa-icon icon-bg-amber">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 14v4"/><path d="M11 10v8"/><path d="M15 6v12"/><path d="M19 12v6"/></svg>
          </div>
          <div class="qa-text">
            <span class="qa-label">View Reports</span>
            <span class="qa-sub">Analytics &amp; exports</span>
          </div>
        </a>

      </div><!-- /quick-actions-row -->

      <!-- ── Two-column: Recent Burials + Donut Chart ──────────── -->
      <div class="two-col fade-up fade-up-6">

        <!-- Recent Burials Table -->
        <div class="content-card">
          <div class="card-header">
            <div>
              <h2 class="card-title">
                <div class="card-title-icon icon-bg-blue">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                Recent Burials
              </h2>
              <p class="card-subtitle">Last 5 registered burial records</p>
            </div>
            <a href="burial-records.php" class="btn-view-all">
              View All
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
          </div>

          <div style="overflow-x:auto;">
            <table class="burials-table">
              <thead>
                <tr>
                  <th>Deceased</th>
                  <th>Lot</th>
                  <th>Date of Death</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($recent_burials)): ?>
                  <tr>
                    <td colspan="4">
                      <div class="empty-state">
                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <p>No recent burials found</p>
                      </div>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php
                  $avatarColors = ['#3b82f6','#6366f1','#8b5cf6','#f43f5e','#10b981','#f59e0b','#0ea5e9','#ec4899'];
                  $ci = 0;
                  foreach ($recent_burials as $burial):
                    $initials = '';
                    $parts = explode(' ', trim($burial['full_name']));
                    foreach (array_slice($parts, 0, 2) as $p) $initials .= strtoupper(substr($p,0,1));
                    $color = $avatarColors[$ci % count($avatarColors)];
                    $ci++;
                  ?>
                  <tr>
                    <td>
                      <div style="display:flex;align-items:center;gap:12px;">
                        <div class="avatar-initials" style="background:<?php echo $color; ?>;"><?php echo htmlspecialchars($initials); ?></div>
                        <div>
                          <div class="burial-name"><?php echo htmlspecialchars($burial['full_name']); ?></div>
                          <div class="burial-meta">ID #<?php echo $burial['id']; ?></div>
                        </div>
                      </div>
                    </td>
                    <td>
                      <div class="lot-badge">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        Lot <?php echo htmlspecialchars($burial['lot_number'] ?: 'N/A'); ?>
                      </div>
                      <div class="section-tag"><?php echo htmlspecialchars($burial['section_name'] ?: 'No Section'); ?></div>
                    </td>
                    <td style="color:#64748b;font-size:13px;">
                      <?php echo $burial['date_of_death'] ? date('M j, Y', strtotime($burial['date_of_death'])) : '<span style="color:#cbd5e1">N/A</span>'; ?>
                    </td>
                    <td>
                      <a href="burial-records.php?id=<?php echo $burial['id']; ?>" class="btn-view-row">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        View
                      </a>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div><!-- /recent burials card -->

        <!-- Donut Chart: Lot Occupancy -->
        <div class="content-card">
          <div class="card-header">
            <div>
              <h2 class="card-title">
                <div class="card-title-icon icon-bg-emerald">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 10 10"/></svg>
                </div>
                Lot Occupancy
              </h2>
              <p class="card-subtitle">Vacant vs occupied overview</p>
            </div>
          </div>
          <div class="donut-wrap">
            <div class="donut-canvas-wrap">
              <canvas id="donutChart" width="190" height="190"></canvas>
              <div class="donut-center">
                <div class="donut-center-num"><?php echo $stats['total_lots']; ?></div>
                <div class="donut-center-label">Total Lots</div>
              </div>
            </div>

            <div class="donut-legend">
              <div class="legend-item">
                <div class="legend-num" style="color:#10b981;"><?php echo $stats['available_lots']; ?></div>
                <div class="legend-label"><span class="legend-dot" style="background:#10b981;"></span>Vacant</div>
              </div>
              <div class="legend-item">
                <div class="legend-num" style="color:#f43f5e;"><?php echo $stats['occupied_lots']; ?></div>
                <div class="legend-label"><span class="legend-dot" style="background:#f43f5e;"></span>Occupied</div>
              </div>
            </div>

            <div class="occupancy-bar-wrap">
              <div class="occupancy-bar-track">
                <div class="occupancy-bar-fill-emerald" style="width:<?php echo $available_percent; ?>%;"></div>
                <div class="occupancy-bar-fill-rose"    style="width:<?php echo $occupied_percent; ?>%;"></div>
              </div>
              <div class="occupancy-bar-labels">
                <span><?php echo $available_percent; ?>% Vacant</span>
                <span><?php echo $occupied_percent; ?>% Occupied</span>
              </div>
            </div>
          </div>
        </div><!-- /donut card -->

      </div><!-- /two-col -->

      <!-- ── Full-width: Lots by Block Bar Chart ───────────────── -->
      <div class="content-card bar-chart-card fade-up fade-up-7" style="margin-bottom:32px;">
        <div class="card-header">
          <div>
            <h2 class="card-title">
              <div class="card-title-icon icon-bg-indigo">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 14v4"/><path d="M11 10v8"/><path d="M15 6v12"/><path d="M19 12v6"/></svg>
              </div>
              Lots by Block
            </h2>
            <p class="card-subtitle">Vacant vs occupied breakdown per block</p>
          </div>
        </div>
        <div class="bar-chart-wrap">
          <canvas id="blockBarChart" height="110"></canvas>
        </div>
      </div>

      <?php endif; ?>
    </main>
  </div><!-- /app -->

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script src="../assets/js/app.js"></script>
  <script>
    // ── Count-up animation ────────────────────────────────────
    function animateCount(el, target) {
      let start = 0;
      const duration = 1200;
      const step = target / (duration / 16);
      const timer = setInterval(() => {
        start += step;
        if (start >= target) {
          el.textContent = target.toLocaleString();
          clearInterval(timer);
          return;
        }
        el.textContent = Math.floor(start).toLocaleString();
      }, 16);
    }
    document.querySelectorAll('[data-count]').forEach(el => {
      animateCount(el, parseInt(el.dataset.count));
    });

    // ── Donut Chart: Lot Occupancy ────────────────────────────
    (function() {
      const ctx = document.getElementById('donutChart');
      if (!ctx) return;
      new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: ['Vacant', 'Occupied'],
          datasets: [{
            data: [<?php echo $stats['available_lots']; ?>, <?php echo $stats['occupied_lots']; ?>],
            backgroundColor: ['#10b981', '#f43f5e'],
            hoverBackgroundColor: ['#059669', '#e11d48'],
            borderWidth: 0,
            hoverOffset: 8,
            borderRadius: 4
          }]
        },
        options: {
          cutout: '74%',
          plugins: {
            legend: { display: false },
            tooltip: {
              backgroundColor: '#0f172a',
              titleColor: '#94a3b8',
              bodyColor: '#f1f5f9',
              padding: 12,
              cornerRadius: 10,
              callbacks: {
                label: ctx => `  ${ctx.label}: ${ctx.parsed.toLocaleString()} lots`
              }
            }
          },
          animation: { animateRotate: true, duration: 1000, easing: 'easeInOutQuart' }
        }
      });
    })();

    // ── Horizontal Bar Chart: Lots per Block ─────────────────
    (function() {
      const ctx = document.getElementById('blockBarChart');
      if (!ctx) return;

      <?php
        $blockData = [];
        foreach ($stats['sections'] as $s) {
          $b = $s['block'] ?? 'Unknown';
          if (!isset($blockData[$b])) $blockData[$b] = ['vacant'=>0,'occupied'=>0];
          $blockData[$b]['vacant']   += intval($s['vacant']);
          $blockData[$b]['occupied'] += intval($s['occupied']);
        }
        $blockLabels   = json_encode(array_keys($blockData));
        $blockVacant   = json_encode(array_column(array_values($blockData), 'vacant'));
        $blockOccupied = json_encode(array_column(array_values($blockData), 'occupied'));
      ?>

      new Chart(ctx, {
        type: 'bar',
        data: {
          labels: <?php echo $blockLabels; ?>,
          datasets: [
            {
              label: 'Vacant',
              data: <?php echo $blockVacant; ?>,
              backgroundColor: '#10b981',
              hoverBackgroundColor: '#059669',
              borderRadius: 6,
              barThickness: 16
            },
            {
              label: 'Occupied',
              data: <?php echo $blockOccupied; ?>,
              backgroundColor: '#f43f5e',
              hoverBackgroundColor: '#e11d48',
              borderRadius: 6,
              barThickness: 16
            }
          ]
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                boxWidth: 12,
                borderRadius: 4,
                usePointStyle: true,
                pointStyle: 'circle',
                font: { size: 12, family: 'Inter, system-ui, sans-serif' },
                color: '#64748b',
                padding: 20
              }
            },
            tooltip: {
              backgroundColor: '#0f172a',
              titleColor: '#94a3b8',
              bodyColor: '#f1f5f9',
              padding: 12,
              cornerRadius: 10,
              callbacks: {
                label: ctx => `  ${ctx.dataset.label}: ${ctx.parsed.x.toLocaleString()} lots`
              }
            }
          },
          scales: {
            x: {
              stacked: false,
              grid: { color: '#f1f5f9', drawBorder: false },
              ticks: {
                font: { size: 11, family: 'Inter, system-ui, sans-serif' },
                color: '#94a3b8'
              },
              border: { display: false }
            },
            y: {
              grid: { display: false },
              ticks: {
                font: { size: 12, weight: '600', family: 'Inter, system-ui, sans-serif' },
                color: '#334155'
              },
              border: { display: false }
            }
          },
          animation: { duration: 900, easing: 'easeInOutQuart' }
        }
      });
    })();
  </script>
</body>
</html>
</html>
