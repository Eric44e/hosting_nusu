<?php
require_once 'config.php';
requireLogin();

if (isAjax()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_read') {
        $pdo->prepare("UPDATE notifications SET is_read=1 WHERE staff_id=?")->execute([$_SESSION['staff_id']]);
        jsonResponse(true,'All marked as read.');
    }
    jsonResponse(false,'Unknown');
}

// Mark all read when page loads
$pdo->prepare("UPDATE notifications SET is_read=1 WHERE staff_id=?")->execute([$_SESSION['staff_id']]);

$notifs = $pdo->prepare("SELECT * FROM notifications WHERE staff_id=? ORDER BY created_at DESC LIMIT 50");
$notifs->execute([$_SESSION['staff_id']]);
$notifs = $notifs->fetchAll();
$notifIcons = [
    'ticket'  => ['icon'=>'fas fa-ticket-alt',          'cls'=>'si-orange'],
    'stock'   => ['icon'=>'fas fa-exclamation-triangle', 'cls'=>'si-orange'],
    'payment' => ['icon'=>'fas fa-dollar-sign',          'cls'=>'si-green'],
    'message' => ['icon'=>'fas fa-comment-dots',         'cls'=>'si-purple'],
    'system'  => ['icon'=>'fas fa-cog',                  'cls'=>'si-cyan'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Notifications — ElectroServe Ltd</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="app-wrapper">
<?php include 'includes/layout.php'; ?>
<main class="main-content fade-in">
  <div class="page-header">
    <div class="page-header-left"><h1>Notifications</h1><p>System alerts and updates</p></div>
  </div>
  <div class="card" style="max-width:700px">
    <?php if(empty($notifs)): ?>
    <div class="empty-state"><i class="fas fa-bell-slash"></i><p>No notifications yet.</p></div>
    <?php else: foreach($notifs as $n):
      $ni = $notifIcons[$n['type']] ?? $notifIcons['system'];
    ?>
    <div class="notif-item" style="padding:1rem 1.4rem">
      <div class="notif-icon <?= $ni['cls'] ?>" style="width:42px;height:42px"><i class="<?= $ni['icon'] ?>"></i></div>
      <div class="notif-text" style="flex:1">
        <div class="notif-title" style="font-size:.9rem;font-weight:600"><?= htmlspecialchars($n['title']) ?></div>
        <div style="font-size:.82rem;color:var(--muted);margin-top:.15rem"><?= htmlspecialchars($n['body']) ?></div>
        <div class="notif-time" style="margin-top:.3rem"><?= timeAgo($n['created_at']) ?> · <?= date('M j, Y g:i A',strtotime($n['created_at'])) ?></div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</main>
</div>
<script src="assets/js/main.js"></script>
</body>
</html>
