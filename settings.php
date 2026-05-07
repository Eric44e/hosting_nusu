<?php
require_once 'config.php';
requireLogin();

if (isAjax()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name  = sanitize($_POST['full_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $dept  = sanitize($_POST['department'] ?? '');
        if (!$name) jsonResponse(false,'Name is required.');
        $pdo->prepare("UPDATE staff SET full_name=?,phone=?,department=? WHERE id=?")
            ->execute([$name,$phone,$dept,$_SESSION['staff_id']]);
        $_SESSION['name'] = $name;
        $stmt = $pdo->prepare("SELECT * FROM staff WHERE id=?"); $stmt->execute([$_SESSION['staff_id']]);
        $_SESSION['user'] = $stmt->fetch();
        jsonResponse(true,'Profile updated!');
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!$current || !$new || !$confirm) jsonResponse(false,'All fields are required.');
        if ($new !== $confirm) jsonResponse(false,'New passwords do not match.');
        if (strlen($new) < 6) jsonResponse(false,'Password must be at least 6 characters.');
        $stmt = $pdo->prepare("SELECT password FROM staff WHERE id=?"); $stmt->execute([$_SESSION['staff_id']]);
        $hash = $stmt->fetchColumn();
        if (!password_verify($current, $hash)) jsonResponse(false,'Current password is incorrect.');
        $pdo->prepare("UPDATE staff SET password=? WHERE id=?")->execute([password_hash($new, PASSWORD_BCRYPT), $_SESSION['staff_id']]);
        jsonResponse(true,'Password changed successfully!');
    }

    jsonResponse(false,'Unknown action');
}

$user = $pdo->prepare("SELECT * FROM staff WHERE id=?"); $user->execute([$_SESSION['staff_id']]); $user=$user->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Settings — NUSU Management System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
.settings-layout { 
  display: grid; 
  grid-template-columns: 280px 1fr; 
  gap: 2rem; 
  align-items: start; 
}

.settings-sidebar {
  position: sticky;
  top: 80px;
}

.settings-nav {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 1rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.settings-nav a { 
  display: flex; 
  align-items: center; 
  gap: 0.8rem; 
  padding: 0.75rem 1rem; 
  border-radius: 10px; 
  font-size: 0.9rem; 
  font-weight: 500;
  color: var(--muted); 
  transition: all .25s ease; 
  cursor: pointer;
  border: 2px solid transparent;
}

.settings-nav a:hover { 
  background: var(--primary-light); 
  color: var(--primary);
}

.settings-nav a.active { 
  background: linear-gradient(135deg, var(--primary-light), rgba(59,130,246,0.05));
  color: var(--primary); 
  border-color: var(--primary);
  font-weight: 600;
}

.settings-section { display: none; } 
.settings-section.active { display: block; animation: fadeIn .25s ease; }

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

.setting-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 2rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  transition: all .3s ease;
}

.setting-card:hover {
  box-shadow: 0 4px 16px rgba(0,0,0,0.08);
  border-color: var(--primary-light);
}

.setting-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid var(--border);
}

.setting-header h3 {
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--text);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.8rem;
}

.setting-header h3 i {
  color: var(--primary);
  font-size: 1.4rem;
}

.setting-header p {
  font-size: 0.85rem;
  color: var(--muted);
  margin: 0.25rem 0 0 0;
}

.setting-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.2rem 0;
  border-bottom: 1px solid var(--border);
  transition: background .2s ease;
}

.setting-item:last-child {
  border-bottom: none;
}

.setting-item:hover {
  background: var(--card2);
  padding: 1.2rem;
  margin: 0 -1.2rem;
  padding-left: 1.2rem;
  padding-right: 1.2rem;
  border-radius: 8px;
}

.setting-item-left h4 {
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--text);
  margin: 0 0 0.25rem 0;
}

.setting-item-left p {
  font-size: 0.85rem;
  color: var(--muted);
  margin: 0;
}

.toggle-switch {
  position: relative;
  display: inline-block;
  width: 52px;
  height: 28px;
  cursor: pointer;
}

.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
  cursor: pointer;
}

.toggle-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #ccc;
  transition: all .3s ease;
  border-radius: 34px;
}

.toggle-slider:before {
  position: absolute;
  content: "";
  height: 22px;
  width: 22px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: all .3s ease;
  border-radius: 50%;
  box-shadow: 0 2px 4px rgba(0,0,0,0.15);
}

input:checked + .toggle-slider {
  background: linear-gradient(135deg, var(--success), #10b981);
}

input:checked + .toggle-slider:before {
  transform: translateX(24px);
}

input:disabled + .toggle-slider {
  opacity: 0.5;
  cursor: not-allowed;
}

.profile-header {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  padding: 2rem;
  background: linear-gradient(135deg, var(--primary-light), rgba(59,130,246,0.05));
  border-radius: 14px;
  margin-bottom: 2rem;
  border: 1px solid rgba(59,130,246,0.2);
}

.profile-avatar {
  width: 90px;
  height: 90px;
  border-radius: 16px;
  border: 4px solid white;
  box-shadow: 0 4px 16px rgba(0,0,0,0.15);
  flex-shrink: 0;
}

.profile-info h2 {
  font-size: 1.4rem;
  font-weight: 800;
  color: var(--text);
  margin: 0 0 0.25rem 0;
  letter-spacing: -0.5px;
}

.profile-info p {
  color: var(--muted);
  font-size: 0.9rem;
  margin: 0.1rem 0;
}

.profile-info .badge-role {
  display: inline-block;
  margin-top: 0.75rem;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-top: 1.5rem;
}

.info-box {
  background: var(--card2);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 1.5rem;
  text-align: center;
  transition: all .3s ease;
}

.info-box:hover {
  border-color: var(--primary-light);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(59,130,246,0.1);
}

.info-box-label {
  font-size: 0.75rem;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.8px;
  margin-bottom: 0.75rem;
  font-weight: 700;
}

.info-box-value {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--text);
  letter-spacing: -0.5px;
}

.form-control {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid var(--border);
  border-radius: 10px;
  font-size: 0.9rem;
  font-family: inherit;
  transition: all .25s ease;
  background: var(--card2);
  color: var(--text);
}

.form-control:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
  background: var(--card);
}

.form-control:disabled,
.form-control[readonly] {
  cursor: not-allowed;
  opacity: 0.6;
}

.form-group {
  margin-bottom: 0;
}

.form-group label {
  display: block;
  font-weight: 600;
  margin-bottom: 0.5rem;
  font-size: 0.9rem;
  color: var(--text);
}

.danger-zone {
  background: rgba(239, 68, 68, 0.08);
  border: 2px solid rgba(239, 68, 68, 0.3);
  border-radius: 12px;
  padding: 1.5rem;
  margin-top: 2rem;
}

.danger-zone h4 {
  color: var(--danger);
  font-size: 1rem;
  margin: 0 0 0.5rem 0;
  font-weight: 700;
}

.danger-zone p {
  color: var(--muted);
  font-size: 0.85rem;
  margin: 0 0 1rem 0;
}

@media (max-width: 1024px) {
  .settings-layout {
    grid-template-columns: 1fr;
  }
  
  .settings-sidebar {
    position: relative;
    top: auto;
  }
  
  .settings-nav {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 0.5rem;
  }
}

@media (max-width: 768px) {
  .profile-header {
    flex-direction: column;
    text-align: center;
  }
  
  .info-grid {
    grid-template-columns: 1fr;
  }
  
  .setting-card {
    padding: 1.5rem;
  }
}

.setting-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid var(--border);
}

.setting-header h3 {
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--text);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.8rem;
}

.setting-header h3 i {
  color: var(--primary);
  font-size: 1.4rem;
}

.setting-header p {
  font-size: 0.85rem;
  color: var(--muted);
  margin: 0.25rem 0 0 0;
}

.setting-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.2rem 0;
  border-bottom: 1px solid var(--border);
  transition: background .2s ease;
}

.setting-item:last-child {
  border-bottom: none;
}

.setting-item:hover {
  background: var(--card2);
  padding: 1.2rem;
  margin: 0 -1.2rem;
  padding-left: 1.2rem;
  padding-right: 1.2rem;
  border-radius: 8px;
}
</style>
</head>
<body>
<div class="app-wrapper">
<?php include 'includes/layout.php'; ?>
<main class="main-content fade-in">
  <div class="page-header">
    <div class="page-header-left"><h1>Settings</h1><p>Account and system preferences</p></div>
  </div>

  <div class="settings-layout">
    <!-- Settings Sidebar Navigation -->
    <div class="settings-sidebar">
      <div class="settings-nav">
        <a href="#" class="active" onclick="showSection('profile',this)"><i class="fas fa-user-circle"></i> Profile</a>
        <a href="#" onclick="showSection('security',this)"><i class="fas fa-shield-alt"></i> Security</a>
        <a href="#" onclick="showSection('notifications',this)"><i class="fas fa-bell"></i> Notifications</a>
        <a href="#" onclick="showSection('preferences',this)"><i class="fas fa-sliders-h"></i> Preferences</a>
        <a href="#" onclick="showSection('system',this)"><i class="fas fa-server"></i> System Info</a>
      </div>
    </div>

    <div>
      <!-- Profile Section -->
      <div class="settings-section active" id="sec-profile">
        <!-- Profile Header Card -->
        <div class="setting-card">
          <div class="profile-header">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['full_name']) ?>&background=FF7A00&color=fff&size=80&bold=true" class="profile-avatar">
            <div class="profile-info">
              <h2><?= htmlspecialchars($user['full_name']) ?></h2>
              <p><?= $user['email'] ?></p>
              <p style="font-size:0.8rem;margin-top:0.5rem">Member since <?= date('M j, Y', strtotime($user['created_at'] ?? 'now')) ?></p>
              <span class="badge badge-primary" style="margin-top:0.5rem"><?= ucfirst($user['role']) ?></span>
            </div>
          </div>
        </div>

        <!-- Profile Details Card -->
        <div class="setting-card">
          <div class="setting-header">
            <div>
              <h3><i class="fas fa-edit"></i> Personal Information</h3>
              <p>Update your profile information</p>
            </div>
          </div>
          <form id="profileForm">
            <input type="hidden" name="action" value="update_profile">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem">
              <div class="form-group">
                <label style="font-weight:600;margin-bottom:0.5rem;display:block">Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" style="padding:0.75rem;border-radius:10px">
              </div>
              <div class="form-group">
                <label style="font-weight:600;margin-bottom:0.5rem;display:block">Phone Number</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']??'') ?>" placeholder="+250 7XX XXX XXX" style="padding:0.75rem;border-radius:10px">
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem">
              <div class="form-group">
                <label style="font-weight:600;margin-bottom:0.5rem;display:block">Email Address</label>
                <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" readonly style="opacity:0.6;padding:0.75rem;border-radius:10px;background:var(--card2)">
                <small style="color:var(--muted);font-size:0.75rem;margin-top:0.25rem">Email cannot be changed</small>
              </div>
              <div class="form-group">
                <label style="font-weight:600;margin-bottom:0.5rem;display:block">Department</label>
                <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($user['department']??'') ?>" placeholder="Your department" style="padding:0.75rem;border-radius:10px">
              </div>
            </div>
            <div class="form-group">
              <label style="font-weight:600;margin-bottom:0.5rem;display:block">Role</label>
              <input class="form-control" value="<?= ucfirst($user['role']) ?>" readonly style="opacity:0.6;padding:0.75rem;border-radius:10px;background:var(--card2)">
              <small style="color:var(--muted);font-size:0.75rem;margin-top:0.25rem">Contact admin to change role</small>
            </div>
            <div style="margin-top:1.5rem;display:flex;gap:1rem">
              <button type="button" class="btn btn-primary" onclick="saveProfile()"><i class="fas fa-check"></i> Save Changes</button>
              <button type="button" class="btn btn-outline" onclick="location.reload()"><i class="fas fa-redo"></i> Reset</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Security Section -->
      <div class="settings-section" id="sec-security">
        <!-- Password Card -->
        <div class="setting-card">
          <div class="setting-header">
            <div>
              <h3><i class="fas fa-key"></i> Change Password</h3>
              <p>Update your password to keep your account secure</p>
            </div>
          </div>
          <form id="passwordForm">
            <input type="hidden" name="action" value="change_password">
            <div class="form-group" style="margin-bottom:1.5rem">
              <label style="font-weight:600;margin-bottom:0.5rem;display:block">Current Password</label>
              <input type="password" name="current_password" class="form-control" placeholder="Enter your current password" style="padding:0.75rem;border-radius:10px">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem">
              <div class="form-group">
                <label style="font-weight:600;margin-bottom:0.5rem;display:block">New Password</label>
                <input type="password" name="new_password" class="form-control" placeholder="At least 6 characters" style="padding:0.75rem;border-radius:10px">
              </div>
              <div class="form-group">
                <label style="font-weight:600;margin-bottom:0.5rem;display:block">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password" style="padding:0.75rem;border-radius:10px">
              </div>
            </div>
            <div style="padding:1rem;background:var(--warning-light);border-radius:10px;font-size:0.85rem;color:var(--warning);margin-bottom:1.5rem;border-left:4px solid var(--warning)">
              <strong><i class="fas fa-lightbulb"></i> Security Tips:</strong>
              <ul style="margin:0.5rem 0 0 0;padding-left:1.5rem">
                <li>Use at least 6 characters (longer is better)</li>
                <li>Mix uppercase, lowercase, and numbers</li>
                <li>Avoid using common words or personal information</li>
              </ul>
            </div>
            <button type="button" class="btn btn-primary" style="background:var(--warning);border:none" onclick="savePassword()"><i class="fas fa-shield-alt"></i> Change Password</button>
          </form>
        </div>

        <!-- Security Info Card -->
        <div class="setting-card">
          <div class="setting-header">
            <div>
              <h3><i class="fas fa-lock"></i> Security Status</h3>
              <p>Monitor your account security</p>
            </div>
          </div>
          <div class="setting-item">
            <div class="setting-item-left">
              <h4>Two-Factor Authentication</h4>
              <p>Add an extra layer of security to your account</p>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" disabled>
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="setting-item">
            <div class="setting-item-left">
              <h4>Active Sessions</h4>
              <p>You have 1 active session</p>
            </div>
            <span class="badge badge-success">Active</span>
          </div>
          <div class="setting-item">
            <div class="setting-item-left">
              <h4>Last Password Change</h4>
              <p>Update your password regularly</p>
            </div>
            <span style="color:var(--muted);font-size:0.85rem">6 months ago</span>
          </div>
        </div>
      </div>

      <!-- Notifications Section -->
      <div class="settings-section" id="sec-notifications">
        <div class="setting-card">
          <div class="setting-header">
            <div>
              <h3><i class="fas fa-bell"></i> Notification Preferences</h3>
              <p>Choose how you receive notifications</p>
            </div>
          </div>
          <div class="setting-item">
            <div class="setting-item-left">
              <h4>Email Notifications</h4>
              <p>Receive important updates via email</p>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" checked>
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="setting-item">
            <div class="setting-item-left">
              <h4>Ticket Updates</h4>
              <p>Get notified when tickets are assigned or updated</p>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" checked>
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="setting-item">
            <div class="setting-item-left">
              <h4>Inventory Alerts</h4>
              <p>Low stock and inventory warnings</p>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" checked>
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="setting-item">
            <div class="setting-item-left">
              <h4>Report Summaries</h4>
              <p>Weekly digest of your activity reports</p>
            </div>
            <label class="toggle-switch">
              <input type="checkbox">
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="setting-item">
            <div class="setting-item-left">
              <h4>Desktop Notifications</h4>
              <p>Browser notifications for real-time updates</p>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" checked>
              <span class="toggle-slider"></span>
            </label>
          </div>
        </div>
      </div>

      <!-- Preferences Section -->
      <div class="settings-section" id="sec-preferences">
        <div class="setting-card">
          <div class="setting-header">
            <div>
              <h3><i class="fas fa-palette"></i> Display Preferences</h3>
              <p>Customize your experience</p>
            </div>
          </div>
          <div class="setting-item">
            <div class="setting-item-left">
              <h4>Dark Mode</h4>
              <p>Reduce eye strain with dark theme</p>
            </div>
            <label class="toggle-switch">
              <input type="checkbox">
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="setting-item">
            <div class="setting-item-left">
              <h4>Compact Sidebar</h4>
              <p>Minimize sidebar to save space</p>
            </div>
            <label class="toggle-switch">
              <input type="checkbox">
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="setting-item">
            <div class="setting-item-left">
              <h4>Show Tooltips</h4>
              <p>Display helpful tooltips on hover</p>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" checked>
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="setting-item">
            <div class="setting-item-left">
              <h4>Animations</h4>
              <p>Enable smooth UI transitions</p>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" checked>
              <span class="toggle-slider"></span>
            </label>
          </div>
        </div>

        <div class="setting-card">
          <div class="setting-header">
            <div>
              <h3><i class="fas fa-cog"></i> Data & Privacy</h3>
              <p>Control your data and privacy settings</p>
            </div>
          </div>
          <div class="setting-item">
            <div class="setting-item-left">
              <h4>Activity Logging</h4>
              <p>Track your account activity and logins</p>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" checked>
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="setting-item">
            <div class="setting-item-left">
              <h4>Analytics</h4>
              <p>Help us improve by sharing usage data</p>
            </div>
            <label class="toggle-switch">
              <input type="checkbox">
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="setting-item" style="border-bottom:none">
            <div class="setting-item-left">
              <h4>Data Export</h4>
              <p>Download a copy of your data</p>
            </div>
            <button class="btn btn-sm btn-outline"><i class="fas fa-download"></i> Export</button>
          </div>
        </div>
      </div>

      <!-- System Info Section -->
      <div class="settings-section" id="sec-system">
        <div class="setting-card">
          <div class="setting-header">
            <div>
              <h3><i class="fas fa-server"></i> System Information</h3>
              <p>Technical details about your installation</p>
            </div>
          </div>
          <div class="info-grid">
            <?php
            $sysInfo = [
              ['System Name',     APP_NAME,      'fas fa-cube'],
              ['Version',         APP_VERSION,   'fas fa-tag'],
              ['PHP Version',     phpversion(),  'fas fa-code'],
              ['Database',        'MySQL via PDO', 'fas fa-database'],
            ];
            foreach($sysInfo as [$k, $v, $icon]): ?>
            <div class="info-box">
              <div style="font-size:1.4rem;color:var(--primary);margin-bottom:0.5rem"><i class="<?= $icon ?>"></i></div>
              <div class="info-box-label"><?= $k ?></div>
              <div class="info-box-value" style="font-size:1rem;word-break:break-word"><?= $v ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="setting-card">
          <div class="setting-header">
            <div>
              <h3><i class="fas fa-chart-bar"></i> System Statistics</h3>
              <p>Current system usage and metrics</p>
            </div>
          </div>
          <div class="info-grid">
            <?php
            $stats = [
              ['Total Staff',     $pdo->query("SELECT COUNT(*) FROM staff WHERE status='active'")->fetchColumn(), 'fas fa-users'],
              ['Total Clients',   $pdo->query("SELECT COUNT(*) FROM clients WHERE status='active'")->fetchColumn(), 'fas fa-handshake'],
              ['Total Tickets',   $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn(), 'fas fa-ticket-alt'],
              ['Total Items',     $pdo->query("SELECT COUNT(*) FROM items WHERE status='active'")->fetchColumn(), 'fas fa-box'],
            ];
            foreach($stats as [$k, $v, $icon]): ?>
            <div class="info-box">
              <div style="font-size:1.4rem;color:var(--success);margin-bottom:0.5rem"><i class="<?= $icon ?>"></i></div>
              <div class="info-box-label"><?= $k ?></div>
              <div class="info-box-value"><?= $v ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="setting-card">
          <div class="setting-header">
            <div>
              <h3><i class="fas fa-clock"></i> Server Information</h3>
              <p>Server status and timestamp</p>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
            <div style="padding:1rem;background:var(--card2);border-radius:10px;border-left:4px solid var(--primary)">
              <div style="font-size:0.8rem;color:var(--muted);text-transform:uppercase;margin-bottom:0.25rem;font-weight:600">Current Date & Time</div>
              <div style="font-size:1.1rem;font-weight:700;color:var(--text)"><?= date('M j, Y') ?></div>
              <div style="font-size:0.9rem;color:var(--muted);margin-top:0.25rem"><?= date('H:i:s A') ?></div>
            </div>
            <div style="padding:1rem;background:var(--card2);border-radius:10px;border-left:4px solid var(--success)">
              <div style="font-size:0.8rem;color:var(--muted);text-transform:uppercase;margin-bottom:0.25rem;font-weight:600">Server Status</div>
              <div style="font-size:1.1rem;font-weight:700;color:var(--success)"><i class="fas fa-circle"></i> Online</div>
              <div style="font-size:0.9rem;color:var(--muted);margin-top:0.25rem">All systems operational</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

</main>
</div>
<script src="assets/js/main.js"></script>
<script>
function showSection(name, el) {
  // Prevent default link behavior
  event.preventDefault();
  
  // Update active navigation
  document.querySelectorAll('.settings-nav a').forEach(a=>a.classList.remove('active'));
  el.classList.add('active');
  
  // Update active sections
  document.querySelectorAll('.settings-section').forEach(s=>s.classList.remove('active'));
  document.getElementById('sec-'+name).classList.add('active');
}

async function saveProfile() {
  const fd = new FormData(document.getElementById('profileForm'));
  Notify.loading('Saving profile...');
  const res = await Ajax.post('settings.php', fd, true);
  Notify.close();
  if (res.success) {
    Notify.success('Success!', res.message);
    setTimeout(() => location.reload(), 1500);
  } else {
    Notify.error('Error', res.message);
  }
}

async function savePassword() {
  const newPwd = document.querySelector('input[name="new_password"]').value;
  const confirmPwd = document.querySelector('input[name="confirm_password"]').value;
  
  if (newPwd !== confirmPwd) {
    Notify.error('Error', 'Passwords do not match');
    return;
  }
  
  if (newPwd.length < 6) {
    Notify.error('Error', 'Password must be at least 6 characters');
    return;
  }
  
  const fd = new FormData(document.getElementById('passwordForm'));
  Notify.loading('Changing password...');
  const res = await Ajax.post('settings.php', fd, true);
  Notify.close();
  
  if (res.success) {
    Notify.success('Success!', res.message);
    document.getElementById('passwordForm').reset();
  } else {
    Notify.error('Error', res.message);
  }
}

// Add smooth scroll behavior
document.querySelectorAll('.settings-nav a').forEach(link => {
  link.addEventListener('click', (e) => {
    e.preventDefault();
    const section = link.getAttribute('data-section');
    showSection(section.replace('#sec-', ''), link);
  });
});
</script>
</body>
</html>
