// SWMS Admin JavaScript

(function() {
    'use strict';

    // ============================================================
    // 1. Sidebar Toggle
    // ============================================================
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mainContent = document.getElementById('mainContent');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            if (window.innerWidth <= 992) {
                sidebar.classList.toggle('mobile-show');
                document.body.classList.toggle('sidebar-open');
            } else {
                sidebar.classList.toggle('collapsed');
                document.body.classList.toggle('sidebar-collapsed');
            }
        });
    }

    // Close sidebar on overlay click (mobile)
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('mobile-show');
            document.body.classList.remove('sidebar-open');
        });
    }

    // ============================================================
    // 2. Sub-menu Toggle
    // ============================================================
    document.querySelectorAll('.nav-item.has-sub').forEach(function(item) {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            this.classList.toggle('open');
            const submenu = this.nextElementSibling;
            if (submenu && submenu.classList.contains('sub-menu')) {
                submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
            }
        });
    });

    // ============================================================
    // 3. Loading Spinner
    // ============================================================
    window.showLoader = function() {
        document.getElementById('spinnerOverlay').classList.add('show');
    };

    window.hideLoader = function() {
        document.getElementById('spinnerOverlay').classList.remove('show');
    };

    // ============================================================
    // 4. AJAX Setup with CSRF
    // ============================================================
    $.ajaxSetup({
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        beforeSend: function(xhr) {
            // Could add CSRF header here
        }
    });

    // ============================================================
    // 5. Auto-hide Alerts
    // ============================================================
    document.querySelectorAll('.alert-dismissible').forEach(function(alert) {
        setTimeout(function() {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });

    // ============================================================
    // 6. Form Validation Enhancement
    // ============================================================
    document.querySelectorAll('form[data-validate]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            var valid = true;
            this.querySelectorAll('[required]').forEach(function(input) {
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    valid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
            });
            if (!valid) e.preventDefault();
        });
    });

    // ============================================================
    // 7. Confirm Dialog
    // ============================================================
    window.confirmAction = function(message, callback) {
        if (confirm(message || 'Are you sure?')) {
            if (typeof callback === 'function') callback();
        }
    };

    // ============================================================
    // 8. Format Numbers
    // ============================================================
    window.formatNumber = function(num) {
        return new Intl.NumberFormat('en-IN').format(num);
    };

    window.formatCurrency = function(num) {
        return 'NRs. ' + new Intl.NumberFormat('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(num);
    };

    // ============================================================
    // 9. Toast Notifications
    // ============================================================
    window.showToast = function(type, message) {
        var toastHtml = '<div class="toast align-items-center text-bg-' + type + ' border-0 show" role="alert" aria-live="assertive" aria-atomic="true" style="position:fixed;top:20px;right:20px;z-index:9999;min-width:250px;">';
        toastHtml += '<div class="d-flex"><div class="toast-body">' + message + '</div>';
        toastHtml += '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>';
        var $toast = $(toastHtml).appendTo('body');
        setTimeout(function() { $toast.remove(); }, 5000);
    };

    // ============================================================
    // 10. Online/Offline Detection
    // ============================================================
    const offlineIndicator = document.getElementById('offlineIndicator');
    if (offlineIndicator) {
        window.addEventListener('online', function() {
            offlineIndicator.style.display = 'none';
        });
        window.addEventListener('offline', function() {
            offlineIndicator.style.display = 'block';
        });
    }

    // ============================================================
    // 10. DataTables Default Config
    // ============================================================
    if (typeof $.fn.DataTable !== 'undefined') {
        $.extend(true, $.fn.dataTable.defaults, {
            language: {
                search: '<i class="fas fa-search"></i>',
                searchPlaceholder: 'Search...',
                lengthMenu: 'Show _MENU_ entries',
                info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                infoEmpty: 'No entries found',
                infoFiltered: '(filtered from _MAX_ total entries)',
                zeroRecords: 'No matching records found',
                paginate: {
                    first: '<i class="fas fa-angle-double-left"></i>',
                    previous: '<i class="fas fa-angle-left"></i>',
                    next: '<i class="fas fa-angle-right"></i>',
                    last: '<i class="fas fa-angle-double-right"></i>'
                }
            },
            responsive: true,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            order: []
        });
    }

    // ============================================================
    // 11. Notification Refresh (every 30s)
    // ============================================================
    function refreshNotifications() {
        // AJAX call to fetch notifications
        // Placeholder - implement when API is ready
    }

    // ============================================================
    // 12. Initialize Select2-like behavior for searchable selects
    // ============================================================
    document.querySelectorAll('.select-searchable').forEach(function(select) {
        // Simple search enhancement for selects
        select.addEventListener('keyup', function(e) {
            var filter = e.target.value.toLowerCase();
            var options = this.querySelectorAll('option');
            var found = false;
            options.forEach(function(opt) {
                if (opt.text.toLowerCase().indexOf(filter) > -1) {
                    opt.style.display = '';
                    if (!found) { opt.selected = true; found = true; }
                } else {
                    opt.style.display = 'none';
                }
            });
        });
    });

    // ============================================================
    // 13. Dynamic Sidebar Badge Updates
    // ============================================================
    function updateSidebarBadges() {
        // Fetch defaulter count
        var defaulterBadge = document.getElementById('defaulterBadge');
        if (defaulterBadge) {
            fetch(API_URL + 'get-defaulter-count.php')
                .then(r => r.json())
                .then(d => { defaulterBadge.textContent = d.count || 0; })
                .catch(() => {});
        }

        // Fetch open complaint count
        var complaintBadge = document.getElementById('complaintBadge');
        if (complaintBadge) {
            fetch(API_URL + 'get-open-complaint-count.php')
                .then(r => r.json())
                .then(d => { complaintBadge.textContent = d.count || 0; })
                .catch(() => {});
        }
    }

    // Update badges on load and every 60 seconds
    if (document.getElementById('defaulterBadge') || document.getElementById('complaintBadge')) {
        updateSidebarBadges();
        setInterval(updateSidebarBadges, 60000);
    }

})();
