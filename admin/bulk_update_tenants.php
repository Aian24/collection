<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_start();
include '../config.php';

// Include PhpSpreadsheet for Excel file support
require_once '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

session_start();

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Check if file was uploaded
if (!isset($_FILES['bulkUpdateCsvFile']) || $_FILES['bulkUpdateCsvFile']['error'] !== UPLOAD_ERR_OK) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit();
}

// Check if branch is provided
if (!isset($_POST['bulkUpdateBranch']) || empty($_POST['bulkUpdateBranch'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Branch not selected']);
    exit();
}

$branch = $_POST['bulkUpdateBranch'];

// Validate branch against allowed tables
$tables_query = "SHOW TABLES";
$tables_result = $conn->query($tables_query);
$allowedTables = [];

while ($table = $tables_result->fetch_array()) {
    $table_name = $table[0];
    if (!str_starts_with($table_name, 'collected') && 
        (in_array($table_name, ['apm', 'nova', 'sanko']) || 
        $conn->query("SHOW TABLES LIKE 'collected$table_name'")->num_rows > 0)) {
        $allowedTables[] = $table_name;
    }
}

if (!in_array($branch, $allowedTables)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid branch selected']);
    exit();
}

// Process the uploaded file
$file = $_FILES['bulkUpdateCsvFile'];
$fileName = $file['name'];
$fileSize = $file['size'];
$fileTmpName = $file['tmp_name'];

// Get file extension to determine file type
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// Validate file type - now supports CSV, XLS, and XLSX
$allowedExtensions = ['csv', 'xls', 'xlsx'];
if (!in_array($fileExtension, $allowedExtensions)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Only CSV, XLS, and XLSX files are allowed']);
    exit();
}

// Validate file size (50MB max - increased for large datasets)
if ($fileSize > 50 * 1024 * 1024) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'File size exceeds 50MB limit']);
    exit();
}

// Function to read data from file (CSV or Excel)
function readFileData($fileTmpName, $fileExtension) {
    $data = [];
    
    if ($fileExtension === 'csv') {
        // Read CSV file
        $handle = fopen($fileTmpName, 'r');
        if ($handle === false) {
            throw new Exception('Unable to read CSV file');
        }
        
        while (($line = fgetcsv($handle)) !== false) {
            $data[] = $line;
        }
        fclose($handle);
    } else {
        // Read Excel file using PhpSpreadsheet
        try {
            $reader = IOFactory::createReaderForFile($fileTmpName);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            $spreadsheet = $reader->load($fileTmpName);
            $worksheet = $spreadsheet->getActiveSheet();
            
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
            
            for ($row = 1; $row <= $highestRow; $row++) {
                $rowData = [];
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    $cell = $worksheet->getCell($columnLetter . $row);
                    
                    // Get the formatted value first, then calculated value as fallback
                    $cellValue = $cell->getFormattedValue();
                    
                    // If it's a numeric cell and formatted value looks like scientific notation, get calculated value
                    if ($cell->getDataType() === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC) {
                        $calculatedValue = $cell->getCalculatedValue();
                        // Check if the formatted value contains scientific notation patterns or malformed numbers
                        if (is_numeric($calculatedValue) && 
                            (strpos($cellValue, 'E') !== false || 
                             strpos($cellValue, 'e') !== false || 
                             preg_match('/\d+\-\d+$/', $cellValue) || // Pattern like "123-45" at end
                             !is_numeric($cellValue))) {
                            $cellValue = number_format($calculatedValue, 2, '.', '');
                        }
                    }
                    
                    // Convert null to empty string for consistency
                    $rowData[] = $cellValue === null ? '' : (string)$cellValue;
                }
                $data[] = $rowData;
            }
        } catch (ReaderException $e) {
            throw new Exception('Error reading Excel file: ' . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception('Error processing Excel file: ' . $e->getMessage());
        }
    }
    
    return $data;
}

// Read file data
try {
    $fileData = readFileData($fileTmpName, $fileExtension);
    
    // Log successful file reading for debugging
    error_log("Successfully read " . count($fileData) . " rows from $fileExtension file: $fileName");
} catch (Exception $e) {
    // Log the error for debugging
    error_log("Error reading file $fileName ($fileExtension): " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error reading file: ' . $e->getMessage()]);
    exit();
}

// Process the data
$lineNumber = 0;
$updated = 0;
$inserted = 0;
$skipped = 0;
$errors = [];
$headerChecked = false; // Flag to track if we've checked for headers
$processedTenants = []; // Track processed tenants to avoid duplicates

foreach ($fileData as $line) {
    $lineNumber++;
    
    // Skip empty lines or lines with only whitespace
    if (empty(array_filter($line, function($field) { return trim($field) !== ''; }))) {
        continue;
    }
    
    // Check for header lines only once and only if it's clearly a header
    if (!$headerChecked && $lineNumber === 1) {
        $firstColumn = strtolower(trim($line[0] ?? ''));
        if ($firstColumn === 'tenant name' || $firstColumn === 'tenantname' || $firstColumn === 'name') {
            $headerChecked = true;
            continue; // Skip the header line
        }
        // If it's not clearly a header, we'll process it as data
        $headerChecked = true;
    }
    
    // Validate minimum columns (at least 6 required: tenantname, tenantcode, spacecode, daily, rentbal, runningbal)
    if (count($line) < 6) {
        $errors[] = "Line $lineNumber: Insufficient columns (minimum 6 required, found " . count($line) . ")";
        $skipped++;
        continue;
    }
    
    // Extract data from line and clean quotes
    $tenantname = trim(str_replace(['"', '"'], '', $line[0] ?? ''));
    $tenantcode = trim(str_replace(['"', '"'], '', $line[1] ?? ''));
    $spacecode = trim(str_replace(['"', '"'], '', $line[2] ?? ''));
    $daily = trim(str_replace(['"', '"'], '', $line[3] ?? ''));
    $rentbal = trim(str_replace(['"', '"'], '', $line[4] ?? ''));
    $runningbal = trim(str_replace(['"', '"'], '', $line[5] ?? ''));
    $started_date = trim(str_replace(['"', '"'], '', $line[6] ?? ''));
    
    // Handle cases where columns might be shifted
    // Check if tenantname looks like a number (which would indicate column shift)
    if ((is_numeric($tenantname) || (is_numeric(substr($tenantname, 0, 1)) && !empty($tenantname))) && !empty($tenantname)) {
        // Likely column shift, try to realign
        // Move all values one position to the right
        $started_date = $runningbal;
        $runningbal = $rentbal;
        $rentbal = $daily;
        $daily = $spacecode;
        $spacecode = $tenantcode;
        $tenantcode = $tenantname;
        $tenantname = ''; // This would be the actual tenant name which is missing
        
        // Try to get tenant name from the next available column if it exists
        if (isset($line[7])) {
            $tenantname = trim(str_replace(['"', '"'], '', $line[7] ?? ''));
        }
    }
    
    // Handle completely empty lines that might have commas (like ',,')
    $nonEmptyFields = array_filter($line, function($field) { return trim($field) !== ''; });
    if (empty($nonEmptyFields)) {
        // Completely empty line with just commas, skip it
        continue;
    }
    
    // Normalize tenant name by removing extra spaces and standardizing format
    $tenantname = preg_replace('/\s+/', ' ', trim($tenantname)); // Replace multiple spaces with single space
    
    // Check if this is essentially an empty row (all important fields are empty)
    $hasAnyData = !empty($tenantname) || !empty($tenantcode) || !empty($spacecode) || !empty($daily) || !empty($rentbal) || !empty($runningbal);
    
    if (!$hasAnyData) {
        // Skip completely empty rows silently
        continue;
    }
    
    // Validate required field - space code is now the primary required field
    if (empty($spacecode)) {
        // Check if this row has numeric data but missing space code - likely a data-only row
        $hasNumericData = !empty($daily) || !empty($rentbal) || !empty($runningbal);
        
        if ($hasNumericData) {
            // This appears to be a row with financial data but no space code
            // Provide detailed information and suggest it might be a subtotal or summary row
            $linePreview = implode(',', array_slice($line, 0, min(7, count($line))));
            $errors[] = "Line $lineNumber: Space code is required (3rd column cannot be empty). This appears to be a data row with values but no space code - it might be a subtotal/summary row that should be removed from the Excel file. Line preview: '$linePreview'";
            $skipped++;
            continue;
        } else {
            // Provide more detailed information about the line
            $linePreview = implode(',', array_slice($line, 0, min(7, count($line)))); // Show first 7 columns or fewer if not available
            $errors[] = "Line $lineNumber: Space code is required (3rd column cannot be empty). Line preview: '$linePreview'";
            $skipped++;
            continue;
        }
    }
    
    // Check for duplicate processing - if we've already processed this tenant in this batch
    $tenantKey = strtolower($spacecode);
    if (isset($processedTenants[$tenantKey])) {
        $errors[] = "Line $lineNumber: Duplicate space code entry '$spacecode' (already processed at line " . $processedTenants[$tenantKey] . "). Skipping duplicate.";
        $skipped++;
        continue;
    }
    $processedTenants[$tenantKey] = $lineNumber;
    
    // Clean numeric values by removing commas and other formatting characters
    // But treat spacecode as a string to preserve formatting (no numeric cleaning)
    if (!empty($daily)) {
        // Handle scientific notation and clean formatting
        $daily = trim($daily);
        if (is_numeric($daily)) {
            $daily = number_format((float)$daily, 2, '.', '');
        } else {
            $daily = preg_replace('/[^\d.-]/', '', $daily);
        }
    }
    if (!empty($rentbal)) {
        // Handle scientific notation and clean formatting
        $rentbal = trim($rentbal);
        if (is_numeric($rentbal)) {
            $rentbal = number_format((float)$rentbal, 2, '.', '');
        } else {
            $rentbal = preg_replace('/[^\d.-]/', '', $rentbal);
        }
    }
    if (!empty($runningbal)) {
        // Handle scientific notation and clean formatting
        $runningbal = trim($runningbal);
        if (is_numeric($runningbal)) {
            $runningbal = number_format((float)$runningbal, 2, '.', '');
        } else {
            $runningbal = preg_replace('/[^\d.-]/', '', $runningbal);
        }
    }
    // Spacecode should remain as-is to preserve formatting like "1.00E+16"
    // Only normalize whitespace
    if (!empty($spacecode)) {
        $spacecode = trim(preg_replace('/\s+/', ' ', $spacecode)); // Normalize space code whitespace only
    }
    
    // Validate numeric fields - but allow empty values
    // Only validate if the field is not empty
    if (!empty($daily)) {
        // Try to convert scientific notation or other formats
        $dailyTest = filter_var($daily, FILTER_VALIDATE_FLOAT);
        if ($dailyTest === false && !is_numeric($daily)) {
            $errors[] = "Line $lineNumber: Daily rent must be numeric (value: '$daily')";
            $skipped++;
            continue;
        }
    }
    
    if (!empty($rentbal)) {
        // Try to convert scientific notation or other formats
        $rentbalTest = filter_var($rentbal, FILTER_VALIDATE_FLOAT);
        if ($rentbalTest === false && !is_numeric($rentbal)) {
            $errors[] = "Line $lineNumber: Rent balance must be numeric (value: '$rentbal')";
            $skipped++;
            continue;
        }
    }
    
    if (!empty($runningbal)) {
        // Try to convert scientific notation or other formats
        $runningbalTest = filter_var($runningbal, FILTER_VALIDATE_FLOAT);
        if ($runningbalTest === false && !is_numeric($runningbal)) {
            $errors[] = "Line $lineNumber: Arrear balance must be numeric (value: '$runningbal')";
            $skipped++;
            continue;
        }
    }
    
    // Validate date format if provided with more flexible parsing
    if (!empty($started_date)) {
        // Try multiple date formats
        $dateFormats = ['Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d'];
        $date = null;
        
        foreach ($dateFormats as $format) {
            $date = DateTime::createFromFormat($format, $started_date);
            if ($date && $date->format($format) === $started_date) {
                // Valid date found, convert to standard format
                $started_date = $date->format('Y-m-d');
                break;
            }
            $date = null;
        }
        
        // If no valid date format found, check if it's a numeric value (which might be a balance)
        if (!$date && is_numeric($started_date)) {
            // This is likely a balance value, not a date
            // Move it to the appropriate balance field and clear the date
            if (empty($runningbal)) {
                $runningbal = $started_date;
            } else if (empty($rentbal)) {
                $rentbal = $started_date;
            }
            $started_date = '';
        } else if (!$date) {
            $errors[] = "Line $lineNumber: Invalid date format (value: '$started_date'). Use YYYY-MM-DD format.";
            $skipped++;
            continue;
        }
    }
    
    // Check if tenant exists by spacecode - this is now the only matching method
    $tableName = $conn->real_escape_string($branch);
    $spacecode_escaped = $conn->real_escape_string($spacecode);
    
    $existingTenant = null;
    $matchedBy = 'spacecode'; // Always match by spacecode
    
    // Find by spacecode - this is the primary and only matching method
    $checkQuery = "SELECT * FROM $tableName WHERE spacecode = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("s", $spacecode_escaped);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $existingTenant = $result->fetch_assoc();
    $checkStmt->close();
    
    if ($existingTenant) {
        // Tenant exists - update only changed fields
        $updateFields = [];
        $updateValues = [];
        $updateTypes = "";
        
        // Only update tenant name if provided (even if empty, as per new requirement)
        // But only add to update if there's actually a difference
        if ($tenantname !== $existingTenant['tenantname']) {
            $updateFields[] = "tenantname = ?";
            $updateValues[] = $tenantname;
            $updateTypes .= "s";
        }
        
        // Only update tenant code if provided and different
        if (!empty($tenantcode) && $tenantcode !== $existingTenant['tenantcode']) {
            $updateFields[] = "tenantcode = ?";
            $updateValues[] = $tenantcode;
            $updateTypes .= "s";
        }
        
        // Update numeric fields only if provided (including zero values)
        // Columns 4, 5, 6 (Daily Rent, Rent Balance, Arrear Balance)
        if ($daily !== '') {
            // Convert both values to float for proper comparison
            $dailyValue = is_numeric($daily) ? (float)$daily : 0;
            $existingDaily = is_numeric($existingTenant['daily']) ? (float)$existingTenant['daily'] : 0;
            
            // Compare with small tolerance for floating point precision
            if (abs($dailyValue - $existingDaily) > 0.001) {
                $updateFields[] = "daily = ?";
                $updateValues[] = $daily;
                $updateTypes .= "s";
            }
        }
        
        if ($rentbal !== '') {
            // Convert both values to float for proper comparison
            $rentbalValue = is_numeric($rentbal) ? (float)$rentbal : 0;
            $existingRentbal = is_numeric($existingTenant['rentbal']) ? (float)$existingTenant['rentbal'] : 0;
            
            // Compare with small tolerance for floating point precision
            if (abs($rentbalValue - $existingRentbal) > 0.001) {
                $updateFields[] = "rentbal = ?";
                $updateValues[] = $rentbal;
                $updateTypes .= "s";
            }
        }
        
        if ($runningbal !== '') {
            // Convert both values to float for proper comparison
            $runningbalValue = is_numeric($runningbal) ? (float)$runningbal : 0;
            $existingRunningbal = is_numeric($existingTenant['runningbal']) ? (float)$existingTenant['runningbal'] : 0;
            
            // Compare with small tolerance for floating point precision
            if (abs($runningbalValue - $existingRunningbal) > 0.001) {
                $updateFields[] = "runningbal = ?";
                $updateValues[] = $runningbal;
                $updateTypes .= "s";
            }
        }
        
        // Only update started_date if provided and different
        if (!empty($started_date) && $started_date !== $existingTenant['started_date']) {
            $updateFields[] = "started_date = ?";
            $updateValues[] = $started_date;
            $updateTypes .= "s";
        }
        
        // Only update if there are changes
        if (!empty($updateFields)) {
            $updateQuery = "UPDATE $tableName SET " . implode(", ", $updateFields) . " WHERE id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            
            // Add id to values array for WHERE clause
            $updateValues[] = $existingTenant['id'];
            $updateTypes .= "i";
            
            $updateStmt->bind_param($updateTypes, ...$updateValues);
            
            if ($updateStmt->execute()) {
                $updated++;
                // Log successful update for debugging
                error_log("Updated tenant at line $lineNumber - Space code: $spacecode, Fields updated: " . implode(', ', array_map(function($field) { return explode(' =', $field)[0]; }, $updateFields)));
            } else {
                $errors[] = "Line $lineNumber: Failed to update tenant - " . $conn->error;
                $skipped++;
            }
            $updateStmt->close();
        } else {
            // No changes needed
            error_log("No changes needed for tenant at line $lineNumber - Space code: $spacecode (Daily: '$daily' vs '{$existingTenant['daily']}', Rentbal: '$rentbal' vs '{$existingTenant['rentbal']}', Runningbal: '$runningbal' vs '{$existingTenant['runningbal']}')");
            $skipped++;
        }
    } else {
        // Tenant doesn't exist - insert new tenant
        // Space code is required, but tenant name is not required anymore
        $insertQuery = "INSERT INTO $tableName (tenantname, tenantcode, spacecode, daily, rentbal, runningbal, started_date) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertQuery);
        
        // Use current date if started_date is not provided
        if (empty($started_date)) {
            $started_date = date('Y-m-d');
        }
        
        $insertStmt->bind_param("sssssss", 
            $tenantname, 
            $tenantcode, 
            $spacecode, 
            $daily, 
            $rentbal, 
            $runningbal, 
            $started_date
        );
        
        if ($insertStmt->execute()) {
            $inserted++;
        } else {
            $errors[] = "Line $lineNumber: Failed to insert new tenant - " . $conn->error;
            $skipped++;
        }
        $insertStmt->close();
    }
}

// Prepare response
$response = [
    'success' => true,
    'updated' => $updated,
    'inserted' => $inserted,
    'skipped' => $skipped,
    'total_processed' => $lineNumber,
    'errors' => $errors,
    'summary' => [
        'total_lines_read' => $lineNumber,
        'successful_updates' => $updated,
        'successful_inserts' => $inserted,
        'skipped_records' => $skipped,
        'error_count' => count($errors)
    ]
];

// If there were too many errors, mark as failed
// But be more lenient if most errors are just missing space codes (likely summary rows)
$criticalErrors = 0;
$spaceCodeErrors = 0;

foreach ($errors as $error) {
    if (strpos($error, 'Space code is required') !== false) {
        $spaceCodeErrors++;
    } else {
        $criticalErrors++;
    }
}

if ($criticalErrors > 10 || ($criticalErrors > 5 && $spaceCodeErrors > 20)) {
    $response['success'] = false;
    $response['message'] = 'Too many critical errors occurred during processing. Please check your file format.';
} elseif (count($errors) > 50) {
    $response['success'] = false;
    $response['message'] = 'Too many errors occurred during processing. Your file may have formatting issues or contain many summary/total rows.';
}

$conn->close();

// Ensure clean output buffer
if (ob_get_length()) {
    ob_clean();
}

// Set proper headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Return JSON response
echo json_encode($response);
exit();
?>