<?php
ob_start(); // Start output buffering
include '../config.php'; // Assuming config.php is in the parent directory
session_start();

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    header("Location: ../index.php");
    exit();
}

// Fetch user data from database using username to avoid session ID conflicts
$username = $_SESSION['username'];
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    $defaults = [
        'username' => '',
        'fname' => '',
        'lname' => '',
        'email' => '',
        'branch' => '',
        'profile_photo' => ''
    ];

    foreach ($user as $key => $value) {
        $_SESSION[$key] = $value !== null ? $value : ($defaults[$key] ?? $value);
    }
    
    $_SESSION['user_id'] = $user['id'];

    foreach ($defaults as $key => $value) {
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = $value;
        }
    }
} else {
    $_SESSION['error_message'] = "Failed to load user profile data.";
}

$stmt->close();

// Ensure required session variables have values
$required_fields = ['fname', 'lname', 'email', 'username', 'user_type', 'branch'];
foreach ($required_fields as $field) {
    if (!isset($_SESSION[$field])) {
        error_log("Missing required session field: " . $field);
        $_SESSION['error_message'] = "Missing required profile data. Please contact administrator.";
        header("Location: ../logout.php");
        exit();
    }
}

$lname = $_SESSION["lname"];
date_default_timezone_set('Asia/Manila'); // Set timezone

// Get selected start and end dates from GET parameters
// Default to today's date for both start and end if not specified
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Validate and format the dates
if (!strtotime($start_date) || !strtotime($end_date)) {
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d');
    error_log("Invalid date range provided: start=$start_date, end=$end_date");
}

// Ensure end_date includes the entire day by setting it to 23:59:59
$end_date_time = date('Y-m-d 23:59:59', strtotime($end_date));
$start_date_time = date('Y-m-d 00:00:00', strtotime($start_date));

// Get selected branch from GET parameters (Locked to APM)
$branch = 'APM';

// Fetch branches
$query_branches = "SELECT DISTINCT branch FROM collected UNION SELECT DISTINCT branch FROM collectednova UNION SELECT DISTINCT branch FROM collectedapm";
$result_branches = mysqli_query($conn, $query_branches);
$branches = [];
if ($result_branches) {
    while ($row = mysqli_fetch_assoc($result_branches)) {
        $branches[] = $row['branch'];
    }
    mysqli_free_result($result_branches);
} else {
    error_log("Error fetching branches: " . mysqli_error($conn));
    // You might want to display an error message to the user here
}


// Determine the table based on the selected branch
// Default to 'collected' if no branch is selected or branch name doesn't match
$table = 'collected'; // Default table
if ($branch === 'Nova Market') {
    $table = 'collectednova';
} elseif ($branch === 'APM') {
    $table = 'collectedapm';
}

// Sanitize branch input for queries
$escaped_branch = mysqli_real_escape_string($conn, $branch);


// --- Data for Summary Cards ---

// Fetch transactions for the selected date range and branch
if ($branch) {
    $query_transactions = "SELECT * FROM $table 
        WHERE collected_date BETWEEN '$start_date_time' AND '$end_date_time' 
        AND branch = '$escaped_branch' 
        ORDER BY collected_date DESC, transaction_number ASC";
} else {
    $query_transactions = "
        (SELECT * FROM collected 
        WHERE collected_date BETWEEN '$start_date_time' AND '$end_date_time')
        UNION ALL
        (SELECT * FROM collectednova 
        WHERE collected_date BETWEEN '$start_date_time' AND '$end_date_time')
        UNION ALL
        (SELECT * FROM collectedapm 
        WHERE collected_date BETWEEN '$start_date_time' AND '$end_date_time')
        ORDER BY collected_date DESC, transaction_number ASC";
}

$result_transactions = mysqli_query($conn, $query_transactions);

$transactions = [];
if ($result_transactions) {
    while ($row = mysqli_fetch_assoc($result_transactions)) {
        // Ensure the date is in the correct format
        if (isset($row['collected_date'])) {
            try {
                $date = new DateTime($row['collected_date']);
                $row['collected_date'] = $date->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                error_log("Date parsing error: " . $e->getMessage());
            }
        }
        $transactions[] = $row;
    }
    mysqli_free_result($result_transactions);
} else {
    error_log("Error fetching transactions for table display: " . mysqli_error($conn));
    // Display error message
}


// Fetch total paid rent, paid balance, and charges for the selected date range from the database (for summary card)
if ($branch) {
    $query_totals = "SELECT
        SUM(paidrent) AS total_paidrent,
        SUM(paidbal) AS total_paidbal,
        GROUP_CONCAT(charges SEPARATOR ',') AS all_charges_summary
        FROM $table
        WHERE collected_date BETWEEN '$start_date_time' AND '$end_date_time'
        AND branch = '$escaped_branch'";
} else {
    $query_totals = "
        SELECT 
            SUM(total_paidrent) as total_paidrent,
            SUM(total_paidbal) as total_paidbal,
            GROUP_CONCAT(all_charges_summary) as all_charges_summary
        FROM (
            (SELECT
                SUM(paidrent) AS total_paidrent,
                SUM(paidbal) AS total_paidbal,
                GROUP_CONCAT(charges SEPARATOR ',') AS all_charges_summary
            FROM collected
            WHERE collected_date BETWEEN '$start_date_time' AND '$end_date_time')
            UNION ALL
            (SELECT
                SUM(paidrent) AS total_paidrent,
                SUM(paidbal) AS total_paidbal,
                GROUP_CONCAT(charges SEPARATOR ',') AS all_charges_summary
            FROM collectednova
            WHERE collected_date BETWEEN '$start_date_time' AND '$end_date_time')
            UNION ALL
            (SELECT
                SUM(paidrent) AS total_paidrent,
                SUM(paidbal) AS total_paidbal,
                GROUP_CONCAT(charges SEPARATOR ',') AS all_charges_summary
            FROM collectedapm
            WHERE collected_date BETWEEN '$start_date_time' AND '$end_date_time')
        ) as combined_totals";
}

$result_totals = mysqli_query($conn, $query_totals);

// Initialize totals
$totalPaidRent = 0;
$totalPaidBal = 0;
$totalCharges = 0;
$all_charges_combined = '';

if ($result_totals) {
    while ($row = mysqli_fetch_assoc($result_totals)) {
        $totalPaidRent += $row['total_paidrent'] ?? 0;
        $totalPaidBal += $row['total_paidbal'] ?? 0;
        if (!empty($row['all_charges_summary'])) {
            $all_charges_combined .= $row['all_charges_summary'] . ',';
        }
    }
    // Process combined charges
    if (!empty($all_charges_combined)) {
        $totalCharges = extractCharges(rtrim($all_charges_combined, ','));
    }
    mysqli_free_result($result_totals);
} else {
    error_log("Error fetching totals for summary card: " . mysqli_error($conn));
}


// Function to extract charges from a single GROUP_CONCAT string (used for summary card and chart data)
function extractCharges($chargesStr)
{
    $total = 0;
    if (empty($chargesStr)) {
        return $total;
    }
    // Split the string by comma
    $chargesArray = explode(',', $chargesStr);
    foreach ($chargesArray as $charge) {
        // Split each charge entry by colon
        $parts = explode(':', $charge);
        // Ensure there's a value part and it's numeric
        if (isset($parts[1])) {
            $amount = trim($parts[1]);
            if (is_numeric($amount)) {
                $total += floatval($amount);
            }
        }
    }
    return $total;
}

// Calculate total tenants that have transactions in the specified date range
if ($branch) {
    $query_tenants = "SELECT COUNT(DISTINCT tenantcode) AS total_tenants 
        FROM $table
        WHERE collected_date BETWEEN '$start_date_time' AND '$end_date_time'
        AND branch = '$escaped_branch'";
} else {
    $query_tenants = "
        SELECT SUM(tenant_count) as total_tenants 
        FROM (
            SELECT COUNT(DISTINCT tenantcode) as tenant_count 
            FROM collected
            WHERE collected_date BETWEEN '$start_date_time' AND '$end_date_time'
            UNION ALL
            SELECT COUNT(DISTINCT tenantcode) as tenant_count 
            FROM collectednova
            WHERE collected_date BETWEEN '$start_date_time' AND '$end_date_time'
            UNION ALL
            SELECT COUNT(DISTINCT tenantcode) as tenant_count 
            FROM collectedapm
            WHERE collected_date BETWEEN '$start_date_time' AND '$end_date_time'
        ) as combined_tenants";
}

$result_tenants = mysqli_query($conn, $query_tenants);

$total_tenants = 0;
if ($result_tenants && $row_tenants = mysqli_fetch_assoc($result_tenants)) {
    $total_tenants = $row_tenants['total_tenants'] ?? 0;
     mysqli_free_result($result_tenants);
} else {
     error_log("Query failed for tenants: " . mysqli_error($conn));
}

// Calculate tenants with no transactions in the specified date range
if ($branch) {
    // Determine the actual tenant table name based on the branch
    $tenant_table = '';
    if ($branch == 'Sanko Market') {
        $tenant_table = 'sanko';
    } elseif ($branch == 'Nova Market') {
        $tenant_table = 'nova';
    } elseif ($branch == 'APM') {
        $tenant_table = 'apm';
    }
    
    // If we have a valid tenant table, use it to find all tenants without transactions
    if (!empty($tenant_table)) {
        // Calculate total days in date range
        $date1 = new DateTime($start_date);
        $date2 = new DateTime($end_date);
        $interval = $date1->diff($date2);
        $days_in_range = $interval->days + 1; // +1 to include both start and end dates
        
        error_log("Date range: $start_date to $end_date, Days in range: $days_in_range");
        
        // First get a list of all tenants (or spaces) for this branch
        $all_tenants_query = "SELECT tenantcode, tenantname, spacecode FROM $tenant_table";
        $all_tenants_result = mysqli_query($conn, $all_tenants_query);
        
        if (!$all_tenants_result) {
            error_log("Error in all_tenants_query: " . mysqli_error($conn));
        }
        
        // Then get a list of transaction days for each tenant AND for each space
        $transaction_days_by_tenant_query = "
            SELECT tenantcode, COUNT(DISTINCT DATE(collected_date)) as transaction_days
            FROM $table
            WHERE branch = '$escaped_branch'
            AND collected_date BETWEEN '$start_date_time' AND '$end_date_time'
            AND tenantcode IS NOT NULL AND tenantcode != ''
            GROUP BY tenantcode";
            
        $transaction_days_by_tenant_result = mysqli_query($conn, $transaction_days_by_tenant_query);
        
        if (!$transaction_days_by_tenant_result) {
            error_log("Error in transaction_days_by_tenant_query: " . mysqli_error($conn));
        }
        
        // Get transaction days by space code
        $transaction_days_by_space_query = "
            SELECT spacecode, COUNT(DISTINCT DATE(collected_date)) as transaction_days
            FROM $table
            WHERE branch = '$escaped_branch'
            AND collected_date BETWEEN '$start_date_time' AND '$end_date_time'
            AND spacecode IS NOT NULL AND spacecode != ''
            GROUP BY spacecode";
            
        $transaction_days_by_space_result = mysqli_query($conn, $transaction_days_by_space_query);
        
        if (!$transaction_days_by_space_result) {
            error_log("Error in transaction_days_by_space_query: " . mysqli_error($conn));
        }
        
        // Build lookup arrays for transaction days
        $transaction_days_by_tenant = [];
        while ($row = mysqli_fetch_assoc($transaction_days_by_tenant_result)) {
            $transaction_days_by_tenant[$row['tenantcode']] = intval($row['transaction_days']);
        }
        
        $transaction_days_by_space = [];
        while ($row = mysqli_fetch_assoc($transaction_days_by_space_result)) {
            if (!empty($row['spacecode'])) {
                $transaction_days_by_space[$row['spacecode']] = intval($row['transaction_days']);
            }
        }
        
        // Build the list of tenants with missing days
        $tenants_no_transactions_list = [];
        $tenants_no_transactions = 0;
        
        if ($all_tenants_result) {
            while ($tenant = mysqli_fetch_assoc($all_tenants_result)) {
                $tenantcode = $tenant['tenantcode'];
                $spacecode = $tenant['spacecode'];
                
                // Check transactions by tenant code first
                $days_with_transactions = 0;
                if (!empty($tenantcode) && isset($transaction_days_by_tenant[$tenantcode])) {
                    $days_with_transactions = $transaction_days_by_tenant[$tenantcode];
                } 
                // If no tenant code or no transactions found by tenant code, try space code
                elseif (!empty($spacecode) && isset($transaction_days_by_space[$spacecode])) {
                    $days_with_transactions = $transaction_days_by_space[$spacecode];
                }
                
                // If this tenant/space has fewer transaction days than the total days in range
                if ($days_with_transactions < $days_in_range) {
                    $missing_days_count = $days_in_range - $days_with_transactions;
                    
                    // Add tenant to the list
                    $tenant['branch'] = $branch;
                    $tenant['days_with_transactions'] = $days_with_transactions;
                    $tenant['total_days_in_range'] = $days_in_range;
                    $tenant['missing_days_count'] = $missing_days_count;
                    
                    $tenants_no_transactions_list[] = $tenant;
                    $tenants_no_transactions++;
                }
            }
        }
        
        // Sort the list by tenant name
        usort($tenants_no_transactions_list, function($a, $b) {
            if (empty($a['tenantname']) && !empty($b['tenantname'])) return 1;
            if (!empty($a['tenantname']) && empty($b['tenantname'])) return -1;
            if (empty($a['tenantname']) && empty($b['tenantname'])) {
                return strcmp($a['spacecode'] ?? '', $b['spacecode'] ?? '');
            }
            return strcmp($a['tenantname'], $b['tenantname']);
        });
    } else {
        // Fallback to the old method if tenant table couldn't be determined
        error_log("Tenant table could not be determined for branch: $branch");
        
        // Calculate total days in date range
        $date1 = new DateTime($start_date);
        $date2 = new DateTime($end_date);
        $interval = $date1->diff($date2);
        $days_in_range = $interval->days + 1; // +1 to include both start and end dates

        // Get all tenants from the transactions table, including those identified by space code
        $all_tenants_query = "SELECT DISTINCT 
                               COALESCE(tenantcode, spacecode) as identifier,
                               tenantcode, tenantname, spacecode, branch 
                             FROM $table 
                             WHERE branch = '$escaped_branch'
                             AND (tenantcode IS NOT NULL OR spacecode IS NOT NULL)";
        $all_tenants_result = mysqli_query($conn, $all_tenants_query);
        
        if (!$all_tenants_result) {
            error_log("Error in fallback all_tenants_query: " . mysqli_error($conn));
        }
        
        // Get transaction days for each tenant by tenant code
        $transaction_days_by_tenant_query = "
            SELECT tenantcode, COUNT(DISTINCT DATE(collected_date)) as transaction_days
            FROM $table
            WHERE branch = '$escaped_branch'
            AND collected_date BETWEEN '$start_date_time' AND '$end_date_time'
            AND tenantcode IS NOT NULL AND tenantcode != ''
            GROUP BY tenantcode";
            
        $transaction_days_by_tenant_result = mysqli_query($conn, $transaction_days_by_tenant_query);
        
        if (!$transaction_days_by_tenant_result) {
            error_log("Error in fallback transaction_days_by_tenant_query: " . mysqli_error($conn));
        }
        
        // Get transaction days for each space by space code
        $transaction_days_by_space_query = "
            SELECT spacecode, COUNT(DISTINCT DATE(collected_date)) as transaction_days
            FROM $table
            WHERE branch = '$escaped_branch'
            AND collected_date BETWEEN '$start_date_time' AND '$end_date_time'
            AND spacecode IS NOT NULL AND spacecode != ''
            GROUP BY spacecode";
            
        $transaction_days_by_space_result = mysqli_query($conn, $transaction_days_by_space_query);
        
        if (!$transaction_days_by_space_result) {
            error_log("Error in fallback transaction_days_by_space_query: " . mysqli_error($conn));
        }
        
        // Build lookup arrays
        $transaction_days_by_tenant = [];
        while ($row = mysqli_fetch_assoc($transaction_days_by_tenant_result)) {
            $transaction_days_by_tenant[$row['tenantcode']] = intval($row['transaction_days']);
        }
        
        $transaction_days_by_space = [];
        while ($row = mysqli_fetch_assoc($transaction_days_by_space_result)) {
            if (!empty($row['spacecode'])) {
                $transaction_days_by_space[$row['spacecode']] = intval($row['transaction_days']);
            }
        }
        
        // Process tenants
        $tenants_no_transactions_list = [];
        $tenants_no_transactions = 0;
        
        if ($all_tenants_result) {
            while ($tenant = mysqli_fetch_assoc($all_tenants_result)) {
                $tenantcode = $tenant['tenantcode'];
                $spacecode = $tenant['spacecode'];
                
                // Fix specific to APM branch which needs to check both tenant code and space code
                if ($branch == 'APM') {
                    // Run a direct query that combines both tenant and space code checks
                    $combined_query = "SELECT COUNT(DISTINCT DATE(collected_date)) as transaction_days 
                                      FROM $table 
                                      WHERE branch = '$escaped_branch'
                                      AND collected_date BETWEEN '$start_date_time' AND '$end_date_time'
                                      AND (";
                    
                    // Add conditions based on available identifiers
                    $conditions = [];
                    if (!empty($tenantcode)) {
                        $conditions[] = "tenantcode = '" . mysqli_real_escape_string($conn, $tenantcode) . "'";
                    }
                    if (!empty($spacecode)) {
                        $conditions[] = "spacecode = '" . mysqli_real_escape_string($conn, $spacecode) . "'";
                    }
                    
                    // Combine conditions with OR
                    $combined_query .= implode(" OR ", $conditions) . ")";
                    
                    $combined_result = mysqli_query($conn, $combined_query);
                    if ($combined_result && $row = mysqli_fetch_assoc($combined_result)) {
                        $days_with_transactions = intval($row['transaction_days']);
                        error_log("APM tenant/space direct query: $combined_query resulted in $days_with_transactions days for tenant " . 
                                  (!empty($tenantcode) ? $tenantcode : $spacecode));
                    } else {
                        $days_with_transactions = 0;
                        error_log("APM query error or no results: " . mysqli_error($conn));
                    }
                } else {
                    // For other branches, use the existing logic
                    $days_with_transactions = 0;
                    if (!empty($tenantcode) && isset($transaction_days_by_tenant[$tenantcode])) {
                        $days_with_transactions = $transaction_days_by_tenant[$tenantcode];
                    } 
                    elseif (!empty($spacecode) && isset($transaction_days_by_space[$spacecode])) {
                        $days_with_transactions = $transaction_days_by_space[$spacecode];
                    }
                }
                
                if ($days_with_transactions < $days_in_range) {
                    $missing_days_count = $days_in_range - $days_with_transactions;
                    
                    $tenant['days_with_transactions'] = $days_with_transactions;
                    $tenant['total_days_in_range'] = $days_in_range;
                    $tenant['missing_days_count'] = $missing_days_count;
                    
                    $tenants_no_transactions_list[] = $tenant;
                    $tenants_no_transactions++;
                }
            }
        }
        
        // Sort the list by tenant name
        usort($tenants_no_transactions_list, function($a, $b) {
            if (empty($a['tenantname']) && !empty($b['tenantname'])) return 1;
            if (!empty($a['tenantname']) && empty($b['tenantname'])) return -1;
            if (empty($a['tenantname']) && empty($b['tenantname'])) {
                return strcmp($a['spacecode'] ?? '', $b['spacecode'] ?? '');
            }
            return strcmp($a['tenantname'], $b['tenantname']);
        });
    }
} else {
    // For all branches - combine all tenant tables
    // Calculate total days in date range
    $date1 = new DateTime($start_date);
    $date2 = new DateTime($end_date);
    $interval = $date1->diff($date2);
    $days_in_range = $interval->days + 1; // +1 to include both start and end dates
    
    error_log("All branches date range: $start_date to $end_date, Days in range: $days_in_range");
    
    // Get all tenants from all branch tables
    $all_tenants_sanko_query = "SELECT tenantcode, tenantname, spacecode, 'Sanko Market' as branch FROM sanko";
    $all_tenants_nova_query = "SELECT tenantcode, tenantname, spacecode, 'Nova Market' as branch FROM nova";
    $all_tenants_apm_query = "SELECT tenantcode, tenantname, spacecode, 'APM' as branch FROM apm";
    
    $all_tenants_sanko = mysqli_query($conn, $all_tenants_sanko_query);
    $all_tenants_nova = mysqli_query($conn, $all_tenants_nova_query);
    $all_tenants_apm = mysqli_query($conn, $all_tenants_apm_query);
    
    if (!$all_tenants_sanko) {
        error_log("Error in all_tenants_sanko_query: " . mysqli_error($conn));
    }
    if (!$all_tenants_nova) {
        error_log("Error in all_tenants_nova_query: " . mysqli_error($conn));
    }
    if (!$all_tenants_apm) {
        error_log("Error in all_tenants_apm_query: " . mysqli_error($conn));
    }
    
    // Get transaction days by tenant code for each branch
    $transaction_days_sanko_tenant_query = "
        SELECT tenantcode, COUNT(DISTINCT DATE(collected_date)) as transaction_days
        FROM collected
        WHERE collected_date BETWEEN '$start_date_time' AND '$end_date_time'
        AND tenantcode IS NOT NULL AND tenantcode != ''
        GROUP BY tenantcode";
        
    $transaction_days_nova_tenant_query = "
        SELECT tenantcode, COUNT(DISTINCT DATE(collected_date)) as transaction_days
        FROM collectednova
        WHERE collected_date BETWEEN '$start_date_time' AND '$end_date_time'
        AND tenantcode IS NOT NULL AND tenantcode != ''
        GROUP BY tenantcode";
        
    $transaction_days_apm_tenant_query = "
        SELECT tenantcode, COUNT(DISTINCT DATE(collected_date)) as transaction_days
        FROM collectedapm
        WHERE collected_date BETWEEN '$start_date_time' AND '$end_date_time'
        AND tenantcode IS NOT NULL AND tenantcode != ''
        GROUP BY tenantcode";
    
    // Get transaction days by space code for each branch
    $transaction_days_sanko_space_query = "
        SELECT spacecode, COUNT(DISTINCT DATE(collected_date)) as transaction_days
        FROM collected
        WHERE collected_date BETWEEN '$start_date_time' AND '$end_date_time'
        AND spacecode IS NOT NULL AND spacecode != ''
        GROUP BY spacecode";
        
    $transaction_days_nova_space_query = "
        SELECT spacecode, COUNT(DISTINCT DATE(collected_date)) as transaction_days
        FROM collectednova
        WHERE collected_date BETWEEN '$start_date_time' AND '$end_date_time'
        AND spacecode IS NOT NULL AND spacecode != ''
        GROUP BY spacecode";
        
    $transaction_days_apm_space_query = "
        SELECT spacecode, COUNT(DISTINCT DATE(collected_date)) as transaction_days
        FROM collectedapm
        WHERE collected_date BETWEEN '$start_date_time' AND '$end_date_time'
        AND spacecode IS NOT NULL AND spacecode != ''
        GROUP BY spacecode";
    
    // Execute tenant code queries
    $transaction_days_sanko_tenant = mysqli_query($conn, $transaction_days_sanko_tenant_query);
    $transaction_days_nova_tenant = mysqli_query($conn, $transaction_days_nova_tenant_query);
    $transaction_days_apm_tenant = mysqli_query($conn, $transaction_days_apm_tenant_query);
    
    // Execute space code queries
    $transaction_days_sanko_space = mysqli_query($conn, $transaction_days_sanko_space_query);
    $transaction_days_nova_space = mysqli_query($conn, $transaction_days_nova_space_query);
    $transaction_days_apm_space = mysqli_query($conn, $transaction_days_apm_space_query);
    
    // Check for errors
    if (!$transaction_days_sanko_tenant) {
        error_log("Error in transaction_days_sanko_tenant_query: " . mysqli_error($conn));
    }
    if (!$transaction_days_nova_tenant) {
        error_log("Error in transaction_days_nova_tenant_query: " . mysqli_error($conn));
    }
    if (!$transaction_days_apm_tenant) {
        error_log("Error in transaction_days_apm_tenant_query: " . mysqli_error($conn));
    }
    if (!$transaction_days_sanko_space) {
        error_log("Error in transaction_days_sanko_space_query: " . mysqli_error($conn));
    }
    if (!$transaction_days_nova_space) {
        error_log("Error in transaction_days_nova_space_query: " . mysqli_error($conn));
    }
    if (!$transaction_days_apm_space) {
        error_log("Error in transaction_days_apm_space_query: " . mysqli_error($conn));
    }
    
    // Build lookup arrays for tenant codes
    $transaction_days_sanko_tenant_lookup = [];
    $transaction_days_nova_tenant_lookup = [];
    $transaction_days_apm_tenant_lookup = [];
    
    if ($transaction_days_sanko_tenant) {
        while ($row = mysqli_fetch_assoc($transaction_days_sanko_tenant)) {
            $transaction_days_sanko_tenant_lookup[$row['tenantcode']] = intval($row['transaction_days']);
        }
    }
    
    if ($transaction_days_nova_tenant) {
        while ($row = mysqli_fetch_assoc($transaction_days_nova_tenant)) {
            $transaction_days_nova_tenant_lookup[$row['tenantcode']] = intval($row['transaction_days']);
        }
    }
    
    if ($transaction_days_apm_tenant) {
        while ($row = mysqli_fetch_assoc($transaction_days_apm_tenant)) {
            $transaction_days_apm_tenant_lookup[$row['tenantcode']] = intval($row['transaction_days']);
        }
    }
    
    // Build lookup arrays for space codes
    $transaction_days_sanko_space_lookup = [];
    $transaction_days_nova_space_lookup = [];
    $transaction_days_apm_space_lookup = [];
    
    if ($transaction_days_sanko_space) {
        while ($row = mysqli_fetch_assoc($transaction_days_sanko_space)) {
            if (!empty($row['spacecode'])) {
                $transaction_days_sanko_space_lookup[$row['spacecode']] = intval($row['transaction_days']);
            }
        }
    }
    
    if ($transaction_days_nova_space) {
        while ($row = mysqli_fetch_assoc($transaction_days_nova_space)) {
            if (!empty($row['spacecode'])) {
                $transaction_days_nova_space_lookup[$row['spacecode']] = intval($row['transaction_days']);
            }
        }
    }
    
    if ($transaction_days_apm_space) {
        while ($row = mysqli_fetch_assoc($transaction_days_apm_space)) {
            if (!empty($row['spacecode'])) {
                $transaction_days_apm_space_lookup[$row['spacecode']] = intval($row['transaction_days']);
            }
        }
    }
    
    // Process all tenants and build the missing transactions list
    $tenants_no_transactions_list = [];
    $tenants_no_transactions = 0;
    
    // Process Sanko tenants
    if ($all_tenants_sanko) {
        while ($tenant = mysqli_fetch_assoc($all_tenants_sanko)) {
            $tenantcode = $tenant['tenantcode'];
            $spacecode = $tenant['spacecode'];
            
            // First check by tenant code
            $days_with_transactions = 0;
            if (!empty($tenantcode) && isset($transaction_days_sanko_tenant_lookup[$tenantcode])) {
                $days_with_transactions = $transaction_days_sanko_tenant_lookup[$tenantcode];
            }
            // Then try by space code if needed
            elseif (!empty($spacecode) && isset($transaction_days_sanko_space_lookup[$spacecode])) {
                $days_with_transactions = $transaction_days_sanko_space_lookup[$spacecode];
            }
                
            if ($days_with_transactions < $days_in_range) {
                $missing_days_count = $days_in_range - $days_with_transactions;
                
                $tenant['days_with_transactions'] = $days_with_transactions;
                $tenant['total_days_in_range'] = $days_in_range;
                $tenant['missing_days_count'] = $missing_days_count;
                
                $tenants_no_transactions_list[] = $tenant;
                $tenants_no_transactions++;
            }
        }
    }
    
    // Process Nova tenants
    if ($all_tenants_nova) {
        while ($tenant = mysqli_fetch_assoc($all_tenants_nova)) {
            $tenantcode = $tenant['tenantcode'];
            $spacecode = $tenant['spacecode'];
            
            // First check by tenant code
            $days_with_transactions = 0;
            if (!empty($tenantcode) && isset($transaction_days_nova_tenant_lookup[$tenantcode])) {
                $days_with_transactions = $transaction_days_nova_tenant_lookup[$tenantcode];
            }
            // Then try by space code if needed
            elseif (!empty($spacecode) && isset($transaction_days_nova_space_lookup[$spacecode])) {
                $days_with_transactions = $transaction_days_nova_space_lookup[$spacecode];
            }
                
            if ($days_with_transactions < $days_in_range) {
                $missing_days_count = $days_in_range - $days_with_transactions;
                
                $tenant['days_with_transactions'] = $days_with_transactions;
                $tenant['total_days_in_range'] = $days_in_range;
                $tenant['missing_days_count'] = $missing_days_count;
                
                $tenants_no_transactions_list[] = $tenant;
                $tenants_no_transactions++;
            }
        }
    }
    
    // Process APM tenants
    if ($all_tenants_apm) {
        while ($tenant = mysqli_fetch_assoc($all_tenants_apm)) {
            $tenantcode = $tenant['tenantcode'];
            $spacecode = $tenant['spacecode'];
            
            // First check by tenant code
            $days_with_transactions = 0;
            if (!empty($tenantcode) && isset($transaction_days_apm_tenant_lookup[$tenantcode])) {
                $days_with_transactions = $transaction_days_apm_tenant_lookup[$tenantcode];
            }
            // Then try by space code if needed
            elseif (!empty($spacecode) && isset($transaction_days_apm_space_lookup[$spacecode])) {
                $days_with_transactions = $transaction_days_apm_space_lookup[$spacecode];
            }
                
            if ($days_with_transactions < $days_in_range) {
                $missing_days_count = $days_in_range - $days_with_transactions;
                
                $tenant['days_with_transactions'] = $days_with_transactions;
                $tenant['total_days_in_range'] = $days_in_range;
                $tenant['missing_days_count'] = $missing_days_count;
                
                $tenants_no_transactions_list[] = $tenant;
                $tenants_no_transactions++;
            }
        }
    }
    
    // Sort the list by branch and then by tenant name, with special handling for entries without tenant names
    usort($tenants_no_transactions_list, function($a, $b) {
        $branch_compare = strcmp($a['branch'], $b['branch']);
        if ($branch_compare !== 0) {
            return $branch_compare;
        }
        
        if (empty($a['tenantname']) && !empty($b['tenantname'])) return 1; // Empty tenant names go last
        if (!empty($a['tenantname']) && empty($b['tenantname'])) return -1;
        if (empty($a['tenantname']) && empty($b['tenantname'])) {
            return strcmp($a['spacecode'] ?? '', $b['spacecode'] ?? '');
        }
        return strcmp($a['tenantname'], $b['tenantname']);
    });
}

// Function to get missing dates for a tenant in the date range
function getMissingTransactionDates($conn, $table, $tenantcode, $branch, $start_date, $end_date) {
    // Create array of all dates in the range
    $period = new DatePeriod(
        new DateTime($start_date),
        new DateInterval('P1D'),
        (new DateTime($end_date))->modify('+1 day')
    );
    
    $all_dates = [];
    foreach ($period as $date) {
        $all_dates[] = $date->format('Y-m-d');
    }
    
    // Get dates where the tenant has transactions
    $query = "SELECT DISTINCT DATE(collected_date) as transaction_date 
              FROM $table 
              WHERE tenantcode = ? 
              AND branch = ? 
              AND collected_date BETWEEN ? AND ?
              ORDER BY transaction_date";
              
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare statement failed: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param("ssss", $tenantcode, $branch, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transaction_dates = [];
    while ($row = $result->fetch_assoc()) {
        $transaction_dates[] = $row['transaction_date'];
    }
    
    // Find the difference - dates without transactions
    return array_diff($all_dates, $transaction_dates);
}

// Fetch the total number of users from the users table (assuming this is not branch specific)
$query_users = "SELECT COUNT(*) AS total_users FROM users";
$result_users = mysqli_query($conn, $query_users);

$totalUsers = 0;
if ($result_users) {
    $row_users = mysqli_fetch_assoc($result_users);
    $totalUsers = $row_users['total_users'] ?? 0;
    mysqli_free_result($result_users);
} else {
     error_log("Query failed for total users: " . mysqli_error($conn));
}


// --- Data for Charts (Monthly and Yearly Collection including Charges) ---

// Prepare data for Monthly Chart (Last 12 months)
$monthly_labels = [];
$monthly_data = [];

// Get the last 12 months
$end_month = new DateTime();
$start_month = clone $end_month;
$start_month->modify('-11 months');

// Initialize the arrays with zeros for all months
for ($i = 0; $i < 12; $i++) {
    $monthly_labels[] = $start_month->format('M Y');
    $monthly_data[] = 0;
    $start_month->modify('+1 month');
}

// Query to get monthly totals
if ($branch) {
    $monthly_query = "SELECT 
        DATE_FORMAT(collected_date, '%Y-%m') as month,
        (paidrent + paidbal) as base_total,
        charges
        FROM $table 
        WHERE collected_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        AND branch = '$escaped_branch'";
} else {
    $monthly_query = "
        SELECT 
            DATE_FORMAT(collected_date, '%Y-%m') as month,
            (paidrent + paidbal) as base_total,
            charges
        FROM collected 
        WHERE collected_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        UNION ALL
        SELECT 
            DATE_FORMAT(collected_date, '%Y-%m') as month,
            (paidrent + paidbal) as base_total,
            charges
        FROM collectednova
        WHERE collected_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        UNION ALL
        SELECT 
            DATE_FORMAT(collected_date, '%Y-%m') as month,
            (paidrent + paidbal) as base_total,
            charges
        FROM collectedapm
        WHERE collected_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)";
}

$monthly_result = mysqli_query($conn, $monthly_query);

if ($monthly_result) {
    while ($row = mysqli_fetch_assoc($monthly_result)) {
        $month_date = new DateTime($row['month'] . '-01');
        $month_index = array_search($month_date->format('M Y'), $monthly_labels);
        if ($month_index !== false) {
            $total = (float)$row['base_total'] + extractCharges($row['charges']);
            $monthly_data[$month_index] += $total;
        }
    }
    mysqli_free_result($monthly_result);
}

// Prepare data for Yearly Chart (Last 5 years)
$yearly_labels = [];
$yearly_data = [];

// Get the last 5 years
$current_year = date('Y');
for ($i = 4; $i >= 0; $i--) {
    $year = $current_year - $i;
    $yearly_labels[] = $year;
    $yearly_data[] = 0;
}

// Query to get yearly totals
if ($branch) {
    $yearly_query = "SELECT 
        YEAR(collected_date) as year,
        (paidrent + paidbal) as base_total,
        charges
        FROM $table 
        WHERE YEAR(collected_date) >= YEAR(CURDATE()) - 4
        AND branch = '$escaped_branch'";
} else {
    $yearly_query = "
        SELECT 
            YEAR(collected_date) as year,
            (paidrent + paidbal) as base_total,
            charges
        FROM collected
        WHERE YEAR(collected_date) >= YEAR(CURDATE()) - 4
        UNION ALL
        SELECT 
            YEAR(collected_date) as year,
            (paidrent + paidbal) as base_total,
            charges
        FROM collectednova
        WHERE YEAR(collected_date) >= YEAR(CURDATE()) - 4
        UNION ALL
        SELECT 
            YEAR(collected_date) as year,
            (paidrent + paidbal) as base_total,
            charges
        FROM collectedapm
        WHERE YEAR(collected_date) >= YEAR(CURDATE()) - 4";
}

$yearly_result = mysqli_query($conn, $yearly_query);

if ($yearly_result) {
    while ($row = mysqli_fetch_assoc($yearly_result)) {
        $year_index = array_search($row['year'], $yearly_labels);
        if ($year_index !== false) {
            $total = (float)$row['base_total'] + extractCharges($row['charges']);
            $yearly_data[$year_index] += $total;
        }
    }
    mysqli_free_result($yearly_result);
}

mysqli_close($conn);

// Prepare chart data as JSON
$monthly_chart_data = json_encode(['labels' => $monthly_labels, 'data' => $monthly_data]);
$yearly_chart_data = json_encode(['labels' => $yearly_labels, 'data' => $yearly_data]);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/x-icon" href="../images/lc.png" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="LCLopez Resources Admin Dashboard">
    <title>Admin Dashboard</title>

    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/modern-dashboard.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/2.0.7/css/dataTables.bootstrap5.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/3.0.2/css/responsive.bootstrap5.css">
</head>

<body id="page-top">
        <!-- Modern Page Loader -->
    <div id="loader-overlay">
        <div class="modern-spinner">
            <div></div><div></div><div></div><div></div>
        </div>
        <div class="loader-text">Loading Dashboard...</div>
    </div>

    <div id="wrapper" class="blur-when-loading">

                <ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">

            <a class="sidebar-brand d-flex align-items-center" href="adminapm.php">
                <div class="sidebar-brand-icon">
                    <img src="../images/lc.png" alt="logo" class="brand-image">
                </div>
                <div class="sidebar-brand-text mx-3">
                    <span class="brand-text-main">LCLopez</span>
                    <span class="brand-text-sub">Resources</span>
                </div>
            </a>

            <hr class="sidebar-divider my-0">

            <li class="nav-item active">
                <a class="nav-link" href="adminapm.php">
                    <i class="fas fa-fw fa-crown dashboard-icon"></i>
                    <span class="dashboard-text">Dashboard</span>
                </a>
            </li>
            <hr class="sidebar-divider">

            <div class="sidebar-heading">
                Menu
            </div>

            <li class="nav-item">
                <a class="nav-link" href="collectionapm.php">
                    <i class="fas fa-fw fa-cash-register"></i>
                    <span>Collection</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="soaapm.php">
                    <i class="fas fa-fw fa-file-invoice"></i>
                    <span>SOA</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="tenantsapm.php">
                    <i class="fas fa-fw fa-user-tie"></i>
                    <span>Tenants</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="voidedtransactionsapm.php">
                    <i class="fas fa-fw fa-ban"></i>
                    <span>Voided Transactions</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="../index.php">
                    <i class="fas fa-fw fa-sign-in-alt"></i>
                    <span>Login Page</span>
                </a>
            </li>

            <hr class="sidebar-divider d-none d-md-block">
        </ul>
        <div id="content-wrapper" class="d-flex flex-column">

            <div id="content">

                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>

                    <ul class="navbar-nav ml-auto">

                        <!-- Auto Update Toggle -->
                        <li class="nav-item dropdown no-arrow mx-1 d-flex align-items-center">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="dashboardAutoUpdate" checked>
                                <label class="custom-control-label text-gray-600" for="dashboardAutoUpdate">Auto Update</label>
                                <span class="update-status ml-2"></span>
                            </div>
                        </li>

                        <div class="topbar-divider d-none d-sm-block"></div>

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
                                        <input type="checkbox" class="custom-control-input" id="autoUpdateToggle" checked>
                                        <label class="custom-control-label" for="autoUpdateToggle">Auto Update</label>
                                    </div>
                                </div>
                                <a class="dropdown-item text-center small text-gray-500" href="#"
                                    id="viewAllTransactions">View All Messages Today</a>
                            </div>
                        </li>
                         <div class="modal fade" id="allTransactionsModal" tabindex="-1" role="dialog"
                            aria-labelledby="allTransactionsModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="allTransactionsModalLabel">All Transactions Today
                                        </h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <div id="allTransactionsContent">
                                            Loading all transactions...
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                         </div>
                                </div>
                            </div>
                        </div>

                        <!-- Missing Transactions Modal -->
                        <div class="modal fade" id="missingTransactionsModal" tabindex="-1" role="dialog"
                            aria-labelledby="missingTransactionsModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="missingTransactionsModalLabel">
                                            Tenants With No Transactions
                                        </h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <small class="text-muted">
                                                Date Range: <?php echo date('M d, Y', strtotime($start_date)); ?> to
                                                <?php echo date('M d, Y', strtotime($end_date)); ?>
                                                <?php echo $branch ? " - " . htmlspecialchars($branch) : " - All Branches"; ?>
                                            </small>
                                        </div>
                                        <div id="missingTransactionsContent">
                                            <div class="alert alert-info">
                                                Loading tenants with missing transactions...
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                         </div>
                                </div>
                            </div>
                        </div>


                        <div class="topbar-divider d-none d-sm-block"></div>

                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small"><?php echo htmlspecialchars($lname); ?></span>
                                <img class="img-profile rounded-circle" 
                                    src="<?php echo isset($_SESSION['profile_photo']) && !empty($_SESSION['profile_photo']) ? 
                                        htmlspecialchars($_SESSION['profile_photo']) : 
                                        'img/undraw_profile.svg'; ?>">
                            </a>
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in"
                                aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#profileModal">
                                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Profile
                                </a>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#settingsModal">
                                    <i class="fas fa-cog fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Settings
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Logout
                                </a>
                            </div>
                        </li>

                    </ul>

                </nav>
                <div class="container-fluid dashboard-container">
    
    <!-- Alerts -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" style="border-radius:var(--radius-md);border:none;background:#ecfdf5;color:#065f46;" id="successAlert" role="alert">
            <i class="fas fa-check-circle mr-2" style="color:#10b981;"></i>
            <span style="font-weight:600;"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></span>
            <button type="button" class="close ml-auto" data-dismiss="alert" style="color:#065f46;"><i class="fas fa-times"></i></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" style="border-radius:var(--radius-md);border:none;background:#fff1f2;color:#9f1239;" id="errorAlert" role="alert">
            <i class="fas fa-exclamation-circle mr-2" style="color:#f43f5e;"></i>
            <span style="font-weight:600;"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></span>
            <button type="button" class="close ml-auto" data-dismiss="alert" style="color:#9f1239;"><i class="fas fa-times"></i></button>
        </div>
    <?php endif; ?>

    <!-- Top Section (Header & Filter) -->
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end mb-4">
        <!-- Header Section -->
        <div class="dash-header mb-3 mb-lg-0">
            <div>
                <h1>Dashboard Overview</h1>
                <p>Welcome back, <?php echo htmlspecialchars($lname); ?>. Here is your current data.</p>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" id="dashboardFilterForm" class="mb-0">
            <div class="filter-bar mb-0">
                <div class="filter-pill" style="cursor: pointer;" onclick="document.getElementById('start_date').showPicker && document.getElementById('start_date').showPicker();">
                    <i class="fas fa-calendar-alt"></i>
                    <div>
                        <div class="filter-label">Start Date</div>
                        <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>" onclick="event.stopPropagation(); this.showPicker && this.showPicker();">
                    </div>
                </div>
                
                <span class="filter-arrow d-none d-md-inline"><i class="fas fa-arrow-right"></i></span>

                <div class="filter-pill" style="cursor: pointer;" onclick="document.getElementById('end_date').showPicker && document.getElementById('end_date').showPicker();">
                    <i class="fas fa-calendar-check"></i>
                    <div>
                        <div class="filter-label">End Date</div>
                        <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>" onclick="event.stopPropagation(); this.showPicker && this.showPicker();">
                    </div>
                </div>

                <div class="filter-pill" style="cursor: pointer;" onclick="document.getElementById('branch').focus();">
                    <i class="fas fa-building"></i>
                    <div>
                        <div class="filter-label">Branch Filter</div>
                        <select disabled style="background-color: #e2e8f0; cursor: not-allowed; opacity: 0.7;">
                            <option value="APM" selected>APM</option>
                        </select>
                        <input type="hidden" id="branch" name="branch" value="APM">
                    </div>
                </div>

                <button type="submit" class="btn-filter-apply">
                    <i class="fas fa-search"></i> Apply
                </button>
            </div>
        </form>
    </div>

    <!-- Metric Cards -->
    <div class="metrics-grid">
        
        <!-- Total Collection -->
        <div class="metric-card blue-grad">
            <div class="metric-icon">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="metric-label">Total Collection</div>
            <div class="metric-value total-collection">
                <?php
                    $totalCollection = $totalPaidRent + $totalPaidBal + $totalCharges;
                    echo '₱' . number_format($totalCollection, 2);
                ?>
            </div>
        </div>

        <!-- Transactions -->
        <div class="metric-card purple-grad">
            <div class="metric-icon">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="metric-label">Transactions</div>
            <div class="metric-value total-transactions"><?php echo count($transactions); ?></div>
        </div>

        <!-- Total Users -->
        <div class="metric-card emerald-grad">
            <div class="metric-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="metric-label">Total Users</div>
            <div class="metric-value total-users"><?php echo $totalUsers; ?></div>
        </div>

        <!-- No Transactions -->
        <div class="metric-card rose-grad" style="cursor:pointer;" id="missingTransactionsCard">
            <div class="metric-icon">
                <i class="fas fa-user-times"></i>
            </div>
            <div class="metric-label">No Transactions</div>
            <div class="metric-value"><?php echo $tenants_no_transactions; ?></div>
            <button id="viewMissingTransactionsBtn" class="metric-action" title="View details">
                <i class="fas fa-arrow-right"></i>
            </button>
        </div>

    </div>

    <!-- Charts -->
    <div class="charts-grid">
        
        <!-- Monthly Chart -->
        <div class="chart-card">
            <div class="chart-card-header">
                <div class="chart-title-wrapper">
                    <div class="chart-icon-box bg-indigo-soft">
                        <i class="fas fa-chart-bar text-indigo"></i>
                    </div>
                    <div>
                        <h2>Monthly Collection</h2>
                        <div class="chart-badge">
                            <span class="status-dot bg-indigo"></span> Trailing 12 months
                        </div>
                    </div>
                </div>
                <button class="chart-menu"><i class="fas fa-ellipsis-h"></i></button>
            </div>
            <div class="chart-area">
                <canvas id="monthlyBarChart"></canvas>
            </div>
        </div>

        <!-- Yearly Chart -->
        <div class="chart-card">
            <div class="chart-card-header">
                <div class="chart-title-wrapper">
                    <div class="chart-icon-box bg-purple-soft">
                        <i class="fas fa-chart-line text-purple"></i>
                    </div>
                    <div>
                        <h2>Yearly Collection</h2>
                        <div class="chart-badge">
                            <span class="status-dot bg-purple"></span> Last 5 years overview
                        </div>
                    </div>
                </div>
                <button class="chart-menu"><i class="fas fa-ellipsis-h"></i></button>
            </div>
            <div class="chart-area">
                <canvas id="yearlyLineChart"></canvas>
            </div>
        </div>

    </div>

</div>
            <footer class="sticky-footer">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; IT Department <?php echo date('Y'); ?></span>
                    </div>
                </div>
            </footer>
            </div>
        </div>
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Ready to Leave?</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                    <a class="btn btn-primary" href="../logout.php">Logout</a> </div>
            </div>
        </div>
    </div>

    <!-- Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="profileModalLabel">Edit Profile</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <form action="update_profile.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="returnUrl" value="<?php echo htmlspecialchars(basename($_SERVER['PHP_SELF'])); ?>">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <div class="mb-4">
                                    <img id="profilePreview" class="img-profile rounded-circle" 
                                        src="<?php echo isset($_SESSION['profile_photo']) && !empty($_SESSION['profile_photo']) ? 
                                            htmlspecialchars($_SESSION['profile_photo']) : 
                                            'img/undraw_profile.svg'; ?>" 
                                        style="width: 200px; height: 200px; object-fit: cover;">
                                    <div class="mt-3">
                                        <label class="btn btn-primary btn-sm">
                                            <i class="fas fa-upload"></i> Upload Photo
                                            <input type="file" name="profile_photo" id="profilePhotoInput" 
                                                accept="image/*" style="display: none;" onchange="previewImage(this)">
                                        </label>
                                    </div>
                                </div>
                                <div class="text-muted small">
                                    <p>Recommended: Square image<br>Max size: 5MB</p>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" id="basic-tab" data-toggle="tab" href="#basic" role="tab">
                                            <i class="fas fa-user"></i> Basic Info
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="security-tab" data-toggle="tab" href="#security" role="tab">
                                            <i class="fas fa-lock"></i> Security
                                        </a>
                                    </li>
                                </ul>
                                <div class="tab-content mt-3" id="profileTabContent">
                                    <div class="tab-pane fade show active" id="basic" role="tabpanel">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>First Name</label>
                                                    <input type="text" class="form-control" name="fname" 
                                                        value="<?php echo isset($_SESSION['fname']) ? htmlspecialchars($_SESSION['fname']) : ''; ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Last Name</label>
                                                    <input type="text" class="form-control" name="lname" 
                                                        value="<?php echo isset($_SESSION['lname']) ? htmlspecialchars($_SESSION['lname']) : ''; ?>" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label>Username</label>
                                            <input type="text" class="form-control" name="username" 
                                                value="<?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : ''; ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Email</label>
                                            <input type="email" class="form-control" name="email" 
                                                value="<?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : ''; ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Branch</label>
                                            <input type="text" class="form-control" name="branch" 
                                                value="<?php echo isset($_SESSION['branch']) ? htmlspecialchars($_SESSION['branch']) : ''; ?>" required>
                                        </div>
                                    </div>
                                    <div class="tab-pane fade" id="security" role="tabpanel">
                                        <div class="form-group">
                                            <label>Current Password</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" name="current_password">
                                                <div class="input-group-append">
                                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword(this)">
                                                        <i class="fas fa-eye-slash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label>New Password</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" name="new_password">
                                                <div class="input-group-append">
                                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword(this)">
                                                        <i class="fas fa-eye-slash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <small class="form-text text-muted">
                                                Password must be at least 8 characters long and contain uppercase, lowercase, and numbers
                                            </small>
                                        </div>
                                        <div class="form-group">
                                            <label>Confirm New Password</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" name="confirm_password">
                                                <div class="input-group-append">
                                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword(this)">
                                                        <i class="fas fa-eye-slash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div class="modal fade" id="settingsModal" tabindex="-1" role="dialog" aria-labelledby="settingsModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="settingsModalLabel">Settings</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="settings-sections">
                        <!-- Appearance Section -->
                        <h6 class="settings-section-title mb-3">
                            <i class="fas fa-paint-brush mr-2"></i> Appearance
                        </h6>
                        <div class="card mb-4">
                            <div class="card-body">
                                <!-- Dark Mode Toggle -->
                                <div class="form-group mb-3">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="darkModeToggle">
                                        <label class="custom-control-label" for="darkModeToggle">Dark Mode</label>
                                    </div>
                                    <small class="form-text text-muted">Switch between light and dark theme</small>
                                </div>
                                
                                <!-- Font Size Setting -->
                                <div class="form-group mb-3">
                                    <label for="fontSize">Font Size</label>
                                    <select class="form-control" id="fontSize">
                                        <option value="small">Small</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="large">Large</option>
                                    </select>
                                    <small class="form-text text-muted">Change the text size across the application</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Notification Settings -->
                        <h6 class="settings-section-title mb-3">
                            <i class="fas fa-bell mr-2"></i> Notifications
                        </h6>
                        <div class="card">
                            <div class="card-body">
                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="enableNotifications" checked>
                                        <label class="custom-control-label" for="enableNotifications">Enable Notifications</label>
                                    </div>
                                    <small class="form-text text-muted">Receive alerts about new transactions</small>
                                </div>
                                
                                <div class="form-group mb-0">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="notificationSound" checked>
                                        <label class="custom-control-label" for="notificationSound">Notification Sound</label>
                                    </div>
                                    <small class="form-text text-muted">Play sound for new notifications</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveSettings">Save Settings</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add this before the closing body tag -->
    <script>
    // Add window load event handler to control page loader
    window.addEventListener('load', function() {
        // Hide the page loader when everything is fully loaded
        setTimeout(function() {
            hidePageLoader();
        }, 500);
    });
    
    function previewImage(input) {
        if (input.files && input.files[0]) {
            // Check file size
            if (input.files[0].size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB');
                input.value = '';
                return;
            }

            // Check file type
            const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!validTypes.includes(input.files[0].type)) {
                alert('Only JPG, PNG & GIF files are allowed');
                input.value = '';
                return;
            }

            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('profilePreview').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function togglePassword(button) {
        const input = button.closest('.input-group').querySelector('input');
        const icon = button.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    }
    </script>

    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>

    <script src="js/sb-admin-2.min.js"></script>

    <script src="vendor/chart.js/Chart.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.7/js/dataTables.js"></script>
    <script src="https://cdn.datatables.net/2.0.7/js/dataTables.bootstrap5.js"></script>
    <script src="https://cdn.datatables.net/responsive/3.0.2/js/dataTables.responsive.js"></script>
    <script src="https://cdn.datatables.net/responsive/3.0.2/js/responsive.bootstrap5.js"></script>


    <script>

        // --- Helper Function for Number Formatting ---
        // This function is defined outside $(document).ready but within the script block
        // so it's globally accessible by chart callbacks and potentially other places.
        function number_format(number, decimals, dec_point, thousands_sep) {
            // * example: number_format(1234.56, 2, ',', ' ');
            // * return: '1,234.56' // Corrected example output for typical usage
            number = (number + '').replace(/[^0-9+\-Ee.]/g, ''); // Clean input
            var n = !isFinite(+number) ? 0 : +number,
                prec = !isFinite(+decimals) ? 2 : Math.abs(decimals), // Default to 2 decimals
                sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
                dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
                s = '',
                toFixedFix = function(n, prec) {
                    // Fix for IE parseFloat(0.55).toFixed(0) = 0;
                    var k = Math.pow(10, prec);
                    return '' + (Math.round(n * k) / k).toFixed(prec);
                };
            s = (toFixedFix(n, prec)).split('.');
            if (s[0].length > 3) {
                s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
            }
            if ((s[1] || '').length < prec) {
                s[1] = s[1] || '';
                s[1] += new Array(prec - s[1].length + 1).join('0');
            }
            return s.join(dec);
        }

        // Pass PHP data to JavaScript as JSON strings
        const monthlyChartData = <?php echo json_encode(['labels' => $monthly_labels, 'data' => $monthly_data]); ?>;
        const yearlyChartData = <?php echo json_encode(['labels' => $yearly_labels, 'data' => $yearly_data]); ?>;

        $(document).ready(function() {
            // Hide the page loader when the page is fully loaded
            setTimeout(function() {
                $('.page-loader').fadeOut(500, function() {
                    $(this).css({
                        'visibility': 'hidden',
                        'opacity': '0'
                    });
                });
            }, 500);
            
            // Hide card loaders after content is loaded
            setTimeout(function() {
                hideCardLoaders();
            }, 1200);
            
            // Adjust page loader background if dark mode is enabled
            if (localStorage.getItem('darkModeEnabled') === 'true') {
                $('.page-loader').addClass('dark-mode');
            }
            
            // Auto-close success and error alerts after 4 seconds
            if ($('#successAlert').length > 0) {
                setTimeout(function() {
                    $('#successAlert').removeClass('animate__fadeIn').addClass('animate__fadeOut');
                    setTimeout(function() {
                        $('#successAlert').alert('close');
                    }, 500);
                }, 4000); // 4 seconds
            }
            
            if ($('#errorAlert').length > 0) {
                setTimeout(function() {
                    $('#errorAlert').removeClass('animate__fadeIn').addClass('animate__fadeOut');
                    setTimeout(function() {
                        $('#errorAlert').alert('close');
                    }, 500);
                }, 4000); // 4 seconds
            }
            
            // Change the color of the "Tenants Without Transactions" card to red
            const tenantsCard = $(".text-xs:contains('Tenants Without Transactions')").closest('.card');
            if (tenantsCard.length > 0) {
                tenantsCard.removeClass('border-left-info').addClass('border-left-danger');
                tenantsCard.find('.icon-circle').removeClass('bg-info').addClass('bg-danger');
                tenantsCard.find('.text-info').removeClass('text-info').addClass('text-danger');
                tenantsCard.find('.btn-info').removeClass('btn-info').addClass('btn-danger');
            }
            
            // Monthly Bar Chart
            var ctxMonthly = document.getElementById("monthlyBarChart");
            if (ctxMonthly) {
                var monthlyGradient = ctxMonthly.getContext('2d').createLinearGradient(0, 0, 0, 400);
                monthlyGradient.addColorStop(0, 'rgba(99, 102, 241, 0.85)');
                monthlyGradient.addColorStop(1, 'rgba(139, 92, 246, 0.2)');

                new Chart(ctxMonthly, {
                    type: 'bar',
                    data: {
                        labels: monthlyChartData.labels,
                        datasets: [{
                            label: "Collection (₱)",
                            backgroundColor: monthlyGradient,
                            hoverBackgroundColor: "rgba(99, 102, 241, 1)",
                            borderColor: "rgba(99, 102, 241, 1)",
                            borderWidth: 0,
                            data: monthlyChartData.data,
                            borderRadius: 8,
                            maxBarThickness: 32,
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        layout: { padding: { left: 5, right: 10, top: 10, bottom: 5 } },
                        legend: { display: false },
                        scales: {
                            yAxes: [{
                                gridLines: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
                                ticks: {
                                    beginAtZero: true,
                                    fontColor: '#94a3b8',
                                    fontSize: 11,
                                    fontFamily: 'Inter',
                                    padding: 8,
                                    callback: function(value) {
                                        if (value >= 1000000) return '₱' + (value/1000000).toFixed(1) + 'M';
                                        if (value >= 1000) return '₱' + (value/1000).toFixed(0) + 'K';
                                        return '₱' + value;
                                    }
                                }
                            }],
                            xAxes: [{
                                gridLines: { display: false, drawBorder: false },
                                ticks: {
                                    fontColor: '#94a3b8',
                                    fontSize: 10,
                                    fontFamily: 'Inter',
                                    maxRotation: 45,
                                    minRotation: 0
                                }
                            }]
                        },
                        tooltips: {
                            backgroundColor: '#1e293b',
                            titleFontColor: '#fff',
                            bodyFontColor: '#e2e8f0',
                            titleFontSize: 12,
                            bodyFontSize: 12,
                            titleFontFamily: 'Inter',
                            bodyFontFamily: 'Inter',
                            borderWidth: 0,
                            cornerRadius: 8,
                            xPadding: 12,
                            yPadding: 10,
                            displayColors: false,
                            callbacks: {
                                label: function(tooltipItem) {
                                    return '₱' + number_format(tooltipItem.yLabel);
                                }
                            }
                        }
                    }
                });
            }

            // Yearly Line Chart
            var ctxYearly = document.getElementById("yearlyLineChart");
            if (ctxYearly) {
                var yearlyGradient = ctxYearly.getContext('2d').createLinearGradient(0, 0, 0, 400);
                yearlyGradient.addColorStop(0, 'rgba(99, 102, 241, 0.25)');
                yearlyGradient.addColorStop(1, 'rgba(139, 92, 246, 0.0)');

                new Chart(ctxYearly, {
                    type: 'line',
                    data: {
                        labels: yearlyChartData.labels,
                        datasets: [{
                            label: "Collection (₱)",
                            lineTension: 0.4,
                            backgroundColor: yearlyGradient,
                            borderColor: "rgba(99, 102, 241, 1)",
                            pointRadius: 5,
                            pointBackgroundColor: "#ffffff",
                            pointBorderColor: "rgba(99, 102, 241, 1)",
                            pointHoverRadius: 7,
                            pointHoverBackgroundColor: "rgba(99, 102, 241, 1)",
                            pointHoverBorderColor: "#ffffff",
                            pointHitRadius: 10,
                            pointBorderWidth: 3,
                            data: yearlyChartData.data
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        layout: { padding: { left: 5, right: 10, top: 10, bottom: 5 } },
                        legend: { display: false },
                        scales: {
                            yAxes: [{
                                gridLines: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
                                ticks: {
                                    beginAtZero: true,
                                    fontColor: '#94a3b8',
                                    fontSize: 11,
                                    fontFamily: 'Inter',
                                    padding: 8,
                                    callback: function(value) {
                                        if (value >= 1000000) return '₱' + (value/1000000).toFixed(1) + 'M';
                                        if (value >= 1000) return '₱' + (value/1000).toFixed(0) + 'K';
                                        return '₱' + value;
                                    }
                                }
                            }],
                            xAxes: [{
                                gridLines: { display: false, drawBorder: false },
                                ticks: {
                                    fontColor: '#94a3b8',
                                    fontSize: 11,
                                    fontFamily: 'Inter'
                                }
                            }]
                        },
                        tooltips: {
                            backgroundColor: '#1e293b',
                            titleFontColor: '#fff',
                            bodyFontColor: '#e2e8f0',
                            titleFontSize: 12,
                            bodyFontSize: 12,
                            titleFontFamily: 'Inter',
                            bodyFontFamily: 'Inter',
                            borderWidth: 0,
                            cornerRadius: 8,
                            xPadding: 12,
                            yPadding: 10,
                            displayColors: false,
                            callbacks: {
                                label: function(tooltipItem) {
                                    return '₱' + number_format(tooltipItem.yLabel);
                                }
                            }
                        }
                    }
                });
            }

            // Replace the DataTables initialization
            var dataTable = $('#dataTable').DataTable({
                responsive: true,
                pageLength: 10,
                order: [[3, "desc"]], // Sort by date column by default
                columns: [
                    { data: 'spacecode' },
                    { data: 'transaction_number' },
                    { data: 'collector' },
                    { 
                        data: 'collected_date',
                        type: 'date',
                        render: function(data, type, row) {
                            if (type === 'display') {
                                if (!data) return '';
                                // Convert to ISO string first
                                const date = new Date(data);
                                if (isNaN(date.getTime())) {
                                    return `<span class="text-danger">${data}</span>`;
                                }
                                return date.toLocaleDateString('en-US', {
                                    month: 'short',
                                    day: '2-digit',
                                    year: 'numeric'
                                });
                            }
                            return data;
                        }
                    },
                    { data: 'branch' },
                    { data: 'tenantname' },
                    { 
                        data: 'paidrent',
                        render: function(data) {
                            return number_format(parseFloat(data) || 0, 2);
                        }
                    },
                    { 
                        data: 'paidbal',
                        render: function(data) {
                            return number_format(parseFloat(data) || 0, 2);
                        }
                    }
                ],
                language: {
                    search: '<i class="fas fa-search"></i>',
                    searchPlaceholder: "Search records...",
                    lengthMenu: "_MENU_ records per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ records",
                    infoEmpty: "Showing 0 to 0 of 0 records",
                    infoFiltered: "(filtered from _MAX_ total records)",
                    zeroRecords: "<div class='text-center p-4'><i class='fas fa-box-open fa-3x text-gray-300 mb-3'></i><br>No matching records found</div>",
                    paginate: {
                        first: '<i class="fas fa-angle-double-left"></i>',
                        previous: '<i class="fas fa-angle-left"></i>',
                        next: '<i class="fas fa-angle-right"></i>',
                        last: '<i class="fas fa-angle-double-right"></i>'
                    }
                },
                drawCallback: function() {
                    $('.dataTables_paginate > .pagination').addClass('pagination-sm');
                },
                initComplete: function() {
                    var api = this.api();
                    $('.dataTables_filter input')
                        .off('.DT')
                        .on('input.DT', function() {
                            var searchValue = this.value;
                            clearTimeout(window.searchTimeout);
                            window.searchTimeout = setTimeout(function() {
                                api.search(searchValue).draw();
                            }, 300);
                        });
                }
            });

            // Add loading animation for AJAX requests
            $(document).ajaxStart(function() {
                $('body').addClass('loading');
                // Don't show page loader to prevent full screen block on every AJAX call
            }).ajaxStop(function() {
                $('body').removeClass('loading');
            });

            // Enhance alert center animations
            $('#alertsDropdown').on('show.bs.dropdown', function () {
                $('#alertMessages .dropdown-item').each(function(index) {
                    $(this).css({
                        'opacity': 0,
                        'transform': 'translateY(-20px)'
                    }).animate({
                        'opacity': 1,
                        'transform': 'translateY(0)'
                    }, {
                        duration: 200,
                        easing: 'easeOutQuad',
                        delay: index * 100
                    });
                });
            });

            // Add smooth scrolling
            $('.scroll-to-top').on('click', function(e) {
                e.preventDefault();
                $('html, body').animate({
                    scrollTop: 0
                }, 500, 'easeInOutQuad');
            });

            // Enhance sidebar toggling animation
            $("#sidebarToggle, #sidebarToggleTop").on('click', function() {
                $("body").toggleClass("sidebar-toggled");
                $(".sidebar").toggleClass("toggled");
                if ($(".sidebar").hasClass("toggled")) {
                    $('.sidebar .collapse').collapse('hide');
                }
            });

            // Add card refresh functionality
            $('.card-header').each(function() {
                var $card = $(this).closest('.card');
                var $refreshBtn = $('<button>')
                    .addClass('btn btn-link btn-sm float-right')
                    .html('<i class="fas fa-sync-alt"></i>')
                    .on('click', function() {
                        var $icon = $(this).find('i');
                        $icon.addClass('fa-spin');
                        $card.addClass('loading');
                        
                        setTimeout(function() {
                            $icon.removeClass('fa-spin');
                            $card.removeClass('loading');
                        }, 1000);
                    });
                
                $(this).append($refreshBtn);
            });

            // Add hover effects to cards
            $('.card').hover(
                function() { $(this).addClass('shadow-lg'); },
                function() { $(this).removeClass('shadow-lg'); }
            );

            // Initialize tooltips and popovers
            $('[data-toggle="tooltip"]').tooltip();
            $('[data-toggle="popover"]').popover();

            // Add animation to alerts
            $('.alert').addClass('fade show');

            // Function to update notifications
            let notificationInterval;
            
            function startAutoUpdate() {
                updateNotifications(); // Initial load
                notificationInterval = setInterval(updateNotifications, 30000); // Update every 30 seconds
            }

            function stopAutoUpdate() {
                if (notificationInterval) {
                    clearInterval(notificationInterval);
                    notificationInterval = null;
                }
            }

            // Handle auto-update toggle
            $('#autoUpdateToggle').change(function() {
                if ($(this).is(':checked')) {
                    startAutoUpdate();
                    localStorage.setItem('autoUpdateEnabled', 'true');
                } else {
                    stopAutoUpdate();
                    localStorage.setItem('autoUpdateEnabled', 'false');
                }
            });

            // Check saved preference on page load
            const autoUpdateEnabled = localStorage.getItem('autoUpdateEnabled') !== 'false';
            $('#autoUpdateToggle').prop('checked', autoUpdateEnabled);
            if (autoUpdateEnabled) {
                startAutoUpdate();
            }
            
            // Add manual refresh button
            $('.dropdown-header').append(
                $('<button>')
                    .addClass('btn btn-link btn-sm float-right text-white p-0')
                    
                    .attr('title', 'Refresh Notifications')
                    .css('fontSize', '0.8rem')
                    .click(function(e) {
                        e.preventDefault();
                        $(this).children('i').addClass('fa-spin');
                        updateNotifications();
                        setTimeout(() => {
                            $(this).children('i').removeClass('fa-spin');
                        }, 1000);
                    })
            );

            // Dashboard Auto Update Functions
            let dashboardInterval;
            let lastUpdateTime;
            const UPDATE_INTERVAL = 59000; // 59 seconds

            function updateDashboardData() {
                lastUpdateTime = Date.now();
                
                // Show loaders for all cards
                $('.card-loader').css({
                    'visibility': 'visible',
                    'opacity': '1'
                }).fadeIn(300);

                // Get current filter values
                const start_date = $('#start_date').val();
                const end_date = $('#end_date').val();
                const branch = $('#branch').val();

                // Show loading state in the table
                if ($.fn.DataTable.isDataTable('#dataTable')) {
                    $('#dataTable').DataTable().clear().draw();
                    $('#dataTable_wrapper').addClass('loading');
                }

                $.ajax({
                    url: 'get_dashboard_data.php',
                    type: 'GET',
                    data: {
                        start_date: start_date,
                        end_date: end_date,
                        branch: branch
                    },
                    success: function(response) {
                        try {
                            const data = typeof response === 'string' ? JSON.parse(response) : response;
                            
                            // Validate and process dates before updating
                            if (Array.isArray(data.transactions)) {
                                data.transactions = data.transactions.map(transaction => {
                                    if (transaction.collected_date && !isValidDate(transaction.collected_date)) {
                                        console.warn('Invalid date detected:', transaction.collected_date);
                                        // Try to fix the date format
                                        const parts = transaction.collected_date.split(/[- :]/);
                                        if (parts.length >= 3) {
                                            transaction.collected_date = `${parts[0]}-${parts[1]}-${parts[2]}`;
                                        }
                                    }
                                    return transaction;
                                });
                            }
                            
                            // Update summary cards
                            updateSummaryCards(data);
                            
                            // Update DataTable
                            updateDataTable(data.transactions);
                            
                            showAlert('success', 'Dashboard updated successfully');
                        } catch (e) {
                            console.error('Error processing response:', e);
                            showAlert('danger', 'Error updating dashboard');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Failed to update dashboard:', error);
                        showAlert('danger', 'Failed to update dashboard');
                    },
                    complete: function() {
                        // Hide loaders after data is loaded
                        setTimeout(function() {
                            hideCardLoaders();
                        $('#dataTable_wrapper').removeClass('loading');
                        }, 300);
                    }
                });
            }

            function updateSummaryCards(data) {
                // Update summary cards with animation
                animateValue('.total-collection', data.totalCollection);
                animateValue('.total-transactions', data.totalTransactions);
                animateValue('.total-tenants', data.totalTenants);
                animateValue('.total-users', data.totalUsers);
            }

            function animateValue(selector, newValue) {
                const element = $(selector);
                const oldValue = parseFloat(element.text().replace(/[^0-9.-]+/g, "")) || 0;
                $({value: oldValue}).animate({value: newValue}, {
                    duration: 500,
                    easing: 'swing',
                    step: function() {
                        if (selector === '.total-collection') {
                            element.text('₱' + number_format(this.value, 2));
                        } else {
                            element.text(Math.floor(this.value));
                        }
                    }
                });
            }

            function updateDataTable(transactions) {
                if ($.fn.DataTable.isDataTable('#dataTable')) {
                    const table = $('#dataTable').DataTable();
                    table.clear();
                    
                    // Process the transactions
                    const processedTransactions = transactions.map(processTransactionData);
                    
                    // Add the data
                    table.rows.add(processedTransactions).draw();
                }
            }

            function showAlert(type, message) {
                const alertHtml = `
                    <div class="alert alert-${type} alert-dismissible fade show position-fixed animate__animated animate__fadeInRight" 
                         style="top: 20px; right: 20px; z-index: 9999;" role="alert">
                        <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle mr-2"></i>
                        ${message}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                `;
                const alert = $(alertHtml);
                $('body').append(alert);
                
                // Close alert after timeout with animation
                setTimeout(() => {
                    alert.removeClass('animate__fadeInRight').addClass('animate__fadeOutRight');
                    setTimeout(() => alert.alert('close'), 500);
                }, 4000);
            }

            // Update countdown display
            function updateCountdown() {
                if ($('#dashboardAutoUpdate').is(':checked') && lastUpdateTime) {
                    const now = Date.now();
                    const timeSinceLastUpdate = now - lastUpdateTime;
                    const timeLeft = Math.ceil((UPDATE_INTERVAL - timeSinceLastUpdate) / 1000);
                    
                    if (timeLeft > 0) {
                        $('.update-status').html(`
                            <span class="badge badge-info" style="font-size: 0.75rem; background-color: var(--accent-indigo); color: white;">
                                Next update in ${timeLeft}s
                            </span>
                        `);
                    } else {
                        $('.update-status').html(`
                            <span class="badge badge-warning">
                                Updating...
                            </span>
                        `);
                    }
                } else {
                    $('.update-status').html(`
                        <span class="badge badge-secondary">
                            Auto-update paused
                        </span>
                    `);
                }
            }

            // Initialize countdown timer
            setInterval(updateCountdown, 1000);

            // Start auto-update on page load if enabled
            $(document).ready(function() {
                const dashboardAutoUpdateEnabled = localStorage.getItem('dashboardAutoUpdateEnabled') !== 'false';
                $('#dashboardAutoUpdate').prop('checked', dashboardAutoUpdateEnabled);
                
                if (dashboardAutoUpdateEnabled) {
                    startDashboardAutoUpdate();
                }

                // Handle toggle changes
                $('#dashboardAutoUpdate').change(function() {
                    if ($(this).is(':checked')) {
                        startDashboardAutoUpdate();
                        localStorage.setItem('dashboardAutoUpdateEnabled', 'true');
                    } else {
                        stopDashboardAutoUpdate();
                        localStorage.setItem('dashboardAutoUpdateEnabled', 'false');
                    }
                });
            });

            function startDashboardAutoUpdate() {
                updateDashboardData(); // Initial update
                if (dashboardInterval) {
                    clearInterval(dashboardInterval);
                }
                dashboardInterval = setInterval(updateDashboardData, UPDATE_INTERVAL);
            }

            function stopDashboardAutoUpdate() {
                if (dashboardInterval) {
                    clearInterval(dashboardInterval);
                    dashboardInterval = null;
                }
                lastUpdateTime = null;
            }

            // Initialize dashboard auto-update based on saved preference
            const dashboardAutoUpdateEnabled = localStorage.getItem('dashboardAutoUpdateEnabled') !== 'false';
            $('#dashboardAutoUpdate').prop('checked', dashboardAutoUpdateEnabled);
            
            if (dashboardAutoUpdateEnabled) {
                startDashboardAutoUpdate();
            }

            // Handle toggle changes
            $('#dashboardAutoUpdate').change(function() {
                if ($(this).is(':checked')) {
                    startDashboardAutoUpdate();
                    localStorage.setItem('dashboardAutoUpdateEnabled', 'true');
                } else {
                    stopDashboardAutoUpdate();
                    localStorage.setItem('dashboardAutoUpdateEnabled', 'false');
                }
            });

            // Add countdown timer
            let countdownSpan = $('.update-status');
            let countdownInterval;

            function updateCountdown() {
                if ($('#dashboardAutoUpdate').is(':checked') && lastUpdateTime) {
                    const now = Date.now();
                    const timeSinceLastUpdate = now - lastUpdateTime;
                    const timeLeft = Math.ceil((UPDATE_INTERVAL - timeSinceLastUpdate) / 1000);
                    
                    if (timeLeft > 0) {
                        countdownSpan.html(`
                            <span class="badge badge-info" style="font-size: 0.75rem; background-color: var(--accent-indigo); color: white;">
                                Next update in ${timeLeft}s
                            </span>
                        `);
                    } else {
                        countdownSpan.html(`
                            <span class="badge badge-warning">
                                Updating...
                            </span>
                        `);
                    }
                } else {
                    countdownSpan.empty();
                }
            }

            // Update countdown every second
            if (countdownInterval) {
                clearInterval(countdownInterval);
            }
            countdownInterval = setInterval(updateCountdown, 1000);

            // Handle manual refresh of notifications
            $('#refreshNotifications').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation(); // Prevent dropdown from closing
                $(this).children('i').addClass('fa-spin');
                
                // Force refresh notifications
                updateNotifications();
                
                // Remove spin class after 1 second
                setTimeout(() => {
                    $(this).children('i').removeClass('fa-spin');
                }, 1000);
            });

            // Show loaders when filter form is submitted
            $('#dashboardFilterForm').on('submit', function() {
                // Show the page loader
                showPageLoader();
                // Show card loaders
                showCardLoaders();
            });
        });

        // Function to format currency
        function formatCurrency(amount) {
            return '₱' + number_format(amount, 2);
        }

        // Update the updateNotifications function to handle the refresh icon
        function updateNotifications() {
            // Show loader in notifications area
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
            
            $.ajax({
                url: 'get_notifications.php',
                type: 'GET',
                dataType: 'json',
                cache: false,
                data: { 
                    _: new Date().getTime(), // Add cache-busting parameter
                    limit: 'all' // Request all notifications
                },
                success: function(response) {
                    // Update notification counter
                    $('#unreadCounter').text(response.total);

                    // Clear existing notifications
                    $('#alertMessages').empty();

                    // Add new notifications
                    if (response.notifications.length > 0) {
                        // Sort notifications with newest first (if they aren't already)
                        response.notifications.sort((a, b) => {
                            return new Date(b.time) - new Date(a.time);
                        });
                        
                        // Show all notifications
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

                        // Update the view all transactions modal content with all notifications
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

        // Handle click on "View All Messages Today"
        $('#viewAllTransactions').click(function(e) {
            e.preventDefault();
            $('#allTransactionsModal').modal('show');
        });

        // Add this JavaScript function to help with date validation
        function isValidDate(dateString) {
            const timestamp = Date.parse(dateString);
            return !isNaN(timestamp);
        }

        // Update the data processing function
        function processTransactionData(transaction) {
            const processed = {...transaction};
            
            // Handle date
            if (processed.collected_date) {
                const date = new Date(processed.collected_date);
                if (!isNaN(date.getTime())) {
                    processed.collected_date = date.toISOString().split('T')[0];
                }
            }
            
            // Handle numeric values
            processed.paidrent = parseFloat(processed.paidrent) || 0;
            processed.paidbal = parseFloat(processed.paidbal) || 0;
            
            return processed;
        }

        // Settings management
        $(document).ready(function() {
            // Load settings from localStorage
            loadSettings();
            
            // Save settings to localStorage
            $('#saveSettings').on('click', function() {
                saveSettings();
                $('#settingsModal').modal('hide');
                showAlert('success', 'Settings saved successfully');
            });
            
            // Dark mode toggle
            $('#darkModeToggle').on('change', function() {
                toggleDarkMode($(this).is(':checked'));
            });
            
            // Font size change
            $('#fontSize').on('change', function() {
                changeFontSize($(this).val());
            });
            
            // Notifications settings
            $('#enableNotifications').on('change', function() {
                localStorage.setItem('enableNotifications', $(this).is(':checked'));
            });
            
            $('#notificationSound').on('change', function() {
                localStorage.setItem('notificationSound', $(this).is(':checked'));
            });
        });
        
        // Load all settings from localStorage
        function loadSettings() {
            // Dark mode
            const darkModeEnabled = localStorage.getItem('darkModeEnabled') === 'true';
            $('#darkModeToggle').prop('checked', darkModeEnabled);
            toggleDarkMode(darkModeEnabled);
            
            // Font size
            const fontSize = localStorage.getItem('fontSize') || 'medium';
            $('#fontSize').val(fontSize);
            changeFontSize(fontSize);
            
            // Notifications
            const enableNotifications = localStorage.getItem('enableNotifications') !== 'false';
            $('#enableNotifications').prop('checked', enableNotifications);
            
            const notificationSound = localStorage.getItem('notificationSound') !== 'false';
            $('#notificationSound').prop('checked', notificationSound);
        }
        
        // Save all settings to localStorage
        function saveSettings() {
            // Dark mode
            localStorage.setItem('darkModeEnabled', $('#darkModeToggle').is(':checked'));
            
            // Font size
            localStorage.setItem('fontSize', $('#fontSize').val());
            
            // Notifications
            localStorage.setItem('enableNotifications', $('#enableNotifications').is(':checked'));
            localStorage.setItem('notificationSound', $('#notificationSound').is(':checked'));
            
            // Apply settings
            toggleDarkMode($('#darkModeToggle').is(':checked'));
            changeFontSize($('#fontSize').val());
        }
        
        // Toggle dark mode
        function toggleDarkMode(enabled) {
            if (enabled) {
                $('body').addClass('dark-mode');
            } else {
                $('body').removeClass('dark-mode');
            }
        }
        
        // Change font size
        function changeFontSize(size) {
            // Remove any existing font size classes
            $('body').removeClass('font-small font-medium font-large');
            
            // Add the selected font size class
            $('body').addClass('font-' + size);
        }
        
        // Start dashboard auto-update with the selected interval
        function startDashboardAutoUpdate() {
            updateDashboardData(); // Initial update
            
            if (dashboardInterval) {
                clearInterval(dashboardInterval);
            }
            
            const interval = parseInt(localStorage.getItem('dashboardUpdateInterval')) || UPDATE_INTERVAL;
            dashboardInterval = setInterval(updateDashboardData, interval);
            
            lastUpdateTime = Date.now();
        }

        // Function to truncate text to a specific length
        function truncateText(text, maxLength) {
            if (text.length <= maxLength) return text;
            return text.substring(0, maxLength) + '...';
        }

        // Initialize the missing transactions modal
        $(document).ready(function() {
            // Handle View Details button click for tenants with no transactions
            $('#viewMissingTransactionsBtn').on('click', function() {
                // Show the modal
                $('#missingTransactionsModal').modal('show');
                
                // Populate the modal with tenant data
                populateMissingTransactionsModal();
            });
        });

        // Function to populate the missing transactions modal
        function populateMissingTransactionsModal() {
            // Get the PHP array of missing transaction tenants and convert to JavaScript
            const tenantsWithNoTransactions = <?php echo json_encode($tenants_no_transactions_list); ?>;
            const dateRange = "<?php echo date('M d, Y', strtotime($start_date)) . ' to ' . date('M d, Y', strtotime($end_date)); ?>";
            const branch = "<?php echo $branch ? htmlspecialchars($branch) : 'All Branches'; ?>";
            
            // Create content for the modal
            let content = '';
            
            if (tenantsWithNoTransactions.length > 0) {
                content += `
                    <div class="table-responsive" style="overflow-x: auto;">
                        <style>
                            #missingTransactionsTable {
                                width: 100%;
                                table-layout: fixed;
                            }
                            #missingTransactionsTable th, 
                            #missingTransactionsTable td {
                                white-space: normal;
                                word-wrap: break-word;
                            }
                            #missingTransactionsTable th:nth-child(1),
                            #missingTransactionsTable td:nth-child(1) {
                                width: 18%;
                            }
                            #missingTransactionsTable th:nth-child(2),
                            #missingTransactionsTable td:nth-child(2) {
                                width: 32%;
                            }
                            #missingTransactionsTable th:nth-child(3),
                            #missingTransactionsTable td:nth-child(3) {
                                width: 18%;
                            }
                            #missingTransactionsTable th:nth-child(4),
                            #missingTransactionsTable td:nth-child(4) {
                                width: 15%;
                            }
                            #missingTransactionsTable th:nth-child(5),
                            #missingTransactionsTable td:nth-child(5) {
                                width: 17%;
                            }
                            </style>
                        <table class="table table-bordered table-hover" id="missingTransactionsTable">
                            <thead class="bg-danger text-white">
                                <tr>
                                    <th>Tenant Code</th>
                                        <th>Tenant Name</th>
                                    <th>Space Code</th>
                                    <th>Branch</th>
                                    <th>Actions</th>
                                    </tr>
                                </thead>
                            <tbody>`;
                
                tenantsWithNoTransactions.forEach(function(tenant) {
                    content += `
                        <tr>
                            <td class="align-middle">${tenant.tenantcode || 'N/A'}</td>
                            <td class="align-middle">
                                ${tenant.tenantname ? 
                                    `<strong>${tenant.tenantname}</strong>` : 
                                    `<strong class="text-danger">No Tenant Name</strong>`
                                }
                            </td>
                            <td class="align-middle">
                                <strong class="${!tenant.tenantname ? 'text-danger font-weight-bold' : ''}">
                                    ${tenant.spacecode || 'N/A'}
                                </strong>
                            </td>
                            <td class="align-middle">${tenant.branch || 'N/A'}</td>
                            <td class="align-middle">
                                <div class="d-flex flex-column mb-2">
                                    <div class="badge badge-danger mb-1">Missing: ${tenant.missing_days_count || 0} days</div>
                                    <div class="badge badge-success">Has: ${tenant.days_with_transactions || 0} / ${tenant.total_days_in_range || 0} days</div>
                                </div>
                                <button class="btn btn-danger btn-block btn-sm view-missing-days" 
                                    data-tenant-code="${tenant.tenantcode || ''}" 
                                    data-space-code="${tenant.spacecode || ''}"
                                    data-tenant-name="${tenant.tenantname || 'Space: ' + tenant.spacecode}"
                                    data-branch="${tenant.branch}">
                                    <i class="fas fa-calendar-times"></i> View Missing Days
                                                </button>
                                            </td>
                        </tr>`;
                });
                
                content += `
                                </tbody>
                            </table>
                    </div>`;
            } else {
                content = `<div class="alert alert-danger">No tenants without transactions found for the selected period.</div>`;
            }
            
            // Update the modal content
            $('#missingTransactionsContent').html(content);
            
            // Initialize DataTable for the missing transactions table
            if (tenantsWithNoTransactions.length > 0) {
                if ($.fn.DataTable.isDataTable('#missingTransactionsTable')) {
                    $('#missingTransactionsTable').DataTable().destroy();
                }
                
                $('#missingTransactionsTable').DataTable({
                    responsive: true,
                    pageLength: 10,
                    order: [[1, "asc"]], // Sort by tenant name by default
                    autoWidth: false, // Disable automatic width calculation
                    columnDefs: [
                        { width: "18%", targets: 0 }, // Tenant Code
                        { width: "32%", targets: 1 }, // Tenant Name
                        { width: "18%", targets: 2 }, // Space Code
                        { width: "15%", targets: 3 }, // Branch
                        { width: "17%", targets: 4 }  // Actions
                    ],
                    language: {
                        search: '<i class="fas fa-search"></i>',
                        searchPlaceholder: "Search tenants...",
                        lengthMenu: "_MENU_ tenants per page",
                        info: "Showing _START_ to _END_ of _TOTAL_ tenants",
                        infoEmpty: "Showing 0 to 0 of 0 tenants",
                        infoFiltered: "(filtered from _MAX_ total tenants)",
                        zeroRecords: "<div class='text-center p-4'><i class='fas fa-user-slash fa-3x text-gray-300 mb-3'></i><br>No matching tenants found</div>"
                    }
                });
                
                // Add click event handler for "View Missing Days" buttons
                $('#missingTransactionsTable').on('click', '.view-missing-days', function() {
                    const tenantCode = $(this).data('tenant-code');
                    const tenantName = $(this).data('tenant-name');
                    const branch = $(this).data('branch');
                    
                    // Show loading state
                    $(this).html('<i class="fas fa-spinner fa-spin"></i> Loading...');
                    
                    // Make AJAX request to get missing days
                    $.ajax({
                        url: 'get_missing_days.php',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            tenant_code: tenantCode,
                            space_code: $(this).data('space-code'),
                            branch: branch,
                            start_date: "<?php echo $start_date; ?>",
                            end_date: "<?php echo $end_date; ?>"
                        },
                        success: function(response) {
                            // Create and show modal with missing days
                            showMissingDaysModal(
                                tenantName, 
                                tenantCode, 
                                branch, 
                                response.missing_days,
                                response.transaction_dates,
                                response.total_transactions,
                                response.total_records_check,
                                response.missing_days_with_info,
                                response.transaction_dates_with_day,
                                response.day_of_week_stats
                            );
                            
                            // Reset button state
                            $('.view-missing-days[data-tenant-code="' + tenantCode + '"]').html('<i class="fas fa-calendar-times"></i> View Missing Days');
                        },
                        error: function() {
                            // Reset button state
                            $('.view-missing-days[data-tenant-code="' + tenantCode + '"]').html('<i class="fas fa-calendar-times"></i> View Missing Days');
                            
                            // Show error message
                            alert('Failed to load missing days. Please try again.');
                        }
                    });
                });
            }
        }
        
        // Function to show modal with missing days
        function showMissingDaysModal(tenantName, tenantCode, branch, missingDays, transactionDates, totalTransactions, totalRecordsCheck, missingDaysWithInfo, transactionDatesWithDay, dayOfWeekStats) {
            // Apply grayscale to the background modal
            $('#missingTransactionsModal').addClass('grayscale');
            
            // Add a second modal-backdrop for better visual separation
            $('body').append('<div class="modal-backdrop show secondary-modal"></div>');
            
            // Create modal HTML
            const modalHTML = `
                <div class="modal fade" id="missingDaysModal" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title">
                                    <i class="fas fa-calendar-times"></i> Missing Transaction Days
                                </h5>
                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                        </div>
                            <div class="modal-body">
                                <div class="alert alert-info mb-3">
                                    <div class="row">
                                        <div class="col-md-6">
                                    <strong>Tenant:</strong> ${tenantName} (${tenantCode})<br>
                                    <strong>Branch:</strong> ${branch}<br>
                                    <strong>Date Range:</strong> <?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?>
                                        </div>
                                        <div class="col-md-6 text-md-right">
                                            <strong>Total Days in Range:</strong> ${missingDaysWithInfo ? missingDaysWithInfo.length + transactionDates.length : missingDays.length + transactionDates.length}<br>
                                            <strong>Days with Transactions:</strong> ${totalTransactions || 0}<br>
                                            <strong>Days without Transactions:</strong> ${missingDays.length}<br>
                                            <strong>Total Records:</strong> ${totalRecordsCheck || 0}
                                        </div>
                                    </div>
                                </div>
                                
                                <ul class="nav nav-tabs" id="missingDaysTabs" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" id="missing-tab" data-toggle="tab" href="#missing" role="tab">
                                            <i class="fas fa-calendar-times"></i> Missing Days (${missingDays.length})
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="present-tab" data-toggle="tab" href="#present" role="tab">
                                            <i class="fas fa-calendar-check"></i> Days with Transactions (${transactionDates ? transactionDates.length : 0})
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="stats-tab" data-toggle="tab" href="#stats" role="tab">
                                            <i class="fas fa-chart-bar"></i> Day of Week Analysis
                                        </a>
                                    </li>
                                </ul>
                                
                                <div class="tab-content mt-3" id="missingDaysTabContent">
                                    <div class="tab-pane fade show active" id="missing" role="tabpanel">
                                <div class="missing-days-container">
                                    ${missingDays.length > 0 ? `
                                        <div class="alert alert-danger">
                                            <strong>Total days without transactions: ${missingDays.length}</strong>
                                        </div>
                                        <div class="row">
                                                    ${missingDaysWithInfo ? 
                                                        missingDaysWithInfo.map(day => `
                                                            <div class="col-md-3 mb-2">
                                                                <span class="badge badge-danger p-2 w-100 d-inline-block">
                                                                    <i class="fas fa-calendar-day"></i> ${formatDate(day.date)} <span class="small">(${day.day_name})</span>
                                                                </span>
                                                            </div>
                                                        `).join('') :
                                                        missingDays.map(day => `
                                                <div class="col-md-3 mb-2">
                                                    <span class="badge badge-danger p-2 w-100 d-inline-block">
                                                        <i class="fas fa-calendar-day"></i> ${formatDate(day)}
                                                    </span>
                                                </div>
                                                        `).join('')
                                                    }
                                        </div>
                                    ` : `
                                        <div class="alert alert-success">
                                            <i class="fas fa-check-circle"></i> No missing transaction days found.
                                        </div>
                                    `}
                                        </div>
                                    </div>
                                    
                                    <div class="tab-pane fade" id="present" role="tabpanel">
                                        <div class="transaction-days-container">
                                            ${transactionDates && transactionDates.length > 0 ? `
                                                <div class="alert alert-success">
                                                    <strong>Total days with transactions: ${transactionDates.length}</strong>
                                                </div>
                                                <div class="row">
                                                    ${transactionDatesWithDay ? 
                                                        transactionDatesWithDay.map(day => `
                                                            <div class="col-md-3 mb-2">
                                                                <span class="badge badge-success p-2 w-100 d-inline-block">
                                                                    <i class="fas fa-calendar-check"></i> ${formatDate(day.date)} <span class="small">(${day.day_name})</span>
                                                                </span>
                                                            </div>
                                                        `).join('') :
                                                        transactionDates.map(day => `
                                                            <div class="col-md-3 mb-2">
                                                                <span class="badge badge-success p-2 w-100 d-inline-block">
                                                                    <i class="fas fa-calendar-check"></i> ${formatDate(day)}
                                                                </span>
                                                            </div>
                                                        `).join('')
                                                    }
                                                </div>
                                            ` : `
                                                <div class="alert alert-warning">
                                                    <i class="fas fa-exclamation-triangle"></i> No transaction days found.
                                                </div>
                                            `}
                                        </div>
                                    </div>
                                    
                                    <div class="tab-pane fade" id="stats" role="tabpanel">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="card shadow mb-4">
                                                    <div class="card-header py-3">
                                                        <h6 class="m-0 font-weight-bold text-primary">Transactions by Day of Week</h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <canvas id="dayOfWeekChart" style="height: 300px;"></canvas>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="card shadow mb-4">
                                                    <div class="card-header py-3">
                                                        <h6 class="m-0 font-weight-bold text-primary">Day of Week Summary</h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="table-responsive">
                                                            <table class="table table-bordered" width="100%">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Day of Week</th>
                                                                        <th>Transaction Count</th>
                                                                        <th>Status</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    ${dayOfWeekStats ? dayOfWeekStats.labels.map((day, index) => `
                                                                        <tr>
                                                                            <td>${day}</td>
                                                                            <td>${dayOfWeekStats.counts[index]}</td>
                                                                            <td>
                                                                                ${dayOfWeekStats.counts[index] > 0 ? 
                                                                                    '<span class="badge badge-success">Has Transactions</span>' : 
                                                                                    '<span class="badge badge-danger">No Transactions</span>'}
                                                                            </td>
                                                                        </tr>
                                                                    `).join('') : ''}
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                </div>
                <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
            `;
            
            // Remove any existing modal
            $('#missingDaysModal').remove();
            
            // Append new modal to body
            $('body').append(modalHTML);
            
            // Show the modal
            $('#missingDaysModal').modal('show');
            
            // Remove grayscale effect when modal is closed
            $('#missingDaysModal').on('hidden.bs.modal', function () {
                $('#missingTransactionsModal').removeClass('grayscale');
                $('.modal-backdrop.secondary-modal').remove();
            });
            
            // If we have day of week stats, create the chart
            if (dayOfWeekStats) {
                setTimeout(() => {
                    const ctx = document.getElementById('dayOfWeekChart');
                    if (ctx) {
                        new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: dayOfWeekStats.labels,
                                datasets: [{
                                    label: 'Transaction Count',
                                    data: dayOfWeekStats.counts,
                                    backgroundColor: 'rgba(78, 115, 223, 0.8)',
                                    borderColor: 'rgba(78, 115, 223, 1)',
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                maintainAspectRatio: false,
                                scales: {
                                    yAxes: [{
                                        ticks: {
                                            beginAtZero: true,
                                            stepSize: 1
                                        }
                                    }]
                                }
                            }
                        });
                    }
                }, 500);
            }
        }
        
        // Function to format date for display
        function formatDate(dateStr) {
            const date = new Date(dateStr);
            const options = { month: 'short', day: 'numeric', year: 'numeric' };
            return date.toLocaleDateString('en-US', options);
        }

        // Function to show card loaders with staggered animation
        function showCardLoaders() {
            // Get all card loaders
            const loaders = $('.card-loader');
            
            // Staggered appearance for a more elegant effect
            loaders.each(function(index) {
                const loader = $(this);
                setTimeout(function() {
                    loader.css({
                        'visibility': 'visible',
                        'opacity': '1',
                        'display': 'flex'
                    }).addClass('active');
                }, index * 100); // Stagger by 100ms per card
            });
        }
        
        // Function to hide card loaders with staggered animation
        function hideCardLoaders() {
            // Get all card loaders
            const loaders = $('.card-loader');
            
            // Staggered disappearance
            loaders.each(function(index) {
                const loader = $(this);
                setTimeout(function() {
                    loader.fadeOut(400, function() {
                        loader.css({
                            'visibility': 'hidden',
                            'opacity': '0'
                        }).removeClass('active');
                    });
                }, index * 80); // Stagger by 80ms per card
            });
        }

        // Function to show the page loader
        function showPageLoader() {
            // Reset progress bar animation
            $('.loader-progress-bar').css('animation', 'none');
            // Force reflow to reset the animation
            $('.loader-progress-bar')[0].offsetHeight;
            // Start animation again
            $('.loader-progress-bar').css('animation', 'progress 3s ease-in-out forwards');
            
            // Show the loader
            $('.page-loader').css({
                'visibility': 'visible',
                'opacity': '1',
                'display': 'flex'
            });
        }
        
        // Function to hide the page loader
        function hidePageLoader() {
            // Complete the progress bar animation immediately
            $('.loader-progress-bar').css('width', '100%');
            
            // Wait a moment to show the completed progress bar before hiding
            setTimeout(function() {
                $('.page-loader').fadeOut(500, function() {
                    $(this).css({
                        'visibility': 'hidden',
                        'opacity': '0'
                    });
                    // Reset progress bar for next time
                    setTimeout(() => {
                        $('.loader-progress-bar').css({
                            'width': '0%',
                            'animation': 'none'
                        });
                    }, 500);
                });
            }, 200);
        }

        // Loader logic
        function hideLoader() {
            const loader = document.getElementById('loader-overlay');
            if (loader) loader.style.display = 'none';
            document.getElementById('wrapper').classList.remove('blur-when-loading','hide-when-loading');
        }
        function showLoader() {
            const loader = document.getElementById('loader-overlay');
            if (loader) loader.style.display = 'flex';
            document.getElementById('wrapper').classList.add('blur-when-loading');
        }
        // Show loader immediately on page load
        document.addEventListener('DOMContentLoaded', function() {
            showLoader();
        });
        window.addEventListener('load', function() {
            setTimeout(hideLoader, 400); // Hide loader after everything is loaded
        });
    </script>

</body>

</html>
<?php ob_end_flush(); // End output buffering and flush output ?>
