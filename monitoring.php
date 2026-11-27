<?php
include 'config.php';
session_start();

if (!isset($_SESSION["username"])) {
    header("Location: index.php");
    exit();
}

$username = $_SESSION["username"];
$lname = $_SESSION["lname"] ?? '';
$branch = $_SESSION["branch"] ?? '';

date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Monitoring — Collection POS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="modern-bottom-nav.css">
    <link rel="stylesheet" href="pos-modern.css">
    <style>
        .unpaid-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 8px;
            max-height: 520px;
            overflow-y: auto;
            padding-right: 3px;
        }
        @media (min-width: 1024px) {
            .unpaid-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }
        }
        .unpaid-item {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 13px;
            padding: 11px 13px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            cursor: pointer;
            transition: all 0.16s ease;
        }
        .unpaid-item:hover {
            background: #eff6ff;
            border-color: #93c5fd;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
        }
        .unpaid-item:active { transform: scale(0.98); }
    </style>
</head>
<body>
    <?php
    $posTitle = 'Monitoring';
    $posSubtitle = ($branch ?: 'Branch') . ' • ' . date('M d, Y');
    $posIcon = 'fas fa-chart-pie';
    include 'pos_shell_nav.php';
    include 'modern_bottom_nav.php';
    ?>

    <div class="pos-page-wrap animate-fadeIn">
        <header class="pos-desktop-header">
            <div class="dh-left">
                <div class="dh-icon dh-icon-amber">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div>
                    <h1 class="dh-title">Daily Collection Monitoring</h1>
                    <div class="dh-meta">
                        <span class="dh-chip"><i class="fas fa-store"></i> <?php echo htmlspecialchars($branch); ?></span>
                        <span class="dh-chip dh-chip-slate"><i class="far fa-calendar"></i> <?php echo date('M d, Y'); ?></span>
                        <span class="dh-chip dh-chip-slate"><i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?></span>
                    </div>
                </div>
            </div>
            <div class="dh-right">
                <button type="button" onclick="loadMonitoringData()" class="btn-pos-action">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <a href="user.php" class="btn-pos-action" style="background:var(--gradient);color:#fff;border:none;">
                    <i class="fas fa-plus"></i> New Collection
                </a>
            </div>
        </header>

        <div class="pos-two-col">
            <!-- Left: Progress & KPI Summary -->
            <section class="pos-panel">
                <div class="pos-panel-header">
                    <div class="pos-panel-title">
                        <span class="pos-panel-icon icon-indigo"><i class="fas fa-calendar-check"></i></span>
                        <span>Collected Today</span>
                    </div>
                    <span class="dh-chip dh-chip-slate"><?php echo date('M d, Y'); ?></span>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:14px;padding:14px;text-align:center;">
                        <div style="font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:0.06em;color:#2563eb;">Paid Spaces</div>
                        <div id="mon-collected" style="font-size:28px;font-weight:800;color:#1e3a8a;line-height:1.15;margin:4px 0;">0</div>
                        <div id="mon-total" style="font-size:11px;font-weight:600;color:#3b82f6;">out of 0</div>
                    </div>
                    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:14px;padding:14px;text-align:center;">
                        <div style="font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:0.06em;color:#dc2626;">Pending Spaces</div>
                        <div id="mon-pending" style="font-size:28px;font-weight:800;color:#991b1b;line-height:1.15;margin:4px 0;">0</div>
                        <div id="mon-percent" style="font-size:11px;font-weight:600;color:#ef4444;">0% left</div>
                    </div>
                </div>

                <div style="margin-bottom:14px;">
                    <div class="flex justify-between text-xs font-bold mb-1.5" style="color:#475569;">
                        <span>Completion Progress</span>
                        <span id="mon-completion-pct">0%</span>
                    </div>
                    <div style="width:100%;height:10px;background:#e2e8f0;border-radius:999px;overflow:hidden;">
                        <div id="mon-bar" style="height:100%;width:0%;background:linear-gradient(90deg,#10b981,#2563eb);border-radius:999px;transition:width 0.6s ease;"></div>
                    </div>
                </div>

                <div style="background:#f8fafc;border:1px solid var(--border);border-radius:12px;padding:11px 13px;display:flex;align-items:flex-start;gap:10px;">
                    <i class="fas fa-bolt" style="color:#2563eb;margin-top:2px;font-size:13px;"></i>
                    <div style="font-size:12px;color:#334155;line-height:1.45;font-weight:500;">
                        <strong style="color:#1e40af;">Quick Collect:</strong> Tap any unpaid space card to launch <strong>New Collection</strong> pre-filled with that tenant's details.
                    </div>
                </div>
            </section>

            <!-- Right: Unpaid Spaces List -->
            <section class="pos-panel">
                <div class="pos-panel-header">
                    <div class="pos-panel-title">
                        <span class="pos-panel-icon icon-danger"><i class="fas fa-user-clock"></i></span>
                        <span>Unpaid Spaces</span>
                    </div>
                    <span id="mon-list-count" class="dh-chip dh-chip-danger">0 pending</span>
                </div>

                <div class="input-shell mb-3">
                    <i class="fas fa-search input-prefix-icon"></i>
                    <input type="text" id="mon-search" onkeyup="filterList()" placeholder="Search space code or tenant name..." class="pos-input has-icon" style="height:42px;font-size:13.5px;">
                </div>

                <div id="mon-list" class="unpaid-grid"></div>
            </section>
        </div>
    </div>

    <script src="nav_badge.js"></script>
    <script>
        function loadMonitoringData() {
            const branch = document.getElementById('branch').value;
            const date = '<?php echo $today; ?>';

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
                        document.getElementById('mon-completion-pct').textContent = `${percent}%`;
                        document.getElementById('mon-bar').style.width = percent + '%';

                        const listCount = document.getElementById('mon-list-count');
                        if (listCount) listCount.textContent = `${pending} pending`;
                    }
                }).catch(() => {});

            const listContainer = document.getElementById('mon-list');
            listContainer.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:24px;font-size:13px;color:#94a3b8;"><i class="fas fa-spinner fa-spin mr-2"></i>Loading unpaid spaces...</div>';

            fetch(`fetch_pending_list.php?branch=${encodeURIComponent(branch)}&date=${encodeURIComponent(date)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        if (data.pending.length === 0) {
                            listContainer.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:32px;background:#ecfdf5;color:#059669;border-radius:14px;font-weight:700;"><i class="fas fa-check-circle text-2xl mb-2 block"></i>All collections complete for today!</div>';
                        } else {
                            listContainer.innerHTML = data.pending.map(item => `
                                <div class="unpaid-item list-item" onclick="collectNow('${item.spacecode}')">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <div style="width:36px;height:36px;border-radius:10px;background:#fee2e2;color:#dc2626;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                            <i class="fas fa-store-alt text-xs"></i>
                                        </div>
                                        <div style="min-width:0;flex:1;">
                                            <div style="font-weight:800;font-size:13px;color:#0f172a;line-height:1.2;">${item.spacecode}</div>
                                            <div style="font-size:11px;font-weight:600;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${item.tenantname}</div>
                                        </div>
                                    </div>
                                    <i class="fas fa-chevron-right" style="color:#94a3b8;font-size:11px;flex-shrink:0;"></i>
                                </div>
                            `).join('');
                        }
                    }
                })
                .catch(() => {
                    listContainer.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:24px;color:#dc2626;font-size:13px;">Error loading unpaid list.</div>';
                });
        }

        function filterList() {
            const filter = document.getElementById('mon-search').value.toUpperCase();
            document.querySelectorAll('.list-item').forEach(item => {
                const text = item.textContent || item.innerText;
                item.style.display = text.toUpperCase().indexOf(filter) > -1 ? "" : "none";
            });
        }

        function collectNow(spacecode) {
            localStorage.setItem('prefill_spacecode', spacecode);
            window.location.href = 'user.php';
        }

        document.addEventListener('DOMContentLoaded', loadMonitoringData);
    </script>
</body>
</html>
