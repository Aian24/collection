<?php
// This file contains the updated red card for tenants without transactions
// To be used in admin.php

$card_html = <<<EOT
<div class="col-xl-3 col-md-6 mb-4">
    <div class="card summary-card border-left-danger shadow h-100">
        <div class="card-body">
            <div class="summary-content">
                <div class="summary-icon">
                    <div class="icon-circle bg-danger">
                        <i class="fas fa-user-times fa-2x text-white pulse-icon"></i>
                    </div>
                </div>
                <div class="summary-details">
                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                        Tenants Without Transactions
                    </div>
                    <div class="text-xs text-gray-600 mb-1">
                        (<?php echo date('M d, Y', strtotime(\$start_date)); ?> to
                        <?php echo date('M d, Y', strtotime(\$end_date)); ?>)
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800 amount">
                        <?php echo \$tenants_no_transactions; ?>
                    </div>
                    <div class="text-center mt-2">
                        <button class="btn btn-danger btn-sm" id="viewMissingTransactionsBtn">
                            <i class="fas fa-search"></i> View Details
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
EOT;
?> 