<?php
$file = 'admin/admin.php';
$content = file_get_contents($file);

// 1. Add modern CSS overrides for the sidebar and topbar
$sidebar_css = <<<CSS
        /* Modern Sidebar Overrides */
        #accordionSidebar {
            background: #0f172a !important; /* Tailwind slate-900 */
            background-image: none !important;
            border-right: 1px solid #1e293b !important; /* slate-800 */
        }
        #accordionSidebar .sidebar-brand {
            padding: 1.5rem 1rem !important;
            height: auto !important;
            margin-bottom: 0.5rem !important;
        }
        #accordionSidebar .sidebar-brand-text {
            color: #f8fafc !important;
            font-weight: 700 !important;
            letter-spacing: 0.5px !important;
        }
        .brand-text-sub { color: #3b82f6 !important; }
        #accordionSidebar .nav-item .nav-link {
            padding: 0.6rem 1rem !important;
            margin: 0.2rem 1rem !important;
            border-radius: 0.5rem !important;
            width: auto !important;
            color: #cbd5e1 !important; /* slate-300 */
            transition: all 0.2s !important;
            font-size: 0.85rem !important;
            font-weight: 500 !important;
        }
        #accordionSidebar .nav-item .nav-link:hover,
        #accordionSidebar .nav-item.active .nav-link {
            background: #1e293b !important; /* slate-800 */
            color: #ffffff !important;
        }
        #accordionSidebar .nav-item .nav-link i {
            color: #64748b !important; /* slate-500 */
            font-size: 1rem !important;
            margin-right: 0.75rem !important;
            width: 1.25rem !important;
            text-align: center !important;
            transition: all 0.2s !important;
        }
        #accordionSidebar .nav-item.active .nav-link i,
        #accordionSidebar .nav-item .nav-link:hover i {
            color: #3b82f6 !important; /* blue-500 */
        }
        #accordionSidebar hr.sidebar-divider {
            border-top: 1px solid #1e293b !important;
            margin: 1rem 0 !important;
        }
        .sidebar-heading {
            color: #475569 !important;
            font-size: 0.65rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            padding: 0 1.5rem !important;
            margin-bottom: 0.5rem !important;
        }
        /* Topbar modernization */
        .topbar {
            height: 4rem !important;
            box-shadow: 0 1px 2px 0 rgba(0,0,0,0.03) !important;
            border-bottom: 1px solid #f1f5f9 !important;
            background: #ffffff !important;
        }
CSS;

if (strpos($content, '/* Modern Sidebar Overrides */') === false) {
    $content = str_replace('</style>', $sidebar_css . "\n    </style>", $content);
}

// 2. Prepare the compact modernized Tailwind HTML body
$new_dashboard_html = <<<'HTML'
<div class="container-fluid px-4 py-4 bg-slate-50 min-h-screen font-sans text-sm">
    
    <!-- Alerts -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-emerald-50 border-l-4 border-emerald-500 p-3 rounded-r-lg shadow-sm mb-4 flex justify-between items-center animate__animated animate__fadeIn" id="successAlert">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-emerald-500 text-lg mr-2"></i>
                <p class="text-emerald-800 font-medium m-0 text-sm"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></p>
            </div>
            <button type="button" class="text-emerald-600 hover:text-emerald-800 focus:outline-none" data-dismiss="alert" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-rose-50 border-l-4 border-rose-500 p-3 rounded-r-lg shadow-sm mb-4 flex justify-between items-center animate__animated animate__fadeIn" id="errorAlert">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-rose-500 text-lg mr-2"></i>
                <p class="text-rose-800 font-medium m-0 text-sm"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></p>
            </div>
            <button type="button" class="text-rose-600 hover:text-rose-800 focus:outline-none" data-dismiss="alert" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-end justify-between mb-5 gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-800 tracking-tight flex items-center gap-2 m-0">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-md shadow-indigo-500/20">
                    <i class="fas fa-chart-pie text-white text-sm"></i>
                </div>
                Overview
            </h1>
            <p class="text-slate-500 mt-1 text-sm font-medium">Welcome back, <?php echo htmlspecialchars($lname); ?>. Here's your summary.</p>
        </div>
    </div>

    <!-- Filter Form -->
    <form method="GET" id="dashboardFilterForm" class="mb-6">
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5"><i class="fas fa-calendar-alt mr-1.5 text-indigo-400"></i>Start Date</label>
                    <input type="date" name="start_date" id="start_date" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-700 font-medium focus:ring-2 focus:ring-indigo-500/50 transition-all outline-none h-10" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5"><i class="fas fa-calendar-check mr-1.5 text-indigo-400"></i>End Date</label>
                    <input type="date" name="end_date" id="end_date" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-700 font-medium focus:ring-2 focus:ring-indigo-500/50 transition-all outline-none h-10" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5"><i class="fas fa-building mr-1.5 text-indigo-400"></i>Branch</label>
                    <select name="branch" id="branch" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-700 font-medium focus:ring-2 focus:ring-indigo-500/50 transition-all outline-none appearance-none h-10">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $branch_option): ?>
                            <option value="<?php echo htmlspecialchars($branch_option); ?>" <?php echo (isset($_GET['branch']) && $_GET['branch'] === $branch_option) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($branch_option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="w-full bg-slate-800 hover:bg-slate-900 text-white rounded-xl px-4 py-2 text-sm font-bold shadow-md shadow-slate-800/10 transition-all hover:-translate-y-0.5 flex items-center justify-center gap-2 h-10">
                        <i class="fas fa-filter text-xs"></i> Apply Filters
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- Analytics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        
        <!-- Total Collection Card -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100 relative overflow-hidden group hover:-translate-y-0.5 transition-all duration-300">
            <div class="absolute -right-4 -top-4 w-16 h-16 rounded-full bg-indigo-50 opacity-50 group-hover:scale-150 transition-transform duration-500"></div>
            <div class="flex items-start justify-between relative z-10">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Total Collection</p>
                    <h3 class="text-xl font-black text-slate-800 tracking-tight">
                        <?php
                            $totalCollection = $totalPaidRent + $totalPaidBal + $totalCharges;
                            echo '₱' . number_format($totalCollection, 2);
                        ?>
                    </h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-blue-500 flex items-center justify-center shadow-md shadow-indigo-500/20">
                    <i class="fas fa-wallet text-white text-sm"></i>
                </div>
            </div>
        </div>

        <!-- Transactions Card -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100 relative overflow-hidden group hover:-translate-y-0.5 transition-all duration-300">
            <div class="absolute -right-4 -top-4 w-16 h-16 rounded-full bg-purple-50 opacity-50 group-hover:scale-150 transition-transform duration-500"></div>
            <div class="flex items-start justify-between relative z-10">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Transactions</p>
                    <h3 class="text-xl font-black text-slate-800 tracking-tight"><?php echo count($transactions); ?></h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center shadow-md shadow-purple-500/20">
                    <i class="fas fa-receipt text-white text-sm"></i>
                </div>
            </div>
        </div>

        <!-- Total Users Card -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100 relative overflow-hidden group hover:-translate-y-0.5 transition-all duration-300">
            <div class="absolute -right-4 -top-4 w-16 h-16 rounded-full bg-emerald-50 opacity-50 group-hover:scale-150 transition-transform duration-500"></div>
            <div class="flex items-start justify-between relative z-10">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Total Users</p>
                    <h3 class="text-xl font-black text-slate-800 tracking-tight"><?php echo $totalUsers; ?></h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center shadow-md shadow-emerald-500/20">
                    <i class="fas fa-users text-white text-sm"></i>
                </div>
            </div>
        </div>

        <!-- Missing Transactions Card -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100 relative overflow-hidden group hover:-translate-y-0.5 transition-all duration-300">
            <div class="absolute -right-4 -top-4 w-16 h-16 rounded-full bg-rose-50 opacity-50 group-hover:scale-150 transition-transform duration-500"></div>
            <div class="flex items-start justify-between relative z-10">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">No Transactions</p>
                    <div class="flex items-center gap-2">
                        <h3 class="text-xl font-black text-rose-500 tracking-tight m-0"><?php echo $tenants_no_transactions; ?></h3>
                        <button class="text-[10px] font-bold text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 px-2 py-1 rounded transition-colors flex items-center gap-1" id="viewMissingTransactionsBtn">
                            View <i class="fas fa-arrow-right text-[8px]"></i>
                        </button>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-rose-400 to-red-500 flex items-center justify-center shadow-md shadow-rose-500/20">
                    <i class="fas fa-user-times text-white text-sm"></i>
                </div>
            </div>
        </div>

    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        
        <!-- Monthly Chart -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h2 class="text-base font-bold text-slate-800 m-0">Monthly Collection</h2>
                </div>
                <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-400">
                    <i class="fas fa-chart-bar text-xs"></i>
                </div>
            </div>
            <div class="chart-area h-56">
                <canvas id="monthlyBarChart"></canvas>
            </div>
        </div>

        <!-- Yearly Chart -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h2 class="text-base font-bold text-slate-800 m-0">Yearly Collection</h2>
                </div>
                <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-400">
                    <i class="fas fa-chart-line text-xs"></i>
                </div>
            </div>
            <div class="chart-area h-56">
                <canvas id="yearlyLineChart"></canvas>
            </div>
        </div>

    </div>

</div>
HTML;

// 3. Replace the content between `<div class="container-fluid">` and `<footer class="sticky-footer bg-white">`
$pattern = '/<div class="container-fluid.*?>.*?<footer class="sticky-footer bg-white">/s';
$replacement = $new_dashboard_html . "\n" . '            <footer class="sticky-footer bg-white">';

$content = preg_replace($pattern, $replacement, $content);

file_put_contents($file, $content);
echo "Successfully updated admin UI to compact Tailwind + Modern Sidebar.\n";
?>
