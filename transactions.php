<?php
session_start();
if (!isset($_SESSION["username"])) {
    header("Location: index.php");
    exit();
}
$username = $_SESSION["username"];
$branch = $_SESSION["branch"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Transactions</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.4/css/jquery.dataTables.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.6/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>

        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        :root {
            --primary:      #2563eb;
            --primary-dark: #1d4ed8;
            --danger:       #ef4444;
            --success:      #22c55e;
            --warning:      #f59e0b;
            --surface:      #ffffff;
            --surface2:     #f1f5f9;
            --border:       #e2e8f0;
            --text:         #1e293b;
            --text-muted:   #64748b;
            --nav-h:        56px;
            --bot-h:        68px;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--surface2);
            color: var(--text);
            margin: 0; padding: 0;
            padding-top: var(--nav-h);
            padding-bottom: var(--bot-h);
            min-height: 100vh;
        }
        /* TOP BAR */
        .top-bar {
            position: fixed; top: 0; left: 0; right: 0;
            height: var(--nav-h);
            background: linear-gradient(90deg,#2563eb,#1d4ed8);
            color: #fff;
            display: flex; align-items: center;
            justify-content: space-between;
            padding: 0 14px;
            z-index: 40;
            box-shadow: 0 2px 8px rgba(0,0,0,.18);
        }
        .brand { display:flex; align-items:center; gap:10px; }
        .brand i { font-size:22px; opacity:.9; }
        .brand-name { font-size:16px; font-weight:700; letter-spacing:.3px; }
        .brand-sub  { font-size:10px; font-weight:500; opacity:.75; margin-top:-2px; }
        .user-pill {
            display:flex; align-items:center; gap:6px;
            background:rgba(255,255,255,.15);
            border:1px solid rgba(255,255,255,.25);
            border-radius:999px;
            padding:6px 12px; color:#fff; cursor:pointer;
            font-size:13px; font-weight:600;
            transition: background .2s;
        }
        .user-pill:hover { background:rgba(255,255,255,.25); }
        /* BOTTOM NAV */
        .bottom-nav {
            position: fixed; bottom: 0; left: 0; right: 0;
            height: var(--bot-h);
            background: #fff;
            display: flex; align-items: stretch;
            border-top: 1px solid var(--border);
            box-shadow: 0 -2px 12px rgba(0,0,0,.08);
            z-index: 40;
        }
        .bot-btn {
            flex: 1; display:flex; flex-direction:column;
            align-items:center; justify-content:center;
            gap:2px; font-size:10px; font-weight:600;
            color: var(--text-muted); background:none; border:none;
            cursor:pointer; text-decoration:none;
            padding: 6px 2px;
            transition: color .2s, background .2s;
            position: relative;
        }
        .bot-btn i { font-size: 19px; transition: transform .2s; }
        .bot-btn:active i { transform: scale(0.9); }
        .bot-btn { font-size: 9px; gap: 2px; padding: 0 1px; }
        
        /* Icon Colors */
        .bot-btn.tx-btn i { color: #3b82f6; }
        .bot-btn.sum-btn i { color: #8b5cf6; }
        .bot-btn.dup-btn i { color: #f43f5e; }
        .bot-btn.mon-btn i { color: #f59e0b; }
        .bot-btn.void-btn i { color: #ef4444; }
        .bot-btn.print-btn i { color: #0ea5e9; }
        .bot-btn.new-btn i { color: #10b981; }
        .bot-btn.stop-btn i { color: #64748b; }

        .bot-btn.active { border-bottom: 3px solid var(--primary); }
        .bot-btn.active.tx-btn { border-color: #3b82f6; }
        .bot-btn.active.mon-btn { border-color: #f59e0b; }
        .bot-btn.active.void-btn { border-color: #ef4444; }
        .bot-btn.active.new-btn { border-color: #10b981; }
        .bot-btn.active.print-btn { border-color: #0ea5e9; }

        .badge {
            position: absolute; top: 4px; left: 50%; margin-left: 8px;
            background: #ef4444; color: #fff;
            border-radius: 999px; font-size: 10px; font-weight: 800;
            min-width: 17px; height: 17px; padding: 0 4px;
            display: flex; align-items: center; justify-content: center;
            border: 2px solid #fff;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        /* SIDE DRAWER */
        .side-menu {
            background: linear-gradient(180deg,#2563eb,#1d4ed8);
        }
        /* PAGE CARD */
        .page-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,.08);
            padding: 24px 20px;
            max-width: 520px;
            margin: 20px auto;
        }
        .page-title {
            font-size: 18px; font-weight: 700;
            color: var(--text); margin-bottom: 16px;
            display: flex; align-items: center; gap: 8px;
        }
        /* FORM ELEMENTS */
        .pos-input {
            border-radius: 10px;
            border: 1.5px solid var(--border);
            padding: 12px 14px;
            font-size: 15px;
            width: 100%;
            background: #fff;
            transition: border-color .2s, box-shadow .2s;
            display: block;
        }
        .pos-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,.15);
        }
        .pos-label {
            font-weight: 600; font-size: 13px;
            color: var(--text-muted);
            display: flex; align-items: center; gap: 6px;
            margin-bottom: 6px;
        }
        .pos-label i { color: var(--primary); }
        .pos-button {
            border-radius: 10px;
            transition: all 0.2s ease;
            font-weight: 600;
            border: none; cursor: pointer;
        }
        .pos-button:hover { transform: translateY(-1px); }
        .pos-button:active { transform: translateY(0); }
        .submit-btn {
            width: 100%; padding: 14px;
            background: linear-gradient(90deg,#2563eb,#1d4ed8);
            color: #fff; border: none; border-radius: 12px;
            font-size: 16px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            gap: 8px; cursor: pointer;
            box-shadow: 0 4px 14px rgba(37,99,235,.35);
            transition: all .2s;
        }
        .submit-btn:hover { box-shadow: 0 6px 18px rgba(37,99,235,.45); transform:translateY(-1px); }
        .section-divider {
            display: flex; align-items: center; margin: 18px 0 14px;
        }
        .section-divider::before, .section-divider::after {
            content:''; flex:1; border-bottom: 1.5px solid var(--border);
        }
        .section-divider-label {
            padding: 0 12px; font-weight: 700; font-size: 11px;
            color: var(--primary); text-transform: uppercase; letter-spacing: 1px;
        }
        @keyframes fadeIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:none} }
        .animate-fadeIn { animation: fadeIn .35s ease; }


        /* TABLE OVERRIDES */
        .data-table-container {
            background: white; border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,.07);
            overflow-x: auto; -webkit-overflow-scrolling: touch;
            border: 1px solid var(--border);
        }
        .data-table { border-collapse: separate; border-spacing: 0; }
        .data-table thead th {
            background: var(--surface2); color: var(--text-muted);
            font-weight: 600; font-size: 12px; text-transform: uppercase;
            letter-spacing: .05em; padding: 14px 10px;
            border-bottom: 2px solid var(--border); white-space: nowrap;
        }
        .data-table tbody tr { border-bottom: 1px solid var(--surface2); transition: background .15s; }
        .data-table tbody tr:hover { background: #eff6ff; }
        .data-table tbody td { padding: 13px 10px; font-size: 14px; white-space: nowrap; }
        @media (max-width:640px) {
            .data-table { min-width: 900px; }
            .data-table thead th, .data-table tbody td { padding: 10px 8px; font-size: 13px; }
        }
        .summary-button {
            background: linear-gradient(135deg,#2563eb,#1d4ed8);
            color:#fff; border-radius:10px; padding:8px 14px;
            font-weight:600; font-size:14px; border:none; cursor:pointer;
            box-shadow:0 4px 12px rgba(37,99,235,.3); transition:all .2s;
            display:flex; align-items:center; gap:6px;
        }
        .summary-button:hover { transform:translateY(-1px); }
        .notification-badge {
            background: linear-gradient(135deg,#dc2626,#b91c1c);
            color:#fff; border-radius:10px; padding:8px 14px;
            font-weight:600; font-size:14px; border:none; cursor:pointer;
            box-shadow:0 4px 12px rgba(220,38,38,.3); transition:all .2s;
            display:flex; align-items:center; gap:6px;
        }
        .notification-badge:hover { transform:translateY(-1px); }
        .dataTables_paginate .paginate_button.current,
        .dataTables_paginate .paginate_button.active {
            background: var(--primary) !important; color:#fff !important;
            border-color: var(--primary) !important; font-weight:700 !important;
        }
        .dataTables_paginate .paginate_button.previous,
        .dataTables_paginate .paginate_button.next { display:none !important; }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate { margin:0 !important; }


        /* ── Transaction Cards ───────────────────────────────── */
        .tx-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,.07);
            border: 1px solid #e2e8f0;
            margin-bottom: 10px;
            overflow: hidden;
            transition: box-shadow .2s;
        }
        .tx-card:hover { box-shadow: 0 4px 18px rgba(37,99,235,.12); }
        .tx-card-header {
            display: flex; justify-content: space-between; align-items: flex-start;
            padding: 12px 14px 10px;
            background: linear-gradient(135deg,#eff6ff,#dbeafe);
            border-bottom: 1px solid #bfdbfe;
        }
        .tx-tran { font-size: 15px; font-weight: 700; color: #1d4ed8; }
        .tx-date { font-size: 11px; color: #64748b; margin-top: 2px; }
        .tx-total {
            font-size: 17px; font-weight: 800;
            color: #15803d;
            background: #dcfce7; border-radius: 8px;
            padding: 4px 10px;
        }
        .tx-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0;
            padding: 10px 14px 12px;
        }
        .tx-field { padding: 4px 4px; }
        .tx-label {
            font-size: 10px; font-weight: 600; color: #94a3b8;
            text-transform: uppercase; letter-spacing: .4px; margin-bottom: 2px;
        }
        .tx-label i { margin-right: 3px; }
        .tx-value { font-size: 13px; font-weight: 600; color: #374151; }
        .tx-money-green { color: #16a34a; font-weight: 700; }
        .tx-money-blue  { color: #2563eb; font-weight: 700; }
        /* ── Pagination ──────────────────────────────────────── */
        .pg-btn {
            min-width: 34px; height: 34px; padding: 0 8px;
            border: 1.5px solid #e2e8f0; border-radius: 8px;
            background: #fff; font-size: 13px; font-weight: 600;
            color: #475569; cursor: pointer; transition: all .2s;
        }
        .pg-btn:hover:not([disabled]) { border-color: #2563eb; color: #2563eb; }
        .pg-btn[disabled] { opacity: .35; cursor: not-allowed; }
        .pg-btn.pg-active {
            background: #2563eb; border-color: #2563eb;
            color: #fff; box-shadow: 0 3px 10px rgba(37,99,235,.3);
        }
        .pg-ellipsis { font-size: 14px; color: #94a3b8; padding: 0 4px; line-height: 34px; }

    </style>
<link rel="stylesheet" href="modern-bottom-nav.css">
</head>
<body>
    <!-- TOP BAR -->
    <nav class="top-bar">
        <div class="brand">
            <i class="fas fa-cash-register"></i>
            <div>
                <div class="brand-name"><?php
                    $db = isset($_SESSION['branch']) ? $_SESSION['branch'] : '';
                    echo ($db==='Sanko Market') ? 'Sanko Market' : htmlspecialchars($db);
                    ?></div>
                <div class="brand-sub">Collection POS</div>
            </div>
        </div>
        <button class="user-pill" id="burger-menu-btn">
            <i class="fas fa-user-circle"></i>
            <span><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : ''; ?></span>
            <i class="fas fa-chevron-down" style="font-size:10px;opacity:.7"></i>
        </button>
    </nav>
    <input type="hidden" id="branch" value="<?php echo htmlspecialchars($branch); ?>">

    <!-- SIDE DRAWER -->
    <div id="side-nav" class="hidden fixed inset-0 z-50">
        <div class="flex h-full">
            <div id="side-nav-backdrop" class="bg-black opacity-50 flex-1 h-full"></div>
            <div class="side-menu w-72 h-full transform transition-transform duration-300 ease-in-out translate-x-full ml-auto">
                <div class="flex justify-between items-center p-4 border-b border-blue-800">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-user-circle text-white text-3xl"></i>
                        <div>
                            <div class="text-white font-bold"><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : ''; ?></div>
                            <div class="text-blue-200 text-xs"><?php
                    $db = isset($_SESSION['branch']) ? $_SESSION['branch'] : '';
                    echo ($db==='Sanko Market') ? 'Sanko Market' : htmlspecialchars($db);
                    ?></div>
                        </div>
                    </div>
                    <button id="close-btn" class="text-white p-2 hover:bg-blue-800 rounded-full">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="flex flex-col mt-4 px-3 gap-1">
                    <a href="user.php" class="text-white text-base py-3 px-4 flex items-center gap-3 rounded-xl hover:bg-blue-800">
                        <i class="fas fa-plus-circle w-5 text-center text-blue-300"></i> New Collection
                    </a>
                    <a href="transactions.php" class="text-white text-base py-3 px-4 flex items-center gap-3 rounded-xl hover:bg-blue-800">
                        <i class="fas fa-list-ul w-5 text-center text-blue-300"></i> Transactions
                    </a>
                    <a href="reprint.php" class="text-white text-base py-3 px-4 flex items-center gap-3 rounded-xl hover:bg-blue-800">
                        <i class="fas fa-print w-5 text-center text-blue-300"></i> Reprint Receipt
                    </a>
                    <a href="void_transaction.php" class="text-white text-base py-3 px-4 flex items-center gap-3 rounded-xl hover:bg-blue-800">
                        <i class="fas fa-ban w-5 text-center text-yellow-300"></i> Void Transaction
                    </a>
                </div>
                <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-blue-800">
                    <a href="index.php" class="text-white text-base py-3 px-4 flex items-center gap-3 rounded-xl hover:bg-red-700 transition">
                        <i class="fas fa-sign-out-alt w-5 text-center text-red-300"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php include 'modern_bottom_nav.php'; ?>

    <!-- Logout Modern Modal -->
    <div id="logoutConfirmModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-[60] animate-fadeIn">
        <div class="mobile-modal bg-white rounded-3xl p-8 max-w-[340px] w-full mx-4 shadow-2xl relative overflow-hidden text-left">
            <div class="absolute -top-10 -right-10 w-32 h-32 bg-red-50 rounded-full opacity-50"></div>
            <div class="text-center relative z-10">
                <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-2xl bg-red-500 mb-6 shadow-xl shadow-red-100">
                    <i class="fas fa-sign-out-alt text-white text-3xl"></i>
                </div>
                <h3 class="text-2xl font-black text-gray-900 mb-2 font-sans">Going so soon?</h3>
                <p class="text-gray-500 mb-8 px-2 font-medium font-sans">Are you sure you want to exit and end your collection session?</p>
                
                <div class="flex flex-col gap-3">
                    <a href="index.php" class="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-4 px-6 rounded-2xl shadow-lg shadow-red-200 transition-all active:scale-95 flex items-center justify-center gap-2 font-sans">
                        <i class="fas fa-door-open mr-1"></i> YES, EXIT
                    </a>
                    <button onclick="hideLogoutModal()" class="w-full bg-gray-50 hover:bg-gray-100 text-gray-500 font-bold py-4 px-6 rounded-2xl transition-all active:scale-95 font-sans">
                        KEEP WORKING
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="px-3 pt-3">

        <!-- Control Panel Row Removed - Integrated into Bottom Nav -->
        <div class="mb-3"></div>

        <!-- Search + Entries row -->
        <div class="flex items-center justify-between mb-3 gap-2">
            <div class="flex items-center gap-2">
                <span class="text-sm text-gray-500 font-medium">Show</span>
                <select id="entriesPerPage" class="pos-input" style="width:70px;padding:6px 8px;font-size:14px">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
            <div class="relative flex-1" style="max-width:200px">
                <i class="fas fa-search absolute" style="left:10px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:13px"></i>
                <input id="searchInput" type="text" placeholder="Search..." class="pos-input w-full" style="padding:8px 8px 8px 30px;font-size:14px">
            </div>
        </div>

        <!-- Cards container -->
        <div id="cardsContainer"></div>

        <!-- Pagination + info -->
        <div class="flex flex-col items-center gap-2 mt-4 mb-2">
            <div id="paginationInfo" class="text-xs text-gray-500 font-medium"></div>
            <div id="paginationBtns" class="flex flex-wrap gap-1 justify-center"></div>
        </div>
    </div>

    <!-- Duplicated Transactions Modal -->
    <div id="duplicatedTransactionsModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
        <div class="flex items-start justify-center min-h-screen pt-16 px-3 pb-20">
            <div class="fixed inset-0 bg-black opacity-50"></div>
            <div class="relative bg-white rounded-2xl shadow-xl w-full overflow-hidden" style="max-width:480px">
                <div class="flex justify-between items-center px-4 py-3" style="background:linear-gradient(90deg,#dc2626,#b91c1c)">
                    <h3 class="text-white font-bold text-base"><i class="fas fa-exclamation-circle mr-2"></i>Duplicated Transactions</h3>
                    <button onclick="document.getElementById('duplicatedTransactionsModal').classList.add('hidden')" class="text-white p-1">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
                <div class="p-3">
                    <div id="dupCardsContainer" class="flex flex-col gap-2">
                        <div class="text-center py-6 text-gray-400"><i class="fas fa-spinner fa-spin text-2xl"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="nav_badge.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script>
    function n(v){ return parseFloat(v||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2}); }
    function fmtDate(d){
        const dt = new Date(d);
        if(isNaN(dt)) return d;
        return dt.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})
            +' '+dt.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'});
    }

    // ── Card builder ──────────────────────────────────────────────────────────
    function getChargesTotal(chStr) {
        if (!chStr || chStr === 'null') return 0;
        let total = 0;
        const parts = chStr.split(',');
        for (let p of parts) {
            const match = p.match(/:\s*([\d,.]+)/);
            if (match) total += parseFloat(match[1].replace(/,/g, '')) || 0;
        }
        return total;
    }

    function buildCard(t) {
        const cTotal = getChargesTotal(t.charges);
        const paid = parseFloat(t.paidrent||0) + parseFloat(t.paidbal||0) + cTotal;
        const charges = t.charges && t.charges !== 'null' ? t.charges : '';
        return `
        <div class="tx-card">
            <div class="tx-card-header">
                <div>
                    <div class="tx-tran">#${t.transaction_number}</div>
                    <div class="tx-date">${fmtDate(t.collected_date)}</div>
                </div>
                <div class="tx-total">&#x20B1;${n(paid)}</div>
            </div>
            <div class="tx-grid">
                <div class="tx-field">
                    <div class="tx-label"><i class="fas fa-map-marker-alt"></i> Space</div>
                    <div class="tx-value">${t.spacecode||'—'}</div>
                </div>
                <div class="tx-field">
                    <div class="tx-label"><i class="fas fa-id-card"></i> Code</div>
                    <div class="tx-value">${t.tenantcode||'—'}</div>
                </div>
                <div class="tx-field">
                    <div class="tx-label"><i class="fas fa-user"></i> Collector</div>
                    <div class="tx-value">${t.collector||'—'}</div>
                </div>
                <div class="tx-field" style="grid-column:1/-1">
                    <div class="tx-label"><i class="fas fa-store"></i> Tenant</div>
                    <div class="tx-value" style="font-size:14px;font-weight:600;color:#1e293b">${t.tenantname||'—'}</div>
                </div>
                <div class="tx-field">
                    <div class="tx-label"><i class="fas fa-money-bill-wave"></i> Paid Rent</div>
                    <div class="tx-value tx-money-green">&#x20B1;${n(t.paidrent)}</div>
                </div>
                <div class="tx-field">
                    <div class="tx-label"><i class="fas fa-money-check"></i> Paid Bal</div>
                    <div class="tx-value tx-money-blue">&#x20B1;${n(t.paidbal)}</div>
                </div>
                <div class="tx-field">
                    <div class="tx-label"><i class="fas fa-building"></i> Branch</div>
                    <div class="tx-value">${t.branch||'—'}</div>
                </div>
                ${charges ? `<div class="tx-field" style="grid-column:1/-1">
                    <div class="tx-label"><i class="fas fa-file-invoice"></i> Charges</div>
                    <div class="tx-value" style="font-size:12px;color:#64748b;white-space:normal">${charges}</div>
                </div>` : ''}
            </div>
        </div>`;
    }

    // ── Pagination engine ─────────────────────────────────────────────────────
    let allData = [], filtered = [], currentPage = 1, perPage = 10;

    function applyFilter() {
        const q = document.getElementById('searchInput').value.toLowerCase().trim();
        filtered = !q ? allData : allData.filter(t =>
            (t.transaction_number+'').toLowerCase().includes(q) ||
            (t.spacecode||'').toLowerCase().includes(q) ||
            (t.tenantcode||'').toLowerCase().includes(q) ||
            (t.tenantname||'').toLowerCase().includes(q) ||
            (t.collector||'').toLowerCase().includes(q) ||
            (t.branch||'').toLowerCase().includes(q)
        );
        currentPage = 1;
        render();
    }

    function render() {
        const total   = filtered.length;
        const pages   = Math.max(1, Math.ceil(total / perPage));
        currentPage   = Math.min(currentPage, pages);
        const start   = (currentPage - 1) * perPage;
        const slice   = filtered.slice(start, start + perPage);

        const container = document.getElementById('cardsContainer');
        if (!total) {
            container.innerHTML = '<div class="text-center py-12 text-gray-400"><i class="fas fa-inbox text-4xl mb-3 block"></i>No transactions found</div>';
        } else {
            container.innerHTML = slice.map(buildCard).join('');
        }

        // Info
        document.getElementById('paginationInfo').textContent =
            total ? `Showing ${start+1}–${Math.min(start+perPage,total)} of ${total} entries` : '';

        // Pagination buttons
        const pEl = document.getElementById('paginationBtns');
        if (pages <= 1) { pEl.innerHTML=''; return; }

        let btns = '';
        // First / Prev
        btns += `<button class="pg-btn" ${currentPage===1?'disabled':''} onclick="goPage(1)">«</button>`;
        btns += `<button class="pg-btn" ${currentPage===1?'disabled':''} onclick="goPage(${currentPage-1})">‹</button>`;
        // Page numbers (show max 5 around current)
        const radius = 2;
        let ps = Math.max(1, currentPage-radius), pe = Math.min(pages, currentPage+radius);
        if(pe-ps < radius*2){ ps=Math.max(1,pe-radius*2); pe=Math.min(pages,ps+radius*2); }
        if(ps > 1) btns += `<span class="pg-ellipsis">…</span>`;
        for(let i=ps;i<=pe;i++){
            btns += `<button class="pg-btn${i===currentPage?' pg-active':''}" onclick="goPage(${i})">${i}</button>`;
        }
        if(pe < pages) btns += `<span class="pg-ellipsis">…</span>`;
        // Next / Last
        btns += `<button class="pg-btn" ${currentPage===pages?'disabled':''} onclick="goPage(${currentPage+1})">›</button>`;
        btns += `<button class="pg-btn" ${currentPage===pages?'disabled':''} onclick="goPage(${pages})">»</button>`;
        pEl.innerHTML = btns;
    }

    function goPage(p){ currentPage = p; render(); window.scrollTo({top:0,behavior:'smooth'}); }

    // ── Load transactions ─────────────────────────────────────────────────────
    function fetchAndLoadTransactions() {
        document.getElementById('cardsContainer').innerHTML =
            '<div class="text-center py-12 text-gray-400"><i class="fas fa-spinner fa-spin text-3xl mb-3 block"></i>Loading...</div>';

        fetch('fetch_transactions.php')
            .then(r => r.json())
            .then(data => {
                // Sort newest first
                data.sort((a,b) => new Date(b.collected_date) - new Date(a.collected_date));
                allData = data;
                applyFilter();
                updateNotificationCount();
            })
            .catch(() => {
                document.getElementById('cardsContainer').innerHTML =
                    '<div class="text-center py-12 text-red-400"><i class="fas fa-exclamation-triangle text-3xl mb-3 block"></i>Error loading transactions</div>';
            });
    }

    function updateNotificationCount() {
        fetch('fetch_duplicated_transactions.php')
            .then(r => r.json())
            .then(data => {
                const badge = document.getElementById('notificationCountBadge');
                if (data.length > 0) {
                    badge.textContent = data.length;
                    badge.style.display = 'flex';
                    document.getElementById('notificationButton').classList.add('animate-pulse');
                } else {
                    badge.style.display = 'none';
                    document.getElementById('notificationButton').classList.remove('animate-pulse');
                }
            }).catch(()=>{});
    }

    // ── Duplicated modal ──────────────────────────────────────────────────────
    function buildDupCard(t) {
        const cTotal = getChargesTotal(t.charges);
        const total = parseFloat(t.paidrent||0) + parseFloat(t.paidbal||0) + cTotal;
        const charges = t.charges && t.charges !== 'null' ? t.charges : '';
        return `<div class="tx-card" style="border-left:3px solid #dc2626">
            <div class="tx-card-header">
                <div><div class="tx-tran" style="color:#dc2626">#${t.transaction_number}</div>
                     <div class="tx-date">${fmtDate(t.collected_date)}</div></div>
                <div class="tx-total" style="color:#16a34a">&#x20B1;${n(total)}</div>
            </div>
            <div class="tx-grid">
                <div class="tx-field"><div class="tx-label">Space</div><div class="tx-value">${t.spacecode||'—'}</div></div>
                <div class="tx-field"><div class="tx-label">Code</div><div class="tx-value">${t.tenantcode||'—'}</div></div>
                <div class="tx-field"><div class="tx-label">Rent Paid</div><div class="tx-value tx-money-green">&#x20B1;${n(t.paidrent)}</div></div>
                ${charges ? `<div class="tx-field" style="grid-column:1/-1"><div class="tx-label">Charges</div><div class="tx-value" style="font-size:12px;color:#64748b">${charges}</div></div>` : ''}
            </div>
        </div>`;
    }

    // Open duplicates modal (named so it can be called directly without triggering href)
    function openDupModal() {
        const modal = document.getElementById('duplicatedTransactionsModal');
        const cont  = document.getElementById('dupCardsContainer');
        modal.classList.remove('hidden');
        cont.innerHTML = '<div class="text-center py-6 text-gray-400"><i class="fas fa-spinner fa-spin text-2xl"></i></div>';

        fetch('fetch_duplicated_transactions.php')
            .then(r => r.json())
            .then(data => {
                cont.innerHTML = data.length
                    ? data.map(buildDupCard).join('')
                    : '<div class="text-center py-8 text-gray-400"><i class="fas fa-check-circle text-3xl mb-2 block"></i>No duplicates found</div>';
            })
            .catch(() => { cont.innerHTML = '<div class="text-center py-6 text-red-400">Error loading data</div>'; });
    }

    // Prevent the <a> href from navigating — open the modal in-page instead
    const notifBtn = document.getElementById('notificationButton');
    if (notifBtn) {
        notifBtn.addEventListener('click', function(e) {
            e.preventDefault();
            openDupModal();
        });
    }

    // ── Event listeners ───────────────────────────────────────────────────────
    document.getElementById('searchInput').addEventListener('input', applyFilter);
    document.getElementById('entriesPerPage').addEventListener('change', function() {
        perPage = parseInt(this.value); currentPage = 1; render();
    });

    document.addEventListener('DOMContentLoaded', () => {
        fetchAndLoadTransactions();

        // Open duplicates modal if URL param is set — call function directly, NOT .click()
        // (using .click() would follow the href and cause an infinite reload loop)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('showDuplicates') === '1') {
            setTimeout(openDupModal, 500);
        }
    });

    // ── Side drawer ───────────────────────────────────────────────────────────
    (function() {
        var btn = document.getElementById('burger-menu-btn');
        var nav = document.getElementById('side-nav');
        var backdrop = document.getElementById('side-nav-backdrop');
        var closeBtn = document.getElementById('close-btn');
        var menu = nav ? nav.querySelector('.side-menu') : null;
        function openDrawer() {
            nav.classList.remove('hidden');
            setTimeout(function(){ menu.classList.remove('translate-x-full'); menu.classList.add('translate-x-0'); }, 20);
        }
        function closeDrawer() {
            menu.classList.add('translate-x-full'); menu.classList.remove('translate-x-0');
            setTimeout(function(){ nav.classList.add('hidden'); }, 300);
        }
        if(btn) btn.addEventListener('click', openDrawer);
        if(closeBtn) closeBtn.addEventListener('click', closeDrawer);
        if(backdrop) backdrop.addEventListener('click', closeDrawer);
    })();

    // Logout Modal Functions
    function showLogoutModal() {
        document.getElementById('logoutConfirmModal').classList.remove('hidden');
    }
    function hideLogoutModal() {
        document.getElementById('logoutConfirmModal').classList.add('hidden');
    }
    </script>
</body>
</html>

