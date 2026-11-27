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
    <title>Transactions — Collection POS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.4/css/jquery.dataTables.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="modern-bottom-nav.css">
    <link rel="stylesheet" href="pos-modern.css">
</head>
<body>
    <?php
    $posTitle = 'Transactions';
    $posSubtitle = ($branch ?: 'Branch') . ' • Today';
    $posIcon = 'fas fa-list-ul';
    include 'pos_shell_nav.php';
    include 'modern_bottom_nav.php';
    ?>

    <div class="pos-page-wrap animate-fadeIn">
        <header class="pos-desktop-header">
            <div class="dh-left">
                <div class="dh-icon">
                    <i class="fas fa-list-ul"></i>
                </div>
                <div>
                    <h1 class="dh-title">Today's Transactions</h1>
                    <div class="dh-meta">
                        <span class="dh-chip"><i class="fas fa-store"></i> <?php echo htmlspecialchars($branch); ?></span>
                        <span class="dh-chip dh-chip-slate"><i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?></span>
                        <span class="dh-chip dh-chip-slate"><i class="far fa-calendar"></i> <?php echo date('M d, Y'); ?></span>
                    </div>
                </div>
            </div>
            <div class="dh-right">
                <button type="button" onclick="fetchAndLoadTransactions()" class="btn-pos-action">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <a href="printsummary.php" class="btn-pos-action">
                    <i class="fas fa-file-invoice-dollar"></i> Summary
                </a>
                <a href="user.php" class="btn-pos-action" style="background:var(--gradient);color:#fff;border:none;">
                    <i class="fas fa-plus"></i> New Collection
                </a>
            </div>
        </header>

        <!-- KPI Strip -->
        <div class="pos-kpi-grid">
            <div class="pos-kpi-card">
                <div class="pos-kpi-icon icon-indigo"><i class="fas fa-receipt"></i></div>
                <div>
                    <div class="pos-kpi-label">Receipts Today</div>
                    <div class="pos-kpi-val" id="kpiTxCount">0</div>
                </div>
            </div>
            <div class="pos-kpi-card">
                <div class="pos-kpi-icon icon-emerald"><i class="fas fa-wallet"></i></div>
                <div>
                    <div class="pos-kpi-label">Total Collected</div>
                    <div class="pos-kpi-val" id="kpiTotalAmt" style="color:#047857;">&#x20B1;0.00</div>
                </div>
            </div>
            <div class="pos-kpi-card">
                <div class="pos-kpi-icon icon-purple"><i class="fas fa-home"></i></div>
                <div>
                    <div class="pos-kpi-label">Rent &amp; Arrears</div>
                    <div class="pos-kpi-val" id="kpiRentAmt" style="color:#6d28d9;">&#x20B1;0.00</div>
                </div>
            </div>
            <div class="pos-kpi-card">
                <div class="pos-kpi-icon icon-amber"><i class="fas fa-bolt"></i></div>
                <div>
                    <div class="pos-kpi-label">Utilities &amp; Charges</div>
                    <div class="pos-kpi-val" id="kpiOtherAmt" style="color:#b45309;">&#x20B1;0.00</div>
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
                <input id="searchInput" type="text" placeholder="Search #, space, tenant..." class="pos-input has-icon" style="height:38px;font-size:13px;">
            </div>
        </div>

        <!-- Cards container (Mobile only) -->
        <div id="cardsContainer" class="lg:hidden"></div>

        <!-- Table container (Desktop only) -->
        <div id="tableWrapper" class="hidden lg:block data-table-container mb-6 p-4">
            <div class="flex items-center justify-between mb-3 px-2">
                <div class="pos-panel-title">
                    <span class="pos-panel-icon icon-indigo"><i class="fas fa-table"></i></span>
                    <span>Transaction Ledger</span>
                </div>
                <span id="desktopCountBadge" class="dh-chip dh-chip-slate">0 entries</span>
            </div>
            <table id="transactionsTable" class="data-table w-full">
                <thead>
                    <tr>
                        <th>Transaction #</th>
                        <th>Date</th>
                        <th>Space</th>
                        <th>Code</th>
                        <th>Tenant</th>
                        <th>Paid Rent</th>
                        <th>Paid Arrear</th>
                        <th>Elec / Water</th>
                        <th>Charges</th>
                        <th>Total</th>
                        <th>Actions</th>
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

    function computeRowBreakdown(t) {
        const rent = parseFloat(t.paidrent || 0);
        const arrear = parseFloat(t.paidbal || 0);
        const elec = parseFloat(t.paidelec || 0) + parseFloat(t.paidelecarrear || 0);
        const water = parseFloat(t.paidwater || 0) + parseFloat(t.paidwaterarrear || 0);
        const cTotal = getChargesTotal(t.charges);
        const sumTotal = rent + arrear + elec + water + cTotal;
        const dbTotal = (t.total !== undefined && t.total !== null && parseFloat(t.total) > 0) ? parseFloat(t.total) : sumTotal;
        return { rent, arrear, elec, water, cTotal, total: dbTotal };
    }

    function buildCard(t) {
        const b = computeRowBreakdown(t);
        const charges = t.charges && t.charges !== 'null' ? t.charges : '';
        const method = t.payment_method || 'Cash';
        return `
        <div class="tx-card">
            <div class="tx-card-header">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="tx-tran">#${t.transaction_number}</span>
                        <span style="font-size:10px;font-weight:800;padding:2px 7px;border-radius:999px;background:${method==='Cheque'?'#fef3c7':'#e0e7ff'};color:${method==='Cheque'?'#92400e':'#4338ca'};">${method}</span>
                    </div>
                    <div class="tx-date">${fmtDate(t.collected_date)}</div>
                </div>
                <div class="tx-total">&#x20B1;${n(b.total)}</div>
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
                    <div class="tx-label"><i class="fas fa-money-bill-wave"></i> Rent</div>
                    <div class="tx-value" style="color:#047857">&#x20B1;${n(b.rent)}</div>
                </div>
                <div class="tx-field">
                    <div class="tx-label"><i class="fas fa-money-check"></i> Arrear</div>
                    <div class="tx-value" style="color:#2563eb">&#x20B1;${n(b.arrear)}</div>
                </div>
                <div class="tx-field">
                    <div class="tx-label"><i class="fas fa-building"></i> Branch</div>
                    <div class="tx-value">${t.branch||'—'}</div>
                </div>
                ${b.elec > 0 ? `<div class="tx-field"><div class="tx-label"><i class="fas fa-bolt" style="color:#d97706"></i> Elec</div><div class="tx-value" style="color:#b45309">&#x20B1;${n(b.elec)}</div></div>` : ''}
                ${b.water > 0 ? `<div class="tx-field"><div class="tx-label"><i class="fas fa-tint" style="color:#0284c7"></i> Water</div><div class="tx-value" style="color:#0369a1">&#x20B1;${n(b.water)}</div></div>` : ''}
                ${charges ? `<div class="tx-field" style="grid-column:1/-1">
                    <div class="tx-label"><i class="fas fa-file-invoice"></i> Charges</div>
                    <div class="tx-value" style="font-size:12px;color:#64748b;white-space:normal;font-weight:600">${charges}</div>
                </div>` : ''}
            </div>
            <div style="padding:8px 14px;background:#f8fafc;border-top:1px solid #f1f5f9;display:flex;justify-content:flex-end;gap:8px;">
                <a href="void_transaction.php?txn=${t.transaction_number}" class="btn-pos-action" style="padding:5px 10px;font-size:11.5px;color:#dc2626;border-color:#fecaca;background:#fef2f2;">
                    <i class="fas fa-ban"></i> Void
                </a>
            </div>
        </div>`;
    }

    let allData = [], filtered = [], currentPage = 1, perPage = 10;

    function updateKPIs() {
        let sumTotal = 0, sumRent = 0, sumOther = 0;
        allData.forEach(t => {
            const b = computeRowBreakdown(t);
            sumTotal += b.total;
            sumRent += b.rent + b.arrear;
            sumOther += b.elec + b.water + b.cTotal;
        });
        document.getElementById('kpiTxCount').textContent = allData.length.toLocaleString();
        document.getElementById('kpiTotalAmt').innerHTML = '&#x20B1;' + n(sumTotal);
        document.getElementById('kpiRentAmt').innerHTML = '&#x20B1;' + n(sumRent);
        document.getElementById('kpiOtherAmt').innerHTML = '&#x20B1;' + n(sumOther);
        const badge = document.getElementById('desktopCountBadge');
        if (badge) badge.textContent = `${allData.length} entries`;
    }

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
            container.innerHTML = '<div class="pos-panel text-center py-12"><i class="fas fa-inbox text-3xl mb-3 block" style="color:#94a3b8;"></i><div class="font-bold text-gray-700">No transactions found</div><div class="text-xs text-gray-400 mt-1">There are no records matching your search.</div></div>';
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
            btns += `<button class="pg-btn${i===currentPage?' pg-active':''}" onclick="goPage(${i})">${i}</button>`;
        }
        if(pe < pages) btns += `<span class="pg-ellipsis">…</span>`;
        btns += `<button class="pg-btn" ${currentPage===pages?'disabled':''} onclick="goPage(${currentPage+1})">›</button>`;
        btns += `<button class="pg-btn" ${currentPage===pages?'disabled':''} onclick="goPage(${pages})">»</button>`;
        pEl.innerHTML = btns;
    }

    function goPage(p){ currentPage = p; render(); window.scrollTo({top:0,behavior:'smooth'}); }

    function fetchAndLoadTransactions() {
        document.getElementById('cardsContainer').innerHTML =
            '<div class="pos-panel text-center py-12" style="color:#94a3b8;"><i class="fas fa-spinner fa-spin text-2xl mb-2 block"></i>Loading transactions...</div>';

        fetch('fetch_transactions.php')
            .then(r => r.json())
            .then(data => {
                data.sort((a,b) => new Date(b.collected_date) - new Date(a.collected_date));
                allData = data;
                updateKPIs();
                applyFilter();
                renderTable();
            })
            .catch(() => {
                document.getElementById('cardsContainer').innerHTML =
                    '<div class="pos-panel text-center py-12 text-red-500"><i class="fas fa-exclamation-triangle text-2xl mb-2 block"></i>Error loading transactions</div>';
            });
    }

    function renderTable() {
        if ($.fn.DataTable.isDataTable('#transactionsTable')) {
            $('#transactionsTable').DataTable().destroy();
        }
        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = allData.map(t => {
            const b = computeRowBreakdown(t);
            const charges = t.charges && t.charges !== 'null' ? t.charges : '—';
            const utilTotal = b.elec + b.water;
            return `
                <tr>
                    <td class="font-bold" style="color:var(--primary-blue)">#${t.transaction_number}</td>
                    <td>${fmtDate(t.collected_date)}</td>
                    <td><span style="font-weight:700;padding:2px 7px;border-radius:6px;background:#eef2ff;color:#4338ca;font-size:11.5px;">${t.spacecode||'—'}</span></td>
                    <td>${t.tenantcode||'—'}</td>
                    <td class="font-bold" style="color:#0f172a">${t.tenantname||'—'}</td>
                    <td style="color:#047857;font-weight:700">&#x20B1;${n(b.rent)}</td>
                    <td style="color:#2563eb;font-weight:700">&#x20B1;${n(b.arrear)}</td>
                    <td style="color:#b45309;font-weight:700">${utilTotal > 0 ? '&#x20B1;'+n(utilTotal) : '—'}</td>
                    <td style="font-size:12px;color:#64748b;max-width:190px;white-space:normal">${charges}</td>
                    <td style="color:#047857;font-weight:800;background:#ecfdf5">&#x20B1;${n(b.total)}</td>
                    <td>
                        <a href="void_transaction.php?txn=${t.transaction_number}" class="btn-pos-action" style="padding:4px 9px;font-size:11.5px;color:#dc2626;border-color:#fecaca;background:#fef2f2;">
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
                search: "Search:",
                emptyTable: '<div class="py-10 text-center text-gray-500">No transactions recorded today.</div>'
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
