<?php
$__notifScript = strtolower(basename($_SERVER['PHP_SELF'] ?? ''));
$__collectionTarget = 'collection.php';
if (strpos($__notifScript, 'apm.php') !== false) {
    $__collectionTarget = 'collectionapm.php';
} elseif (strpos($__notifScript, 'acc.php') !== false) {
    $__collectionTarget = 'collectionacc.php';
}
?>
<li class="nav-item dropdown no-arrow mx-1 notif-nav-item">
    <a class="nav-link dropdown-toggle notif-bell-btn" href="#" id="alertsDropdown" role="button"
        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Activity &amp; Alerts Center">
        <i class="fas fa-bell"></i>
        <span class="notif-badge d-none" id="unreadCounter">0</span>
    </a>

    <div class="dropdown-menu dropdown-menu-right shadow notif-dropdown" aria-labelledby="alertsDropdown" id="alertsCenterMenu">
        <!-- Modern Header -->
        <div class="notif-header">
            <div class="notif-header-top">
                <div class="d-flex align-items-center" style="gap: 8px;">
                    <span class="notif-header-icon"><i class="fas fa-bell"></i></span>
                    <div>
                        <div class="notif-header-title">
                            Alerts Center
                            <span class="notif-live-pill" id="notifLivePill"><span class="dot"></span>Live</span>
                        </div>
                        <div class="notif-header-sub" id="notifTodaySummary">Loading today's activity...</div>
                    </div>
                </div>
                <div class="notif-header-actions">
                    <button type="button" class="notif-icon-btn" id="notifSoundToggle" title="Toggle alert sound">
                        <i class="fas fa-volume-up"></i>
                    </button>
                    <button type="button" class="notif-icon-btn" id="notifMarkAllRead" title="Mark all as read">
                        <i class="fas fa-check-double"></i>
                    </button>
                    <button type="button" class="notif-icon-btn" id="notifRefreshBtn" title="Refresh alerts">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>

            <!-- Filter Tabs & Inline Search -->
            <div class="notif-controls">
                <div class="notif-tabs" role="tablist">
                    <button type="button" class="notif-tab active" data-filter="all">All <span class="notif-tab-count" id="notifTabAllCount">0</span></button>
                    <button type="button" class="notif-tab" data-filter="unread">Unread <span class="notif-tab-count" id="notifTabUnreadCount">0</span></button>
                    <button type="button" class="notif-tab" data-filter="payment">Paid</button>
                    <button type="button" class="notif-tab" data-filter="void">Void <span class="notif-tab-count notif-tab-rose d-none" id="notifTabVoidCount">0</span></button>
                </div>
                <div class="notif-search-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" id="notifQuickSearch" class="notif-search-input" placeholder="Search tenant, space, trans #..." autocomplete="off">
                </div>
            </div>
        </div>

        <!-- Notification Feed -->
        <div id="alertMessages" class="notif-feed">
            <div class="notif-empty">
                <i class="fas fa-circle-notch fa-spin text-primary mb-2"></i>
                <div>Loading latest alerts...</div>
            </div>
        </div>

        <!-- Clean Utility Footer -->
        <div class="notif-footer">
            <button type="button" class="btn btn-compact btn-soft-indigo notif-footer-btn" id="viewAllTransactionsBtn">
                <i class="fas fa-layer-group"></i> All Activity Today
            </button>
            <a href="<?php echo htmlspecialchars($__collectionTarget); ?>" class="btn btn-compact btn-light text-secondary notif-footer-link">
                Ledger <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
    </div>
</li>
