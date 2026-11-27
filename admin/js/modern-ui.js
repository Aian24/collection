/**
 * Modern Admin Interactive UI Engine v4
 * Includes: Animated Custom Dropdown Enhancer, Theme Sync, Spotlight Glow,
 * Tactile Ripples, Animated Counters, Toast Notifications, DataTables Draw Stagger.
 */
(function (window, document) {
    'use strict';

    const ModernUI = {
        init() {
            this.ensureContainers();
            this.initTheme();
            this.initTopbarEnhancements();
            this.initSidebarInteractions();
            this.initModalFixes();
            this.enhanceSelects();
            this.initSpotlightEffect();
            this.initRipples();
            this.initCounters();
            this.initScrollToTop();
            this.initNotificationBellPulse();
            this.initDataTableAnimations();
            this.initAjaxProgressBar();
        },

        /* ----------------------------------------------------------
         * Prevent modals from trapping inside sticky/animated stacking contexts
         * and sync enhanced selects when modals open.
         * ---------------------------------------------------------- */
        initModalFixes() {
            if (typeof window.jQuery === 'undefined') return;
            window.jQuery(document).on('show.bs.modal', '.modal', function () {
                if (this.parentNode !== document.body) {
                    document.body.appendChild(this);
                }
            });
            window.jQuery(document).on('shown.bs.modal', '.modal', function () {
                this.querySelectorAll('select[data-enhanced="1"]').forEach((sel) => {
                    sel.dispatchEvent(new CustomEvent('modern:sync'));
                });
            });
        },

        ensureContainers() {
            if (!document.getElementById('modernToastContainer')) {
                const toastBox = document.createElement('div');
                toastBox.id = 'modernToastContainer';
                document.body.appendChild(toastBox);
            }
            if (!document.getElementById('modernTopProgress')) {
                const bar = document.createElement('div');
                bar.id = 'modernTopProgress';
                document.body.appendChild(bar);
            }
        },

        /* ----------------------------------------------------------
         * Animated Custom Dropdown Enhancer for <select> elements
         * Syncs 100% with native <select> value, onchange, and MutationObserver
         * ---------------------------------------------------------- */
        enhanceSelects() {
            const selectors = [
                '.filter-pill select',
                'select.form-control-modern',
                'select.form-select'
            ].join(', ');

            document.querySelectorAll(selectors).forEach((selectEl) => {
                this.upgradeSelect(selectEl);
            });

            // Close any open dropdown when clicking outside or pressing Escape
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.modern-select-wrap')) {
                    document.querySelectorAll('.modern-select-wrap.open').forEach((w) => {
                        w.classList.remove('open');
                        const card = w.closest('.card, .page-hero, .filter-bar, .soa-card');
                        if (card) card.style.zIndex = '';
                    });
                }
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    document.querySelectorAll('.modern-select-wrap.open').forEach((w) => {
                        w.classList.remove('open');
                        const card = w.closest('.card, .page-hero, .filter-bar, .soa-card');
                        if (card) card.style.zIndex = '';
                    });
                }
            });
        },

        upgradeSelect(selectEl) {
            if (!selectEl || selectEl.dataset.enhanced === '1' || selectEl.closest('.dataTables_length')) return;
            if (selectEl.disabled) return;
            selectEl.dataset.enhanced = '1';

            const wrap = document.createElement('div');
            wrap.className = 'modern-select-wrap';
            if (selectEl.classList.contains('w-100') || selectEl.closest('.modal-body')) wrap.classList.add('w-100');

            const parent = selectEl.parentNode;
            parent.insertBefore(wrap, selectEl);
            wrap.appendChild(selectEl);
            selectEl.classList.add('modern-select-native');

            const trigger = document.createElement('button');
            trigger.type = 'button';
            trigger.className = 'modern-select-trigger';
            trigger.innerHTML = `<span class="ms-text"></span><i class="fas fa-chevron-down ms-chevron"></i>`;

            const menu = document.createElement('div');
            menu.className = 'modern-select-menu';

            wrap.appendChild(trigger);
            wrap.appendChild(menu);

            const rebuildOptions = () => {
                menu.innerHTML = '';
                const selectedOpt = selectEl.options[selectEl.selectedIndex];
                trigger.querySelector('.ms-text').textContent = selectedOpt ? selectedOpt.textContent.trim() : 'Select...';

                Array.from(selectEl.options).forEach((opt, idx) => {
                    const item = document.createElement('div');
                    item.className = 'modern-select-option';
                    if (opt.selected) item.classList.add('selected');
                    if (opt.disabled) item.classList.add('disabled');
                    item.dataset.value = opt.value;
                    item.innerHTML = `
                        <span>${this.escapeHtml(opt.textContent.trim())}</span>
                        ${opt.selected && !opt.disabled ? '<i class="fas fa-check text-primary" style="font-size:0.68rem;"></i>' : ''}
                    `;
                    item.addEventListener('click', (e) => {
                        e.stopPropagation();
                        if (opt.disabled) return;
                        selectEl.selectedIndex = idx;
                        selectEl.value = opt.value;
                        wrap.classList.remove('open');
                        rebuildOptions();
                        selectEl.dispatchEvent(new Event('change', { bubbles: true }));
                        if (typeof window.jQuery !== 'undefined') {
                            window.jQuery(selectEl).trigger('change');
                        }
                    });
                    menu.appendChild(item);
                });
            };

            rebuildOptions();

            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                const willOpen = !wrap.classList.contains('open');
                document.querySelectorAll('.modern-select-wrap.open').forEach((w) => {
                    w.classList.remove('open');
                    const c = w.closest('.card, .page-hero, .filter-bar, .soa-card');
                    if (c) c.style.zIndex = '';
                });
                if (willOpen) {
                    wrap.classList.add('open');
                    const card = wrap.closest('.card, .page-hero, .filter-bar, .soa-card');
                    if (card) card.style.zIndex = '150';
                }
            });

            // Sync if value changes programmatically
            selectEl.addEventListener('change', rebuildOptions);
            selectEl.addEventListener('modern:sync', rebuildOptions);

            // Sync if <option> children are dynamically added via AJAX (e.g., #spacecodeFilter in soa.php)
            const obs = new MutationObserver(() => rebuildOptions());
            obs.observe(selectEl, { childList: true, subtree: true });
        },

        /* ----------------------------------------------------------
         * Theme & Font Size Persistence
         * ---------------------------------------------------------- */
        initTheme() {
            const darkEnabled = localStorage.getItem('darkModeEnabled') === 'true';
            const fontSize = localStorage.getItem('fontSize') || 'medium';
            this.applyDarkMode(darkEnabled);
            this.applyFontSize(fontSize);

            const darkToggle = document.getElementById('darkModeToggle');
            if (darkToggle) {
                darkToggle.checked = darkEnabled;
                darkToggle.addEventListener('change', (e) => {
                    this.applyDarkMode(e.target.checked);
                    localStorage.setItem('darkModeEnabled', String(e.target.checked));
                });
            }

            const fontSelect = document.getElementById('fontSize');
            if (fontSelect) {
                fontSelect.value = fontSize;
                fontSelect.addEventListener('change', (e) => {
                    this.applyFontSize(e.target.value);
                    localStorage.setItem('fontSize', e.target.value);
                });
            }

            const saveBtn = document.getElementById('saveSettings');
            if (saveBtn) {
                saveBtn.addEventListener('click', () => {
                    if (darkToggle) {
                        this.applyDarkMode(darkToggle.checked);
                        localStorage.setItem('darkModeEnabled', String(darkToggle.checked));
                    }
                    if (fontSelect) {
                        this.applyFontSize(fontSelect.value);
                        localStorage.setItem('fontSize', fontSelect.value);
                    }
                    const enableNotif = document.getElementById('enableNotifications');
                    const notifSound = document.getElementById('notificationSound');
                    if (enableNotif) localStorage.setItem('enableNotifications', String(enableNotif.checked));
                    if (notifSound) localStorage.setItem('notificationSound', String(notifSound.checked));

                    if (typeof window.$ !== 'undefined') window.$('#settingsModal').modal('hide');
                    this.toast('success', 'Settings Saved', 'Your preferences have been updated.');
                });
            }
        },

        applyDarkMode(enabled) {
            document.body.classList.toggle('dark-mode', !!enabled);
            const btn = document.getElementById('quickThemeToggle');
            if (btn) {
                btn.innerHTML = enabled
                    ? '<i class="fas fa-sun text-warning"></i>'
                    : '<i class="fas fa-moon"></i>';
                btn.setAttribute('title', enabled ? 'Switch to Light Mode' : 'Switch to Dark Mode');
            }
            const darkToggle = document.getElementById('darkModeToggle');
            if (darkToggle && darkToggle.checked !== !!enabled) darkToggle.checked = !!enabled;
        },

        applyFontSize(size) {
            document.body.classList.remove('font-small', 'font-medium', 'font-large');
            document.body.classList.add('font-' + (size || 'medium'));
        },

        /* ----------------------------------------------------------
         * Topbar Quick Theme Toggle & Live Pill
         * ---------------------------------------------------------- */
        initTopbarEnhancements() {
            const topbarNav = document.querySelector('.topbar .navbar-nav');
            if (!topbarNav) return;

            if (!document.getElementById('quickThemeToggle')) {
                const li = document.createElement('li');
                li.className = 'nav-item d-flex align-items-center mx-1';
                const isDark = document.body.classList.contains('dark-mode');
                li.innerHTML = `
                    <button type="button" id="quickThemeToggle" class="theme-toggle-btn" title="${isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'}">
                        ${isDark ? '<i class="fas fa-sun text-warning"></i>' : '<i class="fas fa-moon"></i>'}
                    </button>
                `;
                const divider = topbarNav.querySelector('.topbar-divider');
                if (divider) topbarNav.insertBefore(li, divider);
                else topbarNav.prepend(li);

                document.getElementById('quickThemeToggle').addEventListener('click', () => {
                    const next = !document.body.classList.contains('dark-mode');
                    this.applyDarkMode(next);
                    localStorage.setItem('darkModeEnabled', String(next));
                    this.toast('info', next ? 'Dark Mode Enabled' : 'Light Mode Enabled', 'Theme preference saved.');
                });
            }

            const topbar = document.querySelector('.topbar');
            if (topbar && !topbar.querySelector('.topbar-left-meta')) {
                const pageTitle = document.title.replace('LCLopez Resources - ', '').trim() || 'Admin Panel';
                const leftMeta = document.createElement('div');
                leftMeta.className = 'topbar-left-meta d-none d-md-flex align-items-center';
                leftMeta.innerHTML = `
                    <div class="topbar-page-pill">
                        <span class="live-dot"></span>
                        <span>${this.escapeHtml(pageTitle)}</span>
                    </div>
                `;
                const toggleBtn = topbar.querySelector('#sidebarToggleTop');
                if (toggleBtn && toggleBtn.nextSibling) topbar.insertBefore(leftMeta, toggleBtn.nextSibling);
                else topbar.prepend(leftMeta);
            }
        },

        /* ----------------------------------------------------------
         * Mobile Sidebar Drawer
         * ---------------------------------------------------------- */
        initSidebarInteractions() {
            const sidebar = document.getElementById('accordionSidebar');
            if (!sidebar) return;

            let backdrop = document.querySelector('.sidebar-mobile-backdrop');
            if (!backdrop) {
                backdrop = document.createElement('div');
                backdrop.className = 'sidebar-mobile-backdrop';
                document.body.appendChild(backdrop);
            }

            const topToggle = document.getElementById('sidebarToggleTop');
            if (topToggle) {
                topToggle.addEventListener('click', (e) => {
                    if (window.innerWidth <= 768) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        const isOpen = sidebar.classList.toggle('mobile-open');
                        backdrop.classList.toggle('active', isOpen);
                    }
                }, true);
            }

            backdrop.addEventListener('click', () => {
                sidebar.classList.remove('mobile-open');
                backdrop.classList.remove('active');
            });
        },

        initSpotlightEffect() {
            document.addEventListener('mousemove', (e) => {
                const card = e.target.closest('.interactive-card, .kpi-card');
                if (!card) return;
                const rect = card.getBoundingClientRect();
                card.style.setProperty('--mouse-x', (((e.clientX - rect.left) / rect.width) * 100).toFixed(1) + '%');
                card.style.setProperty('--mouse-y', (((e.clientY - rect.top) / rect.height) * 100).toFixed(1) + '%');
            });
        },

        initRipples() {
            document.addEventListener('click', (e) => {
                const target = e.target.closest('.btn, .btn-filter-apply, .preset-pill-btn');
                if (!target || target.disabled) return;
                const rect = target.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const ripple = document.createElement('span');
                ripple.className = 'ui-ripple';
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
                ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
                target.appendChild(ripple);
                setTimeout(() => ripple.remove(), 600);
            });
        },

        initCounters() {
            document.querySelectorAll('[data-count-target]').forEach((el) => {
                const rawTarget = parseFloat(el.getAttribute('data-count-target'));
                if (isNaN(rawTarget)) return;
                const prefix = el.getAttribute('data-count-prefix') || '';
                const suffix = el.getAttribute('data-count-suffix') || '';
                const decimals = parseInt(el.getAttribute('data-count-decimals') || '0', 10);
                this.animateNumber(el, 0, rawTarget, 700, prefix, suffix, decimals);
            });
        },

        animateNumber(el, from, to, duration = 650, prefix = '', suffix = '', decimals = 0) {
            if (!el) return;
            const start = performance.now();
            const easeOutExpo = (t) => (t === 1 ? 1 : 1 - Math.pow(2, -10 * t));
            const frame = (now) => {
                const progress = Math.min((now - start) / duration, 1);
                const current = from + (to - from) * easeOutExpo(progress);
                el.textContent = prefix + current.toLocaleString('en-US', {
                    minimumFractionDigits: decimals, maximumFractionDigits: decimals
                }) + suffix;
                if (progress < 1) requestAnimationFrame(frame);
                else el.textContent = prefix + to.toLocaleString('en-US', {
                    minimumFractionDigits: decimals, maximumFractionDigits: decimals
                }) + suffix;
            };
            requestAnimationFrame(frame);
        },

        initScrollToTop() {
            const btn = document.querySelector('.scroll-to-top');
            if (!btn) return;
            window.addEventListener('scroll', () => {
                btn.style.display = window.scrollY > 200 ? 'inline' : 'none';
            });
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        },

        initNotificationBellPulse() {
            const counter = document.getElementById('unreadCounter');
            const bellLink = document.getElementById('alertsDropdown');
            if (!counter || !bellLink) return;
            const syncBell = () => bellLink.classList.toggle('has-unread', parseInt(counter.textContent || '0', 10) > 0);
            syncBell();
            new MutationObserver(syncBell).observe(counter, { childList: true, characterData: true, subtree: true });
        },

        initDataTableAnimations() {
            if (typeof window.jQuery === 'undefined') return;
            window.jQuery(document).on('draw.dt', 'table.dataTable', function () {
                this.querySelectorAll('tbody tr').forEach((row, idx) => {
                    if (idx < 25) {
                        row.classList.remove('row-animate-in');
                        void row.offsetWidth;
                        row.style.animationDelay = (idx * 14) + 'ms';
                        row.classList.add('row-animate-in');
                    }
                });
            });
        },

        initAjaxProgressBar() {
            if (typeof window.jQuery === 'undefined') return;
            const bar = document.getElementById('modernTopProgress');
            if (!bar) return;
            window.jQuery(document).ajaxStart(() => {
                bar.style.opacity = '1';
                bar.style.width = '68%';
            }).ajaxStop(() => {
                bar.style.width = '100%';
                setTimeout(() => {
                    bar.style.opacity = '0';
                    setTimeout(() => { bar.style.width = '0%'; }, 220);
                }, 200);
            });
        },

        toast(type = 'info', title = 'Notice', message = '', duration = 3200) {
            const container = document.getElementById('modernToastContainer');
            if (!container) return;
            const iconMap = {
                success: 'fa-check-circle', info: 'fa-info-circle',
                warning: 'fa-exclamation-triangle', danger: 'fa-times-circle'
            };
            const el = document.createElement('div');
            el.className = `modern-toast toast-${type}`;
            el.innerHTML = `
                <div class="modern-toast-icon"><i class="fas ${iconMap[type] || 'fa-info-circle'}"></i></div>
                <div style="flex:1; min-width:0;">
                    <div class="modern-toast-title">${this.escapeHtml(title)}</div>
                    ${message ? `<div class="modern-toast-msg">${this.escapeHtml(message)}</div>` : ''}
                </div>
                <button type="button" class="close ml-2" aria-label="Close" style="font-size:0.95rem; opacity:0.5; border:none; background:none;">&times;</button>
            `;
            const close = () => {
                el.classList.add('toast-leaving');
                setTimeout(() => el.remove(), 220);
            };
            el.querySelector('.close').addEventListener('click', close);
            container.appendChild(el);
            if (duration > 0) setTimeout(close, duration);
        },

        getDatePresetRange(preset) {
            const now = new Date();
            const pad = (n) => String(n).padStart(2, '0');
            const fmt = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
            let start = new Date(now), end = new Date(now);
            switch (preset) {
                case 'yesterday': start.setDate(now.getDate() - 1); end.setDate(now.getDate() - 1); break;
                case 'week': {
                    const day = now.getDay();
                    start.setDate(now.getDate() - (day === 0 ? 6 : day - 1));
                    break;
                }
                case 'last7': start.setDate(now.getDate() - 6); break;
                case 'last30': start.setDate(now.getDate() - 29); break;
                case 'month':
                    start = new Date(now.getFullYear(), now.getMonth(), 1);
                    end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                    break;
                case 'lastmonth':
                    start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                    end = new Date(now.getFullYear(), now.getMonth(), 0);
                    break;
                case 'ytd': start = new Date(now.getFullYear(), 0, 1); break;
            }
            return { start: fmt(start), end: fmt(end) };
        },

        escapeHtml(str) {
            return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
    };

    window.ModernUI = ModernUI;
    window.showModernToast = (type, title, message, duration) => ModernUI.toast(type, title, message, duration);
    document.addEventListener('DOMContentLoaded', () => ModernUI.init());
})(window, document);
