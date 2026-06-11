<?php
$file = 'admin/admin.php';
$content = file_get_contents($file);

// 1. Sidebar CSS rewrite
$sidebar_css = <<<CSS
        /* Premium Sidebar Overrides */
        #accordionSidebar {
            background: #ffffff !important;
            background-image: none !important;
            border-right: 1px solid #e2e8f0 !important; /* slate-200 */
            width: 260px !important;
            box-shadow: 1px 0 10px rgba(0,0,0,0.02) !important;
        }
        #accordionSidebar .sidebar-brand {
            padding: 1.5rem 1.5rem !important;
            height: auto !important;
            margin-bottom: 1rem !important;
            justify-content: flex-start !important;
        }
        #accordionSidebar .sidebar-brand-text {
            color: #0f172a !important; /* slate-900 */
            font-weight: 800 !important;
            letter-spacing: -0.5px !important;
            font-size: 1.1rem !important;
        }
        .brand-text-sub { 
            color: #6366f1 !important; /* indigo-500 */
            font-weight: 600 !important;
            font-size: 0.8rem !important;
            display: block;
            letter-spacing: 0;
            margin-top: -2px;
        }
        #accordionSidebar .nav-item .nav-link {
            padding: 0.75rem 1.5rem !important;
            margin: 0.25rem 1rem !important;
            border-radius: 0.75rem !important;
            width: auto !important;
            color: #64748b !important; /* slate-500 */
            transition: all 0.2s ease !important;
            font-size: 0.9rem !important;
            font-weight: 600 !important;
            display: flex !important;
            align-items: center !important;
        }
        #accordionSidebar .nav-item .nav-link:hover {
            background: #f8fafc !important; /* slate-50 */
            color: #0f172a !important; /* slate-900 */
        }
        #accordionSidebar .nav-item.active .nav-link {
            background: #eef2ff !important; /* indigo-50 */
            color: #4f46e5 !important; /* indigo-600 */
        }
        #accordionSidebar .nav-item .nav-link i {
            color: #94a3b8 !important; /* slate-400 */
            font-size: 1.1rem !important;
            margin-right: 1rem !important;
            width: 1.5rem !important;
            text-align: center !important;
            transition: all 0.2s ease !important;
        }
        #accordionSidebar .nav-item.active .nav-link i {
            color: #4f46e5 !important; /* indigo-600 */
        }
        #accordionSidebar .nav-item .nav-link:hover i {
            color: #6366f1 !important; /* indigo-500 */
        }
        #accordionSidebar hr.sidebar-divider {
            border-top: 1px solid #f1f5f9 !important;
            margin: 1.5rem 1.5rem !important;
        }
        .sidebar-heading {
            color: #94a3b8 !important; /* slate-400 */
            font-size: 0.7rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            padding: 0 1.5rem !important;
            margin-bottom: 0.75rem !important;
        }
        /* Topbar modernization */
        .topbar {
            height: 4.5rem !important;
            box-shadow: none !important;
            border-bottom: 1px solid #e2e8f0 !important;
            background: #ffffff !important;
        }
        /* Dashboard Background */
        #content-wrapper {
            background-color: #f8fafc !important; /* slate-50 */
        }
CSS;

// Remove old sidebar css if it exists
if (preg_match('/\/\* Modern Sidebar Overrides \*\/.*?\/\* Topbar modernization \*\/.*?\}/s', $content)) {
    $content = preg_replace('/\/\* Modern Sidebar Overrides \*\/.*?\/\* Topbar modernization \*\/.*?\}/s', $sidebar_css, $content);
} else {
    $content = str_replace('</style>', $sidebar_css . "\n    </style>", $content);
}


// 2. Main Layout HTML rewrite
$new_dashboard_html = <<<'HTML'
<div class="container-fluid px-6 py-8 font-sans">
    
    <!-- Alerts -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-6 flex justify-between items-center shadow-sm" id="successAlert">
            <div class="flex items-center gap-2">
                <i class="fas fa-check-circle text-emerald-500"></i>
                <span class="font-medium"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></span>
            </div>
            <button type="button" class="text-emerald-500 hover:text-emerald-700" data-dismiss="alert">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl mb-6 flex justify-between items-center shadow-sm" id="errorAlert">
            <div class="flex items-center gap-2">
                <i class="fas fa-exclamation-circle text-rose-500"></i>
                <span class="font-medium"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></span>
            </div>
            <button type="button" class="text-rose-500 hover:text-rose-700" data-dismiss="alert">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-end justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight mb-1">Dashboard Overview</h1>
            <p class="text-slate-500 text-sm font-medium">Welcome back, <?php echo htmlspecialchars($lname); ?>. Here is your current data.</p>
        </div>
    </div>

    <!-- Filter Form (Slim Pill Style) -->
    <form method="GET" id="dashboardFilterForm" class="mb-8">
        <div class="bg-white rounded-2xl p-2 shadow-sm border border-slate-200 inline-flex flex-col md:flex-row gap-2 w-full md:w-auto items-center">
            
            <div class="flex items-center bg-slate-50 rounded-xl px-3 py-2 border border-slate-100 w-full md:w-auto">
                <i class="fas fa-calendar-alt text-slate-400 mr-2 text-sm"></i>
                <div class="flex flex-col">
                    <span class="text-[9px] font-bold text-slate-500 uppercase tracking-wide">Start Date</span>
                    <input type="date" name="start_date" id="start_date" class="bg-transparent border-0 p-0 text-sm text-slate-800 font-semibold focus:ring-0 outline-none w-32" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
            </div>
            
            <div class="hidden md:block text-slate-300">
                <i class="fas fa-arrow-right text-xs"></i>
            </div>

            <div class="flex items-center bg-slate-50 rounded-xl px-3 py-2 border border-slate-100 w-full md:w-auto">
                <i class="fas fa-calendar-check text-slate-400 mr-2 text-sm"></i>
                <div class="flex flex-col">
                    <span class="text-[9px] font-bold text-slate-500 uppercase tracking-wide">End Date</span>
                    <input type="date" name="end_date" id="end_date" class="bg-transparent border-0 p-0 text-sm text-slate-800 font-semibold focus:ring-0 outline-none w-32" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
            </div>

            <div class="flex items-center bg-slate-50 rounded-xl px-3 py-2 border border-slate-100 w-full md:w-auto md:ml-4">
                <i class="fas fa-building text-slate-400 mr-2 text-sm"></i>
                <div class="flex flex-col">
                    <span class="text-[9px] font-bold text-slate-500 uppercase tracking-wide">Branch Filter</span>
                    <select name="branch" id="branch" class="bg-transparent border-0 p-0 text-sm text-slate-800 font-semibold focus:ring-0 outline-none w-40 cursor-pointer appearance-none">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $branch_option): ?>
                            <option value="<?php echo htmlspecialchars($branch_option); ?>" <?php echo (isset($_GET['branch']) && $_GET['branch'] === $branch_option) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($branch_option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl px-6 py-3 text-sm font-semibold shadow-sm transition-colors flex items-center justify-center gap-2 w-full md:w-auto h-full">
                <i class="fas fa-search text-xs"></i> Apply
            </button>
            
        </div>
    </form>

    <!-- Analytics Cards (Modern Minimalist) -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">
        
        <!-- Total Collection Card -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 flex items-center gap-4 hover:border-indigo-300 transition-colors cursor-default">
            <div class="w-12 h-12 rounded-full bg-indigo-50 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-wallet text-indigo-600 text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-500 mb-0.5">Total Collection</p>
                <h3 class="text-2xl font-bold text-slate-900 tracking-tight">
                    <?php
                        $totalCollection = $totalPaidRent + $totalPaidBal + $totalCharges;
                        echo '₱' . number_format($totalCollection, 2);
                    ?>
                </h3>
            </div>
        </div>

        <!-- Transactions Card -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 flex items-center gap-4 hover:border-purple-300 transition-colors cursor-default">
            <div class="w-12 h-12 rounded-full bg-purple-50 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-receipt text-purple-600 text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-500 mb-0.5">Transactions</p>
                <h3 class="text-2xl font-bold text-slate-900 tracking-tight"><?php echo count($transactions); ?></h3>
            </div>
        </div>

        <!-- Total Users Card -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 flex items-center gap-4 hover:border-emerald-300 transition-colors cursor-default">
            <div class="w-12 h-12 rounded-full bg-emerald-50 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-users text-emerald-600 text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-500 mb-0.5">Total Users</p>
                <h3 class="text-2xl font-bold text-slate-900 tracking-tight"><?php echo $totalUsers; ?></h3>
            </div>
        </div>

        <!-- Missing Transactions Card -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 flex items-center gap-4 hover:border-rose-300 transition-colors cursor-default relative overflow-hidden group">
            <div class="w-12 h-12 rounded-full bg-rose-50 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-user-times text-rose-600 text-xl"></i>
            </div>
            <div class="flex-grow">
                <p class="text-sm font-semibold text-slate-500 mb-0.5">No Transactions</p>
                <h3 class="text-2xl font-bold text-rose-600 tracking-tight"><?php echo $tenants_no_transactions; ?></h3>
            </div>
            <button id="viewMissingTransactionsBtn" class="absolute right-4 opacity-0 group-hover:opacity-100 transition-opacity bg-rose-100 text-rose-600 w-8 h-8 rounded-full flex items-center justify-center hover:bg-rose-200 focus:outline-none">
                <i class="fas fa-arrow-right text-xs"></i>
            </button>
        </div>

    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-8">
        
        <!-- Monthly Chart -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Monthly Collection</h2>
                    <p class="text-xs text-slate-500 font-medium">Trailing 12 months</p>
                </div>
                <button class="text-slate-400 hover:text-slate-600"><i class="fas fa-ellipsis-h"></i></button>
            </div>
            <div class="chart-area h-64 relative w-full">
                <canvas id="monthlyBarChart"></canvas>
            </div>
        </div>

        <!-- Yearly Chart -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Yearly Collection</h2>
                    <p class="text-xs text-slate-500 font-medium">Last 5 years overview</p>
                </div>
                <button class="text-slate-400 hover:text-slate-600"><i class="fas fa-ellipsis-h"></i></button>
            </div>
            <div class="chart-area h-64 relative w-full">
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
echo "Successfully updated admin UI to Vercel/Stripe style premium design.\n";
?>
