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
            SELECT dr.*,
                   cl.lot_number, cl.map_x, cl.map_y, cl.map_width, cl.map_height,
                   s.name AS section_name,
                   b.name AS block_name
            FROM deceased_records dr
            LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id
            LEFT JOIN sections s ON cl.section_id = s.id
            LEFT JOIN blocks b ON s.block_id = b.id
            WHERE dr.is_archived = 0
            ORDER BY dr.created_at DESC
            LIMIT 5
        ");
        $recent_burials = $stmt->fetchAll();

        // Fetch primary image for each recent burial
        $imagesTableExists = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='burial_record_images'")->fetch() !== false;
        foreach ($recent_burials as &$rb) {
            $rb['primary_image'] = null;
            if ($imagesTableExists && $rb['id']) {
                $imgStmt = $conn->prepare("SELECT image_path FROM burial_record_images WHERE burial_record_id=:id ORDER BY is_primary DESC, display_order ASC LIMIT 1");
                $imgStmt->execute([':id' => $rb['id']]);
                $img = $imgStmt->fetchColumn();
                if ($img) $rb['primary_image'] = $img;
            }
        }
        unset($rb);

        // Map image existence check
        $mapImageExists = file_exists(__DIR__ . '/../assets/images/cemetery.jpg');

        // Fetch all lots with map coordinates for the inline map overlay
        $allLotsStmt = $conn->query("
            SELECT cl.id, cl.lot_number, cl.map_x, cl.map_y, cl.map_width, cl.map_height,
                   CASE
                     WHEN dr_c.cnt > 0 THEN 'Occupied'
                     WHEN ll_c.cnt  > 0 THEN 'Occupied'
                     ELSE cl.status
                   END AS actual_status
            FROM cemetery_lots cl
            LEFT JOIN (SELECT lot_id, COUNT(*) AS cnt FROM deceased_records WHERE is_archived=0 GROUP BY lot_id) dr_c ON dr_c.lot_id = cl.id
            LEFT JOIN (SELECT lot_id, COUNT(*) AS cnt FROM lot_layers WHERE is_occupied=1 GROUP BY lot_id) ll_c ON ll_c.lot_id = cl.id
            WHERE cl.map_x IS NOT NULL AND cl.map_y IS NOT NULL
        ");
        $allMapLots = $allLotsStmt->fetchAll(PDO::FETCH_ASSOC);

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

    /* ── Burial Cards ────────────────────────────────────────── */
    .burial-cards-list {
      display: flex;
      flex-direction: column;
      gap: 0;
    }
    .burial-card-row {
      display: flex;
      align-items: stretch;
      gap: 0;
      padding: 16px 22px;
      border-bottom: 1px solid #f1f5f9;
      cursor: pointer;
      transition: background 0.15s ease;
      position: relative;
    }
    .burial-card-row:last-child { border-bottom: none; }
    .burial-card-row:hover { background: #f8faff; }
    .burial-card-row:hover .bcr-arrow { opacity: 1; transform: translateX(0); }

    /* Photo / avatar */
    .bcr-photo {
      width: 52px; height: 52px;
      border-radius: 12px;
      flex-shrink: 0;
      overflow: hidden;
      position: relative;
      margin-right: 14px;
      align-self: center;
    }
    .bcr-photo img {
      width: 100%; height: 100%;
      object-fit: cover;
      display: block;
    }
    .bcr-photo-initials {
      width: 100%; height: 100%;
      display: flex; align-items: center; justify-content: center;
      font-size: 18px; font-weight: 800; color: #fff;
      letter-spacing: -0.02em;
    }

    /* Info block */
    .bcr-info { flex: 1; min-width: 0; }
    .bcr-name {
      font-size: 14px; font-weight: 700; color: #0f172a;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      margin-bottom: 3px;
    }
    .bcr-meta {
      display: flex; flex-wrap: wrap; gap: 6px; align-items: center;
      margin-bottom: 6px;
    }
    .bcr-tag {
      display: inline-flex; align-items: center; gap: 3px;
      font-size: 11px; font-weight: 600;
      padding: 2px 8px; border-radius: 5px;
    }
    .bcr-tag-lot  { background: #eff6ff; color: #3b82f6; }
    .bcr-tag-sec  { background: #f5f3ff; color: #7c3aed; }
    .bcr-tag-blk  { background: #ecfdf5; color: #059669; }
    .bcr-dates {
      display: flex; gap: 12px; flex-wrap: wrap;
    }
    .bcr-date-item {
      display: flex; align-items: center; gap: 4px;
      font-size: 11px; color: #94a3b8;
    }
    .bcr-date-item strong { color: #475569; font-weight: 600; }

    /* Map pin thumbnail */
    .bcr-map-thumb {
      width: 64px; height: 52px;
      border-radius: 10px;
      overflow: hidden;
      flex-shrink: 0;
      margin-left: 12px;
      position: relative;
      align-self: center;
      border: 1.5px solid #e2e8f0;
      background: #f1f5f9;
    }
    .bcr-map-thumb canvas {
      display: block;
      width: 100%; height: 100%;
    }
    .bcr-map-no-coords {
      width: 100%; height: 100%;
      display: flex; align-items: center; justify-content: center;
      color: #cbd5e1;
    }
    .bcr-map-pin {
      position: absolute;
      top: 50%; left: 50%;
      transform: translate(-50%, -100%);
      font-size: 14px;
      filter: drop-shadow(0 1px 2px rgba(0,0,0,0.4));
      pointer-events: none;
    }

    /* Arrow */
    .bcr-arrow {
      position: absolute; right: 18px; top: 50%;
      transform: translateX(4px) translateY(-50%);
      opacity: 0;
      transition: all 0.18s ease;
      color: #94a3b8;
    }

    /* ── Detail Slide-over Panel ─────────────────────────────── */
    .burial-detail-overlay {
      position: fixed; inset: 0; z-index: 3000;
      background: rgba(15,23,42,0.55);
      backdrop-filter: blur(4px);
      display: none; align-items: flex-end; justify-content: flex-end;
    }
    .burial-detail-overlay.open { display: flex; }

    .burial-detail-panel {
      width: 420px; max-width: 100vw;
      height: 100vh;
      background: #fff;
      display: flex; flex-direction: column;
      box-shadow: -8px 0 40px rgba(0,0,0,0.15);
      animation: slideInRight 0.3s cubic-bezier(0.4,0,0.2,1);
      overflow: hidden;
    }
    @keyframes slideInRight {
      from { transform: translateX(100%); opacity: 0; }
      to   { transform: translateX(0);    opacity: 1; }
    }

    .bdp-header {
      padding: 20px 22px 16px;
      border-bottom: 1px solid #f1f5f9;
      display: flex; align-items: flex-start; gap: 14px;
      flex-shrink: 0;
    }
    .bdp-photo {
      width: 60px; height: 60px; border-radius: 14px;
      overflow: hidden; flex-shrink: 0;
    }
    .bdp-photo img { width:100%; height:100%; object-fit:cover; }
    .bdp-photo-initials {
      width:100%; height:100%;
      display:flex; align-items:center; justify-content:center;
      font-size:22px; font-weight:800; color:#fff;
    }
    .bdp-header-info { flex: 1; min-width: 0; }
    .bdp-name {
      font-size: 17px; font-weight: 800; color: #0f172a;
      margin: 0 0 4px 0; line-height: 1.2;
    }
    .bdp-id { font-size: 11px; color: #94a3b8; font-weight: 500; }
    .bdp-close {
      background: #f1f5f9; border: none; border-radius: 8px;
      width: 32px; height: 32px; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      color: #64748b; flex-shrink: 0;
      transition: background 0.15s;
    }
    .bdp-close:hover { background: #e2e8f0; color: #0f172a; }

    .bdp-body {
      flex: 1; overflow-y: auto; padding: 20px 22px;
      scrollbar-width: thin; scrollbar-color: #e2e8f0 transparent;
    }
    .bdp-body::-webkit-scrollbar { width: 5px; }
    .bdp-body::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 3px; }

    .bdp-section-title {
      font-size: 10px; font-weight: 700; color: #94a3b8;
      text-transform: uppercase; letter-spacing: 0.08em;
      margin: 0 0 10px 0;
    }
    .bdp-info-grid {
      display: grid; grid-template-columns: 1fr 1fr;
      gap: 10px; margin-bottom: 20px;
    }
    .bdp-info-item {
      background: #f8fafc; border-radius: 10px;
      padding: 10px 12px;
    }
    .bdp-info-label { font-size: 10px; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 3px; }
    .bdp-info-value { font-size: 13px; color: #0f172a; font-weight: 600; }
    .bdp-info-item.full { grid-column: 1 / -1; }

    /* Map in panel */
    .bdp-map-wrap {
      border-radius: 12px; overflow: hidden;
      border: 1.5px solid #e2e8f0;
      margin-bottom: 20px;
      position: relative;
      background: #f1f5f9;
      height: 180px;
      cursor: pointer;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .bdp-map-wrap:hover {
      border-color: #10b981;
      box-shadow: 0 0 0 3px rgba(16,185,129,0.15);
    }
    .bdp-map-wrap:hover .bdp-map-hover-hint { opacity: 1; }
    .bdp-map-wrap canvas { display: block; width: 100%; height: 100%; }
    .bdp-map-wrap img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .bdp-map-pin-overlay {
      position: absolute; inset: 0;
      display: flex; align-items: center; justify-content: center;
      pointer-events: none;
    }
    .bdp-map-pin-icon {
      font-size: 28px;
      filter: drop-shadow(0 2px 6px rgba(0,0,0,0.5));
      animation: pinBounce2 2s ease-in-out infinite;
    }
    @keyframes pinBounce2 {
      0%,100% { transform: translateY(0); }
      50%      { transform: translateY(-6px); }
    }
    .bdp-map-label {
      position: absolute; bottom: 8px; left: 8px;
      background: rgba(15,23,42,0.75); color: #fff;
      font-size: 11px; font-weight: 700;
      padding: 3px 8px; border-radius: 6px;
      backdrop-filter: blur(4px);
    }
    .bdp-map-hover-hint {
      position: absolute; bottom: 8px; right: 8px;
      background: rgba(16,185,129,0.92); color: #fff;
      font-size: 11px; font-weight: 700;
      padding: 4px 10px; border-radius: 6px;
      display: flex; align-items: center; gap: 5px;
      opacity: 0; transition: opacity 0.2s;
      pointer-events: none;
    }
    .bdp-map-no-loc {
      height: 100%; display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      color: #94a3b8; gap: 6px;
    }
    .bdp-map-no-loc p { font-size: 12px; margin: 0; }

    /* Images in panel */
    .bdp-images-grid {
      display: grid; grid-template-columns: repeat(3, 1fr);
      gap: 8px; margin-bottom: 20px;
    }
    .bdp-img-thumb {
      aspect-ratio: 1; border-radius: 8px; overflow: hidden;
      cursor: pointer; position: relative;
    }
    .bdp-img-thumb img {
      width: 100%; height: 100%; object-fit: cover;
      transition: transform 0.2s ease;
    }
    .bdp-img-thumb:hover img { transform: scale(1.06); }
    .bdp-no-images {
      background: #f8fafc; border-radius: 10px;
      padding: 20px; text-align: center;
      color: #94a3b8; font-size: 12px;
      margin-bottom: 20px;
    }

    .bdp-footer {
      padding: 14px 22px;
      border-top: 1px solid #f1f5f9;
      flex-shrink: 0;
      display: flex;
      gap: 10px;
    }
    .bdp-view-btn, .bdp-map-btn {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      flex: 1; padding: 12px;
      color: #fff; border: none; border-radius: 12px;
      font-size: 14px; font-weight: 700; cursor: pointer;
      text-decoration: none;
      transition: opacity 0.2s, transform 0.2s;
    }
    .bdp-view-btn {
      background: linear-gradient(135deg, #3b82f6, #6366f1);
    }
    .bdp-view-btn:hover { opacity: 0.92; transform: translateY(-1px); }
    .bdp-map-btn {
      background: linear-gradient(135deg, #10b981, #059669);
    }
    .bdp-map-btn:hover { opacity: 0.92; transform: translateY(-1px); }

    /* Lightbox */
    .bdp-lightbox {
      position: fixed; inset: 0; z-index: 5000;
      background: rgba(0,0,0,0.92);
      display: none; align-items: center; justify-content: center;
    }
    .bdp-lightbox.open { display: flex; }
    .bdp-lightbox img {
      max-width: 90vw; max-height: 90vh;
      border-radius: 8px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.5);
    }
    .bdp-lightbox-close {
      position: absolute; top: 20px; right: 20px;
      background: rgba(255,255,255,0.15); border: none;
      color: #fff; font-size: 24px; width: 44px; height: 44px;
      border-radius: 50%; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      transition: background 0.2s;
    }
    .bdp-lightbox-close:hover { background: rgba(255,255,255,0.25); }

    /* ── Inline Map Modal ────────────────────────────────────── */
    .inline-map-modal {
      position: fixed; inset: 0; z-index: 4000;
      background: rgba(15,23,42,0.75);
      backdrop-filter: blur(6px);
      display: none; align-items: center; justify-content: center;
      padding: 20px;
    }
    .inline-map-modal.open { display: flex; }
    .inline-map-container {
      width: 95vw; max-width: 1200px;
      height: 85vh; max-height: 800px;
      background: #fff;
      border-radius: 20px;
      box-shadow: 0 25px 80px rgba(0,0,0,0.3);
      display: flex; flex-direction: column;
      overflow: hidden;
      animation: modalScaleIn 0.3s cubic-bezier(0.34,1.56,0.64,1);
    }
    @keyframes modalScaleIn {
      from { transform: scale(0.92); opacity: 0; }
      to   { transform: scale(1); opacity: 1; }
    }
    .inline-map-header {
      padding: 18px 24px;
      border-bottom: 1px solid #f1f5f9;
      display: flex; justify-content: space-between; align-items: center;
      flex-shrink: 0;
    }
    .inline-map-title {
      font-size: 17px; font-weight: 800; color: #0f172a;
      margin: 0; display: flex; align-items: center; gap: 10px;
    }
    .inline-map-close {
      background: #f1f5f9; border: none; border-radius: 10px;
      width: 36px; height: 36px; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      color: #64748b; transition: all 0.15s;
    }
    .inline-map-close:hover { background: #e2e8f0; color: #0f172a; }
    .inline-map-body {
      flex: 1; position: relative; overflow: hidden;
      background: #f8fafc;
    }
    .inline-map-canvas-wrap {
      position: absolute; inset: 0;
      cursor: grab;
    }
    .inline-map-canvas-wrap.grabbing { cursor: grabbing; }
    .inline-map-canvas {
      position: relative;
      transform-origin: 0 0;
    }
    .inline-map-img {
      display: block;
      width: 100%;
      user-select: none;
      -webkit-user-drag: none;
    }
    .inline-lot-marker {
      position: absolute;
      border: 1px solid;
      cursor: pointer;
      transition: all 0.2s;
    }
    .inline-lot-marker.vacant { border-color: #22c55e; background: rgba(34,197,94,0.2); }
    .inline-lot-marker.occupied { border-color: #f97316; background: rgba(249,115,22,0.2); }
    .inline-lot-marker.highlighted {
      border: 3px solid #ef4444 !important;
      background: rgba(239,68,68,0.25) !important;
      z-index: 100 !important;
      box-shadow: 0 0 0 4px rgba(239,68,68,0.2), 0 0 20px rgba(239,68,68,0.4);
      animation: pulse-highlight 2s infinite;
    }
    @keyframes pulse-highlight {
      0%, 100% { box-shadow: 0 0 0 4px rgba(239,68,68,0.2), 0 0 20px rgba(239,68,68,0.4); }
      50%      { box-shadow: 0 0 0 8px rgba(239,68,68,0.1), 0 0 30px rgba(239,68,68,0.6); }
    }
    .inline-lot-marker.highlighted::after {
      content: '📍';
      position: absolute;
      top: 50%; left: 50%;
      transform: translate(-50%, -120%);
      font-size: 24px;
      filter: drop-shadow(0 2px 6px rgba(0,0,0,0.4));
      animation: pinFloat 2s ease-in-out infinite;
      pointer-events: none;
    }
    @keyframes pinFloat {
      0%, 100% { transform: translate(-50%, -120%); }
      50%      { transform: translate(-50%, -140%); }
    }
    .inline-map-controls {
      position: absolute; bottom: 20px; right: 20px;
      display: flex; flex-direction: column; gap: 8px;
      z-index: 10;
    }
    .inline-map-btn {
      width: 40px; height: 40px;
      background: #fff; border: 1px solid #e2e8f0;
      border-radius: 10px; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      color: #475569; font-size: 18px; font-weight: 700;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      transition: all 0.2s;
    }
    .inline-map-btn:hover { background: #f8fafc; border-color: #cbd5e1; transform: scale(1.05); }
    .inline-map-legend {
      position: absolute; top: 20px; left: 20px;
      background: rgba(255,255,255,0.95);
      backdrop-filter: blur(8px);
      border-radius: 12px;
      padding: 12px 16px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.1);
      display: flex; gap: 16px;
      font-size: 12px; font-weight: 600;
    }
    .inline-legend-item {
      display: flex; align-items: center; gap: 6px;
    }
    .inline-legend-box {
      width: 16px; height: 16px;
      border-radius: 4px;
      border: 2px solid;
    }
    .inline-legend-box.vacant { border-color: #22c55e; background: rgba(34,197,94,0.2); }
    .inline-legend-box.occupied { border-color: #f97316; background: rgba(249,115,22,0.2); }

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

      <!-- ── Two-column: Recent Burials Cards + Donut Chart ──────── -->
      <div class="two-col fade-up fade-up-6">

        <!-- Recent Burials Cards -->
        <div class="content-card">
          <div class="card-header">
            <div>
              <h2 class="card-title">
                <div class="card-title-icon icon-bg-blue">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                Recent Burials
              </h2>
              <p class="card-subtitle">Last 5 registered — click any row for full details</p>
            </div>
            <a href="burial-records.php" class="btn-view-all">
              View All
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
          </div>

          <?php if (empty($recent_burials)): ?>
            <div class="empty-state">
              <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              <p>No recent burials found</p>
            </div>
          <?php else: ?>
            <?php
            $avatarColors = ['#3b82f6','#6366f1','#8b5cf6','#f43f5e','#10b981','#f59e0b','#0ea5e9','#ec4899'];
            $ci = 0;
            // Build JS data array for the panel
            $burialJsData = [];
            foreach ($recent_burials as $rb) {
              $initials = '';
              foreach (array_slice(explode(' ', trim($rb['full_name'])), 0, 2) as $p) $initials .= strtoupper(substr($p,0,1));
              $burialJsData[] = [
                'id'           => $rb['id'],
                'full_name'    => $rb['full_name'],
                'initials'     => $initials,
                'color'        => $avatarColors[$ci % count($avatarColors)],
                'lot_number'   => $rb['lot_number'] ?? null,
                'section_name' => $rb['section_name'] ?? null,
                'block_name'   => $rb['block_name'] ?? null,
                'date_of_birth'  => $rb['date_of_birth'] ?? null,
                'date_of_death'  => $rb['date_of_death'] ?? null,
                'date_of_burial' => $rb['date_of_burial'] ?? null,
                'age'            => $rb['age'] ?? null,
                'cause_of_death' => $rb['cause_of_death'] ?? null,
                'next_of_kin'    => $rb['next_of_kin'] ?? null,
                'next_of_kin_contact' => $rb['next_of_kin_contact'] ?? null,
                'remarks'        => $rb['remarks'] ?? null,
                'map_x'          => $rb['map_x'] ?? null,
                'map_y'          => $rb['map_y'] ?? null,
                'map_width'      => $rb['map_width'] ?? null,
                'map_height'     => $rb['map_height'] ?? null,
                'primary_image'  => $rb['primary_image'] ?? null,
                'map_image_exists' => !empty($mapImageExists),
                'lot_id'         => $rb['lot_id'] ?? null,
              ];
              $ci++;
            }
            ?>
            <div class="burial-cards-list" id="burialCardsList">
              <?php foreach ($burialJsData as $idx => $rb): ?>
              <div class="burial-card-row" onclick="openBurialPanel(<?php echo $idx; ?>)" data-idx="<?php echo $idx; ?>">

                <!-- Photo / Avatar -->
                <div class="bcr-photo">
                  <?php if ($rb['primary_image']): ?>
                    <img src="../<?php echo htmlspecialchars($rb['primary_image']); ?>" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                    <div class="bcr-photo-initials" style="background:<?php echo $rb['color']; ?>;display:none;"><?php echo htmlspecialchars($rb['initials']); ?></div>
                  <?php else: ?>
                    <div class="bcr-photo-initials" style="background:<?php echo $rb['color']; ?>;"><?php echo htmlspecialchars($rb['initials']); ?></div>
                  <?php endif; ?>
                </div>

                <!-- Info -->
                <div class="bcr-info">
                  <div class="bcr-name"><?php echo htmlspecialchars($rb['full_name']); ?></div>
                  <div class="bcr-meta">
                    <?php if ($rb['lot_number']): ?>
                      <span class="bcr-tag bcr-tag-lot">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        Lot <?php echo htmlspecialchars($rb['lot_number']); ?>
                      </span>
                    <?php endif; ?>
                    <?php if ($rb['section_name']): ?>
                      <span class="bcr-tag bcr-tag-sec"><?php echo htmlspecialchars($rb['section_name']); ?></span>
                    <?php endif; ?>
                    <?php if ($rb['block_name']): ?>
                      <span class="bcr-tag bcr-tag-blk"><?php echo htmlspecialchars($rb['block_name']); ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="bcr-dates">
                    <?php if ($rb['date_of_death']): ?>
                      <div class="bcr-date-item">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <strong>Died:</strong> <?php echo date('M j, Y', strtotime($rb['date_of_death'])); ?>
                      </div>
                    <?php endif; ?>
                    <?php if ($rb['age']): ?>
                      <div class="bcr-date-item">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <strong>Age:</strong> <?php echo htmlspecialchars($rb['age']); ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Mini map thumbnail -->
                <div class="bcr-map-thumb">
                  <?php if ($rb['map_x'] !== null && $rb['map_image_exists']): ?>
                    <canvas class="bcr-map-canvas"
                      data-mx="<?php echo $rb['map_x']; ?>"
                      data-my="<?php echo $rb['map_y']; ?>"
                      data-mw="<?php echo $rb['map_width']; ?>"
                      data-mh="<?php echo $rb['map_height']; ?>"
                      width="64" height="52"></canvas>
                    <div class="bcr-map-pin">📍</div>
                  <?php else: ?>
                    <div class="bcr-map-no-coords">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    </div>
                  <?php endif; ?>
                </div>

                <!-- Arrow -->
                <div class="bcr-arrow">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </div>

              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div><!-- /burial cards -->

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

      <!-- ── Burial Detail Slide-over Panel ────────────────────── -->
      <div class="burial-detail-overlay" id="burialDetailOverlay" onclick="closeBurialPanel(event)">
        <div class="burial-detail-panel" id="burialDetailPanel">

          <div class="bdp-header">
            <div class="bdp-photo" id="bdpPhoto"></div>
            <div class="bdp-header-info">
              <h3 class="bdp-name" id="bdpName">—</h3>
              <div class="bdp-id" id="bdpId"></div>
            </div>
            <button class="bdp-close" onclick="closeBurialPanelDirect()">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>

          <div class="bdp-body" id="bdpBody">
            <!-- Filled by JS -->
          </div>

          <div class="bdp-footer">
            <button class="bdp-map-btn" id="bdpMapBtn">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z"/><path d="M9 4v14"/><path d="M15 6v14"/></svg>
              View on Map
            </button>
            <a href="#" class="bdp-view-btn" id="bdpViewBtn" target="_blank">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              Open Full Record
            </a>
          </div>
        </div>
      </div>

      <!-- Inline Map Modal -->
      <div class="inline-map-modal" id="inlineMapModal" onclick="closeInlineMap(event)">
        <div class="inline-map-container" onclick="event.stopPropagation()">
          <div class="inline-map-header">
            <h3 class="inline-map-title" id="inlineMapTitle">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5"><path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z"/><path d="M9 4v14"/><path d="M15 6v14"/></svg>
              Cemetery Map
            </h3>
            <button class="inline-map-close" onclick="closeInlineMapDirect()">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <div class="inline-map-body" id="inlineMapBody">
            <div class="inline-map-canvas-wrap" id="inlineMapWrap">
              <div class="inline-map-canvas" id="inlineMapCanvas">
                <img class="inline-map-img" id="inlineMapImg" src="../assets/images/cemetery.jpg" alt="Cemetery Map" draggable="false">
                <!-- lot markers injected by JS -->
              </div>
            </div>
            <!-- Legend -->
            <div class="inline-map-legend">
              <div class="inline-legend-item">
                <div class="inline-legend-box vacant"></div>
                <span style="color:#166534;">Vacant</span>
              </div>
              <div class="inline-legend-item">
                <div class="inline-legend-box occupied"></div>
                <span style="color:#9a3412;">Occupied</span>
              </div>
              <div class="inline-legend-item">
                <div style="width:16px;height:16px;border-radius:4px;border:3px solid #ef4444;background:rgba(239,68,68,0.2);"></div>
                <span style="color:#ef4444;">Selected Lot</span>
              </div>
            </div>
            <!-- Controls -->
            <div class="inline-map-controls">
              <button class="inline-map-btn" onclick="inlineMapZoom(1.25)" title="Zoom in">+</button>
              <button class="inline-map-btn" onclick="inlineMapZoom(0.8)" title="Zoom out">−</button>
              <button class="inline-map-btn" onclick="inlineMapReset()" title="Reset view" style="font-size:13px;">⌂</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Lightbox -->
      <div class="bdp-lightbox" id="bdpLightbox" onclick="closeLightbox()">
        <button class="bdp-lightbox-close" onclick="closeLightbox()">✕</button>
        <img id="bdpLightboxImg" src="" alt="">
      </div>

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
    // ── Burial data from PHP ──────────────────────────────────
    const BURIALS = <?php echo json_encode(array_values($burialJsData ?? []), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const MAP_IMG_SRC = '../assets/images/cemetery.jpg';

    // ── Count-up animation ────────────────────────────────────
    function animateCount(el, target) {
      let start = 0;
      const duration = 1200;
      const step = target / (duration / 16);
      const timer = setInterval(() => {
        start += step;
        if (start >= target) { el.textContent = target.toLocaleString(); clearInterval(timer); return; }
        el.textContent = Math.floor(start).toLocaleString();
      }, 16);
    }
    document.querySelectorAll('[data-count]').forEach(el => animateCount(el, parseInt(el.dataset.count)));

    // ── Mini map thumbnails ───────────────────────────────────
    (function() {
      const mapImg = new Image();
      mapImg.src = MAP_IMG_SRC;
      mapImg.onload = function() {
        document.querySelectorAll('.bcr-map-canvas').forEach(canvas => {
          const mx = parseFloat(canvas.dataset.mx);
          const my = parseFloat(canvas.dataset.my);
          const mw = parseFloat(canvas.dataset.mw) || 20;
          const mh = parseFloat(canvas.dataset.mh) || 20;
          const ctx = canvas.getContext('2d');
          const W = canvas.width, H = canvas.height;

          // Zoom: show a region around the lot (3× the lot size, centered)
          const zoom = 3.5;
          const srcW = mw * zoom, srcH = mh * zoom;
          const srcX = mx + mw/2 - srcW/2;
          const srcY = my + mh/2 - srcH/2;

          // Scale to natural image dimensions
          const scaleX = mapImg.naturalWidth  / 100;
          const scaleY = mapImg.naturalHeight / 100;

          ctx.drawImage(mapImg,
            srcX * scaleX, srcY * scaleY, srcW * scaleX, srcH * scaleY,
            0, 0, W, H
          );

          // Draw lot highlight
          const lotX = (mx - srcX) / srcW * W;
          const lotY = (my - srcY) / srcH * H;
          const lotW = mw / srcW * W;
          const lotH = mh / srcH * H;
          ctx.strokeStyle = '#f43f5e';
          ctx.lineWidth = 2;
          ctx.strokeRect(lotX, lotY, lotW, lotH);
          ctx.fillStyle = 'rgba(244,63,94,0.2)';
          ctx.fillRect(lotX, lotY, lotW, lotH);
        });
      };
    })();

    // ── Detail Panel ──────────────────────────────────────────
    let panelMapImg = null;

    function fmt(d) {
      if (!d) return '<span style="color:#cbd5e1">—</span>';
      try { return new Date(d).toLocaleDateString('en-US', {year:'numeric',month:'short',day:'numeric'}); }
      catch(e) { return d; }
    }

    function openBurialPanel(idx) {
      const b = BURIALS[idx];
      if (!b) return;

      // Header
      const photoEl = document.getElementById('bdpPhoto');
      if (b.primary_image) {
        photoEl.innerHTML = `<img src="../${b.primary_image}" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
          <div class="bdp-photo-initials" style="background:${b.color};display:none;">${b.initials}</div>`;
      } else {
        photoEl.innerHTML = `<div class="bdp-photo-initials" style="background:${b.color};">${b.initials}</div>`;
      }
      document.getElementById('bdpName').textContent = b.full_name;
      document.getElementById('bdpId').textContent   = `Record ID #${b.id}`;
      document.getElementById('bdpViewBtn').href     = `burial-records.php?id=${b.id}`;

      // Map button — only show if lot has coordinates
      const mapBtn = document.getElementById('bdpMapBtn');
      if (b.lot_id && b.map_x !== null) {
        mapBtn.onclick = () => openInlineMap(b.lot_id, null);
        mapBtn.style.display = 'flex';
      } else {
        mapBtn.style.display = 'none';
      }

      // Body
      let html = '';

      // Location info
      html += `<p class="bdp-section-title">📍 Location</p>`;
      html += `<div class="bdp-info-grid">`;
      html += `<div class="bdp-info-item"><div class="bdp-info-label">Lot Number</div><div class="bdp-info-value">${b.lot_number || '—'}</div></div>`;
      html += `<div class="bdp-info-item"><div class="bdp-info-label">Section</div><div class="bdp-info-value">${b.section_name || '—'}</div></div>`;
      html += `<div class="bdp-info-item"><div class="bdp-info-label">Block</div><div class="bdp-info-value">${b.block_name || '—'}</div></div>`;
      html += `<div class="bdp-info-item"><div class="bdp-info-label">Layer</div><div class="bdp-info-value">${b.layer || '—'}</div></div>`;
      html += `</div>`;

      // Map
      html += `<p class="bdp-section-title">🗺️ Map Location</p>`;
      if (b.map_x !== null && b.map_image_exists) {
        html += `<div class="bdp-map-wrap" onclick="openInlineMap(${b.lot_id}, event)" title="Click to expand map">
          <canvas id="bdpMapCanvas" width="376" height="180"></canvas>
          <div class="bdp-map-pin-overlay"><span class="bdp-map-pin-icon">📍</span></div>
          <div class="bdp-map-label">Lot ${b.lot_number || ''} · ${b.section_name || ''}</div>
          <div class="bdp-map-hover-hint">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z"/><path d="M9 4v14"/><path d="M15 6v14"/></svg>
            Expand Map
          </div>
        </div>`;
      } else {
        html += `<div class="bdp-map-wrap" style="cursor:default;">
          <div class="bdp-map-no-loc">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <p>No map coordinates set for this lot</p>
          </div>
        </div>`;
      }

      // Vital info
      html += `<p class="bdp-section-title">👤 Vital Information</p>`;
      html += `<div class="bdp-info-grid">`;
      html += `<div class="bdp-info-item"><div class="bdp-info-label">Date of Birth</div><div class="bdp-info-value">${fmt(b.date_of_birth)}</div></div>`;
      html += `<div class="bdp-info-item"><div class="bdp-info-label">Date of Death</div><div class="bdp-info-value">${fmt(b.date_of_death)}</div></div>`;
      html += `<div class="bdp-info-item"><div class="bdp-info-label">Date of Burial</div><div class="bdp-info-value">${fmt(b.date_of_burial)}</div></div>`;
      html += `<div class="bdp-info-item"><div class="bdp-info-label">Age</div><div class="bdp-info-value">${b.age || '—'}</div></div>`;
      if (b.cause_of_death) html += `<div class="bdp-info-item full"><div class="bdp-info-label">Cause of Death</div><div class="bdp-info-value">${b.cause_of_death}</div></div>`;
      if (b.next_of_kin)    html += `<div class="bdp-info-item"><div class="bdp-info-label">Next of Kin</div><div class="bdp-info-value">${b.next_of_kin}</div></div>`;
      if (b.next_of_kin_contact) html += `<div class="bdp-info-item"><div class="bdp-info-label">Contact</div><div class="bdp-info-value">${b.next_of_kin_contact}</div></div>`;
      if (b.remarks)        html += `<div class="bdp-info-item full"><div class="bdp-info-label">Remarks</div><div class="bdp-info-value">${b.remarks}</div></div>`;
      html += `</div>`;

      // Images — fetch via API
      html += `<p class="bdp-section-title">🖼️ Photos</p>`;
      html += `<div id="bdpImagesArea"><div class="bdp-no-images" style="color:#94a3b8;font-size:12px;">Loading photos…</div></div>`;

      document.getElementById('bdpBody').innerHTML = html;

      // Open overlay
      document.getElementById('burialDetailOverlay').classList.add('open');
      document.body.style.overflow = 'hidden';

      // Draw map canvas
      if (b.map_x !== null && b.map_image_exists) {
        drawPanelMap(b);
      }

      // Load images
      fetch(`../api/burial_images.php?burial_record_id=${b.id}`)
        .then(r => r.json())
        .then(res => {
          const area = document.getElementById('bdpImagesArea');
          if (!area) return;
          if (res.success && res.data && res.data.length > 0) {
            let imgHtml = '<div class="bdp-images-grid">';
            res.data.forEach(img => {
              imgHtml += `<div class="bdp-img-thumb" onclick="openLightbox('../${img.image_path}')">
                <img src="../${img.image_path}" alt="${img.image_caption || ''}" loading="lazy">
              </div>`;
            });
            imgHtml += '</div>';
            area.innerHTML = imgHtml;
          } else {
            area.innerHTML = '<div class="bdp-no-images">No photos uploaded for this record.</div>';
          }
        })
        .catch(() => {
          const area = document.getElementById('bdpImagesArea');
          if (area) area.innerHTML = '<div class="bdp-no-images">Could not load photos.</div>';
        });
    }

    function drawPanelMap(b) {
      const canvas = document.getElementById('bdpMapCanvas');
      if (!canvas) return;
      const W = canvas.width, H = canvas.height;
      const ctx = canvas.getContext('2d');

      const doRender = (img) => {
        const mx = parseFloat(b.map_x), my = parseFloat(b.map_y);
        const mw = parseFloat(b.map_width) || 20, mh = parseFloat(b.map_height) || 20;
        const zoom = 4;
        const srcW = mw * zoom, srcH = mh * zoom;
        const srcX = mx + mw/2 - srcW/2;
        const srcY = my + mh/2 - srcH/2;
        const scaleX = img.naturalWidth  / 100;
        const scaleY = img.naturalHeight / 100;

        ctx.drawImage(img, srcX*scaleX, srcY*scaleY, srcW*scaleX, srcH*scaleY, 0, 0, W, H);

        // Lot highlight
        const lotX = (mx - srcX) / srcW * W;
        const lotY = (my - srcY) / srcH * H;
        const lotW = mw / srcW * W;
        const lotH = mh / srcH * H;

        // Glow
        ctx.shadowColor = '#f43f5e';
        ctx.shadowBlur  = 12;
        ctx.strokeStyle = '#f43f5e';
        ctx.lineWidth   = 3;
        ctx.strokeRect(lotX, lotY, lotW, lotH);
        ctx.shadowBlur  = 0;
        ctx.fillStyle   = 'rgba(244,63,94,0.25)';
        ctx.fillRect(lotX, lotY, lotW, lotH);
      };

      if (panelMapImg && panelMapImg.complete) {
        doRender(panelMapImg);
      } else {
        panelMapImg = new Image();
        panelMapImg.src = MAP_IMG_SRC;
        panelMapImg.onload = () => doRender(panelMapImg);
      }
    }

    function closeBurialPanel(e) {
      if (e.target === document.getElementById('burialDetailOverlay')) closeBurialPanelDirect();
    }
    function closeBurialPanelDirect() {
      document.getElementById('burialDetailOverlay').classList.remove('open');
      document.body.style.overflow = '';
    }

    // ── Lightbox ──────────────────────────────────────────────
    function openLightbox(src) {
      document.getElementById('bdpLightboxImg').src = src;
      document.getElementById('bdpLightbox').classList.add('open');
    }
    function closeLightbox() {
      document.getElementById('bdpLightbox').classList.remove('open');
    }
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') { closeInlineMapDirect(); closeBurialPanelDirect(); closeLightbox(); }
    });

    // ── Inline Map ────────────────────────────────────────────
    const ALL_LOTS = <?php echo json_encode(array_values($allMapLots ?? []), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    let imZoom = 1, imPanX = 0, imPanY = 0;
    let imDragging = false, imDragStartX = 0, imDragStartY = 0, imPanStartX = 0, imPanStartY = 0;
    let imHighlightLotId = null;
    let imMarkersBuilt = false;

    function openInlineMap(lotId, e) {
      e && e.stopPropagation();
      imHighlightLotId = lotId;

      // Set title
      const b = BURIALS.find(x => x.lot_id == lotId);
      const title = b ? `Lot ${b.lot_number || ''} — ${b.section_name || ''}` : 'Cemetery Map';
      document.getElementById('inlineMapTitle').innerHTML = `
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5"><path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z"/><path d="M9 4v14"/><path d="M15 6v14"/></svg>
        ${title}`;

      // Build markers once
      if (!imMarkersBuilt) buildInlineMarkers();

      // Highlight the target lot
      document.querySelectorAll('.inline-lot-marker').forEach(m => {
        m.classList.toggle('highlighted', m.dataset.lotId == lotId);
      });

      // Reset view then zoom to lot
      imZoom = 1; imPanX = 0; imPanY = 0;
      applyInlineTransform();

      document.getElementById('inlineMapModal').classList.add('open');
      document.body.style.overflow = 'hidden';

      // After image loads, zoom to the highlighted lot
      const img = document.getElementById('inlineMapImg');
      const doZoom = () => zoomToLot(lotId, img);
      if (img.complete) { setTimeout(doZoom, 80); }
      else { img.onload = () => setTimeout(doZoom, 80); }
    }

    function buildInlineMarkers() {
      const canvas = document.getElementById('inlineMapCanvas');
      const img    = document.getElementById('inlineMapImg');

      ALL_LOTS.forEach(lot => {
        const m = document.createElement('div');
        m.className = 'inline-lot-marker ' + (lot.actual_status === 'Occupied' ? 'occupied' : 'vacant');
        m.dataset.lotId = lot.id;
        m.title = `Lot ${lot.lot_number}`;

        // Positions are percentages of the image
        m.style.left   = lot.map_x + '%';
        m.style.top    = lot.map_y + '%';
        m.style.width  = (lot.map_width  || 2) + '%';
        m.style.height = (lot.map_height || 2) + '%';

        canvas.appendChild(m);
      });
      imMarkersBuilt = true;
    }

    function zoomToLot(lotId, img) {
      const lot = ALL_LOTS.find(l => l.id == lotId);
      if (!lot) return;

      const wrap = document.getElementById('inlineMapWrap');
      const wW = wrap.clientWidth, wH = wrap.clientHeight;
      const iW = img.clientWidth,  iH = img.clientHeight;

      // Lot center in image pixels
      const lotCX = (parseFloat(lot.map_x) + parseFloat(lot.map_width  || 2) / 2) / 100 * iW;
      const lotCY = (parseFloat(lot.map_y) + parseFloat(lot.map_height || 2) / 2) / 100 * iH;

      imZoom = 5;
      imPanX = wW / 2 - lotCX * imZoom;
      imPanY = wH / 2 - lotCY * imZoom;
      clampPan(iW, iH, wW, wH);
      applyInlineTransform(true);
    }

    function applyInlineTransform(animate) {
      const canvas = document.getElementById('inlineMapCanvas');
      if (!canvas) return;
      canvas.style.transition = animate ? 'transform 0.5s cubic-bezier(0.4,0,0.2,1)' : 'none';
      canvas.style.transform  = `translate(${imPanX}px, ${imPanY}px) scale(${imZoom})`;
    }

    function clampPan(iW, iH, wW, wH) {
      const maxX = 0, minX = wW - iW * imZoom;
      const maxY = 0, minY = wH - iH * imZoom;
      imPanX = Math.min(maxX, Math.max(imZoom > 1 ? minX : 0, imPanX));
      imPanY = Math.min(maxY, Math.max(imZoom > 1 ? minY : 0, imPanY));
    }

    function inlineMapZoom(factor) {
      const wrap = document.getElementById('inlineMapWrap');
      const img  = document.getElementById('inlineMapImg');
      const cx = wrap.clientWidth / 2, cy = wrap.clientHeight / 2;
      imPanX = cx - (cx - imPanX) * factor;
      imPanY = cy - (cy - imPanY) * factor;
      imZoom = Math.min(10, Math.max(0.5, imZoom * factor));
      clampPan(img.clientWidth, img.clientHeight, wrap.clientWidth, wrap.clientHeight);
      applyInlineTransform(true);
    }

    function inlineMapReset() {
      if (imHighlightLotId) {
        zoomToLot(imHighlightLotId, document.getElementById('inlineMapImg'));
      } else {
        imZoom = 1; imPanX = 0; imPanY = 0;
        applyInlineTransform(true);
      }
    }

    // Drag to pan
    (function() {
      const getWrap = () => document.getElementById('inlineMapWrap');
      function onDown(e) {
        if (!document.getElementById('inlineMapModal').classList.contains('open')) return;
        imDragging = true;
        const pt = e.touches ? e.touches[0] : e;
        imDragStartX = pt.clientX; imDragStartY = pt.clientY;
        imPanStartX  = imPanX;     imPanStartY  = imPanY;
        getWrap().classList.add('grabbing');
        e.preventDefault();
      }
      function onMove(e) {
        if (!imDragging) return;
        const pt = e.touches ? e.touches[0] : e;
        const dx = pt.clientX - imDragStartX, dy = pt.clientY - imDragStartY;
        const wrap = getWrap(), img = document.getElementById('inlineMapImg');
        imPanX = imPanStartX + dx;
        imPanY = imPanStartY + dy;
        clampPan(img.clientWidth, img.clientHeight, wrap.clientWidth, wrap.clientHeight);
        applyInlineTransform(false);
        e.preventDefault();
      }
      function onUp() {
        imDragging = false;
        const w = getWrap(); if (w) w.classList.remove('grabbing');
      }
      document.addEventListener('mousedown',  onDown, { passive: false });
      document.addEventListener('mousemove',  onMove, { passive: false });
      document.addEventListener('mouseup',    onUp);
      document.addEventListener('touchstart', onDown, { passive: false });
      document.addEventListener('touchmove',  onMove, { passive: false });
      document.addEventListener('touchend',   onUp);

      // Scroll to zoom
      document.addEventListener('wheel', e => {
        if (!document.getElementById('inlineMapModal').classList.contains('open')) return;
        const wrap = getWrap();
        if (!wrap || !wrap.contains(e.target)) return;
        e.preventDefault();
        const factor = e.deltaY < 0 ? 1.12 : 0.88;
        const rect = wrap.getBoundingClientRect();
        const cx = e.clientX - rect.left, cy = e.clientY - rect.top;
        imPanX = cx - (cx - imPanX) * factor;
        imPanY = cy - (cy - imPanY) * factor;
        imZoom = Math.min(10, Math.max(0.5, imZoom * factor));
        const img = document.getElementById('inlineMapImg');
        clampPan(img.clientWidth, img.clientHeight, wrap.clientWidth, wrap.clientHeight);
        applyInlineTransform(false);
      }, { passive: false });
    })();

    function closeInlineMap(e) {
      if (e.target === document.getElementById('inlineMapModal')) closeInlineMapDirect();
    }
    function closeInlineMapDirect() {
      document.getElementById('inlineMapModal').classList.remove('open');
      document.body.style.overflow = '';
    }

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
            borderWidth: 0, hoverOffset: 8, borderRadius: 4
          }]
        },
        options: {
          cutout: '74%',
          plugins: {
            legend: { display: false },
            tooltip: {
              backgroundColor: '#0f172a', titleColor: '#94a3b8', bodyColor: '#f1f5f9',
              padding: 12, cornerRadius: 10,
              callbacks: { label: ctx => `  ${ctx.label}: ${ctx.parsed.toLocaleString()} lots` }
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
            { label:'Vacant',   data:<?php echo $blockVacant; ?>,   backgroundColor:'#10b981', hoverBackgroundColor:'#059669', borderRadius:6, barThickness:16 },
            { label:'Occupied', data:<?php echo $blockOccupied; ?>, backgroundColor:'#f43f5e', hoverBackgroundColor:'#e11d48', borderRadius:6, barThickness:16 }
          ]
        },
        options: {
          indexAxis: 'y', responsive: true,
          plugins: {
            legend: { position:'bottom', labels:{ boxWidth:12, borderRadius:4, usePointStyle:true, pointStyle:'circle', font:{size:12,family:'Inter,system-ui,sans-serif'}, color:'#64748b', padding:20 } },
            tooltip: { backgroundColor:'#0f172a', titleColor:'#94a3b8', bodyColor:'#f1f5f9', padding:12, cornerRadius:10, callbacks:{ label: ctx => `  ${ctx.dataset.label}: ${ctx.parsed.x.toLocaleString()} lots` } }
          },
          scales: {
            x: { stacked:false, grid:{color:'#f1f5f9',drawBorder:false}, ticks:{font:{size:11,family:'Inter,system-ui,sans-serif'},color:'#94a3b8'}, border:{display:false} },
            y: { grid:{display:false}, ticks:{font:{size:12,weight:'600',family:'Inter,system-ui,sans-serif'},color:'#334155'}, border:{display:false} }
          },
          animation: { duration:900, easing:'easeInOutQuart' }
        }
      });
    })();
  </script>
</body>
</html>
