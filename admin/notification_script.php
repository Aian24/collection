<script>
$(document).ready(function() {
    function formatCurrency(amount) {
        return '₱' + parseFloat(amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function truncateText(text, length) {
        if (!text) return '';
        if (text.length <= length) return text;
        return text.substring(0, length) + '...';
    }

    function updateNotifications() {
        const loadingHtml = `
            <a class="dropdown-item" href="#">
                <div class="mr-3">
                    <div class="icon-circle bg-primary">
                        <i class="fas fa-sync fa-spin text-white"></i>
                    </div>
                </div>
                <div>
                    <div class="small text-gray-500">Loading...</div>
                    <span class="font-weight-bold">Loading notifications...</span>
                </div>
            </a>
        `;
        $('#alertMessages').html(loadingHtml);
        
        // Check if we are in an APM page based on URL
        const isApm = window.location.pathname.toLowerCase().includes('apm.php');
        const ajaxData = { 
            _: new Date().getTime(),
            limit: 'all'
        };
        
        if (isApm) {
            ajaxData.branch = 'APM';
        }

        $.ajax({
            url: 'get_notifications.php',
            type: 'GET',
            dataType: 'json',
            cache: false,
            data: ajaxData,
            success: function(response) {
                $('#unreadCounter').text(response.total);
                $('#alertMessages').empty();

                if (response.notifications && response.notifications.length > 0) {
                    response.notifications.sort((a, b) => new Date(b.time) - new Date(a.time));
                    
                    response.notifications.forEach(function(notification, index) {
                        var notificationHtml = `
                            <a class="dropdown-item notification-item-${index}" href="#">
                                <div>
                                    <div class="icon-circle bg-primary">
                                        <i class="fas fa-file-alt text-white"></i>
                                    </div>
                                    <div class="small text-gray-500">${notification.time} - ${notification.branch}</div>
                                    <span class="font-weight-bold">${truncateText(notification.message, 30)}</span><br>
                                    <span class="text-primary">${formatCurrency(notification.amount)}</span>
                                </div>
                            </a>
                        `;
                        $('#alertMessages').append(notificationHtml);
                    });

                    var modalContent = response.notifications.map(function(notification) {
                        return `
                            <div class="alert alert-info">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">${notification.message}</h6>
                                        <small class="text-muted">Transaction #: ${notification.transaction_number}</small>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-primary font-weight-bold">${formatCurrency(notification.amount)}</div>
                                        <small class="text-muted">${notification.time} - ${notification.branch}</small>
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('');
                    
                    $('#allTransactionsContent').html(modalContent);
                } else {
                    $('#alertMessages').html('<a class="dropdown-item text-center" href="#">No new transactions today</a>');
                    $('#allTransactionsContent').html('<div class="alert alert-info">No transactions recorded today.</div>');
                }
            },
            error: function() {
                console.error('Failed to fetch notifications');
            }
        });
    }

    $('#viewAllTransactionsBtn').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $('#allTransactionsModal').modal('show');
    });

    let notificationInterval;
    function startNotificationInterval() {
        if ($('#autoUpdateToggleNotifications').is(':checked')) {
            updateNotifications();
            notificationInterval = setInterval(updateNotifications, 30000);
        }
    }

    function stopNotificationInterval() {
        if (notificationInterval) {
            clearInterval(notificationInterval);
            notificationInterval = null;
        }
    }

    $('#autoUpdateToggleNotifications').on('change', function() {
        if ($(this).is(':checked')) {
            startNotificationInterval();
        } else {
            stopNotificationInterval();
        }
    });

    // Pause updates when modal is open
    $('#allTransactionsModal').on('show.bs.modal', function () {
        stopNotificationInterval();
    });
    
    $('#allTransactionsModal').on('hidden.bs.modal', function () {
        if ($('#autoUpdateToggleNotifications').is(':checked')) {
            startNotificationInterval();
        }
    });

    // Start initially
    startNotificationInterval();
});
</script>

