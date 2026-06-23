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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($phone) || empty($password)) {
        $error = 'Please enter both phone number and password.';
    } else {
        // Query user
        $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE phone = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $stmt->bind_result($id, $name, $hashed_password, $role);
        
        if ($stmt->fetch() && password_verify($password, $hashed_password)) {
            // Set session variables
            $_SESSION['user_id'] = $id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_role'] = $role;
            
            $stmt->close();

            // Redirect based on role
            if ($role === 'admin') {
                header("Location: admin.php");
            } elseif ($role === 'customer') {
                header("Location: customer_dashboard.php");
            } else {
                header("Location: maid_dashboard.php");
            }
            exit;
        } else {
            $error = 'Invalid Phone Number/Admin ID or Password.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - KAAMWALI</title>
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
        <a href="register.php">Register</a>
      </div>
    </div>
  </nav>

  <div class="auth-page">
    <div class="auth-wrapper glass-card">
      <div class="auth-header">
        <h2>Welcome Back</h2>
        <p class="text-light">Login to manage your household services</p>
      </div>

      <?php if (!empty($error)): ?>
        <div style="background: rgba(220, 53, 69, 0.1); color: #dc3545; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; border: 1px solid rgba(220, 53, 69, 0.2); text-align: center;">
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>
      
      <form id="login-form" method="POST" action="login.php">
        <div class="form-group">
          <label for="phone">Phone Number / Admin ID</label>
          <input type="text" id="phone" name="phone" class="form-control" placeholder="Enter your registered phone or 'admin'" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
        </div>
        
        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Login</button>
      </form>
      
      <div class="auth-footer">
        <p>Don't have an account? <a href="register.php">Register here</a></p>
        <p style="margin-top: 10px; font-size: 0.85rem;">Login as <strong>admin</strong> (Password: <strong>admin123</strong>) to access dashboard.</p>
      </div>
    </div>
  </div>

  <script src="app.js"></script>
</body>
</html>
