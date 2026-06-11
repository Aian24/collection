<?php
include 'config.php';
session_start();

if (!isset($_SESSION["username"])) {
    header("Location: index.php");
    exit();
}

$username = $_SESSION["username"];
$lname = $_SESSION["lname"];
$branch = $_SESSION["branch"];

date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Monitoring - Collection POS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        :root {
            --primary: #2563eb;
            --surface2: #f1f5f9;
            --nav-h: 56px;
            --bot-h: 68px;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--surface2);
            padding-top: var(--nav-h);
            padding-bottom: calc(var(--bot-h) + env(safe-area-inset-bottom, 0px));
            min-height: 100vh;
        }
        .top-bar {
            position: fixed; top: 0; left: 0; right: 0; height: var(--nav-h);
            background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 14px; z-index: 100;
        }
        .bottom-nav {
            position: fixed; bottom: 0; left: 0; right: 0; height: var(--bot-h);
            background: #fff; border-top: 1px solid #e2e8f0;
            display: flex; align-items: stretch; z-index: 100;
            padding-bottom: env(safe-area-inset-bottom, 0px);
        }
        .bot-btn {
            flex: 1; display: flex; flex-direction: column;
            align-items: center; justify-content: center; gap: 3px;
            text-decoration: none; color: #64748b; font-size: 10px; font-weight: 600;
            position: relative;
        }
        .bot-btn i { font-size: 19px; transition: transform .2s; }
        .bot-btn:active i { transform: scale(0.9); }
        .bot-btn { font-size: 9px; gap: 2px; padding:  0 1px; }
        
        /* Icon Colors */
        .bot-btn.tx-btn i { color: #3b82f6; }
        .bot-btn.sum-btn i { color: #8b5cf6; }
        .bot-btn.dup-btn i { color: #f43f5e; }
        .bot-btn.new-btn i { color: #10b981; }
        .bot-btn.mon-btn i { color: #f59e0b; }
        .bot-btn.void-btn i { color: #ef4444; }
        .bot-btn.print-btn i { color: #0ea5e9; }
        .bot-btn.stop-btn i { color: #64748b; }

        .bot-btn.active { border-bottom: 3px solid var(--primary); }
        .bot-btn.active.tx-btn { border-color: #3b82f6; }
        .bot-btn.active.mon-btn { border-color: #f59e0b; }
        .bot-btn.active.void-btn { border-color: #ef4444; }
        .bot-btn.active.new-btn { border-color: #10b981; }
        .bot-btn.active.print-btn { border-color: #0ea5e9; }

        .badge {
            position: absolute; top: 4px; left: 50%; margin-left: 8px;
            background: #ef4444; color: #fff; font-size: 10px; font-weight: 800;
            min-width: 17px; height: 17px; border-radius: 999px; padding: 0 4px;
            display: flex; align-items: center; justify-content: center;
            border: 2px solid #fff;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .monitor-card {
            background: #fff; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); padding: 20px;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fadeIn { animation: fadeIn .4s ease-out; }

        /* Suppress DataTables responsive arrow bleed */
        .list-item::before, .list-item td::before,
        tr.odd td:first-child::before, tr.even td:first-child::before,
        td.dtr-control::before { display: none !important; content: none !important; }
        .list-item { list-style: none !important; }
    </style>
</head>
<body>

    <nav class="top-bar">
        <div class="flex items-center gap-2 text-white">
            <i class="fas fa-chart-line text-xl"></i>
            <span class="font-bold">Monitoring</span>
        </div>
        <div class="text-white text-xs opacity-80"><?php echo htmlspecialchars($branch); ?></div>
    </nav>

    <div class="p-3">
        <!-- MONITOR PROGRESS CARD -->
        <div class="monitor-card animate-fadeIn mb-3" style="padding: 14px;">
            <div class="flex justify-between items-center mb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center text-blue-600 text-sm">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <span class="text-sm font-black text-gray-800">Collected Today</span>
                </div>
                <div class="text-[10px] font-bold text-gray-400 bg-gray-50 px-2 py-1 rounded-md">
                    <?php echo date('M d, Y'); ?>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 mb-3">
                <div class="bg-blue-50 p-3 rounded-xl text-center border border-blue-100">
                    <div class="text-[9px] uppercase font-black text-blue-400 mb-0.5">Paid</div>
                    <div id="mon-collected" class="text-xl font-black text-blue-700 leading-tight">0</div>
                    <div id="mon-total" class="text-[9px] text-blue-400">out of 0</div>
                </div>
                <div class="bg-red-50 p-3 rounded-xl text-center border border-red-100">
                    <div class="text-[9px] uppercase font-black text-red-400 mb-0.5">Unpaid</div>
                    <div id="mon-pending" class="text-xl font-black text-red-700 leading-tight">0</div>
                    <div id="mon-percent" class="text-[9px] text-red-400">0% left</div>
                </div>
            </div>

            <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden mb-3">
                <div id="mon-bar" class="h-full bg-blue-600 transition-all duration-1000" style="width: 0%"></div>
            </div>

            <!-- STEPS / GUIDE -->
            <div class="bg-blue-600 bg-opacity-5 rounded-xl p-2.5 flex items-start gap-2.5 border border-blue-100">
                <div class="text-blue-600 mt-0.5"><i class="fas fa-info-circle text-xs"></i></div>
                <div class="text-[10px] text-blue-800 leading-relaxed font-semibold">
                    <span class="text-blue-600 font-black uppercase mr-1">Easy Step:</span> 
                    Simply click on any <span class="text-red-600 font-bold">Unpaid Space</span> below to automatically pre-fill the New Collection form.
                </div>
            </div>
        </div>

        <div class="monitor-card animate-fadeIn" style="padding: 16px;">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-sm font-black text-gray-800 flex items-center gap-2">
                    <i class="fas fa-user-clock text-red-500 text-xs"></i> Unpaid List
                </h3>
            </div>
            
            <div class="relative mb-3">
                <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                <input type="text" id="mon-search" onkeyup="filterList()" placeholder="Search space code..." 
                       class="w-full bg-gray-50 border border-gray-100 rounded-lg py-2 pl-9 pr-4 text-xs focus:ring-2 focus:ring-blue-100 transition">
            </div>

            <div id="mon-list" class="space-y-2 max-h-[45vh] overflow-y-auto pr-1">
                <!-- List items -->
            </div>
        </div>
    </div>

    <nav class="bottom-nav">
        <a href="transactions.php" class="bot-btn tx-btn">
            <i class="fas fa-list-ul"></i>
            TXS
        </a>
        <a href="printsummary.php" class="bot-btn sum-btn">
            <i class="fas fa-database"></i>
            Summ
        </a>
        <a href="#" onclick="openDupModal(event)" id="notificationButton" class="bot-btn dup-btn">
            <i class="fas fa-exclamation-circle"></i>
            <span id="notificationCountBadge" class="badge" style="display:none">0</span>
            Dups
        </a>
        <a href="user.php" class="bot-btn new-btn">
            <i class="fas fa-plus-circle"></i>
            New
        </a>
        <a href="monitoring.php" class="bot-btn mon-btn active">
            <i class="fas fa-chart-line"></i>
            <span id="notificationBadge" class="badge" style="display:none"></span>
            Mon
        </a>
        <a href="void_transaction.php" class="bot-btn void-btn">
            <i class="fas fa-ban"></i>
            Void
        </a>
        <a href="reprint.php" class="bot-btn print-btn">
            <i class="fas fa-print"></i>
            Print
        </a>
        <button type="button" onclick="showLogoutModal()" class="bot-btn stop-btn">
            <i class="fas fa-sign-out-alt"></i>
            Exit
        </button>
    </nav>

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

    <!-- Duplicated Transactions Modal -->
    <div id="duplicatedTransactionsModal" class="fixed z-50 inset-0 hidden" style="background:rgba(0,0,0,0.55)">
        <div class="flex items-start justify-center min-h-screen pt-14 px-3 pb-24">
            <div class="relative bg-white rounded-2xl shadow-2xl w-full overflow-hidden" style="max-width:480px">
                <div class="flex justify-between items-center px-4 py-3" style="background:linear-gradient(90deg,#dc2626,#b91c1c)">
                    <h3 class="text-white font-bold text-base flex items-center gap-2">
                        <i class="fas fa-exclamation-circle"></i> Duplicated Transactions
                    </h3>
                    <button onclick="closeDupModal()" class="text-white p-1 hover:opacity-75">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
                <div class="p-3 overflow-y-auto" style="max-height:70vh">
                    <div id="dupCardsContainer" class="flex flex-col gap-2">
                        <div class="text-center py-6 text-gray-400"><i class="fas fa-spinner fa-spin text-2xl"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" id="branch" value="<?php echo $branch; ?>">

    <script src="nav_badge.js"></script>
    <script>
        function loadMonitoringData() {
            const branch = document.getElementById('branch').value;
            const date = '<?php echo $today; ?>';
            
            // Fetch Stats
            fetch(`fetch_monitoring_stats.php?branch=${encodeURIComponent(branch)}&date=${encodeURIComponent(date)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const total = parseInt(data.total) || 0;
                        const collected = parseInt(data.collected) || 0;
                        const pending = parseInt(data.pending) || 0;

                        document.getElementById('mon-collected').textContent = collected;
                        document.getElementById('mon-total').textContent = `out of ${total}`;
                        document.getElementById('mon-pending').textContent = pending;
                        
                        const percent = total > 0 ? Math.round((collected / total) * 100) : 0;
                        const remaining = 100 - percent;
                        document.getElementById('mon-percent').textContent = `${remaining}% left`;
                        document.getElementById('mon-bar').style.width = percent + '%';
                        
                        // Badge
                        const badge = document.getElementById('notificationBadge');
                        if (pending > 0) {
                            badge.textContent = pending;
                            badge.style.display = 'flex';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                });

            // Fetch List
            const listContainer = document.getElementById('mon-list');
            listContainer.innerHTML = '<div class="text-center py-4 text-xs opacity-50">Loading list...</div>';
            
            fetch(`fetch_pending_list.php?branch=${encodeURIComponent(branch)}&date=${encodeURIComponent(date)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        if (data.pending.length === 0) {
                            listContainer.innerHTML = '<div class="text-center py-8 text-green-500 font-bold bg-green-50 rounded-xl animate-fadeIn"><i class="fas fa-check-circle text-2xl mb-2 block"></i>All collections complete!</div>';
                        } else {
                            listContainer.innerHTML = data.pending.map(item => `
                                <div class="bg-white p-3 rounded-xl flex justify-between items-center mb-2 border border-gray-100 shadow-sm list-item active:scale-95 transition-transform" onclick="collectNow('${item.spacecode}')">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <div class="w-10 h-10 rounded-xl bg-red-50 text-red-500 flex items-center justify-center shrink-0 border border-red-100">
                                            <i class="fas fa-store-alt text-sm"></i>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="font-extrabold text-gray-800 text-xs leading-tight break-words mb-0.5">${item.spacecode}</div>
                                            <div class="text-[9px] text-gray-500 uppercase font-black truncate">${item.tenantname}</div>
                                        </div>
                                    </div>
                                </div>
                            `).join('');
                        }
                    }
                });
        }

        function filterList() {
            const filter = document.getElementById('mon-search').value.toUpperCase();
            const items = document.querySelectorAll('.list-item');
            items.forEach(item => {
                const text = item.textContent || item.innerText;
                item.style.display = text.toUpperCase().indexOf(filter) > -1 ? "" : "none";
            });
        }

        function collectNow(spacecode) {
            // Store spacecode to pre-fill on user.php
            localStorage.setItem('prefill_spacecode', spacecode);
            window.location.href = 'user.php';
        }

        window.onload = loadMonitoringData;

        // Logout Modal Functions
        function showLogoutModal() {
            document.getElementById('logoutConfirmModal').classList.remove('hidden');
        }
        function hideLogoutModal() {
            document.getElementById('logoutConfirmModal').classList.add('hidden');
        }

        // Duplicate Card Modal Logic
        function closeDupModal() {
            document.getElementById('duplicatedTransactionsModal').classList.add('hidden');
        }

        function fmtDate(d) {
            const dt = new Date(d);
            return isNaN(dt) ? d : dt.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})+' '+dt.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'});
        }
        function nFmt(v) { return parseFloat(v||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2}); }
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
        function buildDupCardUser(t) {
            const cTotal = getChargesTotal(t.charges);
            const total = parseFloat(t.paidrent||0) + parseFloat(t.paidbal||0) + cTotal;
            const charges = t.charges && t.charges !== 'null' ? t.charges : '';
            return `<div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.07);border:1px solid #fee2e2;border-left:4px solid #dc2626;margin-bottom:0;overflow:hidden">
                <div style="background:linear-gradient(135deg,#fff5f5,#fee2e2);padding:10px 14px 8px;border-bottom:1px solid #fecaca;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:15px;font-weight:700;color:#dc2626">#${t.transaction_number}</div>
                        <div style="font-size:11px;color:#64748b">${fmtDate(t.collected_date)}</div>
                    </div>
                    <div style="font-size:14px;font-weight:800;color:#16a34a">&#x20B1;${nFmt(total)}</div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0;padding:10px 14px 12px">
                    <div style="padding:4px"><div style="font-size:10px;font-weight:600;color:#94a3b8;text-transform:uppercase;margin-bottom:2px">Space</div><div style="font-size:13px;font-weight:600;color:#374151">${t.spacecode||'—'}</div></div>
                    <div style="padding:4px"><div style="font-size:10px;font-weight:600;color:#94a3b8;text-transform:uppercase;margin-bottom:2px">Code</div><div style="font-size:13px;font-weight:600;color:#374151">${t.tenantcode||'—'}</div></div>
                    <div style="padding:4px"><div style="font-size:10px;font-weight:600;color:#94a3b8;text-transform:uppercase;margin-bottom:2px">Paid Rent</div><div style="font-size:13px;font-weight:700;color:#16a34a">&#x20B1;${nFmt(t.paidrent)}</div></div>
                    ${charges ? `<div style="padding:4px;grid-column:1/-1"><div style="font-size:10px;font-weight:600;color:#94a3b8;text-transform:uppercase;margin-bottom:2px">Charges</div><div style="font-size:12px;color:#64748b">${charges}</div></div>` : ''}
                    <div style="padding:4px;grid-column:1/-1"><div style="font-size:10px;font-weight:600;color:#94a3b8;text-transform:uppercase;margin-bottom:2px">Tenant</div><div style="font-size:14px;font-weight:600;color:#1e293b">${t.tenantname||'—'}</div></div>
                </div>
            </div>`;
        }
        function openDupModal(e) {
            if(e) e.preventDefault();
            const m = document.getElementById('duplicatedTransactionsModal'), c = document.getElementById('dupCardsContainer');
            if(!m || !c) return;
            m.classList.remove('hidden');
            c.innerHTML = '<div style="text-align:center;padding:24px;color:#94a3b8"><i class="fas fa-spinner fa-spin" style="font-size:24px"></i></div>';
            fetch('fetch_duplicated_transactions.php').then(r=>r.json()).then(data=>{
                c.innerHTML = data.length ? data.map(buildDupCardUser).join('') : '<div style="text-align:center;padding:32px;color:#22c55e"><i class="fas fa-check-circle" style="font-size:32px;display:block;margin-bottom:8px"></i>No duplicates found!</div>';
            }).catch(()=>c.innerHTML='<div style="text-align:center;padding:24px;color:#ef4444">Error loading</div>');
        }
    </script>
</body>
</html>
