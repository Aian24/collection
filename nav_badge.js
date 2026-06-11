function updateNavBadge() {
    const badge = document.getElementById('notificationBadge');
    if (!badge) return;

    // We need branch and date. If not available, we can't update.
    // Usually branch is in session or a hidden input.
    // Check various common IDs used across different pages
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

// Update every 30 seconds
setInterval(updateNavBadge, 30000);
document.addEventListener('DOMContentLoaded', updateNavBadge);
