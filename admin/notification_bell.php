<li class="nav-item dropdown no-arrow mx-1">
    <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button"
        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <i class="fas fa-bell fa-fw"></i>
        <span class="badge badge-danger badge-counter" id="unreadCounter">0</span>
    </a>
    <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in"
        aria-labelledby="alertsDropdown">
        <h6 class="dropdown-header">
            Alerts Center
        </h6>
        <div id="alertMessages" class="dropdown-notifications-container" style="max-height: 500px; overflow-y: auto;">
            <a class="dropdown-item d-flex align-items-center" href="#">
                <div class="mr-3">
                    <div class="icon-circle bg-primary">
                        <i class="fas fa-file-alt text-white"></i>
                    </div>
                </div>
                <div>
                    <div class="small text-gray-500">Loading...</div>
                    <span class="font-weight-bold">Loading alerts...</span>
                </div>
            </a>
        </div>
        <div class="dropdown-item text-center small text-gray-500">
            <div class="custom-control custom-switch mb-2">
                <input type="checkbox" class="custom-control-input" id="autoUpdateToggleNotifications" checked>
                <label class="custom-control-label" for="autoUpdateToggleNotifications">Auto Update Notifications</label>
            </div>
        </div>
        <a class="dropdown-item text-center small text-gray-500" href="#"
            id="viewAllTransactionsBtn">View All Messages Today</a>
    </div>
</li>

<div class="modal fade" id="allTransactionsModal" tabindex="-1" role="dialog"
    aria-labelledby="allTransactionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="allTransactionsModalLabel">All Transactions Today</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="allTransactionsContent">
                    Loading all transactions...
                </div>
            </div>
        </div>
    </div>
</div>
