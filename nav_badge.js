/**
 * Navigation Badge Updater
 * Polls for monitoring stats and duplicate counts to update nav badges.
 *
 * OPTIMIZED: Reduced polling frequency from 30s to 60s and added
 * visibility-based pausing to prevent background process buildup on shared hosting.
 */
(function() {
    'use strict';

    let badgeInterval = null;
    const POLL_INTERVAL = 60000; // Poll every 60 seconds (was 30s)

    function updateNavBadge() {
        // Don't poll if the page is not visible
        if (document.hidden) return;

        const badge = document.getElementById('notificationBadge');
        if (!badge) return;

        // We need branch and date. If not available, we can't update.
        const branchEl = document.getElementById('branch') || document.getElementById('branch_nav') || document.getElementById('branch_hidden') || document.querySelector('.brand-name');
        if (!branchEl) return;

        let branch = branchEl.value || branchEl.textContent;
        branch = (branch || "").trim();
        if (!branch) return;

        // Get local date YYYY-MM-DD
        const now = new Date();
        const today = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');

        // Fetch Monitoring Stats
        fetch(`fetch_monitoring_stats.php?branch=${encodeURIComponent(branch)}&date=${encodeURIComponent(today)}`)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.pending > 0) {
                    badge.textContent = data.pending;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }
            }).catch(() => { });

        // Fetch Duplicated Count
        const dupBadge = document.getElementById('notificationCountBadge');
        if (dupBadge) {
            fetch('fetch_duplicated_transactions.php')
                .then(r => r.json())
                .then(data => {
                    if (data.length > 0) {
                        dupBadge.textContent = data.length;
                        dupBadge.style.display = 'flex';
                        const parentBtn = document.getElementById('notificationButton');
                        if (parentBtn) parentBtn.classList.add('animate-pulse');
                    } else {
                        dupBadge.style.display = 'none';
                        const parentBtn = document.getElementById('notificationButton');
                        if (parentBtn) parentBtn.classList.remove('animate-pulse');
                    }
                }).catch(() => { });
        }
    }

    function startPolling() {
        if (badgeInterval) clearInterval(badgeInterval);
        updateNavBadge(); // Immediate first check
        badgeInterval = setInterval(updateNavBadge, POLL_INTERVAL);
    }

    function stopPolling() {
        if (badgeInterval) {
            clearInterval(badgeInterval);
            badgeInterval = null;
        }
    }

    // Pause/resume polling based on page visibility
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopPolling();
        } else {
            startPolling();
        }
    });

    // Start on DOM ready
    document.addEventListener('DOMContentLoaded', startPolling);
})();
