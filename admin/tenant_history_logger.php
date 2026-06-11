<?php
/**
 * Tenant History Logger
 * Logs tenant creation, updates, and deletions
 */

function logTenantHistory($conn, $action, $tenantData, $userEmail, $userName, $branch, $changesMade = null) {
    // Set the MySQL session timezone to Manila time
    date_default_timezone_set('Asia/Manila'); // Set timezone
  
    
    // Prepare the insert query for tenant history
    $query = "INSERT INTO tenant_history 
              (tenant_id, action, tenant_name, tenant_code, space_code, daily_rent, rent_balance, running_balance, branch, user_email, user_name, changes_made, date) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        // Extract tenant data
        $tenantId = $tenantData['id'] ?? null;
        $tenantName = $tenantData['tenantname'] ?? $tenantData['tenant_name'] ?? '';
        $tenantCode = $tenantData['tenantcode'] ?? $tenantData['tenant_code'] ?? null;
        $spaceCode = $tenantData['spacecode'] ?? $tenantData['space_code'] ?? null;
        $dailyRent = $tenantData['daily'] ?? $tenantData['daily_rent'] ?? null;
        $rentBalance = $tenantData['rentbal'] ?? $tenantData['rent_balance'] ?? null;
        $runningBalance = $tenantData['runningbal'] ?? $tenantData['running_balance'] ?? null;
        $currentDate = date('Y-m-d'); // Get current date in Y-m-d format
        
        // Bind parameters and execute
        $stmt->bind_param(
            "issssssssssss",
            $tenantId,
            $action,
            $tenantName,
            $tenantCode,
            $spaceCode,
            $dailyRent,
            $rentBalance,
            $runningBalance,
            $branch,
            $userEmail,
            $userName,
            $changesMade,
            $currentDate
        );
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    return false;
}

function getTenantChanges($oldData, $newData) {
    $changes = [];
    
    // Compare fields and record changes
    $fields = ['tenantname', 'tenantcode', 'spacecode', 'daily', 'rentbal', 'runningbal'];
    $fieldLabels = [
        'tenantname' => 'Tenant Name',
        'tenantcode' => 'Tenant Code',
        'spacecode' => 'Space Code',
        'daily' => 'Daily Rent',
        'rentbal' => 'Rent Balance',
        'runningbal' => 'Running Balance'
    ];
    
    foreach ($fields as $field) {
        $oldValue = $oldData[$field] ?? '';
        $newValue = $newData[$field] ?? '';
        
        if ($oldValue != $newValue) {
            $changes[] = sprintf(
                "%s: '%s' → '%s'",
                $fieldLabels[$field],
                $oldValue,
                $newValue
            );
        }
    }
    
    return implode(', ', $changes);
}
?>