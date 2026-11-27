<?php
ob_start();
session_start();

if (!isset($_SESSION["username"])) {
    header("Location: index.php");
    exit();
}

// Handle direct POST lookup if ever posted to itself
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include 'config.php';

    $branch = $_POST['branch'] ?? '';
    $transaction_number = $_POST['transaction_number'] ?? '';

    $table = ($branch === 'Nova Market') ? 'collectednova' :
             (($branch === 'APM Branch' || $branch === 'APM') ? 'collectedapm' :
             (($branch === 'ACC' || $branch === 'Ambulant') ? 'collectedacc' : 'collected'));

    $stmt = $conn->prepare("SELECT * FROM `$table` WHERE transaction_number = ?");
    if ($stmt) {
        $stmt->bind_param("s", $transaction_number);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "Transaction Number: " . htmlspecialchars($row['transaction_number']) . "<br>";
            }
        } else {
            echo "No transaction found with that number.";
        }
        $stmt->close();
    }

    if (isset($conn) && $conn) {
        $conn->close();
    }
}

$username = $_SESSION['username'] ?? '';
$branch = $_SESSION['branch'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Reprint Receipt — Collection POS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="modern-bottom-nav.css">
    <link rel="stylesheet" href="pos-modern.css">
</head>
<body>
    <?php
    $posTitle = 'Reprint Receipt';
    $posSubtitle = ($branch ?: 'Branch') . ' • Receipt Lookup';
    $posIcon = 'fas fa-print';
    include 'pos_shell_nav.php';
    include 'modern_bottom_nav.php';
    ?>

    <div class="pos-page-wrap animate-fadeIn">
        <header class="pos-desktop-header">
            <div class="dh-left">
                <div class="dh-icon dh-icon-sky">
                    <i class="fas fa-print"></i>
                </div>
                <div>
                    <h1 class="dh-title">Reprint Receipt</h1>
                    <div class="dh-meta">
                        <span class="dh-chip"><i class="fas fa-store"></i> <?php echo htmlspecialchars($branch); ?></span>
                        <span class="dh-chip dh-chip-slate"><i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?></span>
                    </div>
                </div>
            </div>
            <div class="dh-right">
                <a href="transactions.php" class="btn-pos-action">
                    <i class="fas fa-list-ul"></i> All Transactions
                </a>
                <a href="user.php" class="btn-pos-action" style="background:var(--gradient);color:#fff;border:none;">
                    <i class="fas fa-plus"></i> New Collection
                </a>
            </div>
        </header>

        <div class="pos-two-col">
            <!-- Left: Reprint Form Card -->
            <section class="pos-panel">
                <div class="pos-panel-header">
                    <div class="pos-panel-title">
                        <span class="pos-panel-icon icon-sky"><i class="fas fa-receipt"></i></span>
                        <span>Receipt Details</span>
                    </div>
                    <span class="dh-chip"><?php echo htmlspecialchars($branch); ?></span>
                </div>

                <form id="reprintForm" action="reprintreceipt.php" method="POST">
                    <input type="hidden" name="branch" value="<?php echo htmlspecialchars($branch); ?>">

                    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:13px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                        <div>
                            <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:0.06em;color:#2563eb;">Active Branch</div>
                            <div style="font-size:15px;font-weight:800;color:#1e3a8a;margin-top:2px;"><?php echo htmlspecialchars($branch); ?></div>
                        </div>
                        <div style="width:38px;height:38px;border-radius:11px;background:#2563eb;color:#fff;display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-building text-sm"></i>
                        </div>
                    </div>

                    <div class="mb-5">
                        <label for="transaction_number" class="field-label">
                            <i class="fas fa-hashtag" style="color:var(--primary-blue);"></i> Transaction Number *
                        </label>
                        <div class="input-shell">
                            <i class="fas fa-hashtag input-prefix-icon"></i>
                            <input type="text" id="transaction_number" name="transaction_number"
                                   class="pos-input has-icon font-bold" placeholder="Enter transaction # (e.g. 10245)"
                                   inputmode="numeric" required>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">
                        <i class="fas fa-print"></i> Reprint Receipt
                    </button>
                </form>
            </section>

            <!-- Right: Today's Receipts Quick-Pick -->
            <section class="pos-panel">
                <div class="pos-panel-header">
                    <div class="pos-panel-title">
                        <span class="pos-panel-icon icon-indigo"><i class="fas fa-history"></i></span>
                        <span>Today's Receipts</span>
                    </div>
                    <span id="recentCountChip" class="dh-chip dh-chip-slate">Loading...</span>
                </div>

                <div class="input-shell mb-3">
                    <i class="fas fa-search input-prefix-icon"></i>
                    <input type="text" id="recentSearchInput" placeholder="Filter by #, space, or tenant..." class="pos-input has-icon" style="height:40px;font-size:13px;">
                </div>

                <div id="recentReceiptsList" class="quick-pick-list">
                    <div style="text-align:center;padding:24px;color:#94a3b8;font-size:13px;">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Loading today's receipts...
                    </div>
                </div>
            </section>
        </div>
    </div>

    <script src="nav_badge.js"></script>
    <script src="app_print_integration.js"></script>
    <script>
        let recentTxData = [];

        function getTxTotal(t) {
            if (t.total !== undefined && t.total !== null && parseFloat(t.total) > 0) {
                return parseFloat(t.total);
            }
            let cTotal = 0;
            if (t.charges && t.charges !== 'null') {
                t.charges.split(',').forEach(p => {
                    const m = p.match(/:\s*([\d,.]+)/);
                    if (m) cTotal += parseFloat(m[1].replace(/,/g, '')) || 0;
                });
            }
            return (parseFloat(t.paidrent || 0) + parseFloat(t.paidbal || 0) +
                    parseFloat(t.paidelec || 0) + parseFloat(t.paidelecarrear || 0) +
                    parseFloat(t.paidwater || 0) + parseFloat(t.paidwaterarrear || 0) + cTotal);
        }

        function renderRecentReceipts(filter) {
            const listEl = document.getElementById('recentReceiptsList');
            const chip = document.getElementById('recentCountChip');
            const q = (filter || '').toLowerCase().trim();
            const filtered = !q ? recentTxData : recentTxData.filter(t =>
                String(t.transaction_number || '').toLowerCase().includes(q) ||
                String(t.spacecode || '').toLowerCase().includes(q) ||
                String(t.tenantname || '').toLowerCase().includes(q)
            );
            if (chip) chip.textContent = `${filtered.length} receipts`;

            if (!filtered.length) {
                listEl.innerHTML = '<div style="text-align:center;padding:28px 12px;color:#94a3b8;font-size:13px;">No receipts found today matching filter.</div>';
                return;
            }

            listEl.innerHTML = filtered.slice(0, 40).map(t => {
                const amt = getTxTotal(t).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                return `
                    <button type="button" class="quick-pick-item" onclick="selectForReprint('${t.transaction_number}')">
                        <div style="min-width:0;flex:1;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="font-size:13.5px;font-weight:800;color:#1d4ed8;">#${t.transaction_number}</span>
                                <span style="font-size:11px;font-weight:700;padding:2px 7px;border-radius:6px;background:#e0e7ff;color:#4338ca;">${t.spacecode || '—'}</span>
                            </div>
                            <div style="font-size:12px;font-weight:600;color:#334155;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${t.tenantname || '—'}</div>
                        </div>
                        <div style="text-align:right;flex-shrink:0;">
                            <div style="font-size:13.5px;font-weight:800;color:#047857;">₱${amt}</div>
                            <div style="font-size:10.5px;font-weight:700;color:#2563eb;">Tap to select</div>
                        </div>
                    </button>
                `;
            }).join('');
        }

        function selectForReprint(txNum) {
            const input = document.getElementById('transaction_number');
            if (input) {
                input.value = txNum;
                input.focus();
            }
        }

        document.getElementById('recentSearchInput').addEventListener('input', function () {
            renderRecentReceipts(this.value);
        });

        document.addEventListener('DOMContentLoaded', function () {
            fetch('fetch_transactions.php')
                .then(r => r.json())
                .then(data => {
                    recentTxData = Array.isArray(data) ? data : [];
                    renderRecentReceipts('');
                })
                .catch(() => {
                    document.getElementById('recentReceiptsList').innerHTML =
                        '<div style="text-align:center;padding:20px;color:#dc2626;font-size:13px;">Could not load today\'s receipts.</div>';
                });
        });
    </script>
</body>
</html>
