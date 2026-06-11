<?php
// Database connection details
$servername = "localhost";
$username = "wqxgzpmy_app";
$password = "R4styL0p3z";
$dbname = "wqxgzpmy_app";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  // Log database connection error (important for debugging)
  error_log("Database connection failed: " . $conn->connect_error);
  die(json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]));
}

// Function to send progress updates
function sendProgress($insertedCount, $totalRows, $duplicateCount = 0, $isComplete = false) {
    $response = [
        'inserted' => $insertedCount,
        'total' => $totalRows,
        'duplicates' => $duplicateCount,
        'progress' => $totalRows > 0 ? round(($insertedCount / $totalRows) * 100, 2) : 0,
        'complete' => $isComplete
    ];
    echo json_encode($response) . "\n";
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

// Function to send delete progress updates
function sendDeleteProgress($deletedCount, $totalCount, $isComplete = false) {
    $response = [
        'deleted' => $deletedCount,
        'total' => $totalCount,
        'progress' => $totalCount > 0 ? round(($deletedCount / $totalCount) * 100, 2) : 0,
        'complete' => $isComplete
    ];
    echo json_encode($response) . "\n";
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $action = $_POST['action'] ?? '';

  if ($action == 'upload_csv_progress') {
    header('Content-Type: application/json');
    set_time_limit(0);
    
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
      $file = $_FILES['csv_file']['tmp_name'];
      $csvData = array_map('str_getcsv', file($file));
      
      $totalRows = count($csvData);
      $insertedCount = 0;
      $duplicateCount = 0;
      $batchSize = 100; // Process 100 rows at a time
      $batchData = [];
      
      // Send initial progress
      sendProgress(0, $totalRows);
      
      foreach ($csvData as $index => $row) {
        if (count($row) == 7) {
          $item_number = trim($row[0]);
          $style_code = trim($row[1]);
          $style_name = trim($row[2]);
          $color = trim($row[3]);
          $size = trim($row[4]);
          $quantity = intval(trim($row[5]));
          $srp = floatval(trim($row[6]));
          
          $batchData[] = [$item_number, $style_code, $style_name, $color, $size, $quantity, $srp];
          
          // Process batch when it reaches batch size or at the end
          if (count($batchData) >= $batchSize || $index == $totalRows - 1) {
            $currentBatchSize = count($batchData);
            $placeholders = str_repeat('(?,?,?,?,?,?,?),', $currentBatchSize);
            $placeholders = rtrim($placeholders, ',');
            
            $stmt = $conn->prepare("INSERT IGNORE INTO items (item_number, style_code, style_name, color, size, quantity, srp) VALUES $placeholders");
            
            // Flatten the batch data for binding
            $flatData = [];
            $types = '';
            foreach ($batchData as $rowData) {
              foreach ($rowData as $value) {
                $flatData[] = $value;
                $types .= is_int($value) || is_float($value) ? (is_float($value) ? 'd' : 'i') : 's';
              }
            }
            
            $stmt->bind_param($types, ...$flatData);
            $stmt->execute();
            
            $batchInserted = $stmt->affected_rows;
            $insertedCount += $batchInserted;
            $duplicateCount += ($currentBatchSize - $batchInserted);
            
            $stmt->close();
            
            // Send progress update
            sendProgress($insertedCount, $totalRows, $duplicateCount);
            
            // Clear batch
            $batchData = [];
            
            // Small delay to make progress visible
            usleep(50000); // 0.05 second delay
          }
        }
      }
      
      // Get final item count
      $count_result = $conn->query("SELECT COUNT(*) AS item_count FROM items");
      $item_count = $count_result->fetch_assoc()['item_count'];
      
      // Send completion message
      $response = [
        'inserted' => $insertedCount,
        'total' => $totalRows,
        'duplicates' => $duplicateCount,
        'progress' => 100,
        'complete' => true,
        'item_count' => $item_count,
        'message' => "CSV data uploaded successfully. $duplicateCount duplicate items were skipped."
      ];
      echo json_encode($response) . "\n";
    } else {
      echo json_encode(['status' => 'error', 'message' => 'File upload error.']);
    }
    
  } elseif ($action == 'upload_csv') {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
      $file = $_FILES['csv_file']['tmp_name'];
      $csvData = array_map('str_getcsv', file($file));

      $values = [];
      $inserted_items = [];
      $error_flag = false;
      $errorMessage = '';
      $row_count = 0;
      foreach ($csvData as $row) {
        if (count($row) == 7) {
          $item_number = $conn->real_escape_string(trim($row[0]));
          $style_code = $conn->real_escape_string(trim($row[1]));
          $style_name = $conn->real_escape_string(trim($row[2]));
          $color = $conn->real_escape_string(trim($row[3]));
          $size = $conn->real_escape_string(trim($row[4]));
          $quantity = intval(trim($row[5]));
          $srp = floatval(trim($row[6]));
          $values[] = "('$item_number', '$style_code', '$style_name', '$color', '$size', $quantity, $srp)";
          $inserted_items[] = $item_number;
          $row_count++;
        } else {
          $error_flag = true;
          $errorMessage = 'CSV data does not match expected columns.';
          break;
        }
      }

      if (!$error_flag && count($values) > 0) {
        $sql = "INSERT IGNORE INTO items (item_number, style_code, style_name, color, size, quantity, srp) VALUES " . implode(',', $values);
        if ($conn->query($sql)) {
          $success_insert_count = $conn->affected_rows;
          $duplicate_count = $row_count - $success_insert_count;

          // Get the total item count after upload
          $count_sql = "SELECT COUNT(*) AS item_count FROM items";
          $count_result = $conn->query($count_sql);
          if ($count_result->num_rows > 0) {
            $row = $count_result->fetch_assoc();
            $item_count = $row['item_count'];
          } else {
            $item_count = 0;
          }

          $message = "CSV data uploaded successfully.";
          if ($duplicate_count > 0) {
            $message .= " {$duplicate_count} duplicate items were skipped.";
          }

          echo json_encode(['status' => 'success', 'message' => $message, 'inserted_count' => $success_insert_count, 'inserted_items' => $inserted_items, 'item_count' => $item_count]);
        } else {
          $error_flag = true;
          $errorMessage = 'Error inserting data: ' . $conn->error;
          error_log("Error inserting data: " . $conn->error);
          echo json_encode(['status' => 'error', 'message' => $errorMessage]);
        }
      } else if ($error_flag) {
        echo json_encode(['status' => 'error', 'message' => $errorMessage]);
      } else {
        echo json_encode(['status' => 'error', 'message' => 'No valid data to insert.']);
      }
    } else {
      echo json_encode(['status' => 'error', 'message' => 'File upload error.']);
    }
  } elseif ($action == 'delete_all_items_progress') {
    header('Content-Type: application/json');
    set_time_limit(0);
    
    try {
      // First, get the total count of items
      $countResult = $conn->query("SELECT COUNT(*) as total FROM items");
      $totalCount = $countResult->fetch_assoc()['total'];
      
      if ($totalCount == 0) {
        sendDeleteProgress(0, 0, true);
        $conn->close();
        exit;
      }
      
      // Send initial progress
      sendDeleteProgress(0, $totalCount);
      
      $deletedCount = 0;
      $batchSize = 1000; // Delete 1000 rows at a time
      
      // Delete in batches with progress updates
      while ($deletedCount < $totalCount) {
        $remainingCount = $totalCount - $deletedCount;
        $currentBatchSize = min($batchSize, $remainingCount);
        
        // Delete a batch of records
        $deleteQuery = "DELETE FROM items LIMIT $currentBatchSize";
        $result = $conn->query($deleteQuery);
        
        if ($result) {
          $deletedCount += $conn->affected_rows;
          
          // Send progress update
          sendDeleteProgress($deletedCount, $totalCount);
          
          // Small delay to make progress visible
          usleep(100000); // 0.1 second delay
        } else {
          echo json_encode(['error' => 'Error deleting items: ' . $conn->error]);
          $conn->close();
          exit;
        }
      }
      
      // Get final item count
      $count_result = $conn->query("SELECT COUNT(*) AS item_count FROM items");
      $item_count = $count_result->fetch_assoc()['item_count'];
      
      // Send completion message
      $response = [
        'deleted' => $deletedCount,
        'total' => $totalCount,
        'progress' => 100,
        'complete' => true,
        'item_count' => $item_count,
        'message' => 'All items deleted successfully.'
      ];
      echo json_encode($response) . "\n";
      
    } catch (Exception $e) {
      echo json_encode(['error' => 'An error occurred: ' . $e->getMessage()]);
    }
    
  } elseif ($action == 'delete_all_items') {
    $sql = "DELETE FROM items";
    if ($conn->query($sql) === TRUE) {


      // Get the total item count after deletion
      $count_sql = "SELECT COUNT(*) AS item_count FROM items";
      $count_result = $conn->query($count_sql);
      if ($count_result->num_rows > 0) {
        $row = $count_result->fetch_assoc();
        $item_count = $row['item_count'];
      } else {
        $item_count = 0;
      }


      echo json_encode(['status' => 'success', 'message' => 'All items deleted successfully.', 'item_count' => $item_count]);
    } else {
      // Log SQL delete error
      error_log("Error deleting items: " . $conn->error);
      echo json_encode(['status' => 'error', 'message' => 'Error deleting items: ' . $conn->error]);
    }
  }  elseif ($action == 'get_item_count') {
    // Get the total item count
    $count_sql = "SELECT COUNT(*) AS item_count FROM items";
    $count_result = $conn->query($count_sql);
    if ($count_result->num_rows > 0) {
      $row = $count_result->fetch_assoc();
      $item_count = $row['item_count'];
      echo json_encode(['status' => 'success', 'item_count' => $item_count]);
    } else {
      echo json_encode(['status' => 'error', 'message' => 'Could not retrieve item count.']);
    }
  }
   else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
  }
  $conn->close();
  exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>PCOUNT APP</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: #333;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      margin: 0;
      position: relative;
      overflow-x: hidden;
    }
    
    /* Animated background particles */
    body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="20" cy="20" r="1" fill="%23ffffff" opacity="0.1"/><circle cx="80" cy="40" r="0.8" fill="%23ffffff" opacity="0.08"/><circle cx="40" cy="80" r="1.2" fill="%23ffffff" opacity="0.06"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>')
      z-index: -1;
      animation: float 20s ease-in-out infinite;
    }
    
    @keyframes float {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-10px); }
    }
    
    .container {
      background: rgba(255,255,255,0.15);
      backdrop-filter: blur(20px) saturate(1.8);
      -webkit-backdrop-filter: blur(20px) saturate(1.8);
      padding: 40px 32px 32px 32px;
      border-radius: 24px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 32px rgba(255,255,255,0.1) inset;
      width: 95%;
      max-width: 500px;
      text-align: center;
      position: relative;
      border: 1px solid rgba(255,255,255,0.2);
      animation: slideInUp 0.8s cubic-bezier(.4,0,.2,1);
    }
    
    @keyframes slideInUp {
      from { opacity: 0; transform: translateY(50px) scale(0.9); }
      to { opacity: 1; transform: translateY(0) scale(1); }
    }
    
    .app-title {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      margin-bottom: 20px;
    }
    
    .app-title-icon {
      width: 42px;
      height: 42px;
      display: inline-block;
      vertical-align: middle;
      animation: iconFloat 3s ease-in-out infinite;
    }
    
    @keyframes iconFloat {
      0%, 100% { transform: translateY(0px) rotate(0deg); }
      50% { transform: translateY(-5px) rotate(5deg); }
    }
    
    .app-title-text {
      color: #ffffff;
      font-size: 2.4em;
      font-weight: 700;
      letter-spacing: 1.5px;
      text-shadow: 2px 4px 8px rgba(0,0,0,0.3);
      background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    
    .form-group {
      margin-bottom: 25px;
      display: block;
    }
    
    .form-group:last-child {
      margin-bottom: 0;
    }
    
    label {
      display: block;
      margin-bottom: 10px;
      color: #ffffff;
      font-weight: 600;
      letter-spacing: 0.5px;
      text-shadow: 1px 2px 4px rgba(0,0,0,0.2);
    }
    
    input[type="file"],
    button {
      font-family: inherit;
      font-size: 1em;
      outline: none;
      transition: all 0.3s ease;
    }
    
    /* Modern file input styling */
    .file-input-container {
      position: relative;
      overflow: hidden;
      border-radius: 15px;
      background: rgba(255,255,255,0.9);
      backdrop-filter: blur(10px);
      border: 2px dashed rgba(102, 126, 234, 0.3);
      padding: 20px;
      transition: all 0.3s ease;
      cursor: pointer;
    }
    
    .file-input-container:hover {
      border-color: rgba(102, 126, 234, 0.6);
      background: rgba(255,255,255,0.95);
      transform: translateY(-2px);
    }
    
    .file-input-container.dragover {
      border-color: #667eea;
      background: rgba(102, 126, 234, 0.1);
      transform: scale(1.02);
    }
    
    input[type="file"] {
      position: absolute;
      left: -9999px;
      opacity: 0;
    }
    
    .file-input-label {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 10px;
      color: #4a5568;
      font-weight: 600;
      cursor: pointer;
    }
    
    .file-icon {
      font-size: 2.5rem;
      color: #667eea;
      margin-bottom: 5px;
    }
    
    .file-info {
      display: none;
      margin-top: 10px;
      padding: 10px;
      background: rgba(102, 126, 234, 0.1);
      border-radius: 8px;
      color: #667eea;
      font-weight: 600;
    }
    
    .action-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      padding: 16px 24px;
      border-radius: 15px;
      border: none;
      font-weight: 700;
      cursor: pointer;
      margin-bottom: 0;
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: #fff;
      position: relative;
      overflow: hidden;
      transition: all 0.3s ease;
      width: 100%;
      display: block;
      box-sizing: border-box;
      font-size: 1.1rem;
    }
    
    .action-btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
      transition: left 0.5s;
    }
    
    .action-btn:hover::before {
      left: 100%;
    }
    
    .action-btn svg {
      width: 22px;
      height: 22px;
      vertical-align: middle;
      fill: #fff;
      transition: fill 0.2s;
    }
    
    .action-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 15px 35px rgba(0,0,0,0.2);
    }
    
    .action-btn:disabled {
      opacity: 0.7;
      cursor: not-allowed;
      transform: none;
    }
    
    #delete_all_button {
      background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
      margin-top: 15px;
    }
    
    #delete_all_button:hover {
      box-shadow: 0 15px 35px rgba(220, 38, 38, 0.3);
    }
    
    #item_count_display {
      display: block;
      text-align: center;
      font-weight: bold;
      font-size: 1.4em;
      color: #ffffff;
      margin-bottom: 20px;
      letter-spacing: 0.5px;
      text-shadow: 2px 4px 8px rgba(0,0,0,0.3);
      background: rgba(255,255,255,0.1);
      padding: 12px;
      border-radius: 12px;
      backdrop-filter: blur(10px);
    }
    
    /* Progress Section */
    .progress-section {
      display: none;
      margin-top: 25px;
      padding: 25px;
      background: rgba(255,255,255,0.9);
      border-radius: 15px;
      border: 1px solid rgba(255,255,255,0.3);
      box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    
    .progress-header {
      margin-bottom: 20px;
    }
    
    .progress-title {
      font-size: 1.3rem;
      font-weight: 600;
      color: #2d3748;
      margin-bottom: 5px;
    }
    
    .progress-subtitle {
      color: #718096;
      font-size: 0.95rem;
    }
    
    .progress-bar-container {
      background: #e2e8f0;
      border-radius: 25px;
      height: 12px;
      overflow: hidden;
      margin: 20px 0;
      position: relative;
    }
    
    .progress-bar {
      background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
      height: 100%;
      border-radius: 25px;
      width: 0%;
      transition: width 0.3s ease;
      position: relative;
    }
    
    .progress-bar::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
      animation: shimmer 2s infinite;
    }
    
    @keyframes shimmer {
      0% { transform: translateX(-100%); }
      100% { transform: translateX(100%); }
    }
    
    .progress-stats {
      display: flex;
      justify-content: space-between;
      margin-top: 15px;
      gap: 10px;
    }
    
    .stat-item {
      text-align: center;
      flex: 1;
      background: rgba(255,255,255,0.5);
      padding: 10px;
      border-radius: 10px;
    }
    
    .stat-value {
      font-size: 1.4rem;
      font-weight: 700;
      color: #667eea;
      display: block;
      transition: all 0.3s ease;
    }
    
    .stat-value.updating {
      transform: scale(1.1);
      color: #48bb78;
    }
    
    .stat-label {
      font-size: 0.8rem;
      color: #718096;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-top: 3px;
    }
    
    .live-counter {
      background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
      color: white;
      padding: 8px 16px;
      border-radius: 20px;
      font-weight: 600;
      display: inline-block;
      margin: 10px 5px 0 5px;
      animation: pulse 1.5s infinite;
    }
    
    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.05); }
    }
    
    /* Enhanced Modal Styling */
    #message_modal {
      display: none;
      position: fixed;
      z-index: 1002;
      left: 0; top: 0; right: 0; bottom: 0;
      width: 100vw; height: 100vh;
      background: rgba(44, 62, 80, 0.7);
      backdrop-filter: blur(5px);
      justify-content: center;
      align-items: center;
      animation: fadeIn 0.3s ease;
    }
    
    #message_modal.active {
      display: flex;
    }
    
    .modal-content {
      background: rgba(255,255,255,0.95);
      backdrop-filter: blur(15px) saturate(1.2);
      -webkit-backdrop-filter: blur(15px) saturate(1.2);
      border-radius: 20px;
      box-shadow: 0 25px 50px rgba(0,0,0,0.2);
      padding: 36px 32px 28px 32px;
      min-width: 320px;
      max-width: 90vw;
      text-align: center;
      position: relative;
      animation: slideInScale 0.4s ease-out;
      border: 1px solid rgba(255,255,255,0.3);
    }
    
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    
    @keyframes slideInScale {
      from {
        transform: translateY(-50px) scale(0.9);
        opacity: 0;
      }
      to {
        transform: translateY(0) scale(1);
        opacity: 1;
      }
    }
    
    .modal-content.success {
      border-left: 6px solid #48bb78;
    }
    
    .modal-content.error {
      border-left: 6px solid #dc2626;
    }
    
    .modal-content .modal-title {
      font-size: 1.4em;
      font-weight: 700;
      margin-bottom: 15px;
      color: #667eea;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
    }
    
    .modal-content.success .modal-title {
      color: #48bb78;
    }
    
    .modal-content.error .modal-title {
      color: #dc2626;
    }
    
    .modal-content .modal-title svg {
      width: 30px;
      height: 30px;
      vertical-align: middle;
    }
    
    .modal-content .modal-message {
      font-size: 1.1em;
      margin-bottom: 20px;
      color: #444;
      line-height: 1.6;
    }
    
    .modal-content .modal-close {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: 12px 28px;
      font-size: 1em;
      font-weight: 600;
      cursor: pointer;
      margin-top: 10px;
      transition: all 0.3s ease;
      box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
    }
    
    .modal-content .modal-close:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 35px rgba(102, 126, 234, 0.4);
    }
    
    /* Mobile Responsiveness */
    @media (max-width: 768px) {
      .container {
        margin: 20px;
        padding: 25px;
        max-width: none;
      }
      
      .app-title-text {
        font-size: 2rem;
      }
      
      .progress-stats {
        flex-direction: column;
        gap: 10px;
      }
      
      .stat-item {
        padding: 8px;
      }
      
      .action-btn {
        padding: 14px 20px;
        font-size: 1rem;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="app-title">
      <span class="app-title-icon">
        <!-- Enhanced SVG icon for the app -->
        <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="20" cy="20" r="19" fill="#ffffff" fill-opacity="0.2" stroke="#ffffff" stroke-width="2"/>
          <rect x="11" y="11" width="18" height="18" rx="5" fill="#ffffff" fill-opacity="0.9"/>
          <path d="M16 20.5L19 23.5L25 17.5" stroke="#667eea" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </span>
      <span class="app-title-text">PCOUNT APP</span>
    </div>
    
    <div class="message-container">
      <div id="item_count_display"></div>
    </div>
    
    <div class="form-group">
      <label for="csv_file">📄 Upload CSV File (No Header):</label>
      <div class="file-input-container" id="file-input-container">
        <input type="file" id="csv_file" name="csv_file" accept=".csv">
        <div class="file-input-label" id="file-input-label">
          <div class="file-icon">📎</div>
          <div>Drag & Drop or Click to Select CSV File</div>
          <small>Supports CSV files with 7 columns</small>
        </div>
        <div class="file-info" id="file-info">
          <span id="file-name"></span>
          <span id="file-size"></span>
        </div>
      </div>
    </div>
    
    <!-- Progress Section -->
    <div id="progress-section" class="progress-section">
      <div class="progress-header">
        <div class="progress-title">Processing Upload...</div>
        <div class="progress-subtitle">Please wait while we import your data</div>
      </div>
      
      <div class="progress-bar-container">
        <div class="progress-bar" id="progress-bar"></div>
      </div>
      
      <div class="progress-stats">
        <div class="stat-item">
          <span class="stat-value" id="inserted-count">0</span>
          <span class="stat-label">Inserted</span>
        </div>
        <div class="stat-item">
          <span class="stat-value" id="total-count">0</span>
          <span class="stat-label">Total</span>
        </div>
        <div class="stat-item">
          <span class="stat-value" id="duplicate-count">0</span>
          <span class="stat-label">Duplicates</span>
        </div>
        <div class="stat-item">
          <span class="stat-value" id="progress-percent">0%</span>
          <span class="stat-label">Progress</span>
        </div>
      </div>
      
      <div style="text-align: center;">
        <span>Status: </span>
        <span class="live-counter" id="live-status">Ready to upload</span>
      </div>
    </div>
    
    <!-- Delete Progress Section -->
    <div id="delete-progress-section" class="progress-section" style="display: none;">
      <div class="progress-header">
        <div class="progress-title" style="color: #dc2626;">Deleting Items...</div>
        <div class="progress-subtitle">Please wait while we remove all items</div>
      </div>
      
      <div class="progress-bar-container">
        <div class="progress-bar" id="delete-progress-bar" style="background: linear-gradient(90deg, #dc2626 0%, #991b1b 100%);"></div>
      </div>
      
      <div class="progress-stats">
        <div class="stat-item">
          <span class="stat-value" id="deleted-count" style="color: #dc2626;">0</span>
          <span class="stat-label">Deleted</span>
        </div>
        <div class="stat-item">
          <span class="stat-value" id="delete-total-count" style="color: #dc2626;">0</span>
          <span class="stat-label">Total</span>
        </div>
        <div class="stat-item">
          <span class="stat-value" id="delete-progress-percent" style="color: #dc2626;">0%</span>
          <span class="stat-label">Progress</span>
        </div>
      </div>
      
      <div style="text-align: center;">
        <span>Status: </span>
        <span class="live-counter" id="delete-live-status" style="background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);">Ready to delete</span>
      </div>
    </div>
    
    <div class="form-group">
      <button id="upload_button" class="action-btn" disabled>
        <!-- Upload SVG icon -->
        <svg viewBox="0 0 24 24"><path d="M12 16V4M12 4L7 9M12 4l5 5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M20 16v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        🚀 Upload CSV with Progress
      </button>
    </div>
    
    <div class="form-group">
      <button id="delete_all_button" class="action-btn">
        <!-- Trash SVG icon -->
        <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m2 0v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        🗑️ Delete All Items
      </button>
    </div>
  </div>

  <!-- Message Modal -->
  <div id="message_modal">
    <div class="modal-content" id="modal_content">
      <div class="modal-title" id="modal_title"></div>
      <div class="modal-message" id="modal_message"></div>
      <button class="modal-close" id="modal_close">OK</button>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const uploadButton = document.getElementById('upload_button');
      const deleteButton = document.getElementById('delete_all_button');
      const csvFile = document.getElementById('csv_file');
      const fileInputContainer = document.getElementById('file-input-container');
      const fileInputLabel = document.getElementById('file-input-label');
      const fileInfo = document.getElementById('file-info');
      const fileName = document.getElementById('file-name');
      const fileSize = document.getElementById('file-size');
      const messageModal = document.getElementById('message_modal');
      const modalContent = document.getElementById('modal_content');
      const modalTitle = document.getElementById('modal_title');
      const modalMessage = document.getElementById('modal_message');
      const modalClose = document.getElementById('modal_close');
      const itemCountDisplay = document.getElementById('item_count_display');
      
      // Progress elements
      const progressSection = document.getElementById('progress-section');
      const progressBar = document.getElementById('progress-bar');
      const insertedCount = document.getElementById('inserted-count');
      const totalCount = document.getElementById('total-count');
      const duplicateCount = document.getElementById('duplicate-count');
      const progressPercent = document.getElementById('progress-percent');
      const liveStatus = document.getElementById('live-status');
      
      // Delete progress elements
      const deleteProgressSection = document.getElementById('delete-progress-section');
      const deleteProgressBar = document.getElementById('delete-progress-bar');
      const deletedCount = document.getElementById('deleted-count');
      const deleteTotalCount = document.getElementById('delete-total-count');
      const deleteProgressPercent = document.getElementById('delete-progress-percent');
      const deleteLiveStatus = document.getElementById('delete-live-status');

      // Drag and drop functionality
      fileInputContainer.addEventListener('click', () => csvFile.click());
      fileInputContainer.addEventListener('dragover', handleDragOver);
      fileInputContainer.addEventListener('dragleave', handleDragLeave);
      fileInputContainer.addEventListener('drop', handleDrop);

      function handleDragOver(e) {
        e.preventDefault();
        fileInputContainer.classList.add('dragover');
      }

      function handleDragLeave(e) {
        e.preventDefault();
        fileInputContainer.classList.remove('dragover');
      }

      function handleDrop(e) {
        e.preventDefault();
        fileInputContainer.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
          csvFile.files = files;
          handleFileSelection();
        }
      }

      csvFile.addEventListener('change', handleFileSelection);

      function handleFileSelection() {
        const file = csvFile.files[0];
        if (file) {
          fileName.textContent = file.name;
          fileSize.textContent = `(${(file.size / 1024).toFixed(1)} KB)`;
          fileInfo.style.display = 'block';
          uploadButton.disabled = false;
          fileInputLabel.innerHTML = `
            <div class="file-icon" style="color: #48bb78;">✓</div>
            <div style="color: #48bb78; font-weight: 600;">File Selected Successfully</div>
            <small>Click to change file</small>
          `;
        }
      }

      function formatNumber(num) {
        return new Intl.NumberFormat().format(num);
      }

      function resetUpload() {
        csvFile.value = '';
        fileInfo.style.display = 'none';
        progressSection.style.display = 'none';
        uploadButton.disabled = true;
        uploadButton.innerHTML = `
          <svg viewBox="0 0 24 24"><path d="M12 16V4M12 4L7 9M12 4l5 5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M20 16v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          🚀 Upload CSV with Progress
        `;
        fileInputLabel.innerHTML = `
          <div class="file-icon">📎</div>
          <div>Drag & Drop or Click to Select CSV File</div>
          <small>Supports CSV files with 7 columns</small>
        `;
        progressBar.style.width = '0%';
        insertedCount.textContent = '0';
        totalCount.textContent = '0';
        duplicateCount.textContent = '0';
        progressPercent.textContent = '0%';
        liveStatus.textContent = 'Ready to upload';
      }

      uploadButton.addEventListener('click', function() {
        const file = csvFile.files[0];
        if (file) {
          if (file.type === 'text/csv' || file.type === 'application/vnd.ms-excel' || file.type === 'text/plain') {
            uploadCSVWithProgress(file);
          } else {
            showModal('Error', 'Please upload a valid CSV file.', 'error');
          }
        } else {
          showModal('Error', 'Please select a CSV file.', 'error');
        }
      });

      deleteButton.addEventListener('click', function() {
        if (confirm('⚠️ Are you sure you want to delete ALL items from the database?\n\nThis action cannot be undone!')) {
          deleteAllItemsWithProgress();
        }
      });

      function showModal(title, message, type = 'success') {
        let iconSVG = '';
        if (type === 'success') {
          iconSVG = `<svg viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="16" fill="#48bb78" fill-opacity="0.15"/><path d="M10 17l4 4 8-8" stroke="#48bb78" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
        } else if (type === 'error') {
          iconSVG = `<svg viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="16" fill="#dc2626" fill-opacity="0.15"/><path d="M11 11l10 10M21 11L11 21" stroke="#dc2626" stroke-width="2.5" stroke-linecap="round"/></svg>`;
        } else {
          iconSVG = `<svg viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="16" fill="#667eea" fill-opacity="0.15"/><path d="M16 10v6M16 22h.01" stroke="#667eea" stroke-width="2.5" stroke-linecap="round"/></svg>`;
        }
        modalTitle.innerHTML = iconSVG + '<span>' + title + '</span>';
        modalMessage.textContent = message;
        modalContent.className = 'modal-content ' + type;
        messageModal.classList.add('active');
      }
      
      function hideModal() {
        messageModal.classList.remove('active');
      }
      
      modalClose.addEventListener('click', hideModal);
      messageModal.addEventListener('click', function(e) {
        if (e.target === messageModal) hideModal();
      });

      function uploadCSVWithProgress(file) {
        progressSection.style.display = 'block';
        uploadButton.disabled = true;
        uploadButton.innerHTML = `
          <svg viewBox="0 0 24 24" class="animate-spin"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>
          🔄 Processing...
        `;
        liveStatus.textContent = 'Starting upload...';
        liveStatus.style.background = 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)';

        const formData = new FormData();
        formData.append('csv_file', file);
        formData.append('action', 'upload_csv_progress');

        fetch('app_sync.php', {
          method: 'POST',
          body: formData,
        })
        .then(response => {
          const reader = response.body.getReader();
          const decoder = new TextDecoder();
          
          function readStream() {
            return reader.read().then(({done, value}) => {
              if (done) {
                return;
              }
              
              const chunk = decoder.decode(value);
              const lines = chunk.split('\n').filter(line => line.trim());
              
              lines.forEach(line => {
                try {
                  const data = JSON.parse(line);
                  updateProgress(data);
                } catch (e) {
                  console.log('Non-JSON response:', line);
                }
              });
              
              return readStream();
            });
          }
          
          return readStream();
        })
        .catch(error => {
          console.error('Error:', error);
          liveStatus.textContent = 'Upload failed';
          liveStatus.style.background = 'linear-gradient(135deg, #dc2626 0%, #991b1b 100%)';
          uploadButton.disabled = false;
          uploadButton.innerHTML = `
            <svg viewBox="0 0 24 24"><path d="M12 16V4M12 4L7 9M12 4l5 5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M20 16v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            🚀 Retry Upload
          `;
          showModal('Error', 'An error occurred during upload: ' + error.message, 'error');
        });
      }

      function updateProgress(data) {
        if (data.error) {
          liveStatus.textContent = data.error;
          liveStatus.style.background = 'linear-gradient(135deg, #dc2626 0%, #991b1b 100%)';
          return;
        }

        insertedCount.textContent = formatNumber(data.inserted);
        totalCount.textContent = formatNumber(data.total);
        duplicateCount.textContent = formatNumber(data.duplicates || 0);
        progressPercent.textContent = data.progress + '%';
        progressBar.style.width = data.progress + '%';
        
        // Animate the updating values
        [insertedCount, totalCount, duplicateCount].forEach(el => {
          el.classList.add('updating');
          setTimeout(() => el.classList.remove('updating'), 300);
        });
        
        liveStatus.textContent = `Inserting... ${formatNumber(data.inserted)} / ${formatNumber(data.total)}`;
        
        if (data.complete) {
          liveStatus.textContent = 'Upload completed successfully!';
          liveStatus.style.background = 'linear-gradient(135deg, #48bb78 0%, #38a169 100%)';
          
          if (data.item_count !== undefined) {
            itemCountDisplay.textContent = `Total Items in Database: ${formatNumber(data.item_count)}`;
          }
          
          setTimeout(() => {
            showModal('Success', data.message || `${formatNumber(data.inserted)} items uploaded successfully!`, 'success');
            setTimeout(resetUpload, 2000);
          }, 1000);
        }
      }

      function deleteAllItemsWithProgress() {
        deleteProgressSection.style.display = 'block';
        deleteButton.disabled = true;
        deleteButton.innerHTML = `
          <svg viewBox="0 0 24 24" class="animate-spin"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>
          🔄 Deleting...
        `;
        deleteLiveStatus.textContent = 'Starting deletion...';
        deleteLiveStatus.style.background = 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)';

        const formData = new FormData();
        formData.append('action', 'delete_all_items_progress');

        fetch('app_sync.php', {
          method: 'POST',
          body: formData,
        })
        .then(response => {
          const reader = response.body.getReader();
          const decoder = new TextDecoder();
          
          function readStream() {
            return reader.read().then(({done, value}) => {
              if (done) {
                return;
              }
              
              const chunk = decoder.decode(value);
              const lines = chunk.split('\n').filter(line => line.trim());
              
              lines.forEach(line => {
                try {
                  const data = JSON.parse(line);
                  updateDeleteProgress(data);
                } catch (e) {
                  console.log('Non-JSON response:', line);
                }
              });
              
              return readStream();
            });
          }
          
          return readStream();
        })
        .catch(error => {
          console.error('Error:', error);
          deleteLiveStatus.textContent = 'Deletion failed';
          deleteLiveStatus.style.background = 'linear-gradient(135deg, #dc2626 0%, #991b1b 100%)';
          deleteButton.disabled = false;
          deleteButton.innerHTML = `
            <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m2 0v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            🚀 Retry Delete
          `;
          showModal('Error', 'An error occurred during deletion: ' + error.message, 'error');
        });
      }

      function updateDeleteProgress(data) {
        if (data.error) {
          deleteLiveStatus.textContent = data.error;
          deleteLiveStatus.style.background = 'linear-gradient(135deg, #dc2626 0%, #991b1b 100%)';
          return;
        }

        deletedCount.textContent = formatNumber(data.deleted);
        deleteTotalCount.textContent = formatNumber(data.total);
        deleteProgressPercent.textContent = data.progress + '%';
        deleteProgressBar.style.width = data.progress + '%';
        
        // Animate the updating values
        [deletedCount, deleteTotalCount].forEach(el => {
          el.classList.add('updating');
          setTimeout(() => el.classList.remove('updating'), 300);
        });
        
        if (data.total === 0) {
          deleteLiveStatus.textContent = 'No items to delete';
          deleteLiveStatus.style.background = 'linear-gradient(135deg, #6b7280 0%, #4b5563 100%)';
        } else {
          deleteLiveStatus.textContent = `Deleting... ${formatNumber(data.deleted)} / ${formatNumber(data.total)}`;
        }
        
        if (data.complete) {
          deleteLiveStatus.textContent = 'Deletion completed successfully!';
          deleteLiveStatus.style.background = 'linear-gradient(135deg, #48bb78 0%, #38a169 100%)';
          
          if (data.item_count !== undefined) {
            itemCountDisplay.textContent = `Total Items in Database: ${formatNumber(data.item_count)}`;
          }
          
          setTimeout(() => {
            showModal('Success', data.message || `${formatNumber(data.deleted)} items deleted successfully!`, 'success');
            setTimeout(() => {
              deleteProgressSection.style.display = 'none';
              deleteButton.disabled = false;
              deleteButton.innerHTML = `
                <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m2 0v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                🗑️ Delete All Items
              `;
              deleteProgressBar.style.width = '0%';
              deletedCount.textContent = '0';
              deleteTotalCount.textContent = '0';
              deleteProgressPercent.textContent = '0%';
              deleteLiveStatus.textContent = 'Ready to delete';ap
            }, 2000);
          }, 1000);
        }
      }

      function getItemCount() {
        const formData = new FormData();
        formData.append('action', 'get_item_count');

        fetch('app_sync.php', {
          method: 'POST',
          body: formData,
        })
        .then(response => response.json())
        .then(data => {
          if (data.status === 'success') {
            itemCountDisplay.textContent = `Total Items in Database: ${formatNumber(data.item_count)}`;
            itemCountDisplay.style.display = 'block';
          } else {
            itemCountDisplay.style.display = 'none';
          }
        })
        .catch(error => {
          console.error('Error fetching item count:', error);
          itemCountDisplay.style.display = 'none';
        });
      }

      // Load initial item count
      getItemCount();
    });
  </script>
</body>
</html>
<?php
$conn->close();
?>