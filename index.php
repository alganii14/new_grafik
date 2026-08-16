<?php
require_once __DIR__ . '/auth.php';
require_authentication();

$dashboardNonce = base64_encode(random_bytes(18));
$dashboardCsrfToken = csrf_token();
send_security_headers(
    "default-src 'self'; "
    . "script-src 'self'; "
    . "script-src-elem 'self' 'nonce-{$dashboardNonce}'; "
    . "script-src-attr 'unsafe-inline'; "
    . "style-src 'self' 'unsafe-inline'; "
    . "img-src 'self' data: blob:; font-src 'self' data:; "
    . "connect-src 'self'; worker-src 'self' blob:; object-src 'none'; "
    . "base-uri 'self'; frame-ancestors 'none'; form-action 'self'"
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($dashboardCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <title>Dashboard Grafik DPK &amp; BriLink</title>
    <script src="assets/vendor/chart.umd.min.js"></script>
    <script src="assets/vendor/chartjs-plugin-datalabels.min.js"></script>
    <script src="assets/vendor/html2canvas.min.js"></script>
    <script src="assets/vendor/xlsx.full.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            color: #333;
        }
        .header {
            background: linear-gradient(135deg, #1a237e, #283593);
            color: white;
            padding: 18px 10px;
            text-align: center;
            position: relative;
        }
        .header h1 { font-size: 22px; font-weight: 600; }
        .header p { font-size: 13px; opacity: 0.8; margin-top: 4px; }
        .header-actions {
            position: absolute;
            top: 50%;
            right: 20px;
            display: flex;
            align-items: center;
            gap: 9px;
            transform: translateY(-50%);
        }
        .session-user {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 7px 11px;
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 8px;
            color: rgba(255,255,255,0.88);
            background: rgba(255,255,255,0.08);
            font-size: 12px;
            font-weight: 600;
        }
        .logout-form { display: flex; }
        .logout-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            color: white;
            background: rgba(8,16,62,0.3);
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .logout-btn:hover {
            border-color: rgba(255,255,255,0.38);
            background: rgba(255,255,255,0.14);
            transform: translateY(-1px);
        }

        .controls {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 16px 10px;
            background: #fff;
            border-bottom: 1px solid #e0e0e0;
            align-items: center;
        }
        .control-group {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .control-group label {
            font-size: 13px;
            font-weight: 600;
            color: #555;
        }
        .tab-group {
            display: flex;
            gap: 4px;
        }
        .tab-btn {
            padding: 7px 16px;
            border: 1px solid #ccc;
            background: #fff;
            cursor: pointer;
            font-size: 13px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .tab-btn:hover { background: #e8eaf6; }
        .tab-btn.active {
            background: #1a237e;
            color: white;
            border-color: #1a237e;
        }
        .month-btn {
            padding: 5px 10px;
            border: 1px solid #ccc;
            background: #fff;
            cursor: pointer;
            font-size: 12px;
            border-radius: 4px;
            transition: all 0.2s;
        }
        .month-btn:hover { background: #e8eaf6; }
        .month-btn.active {
            color: white;
            border-color: transparent;
        }

        .chart-container {
            margin: 20px 10px;
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        .chart-title {
            font-size: 17px;
            font-weight: 700;
            color: #1a237e;
            margin-bottom: 4px;
        }
        .chart-subtitle {
            font-size: 12px;
            color: #888;
            margin-bottom: 16px;
        }
        .chart-wrapper {
            position: relative;
            height: 480px;
        }
        .info-cards {
            display: flex;
            gap: 12px;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        .info-card {
            flex: 1;
            min-width: 140px;
            padding: 12px 16px;
            border-radius: 8px;
            background: #f5f5f5;
            border-left: 4px solid #1a237e;
        }
        .info-card .label { font-size: 11px; color: #888; text-transform: uppercase; }
        .info-card .value { font-size: 18px; font-weight: 700; color: #1a237e; margin-top: 2px; }
        .info-card .detail { font-size: 11px; color: #666; }

        .bottom-note {
            margin-top: 12px;
            padding: 10px 14px;
            background: #fff8e1;
            border-radius: 8px;
            font-size: 12px;
            color: #f57f17;
            border: 1px solid #ffecb3;
        }

        .legend-custom {
            display: flex;
            gap: 20px;
            margin-top: 14px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 500;
        }
        .legend-dot {
            width: 12px;
            height: 4px;
            border-radius: 2px;
        }

        .tables-row {
            display: flex;
            gap: 20px;
            margin: 20px 10px;
            flex-wrap: wrap;
        }
        .table-box {
            flex: 1;
            min-width: 420px;
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        .table-box h3 {
            font-size: 14px;
            font-weight: 700;
            color: #1a237e;
            margin-bottom: 12px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .data-table thead th {
            background: #1a237e;
            color: #fff;
            padding: 8px 10px;
            text-align: center;
            font-weight: 600;
            white-space: nowrap;
        }
        .data-table thead th:first-child {
            border-radius: 6px 0 0 0;
        }
        .data-table thead th:last-child {
            border-radius: 0 6px 0 0;
        }
        .data-table tbody td {
            padding: 7px 10px;
            text-align: center;
            border-bottom: 1px solid #eee;
        }
        .data-table tbody tr:nth-child(even) {
            background: #f8f9ff;
        }
        .data-table tbody tr:hover {
            background: #e8eaf6;
        }
        .val-up { color: #2e7d32; font-weight: 600; }
        .val-down { color: #c62828; font-weight: 600; }
        .val-neutral { color: #888; }

        /* CRUD Modal Styles */
        .crud-btn {
            padding: 8px 20px;
            background: linear-gradient(135deg, #43a047, #2e7d32);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .crud-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(46,125,50,0.3); }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: flex-start;
            padding: 30px 16px;
            overflow-y: auto;
        }
        .modal-overlay.active { display: flex; }

        .modal-box {
            background: #fff;
            border-radius: 16px;
            width: 100%;
            max-width: 900px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            animation: modalIn 0.3s ease;
        }
        @keyframes modalIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid #e0e0e0;
            background: linear-gradient(135deg, #1a237e, #283593);
            border-radius: 16px 16px 0 0;
            color: white;
        }
        .modal-header h2 { font-size: 18px; font-weight: 700; }
        .modal-close {
            width: 32px; height: 32px;
            background: rgba(255,255,255,0.2);
            border: none;
            border-radius: 50%;
            color: white;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        .modal-close:hover { background: rgba(255,255,255,0.3); }

        .modal-body { padding: 24px; }

        .form-row {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .form-group {
            flex: 1;
            min-width: 140px;
        }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #555;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        .form-group select,
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
            background: #fff;
        }
        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #1a237e;
        }
        .form-group input.auto-calc {
            background: #e8eaf6;
            color: #1a237e;
            font-weight: 700;
            border-color: #c5cae9;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #eee;
        }
        .btn-save {
            padding: 10px 28px;
            background: linear-gradient(135deg, #1a237e, #283593);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-save:hover { box-shadow: 0 4px 12px rgba(26,35,126,0.3); }
        .btn-delete {
            padding: 10px 28px;
            background: linear-gradient(135deg, #c62828, #b71c1c);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-delete:hover { box-shadow: 0 4px 12px rgba(198,40,40,0.3); }
        .btn-cancel {
            padding: 10px 28px;
            background: #f5f5f5;
            color: #555;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .crud-data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-top: 16px;
        }
        .crud-data-table thead th {
            background: #1a237e;
            color: #fff;
            padding: 8px 10px;
            text-align: center;
            font-weight: 600;
            font-size: 11px;
        }
        .crud-data-table thead th:first-child { border-radius: 6px 0 0 0; }
        .crud-data-table thead th:last-child { border-radius: 0 6px 0 0; }
        .crud-data-table tbody td {
            padding: 6px 8px;
            text-align: center;
            border-bottom: 1px solid #eee;
            font-size: 12px;
        }
        .crud-data-table tbody tr:nth-child(even) { background: #f8f9ff; }
        .crud-data-table tbody tr:hover { background: #e8eaf6; cursor: pointer; }
        .crud-data-table tbody tr.selected { background: #c5cae9; }

        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 14px 24px;
            border-radius: 10px;
            color: white;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
            z-index: 2000;
            animation: toastIn 0.3s ease, toastOut 0.3s ease 2.5s forwards;
        }
        .toast.success { background: linear-gradient(135deg, #43a047, #2e7d32); }
        .toast.error { background: linear-gradient(135deg, #e53935, #c62828); }
        @keyframes toastIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes toastOut { from { opacity: 1; } to { opacity: 0; } }

        .section-divider {
            font-size: 13px;
            font-weight: 700;
            color: #1a237e;
            margin: 20px 0 8px;
            padding-bottom: 6px;
            border-bottom: 2px solid #e8eaf6;
        }

        /* ── PAGE SWITCHER ── */
        .page-nav {
            display: flex;
            gap: 0;
            background: #1a237e;
            padding: 0 16px;
            border-bottom: 3px solid #f59e0b;
        }
        .page-nav-btn {
            padding: 10px 28px;
            background: none;
            border: none;
            color: rgba(255,255,255,.65);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -3px;
            transition: all .2s;
        }
        .page-nav-btn:hover { color: #fff; }
        .page-nav-btn.active { color: #fff; border-bottom-color: #f59e0b; }

        /* ── BRILINK SECTION ── */
        #page-brilink { display: none; }
        .bl-container { max-width: 1200px; margin: 0 auto; padding: 24px 16px 40px; }
        .bl-section-title {
            font-size: 1.05rem; font-weight: 700; color: #1a237e;
            padding: 8px 0 4px; border-bottom: 2.5px solid #1a237e;
            margin-bottom: 16px; display: flex; align-items: center; gap: 8px;
        }
        .bl-section-title .bl-badge {
            background: #1a237e; color: #fff; border-radius: 50%;
            width: 24px; height: 24px; display: flex; align-items: center;
            justify-content: center; font-size: .78rem; flex-shrink: 0;
        }
        .bl-card {
            background: #fff; border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,.07);
            padding: 20px 22px 16px; margin-bottom: 24px;
        }
        .bl-card-title { font-size: .96rem; font-weight: 700; color: #1a237e; margin-bottom: 3px; }
        .bl-card-subtitle { font-size: .78rem; color: #888; margin-bottom: 12px; }
        .bl-chart-row { display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 24px; }
        .bl-summary-row { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
        .bl-badge-box {
            background: #f0f2f5; border-left: 4px solid #1a237e;
            border-radius: 6px; padding: 6px 12px; min-width: 120px;
        }
        .bl-badge-box .lbl { font-size: .7rem; color: #666; }
        .bl-badge-box .val { font-size: .93rem; font-weight: 700; color: #1a237e; margin-top: 1px; }

        /* BriLink input modal overrides */
        #inputModal.modal-overlay { align-items: center; padding: 0; }
        #inputModal .modal-box {
            max-height: 92vh; display: flex; flex-direction: column;
            max-width: 880px;
        }
        #inputModal .modal-header {
            background: linear-gradient(135deg,#1a237e,#283593);
            border-radius: 16px 16px 0 0;
        }
        #inputModal .modal-body { overflow-y: auto; flex: 1; }

        /* input table */
        .input-table { width: 100%; border-collapse: collapse; font-size: .82rem; margin-top: 10px; }
        .input-table th {
            background: #1a237e; color: #fff; padding: 7px 10px;
            text-align: left; font-weight: 600;
        }
        .input-table td { padding: 5px 8px; border-bottom: 1px solid #eee; vertical-align: middle; }
        .input-table tr:nth-child(even) td { background: #f8f9ff; }
        .input-table input[type=text] {
            width: 100%; border: 1px solid #ccc; border-radius: 5px;
            padding: 4px 7px; font-size: .82rem;
        }
        .input-table input[type=text]:focus { border-color: #1a237e; outline: none; }
        .input-table .auto-field {
            background: #e8eaf6; color: #1a237e; font-weight: 600;
            padding: 4px 7px; border-radius: 5px; display: block;
        }
        .input-table tr.total-row td { background: #e8eaf6; font-weight: 700; color: #1a237e; }
        .tab-row { display: flex; gap: 6px; margin-bottom: 14px; flex-wrap: wrap; }

        @media(max-width: 1100px) {
            .header { padding: 18px 12px; }
            .header-actions {
                position: static;
                justify-content: center;
                flex-wrap: wrap;
                margin-top: 14px;
                transform: none;
            }
        }
        @media(max-width: 768px) {
            .bl-chart-row { grid-template-columns: 1fr; }
            .header-actions { gap: 7px; }
            .session-user { display: none; }
            .crud-btn, .logout-btn { padding: 8px 11px; }
        }
    </style>
</head>
<body>

<div class="header">
    <h1>Dashboard Grafik Harian DPK &amp; BriLink</h1>
    <p>Monitoring Data Harian Tabungan, Giro, Deposito, CASA &amp; DPK · BriLink</p>
    <div class="header-actions">
        <span class="session-user">👤 <?= htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8') ?></span>
        <button class="crud-btn" id="headerInputBtn" type="button" onclick="openCrudModal()">✏️ Input Data</button>
        <form class="logout-form" action="logout.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($dashboardCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button class="logout-btn" type="submit">↪ Keluar</button>
        </form>
    </div>
</div>

<div class="page-nav">
    <button class="page-nav-btn active" id="navDpk" onclick="switchPage('dpk')">📊 Grafik DPK</button>
    <button class="page-nav-btn" id="navBrilink" onclick="switchPage('brilink')">🏦 BriLink</button>
</div>

<div id="page-dpk">
<div class="controls">
    <div class="control-group">
        <label>Kategori:</label>
        <div class="tab-group" id="folderTabs">
            <button class="tab-btn active" data-folder="csv_konsol">Konsolidasi</button>
            <button class="tab-btn" data-folder="csv_kc_only">KC Only</button>
            <button class="tab-btn" data-folder="csv_kcp_only">KCP Only</button>
            <button class="tab-btn" data-folder="csv_mikro">Mikro</button>
            <button class="tab-btn" data-folder="csv_ritel">Ritel</button>
        </div>
    </div>
    <div class="control-group">
        <label>Metrik:</label>
        <div class="tab-group" id="metricTabs">
            <button class="tab-btn active" data-metric="tabungan">Tabungan</button>
            <button class="tab-btn" data-metric="giro">Giro</button>
            <button class="tab-btn" data-metric="depo">Deposito</button>
            <button class="tab-btn" data-metric="casa">CASA</button>
            <button class="tab-btn" data-metric="dpk">DPK</button>
        </div>
    </div>
</div>

<div class="controls" style="padding-top:8px;border-top:none;">
    <div class="control-group">
        <label>Bulan:</label>
        <div class="tab-group" id="monthFilter" style="flex-wrap:wrap;"></div>
    </div>
    <div class="control-group" style="margin-left:auto;">
        <label>Label:</label>
        <div class="tab-group" id="labelMode">
            <button class="tab-btn active" data-label="all">Semua</button>
            <button class="tab-btn" data-label="edge">Awal & Akhir</button>
            <button class="tab-btn" data-label="none">Tanpa Label</button>
        </div>
    </div>
</div>

<div class="chart-container">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <div>
            <div class="chart-title" id="chartTitle">Tabungan - Konsolidasi</div>
            <div class="chart-subtitle" id="chartSubtitle">Data harian per bulan</div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
            <button class="tab-btn" onclick="exportCurrentSelectionExcel()" style="padding:7px 16px;white-space:nowrap;">Export Excel</button>
            <button class="tab-btn" onclick="downloadChart()" style="padding:7px 16px;white-space:nowrap;">Download Chart</button>
        </div>
    </div>
    <div class="chart-wrapper">
        <canvas id="mainChart"></canvas>
    </div>
    <div class="legend-custom" id="customLegend"></div>
</div>

<div class="tables-row" id="tablesRow">
    <div class="table-box" id="avgTableBox">
        <h3>Average Balance vs Ending Balance</h3>
        <table class="data-table" id="avgTable">
            <thead>
                <tr>
                    <th>Bulan</th>
                    <th>Ending Balance</th>
                    <th>Avg Bln</th>
                    <th>Avg/Ending</th>
                    <th>MTD</th>
                    <th>YTD</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    <div class="table-box" id="bottomTableBox">
        <h3>Bottom vs Ending Balance</h3>
        <table class="data-table" id="bottomTable">
            <thead>
                <tr>
                    <th>Bulan</th>
                    <th>Ending Balance</th>
                    <th>Bottom</th>
                    <th>Bottom/Ending</th>
                    <th>MTD</th>
                    <th>YTD</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
<div style="text-align:center;margin:0 10px 20px;">
    <button class="tab-btn" onclick="downloadTable('tablesRow','semua_tabel')" style="padding:8px 24px;">Download Semua Tabel</button>
</div>
</div><!-- /page-dpk -->

<!-- ══════════════════ BRILINK PAGE ══════════════════ -->
<div id="page-brilink">
<div class="bl-container">

    <!-- Chart selector tabs -->
    <div class="controls" style="margin-bottom:0;">
        <div class="control-group">
            <label>Grafik:</label>
            <div class="tab-group" id="blChartTabs">
                <button class="tab-btn active" onclick="switchBlChart('vol',this)">Volume TRX Setor</button>
                <button class="tab-btn" onclick="switchBlChart('agen',this)">CASA Posisi Agen</button>
                <button class="tab-btn" onclick="switchBlChart('uker',this)">CASA per UKER</button>
            </div>
        </div>
    </div>
    <!-- BriLink Month Filter (shared) -->
    <div class="controls" style="padding-top:8px;border-top:none;">
        <div class="control-group">
            <label>Bulan:</label>
            <div class="tab-group" id="blMonthFilter" style="flex-wrap:wrap;"></div>
        </div>
    </div>

    <!-- Section 1: Volume TRX -->
    <div id="bl-sec-vol">
        <div class="bl-section-title"><span class="bl-badge">1</span>Volume Transaksi Setor BriLink</div>
        
        <!-- Filter Agen -->
        <div class="controls" style="margin-bottom:12px;">
            <div class="control-group" style="align-items:flex-start;">
                <label style="padding-top:6px;">Filter Agen:</label>
                <div style="display:flex;flex-wrap:wrap;gap:6px;" id="volAgenFilterTabs"></div>
            </div>
        </div>

        <!-- Grafik per Agen -->
        <div class="bl-card" id="card-vol">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px;">
                <div>
                    <div class="bl-card-title">Volume TRX Setor Harian per Agen (Rp Juta)</div>
                    <div class="bl-card-subtitle" id="volSubtitle">Memuat data…</div>
                </div>
                <button class="tab-btn" onclick="blDownloadChart('card-vol','volume_trx')" style="white-space:nowrap;padding:6px 14px;margin-left:12px;">Download Chart</button>
            </div>
            <div class="bl-summary-row" id="volSummary"></div>
            <div style="position:relative;height:380px;"><canvas id="chartVol"></canvas></div>
            <div class="legend-custom" id="volLegend"></div>
        </div>

        <!-- Grafik Konsol per Bulan -->
        <div class="bl-card" id="card-vol-monthly">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px;">
                <div>
                    <div class="bl-card-title">Grafik Konsol Volume TRX per Bulan (Rp Miliar)</div>
                    <div class="bl-card-subtitle" id="volMonthlySubtitle">Volume harian per bulan - semua agen (satu garis per bulan)</div>
                </div>
                <button class="tab-btn" onclick="blDownloadChart('card-vol-monthly','volume_trx_monthly')" style="white-space:nowrap;padding:6px 14px;margin-left:12px;">Download Chart</button>
            </div>
            <div class="controls" style="margin-bottom:8px;padding:8px 0 0;border:none;">
                <div class="control-group">
                    <label>Bulan:</label>
                    <div class="tab-group" id="volKonsolMonthFilter" style="flex-wrap:wrap;"></div>
                </div>
            </div>
            <div class="bl-summary-row" id="volMonthlySummary"></div>
            <div style="position:relative;height:380px;"><canvas id="chartVolMonthly"></canvas></div>
            <div class="legend-custom" id="volMonthlyLegend"></div>
        </div>
    </div>

    <!-- Section 2: CASA Agen -->
    <div id="bl-sec-agen" style="display:none;">
        <div class="bl-section-title"><span class="bl-badge">2</span>CASA Posisi Agen BriLink</div>

        <!-- UKER + Agen Filter -->
        <div class="controls" style="margin-bottom:4px;">
            <div class="control-group">
                <label>Filter UKER:</label>
                <div class="tab-group" id="ukerFilterTabs" style="flex-wrap:wrap;"></div>
            </div>
        </div>
        <div class="controls" style="margin-bottom:12px;" id="agenFilterRow" style="display:none;">
            <div class="control-group" style="align-items:flex-start;">
                <label style="padding-top:6px;">Filter Agen:</label>
                <div style="display:flex;flex-wrap:wrap;gap:6px;" id="agenFilterTabs"></div>
            </div>
        </div>

        <!-- Agent info table (shown when specific UKER selected) -->
        <div id="agenInfoBox" style="display:none;margin-bottom:16px;">
            <div class="bl-card" style="padding:14px 18px;">
                <div class="bl-card-title" id="agenInfoTitle">Daftar Agen</div>
                <table class="data-table" id="agenInfoTable" style="margin-top:8px;font-size:.85rem;">
                    <thead><tr><th>#</th><th>Nama Agen</th><th>UKER</th><th>No. Rekening</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <div class="bl-chart-row">
            <div class="bl-card" id="card-agen">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px;">
                    <div>
                        <div class="bl-card-title" id="agenChartTitle">Tren CASA Harian per Agen (Rp Juta)</div>

                    </div>
                    <button class="tab-btn" onclick="blDownloadChart('card-agen','casa_agen_tren')" style="white-space:nowrap;padding:6px 14px;margin-left:12px;">Download Chart</button>
                </div>
                <div style="position:relative;height:420px;"><canvas id="chartAgen"></canvas></div>
                <div class="legend-custom" id="agenLegend"></div>
            </div>
            <div class="bl-card" id="card-trend">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px;">
                    <div>
                        <div class="bl-card-title">Tren Total CASA Agen (Rp Miliar)</div>
                        <div class="bl-card-subtitle">Gabungan seluruh agen</div>
                    </div>
                    <button class="tab-btn" onclick="blDownloadChart('card-trend','casa_agen_total')" style="white-space:nowrap;padding:6px 14px;margin-left:12px;">Download Chart</button>
                </div>
                <div class="bl-summary-row" id="casaSummary"></div>
                <div style="position:relative;height:380px;"><canvas id="chartCasaTrend"></canvas></div>
                <div class="legend-custom" id="trendLegend"></div>
            </div>
        </div>

        <!-- Grafik Konsol CASA per Bulan -->
        <div class="bl-card" id="card-casa-monthly">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px;">
                <div>
                    <div class="bl-card-title">Grafik Konsol CASA per Bulan (Rp Miliar)</div>
                    <div class="bl-card-subtitle">Total gabungan semua agen per bulan</div>
                </div>
                <button class="tab-btn" onclick="blDownloadChart('card-casa-monthly','casa_agen_monthly')" style="white-space:nowrap;padding:6px 14px;margin-left:12px;">Download Chart</button>
            </div>
            <div class="bl-summary-row" id="casaMonthlySummary"></div>
            <div style="position:relative;height:380px;"><canvas id="chartCasaMonthly"></canvas></div>
            <div class="legend-custom" id="casaMonthlyLegend"></div>
        </div>
    </div>

    <!-- Section 3: CASA UKER -->
    <div id="bl-sec-uker" style="display:none;">
        <div class="bl-section-title"><span class="bl-badge">3</span>CASA per Unit Kerja (UKER)</div>
        <!-- UKER line toggle -->
        <div class="controls" style="margin-bottom:12px;">
            <div class="control-group" style="align-items:flex-start;">
                <label style="padding-top:6px;">Tampilkan:</label>
                <div style="display:flex;flex-wrap:wrap;gap:6px;" id="ukerLineFilterTabs"></div>
            </div>
        </div>
        <div class="bl-card" id="card-uker">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px;">
                <div>
                    <div class="bl-card-title">Tren CASA per UKER (Rp Miliar)</div>

                </div>
                <button class="tab-btn" onclick="blDownloadChart('card-uker','casa_uker')" style="white-space:nowrap;padding:6px 14px;margin-left:12px;">Download Chart</button>
            </div>
            <div class="bl-summary-row" id="ukerSummary"></div>
            <div style="position:relative;height:380px;"><canvas id="chartUker"></canvas></div>
            <div class="legend-custom" id="ukerLegend"></div>
        </div>

        <!-- Grafik Konsol CASA per UKER Bulanan -->
        <div class="bl-card" id="card-uker-monthly">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px;">
                <div>
                    <div class="bl-card-title">Grafik Konsol CASA per UKER per Bulan (Rp Miliar)</div>
                    <div class="bl-card-subtitle" id="ukerMonthlySubtitle">CASA harian per bulan - semua UKER (satu garis per bulan)</div>
                </div>
                <button class="tab-btn" onclick="blDownloadChart('card-uker-monthly','casa_uker_monthly')" style="white-space:nowrap;padding:6px 14px;margin-left:12px;">Download Chart</button>
            </div>
            <div class="controls" style="margin-bottom:8px;padding:8px 0 0;border:none;">
                <div class="control-group">
                    <label>Bulan:</label>
                    <div class="tab-group" id="ukerKonsolMonthFilter" style="flex-wrap:wrap;"></div>
                </div>
            </div>
            <div class="bl-summary-row" id="ukerMonthlySummary"></div>
            <div style="position:relative;height:420px;"><canvas id="chartUkerMonthly"></canvas></div>
            <div class="legend-custom" id="ukerMonthlyLegend"></div>
        </div>

        <!-- Grafik Detail per Bulan untuk UKER Terpilih -->
        <div id="ukerMonthlyBreakdownContainer"></div>
    </div>

</div><!-- /bl-container -->
</div><!-- /page-brilink -->

<!-- BriLink Input Modal -->
<div class="modal-overlay" id="inputModal">
    <div class="modal-box">
        <div class="modal-header">
            <h2>✏️ Input / Edit Data BriLink</h2>
            <button class="modal-close" onclick="closeInputModal()">✕</button>
        </div>
        <div class="modal-body">
            <div class="section-divider">Jenis Data</div>
            <div class="tab-row" id="inputTabs">
                <button class="tab-btn active" onclick="setInputTab('volume_trx',this)">Volume TRX Setor</button>
                <button class="tab-btn" onclick="setInputTab('casa_agen',this)">CASA Posisi Agen</button>
                <button class="tab-btn" onclick="setInputTab('casa_uker',this)">CASA UKER</button>
            </div>
            <!-- Template Download/Upload -->
            <div id="templateSection" style="display:none;">
                <div class="section-divider">📋 Template Excel (Batch Input)</div>
                <div class="form-row" style="align-items:center;">
                    <button class="btn-save" onclick="downloadBrilinkTemplate()" style="background:linear-gradient(135deg,#00838f,#006064);">
                        📥 Download Template
                    </button>
                    <div class="form-group" style="max-width:300px;">
                        <label>Upload Template yang Sudah Diisi</label>
                        <input type="file" id="templateFileInput" accept=".xlsx,.xls" style="font-size:13px;">
                    </div>
                    <button class="btn-save" onclick="uploadBrilinkTemplate()" style="background:linear-gradient(135deg,#2e7d32,#1b5e20);">
                        📤 Upload & Simpan
                    </button>
                </div>
                <div id="templateInfo" style="font-size:12px;color:#888;margin-top:4px;"></div>
            </div>
            <div class="section-divider">Tanggal</div>
            <div class="form-row">
                <div class="form-group" style="max-width:220px;">
                    <label>Pilih Tanggal yang Ada</label>
                    <select id="inputDateSelect" onchange="onDateSelectChange()">
                        <option value="">-- Pilih --</option>
                    </select>
                </div>
                <div class="form-group" style="max-width:200px;">
                    <label>Atau Tanggal Baru</label>
                    <input type="date" id="inputDateNew" onchange="onDateNewChange()">
                </div>
                <div class="form-group" style="max-width:160px;display:flex;align-items:flex-end;">
                    <button class="btn-delete" onclick="confirmDeleteDate()" style="width:100%;">🗑️ Hapus Tanggal</button>
                </div>
            </div>
            <div class="section-divider">Data <span id="inputDateLabel" style="color:#1a237e;"></span></div>
            <div style="overflow-x:auto;">
                <table class="input-table" id="inputDataTable">
                    <thead id="inputTableHead"></thead>
                    <tbody id="inputTableBody"></tbody>
                </table>
            </div>
            <div class="form-actions" style="margin-top:18px;">
                <button class="btn-save" onclick="saveInputData()">💾 Simpan Data</button>
                <button class="btn-cancel" onclick="closeInputModal()">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- CRUD Modal -->
<div class="modal-overlay" id="crudModal">
    <div class="modal-box">
        <div class="modal-header">
            <h2>✏️ Input / Edit Data CSV</h2>
            <button class="modal-close" onclick="closeCrudModal()">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group">
                    <label>Kategori</label>
                    <select id="crudFolder" onchange="onCrudFolderChange()">
                        <option value="csv_konsol">Konsolidasi</option>
                        <option value="csv_kc_only">KC Only</option>
                        <option value="csv_kcp_only">KCP Only</option>
                        <option value="csv_mikro">Mikro</option>
                        <option value="csv_ritel">Ritel</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Bulan</label>
                    <select id="crudMonth" onchange="onCrudMonthChange()">
                        <option value="Des-2025">Des 2025</option>
                        <option value="Jan">Jan 2026</option>
                        <option value="Feb">Feb 2026</option>
                        <option value="Mar" selected>Mar 2026</option>
                        <option value="Apr">Apr 2026</option>
                        <option value="May">Mei 2026</option>
                        <option value="Jun">Jun 2026</option>
                        <option value="Jul">Jul 2026</option>
                        <option value="Aug">Ags 2026</option>
                        <option value="Sep">Sep 2026</option>
                        <option value="Oct">Okt 2026</option>
                        <option value="Nov">Nov 2026</option>
                        <option value="Dec">Des 2026</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Hari</label>
                    <select id="crudDay"></select>
                </div>
            </div>

            <div class="section-divider">Input Data (isi manual)</div>
            <div class="form-row">
                <div class="form-group">
                    <label>Tabungan</label>
                    <input type="text" id="crudTabungan" placeholder="0" oninput="calcCasaDpk()">
                </div>
                <div class="form-group">
                    <label>Giro</label>
                    <input type="text" id="crudGiro" placeholder="0" oninput="calcCasaDpk()">
                </div>
                <div class="form-group">
                    <label>Deposito</label>
                    <input type="text" id="crudDepo" placeholder="0" oninput="calcCasaDpk()">
                </div>
            </div>

            <div class="section-divider">Otomatis (CASA & DPK)</div>
            <div class="form-row">
                <div class="form-group">
                    <label>CASA (Tabungan + Giro)</label>
                    <input type="text" id="crudCasa" class="auto-calc" readonly>
                </div>
                <div class="form-group">
                    <label>DPK (CASA + Deposito)</label>
                    <input type="text" id="crudDpk" class="auto-calc" readonly>
                </div>
            </div>

            <div class="form-actions">
                <button class="btn-save" onclick="saveCrudData()">💾 Simpan</button>
                <button class="btn-delete" onclick="deleteCrudData()">🗑️ Hapus Hari Ini</button>
                <button class="btn-cancel" onclick="closeCrudModal()">Batal</button>
                <button class="btn-save" onclick="loadExistingData()" style="margin-left:auto;background:linear-gradient(135deg,#00838f,#006064);">🔄 Muat Data</button>
            </div>

            <div class="section-divider" style="margin-top:24px;">Data Bulan Ini</div>
            <div style="overflow-x:auto;">
                <table class="crud-data-table" id="crudDataTable">
                    <thead><tr id="crudTableHead"></tr></thead>
                    <tbody id="crudTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= htmlspecialchars($dashboardNonce, ENT_QUOTES, 'UTF-8') ?>">
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

const MONTH_COLORS = {
    'Des-2025': { line: '#9e9e9e', bg: 'rgba(158,158,158,0.1)' },
    'Jan':      { line: '#0b2f61', bg: 'rgba(11,47,97,0.1)' },
    'Feb':      { line: '#e65100', bg: 'rgba(230,81,0,0.1)' },
    'Mar':      { line: '#2e7d32', bg: 'rgba(46,125,50,0.1)' },
    'Apr':      { line: '#6a1b9a', bg: 'rgba(106,27,154,0.1)' },
    'May':      { line: '#c62828', bg: 'rgba(198,40,40,0.1)' },
    'Jun':      { line: '#00838f', bg: 'rgba(0,131,143,0.1)' },
    'Jul':      { line: '#0288d1', bg: 'rgba(2,136,209,0.1)' },
    'Aug':      { line: '#ad1457', bg: 'rgba(173,20,87,0.1)' },
    'Sep':      { line: '#4527a0', bg: 'rgba(69,39,160,0.1)' },
    'Oct':      { line: '#1b5e20', bg: 'rgba(27,94,32,0.1)' },
    'Nov':      { line: '#ff8f00', bg: 'rgba(255,143,0,0.1)' },
    'Dec':      { line: '#b71c1c', bg: 'rgba(183,28,28,0.1)' }
};

const MONTH_LABELS = {
    'Des-2025': "Des '25", 'Jan': "Jan '26", 'Feb': "Feb '26", 'Mar': "Mar '26",
    'Apr': "Apr '26", 'May': "Mei '26", 'Jun': "Jun '26", 'Jul': "Jul '26",
    'Aug': "Ags '26", 'Sep': "Sep '26", 'Oct': "Okt '26", 'Nov': "Nov '26", 'Dec': "Des '26"
};

const METRIC_NAMES = {
    tabungan: 'Tabungan', giro: 'Giro', depo: 'Deposito', casa: 'CASA', dpk: 'DPK'
};
const FOLDER_NAMES = {
    'csv_konsol': 'Konsolidasi', 'csv_kc_only': 'KC Only',
    'csv_kcp_only': 'KCP Only', 'csv_mikro': 'Mikro', 'csv_ritel': 'Ritel'
};

let currentFolder = 'csv_konsol';
let currentMetric = 'tabungan';
let selectedMonths = new Set();
let labelMode = 'all';
let chart = null;

async function loadCSV(folder, metric) {
    try {
        const resp = await fetch(`data.php?action=read&folder=${encodeURIComponent(folder)}`);
        const json = await resp.json();

        if (resp.status === 401 && json.redirect) {
            window.location.href = json.redirect;
            return null;
        }
        if (!resp.ok || !json.success || !json.data?.[metric]) {
            throw new Error(json.error || 'Data tidak tersedia');
        }

        const csv = json.data[metric];
        const headers = csv.headers.map(header => header.trim());
        const data = {};
        headers.forEach(header => { data[header] = []; });
        const isKCP = folder === 'csv_kcp_only';

        csv.data.forEach(row => {
            headers.forEach(header => {
                const value = String(row[header] ?? '').trim();
                if (value === '') {
                    data[header].push(null);
                } else {
                    data[header].push(isKCP ? parseInt(value.replace('.', ''), 10) : parseFloat(value));
                }
            });
        });

        return { headers, data };
    } catch (e) {
        return null;
    }
}

function getStats(arr) {
    const valid = arr.filter(v => v !== null && !isNaN(v));
    if (valid.length === 0) return null;
    const min = Math.min(...valid);
    const max = Math.max(...valid);
    const first = valid[0];
    const last = valid[valid.length - 1];
    const avg = valid.reduce((a, b) => a + b, 0) / valid.length;
    const minIdx = arr.indexOf(min);
    const maxIdx = arr.indexOf(max);
    return { min, max, first, last, avg, count: valid.length, minIdx, maxIdx };
}

function formatNum(v) {
    if (v === null || v === undefined) return '-';
    if (v % 1 === 0) {
        return v >= 1000 ? Math.round(v).toLocaleString('id-ID') : v.toFixed(0);
    }
    return v.toFixed(3);
}

async function renderChart() {
    const result = await loadCSV(currentFolder, currentMetric);
    if (!result) {
        document.getElementById('chartTitle').textContent = 'Data tidak tersedia';
        document.getElementById('customLegend').innerHTML = '';
        if (chart) { chart.destroy(); chart = null; }
        return;
    }

    const { headers, data } = result;

    // Build month filter buttons
    const availableMonths = [];
    headers.forEach(month => {
        const vals = data[month];
        const stats = getStats(vals);
        if (stats && stats.count > 0) availableMonths.push(month);
    });

    // Initialize selectedMonths if empty or has invalid months
    if (selectedMonths.size === 0) {
        availableMonths.forEach(m => selectedMonths.add(m));
    }

    const filterContainer = document.getElementById('monthFilter');
    filterContainer.innerHTML = '';
    availableMonths.forEach(month => {
        const btn = document.createElement('button');
        btn.className = 'month-btn' + (selectedMonths.has(month) ? ' active' : '');
        btn.textContent = MONTH_LABELS[month] || month;
        const color = MONTH_COLORS[month] || { line: '#333' };
        if (selectedMonths.has(month)) {
            btn.style.background = color.line;
            btn.style.borderColor = color.line;
        }
        btn.addEventListener('click', () => {
            if (selectedMonths.has(month)) {
                if (selectedMonths.size > 1) selectedMonths.delete(month);
            } else {
                selectedMonths.add(month);
            }
            renderChart();
        });
        filterContainer.appendChild(btn);
    });

    // Calculate maxDays based on last valid data point of selected months only
    const maxDays = Math.max(...headers.filter(h => selectedMonths.has(h)).map(h => {
        const vals = data[h];
        if (!vals) return 0;
        for (let i = vals.length - 1; i >= 0; i--) { if (vals[i] !== null) return i + 1; }
        return 0;
    }), 0);
    const dayLabels = Array.from({ length: maxDays }, (_, i) => i + 1);

    const datasets = [];
    const allStats = {};
    let globalMin = Infinity, globalMinMonth = '', globalMax = -Infinity, globalMaxMonth = '';

    headers.forEach(month => {
        const vals = data[month];
        const stats = getStats(vals);
        if (!stats || stats.count === 0) return;
        allStats[month] = stats;

        if (!selectedMonths.has(month)) return;

        if (stats.min < globalMin) { globalMin = stats.min; globalMinMonth = month; }
        if (stats.max > globalMax) { globalMax = stats.max; globalMaxMonth = month; }

        const color = MONTH_COLORS[month] || { line: '#333', bg: 'rgba(51,51,51,0.1)' };
        datasets.push({
            label: MONTH_LABELS[month] || month,
            data: vals.slice(0, maxDays),
            borderColor: color.line,
            backgroundColor: color.bg,
            borderWidth: 2.5,
            pointRadius: 3,
            pointHoverRadius: 5,
            tension: 0.3,
            fill: false,
            spanGaps: false,
            monthKey: month
        });
    });

    // Title
    document.getElementById('chartTitle').textContent =
        `${METRIC_NAMES[currentMetric]} - ${FOLDER_NAMES[currentFolder]}`;
    document.getElementById('chartSubtitle').textContent = 'Data harian per bulan (Rp Miliar)';

    // Destroy old chart
    if (chart) { chart.destroy(); }

    const ctx = document.getElementById('mainChart').getContext('2d');
    chart = new Chart(ctx, {
        type: 'line',
        data: { labels: dayLabels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: { top: 30, right: 30, bottom: 10, left: 10 }
            },
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleFont: { size: 13 },
                    bodyFont: { size: 12 },
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        title: (items) => `Hari ke-${items[0].label}`,
                        label: (item) => {
                            const v = item.parsed.y;
                            return v !== null ? `${item.dataset.label}: ${formatNum(v)}` : null;
                        }
                    }
                },
                datalabels: {
                    display: function(ctx) {
                        if (labelMode === 'none') return false;
                        const val = ctx.dataset.data[ctx.dataIndex];
                        if (val === null) return false;
                        if (labelMode === 'all') return true;
                        // edge: show first and last valid points only
                        const ds = ctx.dataset;
                        const vals = ds.data.filter(v => v !== null);
                        if (vals.length === 0) return false;
                        const firstIdx = ds.data.indexOf(vals[0]);
                        const lastIdx = ds.data.lastIndexOf(vals[vals.length - 1]);
                        return ctx.dataIndex === firstIdx || ctx.dataIndex === lastIdx;
                    },
                    color: function(ctx) { return ctx.dataset.borderColor; },
                    font: { size: 9, weight: 'bold' },
                    anchor: 'end',
                    align: 'top',
                    offset: 2,
                    formatter: (v) => formatNum(v)
                }
            },
            scales: {
                x: {
                    title: { display: true, text: 'Hari', font: { size: 12, weight: '600' } },
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: { font: { size: 11 } }
                },
                y: {
                    title: { display: true, text: 'Rp Miliar', font: { size: 12, weight: '600' } },
                    grid: { color: 'rgba(0,0,0,0.06)' },
                    ticks: { font: { size: 11 } }
                }
            }
        },
        plugins: [ChartDataLabels]
    });

    // Custom legend
    let legendHtml = '';
    datasets.forEach(ds => {
        legendHtml += `<div class="legend-item">
            <div class="legend-dot" style="background:${ds.borderColor}"></div>
            <span>${ds.label}</span>
        </div>`;
    });
    document.getElementById('customLegend').innerHTML = legendHtml;

    // Render tables
    renderTables(allStats, headers);
    // Render position table
    await renderPositionTable();
}

async function renderPositionTable() {
    const metrics = ['dpk', 'tabungan', 'giro', 'depo', 'casa'];
    const metricLabels = {
        dpk: 'Total Volume DPK',
        tabungan: 'Volume Tabungan',
        giro: 'Volume Giro',
        depo: 'Volume Deposito',
        casa: 'Volume Simpanan CASA'
    };
    const DAYS_IN_MONTH = { 'Des-2025': 31, 'Jan': 31, 'Feb': 28, 'Mar': 31, 'Apr': 30, 'May': 31, 'Jun': 30, 'Jul': 31, 'Aug': 31, 'Sep': 30, 'Oct': 31, 'Nov': 30, 'Dec': 31 };
    const MONTH_FULL = { 'Des-2025': '-Dec-25', 'Jan': '-Jan-26', 'Feb': '-Feb-26', 'Mar': '-Mar-26', 'Apr': '-Apr-26', 'May': '-May-26', 'Jun': '-Jun-26', 'Jul': '-Jul-26', 'Aug': '-Aug-26', 'Sep': '-Sep-26', 'Oct': '-Oct-26', 'Nov': '-Nov-26', 'Dec': '-Dec-26' };

    const allData = {};
    for (const m of metrics) {
        allData[m] = await loadCSV(currentFolder, m);
    }

    const refMetric = allData['tabungan'] || allData['dpk'];
    if (!refMetric) {
        document.getElementById('posHead').innerHTML = '';
        document.getElementById('posBody').innerHTML = '';
        return;
    }

    const { headers, data } = refMetric;
    const dateColumns = [];

    for (const month of headers) {
        const vals = data[month];
        if (!vals) continue;
        const validIndices = [];
        vals.forEach(function(v, i) { if (v !== null && !isNaN(v)) validIndices.push(i); });
        if (validIndices.length === 0) continue;

        const lastValidDay = validIndices[validIndices.length - 1] + 1;
        const maxDays = DAYS_IN_MONTH[month] || 31;
        const isComplete = lastValidDay >= maxDays;

        if (isComplete) {
            dateColumns.push({ month: month, dayIdx: maxDays - 1, label: maxDays + MONTH_FULL[month] });
        } else {
            if (validIndices.length >= 2) {
                var prevDay = validIndices[validIndices.length - 2];
                dateColumns.push({ month: month, dayIdx: prevDay, label: (prevDay + 1) + MONTH_FULL[month] });
            }
            var lastDay = validIndices[validIndices.length - 1];
            dateColumns.push({ month: month, dayIdx: lastDay, label: (lastDay + 1) + MONTH_FULL[month] });
        }
    }

    var headHtml = '<th>Keterangan</th>';
    dateColumns.forEach(function(dc) { headHtml += '<th>' + dc.label + '</th>'; });
    document.getElementById('posHead').innerHTML = headHtml;

    var bodyHtml = '';
    for (var mi = 0; mi < metrics.length; mi++) {
        var metric = metrics[mi];
        var md = allData[metric];
        bodyHtml += '<tr><td style="text-align:left;font-weight:600;">' + metricLabels[metric] + '</td>';
        for (var di = 0; di < dateColumns.length; di++) {
            var dc = dateColumns[di];
            var val = '-';
            if (md && md.data[dc.month]) {
                var v = md.data[dc.month][dc.dayIdx];
                if (v !== null && v !== undefined && !isNaN(v)) val = formatNum(v);
            }
            var isLast = di === dateColumns.length - 1;
            var style = isLast ? 'font-weight:700;color:#1a237e;' : '';
            bodyHtml += '<td style="' + style + '">' + val + '</td>';
        }
        bodyHtml += '</tr>';
    }
    document.getElementById('posBody').innerHTML = bodyHtml;
}

function renderTables(allStats, headers) {
    const activeMonths = headers.filter(m => allStats[m]);
    const baseMonth = activeMonths.length > 0 ? activeMonths[0] : null;
    const baseAvg = baseMonth ? allStats[baseMonth].avg : 0;
    const baseBottom = baseMonth ? allStats[baseMonth].min : 0;

    function fmtVal(v) {
        if (v === null || v === undefined) return '-';
        if (isKCP) {
            return Math.round(v).toLocaleString('id-ID');
        }
        if (v % 1 === 0) {
            return v >= 1000 ? Math.round(v).toLocaleString('id-ID') : v.toFixed(0);
        }
        return v.toFixed(3);
    }
    function fmtPct(v) {
        if (v === null || v === undefined) return '-';
        return v.toFixed(1) + '%';
    }
    // Only KCP folder uses dot as thousands separator (skip x1000)
    const isKCP = currentFolder === 'csv_kcp_only';

    function fmtMiliar(v) {
        if (v === null || v === undefined) return '<span class="val-neutral">-</span>';
        const miliar = isKCP ? Math.round(v) : Math.round(v * 1000);
        const arrow = miliar > 0 ? '▲' : miliar < 0 ? '▼' : '';
        const cls = miliar > 0 ? 'val-up' : miliar < 0 ? 'val-down' : 'val-neutral';
        const formatted = Math.abs(miliar).toLocaleString('id-ID');
        return `<span class="${cls}">${arrow} ${formatted}</span>`;
    }

    // Average table: MTD = avg ini - avg sebelumnya, YTD = avg ini - avg Des
    let avgHtml = '';
    let prevAvg = null;
    activeMonths.forEach((m, i) => {
        const s = allStats[m];
        const ending = s.last;
        const avg = s.avg;
        const avgRatio = (avg / ending) * 100;
        const mtd = prevAvg !== null ? avg - prevAvg : null;
        const ytd = i > 0 ? avg - baseAvg : null;

        avgHtml += `<tr>
            <td><b>${MONTH_LABELS[m] || m}</b></td>
            <td>${fmtVal(ending)}</td>
            <td>${fmtVal(avg)}</td>
            <td>${fmtPct(avgRatio)}</td>
            <td>${fmtMiliar(mtd)}</td>
            <td>${fmtMiliar(ytd)}</td>
        </tr>`;
        prevAvg = avg;
    });
    document.querySelector('#avgTable tbody').innerHTML = avgHtml;

    // Bottom table: MTD = bottom ini - bottom sebelumnya, YTD = bottom ini - bottom Des
    let bottomHtml = '';
    let prevBottom = null;
    activeMonths.forEach((m, i) => {
        const s = allStats[m];
        const ending = s.last;
        const bottom = s.min;
        const bottomRatio = (bottom / ending) * 100;
        const mtd = prevBottom !== null ? bottom - prevBottom : null;
        const ytd = i > 0 ? bottom - baseBottom : null;

        bottomHtml += `<tr>
            <td><b>${MONTH_LABELS[m] || m}</b></td>
            <td>${fmtVal(ending)}</td>
            <td>${fmtVal(bottom)}</td>
            <td>${fmtPct(bottomRatio)}</td>
            <td>${fmtMiliar(mtd)}</td>
            <td>${fmtMiliar(ytd)}</td>
        </tr>`;
        prevBottom = bottom;
    });
    document.querySelector('#bottomTable tbody').innerHTML = bottomHtml;
}

// Tab click handlers
document.querySelectorAll('#folderTabs .tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('#folderTabs .tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentFolder = btn.dataset.folder;
        selectedMonths.clear();
        renderChart();
    });
});

document.querySelectorAll('#metricTabs .tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('#metricTabs .tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentMetric = btn.dataset.metric;
        selectedMonths.clear();
        renderChart();
    });
});

document.querySelectorAll('#labelMode .tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('#labelMode .tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        labelMode = btn.dataset.label;
        renderChart();
    });
});

function downloadTable(elementId, filename) {
    const el = document.getElementById(elementId);
    html2canvas(el, { backgroundColor: '#ffffff', scale: 2 }).then(canvas => {
        const link = document.createElement('a');
        link.download = `${filename}_${FOLDER_NAMES[currentFolder]}_${METRIC_NAMES[currentMetric]}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
    });
}

function downloadChart() {
    if (!chart) return;

    // Hide title bar sementara
    const titleBar = document.querySelector('.chart-container > div:first-child');
    titleBar.style.display = 'none';

    const el = document.querySelector('.chart-container');
    html2canvas(el, { backgroundColor: '#ffffff', scale: 2 }).then(canvas => {
        titleBar.style.display = 'flex';

        const link = document.createElement('a');
        const timestamp = new Date().toISOString().slice(0,10);
        link.download = `chart_${METRIC_NAMES[currentMetric]}_${FOLDER_NAMES[currentFolder]}_${timestamp}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
    });
}

function sanitizeFilenamePart(str) {
    return String(str).replace(/[^a-zA-Z0-9-_]+/g, '_').replace(/^_+|_+$/g, '');
}

async function exportCurrentSelectionExcel() {
    const result = await loadCSV(currentFolder, currentMetric);
    if (!result) {
        showToast('Data tidak tersedia untuk diexport', 'error');
        return;
    }

    const { headers, data } = result;
    const activeMonths = headers.filter(h => selectedMonths.has(h));
    if (activeMonths.length !== 1) {
        showToast('Pilih tepat 1 bulan dulu untuk export Excel', 'error');
        return;
    }

    const month = activeMonths[0];
    const monthValues = data[month] || [];
    const DAYS_IN_MONTH = {
        'Des-2025': 31, 'Jan': 31, 'Feb': 28, 'Mar': 31, 'Apr': 30, 'May': 31,
        'Jun': 30, 'Jul': 31, 'Aug': 31, 'Sep': 30, 'Oct': 31, 'Nov': 30, 'Dec': 31
    };
    const maxDays = DAYS_IN_MONTH[month] || Math.max(monthValues.length, 31);

    const aoa = [['Tanggal', 'Nilai']];
    for (let day = 1; day <= maxDays; day++) {
        const v = monthValues[day - 1];
        aoa.push([day, (v === null || v === undefined || isNaN(v)) ? '' : v]);
    }

    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(aoa);
    XLSX.utils.book_append_sheet(wb, ws, 'Data');

    const monthLabel = MONTH_LABELS[month] || month;
    const filename = [
        'data',
        sanitizeFilenamePart(METRIC_NAMES[currentMetric]),
        sanitizeFilenamePart(FOLDER_NAMES[currentFolder]),
        sanitizeFilenamePart(monthLabel)
    ].join('_') + '.xlsx';

    XLSX.writeFile(wb, filename);
    showToast(`Excel berhasil diunduh: ${monthLabel}`, 'success');
}

// Initial render
renderChart();

// ===== CRUD FUNCTIONS =====
let crudCacheData = null;

function openCrudModal() {
    document.getElementById('crudModal').classList.add('active');
    // Populate day select
    const daySelect = document.getElementById('crudDay');
    daySelect.innerHTML = '';
    for (let i = 1; i <= 31; i++) {
        daySelect.innerHTML += `<option value="${i}">${i}</option>`;
    }
    // Set folder to match current dashboard selection
    document.getElementById('crudFolder').value = currentFolder;
    loadCrudTable();
}

function closeCrudModal() {
    document.getElementById('crudModal').classList.remove('active');
}

function onCrudFolderChange() {
    loadCrudTable();
}
function onCrudMonthChange() {
    loadCrudTable();
}

function parseKCPValue(str) {
    // KCP tabungan/casa/dpk: "2.070" means 2070 (dot = thousands)
    if (!str || str.trim() === '') return 0;
    return parseFloat(str.replace(/\./g, ''));
}

function parseStdValue(str) {
    // Standard: "35.832" means 35.832
    if (!str || str.trim() === '') return 0;
    return parseFloat(str);
}

function calcCasaDpk() {
    const folder = document.getElementById('crudFolder').value;
    const isKCP = folder === 'csv_kcp_only';
    const isMikro = folder === 'csv_mikro';

    const tabStr = document.getElementById('crudTabungan').value;
    const giroStr = document.getElementById('crudGiro').value;
    const depoStr = document.getElementById('crudDepo').value;

    let tabNum, giroNum, depoNum;
    if (isKCP) {
        tabNum = parseKCPValue(tabStr);
        giroNum = parseFloat(giroStr) || 0;
        depoNum = parseFloat(depoStr) || 0;
    } else {
        tabNum = parseFloat(tabStr) || 0;
        giroNum = parseFloat(giroStr) || 0;
        depoNum = parseFloat(depoStr) || 0;
    }

    // Mikro: Giro dalam Juta, perlu /1000 untuk konversi ke Miliar
    const giroForCalc = isMikro ? giroNum / 1000 : giroNum;
    const casa = tabNum + giroForCalc;
    const dpk = casa + depoNum;

    if (isKCP) {
        document.getElementById('crudCasa').value = casa.toLocaleString('id-ID');
        document.getElementById('crudDpk').value = dpk.toLocaleString('id-ID');
    } else {
        document.getElementById('crudCasa').value = casa.toFixed(3);
        document.getElementById('crudDpk').value = dpk.toFixed(3);
    }
}

async function loadCrudTable() {
    const folder = document.getElementById('crudFolder').value;
    const month = document.getElementById('crudMonth').value;

    try {
        const ts = new Date().getTime();
        const resp = await fetch(`data.php?action=read&folder=${encodeURIComponent(folder)}&_t=${ts}`);
        const json = await resp.json();
        if (!json.success) {
            showToast('Gagal memuat data: ' + (json.error || ''), 'error');
            return;
        }
        crudCacheData = json.data;
        renderCrudTable(folder, month);
    } catch(e) {
        showToast('Error: ' + e.message, 'error');
    }
}

function renderCrudTable(folder, month) {
    const headEl = document.getElementById('crudTableHead');
    const bodyEl = document.getElementById('crudTableBody');
    headEl.innerHTML = '<th>Hari</th><th>Tabungan</th><th>Giro</th><th>Deposito</th><th>CASA</th><th>DPK</th>';

    if (!crudCacheData) {
        bodyEl.innerHTML = '<tr><td colspan="6">Tidak ada data</td></tr>';
        return;
    }

    const metrics = ['tabungan', 'giro', 'depo', 'casa', 'dpk'];
    // Find max rows across all metrics
    let maxRows = 0;
    metrics.forEach(m => {
        if (crudCacheData[m] && crudCacheData[m].data) {
            maxRows = Math.max(maxRows, crudCacheData[m].data.length);
        }
    });

    let html = '';
    for (let i = 0; i < maxRows; i++) {
        const vals = {};
        let hasData = false;
        metrics.forEach(m => {
            const mData = crudCacheData[m];
            if (mData && mData.data && mData.data[i]) {
                const v = mData.data[i][month] || '';
                vals[m] = v;
                if (v !== '') hasData = true;
            } else {
                vals[m] = '';
            }
        });
        if (!hasData) continue;

        html += `<tr onclick="selectCrudRow(${i+1},'${vals.tabungan}','${vals.giro}','${vals.depo}')">
            <td><b>${i+1}</b></td>
            <td>${vals.tabungan || '-'}</td>
            <td>${vals.giro || '-'}</td>
            <td>${vals.depo || '-'}</td>
            <td>${vals.casa || '-'}</td>
            <td>${vals.dpk || '-'}</td>
        </tr>`;
    }

    if (!html) {
        html = '<tr><td colspan="6" style="color:#888;">Belum ada data untuk bulan ini</td></tr>';
    }
    bodyEl.innerHTML = html;
}

function selectCrudRow(day, tabungan, giro, depo) {
    document.getElementById('crudDay').value = day;
    document.getElementById('crudTabungan').value = tabungan;
    document.getElementById('crudGiro').value = giro;
    document.getElementById('crudDepo').value = depo;
    calcCasaDpk();

    // Highlight selected row
    document.querySelectorAll('.crud-data-table tbody tr').forEach(tr => tr.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
}

async function loadExistingData() {
    const folder = document.getElementById('crudFolder').value;
    const month = document.getElementById('crudMonth').value;
    const day = parseInt(document.getElementById('crudDay').value);

    if (!crudCacheData) await loadCrudTable();
    if (!crudCacheData) return;

    const rowIdx = day - 1;
    const metrics = ['tabungan', 'giro', 'depo'];
    const fields = ['crudTabungan', 'crudGiro', 'crudDepo'];

    metrics.forEach((m, i) => {
        const mData = crudCacheData[m];
        let val = '';
        if (mData && mData.data && mData.data[rowIdx]) {
            val = mData.data[rowIdx][month] || '';
        }
        document.getElementById(fields[i]).value = val;
    });

    calcCasaDpk();
    showToast(`Data hari ${day} dimuat`, 'success');
}

async function saveCrudData() {
    const folder = document.getElementById('crudFolder').value;
    const month = document.getElementById('crudMonth').value;
    const day = parseInt(document.getElementById('crudDay').value);
    const tabungan = document.getElementById('crudTabungan').value.trim();
    const giro = document.getElementById('crudGiro').value.trim();
    const depo = document.getElementById('crudDepo').value.trim();

    if (!tabungan && !giro && !depo) {
        showToast('Isi minimal satu field!', 'error');
        return;
    }

    try {
        const resp = await fetch('data.php?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            body: JSON.stringify({ folder, month, day, tabungan, giro, depo })
        });
        const json = await resp.json();
        if (json.success) {
            showToast(`✅ Data hari ${day} berhasil disimpan!`, 'success');
            await loadCrudTable();
            renderChart(); // Refresh dashboard chart
        } else {
            showToast('Gagal: ' + (json.error || ''), 'error');
        }
    } catch(e) {
        showToast('Error: ' + e.message, 'error');
    }
}

async function deleteCrudData() {
    const folder = document.getElementById('crudFolder').value;
    const month = document.getElementById('crudMonth').value;
    const day = parseInt(document.getElementById('crudDay').value);

    if (!confirm(`Hapus data hari ${day} bulan ${month} di ${FOLDER_NAMES[folder]}?`)) return;

    try {
        const resp = await fetch('data.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            body: JSON.stringify({ folder, month, day })
        });
        const json = await resp.json();
        if (json.success) {
            showToast(`🗑️ Data hari ${day} berhasil dihapus!`, 'success');
            document.getElementById('crudTabungan').value = '';
            document.getElementById('crudGiro').value = '';
            document.getElementById('crudDepo').value = '';
            document.getElementById('crudCasa').value = '';
            document.getElementById('crudDpk').value = '';
            await loadCrudTable();
            renderChart();
        } else {
            showToast('Gagal: ' + (json.error || ''), 'error');
        }
    } catch(e) {
        showToast('Error: ' + e.message, 'error');
    }
}

function showToast(msg, type) {
    const existing = document.querySelectorAll('.toast');
    existing.forEach(el => el.remove());

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// Close modal on overlay click
document.getElementById('crudModal').addEventListener('click', function(e) {
    if (e.target === this) closeCrudModal();
});

// ═══════════════════════════════════════════════════════════
//  PAGE SWITCHER
// ═══════════════════════════════════════════════════════════
let blLoaded = false;

function switchPage(page) {
    if (page === 'dpk') {
        document.getElementById('page-dpk').style.display = '';
        document.getElementById('page-brilink').style.display = 'none';
        document.getElementById('navDpk').classList.add('active');
        document.getElementById('navBrilink').classList.remove('active');
        document.getElementById('headerInputBtn').textContent = '✏️ Input Data';
        document.getElementById('headerInputBtn').setAttribute('onclick', 'openCrudModal()');
    } else {
        document.getElementById('page-dpk').style.display = 'none';
        document.getElementById('page-brilink').style.display = 'block';
        document.getElementById('navDpk').classList.remove('active');
        document.getElementById('navBrilink').classList.add('active');
        document.getElementById('headerInputBtn').textContent = '✏️ Input BriLink';
        document.getElementById('headerInputBtn').setAttribute('onclick', 'openInputModal()');
        if (!blLoaded) {
            blLoaded = true;
            blSectionLoaded.vol = true;
            renderVolumeTrx();
        }
    }
}

// ═══════════════════════════════════════════════════════════
//  BRILINK CONFIG
// ═══════════════════════════════════════════════════════════
const META_COLS = {
    volume_trx: ['NAMA','JENIS'],
    casa_agen:  ['NAMA','UKER','NO_REK'],
    casa_uker:  ['NAMA_UKER','JUMLAH_AGEN']
};
const FILE_LABELS = {
    volume_trx: 'Volume TRX Setor',
    casa_agen:  'CASA Posisi Agen',
    casa_uker:  'CASA UKER'
};
const BL_UNITS = {
    volume_trx: 'Rp (Rupiah)',
    casa_agen:  'Rp (Rupiah)',
    casa_uker:  'Miliar'
};
const BL_PALETTE = [
    '#1565c0','#e65100','#2e7d32','#6a1b9a','#c62828',
    '#00838f','#ef6c00','#ad1457','#4527a0','#1b5e20',
    '#ff6f00','#b71c1c','#0d47a1','#33691e','#880e4f',
    '#006064','#bf360c','#4a148c','#827717','#01579b',
    '#37474f','#558b2f'
];

let blData   = { volume_trx: null, casa_agen: null, casa_uker: null };
let blCharts = { chartVol: null, chartAgen: null, chartCasaTrend: null, chartUker: null, chartVolMonthly: null, chartCasaMonthly: null, chartUkerMonthly: null };
let blActiveTab  = 'volume_trx';
let blActiveDate = '';
let blSelectedMonth = ''; // e.g. 'Apr-2026', empty = latest month

const BL_MONTH_COLORS = {
    'Jan': '#0b2f61', 'Feb': '#e65100', 'Mar': '#2e7d32', 'Apr': '#6a1b9a',
    'May': '#c62828', 'Jun': '#00838f', 'Jul': '#0288d1', 'Aug': '#ad1457',
    'Sep': '#4527a0', 'Oct': '#1b5e20', 'Nov': '#ff8f00', 'Dec': '#b71c1c'
};
const BL_MONTH_LABELS_ID = {
    'Jan': 'Jan', 'Feb': 'Feb', 'Mar': 'Mar', 'Apr': 'Apr',
    'May': 'Mei', 'Jun': 'Jun', 'Jul': 'Jul', 'Aug': 'Ags',
    'Sep': 'Sep', 'Oct': 'Okt', 'Nov': 'Nov', 'Dec': 'Des'
};

// Extract month key from CSV date like '23-Apr-2026' → 'Apr-2026'
function blGetMonthKey(dateStr) {
    const p = dateStr.split('-');
    if (p.length !== 3) return '';
    return p[1] + '-' + p[2]; // 'Apr-2026'
}

// Get human-readable month label from key like 'Apr-2026' → 'Apr 2026'
function blGetMonthLabel(monthKey) {
    const p = monthKey.split('-');
    if (p.length !== 2) return monthKey;
    const lbl = BL_MONTH_LABELS_ID[p[0]] || p[0];
    return lbl + ' ' + p[1];
}

// Get color for month key
function blGetMonthColor(monthKey) {
    const p = monthKey.split('-');
    return BL_MONTH_COLORS[p[0]] || '#37474f';
}

// Group date columns by month
function blGroupDatesByMonth(dateCols) {
    const groups = {};
    dateCols.forEach(d => {
        const mk = blGetMonthKey(d);
        if (!mk) return;
        if (!groups[mk]) groups[mk] = [];
        groups[mk].push(d);
    });
    return groups;
}

// Filter date columns by selected month
function blFilterDateColsByMonth(dateCols) {
    if (!blSelectedMonth) return dateCols;
    return dateCols.filter(d => blGetMonthKey(d) === blSelectedMonth);
}

// Render month filter buttons (shared across all BriLink sections)
function blRenderMonthFilter(dateCols) {
    const groups = blGroupDatesByMonth(dateCols);
    const monthKeys = Object.keys(groups);
    if (monthKeys.length === 0) return;

    // Auto-select latest month if not set or invalid
    if (!blSelectedMonth || !monthKeys.includes(blSelectedMonth)) {
        blSelectedMonth = monthKeys[monthKeys.length - 1];
    }

    const container = document.getElementById('blMonthFilter');
    container.innerHTML = '';
    monthKeys.forEach(mk => {
        const btn = document.createElement('button');
        btn.className = 'month-btn' + (mk === blSelectedMonth ? ' active' : '');
        btn.textContent = blGetMonthLabel(mk);
        const color = blGetMonthColor(mk);
        if (mk === blSelectedMonth) {
            btn.style.background = color;
            btn.style.borderColor = color;
        }
        btn.addEventListener('click', () => {
            blSelectedMonth = mk;
            // Re-render current visible section
            const active = ['vol','agen','uker'].find(s =>
                document.getElementById('bl-sec-'+s).style.display !== 'none') || 'vol';
            if (active === 'vol')  renderVolumeTrx();
            if (active === 'agen') renderCasaAgen();
            if (active === 'uker') renderCasaUker();
        });
        container.appendChild(btn);
    });
}

const blToJuta   = v => parseFloat((v / 1e6).toFixed(4));
const blToMiliar = v => parseFloat((v / 1e9).toFixed(6));
const blFmt = (v, d=2) => (+v).toLocaleString('id-ID', {minimumFractionDigits:d, maximumFractionDigits:d});

function blGetDateCols(file, headers) {
    const dates = headers.filter(h => !META_COLS[file].includes(h));
    const mon = {Jan:0,Feb:1,Mar:2,Apr:3,May:4,Jun:5,Jul:6,Aug:7,Sep:8,Oct:9,Nov:10,Dec:11};
    return dates.sort((a, b) => {
        const pa = a.split('-');
        const pb = b.split('-');
        if (pa.length !== 3 || pb.length !== 3) return 0;
        const da = new Date(+pa[2], mon[pa[1]] || 0, +pa[0]);
        const db = new Date(+pb[2], mon[pb[1]] || 0, +pb[0]);
        return da - db;
    });
}
function jsDateToCSV(str) {
    const d = new Date(str + 'T00:00:00');
    const mon = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    return `${String(d.getDate()).padStart(2,'0')}-${mon[d.getMonth()]}-${d.getFullYear()}`;
}
function csvDateToJS(str) {
    const mon = {Jan:0,Feb:1,Mar:2,Apr:3,May:4,Jun:5,Jul:6,Aug:7,Sep:8,Oct:9,Nov:10,Dec:11};
    const p = str.split('-');
    if (p.length !== 3) return '';
    const d = new Date(+p[2], mon[p[1]], +p[0]);
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}
function blAddBadge(el, lbl, val, color='#1a237e') {
    const d = document.createElement('div');
    d.className = 'bl-badge-box';
    d.style.borderLeftColor = color;
    d.innerHTML = `<div class="lbl">${lbl}</div><div class="val" style="color:${color}">${val}</div>`;
    el.appendChild(d);
}
function blDestroy(id) {
    if (blCharts[id]) { blCharts[id].destroy(); blCharts[id] = null; }
}

async function blLoadCSV(file) {
    try {
        const ts = new Date().getTime();
        const r = await fetch(`data.php?action=read_brilink&file=${encodeURIComponent(file)}&_t=${ts}`);
        const j = await r.json();
        if (!j.success) throw new Error(j.error || 'API error');
        return j.data;
    } catch(e) {
        showToast(`Gagal memuat ${file}: ${e.message}`, 'error');
        return null;
    }
}

// ── Chart 1: Volume TRX ──────────────────────────────────────────────────────
let blSelectedVolAgens = new Set(); // empty = all visible
let blSelectedVolKonsolMonths = new Set();
let blSelectedUkerKonsolMonths = new Set();

function blSortMonthKeys(monthGroups) {
    const MONTH_ORDER = {
        'Jan': 1, 'Feb': 2, 'Mar': 3, 'Apr': 4, 'May': 5, 'Jun': 6,
        'Jul': 7, 'Aug': 8, 'Sep': 9, 'Oct': 10, 'Nov': 11, 'Dec': 12
    };
    return Object.keys(monthGroups).sort((a, b) => {
        const [monthA, yearA] = a.split('-');
        const [monthB, yearB] = b.split('-');
        if (yearA !== yearB) return parseInt(yearA) - parseInt(yearB);
        return MONTH_ORDER[monthA] - MONTH_ORDER[monthB];
    });
}

function blRenderKonsolMonthFilter(containerId, monthKeys, selectedSet, onRerender) {
    const needsInit = selectedSet.size === 0 ||
        [...selectedSet].some(m => !monthKeys.includes(m));
    if (needsInit) {
        selectedSet.clear();
        monthKeys.forEach(m => selectedSet.add(m));
    }

    const container = document.getElementById(containerId);
    if (!container) return;
    container.innerHTML = '';
    monthKeys.forEach(mk => {
        const btn = document.createElement('button');
        btn.className = 'month-btn' + (selectedSet.has(mk) ? ' active' : '');
        btn.textContent = blGetMonthLabel(mk);
        const color = blGetMonthColor(mk);
        if (selectedSet.has(mk)) {
            btn.style.background = color;
            btn.style.borderColor = color;
        }
        btn.addEventListener('click', () => {
            if (selectedSet.has(mk)) {
                if (selectedSet.size > 1) selectedSet.delete(mk);
            } else {
                selectedSet.add(mk);
            }
            onRerender();
        });
        container.appendChild(btn);
    });
}

function blGetFilteredVolRows(csv) {
    if (!csv || !csv.data) return [];
    if (blSelectedVolAgens.size === 0) return csv.data;
    return csv.data.filter(row => blSelectedVolAgens.has(row.NAMA));
}

async function renderVolumeTrx() {
    const csv = await blLoadCSV('volume_trx');
    blData.volume_trx = csv;
    if (!csv) return;
    const allDateCols = blGetDateCols('volume_trx', csv.headers);
    if (!allDateCols.length) { document.getElementById('volSubtitle').textContent = 'Belum ada data'; return; }
    blRenderMonthFilter(allDateCols);
    const dateCols = blFilterDateColsByMonth(allDateCols);
    if (!dateCols.length) { document.getElementById('volSubtitle').textContent = 'Tidak ada data bulan ini'; return; }
    document.getElementById('volSubtitle').textContent = `Per tanggal ${dateCols[0]} – ${dateCols[dateCols.length-1]}`;

    // Build agen filter buttons
    renderVolAgenFilterButtons(csv);

    // Render per-agen chart
    renderVolPerAgenChart(csv, dateCols);

    // Render monthly consolidated chart
    renderVolMonthlyChart(csv, allDateCols);
}

function renderVolAgenFilterButtons(csv) {
    const filterTabs = document.getElementById('volAgenFilterTabs');
    filterTabs.innerHTML = '';
    
    // "Semua Agen" reset button
    const resetBtn = document.createElement('button');
    resetBtn.className = 'tab-btn active';
    resetBtn.id = 'volAgenResetBtn';
    resetBtn.textContent = 'Semua Agen';
    resetBtn.onclick = () => {
        blSelectedVolAgens = new Set();
        document.querySelectorAll('#volAgenFilterTabs .tab-btn').forEach(b => {
            b.classList.toggle('active', b === resetBtn);
        });
        updateVolPerAgenChart();
    };
    filterTabs.appendChild(resetBtn);
    
    csv.data.forEach((row, i) => {
        const color = BL_PALETTE[i % BL_PALETTE.length];
        const btn = document.createElement('button');
        btn.className = 'tab-btn';
        btn.style.borderLeft = `4px solid ${color}`;
        btn.textContent = row.NAMA;
        btn.onclick = () => {
            document.getElementById('volAgenResetBtn').classList.remove('active');
            toggleVolAgen(row.NAMA, btn);
            if (blSelectedVolAgens.size === 0)
                document.getElementById('volAgenResetBtn').classList.add('active');
        };
        filterTabs.appendChild(btn);
    });
}

function toggleVolAgen(nama, btn) {
    if (blSelectedVolAgens.has(nama)) {
        blSelectedVolAgens.delete(nama);
        btn.classList.remove('active');
    } else {
        blSelectedVolAgens.add(nama);
        btn.classList.add('active');
    }
    updateVolPerAgenChart();
}

function updateVolPerAgenChart() {
    const chart = blCharts.chartVol;
    if (!chart) return;
    chart.data.datasets.forEach(ds => {
        if (ds.label === 'TOTAL') {
            ds.hidden = blSelectedVolAgens.size > 0;
        } else {
            const isSelected = blSelectedVolAgens.size === 0 || blSelectedVolAgens.has(ds.label);
            ds.hidden = !isSelected;
        }
    });
    chart.update();

    let leg = '';
    chart.data.datasets.forEach(ds => {
        if (!ds.hidden)
            leg += `<div class="legend-item"><div class="legend-dot" style="background:${ds.borderColor}"></div><span>${ds.label}</span></div>`;
    });
    document.getElementById('volLegend').innerHTML = leg;

    const csv = blData.volume_trx;
    if (csv) {
        const allDateCols = blGetDateCols('volume_trx', csv.headers);
        renderVolMonthlyChart(csv, allDateCols);
    }
}

function renderVolPerAgenChart(csv, dateCols) {
    const datasets = csv.data.map((row, i) => ({
        label: row.NAMA,
        data: dateCols.map(d => blToJuta(parseFloat(row[d]) || 0)),
        borderColor: BL_PALETTE[i % BL_PALETTE.length],
        backgroundColor: BL_PALETTE[i % BL_PALETTE.length] + '18',
        borderWidth: 2.5, pointRadius: 5,
        pointBackgroundColor: BL_PALETTE[i % BL_PALETTE.length],
        pointBorderColor: '#fff', pointBorderWidth: 2,
        tension: 0.3, fill: false
    }));
    
    const totalByDate = dateCols.map(d => csv.data.reduce((s, row) => s + (parseFloat(row[d]) || 0), 0));
    datasets.push({
        label: 'TOTAL',
        data: totalByDate.map(blToJuta),
        borderColor: '#37474f', backgroundColor: 'rgba(55,71,79,.05)',
        borderWidth: 3, borderDash: [6, 3], pointRadius: 5,
        pointBackgroundColor: '#37474f', pointBorderColor: '#fff', pointBorderWidth: 2,
        tension: 0.3, fill: false
    });

    const sumEl = document.getElementById('volSummary'); sumEl.innerHTML = '';
    const lastT = blToJuta(totalByDate[totalByDate.length-1]);
    const maxT  = Math.max(...totalByDate.map(blToJuta));
    const maxI  = totalByDate.map(blToJuta).indexOf(maxT);
    blAddBadge(sumEl, `Total ${dateCols[dateCols.length-1]}`, 'Rp '+blFmt(lastT,1)+' Jt', '#1a237e');
    blAddBadge(sumEl, 'Puncak Volume', 'Rp '+blFmt(maxT,1)+' Jt', '#c62828');
    blAddBadge(sumEl, 'Tgl Puncak', dateCols[maxI]||'-', '#2e7d32');
    blAddBadge(sumEl, 'Jumlah Agen', csv.data.length+' agen', '#6a1b9a');

    blDestroy('chartVol');
    blCharts.chartVol = new Chart(document.getElementById('chartVol').getContext('2d'), {
        type: 'line',
        data: { labels: dateCols, datasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            layout: { padding: { top: 30, right: 30, bottom: 10, left: 10 } },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleFont: { size: 13 }, bodyFont: { size: 12 },
                    padding: 12, cornerRadius: 8,
                    callbacks: {
                        title: items => items[0].label,
                        label: c => c.parsed.y !== null ? `${c.dataset.label}: Rp ${blFmt(c.parsed.y, 3)} Jt` : null
                    }
                },
                datalabels: {
                    display: true,
                    color: ctx => ctx.dataset.borderColor,
                    font: { size: 9, weight: 'bold' },
                    anchor: 'end', align: 'top', offset: 2,
                    formatter: v => blFmt(v, 3)
                }
            },
            scales: {
                x: {
                    title: { display: true, text: 'Tanggal', font: { size: 12, weight: '600' } },
                    grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 11 } }
                },
                y: {
                    title: { display: true, text: 'Rp Juta', font: { size: 12, weight: '600' } },
                    grid: { color: 'rgba(0,0,0,0.06)' },
                    ticks: { font: { size: 11 }, callback: v => blFmt(v, 0) }
                }
            }
        }, plugins: [ChartDataLabels]
    });

    let volLeg = '';
    datasets.forEach(ds => {
        volLeg += `<div class="legend-item"><div class="legend-dot" style="background:${ds.borderColor}"></div><span>${ds.label}</span></div>`;
    });
    document.getElementById('volLegend').innerHTML = volLeg;
}

function renderVolMonthlyChart(csv, allDateCols) {
    const filteredRows = blGetFilteredVolRows(csv);
    const monthGroups = blGroupDatesByMonth(allDateCols);

    const subEl = document.getElementById('volMonthlySubtitle');
    if (subEl) {
        if (blSelectedVolAgens.size === 0) {
            subEl.textContent = 'Volume harian per bulan - semua agen (satu garis per bulan)';
        } else if (blSelectedVolAgens.size === 1) {
            subEl.textContent = `Volume harian per bulan - ${[...blSelectedVolAgens][0]}`;
        } else {
            subEl.textContent = `Volume harian per bulan - ${blSelectedVolAgens.size} agen terpilih`;
        }
    }

    const monthKeys = blSortMonthKeys(monthGroups);
    if (monthKeys.length === 0) return;

    blRenderKonsolMonthFilter('volKonsolMonthFilter', monthKeys, blSelectedVolKonsolMonths, () => {
        renderVolMonthlyChart(csv, allDateCols);
    });

    const activeMonthKeys = monthKeys.filter(mk => blSelectedVolKonsolMonths.has(mk));
    if (activeMonthKeys.length === 0) return;

    let maxDays = 0;
    activeMonthKeys.forEach(mk => {
        const days = monthGroups[mk].map(d => parseInt(d.split('-')[0], 10));
        maxDays = Math.max(maxDays, ...days);
    });
    const dayLabels = Array.from({ length: maxDays }, (_, i) => String(i + 1));

    const datasets = [];
    let peakDaily = -Infinity, peakDate = '-', lastDaily = 0, lastDate = '-';

    activeMonthKeys.forEach(mk => {
        const monthLabel = blGetMonthLabel(mk);
        const color = blGetMonthColor(mk);
        const dateMap = {};

        monthGroups[mk].forEach(d => {
            const day = parseInt(d.split('-')[0], 10);
            const dayTotal = blToMiliar(filteredRows.reduce((s, row) => s + (parseFloat(row[d]) || 0), 0));
            dateMap[day] = dayTotal;
            if (dayTotal > peakDaily) { peakDaily = dayTotal; peakDate = d; }
        });

        const data = dayLabels.map(dayStr => {
            const day = parseInt(dayStr, 10);
            return dateMap[day] !== undefined ? dateMap[day] : null;
        });

        const lastDayInMonth = monthGroups[mk][monthGroups[mk].length - 1];
        lastDaily = dateMap[parseInt(lastDayInMonth.split('-')[0], 10)] || 0;
        lastDate = lastDayInMonth;

        datasets.push({
            label: monthLabel,
            data,
            borderColor: color,
            backgroundColor: color + '18',
            borderWidth: 2.5,
            pointRadius: 4,
            pointBackgroundColor: color,
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            tension: 0.3,
            fill: false,
            spanGaps: false
        });
    });

    const sumEl = document.getElementById('volMonthlySummary');
    sumEl.innerHTML = '';
    blAddBadge(sumEl, `Total ${lastDate}`, blFmt(lastDaily, 3) + ' M', '#1a237e');
    blAddBadge(sumEl, 'Puncak Harian', blFmt(peakDaily, 3) + ' M', '#c62828');
    blAddBadge(sumEl, 'Tgl Puncak', peakDate, '#2e7d32');
    if (blSelectedVolAgens.size > 0) {
        blAddBadge(sumEl, 'Agen Terpilih', blSelectedVolAgens.size + ' agen', '#6a1b9a');
    } else {
        blAddBadge(sumEl, 'Bulan Tampil', activeMonthKeys.length + ' bulan', '#6a1b9a');
    }

    blDestroy('chartVolMonthly');
    blCharts.chartVolMonthly = new Chart(document.getElementById('chartVolMonthly').getContext('2d'), {
        type: 'line',
        data: { labels: dayLabels, datasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            layout: { padding: { top: 30, right: 30, bottom: 10, left: 10 } },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleFont: { size: 13 }, bodyFont: { size: 12 },
                    padding: 12, cornerRadius: 8,
                    callbacks: {
                        title: items => `Hari ke-${items[0].label}`,
                        label: c => c.parsed.y !== null
                            ? `${c.dataset.label}: Rp ${blFmt(c.parsed.y, 4)} Miliar`
                            : null
                    }
                },
                datalabels: {
                    display: ctx => ctx.dataset.data[ctx.dataIndex] !== null,
                    color: ctx => ctx.dataset.borderColor,
                    font: { size: 9, weight: 'bold' },
                    anchor: 'end', align: 'top', offset: 2,
                    formatter: v => blFmt(v, 3)
                }
            },
            scales: {
                x: {
                    title: { display: true, text: 'Tanggal', font: { size: 12, weight: '600' } },
                    grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 11 } }
                },
                y: {
                    title: { display: true, text: 'Rp Miliar', font: { size: 12, weight: '600' } },
                    grid: { color: 'rgba(0,0,0,0.06)' },
                    ticks: { font: { size: 11 }, callback: v => blFmt(v, 2) }
                }
            }
        }, plugins: [ChartDataLabels]
    });

    let leg = '';
    datasets.forEach(ds => {
        leg += `<div class="legend-item"><div class="legend-dot" style="background:${ds.borderColor}"></div><span>${ds.label}</span></div>`;
    });
    document.getElementById('volMonthlyLegend').innerHTML = leg;
}

// ── Chart 2: CASA Agen ───────────────────────────────────────────────────────
let blActiveUker = 'Semua';
let blSelectedAgens = new Set(); // empty = all visible

async function renderCasaAgen() {
    const csv = await blLoadCSV('casa_agen');
    blData.casa_agen = csv;
    if (!csv) return;
    const allDateCols = blGetDateCols('casa_agen', csv.headers);
    if (!allDateCols.length) return;
    blRenderMonthFilter(allDateCols);

    // Build UKER filter tabs
    const ukers = ['Semua', ...new Set(csv.data.map(r => r.UKER).filter(Boolean))];
    const tabsEl = document.getElementById('ukerFilterTabs');
    tabsEl.innerHTML = '';
    ukers.forEach(u => {
        const btn = document.createElement('button');
        btn.className = 'tab-btn' + (u === blActiveUker ? ' active' : '');
        btn.textContent = u;
        btn.onclick = () => filterAgenByUker(u, btn);
        tabsEl.appendChild(btn);
    });

    // Render chart + trend with current filter
    blSelectedAgens = new Set();
    const dateCols = blFilterDateColsByMonth(allDateCols);
    _renderAgenChart(csv, dateCols, blActiveUker);
}

function filterAgenByUker(uker, btn) {
    blActiveUker = uker;
    blSelectedAgens = new Set();
    document.querySelectorAll('#ukerFilterTabs .tab-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    const csv = blData.casa_agen;
    if (!csv) return;
    const allDateCols = blGetDateCols('casa_agen', csv.headers);
    const dateCols = blFilterDateColsByMonth(allDateCols);
    _renderAgenChart(csv, dateCols, uker);
}

function toggleAgen(nama, btn) {
    if (blSelectedAgens.has(nama)) {
        blSelectedAgens.delete(nama);
        btn.classList.remove('active');
    } else {
        blSelectedAgens.add(nama);
        btn.classList.add('active');
    }
    const csv = blData.casa_agen;
    if (!csv) return;
    const dateCols = blGetDateCols('casa_agen', csv.headers);
    _updateAgenChart(csv, dateCols);
}

function _updateAgenChart(csv, dateCols) {
    const chart = blCharts.chartAgen;
    if (!chart) return;
    chart.data.datasets.forEach(ds => {
        const isSelected = blSelectedAgens.size === 0 || blSelectedAgens.has(ds.label);
        ds.hidden = !isSelected;
    });
    chart.update();
    // Sync legend
    let leg = '';
    chart.data.datasets.forEach(ds => {
        if (!ds.hidden)
            leg += `<div class="legend-item"><div class="legend-dot" style="background:${ds.borderColor}"></div><span>${ds.label}</span></div>`;
    });
    document.getElementById('agenLegend').innerHTML = leg;
}

function _renderAgenChart(csv, dateCols, ukerFilter) {
    const lastDate = dateCols[dateCols.length - 1];
    const rows = ukerFilter === 'Semua' ? csv.data : csv.data.filter(r => r.UKER === ukerFilter);

    // Agent info table
    const infoBox = document.getElementById('agenInfoBox');
    if (ukerFilter !== 'Semua') {
        infoBox.style.display = '';
        document.getElementById('agenInfoTitle').textContent =
            `Daftar Agen – ${ukerFilter} (${rows.length} agen)`;
        let tbHtml = '';
        rows.forEach((r, i) => {
            tbHtml += `<tr><td>${i+1}</td><td>${r.NAMA}</td><td>${r.UKER}</td><td>${r.NO_REK||'-'}</td></tr>`;
        });
        document.getElementById('agenInfoTable').querySelector('tbody').innerHTML = tbHtml;
    } else {
        infoBox.style.display = 'none';
    }

    // Agent toggle buttons
    const filterRow = document.getElementById('agenFilterRow');
    const filterTabs = document.getElementById('agenFilterTabs');
    filterRow.style.display = '';
    filterTabs.innerHTML = '';
    // "Semua Agen" reset button
    const resetBtn = document.createElement('button');
    resetBtn.className = 'tab-btn active';
    resetBtn.id = 'agenResetBtn';
    resetBtn.textContent = 'Semua Agen';
    resetBtn.onclick = () => {
        blSelectedAgens = new Set();
        document.querySelectorAll('#agenFilterTabs .tab-btn').forEach(b => {
            b.classList.toggle('active', b === resetBtn);
        });
        _updateAgenChart(csv, dateCols);
    };
    filterTabs.appendChild(resetBtn);
    rows.forEach((row, i) => {
        const color = BL_PALETTE[i % BL_PALETTE.length];
        const btn = document.createElement('button');
        btn.className = 'tab-btn';
        btn.style.borderLeft = `4px solid ${color}`;
        btn.textContent = row.NAMA;
        btn.onclick = () => {
            document.getElementById('agenResetBtn').classList.remove('active');
            toggleAgen(row.NAMA, btn);
            if (blSelectedAgens.size === 0)
                document.getElementById('agenResetBtn').classList.add('active');
        };
        filterTabs.appendChild(btn);
    });

    document.getElementById('agenChartTitle').textContent =
        ukerFilter === 'Semua'
        ? `Tren CASA Harian per Agen (Rp Juta)`
        : `Tren CASA – ${ukerFilter} (Rp Juta)`;


    const agenDatasets = rows.map((row, i) => ({
        label: row.NAMA,
        data: dateCols.map(d => blToJuta(parseFloat(row[d]) || 0)),
        borderColor: BL_PALETTE[i % BL_PALETTE.length],
        backgroundColor: BL_PALETTE[i % BL_PALETTE.length] + '18',
        borderWidth: 2, pointRadius: 4,
        pointBackgroundColor: BL_PALETTE[i % BL_PALETTE.length],
        pointBorderColor: '#fff', pointBorderWidth: 2,
        tension: 0.3, fill: false
    }));

    blDestroy('chartAgen');
    blCharts.chartAgen = new Chart(document.getElementById('chartAgen').getContext('2d'), {
        type: 'line',
        data: { labels: dateCols, datasets: agenDatasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            layout: { padding: { top: 30, right: 60, bottom: 10, left: 10 } },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleFont: { size: 13 }, bodyFont: { size: 12 },
                    padding: 12, cornerRadius: 8,
                    callbacks: {
                        title: items => items[0].label,
                        label: c => c.parsed.y !== null ? `${c.dataset.label}: Rp ${blFmt(c.parsed.y, 3)} Jt` : null
                    }
                },
                datalabels: {
                    display: true,
                    color: ctx => ctx.dataset.borderColor,
                    font: { size: 9, weight: 'bold' },
                    anchor: 'end', align: 'top', offset: 2,
                    formatter: v => blFmt(v, 2)
                }
            },
            scales: {
                x: {
                    title: { display: true, text: 'Tanggal', font: { size: 12, weight: '600' } },
                    grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 11 } }
                },
                y: {
                    title: { display: true, text: 'Rp Juta', font: { size: 12, weight: '600' } },
                    grid: { color: 'rgba(0,0,0,0.06)' },
                    ticks: { font: { size: 11 }, callback: v => blFmt(v, 0) }
                }
            }
        }, plugins: [ChartDataLabels]
    });

    let agenLeg = '';
    agenDatasets.forEach(ds => {
        agenLeg += `<div class="legend-item"><div class="legend-dot" style="background:${ds.borderColor}"></div><span>${ds.label}</span></div>`;
    });
    document.getElementById('agenLegend').innerHTML = agenLeg;

    const totalByDate = dateCols.map(d => blToMiliar(csv.data.reduce((s, row) => s + (parseFloat(row[d]) || 0), 0)));
    const sumEl = document.getElementById('casaSummary'); sumEl.innerHTML = '';
    const last = totalByDate[totalByDate.length-1];
    blAddBadge(sumEl, `CASA ${lastDate}`, blFmt(last,3)+' M', '#1a237e');

    const trendDataset = [{ label: 'Total CASA Agen',
        data: totalByDate, borderColor: '#1a237e', backgroundColor: 'rgba(26,35,126,.07)',
        borderWidth: 2.5, pointRadius: 5,
        pointBackgroundColor: '#1a237e', pointBorderColor: '#fff', pointBorderWidth: 2,
        tension: 0.3, fill: true }];

    blDestroy('chartCasaTrend');
    blCharts.chartCasaTrend = new Chart(document.getElementById('chartCasaTrend').getContext('2d'), {
        type: 'line',
        data: { labels: dateCols, datasets: trendDataset },
        options: {
            responsive: true, maintainAspectRatio: false,
            layout: { padding: { top: 30, right: 30, bottom: 10, left: 10 } },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleFont: { size: 13 }, bodyFont: { size: 12 },
                    padding: 12, cornerRadius: 8,
                    callbacks: {
                        title: items => items[0].label,
                        label: c => `Total CASA: Rp ${blFmt(c.parsed.y, 4)} Miliar`
                    }
                },
                datalabels: {
                    display: true, color: '#1a237e',
                    font: { size: 9, weight: 'bold' },
                    anchor: 'end', align: 'top', offset: 2,
                    formatter: v => blFmt(v, 3)
                }
            },
            scales: {
                x: {
                    title: { display: true, text: 'Tanggal', font: { size: 12, weight: '600' } },
                    grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 11 } }
                },
                y: {
                    title: { display: true, text: 'Rp Miliar', font: { size: 12, weight: '600' } },
                    grid: { color: 'rgba(0,0,0,0.06)' },
                    ticks: { font: { size: 11 }, callback: v => blFmt(v, 3) }
                }
            }
        }, plugins: [ChartDataLabels]
    });

    document.getElementById('trendLegend').innerHTML =
        `<div class="legend-item"><div class="legend-dot" style="background:#1a237e"></div><span>Total CASA Agen (Rp Miliar)</span></div>`;

    // Render monthly consolidated chart
    const allDateCols = blGetDateCols('casa_agen', csv.headers);
    renderCasaMonthlyChart(csv, allDateCols);
}

function renderCasaMonthlyChart(csv, allDateCols) {
    // Group data by month
    const monthGroups = blGroupDatesByMonth(allDateCols);
    
    // Sort month keys chronologically (not alphabetically)
    const MONTH_ORDER = {
        'Jan': 1, 'Feb': 2, 'Mar': 3, 'Apr': 4, 'May': 5, 'Jun': 6,
        'Jul': 7, 'Aug': 8, 'Sep': 9, 'Oct': 10, 'Nov': 11, 'Dec': 12
    };
    
    const monthKeys = Object.keys(monthGroups).sort((a, b) => {
        // Parse 'Apr-2026' format
        const [monthA, yearA] = a.split('-');
        const [monthB, yearB] = b.split('-');
        
        // Compare year first, then month
        if (yearA !== yearB) {
            return parseInt(yearA) - parseInt(yearB);
        }
        return MONTH_ORDER[monthA] - MONTH_ORDER[monthB];
    });
    
    if (monthKeys.length === 0) return;

    // Calculate TOTAL per month (sum all days in the month)
    const monthlyTotal = monthKeys.map(mk => {
        const dates = monthGroups[mk];
        let totalSum = 0;
        dates.forEach(d => {
            const dayTotal = csv.data.reduce((s, row) => s + (parseFloat(row[d]) || 0), 0);
            totalSum += dayTotal;
        });
        return blToMiliar(totalSum);
    });

    const sumEl = document.getElementById('casaMonthlySummary'); 
    sumEl.innerHTML = '';
    const lastMonthTotal = monthlyTotal[monthlyTotal.length - 1];
    const lastMonthLabel = blGetMonthLabel(monthKeys[monthKeys.length - 1]);
    const maxMonth = Math.max(...monthlyTotal);
    const maxMonthIdx = monthlyTotal.indexOf(maxMonth);
    const grandTotal = monthlyTotal.reduce((a, b) => a + b, 0);
    
    blAddBadge(sumEl, `Total ${lastMonthLabel}`, blFmt(lastMonthTotal, 3) + ' M', '#1a237e');
    blAddBadge(sumEl, 'Puncak Bulanan', blFmt(maxMonth, 3) + ' M', '#c62828');
    blAddBadge(sumEl, 'Bulan Puncak', blGetMonthLabel(monthKeys[maxMonthIdx]), '#2e7d32');
    blAddBadge(sumEl, 'Grand Total', blFmt(grandTotal, 3) + ' M', '#6a1b9a');

    const labels = monthKeys.map(mk => blGetMonthLabel(mk));
    
    const datasets = [
        {
            label: 'Total Bulanan',
            data: monthlyTotal,
            borderColor: '#1a237e',
            backgroundColor: 'rgba(26,35,126,0.1)',
            borderWidth: 3,
            pointRadius: 6,
            pointBackgroundColor: '#1a237e',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            tension: 0.3,
            fill: false
        }
    ];

    blDestroy('chartCasaMonthly');
    blCharts.chartCasaMonthly = new Chart(document.getElementById('chartCasaMonthly').getContext('2d'), {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            layout: { padding: { top: 30, right: 30, bottom: 10, left: 10 } },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleFont: { size: 13 }, bodyFont: { size: 12 },
                    padding: 12, cornerRadius: 8,
                    callbacks: {
                        title: items => items[0].label,
                        label: c => `${c.dataset.label}: Rp ${blFmt(c.parsed.y, 4)} Miliar`
                    }
                },
                datalabels: {
                    display: true,
                    color: ctx => ctx.dataset.borderColor,
                    font: { size: 10, weight: 'bold' },
                    anchor: 'end', align: 'top', offset: 2,
                    formatter: v => blFmt(v, 3)
                }
            },
            scales: {
                x: {
                    title: { display: true, text: 'Bulan', font: { size: 12, weight: '600' } },
                    grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 11 } }
                },
                y: {
                    title: { display: true, text: 'Rp Miliar', font: { size: 12, weight: '600' } },
                    grid: { color: 'rgba(0,0,0,0.06)' },
                    ticks: { font: { size: 11 }, callback: v => blFmt(v, 2) }
                }
            }
        }, plugins: [ChartDataLabels]
    });

    let leg = '';
    datasets.forEach(ds => {
        leg += `<div class="legend-item"><div class="legend-dot" style="background:${ds.borderColor}"></div><span>${ds.label}</span></div>`;
    });
    document.getElementById('casaMonthlyLegend').innerHTML = leg;
}

// ── Chart 3: CASA UKER ───────────────────────────────────────────────────────
let blSelectedUkers = new Set(); // empty = all visible

function blGetUkerLabel(row) {
    return `${row.NAMA_UKER} (${row.JUMLAH_AGEN} agen)`;
}

function blGetFilteredUkerRows(csv) {
    if (!csv || !csv.data) return [];
    if (blSelectedUkers.size === 0) return csv.data;
    return csv.data.filter(row => blSelectedUkers.has(blGetUkerLabel(row)));
}

async function renderCasaUker() {
    const csv = await blLoadCSV('casa_uker');
    blData.casa_uker = csv;
    if (!csv) return;
    // Build month filter and use selected month if set
    const allDateCols = blGetDateCols('casa_uker', csv.headers);
    if (!allDateCols.length) return;
    blRenderMonthFilter(allDateCols);
    const dateCols = blFilterDateColsByMonth(allDateCols);
    if (!dateCols.length) {
        document.getElementById('ukerSummary').textContent = 'Tidak ada data untuk bulan yang dipilih';
        return;
    }

    const UKER_COLORS = ['#1565c0','#e65100','#2e7d32','#6a1b9a','#c62828','#00838f'];
    const datasets = csv.data.map((row, i) => ({
        label: `${row.NAMA_UKER} (${row.JUMLAH_AGEN} agen)`,
        data: dateCols.map(d => parseFloat(parseFloat(row[d]||0).toFixed(3))),
        borderColor: UKER_COLORS[i]||BL_PALETTE[i],
        backgroundColor: (UKER_COLORS[i]||BL_PALETTE[i])+'18',
        borderWidth: 2.5, pointRadius: 5,
        pointBackgroundColor: UKER_COLORS[i]||BL_PALETTE[i],
        pointBorderColor: '#fff', pointBorderWidth: 2,
        tension: 0.3, fill: false
    }));
    const totalByDate = dateCols.map(d => csv.data.reduce((s,row) => s+(parseFloat(row[d])||0), 0));
    datasets.push({
        label: 'TOTAL', data: totalByDate.map(v => parseFloat(v.toFixed(3))),
        borderColor: '#37474f', backgroundColor: 'rgba(55,71,79,.05)',
        borderWidth: 3, borderDash: [6, 3], pointRadius: 5,
        pointBackgroundColor: '#37474f', pointBorderColor: '#fff', pointBorderWidth: 2,
        tension: 0.3, fill: false
    });

    const sumEl = document.getElementById('ukerSummary'); sumEl.innerHTML = '';
    const lastT = totalByDate[totalByDate.length-1];
    blAddBadge(sumEl, `Total ${dateCols[dateCols.length-1]}`, blFmt(lastT,3)+' M', '#1a237e');

    // Build UKER line toggle buttons
    blSelectedUkers = new Set();
    const filterTabs = document.getElementById('ukerLineFilterTabs');
    filterTabs.innerHTML = '';
    const resetBtn = document.createElement('button');
    resetBtn.className = 'tab-btn active';
    resetBtn.id = 'ukerLineResetBtn';
    resetBtn.textContent = 'Semua';
    resetBtn.onclick = () => {
        blSelectedUkers = new Set();
        document.querySelectorAll('#ukerLineFilterTabs .tab-btn').forEach(b =>
            b.classList.toggle('active', b === resetBtn));
        _updateUkerChart();
    };
    filterTabs.appendChild(resetBtn);
    // UKER buttons (exclude TOTAL)
    datasets.slice(0, -1).forEach((ds, i) => {
        const color = UKER_COLORS[i]||BL_PALETTE[i];
        const btn = document.createElement('button');
        btn.className = 'tab-btn';
        btn.style.borderLeft = `4px solid ${color}`;
        btn.textContent = csv.data[i].NAMA_UKER;
        btn.onclick = () => {
            document.getElementById('ukerLineResetBtn').classList.remove('active');
            const lbl = ds.label;
            if (blSelectedUkers.has(lbl)) {
                blSelectedUkers.delete(lbl);
                btn.classList.remove('active');
            } else {
                blSelectedUkers.add(lbl);
                btn.classList.add('active');
            }
            if (blSelectedUkers.size === 0)
                document.getElementById('ukerLineResetBtn').classList.add('active');
            _updateUkerChart();
        };
        filterTabs.appendChild(btn);
    });
    // TOTAL toggle
    const totalBtn = document.createElement('button');
    totalBtn.className = 'tab-btn active';
    totalBtn.id = 'ukerTotalBtn';
    totalBtn.style.borderLeft = '4px solid #37474f';
    totalBtn.textContent = 'TOTAL';
    totalBtn.onclick = () => {
        totalBtn.classList.toggle('active');
        const chart = blCharts.chartUker;
        if (!chart) return;
        const totalDs = chart.data.datasets[chart.data.datasets.length - 1];
        totalDs.hidden = !totalDs.hidden;
        chart.update();
        // Sync legend
        let leg = '';
        chart.data.datasets.forEach(ds => {
            if (!ds.hidden)
                leg += `<div class="legend-item"><div class="legend-dot" style="background:${ds.borderColor}"></div><span>${ds.label}</span></div>`;
        });
        document.getElementById('ukerLegend').innerHTML = leg;
    };
    filterTabs.appendChild(totalBtn);

    blDestroy('chartUker');
    blCharts.chartUker = new Chart(document.getElementById('chartUker').getContext('2d'), {
        type: 'line',
        data: { labels: dateCols, datasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            layout: { padding: { top: 30, right: 30, bottom: 10, left: 10 } },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleFont: { size: 13 }, bodyFont: { size: 12 },
                    padding: 12, cornerRadius: 8,
                    callbacks: {
                        title: items => items[0].label,
                        label: c => !c.dataset.hidden && c.parsed.y !== null ? `${c.dataset.label}: ${blFmt(c.parsed.y, 3)} M` : null
                    }
                },
                datalabels: {
                    display: true,
                    color: ctx => ctx.dataset.borderColor,
                    font: { size: 9, weight: 'bold' },
                    anchor: 'end', align: 'top', offset: 2,
                    formatter: v => blFmt(v, 3)
                }
            },
            scales: {
                x: {
                    title: { display: true, text: 'Tanggal', font: { size: 12, weight: '600' } },
                    grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 11 } }
                },
                y: {
                    title: { display: true, text: 'Rp Miliar', font: { size: 12, weight: '600' } },
                    grid: { color: 'rgba(0,0,0,0.06)' },
                    ticks: { font: { size: 11 }, callback: v => blFmt(v, 0) }
                }
            }
        }, plugins: [ChartDataLabels]
    });

    let ukerLeg = '';
    datasets.forEach(ds => {
        ukerLeg += `<div class="legend-item"><div class="legend-dot" style="background:${ds.borderColor}"></div><span>${ds.label}</span></div>`;
    });
    document.getElementById('ukerLegend').innerHTML = ukerLeg;

    // Render monthly consolidated chart per UKER
    renderUkerMonthlyChart(csv, allDateCols);
}

function _updateUkerChart() {
    const chart = blCharts.chartUker;
    if (!chart) return;
    chart.data.datasets.forEach((ds, i) => {
        if (i === chart.data.datasets.length - 1) {
            ds.hidden = blSelectedUkers.size > 0;
            return;
        }
        ds.hidden = blSelectedUkers.size > 0 && !blSelectedUkers.has(ds.label);
    });
    chart.update();

    const lastIdx = chart.data.labels.length - 1;
    const lastDate = chart.data.labels[lastIdx];
    const visibleSum = chart.data.datasets
        .slice(0, -1)
        .filter(ds => !ds.hidden)
        .reduce((s, ds) => s + (ds.data[lastIdx] || 0), 0);
    const sumEl = document.getElementById('ukerSummary');
    sumEl.innerHTML = '';
    blAddBadge(sumEl, `Total ${lastDate}`, blFmt(visibleSum, 3) + ' M', '#1a237e');

    let leg = '';
    chart.data.datasets.forEach(ds => {
        if (!ds.hidden)
            leg += `<div class="legend-item"><div class="legend-dot" style="background:${ds.borderColor}"></div><span>${ds.label}</span></div>`;
    });
    document.getElementById('ukerLegend').innerHTML = leg;

    const csv = blData.casa_uker;
    if (csv) {
        const allDateCols = blGetDateCols('casa_uker', csv.headers);
        renderUkerMonthlyChart(csv, allDateCols);
    }
}

function renderUkerMonthlyChart(csv, allDateCols) {
    const filteredRows = blGetFilteredUkerRows(csv);
    const monthGroups = blGroupDatesByMonth(allDateCols);

    const subEl = document.getElementById('ukerMonthlySubtitle');
    if (subEl) {
        if (blSelectedUkers.size === 0) {
            subEl.textContent = 'CASA harian per bulan - semua UKER (satu garis per bulan)';
        } else if (blSelectedUkers.size === 1) {
            subEl.textContent = `CASA harian per bulan - ${filteredRows[0]?.NAMA_UKER || 'UKER terpilih'}`;
        } else {
            subEl.textContent = `CASA harian per bulan - ${blSelectedUkers.size} UKER terpilih`;
        }
    }

    const monthKeys = blSortMonthKeys(monthGroups);
    if (monthKeys.length === 0) return;

    blRenderKonsolMonthFilter('ukerKonsolMonthFilter', monthKeys, blSelectedUkerKonsolMonths, () => {
        renderUkerMonthlyChart(csv, allDateCols);
    });

    const activeMonthKeys = monthKeys.filter(mk => blSelectedUkerKonsolMonths.has(mk));
    if (activeMonthKeys.length === 0) return;

    let maxDays = 0;
    activeMonthKeys.forEach(mk => {
        const days = monthGroups[mk].map(d => parseInt(d.split('-')[0], 10));
        maxDays = Math.max(maxDays, ...days);
    });
    const dayLabels = Array.from({ length: maxDays }, (_, i) => String(i + 1));

    const datasets = [];
    let peakDaily = -Infinity, peakDate = '-', lastDaily = 0, lastDate = '-';

    activeMonthKeys.forEach(mk => {
        const monthLabel = blGetMonthLabel(mk);
        const color = blGetMonthColor(mk);
        const dateMap = {};

        monthGroups[mk].forEach(d => {
            const day = parseInt(d.split('-')[0], 10);
            const dayTotal = parseFloat(filteredRows.reduce((s, row) => s + (parseFloat(row[d]) || 0), 0).toFixed(3));
            dateMap[day] = dayTotal;
            if (dayTotal > peakDaily) { peakDaily = dayTotal; peakDate = d; }
        });

        const data = dayLabels.map(dayStr => {
            const day = parseInt(dayStr, 10);
            return dateMap[day] !== undefined ? dateMap[day] : null;
        });

        const lastDayInMonth = monthGroups[mk][monthGroups[mk].length - 1];
        lastDaily = dateMap[parseInt(lastDayInMonth.split('-')[0], 10)] || 0;
        lastDate = lastDayInMonth;

        datasets.push({
            label: monthLabel,
            data,
            borderColor: color,
            backgroundColor: color + '18',
            borderWidth: 2.5,
            pointRadius: 4,
            pointBackgroundColor: color,
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            tension: 0.3,
            fill: false,
            spanGaps: false
        });
    });

    const sumEl = document.getElementById('ukerMonthlySummary');
    sumEl.innerHTML = '';
    blAddBadge(sumEl, `Total ${lastDate}`, blFmt(lastDaily, 3) + ' M', '#1a237e');
    blAddBadge(sumEl, 'Puncak Harian', blFmt(peakDaily, 3) + ' M', '#c62828');
    blAddBadge(sumEl, 'Tgl Puncak', peakDate, '#2e7d32');
    if (blSelectedUkers.size > 0) {
        blAddBadge(sumEl, 'UKER Terpilih', blSelectedUkers.size + ' unit', '#6a1b9a');
    } else {
        blAddBadge(sumEl, 'Bulan Tampil', activeMonthKeys.length + ' bulan', '#6a1b9a');
    }

    blDestroy('chartUkerMonthly');
    blCharts.chartUkerMonthly = new Chart(document.getElementById('chartUkerMonthly').getContext('2d'), {
        type: 'line',
        data: { labels: dayLabels, datasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            layout: { padding: { top: 30, right: 30, bottom: 10, left: 10 } },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleFont: { size: 13 }, bodyFont: { size: 12 },
                    padding: 12, cornerRadius: 8,
                    callbacks: {
                        title: items => `Hari ke-${items[0].label}`,
                        label: c => c.parsed.y !== null
                            ? `${c.dataset.label}: Rp ${blFmt(c.parsed.y, 4)} Miliar`
                            : null
                    }
                },
                datalabels: {
                    display: ctx => ctx.dataset.data[ctx.dataIndex] !== null,
                    color: ctx => ctx.dataset.borderColor,
                    font: { size: 9, weight: 'bold' },
                    anchor: 'end', align: 'top', offset: 2,
                    formatter: v => blFmt(v, 3)
                }
            },
            scales: {
                x: {
                    title: { display: true, text: 'Tanggal', font: { size: 12, weight: '600' } },
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: { font: { size: 11 } }
                },
                y: {
                    title: { display: true, text: 'Rp Miliar', font: { size: 12, weight: '600' } },
                    grid: { color: 'rgba(0,0,0,0.06)' },
                    ticks: { font: { size: 11 }, callback: v => blFmt(v, 2) }
                }
            }
        },
        plugins: [ChartDataLabels]
    });

    let leg = '';
    datasets.forEach(ds => {
        leg += `<div class="legend-item"><div class="legend-dot" style="background:${ds.borderColor}"></div><span>${ds.label}</span></div>`;
    });
    document.getElementById('ukerMonthlyLegend').innerHTML = leg;

    const breakdownContainer = document.getElementById('ukerMonthlyBreakdownContainer');
    if (breakdownContainer) breakdownContainer.innerHTML = '';
}

function renderUkerMonthlyBreakdown(csv, allDateCols) {
    const container = document.getElementById('ukerMonthlyBreakdownContainer');
    
    // If no UKER selected, don't show breakdown
    if (blSelectedUkers.size === 0) {
        container.innerHTML = '';
        return;
    }
    
    // Group dates by month
    const monthGroups = blGroupDatesByMonth(allDateCols);
    const MONTH_ORDER = {
        'Jan': 1, 'Feb': 2, 'Mar': 3, 'Apr': 4, 'May': 5, 'Jun': 6,
        'Jul': 7, 'Aug': 8, 'Sep': 9, 'Oct': 10, 'Nov': 11, 'Dec': 12
    };
    
    const monthKeys = Object.keys(monthGroups).sort((a, b) => {
        const [monthA, yearA] = a.split('-');
        const [monthB, yearB] = b.split('-');
        if (yearA !== yearB) return parseInt(yearA) - parseInt(yearB);
        return MONTH_ORDER[monthA] - MONTH_ORDER[monthB];
    });
    
    // Get selected UKER data
    const selectedUkerData = csv.data.filter(row => {
        const label = `${row.NAMA_UKER} (${row.JUMLAH_AGEN} agen)`;
        return blSelectedUkers.has(label);
    });
    
    if (selectedUkerData.length === 0) {
        container.innerHTML = '';
        return;
    }
    
    const UKER_COLORS = ['#1565c0','#e65100','#2e7d32','#6a1b9a','#c62828','#00838f'];
    
    // Create ONE card with ONE chart containing lines per month
    container.innerHTML = '';
    
    const card = document.createElement('div');
    card.className = 'bl-card';
    card.style.marginTop = '20px';
    
    const chartId = 'chartUkerMonthlyBreakdown';
    
    card.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px;">
            <div>
                <div class="bl-card-title">CASA per Hari - Konsolidasi Bulanan</div>
                <div class="bl-card-subtitle">Data harian per bulan - UKER terpilih</div>
            </div>
        </div>
        <div class="bl-summary-row" id="summary_${chartId}"></div>
        <div style="position:relative;height:420px;"><canvas id="${chartId}"></canvas></div>
        <div class="legend-custom" id="legend_${chartId}"></div>
    `;
    
    container.appendChild(card);
    
    // Determine maximum number of days (1-31)
    let maxDays = 0;
    monthKeys.forEach(mk => {
        const dates = monthGroups[mk];
        const days = dates.map(d => {
            const parts = d.split('-');
            return parseInt(parts[0]);
        });
        maxDays = Math.max(maxDays, Math.max(...days));
    });
    
    // X-axis labels: "1", "2", "3", ..., "31"
    const dayLabels = Array.from({length: maxDays}, (_, i) => String(i + 1));
    
    // Build datasets: one line per UKER per MONTH
    const datasets = [];
    const MONTH_COLORS = ['#9c27b0', '#f44336', '#00bcd4']; // Purple, Red, Cyan for months
    
    selectedUkerData.forEach((row, ukerIdx) => {
        const ukerName = row.NAMA_UKER;
        const originalIdx = csv.data.indexOf(row);
        
        // Create one line per month for this UKER
        monthKeys.forEach((monthKey, monthIdx) => {
            const monthLabel = blGetMonthLabel(monthKey);
            const dates = monthGroups[monthKey];
            
            // Use different color per month
            const color = MONTH_COLORS[monthIdx % MONTH_COLORS.length];
            
            // Map dates to day-of-month
            const dateMap = {};
            dates.forEach(d => {
                const parts = d.split('-'); // "23-Apr-2026"
                const day = parseInt(parts[0]);
                dateMap[day] = parseFloat((parseFloat(row[d]) || 0).toFixed(3));
            });
            
            // Create data array aligned with dayLabels (1-31)
            const data = dayLabels.map(dayStr => {
                const day = parseInt(dayStr);
                return dateMap[day] !== undefined ? dateMap[day] : null;
            });
            
            datasets.push({
                label: `${monthLabel}`,
                data: data,
                borderColor: color,
                backgroundColor: color + '18',
                borderWidth: 2.5,
                pointRadius: 4,
                pointBackgroundColor: color,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                tension: 0.3,
                fill: false,
                spanGaps: false,
                ukerName: ukerName
            });
        });
    });
    
    // Calculate summary
    const sumEl = document.getElementById(`summary_${chartId}`);
    let totalSum = 0;
    allDateCols.forEach(d => {
        selectedUkerData.forEach(row => {
            totalSum += parseFloat(row[d]) || 0;
        });
    });
    
    blAddBadge(sumEl, 'Total Keseluruhan', blFmt(totalSum, 3) + ' M', '#1a237e');
    blAddBadge(sumEl, 'Jumlah UKER', selectedUkerData.length + ' unit', '#6a1b9a');
    blAddBadge(sumEl, 'Jumlah Bulan', monthKeys.length + ' bulan', '#2e7d32');
    
    // Create chart
    blDestroy(chartId);
    blCharts[chartId] = new Chart(document.getElementById(chartId).getContext('2d'), {
        type: 'line',
        data: { labels: dayLabels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { top: 30, right: 30, bottom: 10, left: 10 } },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleFont: { size: 13 },
                    bodyFont: { size: 12 },
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        title: items => `Hari ke-${items[0].label}`,
                        label: c => {
                            if (c.parsed.y === null) return null;
                            return `${c.dataset.ukerName} - ${c.dataset.label}: Rp ${blFmt(c.parsed.y, 3)} Miliar`;
                        }
                    }
                },
                datalabels: {
                    display: false
                }
            },
            scales: {
                x: {
                    title: { display: true, text: 'Hari', font: { size: 12, weight: '600' } },
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: { font: { size: 10 } }
                },
                y: {
                    title: { display: true, text: 'Rp Miliar', font: { size: 12, weight: '600' } },
                    grid: { color: 'rgba(0,0,0,0.06)' },
                    ticks: {
                        font: { size: 11 },
                        callback: v => blFmt(v, 1)
                    }
                }
            }
        },
        plugins: [ChartDataLabels]
    });
    
    // Build legend - show months with different colors
    let leg = '';
    
    if (selectedUkerData.length === 1) {
        // Single UKER: show months only
        monthKeys.forEach((mk, idx) => {
            const color = MONTH_COLORS[idx % MONTH_COLORS.length];
            leg += `<div class="legend-item">
                <div class="legend-dot" style="background:${color}"></div>
                <span>${blGetMonthLabel(mk)}</span>
            </div>`;
        });
    } else {
        // Multiple UKERs: group by UKER with month sub-legend
        selectedUkerData.forEach((row, ukerIdx) => {
            leg += `<div class="legend-item" style="flex-direction:column;align-items:flex-start;gap:4px;margin-bottom:8px;">
                <div style="font-weight:600;color:#333;">${row.NAMA_UKER}</div>
                <div style="display:flex;gap:12px;padding-left:8px;">`;
            
            monthKeys.forEach((mk, idx) => {
                const color = MONTH_COLORS[idx % MONTH_COLORS.length];
                leg += `<div style="display:flex;align-items:center;gap:4px;">
                    <div class="legend-dot" style="background:${color}"></div>
                    <span style="font-size:11px;">${blGetMonthLabel(mk)}</span>
                </div>`;
            });
            
            leg += `</div></div>`;
        });
    }
    
    document.getElementById(`legend_${chartId}`).innerHTML = leg;
}

function blDownloadChart(cardId, filename) {
    const el = document.getElementById(cardId);
    if (!el) return;
    const btn = el.querySelector('button');
    if (btn) btn.style.visibility = 'hidden';
    const timestamp = new Date().toISOString().slice(0,10);
    html2canvas(el, { backgroundColor: '#ffffff', scale: 2 }).then(canvas => {
        if (btn) btn.style.visibility = '';
        const link = document.createElement('a');
        link.download = `brilink_${filename}_${timestamp}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
    });
}

// Track which BriLink charts have been loaded
const blSectionLoaded = { vol: false, agen: false, uker: false };

function switchBlChart(section, btn) {
    ['vol','agen','uker'].forEach(s => {
        document.getElementById('bl-sec-'+s).style.display = s === section ? '' : 'none';
    });
    document.querySelectorAll('#blChartTabs .tab-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    // Lazy-load only when first shown
    if (!blSectionLoaded[section]) {
        blSectionLoaded[section] = true;
        if (section === 'vol')  renderVolumeTrx();
        if (section === 'agen') renderCasaAgen();
        if (section === 'uker') renderCasaUker();
    }
}

async function reloadAll() {
    // Reload only the currently visible section, reset others
    const active = ['vol','agen','uker'].find(s => document.getElementById('bl-sec-'+s).style.display !== 'none') || 'vol';
    blSectionLoaded.vol  = false;
    blSectionLoaded.agen = false;
    blSectionLoaded.uker = false;
    blSectionLoaded[active] = true;
    if (active === 'vol')  await renderVolumeTrx();
    if (active === 'agen') await renderCasaAgen();
    if (active === 'uker') await renderCasaUker();
}

// ── BriLink Input Modal ──────────────────────────────────────────────────────
function openInputModal() {
    document.getElementById('inputModal').classList.add('active');
    setInputTab('volume_trx', document.querySelector('#inputTabs .tab-btn'));
}
function closeInputModal() {
    document.getElementById('inputModal').classList.remove('active');
}
async function setInputTab(tab, btn) {
    blActiveTab = tab;
    document.querySelectorAll('#inputTabs .tab-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    blActiveDate = '';
    
    // Show/hide template download/upload section for CASA Agen and CASA UKER
    const tempSec = document.getElementById('templateSection');
    if (tempSec) {
        if (tab === 'casa_agen' || tab === 'casa_uker') {
            tempSec.style.display = '';
            document.getElementById('templateInfo').textContent = `Format Excel akan mengikuti kolom data ${FILE_LABELS[tab]}.`;
        } else {
            tempSec.style.display = 'none';
        }
    }
    
    // Pre-load CSV data if not yet loaded (needed for template download/upload)
    if (!blData[tab]) {
        blData[tab] = await blLoadCSV(tab);
    }
    
    populateDateSelect();
    renderInputTable();
}

async function downloadBrilinkTemplate() {
    // Allow downloading template even if user didn't pick an existing date.
    // If no date selected, default to today's date in CSV format (DD-Mon-YYYY).
    // Load CSV data if not yet loaded
    if (!blData[blActiveTab]) {
        blData[blActiveTab] = await blLoadCSV(blActiveTab);
    }
    const csv = blData[blActiveTab];
    let dateToUse = blActiveDate;
    if (!dateToUse) {
        const todayISO = new Date().toISOString().slice(0,10); // YYYY-MM-DD
        dateToUse = jsDateToCSV(todayISO);
        showToast(`Tidak ada tanggal dipilih — menggunakan tanggal hari ini: ${dateToUse}`, 'success');
    }
    if (!csv) {
        showToast('Data CSV tidak dapat dimuat. Pastikan file CSV tersedia.', 'error');
        return;
    }

    let headers = [];
    let dataRows = [];

    if (blActiveTab === 'casa_agen') {
        headers = ['NAMA', 'UKER', 'NO_REK', dateToUse];
        dataRows = csv.data.map(row => [
            row.NAMA || '',
            row.UKER || '',
            row.NO_REK || '',
            row[dateToUse] !== undefined ? row[dateToUse] : ''
        ]);
    } else if (blActiveTab === 'casa_uker') {
        headers = ['NAMA_UKER', 'JUMLAH_AGEN', dateToUse];
        dataRows = csv.data.map(row => {
            const rawVal = row[dateToUse];
            let val = '';
            if (rawVal !== undefined && rawVal !== '') {
                const num = parseFloat(rawVal);
                // Convert from Milyar to Rupiah for the Excel template
                val = !isNaN(num) ? String(Math.round(num * 1000000000)) : rawVal;
            }
            return [
                row.NAMA_UKER || '',
                row.JUMLAH_AGEN || '',
                val
            ];
        });
    } else {
        headers = ['NAMA', 'JENIS', dateToUse];
        dataRows = csv.data.map(row => [
            row.NAMA || '',
            row.JENIS || '',
            row[dateToUse] !== undefined ? row[dateToUse] : ''
        ]);
    }

    // Create a worksheet
    const wsData = [headers, ...dataRows];
    const ws = XLSX.utils.aoa_to_sheet(wsData);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Template " + FILE_LABELS[blActiveTab]);

    // Generate buffer and trigger download as proper .xlsx file
    const wbout = XLSX.write(wb, { bookType: 'xlsx', type: 'array' });
    const blob = new Blob([wbout], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `Template_${blActiveTab}_${dateToUse}.xlsx`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

async function uploadBrilinkTemplate() {
    const fileInput = document.getElementById('templateFileInput');
    if (!fileInput.files.length) {
        showToast('Pilih file Excel (.xlsx atau .xls) terlebih dahulu!', 'error');
        return;
    }
    const file = fileInput.files[0];
    const reader = new FileReader();

    reader.onload = async function(e) {
        try {
            const data = e.target.result;
            const workbook = XLSX.read(data, { type: 'binary', cellDates: true });
            const sheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[sheetName];
            
            // Read headers separately to handle dates properly
            const range = XLSX.utils.decode_range(worksheet['!ref']);
            const headerRow = [];
            for (let col = range.s.c; col <= range.e.c; col++) {
                const cellAddress = XLSX.utils.encode_cell({ r: 0, c: col });
                const cell = worksheet[cellAddress];
                if (cell) {
                    let headerValue = cell.v;
                    // If it's a Date object, format it properly
                    if (headerValue instanceof Date) {
                        // Round to nearest hour to handle historical timezone offset and leap seconds shift
                        const ms = 60 * 60 * 1000;
                        const roundedDate = new Date(Math.round(headerValue.getTime() / ms) * ms);
                        const day = String(roundedDate.getDate()).padStart(2, '0');
                        const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                        const month = monthNames[roundedDate.getMonth()];
                        const year = roundedDate.getFullYear();
                        headerValue = `${day}-${month}-${year}`;
                    } else {
                        headerValue = String(headerValue || '');
                    }
                    headerRow.push(headerValue);
                } else {
                    headerRow.push('');
                }
            }
            
            // Read data rows with raw: true for numeric values
            const dataRows = XLSX.utils.sheet_to_json(worksheet, { header: 1, raw: true, defval: '', range: 1 });
            
            // Combine header and data
            const rows = [headerRow, ...dataRows];

            if (rows.length < 2) {
                showToast('File Excel kosong atau tidak memiliki baris data!', 'error');
                return;
            }

            const headers = rows[0].map(h => String(h || '').trim());
            
            // Validate metadata headers
            const meta = META_COLS[blActiveTab];
            for (let i = 0; i < meta.length; i++) {
                if (headers[i] !== meta[i]) {
                    showToast(`Format kolom Excel salah. Kolom pertama harus: ${meta.join(', ')}`, 'error');
                    return;
                }
            }

            // Helper function to convert Excel serial number to DD-Mon-YYYY format
            function excelSerialToDate(serial) {
                // Excel serial date starts from 1900-01-01 (serial 1)
                // But Excel incorrectly treats 1900 as a leap year, so we need to adjust
                const excelEpoch = new Date(1899, 11, 30); // December 30, 1899
                const days = Math.floor(serial);
                const date = new Date(excelEpoch.getTime() + days * 86400000);
                
                // Round to the nearest hour to avoid timezone shift / historical offset bug
                const ms = 60 * 60 * 1000;
                const roundedDate = new Date(Math.round(date.getTime() / ms) * ms);
                
                const day = String(roundedDate.getDate()).padStart(2, '0');
                const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                const month = monthNames[roundedDate.getMonth()];
                const year = roundedDate.getFullYear();
                
                return `${day}-${month}-${year}`;
            }

            // Helper function to validate and convert date header
            function validateAndConvertDateHeader(header) {
                const trimmed = String(header || '').trim();
                
                // Check if it's already in DD-Mon-YYYY format
                const dateRegex = /^\d{2}-[A-Za-z]{3}-\d{4}$/;
                if (dateRegex.test(trimmed)) {
                    return trimmed;
                }
                
                // Check if it's in YYYY-MM-DD format (common Excel format)
                const isoRegex = /^(\d{4})-(\d{2})-(\d{2})$/;
                const isoMatch = trimmed.match(isoRegex);
                if (isoMatch) {
                    const year = isoMatch[1];
                    const monthNum = parseInt(isoMatch[2], 10);
                    const dayNum = parseInt(isoMatch[3], 10);
                    const day = String(dayNum).padStart(2, '0'); // Ensure 2 digits
                    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    const month = monthNames[monthNum - 1];
                    return `${day}-${month}-${year}`;
                }
                
                // Check if it's a number (Excel serial date)
                const num = parseFloat(trimmed);
                if (!isNaN(num) && num > 0 && num < 100000) {
                    return excelSerialToDate(num);
                }
                
                // Try to parse as date string
                const parsed = new Date(trimmed);
                if (!isNaN(parsed.getTime())) {
                    const ms = 60 * 60 * 1000;
                    const roundedDate = new Date(Math.round(parsed.getTime() / ms) * ms);
                    const day = String(roundedDate.getDate()).padStart(2, '0');
                    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    const month = monthNames[roundedDate.getMonth()];
                    const year = roundedDate.getFullYear();
                    return `${day}-${month}-${year}`;
                }
                
                return null; // Invalid date format
            }

            // The remaining columns in headers should be date columns
            const rawDateHeaders = headers.slice(meta.length);
            if (rawDateHeaders.length === 0) {
                showToast('Tidak ditemukan kolom tanggal untuk di-update!', 'error');
                return;
            }

            // Convert and validate date headers
            const dateHeaders = [];
            for (const d of rawDateHeaders) {
                const converted = validateAndConvertDateHeader(d);
                if (!converted) {
                    showToast(`Format kolom tanggal "${d}" tidak valid. Gunakan format tanggal Excel atau DD-Mon-YYYY (contoh: 23-Apr-2026)`, 'error');
                    return;
                }
                dateHeaders.push(converted);
            }

            // Parse entries
            const entries = [];
            const keyColIndex = 0; // 'NAMA' or 'NAMA_UKER'

            for (let r = 1; r < rows.length; r++) {
                const row = rows[r];
                if (!row || row.length === 0) continue;
                const name = String(row[keyColIndex] || '').trim();
                if (!name) continue;

                const dates = {};
                for (let c = 0; c < dateHeaders.length; c++) {
                    const colIndex = meta.length + c;
                    const val = row[colIndex];
                    
                    // Debug first row
                    if (r === 1 && c === 0) {
                        console.log('First data row debug:', {
                            rowIndex: r,
                            colIndex: colIndex,
                            rawValue: val,
                            valueType: typeof val,
                            fullRow: row,
                            meta: meta,
                            metaLength: meta.length,
                            dateHeader: dateHeaders[c]
                        });
                    }
                    
                    // Convert value to string and handle numeric values properly
                    let valueStr = '';
                    if (val !== undefined && val !== null && val !== '') {
                        // If it's a number, format it properly (no scientific notation)
                        if (typeof val === 'number') {
                            valueStr = String(Math.round(val));
                        } else {
                            valueStr = String(val).trim();
                            // For CASA UKER, convert from Rupiah to Milyar
                            if (blActiveTab === 'casa_uker') {
                                const num = parseFloat(valueStr);
                                if (!isNaN(num)) valueStr = String(num / 1000000000);
                            }
                        }
                    }
                    
                    // For CASA UKER, also handle numeric parsed values directly
                    if (blActiveTab === 'casa_uker' && typeof val === 'number') {
                        valueStr = String(val / 1000000000);
                    }
                    dates[dateHeaders[c]] = valueStr;
                }

                entries.push({ name, dates });
            }

            if (entries.length === 0) {
                showToast('Tidak ada baris data valid untuk di-upload!', 'error');
                return;
            }

            // Debug: log the data being sent
            console.log('Sending bulk save data:', {
                file: blActiveTab,
                entries: entries,
                sampleEntry: entries[0]
            });

            // Send to bulk save API
            const response = await fetch('data.php?action=bulk_save_brilink', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                body: JSON.stringify({
                    file: blActiveTab,
                    entries: entries
                })
            });

            const result = await response.json();
            if (result.success) {
                showToast(`✅ ${result.message}`, 'success');
                // Clear input file
                fileInput.value = '';
                // Reload data and refresh charts
                blData[blActiveTab] = await blLoadCSV(blActiveTab);
                populateDateSelect();
                renderInputTable();
                if (blActiveTab === 'volume_trx') renderVolumeTrx();
                else if (blActiveTab === 'casa_agen') renderCasaAgen();
                else renderCasaUker();
            } else {
                showToast('Gagal upload: ' + (result.error || ''), 'error');
            }
        } catch (err) {
            showToast('Gagal memproses file Excel: ' + err.message, 'error');
        }
    };

    reader.onerror = function() {
        showToast('Gagal membaca file Excel!', 'error');
    };

    reader.readAsBinaryString(file);
}
function populateDateSelect() {
    const csv = blData[blActiveTab];
    const sel = document.getElementById('inputDateSelect');
    sel.innerHTML = '<option value="">-- Pilih Tanggal --</option>';
    if (csv) {
        const dateCols = blGetDateCols(blActiveTab, csv.headers);
        // Detect if there are any fully-empty date columns
        const emptyDates = dateCols.filter(d => csv.data.every(r => {
            const v = r[d];
            return v === undefined || v === null || String(v).trim() === '';
        }));
        if (emptyDates.length > 0) {
            sel.innerHTML += `<option value="__EMPTY__">-- Pilih tanggal kosong (isi belum ada) --</option>`;
        }
        dateCols.forEach(d => { sel.innerHTML += `<option value="${d}">${d}</option>`; });
    }
    document.getElementById('inputDateNew').value = '';
    document.getElementById('inputDateLabel').textContent = '';
}
function onDateSelectChange() {
    const val = document.getElementById('inputDateSelect').value;
    if (!val) return;
    // Special option: choose first fully-empty date column
    if (val === '__EMPTY__') {
        const csv = blData[blActiveTab];
        if (!csv) { showToast('Data belum dimuat.', 'error'); return; }
        const dateCols = blGetDateCols(blActiveTab, csv.headers);
        const firstEmpty = dateCols.find(d => csv.data.every(r => {
            const v = r[d];
            return v === undefined || v === null || String(v).trim() === '';
        }));
        if (!firstEmpty) { showToast('Tidak ditemukan tanggal kosong.', 'error'); return; }
        document.getElementById('inputDateNew').value = csvDateToJS(firstEmpty) || '';
        blActiveDate = firstEmpty;
        document.getElementById('inputDateLabel').textContent = firstEmpty;
        renderInputTable();
        return;
    }
    document.getElementById('inputDateNew').value = csvDateToJS(val) || '';
    blActiveDate = val;
    document.getElementById('inputDateLabel').textContent = val;
    renderInputTable();
}
function onDateNewChange() {
    const val = document.getElementById('inputDateNew').value;
    if (!val) return;
    const csvDate = jsDateToCSV(val);
    blActiveDate = csvDate;
    document.getElementById('inputDateLabel').textContent = csvDate;
    const sel = document.getElementById('inputDateSelect');
    const found = [...sel.options].find(o => o.value === csvDate);
    sel.value = found ? csvDate : '';
    renderInputTable();
}
function renderInputTable() {
    const headEl = document.getElementById('inputTableHead');
    const bodyEl = document.getElementById('inputTableBody');
    const csv = blData[blActiveTab];
    const meta = META_COLS[blActiveTab];
    if (!csv || !blActiveDate) {
        headEl.innerHTML = '';
        bodyEl.innerHTML = '<tr><td colspan="10" style="color:#888;padding:14px;">Pilih tanggal untuk memulai input.</td></tr>';
        return;
    }
    let hHtml = '';
    meta.forEach(m => hHtml += `<th>${m}</th>`);
    hHtml += `<th>${blActiveDate}<br><small>(${BL_UNITS[blActiveTab]})</small></th>`;
    if (blActiveTab !== 'casa_uker') hHtml += '<th>TOTAL (auto)</th>';
    headEl.innerHTML = `<tr>${hHtml}</tr>`;
    let bHtml = '', total = 0;
    csv.data.forEach((row, i) => {
        let curVal = row[blActiveDate] !== undefined ? row[blActiveDate] : '';
        total += parseFloat(curVal) || 0;
        // For CASA UKER, display values as full Rupiah integers
        if (blActiveTab === 'casa_uker' && curVal !== '') {
            const num = parseFloat(curVal);
            if (!isNaN(num)) curVal = String(Math.round(num * 1000000000));
        }
        let rHtml = '';
        meta.forEach(m => rHtml += `<td>${row[m] || ''}</td>`);
        rHtml += `<td><input type="text" id="inp_${i}" value="${curVal}" oninput="updateTotalRow()" placeholder="0"></td>`;
        bHtml += `<tr>${rHtml}</tr>`;
    });
    if (blActiveTab !== 'casa_uker') {
        const fmtTot = blActiveTab === 'casa_agen' ? 'Rp '+blFmt(blToJuta(total),3)+' Jt' : 'Rp '+blFmt(blToJuta(total),2)+' Jt';
        bHtml += `<tr class="total-row"><td colspan="${meta.length}"><b>TOTAL</b></td><td></td><td id="totalRowVal"><span class="auto-field">${fmtTot}</span></td></tr>`;
    } else {
        bHtml += `<tr class="total-row"><td colspan="${meta.length}"><b>TOTAL</b></td><td><span class="auto-field">${blFmt(total,3)} M</span></td></tr>`;
    }
    bodyEl.innerHTML = bHtml;
}
function updateTotalRow() {
    const csv = blData[blActiveTab]; if (!csv) return;
    const totalEl = document.getElementById('totalRowVal'); if (!totalEl) return;
    if (blActiveTab === 'casa_uker') return;
    let total = 0;
    csv.data.forEach((_, i) => { total += parseFloat(document.getElementById(`inp_${i}`)?.value || 0); });
    const fmtTot = blActiveTab === 'casa_agen' ? 'Rp '+blFmt(blToJuta(total),3)+' Jt' : 'Rp '+blFmt(blToJuta(total),2)+' Jt';
    totalEl.innerHTML = `<span class="auto-field">${fmtTot}</span>`;
}
async function saveInputData() {
    const csv = blData[blActiveTab];
    if (!csv || !blActiveDate) { showToast('Pilih tanggal terlebih dahulu!', 'error'); return; }
    const keyCol = META_COLS[blActiveTab][0];
    const updates = csv.data.map((row, i) => {
        let value = (document.getElementById(`inp_${i}`)?.value || '').trim();
        // For CASA UKER, ensure values are saved as Milyar
        if (blActiveTab === 'casa_uker' && value !== '') {
            const num = parseFloat(value);
            if (!isNaN(num)) value = String(num / 1000000000);
        }
        return { name: row[keyCol], value };
    }).filter(u => u.value !== '');
    if (!updates.length) { showToast('Tidak ada nilai yang diisi!', 'error'); return; }
    try {
        const r = await fetch('data.php?action=save_brilink', {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            body: JSON.stringify({ file: blActiveTab, date: blActiveDate, updates })
        });
        const j = await r.json();
        if (j.success) {
            showToast(`✅ ${j.message}`, 'success');
            blData[blActiveTab] = await blLoadCSV(blActiveTab);
            populateDateSelect(); renderInputTable();
            if (blActiveTab === 'volume_trx') renderVolumeTrx();
            else if (blActiveTab === 'casa_agen') renderCasaAgen();
            else renderCasaUker();
        } else { showToast('Gagal: '+(j.error||''), 'error'); }
    } catch(e) { showToast('Error: '+e.message, 'error'); }
}
async function confirmDeleteDate() {
    if (!blActiveDate) { showToast('Pilih tanggal yang ingin dihapus!', 'error'); return; }
    if (!confirm(`Hapus data tanggal "${blActiveDate}" dari ${FILE_LABELS[blActiveTab]}?`)) return;
    try {
        const r = await fetch('data.php?action=delete_brilink_date', {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            body: JSON.stringify({ file: blActiveTab, date: blActiveDate })
        });
        const j = await r.json();
        if (j.success) {
            showToast(`🗑️ ${j.message}`, 'success');
            blData[blActiveTab] = await blLoadCSV(blActiveTab);
            blActiveDate = ''; populateDateSelect(); renderInputTable();
            if (blActiveTab === 'volume_trx') renderVolumeTrx();
            else if (blActiveTab === 'casa_agen') renderCasaAgen();
            else renderCasaUker();
        } else { showToast('Gagal: '+(j.error||''), 'error'); }
    } catch(e) { showToast('Error: '+e.message, 'error'); }
}
document.getElementById('inputModal').addEventListener('click', function(e) {
    if (e.target === this) closeInputModal();
});
</script>

</body>
</html>
