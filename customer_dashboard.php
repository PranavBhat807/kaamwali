<?php
require_once 'db.php';

// Access control: must be customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: login.php");
    exit;
}

$customer_id = $_SESSION['user_id'];
$customer_name = $_SESSION['user_name'];
$message = '';
$msg_type = ''; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_maid'])) {
    $maid_id = intval($_POST['maid_id'] ?? 0);
    $service = trim($_POST['service'] ?? '');

    if ($maid_id <= 0 || empty($service)) {
        $message = "Invalid booking details.";
        $msg_type = "error";
    } else {
        // Verify maid is available
        $check_stmt = $conn->prepare("SELECT name, status FROM users WHERE id = ? AND role = 'maid'");
        $check_stmt->bind_param("i", $maid_id);
        $check_stmt->execute();
        $check_stmt->bind_result($maid_name, $maid_status);
        if ($check_stmt->fetch()) {
            if ($maid_status === 'Busy') {
                $message = "Sorry, $maid_name is currently busy. You cannot book them right now.";
                $msg_type = "error";
                $check_stmt->close();
            } else {
                $check_stmt->close();
                // Create booking
                $book_stmt = $conn->prepare("INSERT INTO bookings (customer_id, maid_id, service, status) VALUES (?, ?, ?, 'Pending')");
                $book_stmt->bind_param("iis", $customer_id, $maid_id, $service);
                if ($book_stmt->execute()) {
                    $message = "Booking request sent successfully to $maid_name!";
                    $msg_type = "success";
                } else {
                    $message = "Failed to send booking request. Please try again.";
                    $msg_type = "error";
                }
                $book_stmt->close();
            }
        } else {
            $message = "Maid not found.";
            $msg_type = "error";
            $check_stmt->close();
        }
    }
}

// Fetch all maids
$maids_query = "SELECT id, name, age, location, services, rating, status FROM users WHERE role = 'maid' ORDER BY name ASC";
$maids_result = $conn->query($maids_query);

// Fetch booking history
$history_stmt = $conn->prepare("
    SELECT b.id, u.name AS maid_name, u.phone AS maid_phone, b.service, b.status, b.created_at 
    FROM bookings b 
    JOIN users u ON b.maid_id = u.id 
    WHERE b.customer_id = ? 
    ORDER BY b.created_at DESC
");
$history_stmt->bind_param("i", $customer_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Dashboard - KAAMWALI</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .dashboard-container {
      padding: 120px 0 60px;
    }
    .welcome-header {
      margin-bottom: 40px;
      text-align: center;
    }
    .welcome-header h1 {
      font-size: 2.8rem;
      color: var(--indigo);
      margin-bottom: 5px;
    }
    .welcome-header p {
      color: var(--text-light);
    }
    .dashboard-section-title {
      font-size: 1.8rem;
      margin: 40px 0 20px;
      color: var(--indigo);
      border-bottom: 2px solid rgba(255, 153, 51, 0.2);
      padding-bottom: 8px;
    }
    .alert-banner {
      padding: 15px;
      border-radius: 8px;
      text-align: center;
      margin-bottom: 30px;
      font-weight: 500;
    }
    .alert-success {
      background: rgba(40, 167, 69, 0.1);
      color: #28a745;
      border: 1px solid rgba(40, 167, 69, 0.2);
    }
    .alert-error {
      background: rgba(220, 53, 69, 0.1);
      color: #dc3545;
      border: 1px solid rgba(220, 53, 69, 0.2);
    }
    .maid-badge {
      display: inline-block;
      padding: 3px 8px;
      background: var(--mango-light);
      color: var(--indigo);
      font-size: 0.8rem;
      border-radius: 4px;
      margin: 3px;
      font-weight: 500;
    }
    .status-badge-pending {
      background: #fff3cd;
      color: #856404;
    }
    .status-badge-accepted {
      background: #cce5ff;
      color: #004085;
    }
    .status-badge-rejected {
      background: #f8d7da;
      color: #721c24;
    }
    .status-badge-completed {
      background: #d4edda;
      color: #155724;
    }
    .booking-select {
      width: 100%;
      padding: 8px 12px;
      border-radius: 6px;
      border: 1px solid #ddd;
      margin-top: 10px;
      font-family: inherit;
    }
  </style>
</head>
<body>
  
  <nav class="navbar">
    <div class="container">
      <a href="customer_dashboard.php" class="logo">
        KAAM<span>WALI</span>
      </a>
      <div class="nav-links">
        <a href="index.php">Home</a>
        <span style="font-weight: 500; color: var(--indigo);">Welcome, <?php echo htmlspecialchars($customer_name); ?></span>
        <a href="logout.php" class="btn btn-outline" style="padding: 8px 20px; font-size: 0.9rem;">Logout</a>
      </div>
    </div>
  </nav>

  <div class="container dashboard-container">
    <div class="welcome-header">
      <h1>Hello, <?php echo htmlspecialchars($customer_name); ?>!</h1>
      <p>Find and book professional maids for your household needs.</p>
    </div>

    <?php if (!empty($message)): ?>
      <div class="alert-banner <?php echo ($msg_type === 'success') ? 'alert-success' : 'alert-error'; ?>">
        <?php echo htmlspecialchars($message); ?>
      </div>
    <?php endif; ?>

    <h2 class="dashboard-section-title">Available Maids</h2>
    <div class="services-grid">
      <?php if ($maids_result && $maids_result->num_rows > 0): ?>
        <?php while ($maid = $maids_result->fetch_assoc()): ?>
          <?php 
            $statusClass = $maid['status'] === 'Available' ? 'status-available' : 'status-busy';
            $services_arr = array_map('trim', explode(',', $maid['services']));
          ?>
          <div class="service-card" style="text-align: left; padding: 30px 25px;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
              <div>
                <h3 style="font-size: 1.4rem; margin-bottom: 5px; color: var(--indigo);"><?php echo htmlspecialchars($maid['name']); ?></h3>
                <span class="text-light" style="font-size: 0.9rem;"><?php echo htmlspecialchars($maid['age']); ?> Yrs &bull; <?php echo htmlspecialchars($maid['location']); ?></span>
              </div>
              <div style="font-size: 0.9rem; font-weight: 600;">
                ⭐ <?php echo htmlspecialchars($maid['rating']); ?>
              </div>
            </div>
            
            <div style="margin-bottom: 15px;">
              <span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($maid['status']); ?></span>
            </div>

            <div style="margin-bottom: 20px;">
              <strong style="font-size: 0.85rem; display: block; margin-bottom: 5px; color: var(--text-dark);">Services Offered:</strong>
              <div>
                <?php foreach ($services_arr as $srv): ?>
                  <span class="maid-badge"><?php echo htmlspecialchars($srv); ?></span>
                <?php endforeach; ?>
              </div>
            </div>

            <?php if ($maid['status'] === 'Available'): ?>
              <form method="POST" action="customer_dashboard.php" style="border-top: 1px solid rgba(0,0,0,0.05); padding-top: 15px;">
                <input type="hidden" name="maid_id" value="<?php echo $maid['id']; ?>">
                <label style="font-size: 0.85rem; font-weight: 500; color: var(--text-light);">Select Required Service:</label>
                <select name="service" class="booking-select" required>
                  <?php foreach ($services_arr as $srv): ?>
                    <option value="<?php echo htmlspecialchars($srv); ?>"><?php echo htmlspecialchars($srv); ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" name="book_maid" class="btn btn-primary" style="width: 100%; margin-top: 12px; padding: 8px 20px; font-size: 0.9rem;">
                  Request Booking
                </button>
              </form>
            <?php else: ?>
              <div style="border-top: 1px solid rgba(0,0,0,0.05); padding-top: 15px; text-align: center;">
                <button class="btn" style="background: #e9ecef; color: #6c757d; cursor: not-allowed; width: 100%; padding: 8px 20px; font-size: 0.9rem;" disabled>
                  Currently Busy
                </button>
              </div>
            <?php endif; ?>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <p style="grid-column: 1 / -1; text-align: center; color: var(--text-light); padding: 40px;">No registered maids found.</p>
      <?php endif; ?>
    </div>

    <h2 class="dashboard-section-title">Your Booking Requests</h2>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Maid Name</th>
            <th>Contact Phone</th>
            <th>Service Booked</th>
            <th>Requested Date</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($history_result && $history_result->num_rows > 0): ?>
            <?php while ($booking = $history_result->fetch_assoc()): ?>
              <?php
                $status_badge_class = 'status-badge-pending';
                if ($booking['status'] === 'Accepted') $status_badge_class = 'status-badge-accepted';
                if ($booking['status'] === 'Rejected') $status_badge_class = 'status-badge-rejected';
                if ($booking['status'] === 'Completed') $status_badge_class = 'status-badge-completed';
              ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($booking['maid_name']); ?></strong></td>
                <td><?php echo htmlspecialchars($booking['maid_phone']); ?></td>
                <td><?php echo htmlspecialchars($booking['service']); ?></td>
                <td><?php echo date('d M Y, h:i A', strtotime($booking['created_at'])); ?></td>
                <td>
                  <span class="status-badge <?php echo $status_badge_class; ?>">
                    <?php echo htmlspecialchars($booking['status']); ?>
                  </span>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" style="text-align: center; color: var(--text-light); padding: 30px;">
                You haven't made any booking requests yet.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <footer style="margin-top: 80px;">
    <div class="container">
      <div class="footer-content">
        <h3>KAAMWALI</h3>
        <p>Premium domestic help management for the modern Indian home.</p>
      </div>
      <div class="footer-bottom">
        <p>&copy; 2026 KAAMWALI. All rights reserved.</p>
      </div>
    </div>
  </footer>

  <script src="app.js"></script>
</body>
</html>
<?php
$history_stmt->close();
?>
