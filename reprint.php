<?php
ob_start(); // Start output buffering
include 'config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    header("Location: index.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $branch = $_POST['branch'];
    $transaction_number = $_POST['transaction_number'];

    // Initialize database connection
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Determine the table based on selected branch
    $table = ($branch === 'Nova Market') ? 'collectednova' : (($branch === 'APM Branch' || $branch === 'APM') ? 'collectedapm' : 'collected');

    // Prepare SQL query
    $stmt = $conn->prepare("SELECT * FROM $table WHERE transaction_number = ?");
    $stmt->bind_param("s", $transaction_number);
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if transaction exists
    if ($result->num_rows > 0) {
        // Output the receipt (customize as needed)
        while ($row = $result->fetch_assoc()) {
            echo "Transaction Number: " . htmlspecialchars($row['transaction_number']) . "<br>";
            // Add other fields you want to display
        }
    } else {
        echo "No transaction found with that number.";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Reprint Receipt</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Google Fonts for fancy styling -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <!-- jQuery first -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <!-- Font Awesome for icons -->
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
        .bot-btn i { font-size:20px; }
        .bot-btn:hover, .bot-btn.active { color: var(--primary); }
        .bot-btn.warn  { color: #b45309; }
        .bot-btn.warn:hover, .bot-btn.warn.active { color:#d97706; }
        .bot-btn.danger { color: var(--danger); }
        .bot-btn.danger:hover { }
        .bot-btn.success-btn { color: var(--success); }
        .bot-btn.success-btn:hover { }
        .bot-btn i { font-size: 20px; transition: transform .2s; }
        .bot-btn:active i { transform: scale(0.9); }
        
        /* Icon Colors */
        .bot-btn.tx-btn i { color: #3b82f6; }
        .bot-btn.sum-btn i { color: #8b5cf6; }
        .bot-btn.dup-btn i { color: #f43f5e; }
        .bot-btn.mon-btn i { color: #f59e0b; }
        .bot-btn.void-btn i { color: #ef4444; }
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
            background: #ef4444; color: #fff; font-size: 10px; font-weight: 800;
            min-width: 17px; height: 17px; border-radius: 999px; padding: 0 4px;
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
                    <button onclick="hideLogoutModal()" class="w-full bg-gray-100 hover:bg-gray-100 text-gray-500 font-bold py-4 px-6 rounded-2xl transition-all active:scale-95 font-sans">
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

    <input type="hidden" id="branch" value="<?php echo htmlspecialchars($_SESSION['branch']); ?>">

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

    <div class="px-3">
        <div class="page-card animate-fadeIn">
            <h1 class="section-title">Reprint Receipt</h1>
            
            <div class="section-divider">
                <div class="section-divider-label">
                    <i class="fas fa-receipt mr-2"></i>Receipt Details
                </div>
            </div>
            
            <form action="reprintreceipt.php" method="POST">
                <!-- Hidden Branch Input (Automated based on user session) -->
                <input type="hidden" name="branch" value="<?php echo htmlspecialchars($_SESSION['branch']); ?>">
                
                <div class="mb-5 bg-blue-50 p-4 rounded-xl border border-blue-100 flex items-center justify-between">
                    <div>
                        <div class="text-[10px] font-bold text-blue-500 uppercase tracking-wider mb-1">Reprinting For</div>
                        <div class="text-base font-black text-blue-900"><?php echo htmlspecialchars($_SESSION['branch']); ?></div>
                    </div>
                    <div class="h-10 w-10 bg-blue-500 rounded-full flex items-center justify-center text-white shadow-lg shadow-blue-100">
                        <i class="fas fa-building text-sm"></i>
                    </div>
                </div>

                <div class="mb-6">
                    <label for="transaction_number" class="pos-label">
                        <i class="fas fa-hashtag"></i>Transaction Number
                    </label>
                    <input type="text" id="transaction_number" name="transaction_number"
                        class="pos-input w-full" required>
                </div>
                
                <div class="flex justify-center">
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-print"></i> Reprint Receipt
                    </button>
                </div>
            </form>
        </div>
    </div>



    <script src="nav_badge.js"></script>
    <script src="app_print_integration.js"></script>
    <script>
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
