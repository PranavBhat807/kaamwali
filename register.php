<?php
require_once 'db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'admin') {
        header("Location: admin.php");
    } elseif ($_SESSION['user_role'] === 'customer') {
        header("Location: customer_dashboard.php");
    } else {
        header("Location: maid_dashboard.php");
    }
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? '');

    if (empty($name) || empty($phone) || empty($password) || empty($role)) {
        $error = 'All fields are required.';
    } else {
        // Additional validation for maid
        $age = null;
        $location = null;
        $services = null;
        
        if ($role === 'maid') {
            $age = intval($_POST['age'] ?? 0);
            $location = trim($_POST['location'] ?? '');
            $services_arr = $_POST['services'] ?? [];
            
            if ($age <= 0) {
                $error = 'Please enter a valid age.';
            } elseif (empty($location)) {
                $error = 'Please enter your location.';
            } elseif (empty($services_arr)) {
                $error = 'Please select at least one service you offer.';
            } else {
                $services = implode(', ', $services_arr);
            }
        }

        if (empty($error)) {
            // Check if phone number already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->bind_param("s", $phone);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $error = 'This phone number is already registered.';
            }
            $stmt->close();
        }

        if (empty($error)) {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert into DB
            $stmt = $conn->prepare("INSERT INTO users (name, phone, password, role, age, location, services) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssiss", $name, $phone, $hashed_password, $role, $age, $location, $services);
            if ($stmt->execute()) {
                $success = 'Registration successful! Redirecting to login...';
            } else {
                $error = 'Something went wrong. Please try again.';
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - KAAMWALI</title>
  <link rel="stylesheet" href="style.css">
</head>
<body style="background: url('hero.png') no-repeat center center/cover;">
  
  <nav class="navbar">
    <div class="container">
      <a href="index.php" class="logo">
        KAAM<span>WALI</span>
      </a>
      <div class="nav-links">
        <a href="index.php">Home</a>
        <a href="login.php">Login</a>
      </div>
    </div>
  </nav>

  <div class="auth-page" style="padding-top: 120px;">
    <div class="auth-wrapper glass-card">
      <div class="auth-header">
        <h2>Create Account</h2>
        <p class="text-light">Join Kaamwali and simplify your life</p>
      </div>

      <?php if (!empty($error)): ?>
        <div style="background: rgba(220, 53, 69, 0.1); color: #dc3545; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; border: 1px solid rgba(220, 53, 69, 0.2); text-align: center;">
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div style="background: rgba(40, 167, 69, 0.1); color: #28a745; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; border: 1px solid rgba(40, 167, 69, 0.2); text-align: center;">
          <?php echo htmlspecialchars($success); ?>
          <script>setTimeout(function(){ window.location.href = 'login.php'; }, 2000);</script>
        </div>
      <?php endif; ?>
      
      <form id="register-form" method="POST" action="register.php">
        <div class="form-group">
          <label for="reg-name">Full Name</label>
          <input type="text" id="reg-name" name="name" class="form-control" placeholder="e.g. Ramesh Singh" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
          <label for="reg-phone">Phone Number</label>
          <input type="tel" id="reg-phone" name="phone" class="form-control" placeholder="Phone (e.g. 9876543210)" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
          <label for="reg-password">Password</label>
          <input type="password" id="reg-password" name="password" class="form-control" placeholder="Create a password" required>
        </div>
        
        <div class="form-group">
          <label for="role">Register As</label>
          <select id="role" name="role" class="form-control" required style="appearance: none;">
            <option value="customer" <?php echo (($_POST['role'] ?? '') === 'customer') ? 'selected' : ''; ?>>Looking for a Maid (Customer)</option>
            <option value="maid" <?php echo (($_POST['role'] ?? '') === 'maid') ? 'selected' : ''; ?>>Looking for Work (Maid)</option>
          </select>
        </div>

        <!-- Extra Maid Fields (hidden by default, shown when role is 'maid') -->
        <div id="maid-fields" style="<?php echo (($_POST['role'] ?? '') === 'maid') ? 'display: block;' : 'display: none;'; ?>">
          <div class="form-group">
            <label for="reg-age">Age</label>
            <input type="number" id="reg-age" name="age" class="form-control" placeholder="e.g. 34" value="<?php echo htmlspecialchars($_POST['age'] ?? ''); ?>">
          </div>
          
          <div class="form-group">
            <label for="reg-location">Location</label>
            <input type="text" id="reg-location" name="location" class="form-control" placeholder="e.g. Andheri West" value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>">
          </div>
          
          <div class="form-group">
            <label style="margin-bottom: 8px; display: block;">Services Offered</label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; padding: 5px 0;">
              <?php
              $selected_services = $_POST['services'] ?? [];
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
        </div>
        
        <button type="submit" class="btn btn-secondary" style="width: 100%; margin-top: 15px;">Sign Up</button>
      </form>
      
      <div class="auth-footer">
        <p>Already have an account? <a href="login.php">Login here</a></p>
      </div>
    </div>
  </div>

  <script src="app.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const roleSelect = document.getElementById('role');
      const maidFields = document.getElementById('maid-fields');
      const ageInput = document.getElementById('reg-age');
      const locationInput = document.getElementById('reg-location');

      function toggleFields() {
        if (roleSelect.value === 'maid') {
          maidFields.style.display = 'block';
          ageInput.setAttribute('required', 'required');
          locationInput.setAttribute('required', 'required');
        } else {
          maidFields.style.display = 'none';
          ageInput.removeAttribute('required');
          locationInput.removeAttribute('required');
        }
      }

      roleSelect.addEventListener('change', toggleFields);
      // Run once on load to handle pre-selected state after form submit error
      toggleFields();
    });
  </script>
</body>
</html>
