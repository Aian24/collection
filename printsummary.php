<?php
ob_start();
session_start();

if (!isset($_SESSION["username"])) {
    header("Location: index.php");
    exit();
}

include 'config.php';

$username = $_SESSION["username"];
$branch = $_SESSION["branch"] ?? '';
date_default_timezone_set('Asia/Manila');
$current_date_time = date('Y-m-d g:i A');
$total_rent = $total_balance = $total_charges = $total = 0;
$total_elec = 0;
$total_water = 0;
$db_grand_total = 0;

// Get the selected date or default to today
$selected_date = isset($_POST['selected_date']) && !empty($_POST['selected_date']) ? $_POST['selected_date'] : date('Y-m-d');
$startOfDay = $selected_date . ' 00:00:00';
$endOfDay = $selected_date . ' 23:59:59';

// Fetch collector's last name and branch first
$lname = '';
$collector_stmt = $conn->prepare("SELECT lname, branch FROM users WHERE username = ?");
if ($collector_stmt) {
    $collector_stmt->bind_param("s", $username);
    $collector_stmt->execute();
    $collector_result = $collector_stmt->get_result();
    if ($collector_result && $collector_result->num_rows > 0) {
        $collector_row = $collector_result->fetch_assoc();
        $lname = $collector_row['lname'] ?? '';
        if (!empty($collector_row['branch'])) {
            $branch = $collector_row['branch'];
        }
    }
    $collector_stmt->close();
}

// Determine the correct table based on the user's branch
$table = ($branch === 'Sanko Market') ? 'collected' :
         (($branch === 'Nova Market') ? 'collectednova' :
         (($branch === 'ACC' || $branch === 'Ambulant') ? 'collectedacc' : 'collectedapm'));

// Fetch totals for the selected date and collector
$query = "SELECT SUM(paidrent) AS total_rent, SUM(paidbal) AS total_balance, (SUM(IFNULL(paidelec,0)) + SUM(IFNULL(paidelecarrear,0))) AS total_elec, (SUM(IFNULL(paidwater,0)) + SUM(IFNULL(paidwaterarrear,0))) AS total_water, SUM(total) AS db_grand_total
          FROM `$table`
          WHERE collected_date BETWEEN ? AND ? AND (collector = ? OR username = ?)";
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("ssss", $startOfDay, $endOfDay, $lname, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $total_rent = (float)($row['total_rent'] ?? 0);
        $total_balance = (float)($row['total_balance'] ?? 0);
        $total_elec = (float)($row['total_elec'] ?? 0);
        $total_water = (float)($row['total_water'] ?? 0);
        $db_grand_total = (float)($row['db_grand_total'] ?? 0);
    }
    $stmt->close();
}

// Fetch charges and group by type
$charges_stmt = $conn->prepare("SELECT charges FROM `$table` WHERE collected_date BETWEEN ? AND ? AND (collector = ? OR username = ?)");
$charge_totals = array();
$total_charges = 0;

if ($charges_stmt) {
    $charges_stmt->bind_param("ssss", $startOfDay, $endOfDay, $lname, $username);
    $charges_stmt->execute();
    $charges_result = $charges_stmt->get_result();
    while ($charge_row = $charges_result->fetch_assoc()) {
        preg_match_all('/([^:,]+):\s*([\d,]+(\.\d{1,2})?)/', $charge_row['charges'], $matches);

        if (count($matches[0]) > 0) {
            foreach ($matches[1] as $index => $charge_type) {
                $charge_value = (float) str_replace(',', '', $matches[2][$index]);
                $charge_type = trim($charge_type);
                $lower_type = strtolower($charge_type);

                if (in_array($lower_type, ['electricity', 'elec', 'electric', 'paidelec', 'electricity bal', 'electricity arrear'])) {
                    $total_elec += $charge_value;
                } elseif (in_array($lower_type, ['water', 'paidwater', 'water bal', 'water arrear']) && strpos($lower_type, 'ice') === false) {
                    $total_water += $charge_value;
                } else {
                    $total_charges += $charge_value;
                    if (!isset($charge_totals[$charge_type])) {
                        $charge_totals[$charge_type] = 0;
                    }
                    $charge_totals[$charge_type] += $charge_value;
                }
            }
        }
    }
    $charges_stmt->close();
}

// Round all totals
$total_rent = round($total_rent, 2);
$total_balance = round($total_balance, 2);
$total_elec = round($total_elec, 2);
$total_water = round($total_water, 2);
$total_charges = round($total_charges, 2);
$total = round($db_grand_total, 2);

if (isset($conn) && $conn) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="icon" type="image/png" href="images/lc.png">
    <title>Summary Report — Collection POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="modern-bottom-nav.css">
    <link rel="stylesheet" href="pos-modern.css">
    <style>
        /* Print styles - exact match to original Sunmi V2s 58mm thermal layout */
        @media print {
            body {
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
                background-image: none !important;
            }
            body, p, h1, div, span {
                color: black !important;
                background: white !important;
                font-family: sans-serif;
                font-size: 12px;
                line-height: 1.2;
                margin: 0;
                padding: 0;
                overflow: visible !important;
            }
            .print-logo {
                width: 100%;
                height: auto;
                margin-bottom: 4%;
            }
            .modern-app, .modern-bottom-nav, .desktop-sidebar, .pos-mobile-shell, .pos-page-wrap {
                display: none !important;
            }
            .print-only {
                display: block !important;
                width: 58mm; /* Standard Thermal Size */
                margin: 0 auto;
                visibility: visible !important;
                opacity: 1 !important;
            }
            .print-only * {
                visibility: visible !important;
            }
            .print-container {
                text-align: center;
                padding: 0;
            }
            .receipt-row {
                display: flex;
                justify-content: space-between;
                margin: 2px 0;
            }
            .receipt-label {
                font-weight: 600; /* font-semibold */
                text-transform: uppercase;
            }
            .receipt-value {
                font-weight: 900; /* font-black */
                text-align: right;
            }
            @page {
                margin: 0;
                size: 58mm auto;
            }
        }
        .print-only { display: none; }

        .summary-hero-card {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 55%, #4f46e5 100%);
            border-radius: 20px;
            padding: 22px;
            color: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 12px 28px rgba(37, 99, 235, 0.25);
        }
        .summary-hero-amt {
            font-size: 36px;
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.1;
            margin: 6px 0 14px;
            font-variant-numeric: tabular-nums;
        }
        .flatpickr-calendar {
            font-family: 'Inter', sans-serif !important;
            border-radius: 14px !important;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.14) !important;
            border: 1px solid #e2e8f0 !important;
        }
        .flatpickr-day.selected {
            background: #2563eb !important;
            border-color: #2563eb !important;
        }
    </style>
</head>
<body>
    <!-- Print-only content (Matched print.php Style) -->
    <div class="print-only">
        <div class="print-container">
            <div class="mb-4">
                <img src="images/lc.png" alt="Logo" class="print-logo">
                <p class="mb-2 font-bold italic">Summary Report</p>
            </div>
            
            <div class="space-y-1 text-xs border-b border-black pb-2 mb-2">
                <div class="receipt-row">
                    <span class="receipt-label">Date:</span>
                    <span class="receipt-value"><?php echo $selected_date; ?></span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Branch:</span>
                    <span class="receipt-value"><?php echo isset($branch) ? $branch : ''; ?></span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Collector:</span>
                    <span class="receipt-value"><?php echo isset($lname) ? $lname : ''; ?></span>
                </div>
            </div>

            <div class="space-y-1 my-2">
                <?php if ($total_rent > 0): ?>
                    <div class="receipt-row">
                        <span class="receipt-label">Total Rent:</span>
                        <span class="receipt-value"><?php echo number_format($total_rent, 2); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($total_balance > 0): ?>
                    <div class="receipt-row">
                        <span class="receipt-label">Total Arrear:</span>
                        <span class="receipt-value"><?php echo number_format($total_balance, 2); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($total_elec > 0): ?>
                    <div class="receipt-row">
                        <span class="receipt-label">Total Elec:</span>
                        <span class="receipt-value"><?php echo number_format($total_elec, 2); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($total_water > 0): ?>
                    <div class="receipt-row">
                        <span class="receipt-label">Total Water:</span>
                        <span class="receipt-value"><?php echo number_format($total_water, 2); ?></span>
                    </div>
                <?php endif; ?>
                <!-- Subtotal row like print.php -->
                <div class="receipt-row" style="border-top: 1px dotted black; margin-top: 4px; padding-top: 4px;">
                    <span class="receipt-label">Payments Subtotal:</span>
                    <span class="receipt-value">(<?php echo number_format((float)$total_rent + (float)$total_balance + (float)$total_elec + (float)$total_water, 2); ?>)</span>
                </div>
            </div>
            
            <?php if (!empty($charge_totals)): ?>
                <div class="space-y-1 my-2 pt-1 border-t-2 border-black">
                    <?php foreach ($charge_totals as $charge_type => $amount): ?>
                        <div class="receipt-row">
                            <span class="receipt-label"><?php echo htmlspecialchars($charge_type); ?>:</span>
                            <span class="receipt-value"><?php echo number_format($amount, 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <!-- Total Charges row like print.php -->
                    <div class="receipt-row" style="border-top: 1px dotted black; margin-top: 4px; padding-top: 4px;">
                        <span class="receipt-label">Total Charges:</span>
                        <span class="receipt-value">(<?php echo number_format($total_charges, 2); ?>)</span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="border-t-2 border-black pt-2 mt-2">
                <div class="receipt-row" style="font-size: 16px;">
                    <span style="font-weight: 900;">Grand Total:</span>
                    <span style="font-weight: 900;">₱<?php echo number_format($total, 2); ?></span>
                </div>
            </div>

            <div style="margin-top: 15px; text-align: center; border-top: 1px dotted black; pt-2">
                <div class="mt-4 space-y-1">
                    <p style="font-size: 9px; font-weight: 600; font-style: italic; color: black; margin-top: 8px;">"Ensuring accurate collections for every transaction."</p>
                    <p style="font-size: 8px; font-weight: 500; margin-top: 4px;">Thank you for your hard work!</p>
                </div>

                <div class="mt-6 border-t border-dotted border-gray-400 pt-2">
                    <div class="text-[8px] uppercase tracking-[0.4em] font-black opacity-80">Official Summary Report</div>
                </div>
            </div>
        </div>
    </div>

    <?php
    $posTitle = 'Summary Report';
    $posSubtitle = ($branch ?: 'Branch') . ' • ' . $selected_date;
    $posIcon = 'fas fa-file-invoice-dollar';
    include 'pos_shell_nav.php';
    include 'modern_bottom_nav.php';
    ?>

    <div class="modern-app pos-page-wrap animate-fadeIn">
        <header class="pos-desktop-header">
            <div class="dh-left">
                <div class="dh-icon">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div>
                    <h1 class="dh-title">Daily Summary Report</h1>
                    <div class="dh-meta">
                        <span class="dh-chip"><i class="fas fa-store"></i> <?php echo htmlspecialchars($branch); ?></span>
                        <span class="dh-chip dh-chip-slate"><i class="fas fa-user"></i> <?php echo htmlspecialchars($lname ?: $username); ?></span>
                        <span class="dh-chip dh-chip-slate"><i class="far fa-calendar"></i> <?php echo htmlspecialchars($selected_date); ?></span>
                    </div>
                </div>
            </div>
            <div class="dh-right">
                <button type="button" onclick="window.print()" class="btn-pos-action" style="background:var(--gradient);color:#fff;border:none;">
                    <i class="fas fa-print"></i> Print Summary
                </button>
            </div>
        </header>

        <div class="pos-two-col">
            <!-- Left Column: Date Picker + Grand Total + Category Breakdown -->
            <div class="flex flex-col gap-3">
                <form id="dateForm" method="post" class="pos-panel" style="padding:14px 16px;">
                    <label for="selected_date" class="field-label">
                        <i class="fas fa-calendar-alt" style="color:var(--primary-blue);"></i> Report Collection Date
                    </label>
                    <div class="input-shell">
                        <i class="far fa-calendar input-prefix-icon" style="color:var(--primary-blue);"></i>
                        <input type="text" name="selected_date" id="selected_date" value="<?php echo htmlspecialchars($selected_date); ?>"
                               class="pos-input has-icon cursor-pointer" readonly>
                        <i class="fas fa-chevron-down" style="position:absolute;right:13px;color:#94a3b8;font-size:12px;pointer-events:none;"></i>
                    </div>
                </form>

                <div class="summary-hero-card">
                    <div style="font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;opacity:0.85;">
                        Grand Total Collected
                    </div>
                    <div class="summary-hero-amt">₱<?php echo number_format($total, 2); ?></div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span style="display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,0.16);border:1px solid rgba(255,255,255,0.22);border-radius:999px;padding:5px 12px;font-size:12px;font-weight:700;">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($lname ?: $username); ?>
                        </span>
                        <span style="display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,0.14);border:1px solid rgba(255,255,255,0.2);border-radius:999px;padding:5px 12px;font-size:12px;font-weight:700;">
                            <i class="far fa-calendar"></i> <?php echo htmlspecialchars($selected_date); ?>
                        </span>
                    </div>
                </div>

                <!-- Category Cards -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="pos-kpi-card">
                        <div class="pos-kpi-icon icon-emerald"><i class="fas fa-home"></i></div>
                        <div>
                            <div class="pos-kpi-label">Total Rent</div>
                            <div class="pos-kpi-val" style="color:#047857;">₱<?php echo number_format($total_rent, 2); ?></div>
                        </div>
                    </div>
                    <div class="pos-kpi-card">
                        <div class="pos-kpi-icon icon-purple"><i class="fas fa-history"></i></div>
                        <div>
                            <div class="pos-kpi-label">Total Arrear</div>
                            <div class="pos-kpi-val" style="color:#6d28d9;">₱<?php echo number_format($total_balance, 2); ?></div>
                        </div>
                    </div>
                    <div class="pos-kpi-card" style="background:#fffbeb;border-color:#fde68a;">
                        <div class="pos-kpi-icon icon-amber"><i class="fas fa-bolt"></i></div>
                        <div>
                            <div class="pos-kpi-label" style="color:#92400e;">Total Elec</div>
                            <div class="pos-kpi-val" style="color:#78350f;">₱<?php echo number_format($total_elec, 2); ?></div>
                        </div>
                    </div>
                    <div class="pos-kpi-card" style="background:#f0f9ff;border-color:#bae6fd;">
                        <div class="pos-kpi-icon icon-sky"><i class="fas fa-tint"></i></div>
                        <div>
                            <div class="pos-kpi-label" style="color:#075985;">Total Water</div>
                            <div class="pos-kpi-val" style="color:#0c4a6e;">₱<?php echo number_format($total_water, 2); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Itemized Charges + Print Action -->
            <div class="flex flex-col gap-3">
                <section class="pos-panel">
                    <div class="pos-panel-header">
                        <div class="pos-panel-title">
                            <span class="pos-panel-icon icon-amber"><i class="fas fa-receipt"></i></span>
                            <div>
                                <div>Itemized Charges</div>
                                <div style="font-size:11px;font-weight:600;color:var(--text-muted);">Non-rent additional charges</div>
                            </div>
                        </div>
                        <span style="font-size:17px;font-weight:800;color:var(--text);font-variant-numeric:tabular-nums;">
                            ₱<?php echo number_format($total_charges, 2); ?>
                        </span>
                    </div>

                    <?php if (!empty($charge_totals)): ?>
                        <div class="flex flex-col gap-2">
                            <?php foreach ($charge_totals as $charge_type => $amount): ?>
                                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 12px;border-radius:12px;background:#f8fafc;border:1px solid #f1f5f9;">
                                    <div class="flex items-center gap-2.5">
                                        <span style="width:8px;height:8px;border-radius:999px;background:#f59e0b;"></span>
                                        <span style="font-size:13.5px;font-weight:700;color:#334155;"><?php echo htmlspecialchars($charge_type); ?></span>
                                    </div>
                                    <span style="font-size:14px;font-weight:800;color:#0f172a;font-variant-numeric:tabular-nums;">₱<?php echo number_format($amount, 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="padding:28px 16px;text-align:center;color:#94a3b8;">
                            <i class="fas fa-file-invoice text-2xl mb-2 block" style="opacity:0.45;"></i>
                            <div style="font-size:13px;font-weight:600;">No extra charges recorded on <?php echo htmlspecialchars($selected_date); ?></div>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top:16px;padding-top:14px;border-top:1px dashed #e2e8f0;">
                        <button type="button" onclick="window.print()" class="submit-btn">
                            <i class="fas fa-print"></i> Print 58mm Summary Receipt
                        </button>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <script src="nav_badge.js"></script>
    <script src="app_print_integration.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr("#selected_date", {
                dateFormat: "Y-m-d",
                disableMobile: true,
                onChange: function(selectedDates, dateStr) {
                    if (dateStr) {
                        document.getElementById("dateForm").submit();
                    }
                }
            });
        });
    </script>
</body>
</html>
