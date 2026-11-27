<?php
ob_start();
include 'config.php';
session_start();

$message = '';
$message_type = '';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION["username"])) {
    header("Location: index.php");
    exit();
}

date_default_timezone_set('Asia/Manila');
$void_date = date('Y-m-d H:i:s');

if (isset($_POST['void_submit'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['void_transaction_message'] = 'Invalid form submission. Please try again.';
        $_SESSION['void_transaction_message_type'] = 'error';
        header("Location: void_transaction.php");
        exit();
    }

    $transaction_number = $_POST['transaction_number'];
    $branch = $_POST['branch'];
    $note = $_POST['note'];

    $valid_branches = ['nova', 'sanko', 'apm'];
    if (!in_array($branch, $valid_branches)) {
        $message = 'Invalid branch selected.';
        $message_type = 'error';
    } else {
        if ($branch === 'sanko') {
            $branch_table = 'collected';
        } else {
            $branch_table = 'collected' . $branch;
        }

        $conn->begin_transaction();

        try {
            $checkColumnQuery = "SHOW COLUMNS FROM $branch_table LIKE 'charges'";
            $columnResult = $conn->query($checkColumnQuery);
            $chargesColumnExists = $columnResult && $columnResult->num_rows > 0;

            $selectQuery = "SELECT rentbal, runningbal, tenantcode, spacecode, rent, paidrent, paidbal, elecbal, paidelec, paidelecarrear, waterbal, paidwater, paidwaterarrear, elecarrear, waterarrear, charges, collector, tenantname FROM $branch_table WHERE transaction_number = ?";
            $stmt = $conn->prepare($selectQuery);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param('s', $transaction_number);
            $stmt->execute();
            $result = $stmt->get_result();
            $transaction = $result->fetch_assoc();

            if (!$transaction) {
                throw new Exception("Transaction not found.");
            }

            $original_rentbal = $transaction['rentbal'];
            $original_runningbal = $transaction['runningbal'];
            $original_elecbal = $transaction['elecbal'] ?? 0;
            $original_waterbal = $transaction['waterbal'] ?? 0;
            $original_elecarrear = $transaction['elecarrear'] ?? 0;
            $original_waterarrear = $transaction['waterarrear'] ?? 0;
            $tenantcode = $transaction['tenantcode'];
            $spacecode = $transaction['spacecode'];
            $rent = $transaction['rent'];
            $paidrent = $transaction['paidrent'];
            $paidbal = $transaction['paidbal'];
            $paidelec = $transaction['paidelec'] ?? 0;
            $paidelecarrear = $transaction['paidelecarrear'] ?? 0;
            $paidwater = $transaction['paidwater'] ?? 0;
            $paidwaterarrear = $transaction['paidwaterarrear'] ?? 0;
            $charges = $transaction['charges'] ?? '';
            $collector = $transaction['collector'];
            $tenantname = $transaction['tenantname'];

            if ($charges === null) {
                $charges = '';
            }
            $charges = trim($charges);

            $tenant_table = ($branch === 'sanko') ? 'sanko' : (($branch === 'apm') ? 'apm' : 'nova');
            $stmt = $conn->prepare("UPDATE $tenant_table SET rentbal = ?, runningbal = ?, elecbal = ?, waterbal = ?, elecarrear = ?, waterarrear = ? WHERE spacecode = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param('dddddds', $original_rentbal, $original_runningbal, $original_elecbal, $original_waterbal, $original_elecarrear, $original_waterarrear, $spacecode);
            $stmt->execute();

            $branch_name = ucfirst($branch) . ' Branch';

            $stmt = $conn->prepare("INSERT INTO void (transaction_number, branch, note, rent, rentbal, runningbal, paidrent, paidbal, charges, collector, tenantname, spacecode, void_date, elecbal, paidelec, paidelecarrear, waterbal, paidwater, paidwaterarrear, elecarrear, waterarrear) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param('sssdddddsssssdddddddd', $transaction_number, $branch_name, $note, $rent, $original_rentbal, $original_runningbal, $paidrent, $paidbal, $charges, $collector, $tenantname, $spacecode, $void_date, $original_elecbal, $paidelec, $paidelecarrear, $original_waterbal, $paidwater, $paidwaterarrear, $original_elecarrear, $original_waterarrear);

            if (!$stmt->execute()) {
                throw new Exception("Insert failed: " . $stmt->error);
            }

            $stmt = $conn->prepare("DELETE FROM $branch_table WHERE transaction_number = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param('s', $transaction_number);
            $stmt->execute();

            $conn->commit();
            $_SESSION['void_transaction_message'] = 'Transaction voided successfully.';
            $_SESSION['void_transaction_message_type'] = 'success';
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            $_SESSION['debug_info'] = [
                'transaction_number' => $transaction_number,
                'branch_table' => $branch_table,
                'all_values' => [
                    'rent' => $rent,
                    'rentbal' => $original_rentbal,
                    'runningbal' => $original_runningbal,
                    'paidrent' => $paidrent,
                    'paidbal' => $paidbal,
                    'charges' => $charges,
                    'collector' => $collector,
                    'tenantname' => $tenantname,
                    'spacecode' => $spacecode
                ]
            ];

            header("Location: void_transaction.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['void_transaction_message'] = $e->getMessage();
            $_SESSION['void_transaction_message_type'] = 'error';
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header("Location: void_transaction.php");
            exit();
        }
    }
}

mysqli_close($conn);
ob_end_flush();

$username = $_SESSION["username"] ?? '';
$userBranch = $_SESSION["branch"] ?? '';
$formBranch = ($userBranch === 'Sanko Market') ? 'sanko' : (($userBranch === 'Nova Market') ? 'nova' : (($userBranch === 'APM') ? 'apm' : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Void Transaction — Collection POS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="modern-bottom-nav.css">
    <link rel="stylesheet" href="pos-modern.css">
</head>
<body>
    <?php
    $posTitle = 'Void Transaction';
    $posSubtitle = ($userBranch ?: 'Branch') . ' • Balance Reversal';
    $posIcon = 'fas fa-ban';
    $posSkipBranchHidden = true;
    include 'pos_shell_nav.php';
    include 'modern_bottom_nav.php';
    ?>
    <input type="hidden" id="branch_nav" value="<?php echo htmlspecialchars($userBranch); ?>">

    <div class="pos-page-wrap animate-fadeIn">
        <header class="pos-desktop-header">
            <div class="dh-left">
                <div class="dh-icon dh-icon-danger">
                    <i class="fas fa-ban"></i>
                </div>
                <div>
                    <h1 class="dh-title">Void Transaction</h1>
                    <div class="dh-meta">
                        <span class="dh-chip dh-chip-danger"><i class="fas fa-exclamation-triangle"></i> Reverts Tenant Balances</span>
                        <span class="dh-chip dh-chip-slate"><i class="fas fa-store"></i> <?php echo htmlspecialchars($userBranch); ?></span>
                        <span class="dh-chip dh-chip-slate"><i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?></span>
                    </div>
                </div>
            </div>
            <div class="dh-right">
                <a href="duplicated.php" class="btn-pos-action">
                    <i class="fas fa-exclamation-circle" style="color:#dc2626;"></i> View Duplicates
                </a>
                <a href="transactions.php" class="btn-pos-action">
                    <i class="fas fa-list-ul"></i> Transactions
                </a>
            </div>
        </header>

        <div class="pos-two-col">
            <!-- Left: Void Form -->
            <section class="pos-panel">
                <div class="pos-panel-header">
                    <div class="pos-panel-title">
                        <span class="pos-panel-icon icon-danger"><i class="fas fa-ban"></i></span>
                        <span>Void Receipt &amp; Revert Balances</span>
                    </div>
                    <span class="dh-chip dh-chip-danger"><?php echo htmlspecialchars($userBranch); ?></span>
                </div>

                <?php if (isset($_SESSION['void_transaction_message'])): ?>
                    <div style="margin-bottom:14px;padding:12px 14px;border-radius:12px;font-size:13px;font-weight:700;display:flex;align-items:center;gap:9px;<?php echo $_SESSION['void_transaction_message_type'] === 'success' ? 'background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;' : 'background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;'; ?>">
                        <i class="fas <?php echo $_SESSION['void_transaction_message_type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                        <span><?php echo htmlspecialchars($_SESSION['void_transaction_message']); ?></span>
                    </div>
                    <?php
                    unset($_SESSION['void_transaction_message']);
                    unset($_SESSION['void_transaction_message_type']);
                    ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['debug_info'])): ?>
                    <div style="margin-bottom:14px;padding:12px 14px;border-radius:12px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;font-size:12px;">
                        <div style="font-weight:800;margin-bottom:6px;"><i class="fas fa-undo mr-1"></i> Reverted Receipt #<?php echo htmlspecialchars($_SESSION['debug_info']['transaction_number']); ?></div>
                        <div class="grid grid-cols-2 gap-1.5" style="font-weight:600;">
                            <div><strong>Tenant:</strong> <?php echo htmlspecialchars($_SESSION['debug_info']['all_values']['tenantname']); ?></div>
                            <div><strong>Space:</strong> <?php echo htmlspecialchars($_SESSION['debug_info']['all_values']['spacecode']); ?></div>
                            <div><strong>Paid Rent:</strong> ₱<?php echo htmlspecialchars($_SESSION['debug_info']['all_values']['paidrent']); ?></div>
                            <div><strong>Paid Bal:</strong> ₱<?php echo htmlspecialchars($_SESSION['debug_info']['all_values']['paidbal']); ?></div>
                        </div>
                    </div>
                    <?php unset($_SESSION['debug_info']); ?>
                <?php endif; ?>

                <form action="void_transaction.php" method="post" id="voidForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="branch" value="<?php echo htmlspecialchars($formBranch); ?>">

                    <div class="mb-4">
                        <label class="field-label" for="transaction_number">
                            <i class="fas fa-hashtag" style="color:#dc2626;"></i> Transaction Number *
                        </label>
                        <div class="input-shell">
                            <i class="fas fa-hashtag input-prefix-icon"></i>
                            <input type="text" id="transaction_number" name="transaction_number"
                                   class="pos-input has-icon font-bold"
                                   value="<?php echo isset($_GET['txn']) ? htmlspecialchars($_GET['txn']) : ''; ?>"
                                   placeholder="Enter or pick a transaction #" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="field-label" for="branch_display">
                            <i class="fas fa-building" style="color:#dc2626;"></i> Branch (Locked to Login)
                        </label>
                        <select id="branch_display" class="pos-input" disabled>
                            <option value="sanko" <?php echo ($formBranch === 'sanko') ? 'selected' : ''; ?>>Sanko Branch</option>
                            <option value="nova" <?php echo ($formBranch === 'nova') ? 'selected' : ''; ?>>Nova Branch</option>
                            <option value="apm" <?php echo ($formBranch === 'apm') ? 'selected' : ''; ?>>APM Branch</option>
                        </select>
                    </div>

                    <div class="mb-5">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-1.5">
                            <label class="field-label mb-0" for="note">
                                <i class="fas fa-sticky-note" style="color:#dc2626;"></i> Reason for Voiding *
                            </label>
                        </div>
                        <div class="flex flex-wrap gap-1.5 mb-2">
                            <button type="button" class="quick-chip" onclick="setVoidReason('Duplicate Entry')">Duplicate Entry</button>
                            <button type="button" class="quick-chip" onclick="setVoidReason('Wrong Amount')">Wrong Amount</button>
                            <button type="button" class="quick-chip" onclick="setVoidReason('Wrong Tenant')">Wrong Tenant</button>
                            <button type="button" class="quick-chip" onclick="setVoidReason('Cancelled Payment')">Cancelled Payment</button>
                        </div>
                        <textarea id="note" name="note" rows="3" class="pos-input" placeholder="Describe why this transaction is being voided..." required></textarea>
                    </div>

                    <button type="submit" name="void_submit" id="submitBtn" class="submit-btn submit-btn-danger">
                        <i class="fas fa-ban"></i> Confirm Void Transaction
                    </button>
                </form>
            </section>

            <!-- Right: Today's Transactions Quick-Select -->
            <section class="pos-panel">
                <div class="pos-panel-header">
                    <div class="pos-panel-title">
                        <span class="pos-panel-icon icon-indigo"><i class="fas fa-search"></i></span>
                        <span>Select Transaction to Void</span>
                    </div>
                    <span id="voidPickCount" class="dh-chip dh-chip-slate">Loading...</span>
                </div>

                <div class="input-shell mb-3">
                    <i class="fas fa-search input-prefix-icon"></i>
                    <input type="text" id="voidPickSearch" placeholder="Search today's #, space, or tenant..." class="pos-input has-icon" style="height:40px;font-size:13px;">
                </div>

                <div id="voidPickList" class="quick-pick-list">
                    <div style="text-align:center;padding:24px;color:#94a3b8;font-size:13px;">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Loading recent transactions...
                    </div>
                </div>
            </section>
        </div>
    </div>

    <script src="nav_badge.js"></script>
    <script>
        let voidTxData = [];

        function setVoidReason(reason) {
            const note = document.getElementById('note');
            if (note) {
                note.value = reason;
                note.focus();
            }
        }

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

        function renderVoidPickList(filter) {
            const listEl = document.getElementById('voidPickList');
            const chip = document.getElementById('voidPickCount');
            const q = (filter || '').toLowerCase().trim();
            const filtered = !q ? voidTxData : voidTxData.filter(t =>
                String(t.transaction_number || '').toLowerCase().includes(q) ||
                String(t.spacecode || '').toLowerCase().includes(q) ||
                String(t.tenantname || '').toLowerCase().includes(q)
            );
            if (chip) chip.textContent = `${filtered.length} today`;

            if (!filtered.length) {
                listEl.innerHTML = '<div style="text-align:center;padding:28px 12px;color:#94a3b8;font-size:13px;">No transactions found today.</div>';
                return;
            }

            listEl.innerHTML = filtered.slice(0, 40).map(t => {
                const amt = getTxTotal(t).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                return `
                    <button type="button" class="quick-pick-item" onclick="pickVoidTxn('${t.transaction_number}')">
                        <div style="min-width:0;flex:1;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="font-size:13.5px;font-weight:800;color:#b91c1c;">#${t.transaction_number}</span>
                                <span style="font-size:11px;font-weight:700;padding:2px 7px;border-radius:6px;background:#fee2e2;color:#991b1b;">${t.spacecode || '—'}</span>
                            </div>
                            <div style="font-size:12px;font-weight:600;color:#334155;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${t.tenantname || '—'}</div>
                        </div>
                        <div style="text-align:right;flex-shrink:0;">
                            <div style="font-size:13.5px;font-weight:800;color:#0f172a;">₱${amt}</div>
                            <div style="font-size:10.5px;font-weight:700;color:#dc2626;">Select to Void</div>
                        </div>
                    </button>
                `;
            }).join('');
        }

        function pickVoidTxn(txNum) {
            const input = document.getElementById('transaction_number');
            if (input) {
                input.value = txNum;
                input.focus();
            }
        }

        document.getElementById('voidPickSearch').addEventListener('input', function () {
            renderVoidPickList(this.value);
        });

        document.addEventListener('DOMContentLoaded', function () {
            fetch('fetch_transactions.php')
                .then(r => r.json())
                .then(data => {
                    voidTxData = Array.isArray(data) ? data : [];
                    renderVoidPickList('');
                })
                .catch(() => {
                    document.getElementById('voidPickList').innerHTML =
                        '<div style="text-align:center;padding:20px;color:#dc2626;font-size:13px;">Could not load today\'s transactions.</div>';
                });
        });
    </script>
</body>
</html>
