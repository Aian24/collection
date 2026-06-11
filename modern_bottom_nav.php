<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<div class="modern-bottom-nav">
    <!-- Left side -->
    <a href="printsummary.php" class="nav-item <?= $currentPage == 'printsummary.php' ? 'active' : '' ?>">
        <i class="fas fa-file-invoice-dollar"></i>
        <span>Summary</span>
    </a>
    
    <a href="reprint.php" class="nav-item <?= $currentPage == 'reprint.php' ? 'active' : '' ?>">
        <i class="fas fa-print"></i>
        <span>Reprint</span>
    </a>

    <!-- Center Floating Action Button (Create) -->
    <div class="nav-item-center">
        <a href="user.php" class="fab-button <?= $currentPage == 'user.php' ? 'active' : '' ?>">
            <i class="fas fa-plus"></i>
        </a>
    </div>

    <!-- Right side -->
    <a href="void_transaction.php" class="nav-item <?= $currentPage == 'void_transaction.php' ? 'active' : '' ?>">
        <i class="fas fa-ban"></i>
        <span>Void</span>
    </a>

    <!-- More Button (Far Right) -->
    <div class="nav-item-more-container">
        <!-- Floating Menu Bubbles above More -->
        <div class="more-popout-menu" id="morePopoutMenu">
            <a href="transactions.php" class="more-pop-item" style="--delay: 0.15s">
                <span class="pop-label">Transactions</span>
                <i class="fas fa-list-ul"></i>
            </a>
            <a href="monitoring.php" class="more-pop-item" style="--delay: 0.1s">
                <span class="pop-label">Monitoring</span>
                <i class="fas fa-chart-pie"></i>
            </a>
            <a href="#" onclick="showModernLogout(); return false;" class="more-pop-item" style="--delay: 0.05s">
                <span class="pop-label">Logout</span>
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
        
        <button type="button" onclick="toggleMoreMenu()" id="moreBtnToggle" class="nav-item">
            <i class="fas fa-ellipsis-h" id="moreIcon"></i>
            <span>More</span>
        </button>
    </div>
    <!-- Modal overlay for the popout (Moved inside to allow z-index layering) -->
    <div class="more-menu-overlay" id="moreMenuOverlay" onclick="toggleMoreMenu()"></div>
</div>

<!-- Logout Confirmation Modal -->
<div id="modernLogoutModal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; display: none;">
    <div style="background: white; border-radius: 24px; padding: 30px 20px; max-width: 320px; width: 90%; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.1); position: relative; z-index: 2001; margin: auto; margin-top: 50%;">
        <div style="width: 70px; height: 70px; border-radius: 20px; background: #fee2e2; color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 30px; margin: 0 auto 20px;">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        <h3 style="font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 10px; font-family: 'Inter', sans-serif;">Going so soon?</h3>
        <p style="color: #64748b; font-size: 14px; margin-bottom: 25px; line-height: 1.5; font-family: 'Inter', sans-serif;">Are you sure you want to exit and end your collection session?</p>
        <div style="display: flex; flex-direction: column; gap: 10px;">
            <a href="logout.php" style="background: #ef4444; color: white; padding: 14px; border-radius: 14px; font-weight: 700; text-decoration: none; font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <i class="fas fa-door-open"></i> YES, EXIT
            </a>
            <button type="button" onclick="hideModernLogout()" style="background: #f1f5f9; color: #64748b; padding: 14px; border-radius: 14px; font-weight: 700; border: none; font-family: 'Inter', sans-serif; cursor: pointer;">
                KEEP WORKING
            </button>
        </div>
    </div>
</div>

<script>
function showModernLogout() {
    toggleMoreMenu(); // Close the more menu
    document.getElementById('modernLogoutModal').style.display = 'flex';
    document.getElementById('modernLogoutModal').style.opacity = '1';
    document.getElementById('modernLogoutModal').style.pointerEvents = 'auto';
}

function hideModernLogout() {
    document.getElementById('modernLogoutModal').style.display = 'none';
}

function toggleMoreMenu() {
    document.getElementById('morePopoutMenu').classList.toggle('active');
    document.getElementById('moreMenuOverlay').classList.toggle('active');
    const moreIcon = document.getElementById('moreIcon');
    if (moreIcon.classList.contains('fa-ellipsis-h')) {
        moreIcon.classList.remove('fa-ellipsis-h');
        moreIcon.classList.add('fa-times');
        moreIcon.style.transform = 'rotate(90deg)';
    } else {
        moreIcon.classList.remove('fa-times');
        moreIcon.classList.add('fa-ellipsis-h');
        moreIcon.style.transform = 'rotate(0deg)';
    }
}
</script>
