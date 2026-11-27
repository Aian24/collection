<?php
session_start();
if (!isset($_SESSION["username"])) {
    header("Location: index.php");
    exit();
}
$username = $_SESSION["username"];
$branch = $_SESSION["branch"] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Duplicated Transactions — Collection POS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.4/css/jquery.dataTables.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="modern-bottom-nav.css">
    <link rel="stylesheet" href="pos-modern.css">
</head>
<body>
    <?php
    $posTitle = 'Duplicated';
    $posSubtitle = 'Transactions needing attention';
    $posIcon = 'fas fa-exclamation-triangle';
    $posDanger = true;
    include 'pos_shell_nav.php';
    include 'modern_bottom_nav.php';
    ?>

    <div class="pos-page-wrap animate-fadeIn">
        <header class="pos-desktop-header">
            <div class="dh-left">
                <div class="dh-icon dh-icon-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <h1 class="dh-title">Duplicated Transactions</h1>
                    <div class="dh-meta">
                        <span class="dh-chip dh-chip-danger"><i class="fas fa-shield-alt"></i> Same-Day Duplicate Check</span>
                        <span class="dh-chip dh-chip-slate"><i class="fas fa-store"></i> <?php echo htmlspecialchars($branch); ?></span>
                        <span class="dh-chip dh-chip-slate"><i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?></span>
                    </div>
                </div>
            </div>
            <div class="dh-right">
                <button type="button" onclick="fetchAndLoadTransactions()" class="btn-pos-action">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <a href="void_transaction.php" class="btn-pos-action" style="background:var(--gradient-danger);color:#fff;border:none;">
                    <i class="fas fa-ban"></i> Void Entry
                </a>
            </div>
        </header>

        <!-- Alert KPI Strip -->
        <div class="pos-kpi-grid">
            <div class="pos-kpi-card" style="border-color:#fecaca;">
                <div class="pos-kpi-icon icon-danger"><i class="fas fa-copy"></i></div>
                <div>
                    <div class="pos-kpi-label">Duplicate Rows</div>
                    <div class="pos-kpi-val" id="kpiDupCount" style="color:#dc2626;">0</div>
                </div>
            </div>
            <div class="pos-kpi-card">
                <div class="pos-kpi-icon icon-amber"><i class="fas fa-store-alt"></i></div>
                <div>
                    <div class="pos-kpi-label">Affected Spaces</div>
                    <div class="pos-kpi-val" id="kpiDupSpaces" style="color:#b45309;">0</div>
                </div>
            </div>
            <div class="pos-kpi-card">
                <div class="pos-kpi-icon icon-indigo"><i class="fas fa-coins"></i></div>
                <div>
                    <div class="pos-kpi-label">Total Flagged Amount</div>
                    <div class="pos-kpi-val" id="kpiDupTotal">&#x20B1;0.00</div>
                </div>
            </div>
            <div class="pos-kpi-card">
                <div class="pos-kpi-icon icon-emerald" id="kpiStatusIcon"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="pos-kpi-label">Audit Status</div>
                    <div class="pos-kpi-val" id="kpiDupStatus" style="font-size:15px;">Checking...</div>
                </div>
            </div>
        </div>

        <!-- Search + Entries row (Mobile only) -->
        <div class="pos-panel flex lg:hidden items-center justify-between mb-3 gap-2" style="padding:10px 12px;">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold uppercase" style="color:var(--text-muted);">Show</span>
                <select id="entriesPerPage" class="pos-input" style="width:68px;height:38px;padding:4px 8px;font-size:13px;">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
            <div class="input-shell flex-1" style="max-width:220px;">
                <i class="fas fa-search input-prefix-icon"></i>
                <input id="searchInput" type="text" placeholder="Search duplicates..." class="pos-input has-icon" style="height:38px;font-size:13px;">
            </div>
        </div>

        <!-- Cards container (Mobile only) -->
        <div id="cardsContainer" class="lg:hidden"></div>

        <!-- Table container (Desktop only) -->
        <div id="tableWrapper" class="hidden lg:block data-table-container data-table-danger-wrap mb-6 p-4" style="border-top: 3px solid #ef4444;">
            <div class="flex items-center justify-between mb-3 px-2">
                <div class="pos-panel-title" style="color:#b91c1c;">
                    <span class="pos-panel-icon icon-danger"><i class="fas fa-exclamation-triangle"></i></span>
                    <span>Flagged Duplicate Receipts</span>
                </div>
                <span id="desktopDupBadge" class="dh-chip dh-chip-danger">0 flagged</span>
            </div>
            <table id="transactionsTable" class="data-table data-table-danger w-full">
                <thead>
                    <tr>
                        <th>Transaction #</th>
                        <th>Date</th>
                        <th>Space</th>
                        <th>Code</th>
                        <th>Collector</th>
                        <th>Tenant</th>
                        <th>Paid Rent</th>
                        <th>Paid Arrear</th>
                        <th>Charges</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="tableBody"></tbody>
            </table>
        </div>

        <!-- Pagination + info (Mobile) -->
        <div class="flex lg:hidden flex-col items-center gap-2 mt-4 mb-2">
            <div id="paginationInfo" class="text-xs font-semibold" style="color:var(--text-muted);"></div>
            <div id="paginationBtns" class="flex flex-wrap gap-1 justify-center"></div>
        </div>
    </div>

    <script src="nav_badge.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.4/js/jquery.dataTables.js"></script>
    <script>
    function n(v){ return parseFloat(v||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2}); }
    function fmtDate(d){
        const dt = new Date(d);
        if(isNaN(dt)) return d;
        return dt.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})
            +' '+dt.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'});
    }

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

    function computeRowTotal(t) {
        if (t.total !== undefined && t.total !== null && parseFloat(t.total) > 0) {
            return parseFloat(t.total);
        }
        const cTotal = getChargesTotal(t.charges);
        return parseFloat(t.paidrent||0) + parseFloat(t.paidbal||0) +
               parseFloat(t.paidelec||0) + parseFloat(t.paidelecarrear||0) +
               parseFloat(t.paidwater||0) + parseFloat(t.paidwaterarrear||0) + cTotal;
    }

    function updateDupKPIs() {
        const spaces = new Set(allData.map(t => t.spacecode).filter(Boolean));
        const totalAmt = allData.reduce((s, t) => s + computeRowTotal(t), 0);

        document.getElementById('kpiDupCount').textContent = allData.length.toLocaleString();
        document.getElementById('kpiDupSpaces').textContent = spaces.size.toLocaleString();
        document.getElementById('kpiDupTotal').innerHTML = '&#x20B1;' + n(totalAmt);

        const statusEl = document.getElementById('kpiDupStatus');
        const iconEl = document.getElementById('kpiStatusIcon');
        const desktopBadge = document.getElementById('desktopDupBadge');
        if (desktopBadge) desktopBadge.textContent = `${allData.length} flagged`;

        if (allData.length > 0) {
            statusEl.textContent = 'Review Needed';
            statusEl.style.color = '#dc2626';
            iconEl.className = 'pos-kpi-icon icon-danger';
            iconEl.innerHTML = '<i class="fas fa-exclamation-circle"></i>';
        } else {
            statusEl.textContent = 'All Clear';
            statusEl.style.color = '#059669';
            iconEl.className = 'pos-kpi-icon icon-emerald';
            iconEl.innerHTML = '<i class="fas fa-check-circle"></i>';
        }
    }

    function buildCard(t) {
        const paid = computeRowTotal(t);
        const charges = t.charges && t.charges !== 'null' ? t.charges : '';
        return `
        <div class="tx-card tx-card-danger">
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
                    <div class="tx-value" style="font-size:13.5px;color:#0f172a">${t.tenantname||'—'}</div>
                </div>
                <div class="tx-field">
                    <div class="tx-label"><i class="fas fa-money-bill-wave"></i> Paid Rent</div>
                    <div class="tx-value" style="color:#047857">&#x20B1;${n(t.paidrent)}</div>
                </div>
                <div class="tx-field">
                    <div class="tx-label"><i class="fas fa-money-check"></i> Paid Bal</div>
                    <div class="tx-value" style="color:#2563eb">&#x20B1;${n(t.paidbal)}</div>
                </div>
                <div class="tx-field">
                    <div class="tx-label"><i class="fas fa-building"></i> Branch</div>
                    <div class="tx-value">${t.branch||'—'}</div>
                </div>
                ${charges ? `<div class="tx-field" style="grid-column:1/-1">
                    <div class="tx-label"><i class="fas fa-file-invoice"></i> Charges</div>
                    <div class="tx-value" style="font-size:12px;color:#64748b;white-space:normal;font-weight:600">${charges}</div>
                </div>` : ''}
            </div>
            <div style="padding:10px 14px;background:#fef2f2;border-top:1px solid #fecaca;display:flex;justify-content:flex-end;">
                <a href="void_transaction.php?txn=${t.transaction_number}" class="btn-pos-action" style="background:#dc2626;color:#fff;border:none;font-size:12.5px;">
                    <i class="fas fa-ban"></i> Void Issue
                </a>
            </div>
        </div>`;
    }

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
        const total = filtered.length;
        const pages = Math.max(1, Math.ceil(total / perPage));
        currentPage = Math.min(currentPage, pages);
        const start = (currentPage - 1) * perPage;
        const slice = filtered.slice(start, start + perPage);

        const container = document.getElementById('cardsContainer');
        if (!total) {
            container.innerHTML = '<div class="pos-panel text-center py-12"><div style="width:58px;height:58px;border-radius:999px;background:#ecfdf5;color:#059669;display:inline-flex;align-items:center;justify-content:center;font-size:24px;margin-bottom:10px;"><i class="fas fa-check"></i></div><div class="font-bold text-gray-800">No duplicates found!</div><div class="text-xs text-gray-400 mt-1">All today\'s transactions look clean.</div></div>';
        } else {
            container.innerHTML = slice.map(buildCard).join('');
        }

        document.getElementById('paginationInfo').textContent =
            total ? `Showing ${start+1}–${Math.min(start+perPage,total)} of ${total} entries` : '';

        const pEl = document.getElementById('paginationBtns');
        if (pages <= 1) { pEl.innerHTML=''; return; }

        let btns = '';
        btns += `<button class="pg-btn" ${currentPage===1?'disabled':''} onclick="goPage(1)">«</button>`;
        btns += `<button class="pg-btn" ${currentPage===1?'disabled':''} onclick="goPage(${currentPage-1})">‹</button>`;
        const radius = 2;
        let ps = Math.max(1, currentPage-radius), pe = Math.min(pages, currentPage+radius);
        if(pe-ps < radius*2){ ps=Math.max(1,pe-radius*2); pe=Math.min(pages,ps+radius*2); }
        if(ps > 1) btns += `<span class="pg-ellipsis">…</span>`;
        for(let i=ps;i<=pe;i++){
            btns += `<button class="pg-btn${i===currentPage?' pg-active-danger':''}" onclick="goPage(${i})">${i}</button>`;
        }
        if(pe < pages) btns += `<span class="pg-ellipsis">…</span>`;
        btns += `<button class="pg-btn" ${currentPage===pages?'disabled':''} onclick="goPage(${currentPage+1})">›</button>`;
        btns += `<button class="pg-btn" ${currentPage===pages?'disabled':''} onclick="goPage(${pages})">»</button>`;
        pEl.innerHTML = btns;
    }

    function goPage(p){ currentPage = p; render(); window.scrollTo({top:0,behavior:'smooth'}); }

    function fetchAndLoadTransactions() {
        document.getElementById('cardsContainer').innerHTML =
            '<div class="pos-panel text-center py-12" style="color:#94a3b8;"><i class="fas fa-spinner fa-spin text-2xl mb-2 block"></i>Loading duplicates...</div>';

        fetch('fetch_duplicated_transactions.php')
            .then(r => r.json())
            .then(data => {
                data.sort((a,b) => new Date(b.collected_date) - new Date(a.collected_date));
                allData = data;
                updateDupKPIs();
                applyFilter();
                renderTable();
            })
            .catch(() => {
                document.getElementById('cardsContainer').innerHTML =
                    '<div class="pos-panel text-center py-12 text-red-500"><i class="fas fa-exclamation-triangle text-2xl mb-2 block"></i>Error loading duplicates</div>';
            });
    }

    function renderTable() {
        if ($.fn.DataTable.isDataTable('#transactionsTable')) {
            $('#transactionsTable').DataTable().destroy();
        }
        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = allData.map(t => {
            const paid = computeRowTotal(t);
            const charges = t.charges && t.charges !== 'null' ? t.charges : '—';
            return `
                <tr>
                    <td class="font-bold" style="color:#dc2626">#${t.transaction_number}</td>
                    <td>${fmtDate(t.collected_date)}</td>
                    <td><span style="font-weight:700;padding:2px 7px;border-radius:6px;background:#fee2e2;color:#991b1b;font-size:11.5px;">${t.spacecode||'—'}</span></td>
                    <td>${t.tenantcode||'—'}</td>
                    <td>${t.collector||'—'}</td>
                    <td class="font-bold" style="color:#0f172a">${t.tenantname||'—'}</td>
                    <td style="color:#047857;font-weight:700">&#x20B1;${n(t.paidrent)}</td>
                    <td style="color:#2563eb;font-weight:700">&#x20B1;${n(t.paidbal)}</td>
                    <td style="font-size:12px;color:#64748b;max-width:190px;white-space:normal">${charges}</td>
                    <td style="color:#047857;font-weight:800;background:#ecfdf5">&#x20B1;${n(paid)}</td>
                    <td>
                        <a href="void_transaction.php?txn=${t.transaction_number}" class="btn-pos-action" style="padding:5px 10px;font-size:12px;color:#dc2626;border-color:#fecaca;background:#fef2f2;">
                            <i class="fas fa-ban"></i> Void
                        </a>
                    </td>
                </tr>
            `;
        }).join('');

        $('#transactionsTable').DataTable({
            responsive: true,
            order: [[1, 'desc']],
            pageLength: 25,
            language: {
                search: "Search Duplicates:",
                emptyTable: '<div class="py-10 text-center text-green-600 font-bold"><i class="fas fa-check-circle mr-2"></i>No duplicates found — all transactions look clean!</div>'
            }
        });
    }

    document.getElementById('searchInput').addEventListener('input', applyFilter);
    document.getElementById('entriesPerPage').addEventListener('change', function() {
        perPage = parseInt(this.value); currentPage = 1; render();
    });

    document.addEventListener('DOMContentLoaded', fetchAndLoadTransactions);
    </script>
</body>
</html>
