<?php
$file = 'admin/admin.php';
$content = file_get_contents($file);

// 1. Inject Tailwind CDN before </head> if not exists
if (strpos($content, 'tailwindcss.com') === false) {
    $tailwind_script = <<<HTML
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
HTML;
    $content = str_replace('</head>', $tailwind_script, $content);
}

// 2. Prepare the completely modernized Tailwind HTML body
$new_dashboard_html = <<<'HTML'
<div class="container-fluid px-4 md:px-8 py-6 bg-slate-50 min-h-screen font-sans">
    
    <!-- Alerts -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-xl shadow-sm mb-6 flex justify-between items-center animate__animated animate__fadeIn" id="successAlert">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-emerald-500 text-xl mr-3"></i>
                <p class="text-emerald-800 font-medium m-0"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></p>
            </div>
            <button type="button" class="text-emerald-600 hover:text-emerald-800 focus:outline-none" data-dismiss="alert" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-r-xl shadow-sm mb-6 flex justify-between items-center animate__animated animate__fadeIn" id="errorAlert">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-rose-500 text-xl mr-3"></i>
                <p class="text-rose-800 font-medium m-0"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></p>
            </div>
            <button type="button" class="text-rose-600 hover:text-rose-800 focus:outline-none" data-dismiss="alert" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight flex items-center gap-3 m-0">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/30">
                    <i class="fas fa-chart-pie text-white text-lg"></i>
                </div>
                Overview
            </h1>
            <p class="text-slate-500 mt-2 font-medium">Welcome back, <?php echo htmlspecialchars($lname); ?>. Here's what's happening today.</p>
        </div>
    </div>

    <!-- Filter Form -->
    <form method="GET" id="dashboardFilterForm" class="mb-8">
        <div class="bg-white rounded-3xl p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2"><i class="fas fa-calendar-alt mr-2 text-indigo-400"></i>Start Date</label>
                    <input type="date" name="start_date" id="start_date" class="w-full bg-slate-50 border-0 rounded-2xl px-4 py-3.5 text-slate-700 font-medium focus:ring-2 focus:ring-indigo-500/50 transition-all outline-none" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2"><i class="fas fa-calendar-check mr-2 text-indigo-400"></i>End Date</label>
                    <input type="date" name="end_date" id="end_date" class="w-full bg-slate-50 border-0 rounded-2xl px-4 py-3.5 text-slate-700 font-medium focus:ring-2 focus:ring-indigo-500/50 transition-all outline-none" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2"><i class="fas fa-building mr-2 text-indigo-400"></i>Branch</label>
                    <select name="branch" id="branch" class="w-full bg-slate-50 border-0 rounded-2xl px-4 py-3.5 text-slate-700 font-medium focus:ring-2 focus:ring-indigo-500/50 transition-all outline-none appearance-none">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $branch_option): ?>
                            <option value="<?php echo htmlspecialchars($branch_option); ?>" <?php echo (isset($_GET['branch']) && $_GET['branch'] === $branch_option) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($branch_option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="w-full bg-slate-800 hover:bg-slate-900 text-white rounded-2xl px-6 py-3.5 font-bold shadow-lg shadow-slate-800/20 transition-all hover:-translate-y-0.5 flex items-center justify-center gap-2">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- Analytics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
        
        <!-- Total Collection Card -->
        <div class="bg-white rounded-3xl p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 relative overflow-hidden group hover:-translate-y-1 transition-all duration-300">
            <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-indigo-50 opacity-50 group-hover:scale-150 transition-transform duration-500"></div>
            <div class="flex items-start justify-between relative z-10">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Total Collection</p>
                    <h3 class="text-3xl font-black text-slate-800 tracking-tight">
                        <?php
                            $totalCollection = $totalPaidRent + $totalPaidBal + $totalCharges;
                            echo '₱' . number_format($totalCollection, 2);
                        ?>
                    </h3>
                    <p class="text-xs text-slate-400 mt-2 font-medium bg-slate-50 inline-block px-2 py-1 rounded-md">Selected Period</p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-blue-500 flex items-center justify-center shadow-lg shadow-indigo-500/30">
                    <i class="fas fa-wallet text-white text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Transactions Card -->
        <div class="bg-white rounded-3xl p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 relative overflow-hidden group hover:-translate-y-1 transition-all duration-300">
            <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-purple-50 opacity-50 group-hover:scale-150 transition-transform duration-500"></div>
            <div class="flex items-start justify-between relative z-10">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Transactions</p>
                    <h3 class="text-3xl font-black text-slate-800 tracking-tight"><?php echo count($transactions); ?></h3>
                    <p class="text-xs text-slate-400 mt-2 font-medium bg-slate-50 inline-block px-2 py-1 rounded-md">Completed</p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center shadow-lg shadow-purple-500/30">
                    <i class="fas fa-receipt text-white text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Total Users Card -->
        <div class="bg-white rounded-3xl p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 relative overflow-hidden group hover:-translate-y-1 transition-all duration-300">
            <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-emerald-50 opacity-50 group-hover:scale-150 transition-transform duration-500"></div>
            <div class="flex items-start justify-between relative z-10">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Total Users</p>
                    <h3 class="text-3xl font-black text-slate-800 tracking-tight"><?php echo $totalUsers; ?></h3>
                    <p class="text-xs text-slate-400 mt-2 font-medium bg-slate-50 inline-block px-2 py-1 rounded-md">System wide</p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center shadow-lg shadow-emerald-500/30">
                    <i class="fas fa-users text-white text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Missing Transactions Card -->
        <div class="bg-white rounded-3xl p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 relative overflow-hidden group hover:-translate-y-1 transition-all duration-300">
            <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-rose-50 opacity-50 group-hover:scale-150 transition-transform duration-500"></div>
            <div class="flex items-start justify-between relative z-10">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Missing Transactions</p>
                    <div class="flex items-end gap-3">
                        <h3 class="text-3xl font-black text-rose-500 tracking-tight"><?php echo $tenants_no_transactions; ?></h3>
                    </div>
                    <button class="mt-3 text-xs font-bold text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1" id="viewMissingTransactionsBtn">
                        View Details <i class="fas fa-arrow-right text-[10px]"></i>
                    </button>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-rose-400 to-red-500 flex items-center justify-center shadow-lg shadow-rose-500/30">
                    <i class="fas fa-user-times text-white text-xl"></i>
                </div>
            </div>
        </div>

    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        
        <!-- Monthly Chart -->
        <div class="bg-white rounded-3xl p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-lg font-bold text-slate-800">Monthly Collection</h2>
                    <p class="text-sm font-medium text-slate-400">Trailing 12 months</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center text-slate-400">
                    <i class="fas fa-chart-bar"></i>
                </div>
            </div>
            <div class="chart-area h-72">
                <canvas id="monthlyBarChart"></canvas>
            </div>
        </div>

        <!-- Yearly Chart -->
        <div class="bg-white rounded-3xl p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-lg font-bold text-slate-800">Yearly Collection</h2>
                    <p class="text-sm font-medium text-slate-400">Last 5 years</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center text-slate-400">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
            <div class="chart-area h-72">
                <canvas id="yearlyLineChart"></canvas>
            </div>
        </div>

    </div>

</div>
HTML;

// 3. Replace the content between `<div class="container-fluid">` and `<footer class="sticky-footer bg-white">`
// To be safe, we use a regex that matches from <div class="container-fluid"> to just before <footer class="sticky-footer bg-white">

$pattern = '/<div class="container-fluid">.*?<footer class="sticky-footer bg-white">/s';
$replacement = $new_dashboard_html . "\n" . '            <footer class="sticky-footer bg-white">';

$content = preg_replace($pattern, $replacement, $content);

file_put_contents($file, $content);
echo "Successfully updated admin UI to full Tailwind.\n";
?>
