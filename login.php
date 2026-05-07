<?php
require_once 'config.php';
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAjax()) {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if (!$email || !$password) {
        jsonResponse(false, 'Email and password are required.');
    }
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE email = ? AND status != 'inactive'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password'])) {
        jsonResponse(false, 'Invalid email or password.');
    }
    $_SESSION['staff_id'] = $user['id'];
    $_SESSION['role']     = $user['role'];
    $_SESSION['name']     = $user['full_name'];
    $_SESSION['user']     = $user;
    jsonResponse(true, 'Login successful!', ['redirect' => 'dashboard.php']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — ElectroServe ERP</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
  --primary: #FF7A00;
  --primary-light: #ff8a50;
  --accent: #10b981;
  --danger: #ef4444;
  --dark: #0f172a;
  --text: #1f2937;
  --text-light: #6b7280;
}

* { margin: 0; padding: 0; box-sizing: border-box; }
html { font-size: 16px; }
body {
  font-family: 'DM Sans', sans-serif;
  background: #ffffff;
  color: var(--text);
  min-height: 100vh;
  overflow: hidden;
}

/* Main Wrapper */
.login-wrapper {
  display: flex;
  min-height: 100vh;
  width: 100%;
}

/* LEFT SIDE - Branding */
.login-left {
  flex: 1;
  background: linear-gradient(135deg, #FF8C00 0%, black 30%, black 70%, black 100%);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  align-items: flex-start;
  padding: 4rem 3rem;
  color: white;
  position: relative;
  overflow: hidden;
  box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
}

.login-left::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -50%;
  width: 600px;
  height: 600px;
  background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
  border-radius: 50%;
  z-index: 0;
}

.login-left::after {
  content: '';
  position: absolute;
  bottom: -30%;
  left: -30%;
  width: 500px;
  height: 500px;
  background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
  border-radius: 50%;
  z-index: 0;
}

.login-brand {
  position: relative;
  z-index: 1;
}

.login-brand h1 {
  font-family: 'Syne', sans-serif;
  font-size: 2.5rem;
  font-weight: 800;
  margin-bottom: 0.5rem;
  letter-spacing: -1px;
}

.login-brand p {
  font-size: 1.25rem;
  font-weight: 400;
  opacity: 0.95;
}

.login-features {
  position: relative;
  z-index: 1;
  width: 100%;
}

.feature-item {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.feature-icon {
  width: 40px;
  height: 40px;
  background: rgba(16, 185, 129, 0.25);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #10b981;
  font-size: 1.1rem;
  flex-shrink: 0;
  font-weight: 700;
  border: 2px solid rgba(16, 185, 129, 0.3);
}

.feature-text h3 {
  font-size: 1rem;
  font-weight: 600;
  margin-bottom: 0.25rem;
}

.feature-text p {
  font-size: 0.9rem;
  opacity: 0.9;
  line-height: 1.4;
}

.login-footer-brand {
  position: relative;
  z-index: 1;
  font-size: 0.85rem;
  opacity: 0.85;
}

/* RIGHT SIDE - Form */
.login-right {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
  background: #ffffff;
}

.login-card {
  width: 100%;
  max-width: 420px;
  padding: 0;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
  border-radius: 20px;
  background: white;
}

.login-header {
  margin-bottom: 2.5rem;
  text-align: center;
  padding: 2.5rem 2rem 0;
}

.login-icon {
  width: 64px;
  height: 64px;
  background: linear-gradient(135deg, #FF7A00, #FF7A00);
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  color: #FF7A00;
  margin: 0 auto 1rem;
}

.login-title {
  font-family: 'Syne', sans-serif;
  font-size: 1.75rem;
  font-weight: 700;
  margin-bottom: 0.5rem;
  color: var(--text);
}

.login-sub {
  font-size: 0.95rem;
  color: var(--text-light);
}

/* Form Styles */
.form-group {
  margin-bottom: 1.5rem;
}

.login-card form {
  padding: 0 2rem 2rem;
}

.form-group label {
  display: block;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text);
  margin-bottom: 0.5rem;
}

.form-group input {
  width: 100%;
  padding: 0.85rem 1rem;
  border: 1.5px solid #e5e7eb;
  border-radius: 10px;
  font-size: 0.95rem;
  font-weight: 500;
  transition: all 0.25s;
  font-family: 'DM Sans', sans-serif;
  color: var(--text);
}

.form-group input::placeholder {
  color: #9ca3af;
}

.form-group input:focus {
  outline: none;
  border-color: var(--primary-light);
  box-shadow: 0 0 0 3px rgba(255, 122, 0, 0.1);
  background-color: #f8faff;
}

.form-options {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.5rem;
  font-size: 0.85rem;
}

.remember-me {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  color: var(--text);
}

.remember-me input {
  width: 16px;
  height: 16px;
  cursor: pointer;
  accent-color:  #FF7A00;
}

.forgot-link {
  color: #ff8a50;
  font-weight: 600;
  text-decoration: none;
  transition: color 0.2s;
}

.forgot-link:hover {
  color: #FF7A00;
}

/* Submit Button */
.btn-login {
  width: 100%;
  padding: 1rem;
  background: linear-gradient(135deg, #FF7A00 0%, #FF7A00 100%);
  border: none;
  border-radius: 10px;
  color: white;
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.25s;
  box-shadow: 0 4px 15px rgba(255, 122, 0, 0.3);
  position: relative;
  overflow: hidden;
  font-family: 'DM Sans', sans-serif;
}

.btn-login::before {
  content: '';
  position: absolute;
  inset: 0;
  background: rgba(255, 255, 255, 0.1);
  opacity: 0;
  transition: opacity 0.25s;
}

.btn-login:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(255, 122, 0, 0.4);
}

.btn-login:hover::before {
  opacity: 1;
}

.btn-login:active {
  transform: translateY(0);
}

.btn-login:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.btn-login .spinner {
  width: 18px;
  height: 18px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #FF7A00;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  display: inline-block;
  margin-right: 0.5rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.login-footer {
  text-align: center;
  margin-top: 1.5rem;
  padding: 1.5rem 2rem;
  border-top: 1px solid #e5e7eb;
  font-size: 0.85rem;
  color: var(--text-light);
  background: #fafbfc;
  border-radius: 0 0 20px 20px;
}

.login-footer a {
  color: var(--primary-light);
  font-weight: 600;
  text-decoration: none;
}

.login-footer a:hover {
  text-decoration: underline;
}

/* Responsive */
@media (max-width: 1024px) {
  .login-left {
    padding: 3rem 2rem;
  }
  
  .login-brand h1 {
    font-size: 2rem;
  }
  
  .login-brand p {
    font-size: 1rem;
  }
}

@media (max-width: 768px) {
  .login-wrapper {
    flex-direction: column;
  }
  
  .login-left {
    padding: 2.5rem 2rem;
    min-height: 40vh;
    justify-content: flex-start;
  }
  
  .login-features {
    display: none;
  }
  
  .login-footer-brand {
    position: absolute;
    bottom: 1.5rem;
    left: 2rem;
    right: 2rem;
  }
  
  .login-right {
    min-height: 60vh;
  }
  
  .login-card {
    max-width: 100%;
  }
}

@media (max-width: 480px) {
  .login-left {
    padding: 2rem 1.5rem;
  }
  
  .login-brand h1 {
    font-size: 1.75rem;
  }
  
  .login-right {
    padding: 1.5rem;
  }
  
  .login-title {
    font-size: 1.5rem;
  }
}
</style>
</head>
<body>
<div class="login-wrapper">
  <!-- LEFT SIDE - Branding -->
  <div class="login-left">
    <div class="login-brand">
      <h1>NUSU</h1>
      <p>Business Management System</p>
    </div>
    
    <div class="login-features">
      <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-check"></i></div>
        <div class="feature-text">
          <h3>Inventory Management</h3>
          <p>Track stock levels, costs, and movements in real-time</p>
        </div>
      </div>
      
      <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-check"></i></div>
        <div class="feature-text">
          <h3>Service Tickets</h3>
          <p>Manage customer requests from creation to completion</p>
        </div>
      </div>
      
      <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-check"></i></div>
        <div class="feature-text">
          <h3>Financial Tracking</h3>
          <p>Automated billing, profit calculation, and tax management</p>
        </div>
      </div>
      
      <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-check"></i></div>
        <div class="feature-text">
          <h3>Real-time Analytics</h3>
          <p>Comprehensive dashboards and reporting tools</p>
        </div>
      </div>
    </div>
    
    <div class="login-footer-brand">
      © 2026 NUSU Business Management System. All rights reserved.
    </div>
  </div>
  
  <!-- RIGHT SIDE - Login Form -->
  <div class="login-right">
    <div class="login-card">
      <div class="login-header">
      
        <h2 class="login-title">Welcome Back!,👋</h2>
        <p class="login-sub">Sign in to your account to continue</p>
      </div>
      
      <form id="loginForm">
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" id="email" placeholder="admin@nusu.com" value="admin@nusu.rw" required autocomplete="email">
        </div>
        
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" id="password" placeholder="••••••••" value="password" required autocomplete="current-password">
        </div>
        
        <div class="form-options">
          <label class="remember-me">
            <input type="checkbox" checked>
            <span>Remember me</span>
          </label>
          <a href="#" class="forgot-link">Forgot password?</a>
        </div>
        
        <button type="submit" class="btn-login" id="loginBtn">
          <i class="fas fa-sign-in-alt" style="margin-right: 0.5rem;"></i> Sign In
        </button>
      </form>
      
      <div class="login-footer">
        <p>Need help? <a href="#">Contact Support</a></p>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('loginForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('loginBtn');
  const originalHTML = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Signing in...';
  const fd = new FormData(this);
  try {
    const res = await fetch('login.php', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} });
    const data = await res.json();
    if (data.success) {
      await Swal.fire({ 
        icon:'success', 
        title:'Welcome!', 
        text:data.message, 
        timer:1500, 
        showConfirmButton:false, 
        background:'#ffffff', 
        color:'#1f2937',
        toast: true,
        position: 'top-end'
      });
      window.location.href = data.redirect;
    } else {
      Swal.fire({ 
        icon:'error', 
        title:'Login Failed', 
        text:data.message, 
        background:'#ffffff', 
        color:'#1f2937', 
        confirmButtonColor:'#3b82f6' 
      });
      btn.disabled = false;
      btn.innerHTML = originalHTML;
    }
  } catch (err) {
    console.error('Login error:', err);
    Swal.fire({ 
      icon:'error', 
      title:'Error', 
      text:'Connection error. Please try again.', 
      background:'#ffffff', 
      color:'#1f2937' 
    });
    btn.disabled = false;
    btn.innerHTML = originalHTML;
  }
});
</script>
</body>
</html>
