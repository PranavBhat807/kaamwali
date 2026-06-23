<?php
require_once 'db.php';

// Access control: must be maid
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'maid') {
    header("Location: login.php");
    exit;
}

$maid_id = $_SESSION['user_id'];
$maid_name = $_SESSION['user_name'];
$message = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $age = intval($_POST['age'] ?? 0);
        $location = trim($_POST['location'] ?? '');
        $services_arr = $_POST['services'] ?? [];

        if (empty($name) || empty($phone) || $age <= 0 || empty($location) || empty($services_arr)) {
            $message = "All fields are required and age must be valid.";
            $msg_type = "error";
        } else {
            // Check if phone number is already taken by another user
            $phone_stmt = $conn->prepare("SELECT id FROM users WHERE phone = ? AND id != ?");
            $phone_stmt->bind_param("si", $phone, $maid_id);
            $phone_stmt->execute();
            $phone_stmt->store_result();
            if ($phone_stmt->num_rows > 0) {
                $message = "This phone number is already registered by another user.";
                $msg_type = "error";
            } else {
                $services = implode(', ', $services_arr);
                $update_stmt = $conn->prepare("UPDATE users SET name = ?, phone = ?, age = ?, location = ?, services = ? WHERE id = ?");
                $update_stmt->bind_param("ssissi", $name, $phone, $age, $location, $services, $maid_id);
                if ($update_stmt->execute()) {
                    $message = "Profile updated successfully!";
                    $msg_type = "success";
                    $_SESSION['user_name'] = $name;
                    $maid_name = $name;
                } else {
                    $message = "Failed to update profile. Please try again.";
                    $msg_type = "error";
                }
                $update_stmt->close();
            }
            $phone_stmt->close();
        }
    } else {
        $booking_id = intval($_POST['booking_id'] ?? 0);

        if ($booking_id > 0 && in_array($action, ['accept', 'reject', 'complete'])) {
            // First verify that this booking is indeed for this maid
            $check_stmt = $conn->prepare("SELECT status FROM bookings WHERE id = ? AND maid_id = ?");
            $check_stmt->bind_param("ii", $booking_id, $maid_id);
            $check_stmt->execute();
            $check_stmt->bind_result($booking_status);
            if ($check_stmt->fetch()) {
                $check_stmt->close();
                
                $conn->begin_transaction();
                try {
                    if ($action === 'accept') {
                        // Update booking status
                        $stmt1 = $conn->prepare("UPDATE bookings SET status = 'Accepted' WHERE id = ?");
                        $stmt1->bind_param("i", $booking_id);
                        $stmt1->execute();
                        $stmt1->close();
                        
                        // Update maid user status to Busy
                        $stmt2 = $conn->prepare("UPDATE users SET status = 'Busy' WHERE id = ?");
                        $stmt2->bind_param("i", $maid_id);
                        $stmt2->execute();
                        $stmt2->close();

                        $message = "Booking request accepted! Your status is now set to 'Busy'.";
                        $msg_type = "success";
                    } elseif ($action === 'reject') {
                        // Update booking status
                        $stmt1 = $conn->prepare("UPDATE bookings SET status = 'Rejected' WHERE id = ?");
                        $stmt1->bind_param("i", $booking_id);
                        $stmt1->execute();
                        $stmt1->close();

                        // Re-calculate if maid has other active accepted jobs. If not, make available.
                        // (For simple logic, we mark them Available when they reject a specific task)
                        $stmt2 = $conn->prepare("UPDATE users SET status = 'Available' WHERE id = ?");
                        $stmt2->bind_param("i", $maid_id);
                        $stmt2->execute();
                        $stmt2->close();

                        $message = "Booking request rejected.";
                        $msg_type = "success";
                    } elseif ($action === 'complete') {
                        // Update booking status
                        $stmt1 = $conn->prepare("UPDATE bookings SET status = 'Completed' WHERE id = ?");
                        $stmt1->bind_param("i", $booking_id);
                        $stmt1->execute();
                        $stmt1->close();

                        // Update maid user status to Available
                        $stmt2 = $conn->prepare("UPDATE users SET status = 'Available' WHERE id = ?");
                        $stmt2->bind_param("i", $maid_id);
                        $stmt2->execute();
                        $stmt2->close();

                        $message = "Job marked as completed! Your status is now set to 'Available'.";
                        $msg_type = "success";
                    }
                    $conn->commit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "An error occurred: " . $e->getMessage();
                    $msg_type = "error";
                }
            } else {
                $check_stmt->close();
                $message = "Booking request not found.";
                $msg_type = "error";
            }
        }
    }
}

// Fetch maid requests
$requests_stmt = $conn->prepare("
    SELECT b.id, u.name AS customer_name, u.phone AS customer_phone, b.service, b.status, b.created_at 
    FROM bookings b 
    JOIN users u ON b.customer_id = u.id 
    WHERE b.maid_id = ? 
    ORDER BY b.created_at DESC
");
$requests_stmt->bind_param("i", $maid_id);
$requests_stmt->execute();
$requests_result = $requests_stmt->get_result();

// Fetch current maid details
$profile_stmt = $conn->prepare("SELECT name, phone, age, location, services, status FROM users WHERE id = ?");
$profile_stmt->bind_param("i", $maid_id);
$profile_stmt->execute();
$profile_stmt->bind_result($maid_name_db, $maid_phone, $maid_age, $maid_location, $maid_services, $current_status);
$profile_stmt->fetch();
$profile_stmt->close();

// Make sure session name matches DB name in case it was updated
$_SESSION['user_name'] = $maid_name_db;
$maid_name = $maid_name_db;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Maid Dashboard - KAAMWALI</title>
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
    .action-group {
      display: flex;
      gap: 10px;
    }
    .dashboard-card {
      background: var(--pure-white);
      padding: 20px;
      border-radius: var(--radius-md);
      box-shadow: var(--shadow-sm);
      margin-bottom: 30px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border: 1px solid rgba(0,0,0,0.03);
    }
    .btn-action {
      padding: 6px 12px;
      border-radius: 4px;
      font-weight: 500;
      font-size: 0.85rem;
      border: none;
      cursor: pointer;
      transition: var(--transition);
    }
    .btn-accept {
      background: #28a745;
      color: white;
    }
    .btn-accept:hover {
      background: #218838;
    }
    .btn-reject {
      background: #dc3545;
      color: white;
    }
    .btn-reject:hover {
      background: #c82333;
    }
    .btn-complete {
      background: var(--indigo);
      color: white;
    }
    .btn-complete:hover {
      background: var(--indigo-light);
    }
    .dashboard-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 30px;
      margin-bottom: 40px;
      align-items: start;
    }
    @media (min-width: 768px) {
      .dashboard-grid {
        grid-template-columns: 1fr 1.2fr;
      }
    }
    .profile-card {
      background: var(--pure-white);
      padding: 30px;
      border-radius: var(--radius-md);
      box-shadow: var(--shadow-sm);
      border: 1px solid rgba(0,0,0,0.03);
    }
    .profile-details p {
      margin-bottom: 15px;
      font-size: 1rem;
      border-bottom: 1px dashed rgba(0,0,0,0.05);
      padding-bottom: 8px;
    }
    .profile-details p:last-child {
      margin-bottom: 0;
      border-bottom: none;
      padding-bottom: 0;
    }
    .profile-details strong {
      color: var(--indigo);
      display: inline-block;
      width: 140px;
    }
  </style>
</head>
<body>
  
  <nav class="navbar">
    <div class="container">
      <a href="maid_dashboard.php" class="logo">
        KAAM<span>WALI</span>
      </a>
      <div class="nav-links">
        <a href="index.php">Home</a>
        <span style="font-weight: 500; color: var(--indigo);">Maid Portal: <?php echo htmlspecialchars($maid_name); ?></span>
        <a href="logout.php" class="btn btn-outline" style="padding: 8px 20px; font-size: 0.9rem;">Logout</a>
      </div>
    </div>
  </nav>

  <div class="container dashboard-container">
    <div class="welcome-header">
      <h1>Welcome Back, <?php echo htmlspecialchars($maid_name); ?>!</h1>
      <p>Manage your incoming jobs and client requests.</p>
    </div>

    <?php if (!empty($message)): ?>
      <div class="alert-banner <?php echo ($msg_type === 'success') ? 'alert-success' : 'alert-error'; ?>">
        <?php echo htmlspecialchars($message); ?>
      </div>
    <?php endif; ?>

    <div class="dashboard-grid">
      <!-- Left Column: Status and Overview -->
      <div style="display: flex; flex-direction: column; gap: 30px;">
        <div class="dashboard-card" style="margin-bottom: 0;">
          <div>
            <h3 style="font-family: inherit; font-size: 1.2rem; color: var(--indigo); margin-bottom: 5px;">Your Current Status</h3>
            <p class="text-light" style="font-size: 0.9rem;">This controls whether clients can book you on the portal.</p>
          </div>
          <div>
            <span class="status-badge <?php echo ($current_status === 'Available') ? 'status-available' : 'status-busy'; ?>" style="font-size: 1rem; padding: 8px 16px;">
              <?php echo htmlspecialchars($current_status); ?>
            </span>
          </div>
        </div>

        <div class="profile-card">
          <h3 style="font-family: inherit; font-size: 1.3rem; color: var(--indigo); margin-bottom: 20px; border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom: 10px;">
            👤 Profile Overview
          </h3>
          <div class="profile-details">
            <p><strong>Full Name:</strong> <?php echo htmlspecialchars($maid_name_db); ?></p>
            <p><strong>Phone Number:</strong> <?php echo htmlspecialchars($maid_phone); ?></p>
            <p><strong>Age:</strong> <?php echo htmlspecialchars($maid_age); ?> Years</p>
            <p><strong>Address/Location:</strong> <?php echo htmlspecialchars($maid_location); ?></p>
            <p><strong>Services Offered:</strong> <?php echo htmlspecialchars($maid_services); ?></p>
          </div>
        </div>
      </div>

      <!-- Right Column: Edit Profile Form -->
      <div class="profile-card">
        <h3 style="font-family: inherit; font-size: 1.3rem; color: var(--indigo); margin-bottom: 20px; border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom: 10px;">
          ✏️ Edit Profile Info
        </h3>
        <form method="POST" action="maid_dashboard.php">
          <input type="hidden" name="action" value="update_profile">
          
          <div class="form-group">
            <label for="edit-name">Full Name</label>
            <input type="text" id="edit-name" name="name" class="form-control" value="<?php echo htmlspecialchars($maid_name_db); ?>" required>
          </div>

          <div class="form-group">
            <label for="edit-phone">Phone Number</label>
            <input type="tel" id="edit-phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($maid_phone); ?>" required>
          </div>

          <div class="form-group">
            <label for="edit-age">Age</label>
            <input type="number" id="edit-age" name="age" class="form-control" value="<?php echo htmlspecialchars($maid_age); ?>" required>
          </div>

          <div class="form-group">
            <label for="edit-location">Address / Location</label>
            <input type="text" id="edit-location" name="location" class="form-control" value="<?php echo htmlspecialchars($maid_location); ?>" required>
          </div>

          <div class="form-group">
            <label style="margin-bottom: 8px; display: block;">Services Offered</label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; padding: 5px 0;">
              <?php
              $selected_services = array_map('trim', explode(',', $maid_services));
              $services_list = ['Jhadu', 'Pocha', 'Roti', 'Cooking', 'Bartan', 'Kapde'];
              foreach ($services_list as $srv) {
                  $checked = in_array($srv, $selected_services) ? 'checked' : '';
                  echo '
                  <label style="font-weight: normal; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-dark);">
                    <input type="checkbox" name="services[]" value="' . $srv . '" style="width: auto;" ' . $checked . '> ' . $srv . '
                  </label>';
              }
              ?>
            </div>
          </div>

          <button type="submit" class="btn btn-secondary" style="width: 100%; margin-top: 10px;">Save Changes</button>
        </form>
      </div>
    </div>

    <h2 class="dashboard-section-title">Client Requests</h2>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Client Name</th>
            <th>Contact Phone</th>
            <th>Service Requested</th>
            <th>Request Date</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($requests_result && $requests_result->num_rows > 0): ?>
            <?php while ($req = $requests_result->fetch_assoc()): ?>
              <?php
                $status_badge_class = 'status-badge-pending';
                if ($req['status'] === 'Accepted') $status_badge_class = 'status-badge-accepted';
                if ($req['status'] === 'Rejected') $status_badge_class = 'status-badge-rejected';
                if ($req['status'] === 'Completed') $status_badge_class = 'status-badge-completed';
              ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($req['customer_name']); ?></strong></td>
                <td><a href="tel:<?php echo htmlspecialchars($req['customer_phone']); ?>" style="color: var(--indigo); text-decoration: underline;"><?php echo htmlspecialchars($req['customer_phone']); ?></a></td>
                <td><?php echo htmlspecialchars($req['service']); ?></td>
                <td><?php echo date('d M Y, h:i A', strtotime($req['created_at'])); ?></td>
                <td>
                  <span class="status-badge <?php echo $status_badge_class; ?>">
                    <?php echo htmlspecialchars($req['status']); ?>
                  </span>
                </td>
                <td>
                  <div class="action-group">
                    <?php if ($req['status'] === 'Pending'): ?>
                      <form method="POST" action="maid_dashboard.php" style="display:inline-block;">
                        <input type="hidden" name="booking_id" value="<?php echo $req['id']; ?>">
                        <input type="hidden" name="action" value="accept">
                        <button type="submit" class="btn-action btn-accept">Accept</button>
                      </form>
                      <form method="POST" action="maid_dashboard.php" style="display:inline-block;">
                        <input type="hidden" name="booking_id" value="<?php echo $req['id']; ?>">
                        <input type="hidden" name="action" value="reject">
                        <button type="submit" class="btn-action btn-reject">Reject</button>
                      </form>
                    <?php elseif ($req['status'] === 'Accepted'): ?>
                      <form method="POST" action="maid_dashboard.php" style="display:inline-block;">
                        <input type="hidden" name="booking_id" value="<?php echo $req['id']; ?>">
                        <input type="hidden" name="action" value="complete">
                        <button type="submit" class="btn-action btn-complete">Mark Completed</button>
                      </form>
                    <?php else: ?>
                      <span class="text-light" style="font-size: 0.85rem; font-style: italic;">No actions</span>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" style="text-align: center; color: var(--text-light); padding: 30px;">
                No incoming client requests found.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <footer style="margin-top: 100px;">
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
$requests_stmt->close();
?>
