<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$posUsername = $_SESSION['username'] ?? '';
$posBranch = $_SESSION['branch'] ?? '';
$posPageTitle = $posTitle ?? ($posBranch === 'Sanko Market' ? 'Sanko Market' : $posBranch);
$posPageSubtitle = $posSubtitle ?? 'Collection POS';
$posPageIcon = $posIcon ?? 'fas fa-cash-register';
$posIsDanger = !empty($posDanger);
?>
<nav class="top-bar pos-mobile-shell <?php echo $posIsDanger ? 'top-bar-danger' : ''; ?>">
    <div class="brand">
        <div class="brand-icon">
            <i class="<?php echo htmlspecialchars($posPageIcon); ?>"></i>
        </div>
        <div style="min-width:0;">
            <div class="brand-name"><?php echo htmlspecialchars($posPageTitle); ?></div>
            <div class="brand-sub"><?php echo htmlspecialchars($posPageSubtitle); ?></div>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <?php if (!empty($posTopRightHtml)) echo $posTopRightHtml; ?>
        <button class="user-pill" id="burger-menu-btn" type="button">
            <i class="fas fa-user-circle"></i>
            <span><?php echo htmlspecialchars($posUsername); ?></span>
            <i class="fas fa-chevron-down" style="font-size:9px;opacity:.75;"></i>
        </button>
    </div>
</nav>

<?php if (empty($posSkipBranchHidden)): ?>
<input type="hidden" id="branch" value="<?php echo htmlspecialchars($posBranch); ?>">
<?php endif; ?>

<div id="side-nav" class="hidden fixed inset-0 z-50 pos-mobile-shell">
    <div class="flex h-full">
        <div id="side-nav-backdrop" class="bg-black opacity-50 flex-1 h-full"></div>
        <div class="side-menu <?php echo $posIsDanger ? 'side-menu-danger' : ''; ?> w-72 h-full transform transition-transform duration-300 ease-in-out translate-x-full ml-auto">
            <div class="flex justify-between items-center p-4" style="border-bottom: 1px solid rgba(255,255,255,0.08);">
                <div class="flex items-center gap-3">
                    <div style="width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,0.14);display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <div class="text-white font-bold text-sm"><?php echo htmlspecialchars($posUsername); ?></div>
                        <div class="text-blue-200 text-xs" style="opacity:0.8;"><?php echo htmlspecialchars($posBranch); ?></div>
                    </div>
                </div>
                <button id="close-btn" class="text-white p-2 rounded-full" type="button" style="background:rgba(255,255,255,0.1);">
                    <i class="fas fa-times text-base"></i>
                </button>
            </div>
            <div class="flex flex-col mt-3 px-3 gap-1">
                <a href="user.php" class="text-gray-100 text-sm font-semibold py-2.5 px-3.5 flex items-center gap-3 rounded-xl hover:bg-white hover:bg-opacity-10">
                    <i class="fas fa-plus-circle w-5 text-center text-blue-300"></i> New Collection
                </a>
                <a href="printsummary.php" class="text-gray-100 text-sm font-semibold py-2.5 px-3.5 flex items-center gap-3 rounded-xl hover:bg-white hover:bg-opacity-10">
                    <i class="fas fa-file-invoice-dollar w-5 text-center text-purple-300"></i> Summary Report
                </a>
                <a href="transactions.php" class="text-gray-100 text-sm font-semibold py-2.5 px-3.5 flex items-center gap-3 rounded-xl hover:bg-white hover:bg-opacity-10">
                    <i class="fas fa-list-ul w-5 text-center text-blue-300"></i> Transactions
                </a>
                <a href="reprint.php" class="text-gray-100 text-sm font-semibold py-2.5 px-3.5 flex items-center gap-3 rounded-xl hover:bg-white hover:bg-opacity-10">
                    <i class="fas fa-print w-5 text-center text-sky-300"></i> Reprint Receipt
                </a>
                <a href="void_transaction.php" class="text-gray-100 text-sm font-semibold py-2.5 px-3.5 flex items-center gap-3 rounded-xl hover:bg-white hover:bg-opacity-10">
                    <i class="fas fa-ban w-5 text-center text-yellow-300"></i> Void Transaction
                </a>
                <a href="monitoring.php" class="text-gray-100 text-sm font-semibold py-2.5 px-3.5 flex items-center gap-3 rounded-xl hover:bg-white hover:bg-opacity-10">
                    <i class="fas fa-chart-pie w-5 text-center text-amber-300"></i> Monitoring
                </a>
                <a href="duplicated.php" class="text-gray-100 text-sm font-semibold py-2.5 px-3.5 flex items-center gap-3 rounded-xl hover:bg-white hover:bg-opacity-10">
                    <i class="fas fa-exclamation-triangle w-5 text-center text-red-300"></i> Duplicated
                </a>
                <a href="downloads/lclopez-collection.apk" download class="text-gray-100 text-sm font-semibold py-2.5 px-3.5 flex items-center gap-3 rounded-xl hover:bg-white hover:bg-opacity-10">
                    <i class="fas fa-download w-5 text-center text-green-300"></i> Download App
                </a>
            </div>
            <div class="absolute bottom-0 left-0 right-0 p-4" style="border-top: 1px solid rgba(255,255,255,0.08);">
                <a href="logout.php" class="text-sm font-bold py-3 px-4 flex items-center gap-3 rounded-xl" style="background:rgba(239,68,68,0.18);color:#fca5a5;">
                    <i class="fas fa-sign-out-alt w-5 text-center"></i> Logout
                </a>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var btn = document.getElementById('burger-menu-btn');
    var nav = document.getElementById('side-nav');
    var backdrop = document.getElementById('side-nav-backdrop');
    var closeBtn = document.getElementById('close-btn');
    var menu = nav ? nav.querySelector('.side-menu') : null;
    function openDrawer() {
        if (!nav || !menu) return;
        nav.classList.remove('hidden');
        setTimeout(function() { menu.classList.remove('translate-x-full'); menu.classList.add('translate-x-0'); }, 20);
    }
    function closeDrawer() {
        if (!nav || !menu) return;
        menu.classList.add('translate-x-full'); menu.classList.remove('translate-x-0');
        setTimeout(function() { nav.classList.add('hidden'); }, 300);
    }
    if (btn) btn.addEventListener('click', openDrawer);
    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
    if (backdrop) backdrop.addEventListener('click', closeDrawer);
})();
</script>

