<?php
require_once 'db.php';

$dashboard_link = '#';
$cta_link = 'register.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'admin') {
        $dashboard_link = 'admin.php';
    } elseif ($_SESSION['user_role'] === 'customer') {
        $dashboard_link = 'customer_dashboard.php';
    } else {
        $dashboard_link = 'maid_dashboard.php';
    }
    $cta_link = $dashboard_link;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KAAMWALI - Premium Maid Services</title>
  <meta name="description" content="Kaamwali offers premium and reliable maid services for Indian households including Jhadu, Pocha, Roti and more.">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  
  <nav class="navbar">
    <div class="container">
      <a href="index.php" class="logo">
        KAAM<span>WALI</span>
      </a>
      <div class="nav-links">
        <a href="#services">Services</a>
        <?php if (isset($_SESSION['user_id'])): ?>
          <span style="font-weight: 500; color: var(--indigo); font-size: 0.95rem;">Hi, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
          <a href="<?php echo $dashboard_link; ?>" class="btn btn-secondary text-white" style="text-decoration:none; padding: 8px 20px; font-size: 0.9rem;">Dashboard</a>
          <a href="logout.php" class="btn btn-outline" style="padding: 8px 20px; font-size: 0.9rem;">Logout</a>
        <?php else: ?>
          <a href="login.php">Login</a>
          <a href="register.php" class="btn btn-primary text-white" style="text-decoration:none;">Register</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <header class="hero container">
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    
    <div class="hero-content animate-on-scroll slide-right">
      <span class="hero-subtitle">Trusted by 10,000+ Indian Homes</span>
      <h1 class="heading-lg">Your Home,<br>Expertly Cared For.</h1>
      <p>Discover reliable, verified, and professional maids for your everyday household chores. From perfect round rotis to a sparkling clean floor, we handle it all with a premium touch.</p>
      <div class="hero-buttons">
        <a href="<?php echo $cta_link; ?>" class="btn btn-primary text-white">Find a Maid</a>
        <a href="#services" class="btn btn-outline">Explore Services</a>
      </div>
    </div>
    
    <div class="hero-image animate-on-scroll slide-left delay-200">
      <img src="hero.png" alt="Indian Maid Illustration">
    </div>
  </header>

  <section id="services" class="services">
    <div class="container">
      <div class="section-header animate-on-scroll slide-up">
        <h2 class="heading-md">Our Services</h2>
        <p>We provide comprehensive household services tailored to the Indian lifestyle, ensuring comfort and hygiene for your family.</p>
      </div>
      
      <div class="services-grid">
        <div class="service-card animate-on-scroll slide-up delay-100">
          <div class="service-icon">🧹</div>
          <h3>Sweeping & Mopping</h3>
          <p>Immaculate sweeping and mopping to keep your floors spotless and germ-free every single day.</p>
        </div>
        
        <div class="service-card animate-on-scroll slide-up delay-200">
          <div class="service-icon">🫓</div>
          <h3>Cooking & Roti</h3>
          <p>Expert cooks making authentic Indian food, from fluffy rotis to fulfilling multi-course meals.</p>
        </div>
        
        <div class="service-card animate-on-scroll slide-up delay-300">
          <div class="service-icon">🍽️</div>
          <h3>Utensils cleaning</h3>
          <p>Thorough dishwashing ensuring your utensils shine brighter without the hassle.</p>
        </div>
        
        <div class="service-card animate-on-scroll slide-up delay-400">
          <div class="service-icon">🧺</div>
          <h3>Laundry</h3>
          <p>Careful washing, drying, and folding of clothes to maintain fabric quality.</p>
        </div>
      </div>
    </div>
  </section>

  <footer>
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
