<?php
require_once 'db.php';

// Access control: must be admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$tab = $_GET['tab'] ?? 'maids';
if (!in_array($tab, ['maids', 'customers', 'bookings'])) {
    $tab = 'maids';
}

$message = '';
$msg_type = '';

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    $delete_id = intval($_POST['delete_id'] ?? 0);
    $type = $_POST['delete_type'] ?? '';
    
    if ($delete_id > 0) {
        if ($type === 'user') {
            // Delete a user (maid or customer). Bookings will be deleted cascade-wise.
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
            $stmt->bind_param("i", $delete_id);
            if ($stmt->execute()) {
                $message = "User account deleted successfully.";
                $msg_type = "success";
            } else {
                $message = "Failed to delete user account.";
                $msg_type = "error";
            }
            $stmt->close();
        } elseif ($type === 'booking') {
            $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
            $stmt->bind_param("i", $delete_id);
            if ($stmt->execute()) {
                $message = "Booking log deleted successfully.";
                $msg_type = "success";
            } else {
                $message = "Failed to delete booking.";
                $msg_type = "error";
            }
            $stmt->close();
        }
    }
}

// Data retrieval based on active tab
$maids = [];
$customers = [];
$bookings = [];

if ($tab === 'maids') {
    $res = $conn->query("SELECT id, name, phone, age, location, services, rating, status FROM users WHERE role = 'maid' ORDER BY id DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $maids[] = $row;
        }
    }
} elseif ($tab === 'customers') {
    $res = $conn->query("SELECT id, name, phone, created_at FROM users WHERE role = 'customer' ORDER BY id DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $customers[] = $row;
        }
    }
} elseif ($tab === 'bookings') {
    $res = $conn->query("
        SELECT b.id, c.name AS customer_name, m.name AS maid_name, b.service, b.status, b.created_at 
        FROM bookings b
        JOIN users c ON b.customer_id = c.id
        JOIN users m ON b.maid_id = m.id
        ORDER BY b.id DESC
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $bookings[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - KAAMWALI</title>
  <link rel="stylesheet" href="style.css">
  <style>
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
    .alert-banner {
      padding: 12px;
      border-radius: 6px;
      margin-bottom: 20px;
      font-size: 0.9rem;
      font-weight: 500;
      text-align: center;
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
    .trash-btn {
      background: none;
      border: none;
      color: #dc3545;
      cursor: pointer;
      font-size: 1.1rem;
      padding: 5px;
      transition: var(--transition);
    }
    .trash-btn:hover {
      color: #bd2130;
      transform: scale(1.1);
    }
  </style>
</head>
<body>
  
  <div class="dashboard">
    <aside class="sidebar">
      <div class="logo">KAAM<span>WALI</span></div>
      <nav class="sidebar-nav">
        <a href="admin.php?tab=maids" class="<?php echo ($tab === 'maids') ? 'active' : ''; ?>">👥 Manage Maids</a>
        <a href="admin.php?tab=customers" class="<?php echo ($tab === 'customers') ? 'active' : ''; ?>">👤 Manage Customers</a>
        <a href="admin.php?tab=bookings" class="<?php echo ($tab === 'bookings') ? 'active' : ''; ?>">📅 Bookings</a>
      </nav>
      <div style="margin-top: auto;">
        <a href="logout.php" class="btn btn-outline" style="border-color: rgba(255,255,255,0.2); color: white; width: 100%; text-align: center;">Logout</a>
      </div>
    </aside>
    
    <main class="main-content">
      <header class="dashboard-header">
        <div>
          <?php if ($tab === 'maids'): ?>
            <h2 class="heading-md">Maids Directory</h2>
            <p class="text-light">Manage all registered domestic workers</p>
          <?php elseif ($tab === 'customers'): ?>
            <h2 class="heading-md">Customers Directory</h2>
            <p class="text-light">Manage all registered clients</p>
          <?php else: ?>
            <h2 class="heading-md">System Bookings</h2>
            <p class="text-light">View and monitor all household service requests</p>
          <?php endif; ?>
        </div>
      </header>

      <?php if (!empty($message)): ?>
        <div class="alert-banner <?php echo ($msg_type === 'success') ? 'alert-success' : 'alert-error'; ?>">
          <?php echo htmlspecialchars($message); ?>
        </div>
      <?php endif; ?>
      
      <div class="table-container">
        <?php if ($tab === 'maids'): ?>
          <table>
            <thead>
              <tr>
                <th>Name</th>
                <th>Phone</th>
                <th>Age</th>
                <th>Location</th>
                <th>Services</th>
                <th>Rating</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($maids) > 0): ?>
                <?php foreach ($maids as $maid): ?>
                  <?php $statusClass = $maid['status'] === 'Available' ? 'status-available' : 'status-busy'; ?>
                  <tr>
                    <td><strong><?php echo htmlspecialchars($maid['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($maid['phone']); ?></td>
                    <td><?php echo htmlspecialchars($maid['age']); ?></td>
                    <td><?php echo htmlspecialchars($maid['location']); ?></td>
                    <td><?php echo htmlspecialchars($maid['services']); ?></td>
                    <td>⭐ <?php echo htmlspecialchars($maid['rating']); ?></td>
                    <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($maid['status']); ?></span></td>
                    <td>
                      <form method="POST" action="admin.php?tab=maids" onsubmit="return confirm('Are you sure you want to delete this maid?');">
                        <input type="hidden" name="delete_id" value="<?php echo $maid['id']; ?>">
                        <input type="hidden" name="delete_type" value="user">
                        <button type="submit" name="delete_item" class="trash-btn" title="Delete Account">🗑</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="8" style="text-align: center; color: var(--text-light); padding: 30px;">No maids registered yet.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>

        <?php elseif ($tab === 'customers'): ?>
          <table>
            <thead>
              <tr>
                <th>Name</th>
                <th>Phone</th>
                <th>Registration Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($customers) > 0): ?>
                <?php foreach ($customers as $cust): ?>
                  <tr>
                    <td><strong><?php echo htmlspecialchars($cust['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($cust['phone']); ?></td>
                    <td><?php echo date('d M Y, h:i A', strtotime($cust['created_at'])); ?></td>
                    <td>
                      <form method="POST" action="admin.php?tab=customers" onsubmit="return confirm('Are you sure you want to delete this customer?');">
                        <input type="hidden" name="delete_id" value="<?php echo $cust['id']; ?>">
                        <input type="hidden" name="delete_type" value="user">
                        <button type="submit" name="delete_item" class="trash-btn" title="Delete Account">🗑</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="4" style="text-align: center; color: var(--text-light); padding: 30px;">No customers registered yet.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>

        <?php elseif ($tab === 'bookings'): ?>
          <table>
            <thead>
              <tr>
                <th>Customer</th>
                <th>Maid</th>
                <th>Service Booked</th>
                <th>Booking Date</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($bookings) > 0): ?>
                <?php foreach ($bookings as $bk): ?>
                  <?php
                    $status_badge_class = 'status-badge-pending';
                    if ($bk['status'] === 'Accepted') $status_badge_class = 'status-badge-accepted';
                    if ($bk['status'] === 'Rejected') $status_badge_class = 'status-badge-rejected';
                    if ($bk['status'] === 'Completed') $status_badge_class = 'status-badge-completed';
                  ?>
                  <tr>
                    <td><strong><?php echo htmlspecialchars($bk['customer_name']); ?></strong></td>
                    <td><strong><?php echo htmlspecialchars($bk['maid_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($bk['service']); ?></td>
                    <td><?php echo date('d M Y, h:i A', strtotime($bk['created_at'])); ?></td>
                    <td><span class="status-badge <?php echo $status_badge_class; ?>"><?php echo htmlspecialchars($bk['status']); ?></span></td>
                    <td>
                      <form method="POST" action="admin.php?tab=bookings" onsubmit="return confirm('Are you sure you want to delete this booking log?');">
                        <input type="hidden" name="delete_id" value="<?php echo $bk['id']; ?>">
                        <input type="hidden" name="delete_type" value="booking">
                        <button type="submit" name="delete_item" class="trash-btn" title="Delete Booking Log">🗑</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" style="text-align: center; color: var(--text-light); padding: 30px;">No bookings recorded yet.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </main>
  </div>

  <script src="app.js"></script>
</body>
</html>
