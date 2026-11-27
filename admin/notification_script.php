<!-- Full Activity Log Modal (Mounted Outside Topbar for Clean Stacking) -->
<div class="modal fade" id="allTransactionsModal" tabindex="-1" role="dialog" aria-labelledby="allTransactionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="allTransactionsModalLabel">
                    <span class="kpi-icon-wrap kpi-icon-indigo" style="width:32px;height:32px;font-size:0.85rem;"><i class="fas fa-bell"></i></span>
                    <span>Today's Activity &amp; Alerts Log</span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Today's Summary Pills -->
                <div class="notif-modal-summary">
                    <div class="notif-stat-pill">
                        <span class="label">Collected Today</span>
                        <span class="val text-success" id="notifModalTotalAmt">₱0.00</span>
                    </div>
                    <div class="notif-stat-pill">
                        <span class="label">Payments</span>
                        <span class="val text-primary" id="notifModalPayCount">0</span>
                    </div>
                    <div class="notif-stat-pill">
                        <span class="label">Voided Alerts</span>
                        <span class="val text-danger" id="notifModalVoidCount">0</span>
                    </div>
                    <div class="ml-auto d-flex align-items-center" style="gap: 8px;">
                        <button type="button" class="btn btn-compact btn-soft-indigo" id="notifModalMarkAllRead">
                            <i class="fas fa-check-double"></i> Mark All Read
                        </button>
                        <button type="button" class="btn btn-compact btn-light text-secondary" id="notifModalRefresh" style="border: 1px solid var(--border-color);">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>

                <!-- Modal Search & Filter Controls -->
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-3" style="gap: 8px;">
                    <div class="notif-tabs" id="notifModalTabs">
                        <button type="button" class="notif-tab active" data-modal-filter="all">All</button>
                        <button type="button" class="notif-tab" data-modal-filter="payment">Payments</button>
                        <button type="button" class="notif-tab" data-modal-filter="void">Voided</button>
                        <button type="button" class="notif-tab d-none" data-modal-filter="APM" data-branch-tab="APM">APM</button>
                        <button type="button" class="notif-tab d-none" data-modal-filter="Sanko Market" data-branch-tab="Sanko Market">Sanko</button>
                        <button type="button" class="notif-tab d-none" data-modal-filter="Nova Market" data-branch-tab="Nova Market">Nova</button>
                    </div>
                    <div class="notif-search-wrap" style="min-width: 240px;">
                        <i class="fas fa-search"></i>
                        <input type="text" id="notifModalSearch" class="notif-search-input" placeholder="Search tenant, space, trans #, collector..." autocomplete="off">
                    </div>
                </div>

                <div id="allTransactionsContent" class="notif-modal-list">
                    <div class="notif-empty py-4">
                        <i class="fas fa-circle-notch fa-spin text-primary mb-2"></i>
                        <div>Loading today's activity...</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-compact btn-light text-secondary" data-dismiss="modal" style="border: 1px solid var(--border-color);">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const todayKey = new Date().toISOString().slice(0, 10);
    const READ_IDS_KEY = 'notifReadIds_' + todayKey;
    const READ_BEFORE_KEY = 'notifReadBeforeTs_' + todayKey;
    const isApmPage = window.location.pathname.toLowerCase().includes('apm.php');

    let latestItems = [];
    let latestMeta = { total: 0, void_count: 0, total_collected: 0, branch_counts: {} };
    let modalItems = [];
    let activeDropdownFilter = 'all';
    let activeModalFilter = 'all';
    let seenIds = new Set();
    let initialLoadDone = false;
    let selectedFocusId = null;

    function esc(str) {
        return $('<div>').text(str == null ? '' : String(str)).html();
    }

    function formatCurrency(amount) {
        const num = parseFloat(amount) || 0;
        return '₱' + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function getReadIds() {
        try {
            const raw = localStorage.getItem(READ_IDS_KEY);
            return new Set(raw ? JSON.parse(raw) : []);
        } catch (e) {
            return new Set();
        }
    }

    function saveReadIds(setObj) {
        try {
            const arr = Array.from(setObj).slice(-400);
            localStorage.setItem(READ_IDS_KEY, JSON.stringify(arr));
        } catch (e) {}
    }

    function getReadBeforeTs() {
        return parseInt(localStorage.getItem(READ_BEFORE_KEY) || '0', 10) || 0;
    }

    function isUnread(item) {
        if (!item || !item.id) return false;
        const readBefore = getReadBeforeTs();
        if (readBefore > 0 && (item.timestamp || 0) <= readBefore) return false;
        return !getReadIds().has(item.id);
    }

    function markItemRead(id) {
        if (!id) return;
        const set = getReadIds();
        set.add(id);
        saveReadIds(set);
    }

    function markAllRead() {
        const maxTs = latestItems.reduce((acc, it) => Math.max(acc, it.timestamp || 0), Math.floor(Date.now() / 1000));
        localStorage.setItem(READ_BEFORE_KEY, String(maxTs));
        const set = getReadIds();
        latestItems.forEach((it) => { if (it.id) set.add(it.id); });
        modalItems.forEach((it) => { if (it.id) set.add(it.id); });
        saveReadIds(set);
        renderDropdown();
        renderModalList();
        if (typeof ModernUI !== 'undefined') {
            ModernUI.toast('success', 'All Caught Up', 'Marked all today\'s alerts as read.');
        }
    }

    function isSoundEnabled() {
        return localStorage.getItem('notificationSound') !== 'false';
    }

    function isNotificationsEnabled() {
        return localStorage.getItem('enableNotifications') !== 'false';
    }

    function syncSoundIcon() {
        const enabled = isSoundEnabled();
        const $btn = $('#notifSoundToggle');
        $btn.toggleClass('muted', !enabled);
        $btn.find('i').attr('class', enabled ? 'fas fa-volume-up' : 'fas fa-volume-mute');
        $btn.attr('title', enabled ? 'Sound alerts on (click to mute)' : 'Sound alerts muted (click to unmute)');
    }

    function playChime() {
        if (!isSoundEnabled()) return;
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            const now = ctx.currentTime;
            [659.25, 880].forEach((freq, i) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(freq, now + i * 0.08);
                gain.gain.setValueAtTime(0.001, now + i * 0.08);
                gain.gain.exponentialRampToValueAtTime(0.06, now + i * 0.08 + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + i * 0.08 + 0.22);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now + i * 0.08);
                osc.stop(now + i * 0.08 + 0.24);
            });
        } catch (e) {}
    }

    function updateCounters() {
        const unreadCount = latestItems.filter(isUnread).length;
        const $badge = $('#unreadCounter');
        const $bell = $('#alertsDropdown');

        if (unreadCount > 0) {
            $badge.text(unreadCount > 99 ? '99+' : unreadCount).removeClass('d-none');
            $bell.addClass('has-unread');
        } else {
            $badge.text('0').addClass('d-none');
            $bell.removeClass('has-unread');
        }

        const totalAll = (latestMeta.total || 0) + (latestMeta.void_count || 0);
        $('#notifTabAllCount').text(totalAll);
        $('#notifTabUnreadCount').text(unreadCount);
        if ((latestMeta.void_count || 0) > 0) {
            $('#notifTabVoidCount').text(latestMeta.void_count).removeClass('d-none');
        } else {
            $('#notifTabVoidCount').addClass('d-none');
        }

        $('#notifTodaySummary').text(
            formatCurrency(latestMeta.total_collected || 0) + ' collected • ' + (latestMeta.total || 0) + ' paid' +
            ((latestMeta.void_count || 0) > 0 ? (' • ' + latestMeta.void_count + ' void') : '')
        );
    }

    function matchesSearch(item, q) {
        if (!q) return true;
        const hay = [
            item.tenantname,
            item.spacecode,
            item.branch,
            item.collector,
            item.transaction_number,
            item.message,
            item.note
        ].join(' ').toLowerCase();
        return hay.indexOf(q) !== -1;
    }

    function renderDropdown() {
        updateCounters();
        const q = ($('#notifQuickSearch').val() || '').trim().toLowerCase();

        const filtered = latestItems.filter((item) => {
            if (activeDropdownFilter === 'unread' && !isUnread(item)) return false;
            if (activeDropdownFilter === 'payment' && item.type !== 'payment') return false;
            if (activeDropdownFilter === 'void' && item.type !== 'void') return false;
            return matchesSearch(item, q);
        });

        const $feed = $('#alertMessages');
        if (filtered.length === 0) {
            const msg = q
                ? 'No alerts match "' + esc(q) + '"'
                : (activeDropdownFilter === 'unread' ? 'All caught up! No unread alerts.' : 'No alerts recorded today.');
            $feed.html(`
                <div class="notif-empty">
                    <i class="fas fa-check-circle text-success mb-2" style="font-size:1.35rem;opacity:0.75;"></i>
                    <div>${msg}</div>
                </div>
            `);
            return;
        }

        const html = filtered.slice(0, 25).map((item) => {
            const unread = isUnread(item);
            const isVoid = item.type === 'void';
            const isLarge = !isVoid && (item.amount || 0) >= 5000;
            const iconWrapClass = isVoid ? 'notif-icon-rose' : (isLarge ? 'notif-icon-indigo' : 'notif-icon-emerald');
            const iconClass = isVoid ? 'fa-ban' : (isLarge ? 'fa-bolt' : 'fa-coins');
            const amtClass = isVoid ? 'text-danger' : 'text-success';
            const amtPrefix = isVoid ? '-' : '+';

            return `
                <a href="#" class="notif-row ${unread ? 'is-unread' : ''} ${isVoid ? 'is-void' : ''}" data-notif-id="${esc(item.id)}">
                    <div class="notif-row-icon ${iconWrapClass}">
                        <i class="fas ${iconClass}"></i>
                    </div>
                    <div class="notif-row-body">
                        <div class="notif-row-top">
                            <span class="notif-tenant">${esc(item.tenantname || item.message)}</span>
                            ${unread ? '<span class="notif-unread-dot" title="Unread"></span>' : ''}
                        </div>
                        <div class="notif-row-meta">
                            ${item.spacecode ? '<span class="notif-chip">' + esc(item.spacecode) + '</span>' : ''}
                            <span>${esc(item.branch || 'Branch')}</span>
                            <span>•</span>
                            <span>${esc(item.time)}</span>
                            ${isVoid && item.note ? '<span class="text-danger font-weight-600">• ' + esc(item.note) + '</span>' : ''}
                        </div>
                    </div>
                    <div class="notif-row-right">
                        <div class="notif-amount ${amtClass}">${amtPrefix}${formatCurrency(item.amount)}</div>
                        ${item.transaction_number ? '<div class="notif-tx">#' + esc(item.transaction_number) + '</div>' : ''}
                    </div>
                </a>
            `;
        }).join('');

        $feed.html(html);
    }

    function renderModalList() {
        const source = modalItems.length > 0 ? modalItems : latestItems;
        const q = ($('#notifModalSearch').val() || '').trim().toLowerCase();

        // Update modal summary stats
        $('#notifModalTotalAmt').text(formatCurrency(latestMeta.total_collected || 0));
        $('#notifModalPayCount').text(latestMeta.total || 0);
        $('#notifModalVoidCount').text(latestMeta.void_count || 0);

        // Show branch tabs if multi-branch context
        if (!isApmPage) {
            ['APM', 'Sanko Market', 'Nova Market'].forEach((b) => {
                const count = (latestMeta.branch_counts && latestMeta.branch_counts[b]) || 0;
                const $t = $('#notifModalTabs [data-branch-tab="' + b + '"]');
                $t.toggleClass('d-none', count === 0);
            });
        }

        const filtered = source.filter((item) => {
            if (activeModalFilter === 'payment' && item.type !== 'payment') return false;
            if (activeModalFilter === 'void' && item.type !== 'void') return false;
            if (['APM', 'Sanko Market', 'Nova Market'].includes(activeModalFilter) && item.branch !== activeModalFilter) return false;
            return matchesSearch(item, q);
        });

        const $container = $('#allTransactionsContent');
        if (filtered.length === 0) {
            $container.html('<div class="notif-empty py-5"><i class="fas fa-inbox text-muted mb-2" style="font-size:1.5rem;"></i><div>No matching alerts for today.</div></div>');
            return;
        }

        const html = filtered.map((item) => {
            const isVoid = item.type === 'void';
            const unread = isUnread(item);
            const focused = selectedFocusId && selectedFocusId === item.id;
            const breakdownParts = [];
            if ((item.paidrent || 0) > 0) breakdownParts.push('Rent ' + formatCurrency(item.paidrent));
            if ((item.paidbal || 0) > 0) breakdownParts.push('Bal ' + formatCurrency(item.paidbal));
            if ((item.charges_total || 0) > 0) breakdownParts.push('Charges ' + formatCurrency(item.charges_total));

            return `
                <div class="notif-modal-card ${isVoid ? 'is-void' : ''} ${unread ? 'is-unread' : ''} ${focused ? 'is-focused' : ''}" data-notif-id="${esc(item.id)}">
                    <div class="d-flex align-items-center" style="gap: 12px; min-width: 0; flex: 1;">
                        <div class="notif-row-icon ${isVoid ? 'notif-icon-rose' : 'notif-icon-emerald'}">
                            <i class="fas ${isVoid ? 'fa-ban' : 'fa-receipt'}"></i>
                        </div>
                        <div style="min-width: 0; flex: 1;">
                            <div class="d-flex flex-wrap align-items-center" style="gap: 6px;">
                                <span class="font-weight-bold text-dark">${esc(item.tenantname || item.message)}</span>
                                ${item.spacecode ? '<span class="badge-soft-cyan">' + esc(item.spacecode) + '</span>' : ''}
                                ${item.branch ? '<span class="badge-soft-indigo">' + esc(item.branch) + '</span>' : ''}
                                ${isVoid ? '<span class="badge-soft-rose">VOIDED</span>' : ''}
                            </div>
                            <div class="small text-muted mt-1">
                                ${item.transaction_number ? 'Trans #' + esc(item.transaction_number) + ' • ' : ''}
                                ${item.collector ? esc(item.collector) + ' • ' : ''}
                                ${esc(item.time_exact || item.time)}
                                ${isVoid && item.note ? ' • <span class="text-danger">' + esc(item.note) + '</span>' : ''}
                                ${!isVoid && breakdownParts.length ? ' • ' + esc(breakdownParts.join(' · ')) : ''}
                            </div>
                        </div>
                    </div>
                    <div class="text-right ml-3">
                        <div class="font-weight-bold ${isVoid ? 'text-danger' : 'text-success'}" style="font-size: 0.96rem;">
                            ${isVoid ? '-' : '+'}${formatCurrency(item.amount)}
                        </div>
                        ${item.payment_method ? '<small class="text-muted">' + esc(item.payment_method) + '</small>' : ''}
                    </div>
                </div>
            `;
        }).join('');

        $container.html(html);
    }

    function fetchNotifications(limitMode) {
        if (document.hidden && limitMode !== 'all') return;

        const ajaxData = {
            _: Date.now(),
            limit: limitMode || '25'
        };
        if (isApmPage) {
            ajaxData.branch = 'APM';
        }

        $.ajax({
            url: 'get_notifications.php',
            type: 'GET',
            dataType: 'json',
            cache: false,
            data: ajaxData,
            success: function(response) {
                if (!response || !Array.isArray(response.notifications)) return;
                const items = response.notifications;
                latestMeta = {
                    total: response.total || 0,
                    void_count: response.void_count || 0,
                    total_collected: response.total_collected || 0,
                    branch_counts: response.branch_counts || {}
                };

                if (limitMode === 'all') {
                    modalItems = items;
                    renderModalList();
                    return;
                }

                // Detect brand new items arriving after initial load
                const newArrivals = [];
                items.forEach((it) => {
                    if (it.id && !seenIds.has(it.id)) {
                        if (initialLoadDone && isUnread(it)) {
                            newArrivals.push(it);
                        }
                        seenIds.add(it.id);
                    }
                });

                latestItems = items;
                initialLoadDone = true;
                renderDropdown();

                if (newArrivals.length > 0 && isNotificationsEnabled()) {
                    playChime();
                    if (typeof ModernUI !== 'undefined') {
                        const first = newArrivals[0];
                        const extra = newArrivals.length > 1 ? ' (+' + (newArrivals.length - 1) + ' more)' : '';
                        const type = first.type === 'void' ? 'warning' : 'success';
                        const title = first.type === 'void' ? 'Voided Transaction' : 'New Collection';
                        ModernUI.toast(
                            type,
                            title + extra,
                            (first.tenantname || 'Tenant') + ' • ' + formatCurrency(first.amount) + (first.branch ? ' (' + first.branch + ')' : '')
                        );
                    }
                }
            },
            error: function() {
                $('#notifLivePill').removeClass('online').html('<span class="dot bg-warning"></span>Retry');
            }
        });
    }

    // Event bindings (keep dropdown open while interacting with filters/search/actions)
    $(document).on('click', '#alertsCenterMenu', function(e) {
        e.stopPropagation();
    });

    $(document).on('click', '#alertsCenterMenu [data-filter]', function(e) {
        e.preventDefault();
        e.stopPropagation();
        activeDropdownFilter = $(this).data('filter');
        $('#alertsCenterMenu [data-filter]').removeClass('active');
        $(this).addClass('active');
        renderDropdown();
    });

    $(document).on('input', '#notifQuickSearch', function() {
        renderDropdown();
    });

    $(document).on('click', '#notifMarkAllRead, #notifModalMarkAllRead', function(e) {
        e.preventDefault();
        e.stopPropagation();
        markAllRead();
    });

    $(document).on('click', '#notifSoundToggle', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const next = !isSoundEnabled();
        localStorage.setItem('notificationSound', next ? 'true' : 'false');
        $('#notificationSound').prop('checked', next);
        syncSoundIcon();
        if (next) playChime();
    });

    $(document).on('click', '#notifRefreshBtn, #notifModalRefresh', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const $icon = $(this).find('i');
        $icon.addClass('fa-spin');
        fetchNotifications('25');
        if ($('#allTransactionsModal').hasClass('show')) {
            fetchNotifications('all');
        }
        setTimeout(() => $icon.removeClass('fa-spin'), 700);
    });

    $(document).on('click', '#alertMessages .notif-row', function(e) {
        e.preventDefault();
        const id = $(this).data('notif-id');
        markItemRead(id);
        selectedFocusId = id;
        renderDropdown();
        $('#alertsDropdown').dropdown('hide');
        $('#allTransactionsModal').modal('show');
    });

    $(document).on('click', '#allTransactionsContent .notif-modal-card', function() {
        const id = $(this).data('notif-id');
        markItemRead(id);
        $(this).removeClass('is-unread');
        renderDropdown();
    });

    $(document).on('click', '#viewAllTransactionsBtn', function(e) {
        e.preventDefault();
        selectedFocusId = null;
        $('#alertsDropdown').dropdown('hide');
        $('#allTransactionsModal').modal('show');
    });

    $(document).on('click', '#notifModalTabs [data-modal-filter]', function() {
        activeModalFilter = $(this).data('modal-filter');
        $('#notifModalTabs [data-modal-filter]').removeClass('active');
        $(this).addClass('active');
        renderModalList();
    });

    $(document).on('input', '#notifModalSearch', function() {
        renderModalList();
    });

    $('#allTransactionsModal').on('show.bs.modal', function() {
        fetchNotifications('all');
    });

    let notificationInterval = null;
    function startNotificationPolling() {
        stopNotificationPolling();
        if (!isNotificationsEnabled()) {
            $('#notifLivePill').html('<span class="dot bg-secondary"></span>Paused');
            return;
        }
        $('#notifLivePill').html('<span class="dot"></span>Live');
        fetchNotifications('25');
        notificationInterval = setInterval(function() {
            if (!document.hidden && !$('#allTransactionsModal').hasClass('show')) {
                fetchNotifications('25');
            }
        }, 30000);
    }

    function stopNotificationPolling() {
        if (notificationInterval) {
            clearInterval(notificationInterval);
            notificationInterval = null;
        }
    }

    // Sync Settings Modal toggles cleanly
    $(document).on('change', '#enableNotifications', function() {
        localStorage.setItem('enableNotifications', $(this).is(':checked') ? 'true' : 'false');
        startNotificationPolling();
    });
    $(document).on('change', '#notificationSound', function() {
        localStorage.setItem('notificationSound', $(this).is(':checked') ? 'true' : 'false');
        syncSoundIcon();
    });

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopNotificationPolling();
        } else {
            startNotificationPolling();
        }
    });

    syncSoundIcon();
    startNotificationPolling();
});
</script>
