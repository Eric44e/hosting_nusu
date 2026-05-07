<?php
require_once 'config.php';
require_once 'modules/TicketManager.php';
require_once 'modules/TimeTrackingManager.php';
requireLogin();
$id=(int)($_GET['id']??0);
if(!$id){header('Location:tickets.php');exit;}

if(isAjax()){
    $a=$_POST['action']??'';
    $sid=$_SESSION['staff_id'];
    $getStatus=function()use($pdo,$id){$s=$pdo->prepare('SELECT status FROM tickets WHERE id=?');$s->execute([$id]);return $s->fetchColumn();};

    if($a==='add_item'){
        if($getStatus()!=='assigned')jsonResponse(false,'Items can only be added in Assigned status.');
        $iid=(int)$_POST['item_id'];$qty=(int)$_POST['quantity'];
        $it=$pdo->prepare('SELECT * FROM items WHERE id=?');$it->execute([$iid]);$it=$it->fetch();
        if(!$it)jsonResponse(false,'Item not found.');
        if($it['quantity']<$qty)jsonResponse(false,'Only '.$it['quantity'].' in stock.');
        
        $pdo->beginTransaction();
        try {
            // Deduct from stock
            $pdo->prepare('UPDATE items SET quantity = quantity - ? WHERE id = ?')->execute([$qty, $iid]);
            
            // Insert into ticket items
            $pdo->prepare('INSERT INTO ticket_items(ticket_id,item_id,item_name,quantity,unit_price,total_price)VALUES(?,?,?,?,?,?)')->execute([$id,$iid,$it['name'],$qty,$it['selling_price'],$it['selling_price']*$qty]);
            
            // Record stock movement
            $tkNum = $pdo->query("SELECT ticket_number FROM tickets WHERE id=$id")->fetchColumn();
            $pdo->prepare("INSERT INTO stock_movements (item_id, ticket_id, type, quantity, reference, staff_id, notes) VALUES (?, ?, 'ticket_used', ?, ?, ?, 'Immediate deduction on ticket add')")->execute([$iid, $id, $qty, $tkNum, $sid]);
            
            // Update ticket totals
            $pdo->exec("UPDATE tickets SET material_cost=(SELECT COALESCE(SUM(total_price),0) FROM ticket_items WHERE ticket_id=$id),total_amount=service_cost+(SELECT COALESCE(SUM(total_price),0) FROM ticket_items WHERE ticket_id=$id)+labor_cost WHERE id=$id");
            
            $pdo->commit();
            jsonResponse(true,'Material added and deducted from stock.',['reload'=>true]);
        } catch(Exception $e) {
            $pdo->rollBack();
            jsonResponse(false,'Error adding material: '.$e->getMessage());
        }
    }
    if($a==='remove_item'){
        if($getStatus()!=='assigned')jsonResponse(false,'Cannot remove after confirmation.');
        $rowId = (int)$_POST['item_row_id'];
        $ti = $pdo->prepare('SELECT * FROM ticket_items WHERE id=? AND ticket_id=?');
        $ti->execute([$rowId, $id]);
        $ti = $ti->fetch();
        if(!$ti)jsonResponse(false,'Item not found on ticket.');

        $pdo->beginTransaction();
        try {
            // Return to stock
            $pdo->prepare('UPDATE items SET quantity = quantity + ? WHERE id = ?')->execute([$ti['quantity'], $ti['item_id']]);
            
            // Record movement
            $tkNum = $pdo->query("SELECT ticket_number FROM tickets WHERE id=$id")->fetchColumn();
            $pdo->prepare("INSERT INTO stock_movements (item_id, ticket_id, type, quantity, reference, staff_id, notes) VALUES (?, ?, 'restock', ?, ?, ?, 'Restored on item removal from ticket')")->execute([$ti['item_id'], $id, $ti['quantity'], $tkNum, $sid]);
            
            // Delete from ticket items
            $pdo->prepare('DELETE FROM ticket_items WHERE id=?')->execute([$rowId]);
            
            // Update ticket totals
            $pdo->exec("UPDATE tickets SET material_cost=(SELECT COALESCE(SUM(total_price),0) FROM ticket_items WHERE ticket_id=$id),total_amount=service_cost+(SELECT COALESCE(SUM(total_price),0) FROM ticket_items WHERE ticket_id=$id)+labor_cost WHERE id=$id");
            
            $pdo->commit();
            jsonResponse(true,'Item removed and returned to stock.',['reload'=>true]);
        } catch(Exception $e) {
            $pdo->rollBack();
            jsonResponse(false,'Error removing item: '.$e->getMessage());
        }
    }
    if($a==='confirm_materials'){
        $r=(new TicketManager($pdo))->confirmMaterials($id,$sid,true);
        jsonResponse((bool)$r,$r?'Materials confirmed! Present cost to client.':'Failed. Add items and ensure Assigned status.',['reload'=>(bool)$r]);
    }
    if($a==='start_timer'){
        if($getStatus()==='confirmed'){
            $pdo->exec("UPDATE tickets SET status='ongoing',started_at=NOW() WHERE id=$id");
            $pdo->prepare('INSERT INTO ticket_logs(ticket_id,status,notes,staff_id)VALUES(?,?,?,?)')->execute([$id,'ongoing','Service started - timer running',$sid]);
        }
        $r=(new TimeTrackingManager($pdo))->startTimer($id,$sid);
        jsonResponse($r['success'],$r['message'],['reload'=>true]);
    }
    if($a==='pause_timer'){$r=(new TimeTrackingManager($pdo))->pauseTimer($id,$sid);jsonResponse($r['success'],$r['message'],array_merge(['reload'=>true],$r));}
    if($a==='resume_timer'){$r=(new TimeTrackingManager($pdo))->resumeTimer($id,$sid);jsonResponse($r['success'],$r['message'],['reload'=>true]);}
    if($a==='stop_timer'){
        // If client sends its own counted seconds, use that directly (avoids server recalculation hang)
        $clientSecs = isset($_POST['client_total_seconds']) ? (int)$_POST['client_total_seconds'] : -1;
        $r = (new TimeTrackingManager($pdo))->stopTimer($id, $sid, $clientSecs > 0 ? $clientSecs : null);
        if($r['success']){
            $pdo->exec("UPDATE tickets SET status='completed',completed_at=NOW() WHERE id=$id");
            $pdo->prepare('INSERT INTO ticket_logs(ticket_id,status,notes,staff_id)VALUES(?,?,?,?)')->execute([$id,'completed','Service complete. Time: '.$r['formatted_time'],$sid]);
        }
        jsonResponse($r['success'],$r['message'],array_merge(['reload'=>true],$r));
    }
    if($a==='process_payment'){
        $amt=(float)$_POST['amount'];$meth=$_POST['payment_method']??'cash';
        if($amt<=0)jsonResponse(false,'Amount must be positive.');
        $r=(new TicketManager($pdo))->recordPaymentAndClose($id,$amt,$meth,$sid);
        if($r&&$r['success'])jsonResponse(true,$r['ticket_closed']?'Ticket closed successfully!':'Partial payment recorded.',['reload'=>true]);
        jsonResponse(false,'Payment processing failed.');
    }
    if($a==='deny_ticket'){
        $reason=sanitize($_POST['denial_reason']??'');
        if(!$reason)jsonResponse(false,'Denial reason is required.');
        $r=(new TicketManager($pdo))->denyTicket($id,$reason,$sid);
        if($r)jsonResponse(true,'Ticket denied.',['redirect'=>'tickets.php']);
        jsonResponse(false,'Cannot deny from current status.');
    }
    if($a==='add_note'){$n=sanitize($_POST['note']??'');if(!$n)jsonResponse(false,'Empty.');$pdo->prepare('INSERT INTO ticket_logs(ticket_id,status,notes,staff_id)VALUES(?,?,?,?)')->execute([$id,'note',$n,$sid]);jsonResponse(true,'Note added.',['reload'=>true]);}
    if($a==='assign_tech'){$tid=(int)$_POST['technician_id'];$pdo->prepare('UPDATE tickets SET technician_id=?,status=?,assigned_at=NOW() WHERE id=?')->execute([$tid,'assigned',$id]);$pdo->prepare('INSERT INTO ticket_logs(ticket_id,status,notes,staff_id)VALUES(?,?,?,?)')->execute([$id,'assigned','Technician assigned',$sid]);jsonResponse(true,'Assigned!',['reload'=>true]);}
    jsonResponse(false,'Unknown action');
}

$ticket=$pdo->prepare('SELECT t.*,c.full_name client_name,c.phone client_phone,c.email client_email,st.name service_name,s.full_name tech_name,tech.specialization,cr.full_name created_by_name FROM tickets t LEFT JOIN clients c ON c.id=t.client_id LEFT JOIN service_types st ON st.id=t.service_type_id LEFT JOIN technicians tech ON tech.id=t.technician_id LEFT JOIN staff s ON s.id=tech.staff_id LEFT JOIN staff cr ON cr.id=t.created_by WHERE t.id=?');
$ticket->execute([$id]);$ticket=$ticket->fetch();
if(!$ticket){header('Location:tickets.php');exit;}
$ticketItems=$pdo->prepare('SELECT ti.*,i.item_code FROM ticket_items ti LEFT JOIN items i ON i.id=ti.item_id WHERE ti.ticket_id=?');$ticketItems->execute([$id]);$ticketItems=$ticketItems->fetchAll();
$logs=$pdo->prepare('SELECT tl.*,s.full_name staff_name FROM ticket_logs tl LEFT JOIN staff s ON s.id=tl.staff_id WHERE tl.ticket_id=? ORDER BY tl.created_at DESC');$logs->execute([$id]);$logs=$logs->fetchAll();
$lastTimerAct=$pdo->prepare('SELECT action FROM ticket_time_logs WHERE ticket_id=? ORDER BY action_time DESC LIMIT 1');$lastTimerAct->execute([$id]);$lastTimerAct=$lastTimerAct->fetchColumn();
$invoice=$pdo->prepare('SELECT * FROM invoices WHERE ticket_id=? ORDER BY id DESC LIMIT 1');$invoice->execute([$id]);$invoice=$invoice->fetch();
$invItems=$pdo->query("SELECT id,item_code,name,selling_price,quantity,unit FROM items WHERE status='active' AND quantity>0 ORDER BY name")->fetchAll();
$techs=$pdo->query("SELECT t.id,s.full_name,t.specialization FROM technicians t JOIN staff s ON s.id=t.staff_id WHERE t.status='active'")->fetchAll();
$stockMoves=$pdo->prepare('SELECT sm.*,i.name item_name,i.item_code FROM stock_movements sm LEFT JOIN items i ON i.id=sm.item_id WHERE sm.ticket_id=? ORDER BY sm.created_at DESC');$stockMoves->execute([$id]);$stockMoves=$stockMoves->fetchAll();
$mat=array_sum(array_column($ticketItems,'total_price'));
$total=$ticket['service_cost']+$ticket['labor_cost']+$mat;
$st=$ticket['status'];
$running=($ticket['time_start']&&$st==='ongoing');
$paused=($lastTimerAct==='pause'&&$st==='ongoing');
$notStarted=($st==='ongoing'&&!$running&&!$paused);
$stepOrder=['pending','assigned','confirmed','ongoing','completed','closed'];
$curIdx=array_search($st,$stepOrder);
$logColors=['pending'=>'#f59e0b','assigned'=>'#1a6cff','confirmed'=>'#8b5cf6','ongoing'=>'#f97316','completed'=>'#22c55e','closed'=>'#6b7a99','denied'=>'#ef4444','note'=>'#a855f7'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $ticket['ticket_number'] ?> — NUSU LTD</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<style>
    .card { background: #000 !important; color: #fff !important; border: 1px solid rgba(255,255,255,0.1) !important; }
    .table-wrap { border: none !important; }
    table th { color: #FF7A00 !important; border-bottom: 1px solid rgba(255,255,255,0.1) !important; }
    table td { border-bottom: 1px solid rgba(255,255,255,0.05) !important; color: #fff !important; }
    .timeline-item { background: #111 !important; border: 1px solid rgba(255,255,255,0.05) !important; color: #fff !important; }
    .log-item { border-left: 3px solid #FF7A00 !important; background: #000 !important; color: #fff !important; margin-bottom: 0.5rem; padding: 0.8rem; }
    .btn-outline { color: #fff !important; border-color: rgba(255,255,255,0.2) !important; }
    .btn-outline:hover { background: #FF7A00 !important; border-color: #FF7A00 !important; }
    .badge-outline { border-color: rgba(255,255,255,0.1) !important; color: #fff !important; }
    h1, h2, h3, h4, h5 { color: #fff !important; }
    .info-label { color: #FF7A00 !important; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; }
    .info-value { color: #fff !important; font-weight: 600; }
</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
.step-bar{display:flex;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap}
.stp{display:flex;align-items:center;gap:.4rem;padding:.5rem 1.2rem;font-size:.8rem;font-weight:600;color:var(--muted);background:rgba(255,255,255,.04);border:1px solid var(--border)}
.stp:first-child{border-radius:10px 0 0 10px}.stp:last-child{border-radius:0 10px 10px 0}
.stp.done{background:var(--success-light);color:var(--success);border-color:var(--success)}
.stp.active{background:var(--primary-light);color:var(--primary);border-color:var(--primary)}
.stp.denied-stp{background:rgba(239,68,68,.12);color:#ef4444;border-color:#ef4444;border-radius:0 10px 10px 0}
.action-box{border-radius:14px;padding:1.3rem 1.5rem;margin-bottom:1.5rem;border:1.5px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.8rem}
.cost-row{display:flex;justify-content:space-between;align-items:center;padding:.55rem 0;border-bottom:1px solid var(--border);font-size:.9rem}
.cost-row:last-child{border-bottom:none;font-weight:700;font-size:1.05rem}
.info-lbl{font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:.2rem}
.info-val{font-size:.9rem;font-weight:500}
.timer-display{font-size:3.5rem;font-weight:800;text-align:center;font-family:'Courier New',monospace;letter-spacing:2px;margin:.5rem 0;display:flex;align-items:center;justify-content:center}
.timer-segment{display:inline-block;min-width:2ch;text-align:center;color:var(--accent);transition:color .3s}
.timer-colon{color:var(--muted);opacity:.5;margin:0 2px;font-size:3rem}
.timer-seconds{color:#f97316;font-size:3rem}
.timer-label{font-size:.68rem;text-transform:uppercase;letter-spacing:1px;color:var(--muted);font-weight:500}
.timer-dot{width:8px;height:8px;border-radius:50%;display:inline-block}
.timer-dot.running{background:#22c55e;animation:pulse-dot 1s infinite}
.timer-dot.paused{background:#f59e0b}
.timer-dot.idle{background:var(--muted)}
@keyframes pulse-dot{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}
.log-row{display:flex;gap:.8rem;padding:.65rem 0;border-bottom:1px solid var(--border)}
.log-row:last-child{border-bottom:none}
.log-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;margin-top:.35rem}
.denied-banner{background:rgba(239,68,68,.1);border:1px solid #ef4444;border-radius:12px;padding:1.2rem;margin-bottom:1.5rem;color:#ef4444}
@media print{.sidebar,.page-actions,.action-box,.modal-overlay{display:none!important}}
</style>
</head>
<body>
<div class="app-wrapper">
<?php include 'includes/layout.php'; ?>
<main class="main-content fade-in">

<div class="page-header">
  <div class="page-header-left">
    <h1><?= $ticket['ticket_number'] ?> <span style="font-size:1rem;font-weight:400;color:var(--muted)">— <?= htmlspecialchars($ticket['title']) ?></span></h1>
    <p>Created <?= date('M j, Y g:i A', strtotime($ticket['created_at'])) ?> by <?= htmlspecialchars($ticket['created_by_name'] ?? 'Admin') ?></p>
  </div>
  <div class="page-actions">
    <?= statusBadge($st) ?> <?= priorityBadge($ticket['priority']) ?>
    <button class="btn btn-outline btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
    <a href="tickets.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
</div>

<!-- STEP BAR -->
<div class="step-bar">
<?php
$steps=[['pending','fa-clock','Pending'],['assigned','fa-user-plus','Assigned'],['confirmed','fa-check-circle','Confirmed'],['ongoing','fa-play','In Progress'],['completed','fa-check','Completed'],['closed','fa-lock','Closed']];
foreach($steps as $i=>[$k,$ico,$lbl]):
  $cls='';
  if($st==='denied'){$cls=($i===0)?'done':'';}
  else{$cls=$i<$curIdx?'done':($i===$curIdx?'active':'');}
?><div class="stp <?= $cls ?>"><i class="fas <?= $ico ?>"></i> <?= $lbl ?></div>
<?php endforeach; ?>
<?php if($st==='denied'): ?><div class="stp denied-stp"><i class="fas fa-ban"></i> Denied</div><?php endif; ?>
</div>

<!-- DENIED BANNER -->
<?php if($st==='denied'&&$ticket['denial_reason']): ?>
<div class="denied-banner"><i class="fas fa-ban"></i> <strong>Ticket Denied</strong><br>
<span style="font-size:.9rem"><?= htmlspecialchars($ticket['denial_reason']) ?></span></div>
<?php endif; ?>

<!-- STATUS ACTION BOX -->
<?php if($st==='pending'): ?>
<div class="action-box" style="background:rgba(245,158,11,.06);border-color:#f59e0b">
  <div><b style="color:#f59e0b"><i class="fas fa-clock"></i> Awaiting Assignment</b><br>
  <small style="color:var(--muted)">System auto-assigns based on specialization &amp; workload. Admin can also assign manually.</small></div>
  <div style="display:flex;gap:.6rem;flex-wrap:wrap">
    <?php if(hasRole('admin','sales')): ?><button class="btn btn-primary btn-sm" onclick="Modal.open('assignModal')"><i class="fas fa-user-plus"></i> Assign Technician</button><?php endif; ?>
    <?php if(hasRole('admin')): ?><button class="btn btn-outline btn-sm" onclick="Modal.open('denyModal')"><i class="fas fa-ban"></i> Deny</button><?php endif; ?>
  </div>
</div>
<?php elseif($st==='assigned'): ?>
<div class="action-box" style="background:rgba(26,108,255,.06);border-color:var(--primary)">
  <div><b style="color:var(--primary)"><i class="fas fa-tools"></i> Material Selection Phase</b><br>
  <small style="color:var(--muted)">Add all required materials. Once done, confirm to lock in the cost and deduct from stock.</small></div>
  <div style="display:flex;gap:.6rem;flex-wrap:wrap">
    <button class="btn btn-primary btn-sm" onclick="Modal.open('addItemModal')"><i class="fas fa-plus"></i> Add Material</button>
    <?php if(!empty($ticketItems)): ?>
    <button class="btn btn-success btn-sm" onclick="confirmMaterials()"><i class="fas fa-check-circle"></i> Confirm Materials &amp; Cost</button>
    <?php else: ?>
    <span style="font-size:.82rem;color:var(--muted);padding:.4rem .8rem;background:var(--border);border-radius:8px"><i class="fas fa-info-circle"></i> Add at least one material to continue</span>
    <?php endif; ?>
    <button class="btn btn-outline btn-sm" onclick="Modal.open('denyModal')"><i class="fas fa-ban"></i> Deny</button>
  </div>
</div>
<?php elseif($st==='confirmed'): ?>
<div class="action-box" style="background:rgba(139,92,246,.07);border-color:#8b5cf6">
  <div><b style="color:#8b5cf6"><i class="fas fa-check-circle"></i> Cost Confirmed — Awaiting Client Approval</b><br>
  <small style="color:var(--muted)">Review the cost summary with the client. Once they confirm they can afford it, start the service timer.</small></div>
  <div style="display:flex;gap:.6rem;flex-wrap:wrap">
    <button class="btn btn-sm" style="background:#8b5cf6;color:#fff;border:none" onclick="clientConfirmAndStart()">
      <i class="fas fa-play-circle"></i> Client Confirms — Start Service Timer
    </button>
    <button class="btn btn-outline btn-sm" onclick="Modal.open('denyModal')"><i class="fas fa-ban"></i> Deny</button>
  </div>
</div>
<?php elseif($st==='ongoing'): ?>
<div class="action-box" style="background:rgba(249,115,22,.06);border-color:#f97316">
  <div><b style="color:#f97316"><i class="fas fa-stopwatch"></i> Service In Progress</b><br>
  <small style="color:var(--muted)">Timer is active. Pause when taking breaks. Stop &amp; Save when service is fully complete.</small></div>
  <div style="display:flex;gap:.6rem;flex-wrap:wrap" id="timerBtns">
    <?php if($notStarted): ?>
    <button class="btn btn-primary btn-sm" id="startBtn" onclick="startTimer()"><i class="fas fa-play"></i> Start Timer</button>
    <?php elseif($running): ?>
    <button class="btn btn-warning btn-sm" onclick="pauseTimer()"><i class="fas fa-pause"></i> Pause</button>
    <button class="btn btn-success btn-sm" onclick="stopTimer()"><i class="fas fa-stop-circle"></i> Stop &amp; Save Time</button>
    <?php elseif($paused): ?>
    <button class="btn btn-info btn-sm" onclick="resumeTimer()"><i class="fas fa-redo"></i> Resume</button>
    <button class="btn btn-success btn-sm" onclick="stopTimer()"><i class="fas fa-stop-circle"></i> Stop &amp; Save Time</button>
    <?php endif; ?>
  </div>
</div>
<?php elseif($st==='completed'): ?>
<div class="action-box" style="background:rgba(34,197,94,.06);border-color:var(--success)">
  <div><b style="color:var(--success)"><i class="fas fa-check-double"></i> Service Completed — Ready for Payment</b><br>
  <small style="color:var(--muted)">Service time logged. Process payment to close this ticket and record revenue.</small></div>
  <div style="display:flex;gap:.6rem">
    <button class="btn btn-success btn-sm" onclick="Modal.open('paymentModal')"><i class="fas fa-money-bill-wave"></i> Process Payment</button>
    <?php if($invoice): ?><a href="invoice_print.php?id=<?= $invoice['id'] ?>" target="_blank" class="btn btn-outline btn-sm"><i class="fas fa-file-invoice"></i> Invoice</a><?php endif; ?>
  </div>
</div>
<?php elseif($st==='closed'): ?>
<div class="action-box" style="background:rgba(107,122,153,.08);border-color:var(--border)">
  <div><b style="color:var(--muted)"><i class="fas fa-lock"></i> Ticket Closed</b><br>
  <small style="color:var(--muted)">Payment received. Revenue recorded on dashboard.</small></div>
  <?php if($invoice): ?><a href="invoice_print.php?id=<?= $invoice['id'] ?>" target="_blank" class="btn btn-outline btn-sm"><i class="fas fa-file-invoice"></i>Bill Now</a><?php endif; ?>
</div>
<?php endif; ?>

<!-- TIMER DISPLAY (ongoing only) -->
<?php if($st==='ongoing'):
  // Accumulated = previously saved seconds from paused sessions
  $totalAccumulatedSeconds = (int)($ticket['time_total_minutes'] ?? 0);

  // How many seconds have elapsed since the current timer was started
  $liveElapsedSeconds = 0;
  // Unix timestamp (seconds) of when this session started — passed to JS
  $sessionStartUnix = 0;
  if ($running && $ticket['time_start']) {
      // Use Africa/Kigali timestamps for consistency with DB session
      $tz        = new DateTimeZone('Africa/Kigali');
      $startTime = new DateTime($ticket['time_start'], $tz);
      $now       = new DateTime('now', $tz);
      $liveElapsedSeconds = max(0, $now->getTimestamp() - $startTime->getTimestamp());
      $sessionStartUnix   = $startTime->getTimestamp();
  }

  // Total for the initial render (accumulated + live so far)
  $totalSeconds = $totalAccumulatedSeconds + $liveElapsedSeconds;
  $dispH = floor($totalSeconds / 3600);
  $dispM = floor(($totalSeconds % 3600) / 60);
  $dispS = $totalSeconds % 60;
  
  // Ensure totalSeconds is passed to JS even if not running
  $initialTotalSeconds = $totalSeconds;
?>
<div class="card" style="margin-bottom:1.2rem;text-align:center">
  <div class="card-body" style="padding:1.5rem">
    <div class="timer-display" id="timerDisplay">
      <span class="timer-segment" id="timerH"><?= sprintf('%02d', $dispH) ?></span><span class="timer-colon">:</span><span class="timer-segment" id="timerM"><?= sprintf('%02d', $dispM) ?></span><span class="timer-colon">:</span><span class="timer-segment timer-seconds" id="timerS"><?= sprintf('%02d', $dispS) ?></span>
    </div>
    <div style="display:flex;justify-content:center;gap:2.5rem;margin-top:.3rem">
      <span class="timer-label">Hours</span>
      <span class="timer-label">Minutes</span>
      <span class="timer-label">Seconds</span>
    </div>
    <div style="font-size:.82rem;color:var(--muted);margin-top:.6rem;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.3rem" id="timerStatus">
      <div><?php if($running): ?><span class="timer-dot running"></span> Timer running<?php elseif($paused): ?><span class="timer-dot paused"></span> Timer paused<?php else: ?><span class="timer-dot idle"></span> Ready to start<?php endif; ?></div>
      <?php if($running): ?>
      <div id="sessionTimeLabel" style="color:var(--primary);font-weight:600;font-size:.75rem">Active Session: 00:00:00</div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="grid-2" style="align-items:start">

<!-- LEFT COLUMN -->
<div style="display:flex;flex-direction:column;gap:1.2rem">

<!-- Ticket Info -->
<div class="card">
  <div class="card-header">
    <div class="card-title">Ticket Information</div>
    <?php if(!$ticket['technician_id']&&in_array($st,['pending','assigned'])&&hasRole('admin','sales')): ?>
    <button class="btn btn-sm btn-primary" onclick="Modal.open('assignModal')"><i class="fas fa-user-plus"></i> Assign</button>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem">
      <div><div class="info-lbl">Client</div><div class="info-val"><?= htmlspecialchars($ticket['client_name']) ?></div></div>
      <div><div class="info-lbl">Phone</div><div class="info-val"><?= htmlspecialchars($ticket['client_phone']??'—') ?></div></div>
      <div><div class="info-lbl">Service Type</div><div class="info-val"><?= htmlspecialchars($ticket['service_name']??'—') ?></div></div>
      <div><div class="info-lbl">Priority</div><div class="info-val"><?= priorityBadge($ticket['priority']) ?></div></div>
      <div style="grid-column:span 2"><div class="info-lbl">Location</div><div class="info-val"><?= htmlspecialchars($ticket['location']??'—') ?></div></div>
      <?php if($ticket['description']): ?>
      <div style="grid-column:span 2"><div class="info-lbl">Description</div><div class="info-val" style="color:var(--muted);font-size:.87rem"><?= nl2br(htmlspecialchars($ticket['description'])) ?></div></div>
      <?php endif; ?>
      <div><div class="info-lbl">Technician</div><div class="info-val"><?= $ticket['tech_name']?htmlspecialchars($ticket['tech_name']):'<span style="color:var(--muted)">Unassigned</span>' ?></div></div>
      <div><div class="info-lbl">Specialization</div><div class="info-val"><?= htmlspecialchars($ticket['specialization']??'—') ?></div></div>
    </div>
    <?php if($ticket['assigned_at']): ?>
    <div style="margin-top:.8rem;padding:.5rem .8rem;background:var(--primary-light);border-radius:8px;font-size:.78rem;color:var(--primary)">
      <i class="fas fa-calendar-check"></i> Assigned <?= date('M j, Y g:i A',strtotime($ticket['assigned_at'])) ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Materials -->
<div class="card">
  <div class="card-header">
    <div class="card-title">Materials Used
      <?php if($ticket['material_confirmed']): ?>
      <span style="font-size:.72rem;color:var(--success);margin-left:.5rem"><i class="fas fa-lock"></i> Locked</span>
      <?php endif; ?>
    </div>
    <?php if($st==='assigned'): ?>
    <button class="btn btn-sm btn-primary" onclick="Modal.open('addItemModal')"><i class="fas fa-plus"></i> Add</button>
    <?php endif; ?>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Item</th><th>Qty</th><th>Unit Price</th><th>Total</th><?php if($st==='assigned'): ?><th></th><?php endif; ?></tr></thead>
      <tbody>
      <?php if(empty($ticketItems)): ?>
      <tr><td colspan="5"><div class="empty-state" style="padding:.8rem"><p style="font-size:.83rem">No materials added yet</p></div></td></tr>
      <?php else: foreach($ticketItems as $ti): ?>
      <tr>
        <td><?= htmlspecialchars($ti['item_name']) ?><br><small style="color:var(--muted)"><?= $ti['item_code']??'' ?></small></td>
        <td><?= $ti['quantity'] ?></td>
        <td><?= formatMoney($ti['unit_price']) ?></td>
        <td style="font-weight:600"><?= formatMoney($ti['total_price']) ?></td>
        <?php if($st==='assigned'): ?>
        <td><button class="btn btn-sm btn-danger btn-icon" onclick="removeItem(<?= $ti['id'] ?>)"><i class="fas fa-trash"></i></button></td>
        <?php endif; ?>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Note -->
<div class="card">
  <div class="card-header"><div class="card-title">Add Note</div></div>
  <div class="card-body">
    <textarea id="noteText" class="form-control" rows="3" placeholder="Write a service note..."></textarea>
    <button class="btn btn-primary btn-sm" style="margin-top:.6rem" onclick="addNote()"><i class="fas fa-plus"></i> Add Note</button>
  </div>
</div>

</div><!-- /left -->

<!-- RIGHT COLUMN -->
<div style="display:flex;flex-direction:column;gap:1.2rem">

<!-- Cost Summary -->
<div class="card">
  <div class="card-header"><div class="card-title">Cost Summary</div></div>
  <div class="card-body">
    <div class="cost-row"><span style="color:var(--muted)">Service Cost</span><span><?= formatMoney($ticket['service_cost']) ?></span></div>
    <div class="cost-row"><span style="color:var(--muted)">Material Cost</span><span><?= formatMoney($mat) ?></span></div>
    <?php if($ticket['labor_cost']>0): ?>
    <div class="cost-row"><span style="color:var(--muted)">Labor Cost</span><span><?= formatMoney($ticket['labor_cost']) ?></span></div>
    <?php endif; ?>
    <div class="cost-row"><span>Total Amount</span><span style="color:var(--accent);font-size:1.2rem"><?= formatMoney($total) ?></span></div>
    <?php if($invoice): ?>
    <div class="cost-row" style="margin-top:.3rem"><span style="color:var(--muted)">Paid</span><span style="color:var(--success)"><?= formatMoney($invoice['paid_amount']) ?></span></div>
    <div class="cost-row"><span style="color:var(--muted)">Balance</span><span style="color:<?= $invoice['balance']>0?'var(--danger)':'var(--success)' ?>"><?= formatMoney($invoice['balance']) ?></span></div>
    <?php endif; ?>
  </div>
  <?php if(!$ticket['material_confirmed']&&$st==='assigned'): ?>
  <div style="padding:.7rem 1.4rem;border-top:1px solid var(--border);font-size:.82rem;color:var(--warning);background:rgba(245,158,11,.06)">
    <i class="fas fa-hourglass-half"></i> Final cost will be confirmed after material selection
  </div>
  <?php endif; ?>
</div>

<!-- Key Dates -->
<div class="card">
  <div class="card-header"><div class="card-title">Timeline</div></div>
  <div class="card-body">
    <?php foreach([['Created',$ticket['created_at']],['Assigned',$ticket['assigned_at']],['Confirmed',$ticket['confirmed_at']??null],['Started',$ticket['started_at']],['Completed',$ticket['completed_at']],['Closed',$ticket['closed_at']]] as [$lbl,$dt]): ?>
    <div class="cost-row" style="font-size:.84rem">
      <span style="color:var(--muted)"><?= $lbl ?></span>
      <span><?= $dt?date('M j, Y g:i A',strtotime($dt)):'—' ?></span>
    </div>
    <?php endforeach; ?>
    <?php if($ticket['time_total_minutes']>0): ?>
    <div class="cost-row" style="font-size:.84rem">
      <span style="color:var(--muted)">Total Service Time</span>
      <span style="color:var(--accent);font-weight:600"><?php
          $h = floor($ticket['time_total_minutes'] / 3600);
          $m = floor(($ticket['time_total_minutes'] % 3600) / 60);
          echo $h.'h '.$m.'m';
      ?></span>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Stock Movements (if any) -->
<?php if(!empty($stockMoves)): ?>
<div class="card">
  <div class="card-header"><div class="card-title">Stock Deducted</div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Item</th><th>Qty Used</th><th>Date</th></tr></thead>
      <tbody>
      <?php foreach($stockMoves as $sm): ?>
      <tr>
        <td><?= htmlspecialchars($sm['item_name']??'—') ?><br><small style="color:var(--muted)"><?= $sm['item_code']??'' ?></small></td>
        <td style="font-weight:600;color:#8b5cf6"><?= $sm['quantity'] ?></td>
        <td style="font-size:.78rem;color:var(--muted)"><?= date('M j, Y',strtotime($sm['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Activity Log -->
<div class="card">
  <div class="card-header"><div class="card-title">Activity Timeline</div></div>
  <div class="card-body" style="max-height:380px;overflow-y:auto">
    <?php if(empty($logs)): ?>
    <div class="empty-state" style="padding:.8rem"><p>No activity yet</p></div>
    <?php else: foreach($logs as $log): $col=$logColors[$log['status']]??'#6b7a99'; ?>
    <div class="log-row">
      <div class="log-dot" style="background:<?= $col ?>"></div>
      <div>
        <div style="font-size:.85rem;font-weight:500"><?= htmlspecialchars($log['notes']) ?></div>
        <div style="font-size:.74rem;color:var(--muted);margin-top:.15rem">
          <?= htmlspecialchars($log['staff_name']??'System') ?> · <?= timeAgo($log['created_at']) ?>
        </div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

</div><!-- /right -->
</div><!-- /grid -->
</main>
</div>

<!-- MODALS -->

<!-- Add Item Modal -->
<div class="modal-overlay" id="addItemModal">
<div class="modal">
  <div class="modal-header"><span class="modal-title"><i class="fas fa-plus-circle" style="color:var(--primary)"></i> Add Material</span><button class="modal-close"><i class="fas fa-times"></i></button></div>
  <div class="modal-body">
    <form id="addItemForm">
      <input type="hidden" name="action" value="add_item">
      <div class="form-group"><label>Select Item *</label>
        <select name="item_id" class="form-control" required id="itemSelect" onchange="updateItemPrice()">
          <option value="">Choose inventory item</option>
          <?php foreach($invItems as $item): ?>
          <option value="<?= $item['id'] ?>" data-price="<?= $item['selling_price'] ?>" data-stock="<?= $item['quantity'] ?>">
            <?= htmlspecialchars($item['name']) ?> — Stock: <?= $item['quantity'] ?> <?= $item['unit'] ?>
          </option>
          <?php endforeach; ?>
        </select></div>
      <div class="form-row">
        <div class="form-group"><label>Quantity *</label>
          <input type="number" name="quantity" id="itemQty" class="form-control" min="1" value="1" required oninput="updateItemTotal()"></div>
        <div class="form-group"><label>Unit Price</label>
          <input type="text" id="itemUnitPrice" class="form-control" readonly style="opacity:.6"></div>
      </div>
      <div style="padding:.8rem;background:var(--primary-light);border-radius:10px;font-size:.85rem">
        <b>Line Total:</b> <span id="itemTotal" style="color:var(--primary);font-weight:700">FRW 0</span>
      </div>
    </form>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline" onclick="Modal.close('addItemModal')">Cancel</button>
    <button class="btn btn-primary" onclick="submitAddItem()"><i class="fas fa-plus"></i> Add Item</button>
  </div>
</div></div>

<!-- Deny Modal -->
<div class="modal-overlay" id="denyModal">
<div class="modal">
  <div class="modal-header"><span class="modal-title" style="color:#ef4444"><i class="fas fa-ban"></i> Deny Ticket</span><button class="modal-close"><i class="fas fa-times"></i></button></div>
  <div class="modal-body">
    <div style="padding:.8rem;background:rgba(239,68,68,.1);border-radius:8px;color:#ef4444;font-size:.85rem;margin-bottom:1rem">
      <i class="fas fa-exclamation-circle"></i> This action will deny the ticket and cannot be undone easily.
    </div>
    <div class="form-group"><label><strong>Reason for Denial *</strong></label>
      <textarea id="denialReason" class="form-control" rows="4" placeholder="Explain why this ticket is being denied..."></textarea>
      <small style="color:var(--muted)">This reason will be logged and visible in the ticket history.</small></div>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline" onclick="Modal.close('denyModal')">Cancel</button>
    <button class="btn btn-danger" onclick="submitDeny()"><i class="fas fa-ban"></i> Deny Ticket</button>
  </div>
</div></div>

<!-- Payment Modal -->
<div class="modal-overlay" id="paymentModal">
<div class="modal">
  <div class="modal-header"><span class="modal-title" style="color:var(--success)"><i class="fas fa-money-bill-wave"></i> Process Payment</span><button class="modal-close"><i class="fas fa-times"></i></button></div>
  <div class="modal-body">
    <div style="padding:.8rem;background:var(--success-light);border-radius:8px;margin-bottom:1rem;font-size:.9rem">
      Total Due: <strong style="color:var(--success);font-size:1.1rem"><?= formatMoney($total) ?></strong>
      <?php if($invoice&&$invoice['balance']<$total): ?>
      &nbsp;| Balance: <strong style="color:var(--danger)"><?= formatMoney($invoice['balance']) ?></strong>
      <?php endif; ?>
    </div>
    <form id="paymentForm">
      <input type="hidden" name="action" value="process_payment">
      <div class="form-row">
        <div class="form-group"><label>Payment Amount (FRW) *</label>
          <input type="number" name="amount" id="payAmount" class="form-control" min="1" step="0.01" value="<?= $invoice?$invoice['balance']:$total ?>" required></div>
        <div class="form-group"><label>Payment Method *</label>
          <select name="payment_method" class="form-control">
            <option value="cash">Cash</option>
            <option value="card">Card</option>
            <option value="transfer">Bank Transfer</option>
            <option value="mobile">Mobile Money</option>
          </select></div>
      </div>
    </form>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline" onclick="Modal.close('paymentModal')">Cancel</button>
    <button class="btn btn-success" onclick="submitPayment()"><i class="fas fa-check"></i> Confirm Payment</button>
  </div>
</div></div>

<!-- Assign Modal -->
<div class="modal-overlay" id="assignModal">
<div class="modal">
  <div class="modal-header"><span class="modal-title"><i class="fas fa-user-plus" style="color:var(--success)"></i> Assign Technician</span><button class="modal-close"><i class="fas fa-times"></i></button></div>
  <div class="modal-body">
    <div class="form-group"><label>Select Technician</label>
      <select id="assignTechId" class="form-control">
        <?php foreach($techs as $t): ?>
        <option value="<?= $t['id'] ?>" <?= $t['id']==$ticket['technician_id']?'selected':'' ?>>
          <?= htmlspecialchars($t['full_name']) ?> — <?= htmlspecialchars($t['specialization']??'') ?>
        </option>
        <?php endforeach; ?>
      </select></div>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline" onclick="Modal.close('assignModal')">Cancel</button>
    <button class="btn btn-success" onclick="submitAssign()"><i class="fas fa-check"></i> Assign</button>
  </div>
</div></div>

<!-- SCRIPTS -->
<script src="assets/js/main.js"></script>
<script>
const ticketId = <?= $id ?>;

function updateItemPrice(){
  const opt=document.getElementById('itemSelect').selectedOptions[0];
  const price=parseFloat(opt?.dataset?.price||0);
  document.getElementById('itemUnitPrice').value='FRW '+price.toLocaleString();
  updateItemTotal();
}
function updateItemTotal(){
  const qty=parseFloat(document.getElementById('itemQty').value||0);
  const price=parseFloat(document.getElementById('itemSelect').selectedOptions[0]?.dataset?.price||0);
  document.getElementById('itemTotal').textContent='FRW '+(qty*price).toLocaleString('en-US',{maximumFractionDigits:0});
}

async function submitAddItem(){
  const opt=document.getElementById('itemSelect').selectedOptions[0];
  const stock = parseInt(opt?.dataset?.stock || 0);
  const qty = parseInt(document.getElementById('itemQty').value || 0);

  if (qty > stock) {
      Notify.error('Stock Insufficient', `You requested ${qty} but only ${stock} is available in stock. Proceeding might cause negative inventory.`);
      return;
  }

  const fd=new FormData(document.getElementById('addItemForm'));
  Notify.loading('Adding...');
  const res=await Ajax.post(`ticket_view.php?id=${ticketId}`,fd,true);
  Notify.close();
  if(res.success){Notify.success('Added!',res.message);setTimeout(()=>location.reload(),800);}
  else Notify.error('Error',res.message);
}

async function removeItem(rowId){
  if(!await Notify.confirmDelete('this material'))return;
  const fd=new FormData();fd.append('action','remove_item');fd.append('item_row_id',rowId);
  const res=await Ajax.post(`ticket_view.php?id=${ticketId}`,fd,true);
  if(res.success){Notify.success('Removed!');setTimeout(()=>location.reload(),800);}
  else Notify.error('Error',res.message);
}

async function confirmMaterials(){
  const ok=await Notify.confirm('Confirm Materials?','This will deduct items from stock and lock the material cost. The cost will then be presented to the client.','Yes, Confirm');
  if(!ok)return;
  const fd=new FormData();fd.append('action','confirm_materials');
  Notify.loading('Confirming...');
  const res=await Ajax.post(`ticket_view.php?id=${ticketId}`,fd,true);
  Notify.close();
  if(res.success){Notify.success('Confirmed!',res.message);setTimeout(()=>location.reload(),1200);}
  else Notify.error('Error',res.message);
}

async function clientConfirmAndStart(){
  const ok=await Notify.confirm(
    'Client Confirms Cost?',
    'Confirm that the client has reviewed and agreed to the total cost shown. The service timer will start immediately after.',
    'Client Agrees — Start Service'
  );
  if(!ok)return;
  const fd=new FormData();fd.append('action','start_timer');
  Notify.loading('Starting service...');
  const res=await Ajax.post(`ticket_view.php?id=${ticketId}`,fd,true);
  Notify.close();
  if(res.success){Notify.success('Service Started!','Timer is now running.');setTimeout(()=>location.reload(),1000);}
  else Notify.error('Error',res.message);
}

async function startTimer(){
  const fd=new FormData();fd.append('action','start_timer');
  Notify.loading('Starting...');
  const res=await Ajax.post(`ticket_view.php?id=${ticketId}`,fd,true);
  Notify.close();
  if(res.success){Notify.success('Timer Started!');setTimeout(()=>location.reload(),800);}
  else Notify.error('Error',res.message);
}

async function pauseTimer(){
  const fd=new FormData();fd.append('action','pause_timer');
  Notify.loading('Pausing...');
  const res=await Ajax.post(`ticket_view.php?id=${ticketId}`,fd,true);
  Notify.close();
  if(res.success){Notify.success('Paused!',`Time worked: ${res.data?.minutes_worked||0} min`);setTimeout(()=>location.reload(),1000);}
  else Notify.error('Error',res.message);
}

async function resumeTimer(){
  const fd=new FormData();fd.append('action','resume_timer');
  Notify.loading('Resuming...');
  const res=await Ajax.post(`ticket_view.php?id=${ticketId}`,fd,true);
  Notify.close();
  if(res.success){Notify.success('Resumed!');setTimeout(()=>location.reload(),800);}
  else Notify.error('Error',res.message);
}

async function stopTimer(){
  const ok = await Notify.confirm(
    'Save Service Time?',
    'This will finalize the time log and move the ticket to Completed status.',
    'Save & Complete'
  );
  if (!ok) return;

  // Send the client-counted total seconds so the server doesn't need to recalculate
  const fd = new FormData();
  fd.append('action', 'stop_timer');
  fd.append('client_total_seconds', window._timerTotalSecs || 0);

  Notify.loading('Saving time...');
  const res = await Ajax.post(`ticket_view.php?id=${ticketId}`, fd, true);
  Notify.close();
  if (res.success) {
    Notify.success('Complete!', `Total time: ${res.data?.formatted_time}`);
    setTimeout(() => location.reload(), 1200);
  } else {
    Notify.error('Error', res.message);
  }
}

let isProcessingPayment = false;
async function submitPayment(){
  if(isProcessingPayment) return;
  const btn = event.currentTarget || event.target;
  
  isProcessingPayment = true;
  if(btn && btn.tagName === 'BUTTON') {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
  }

  const fd=new FormData(document.getElementById('paymentForm'));
  Notify.loading('Processing payment...');
  const res=await Ajax.post(`ticket_view.php?id=${ticketId}`,fd,true);
  Notify.close();
  
  if(res.success){
    await Swal.fire({icon:'success',title:'Payment Processed!',text:res.message,background:'#1a2235',color:'#e2e8f0',confirmButtonColor:'#22c55e'});
    location.reload();
  } else {
    Notify.error('Error',res.message);
    isProcessingPayment = false;
    if(btn && btn.tagName === 'BUTTON') {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-check"></i> Confirm Payment';
    }
  }
}

async function submitDeny(){
  const reason=document.getElementById('denialReason').value.trim();
  if(!reason){Notify.error('Required','Please enter a denial reason.');return;}
  const ok=await Notify.confirm('Deny this ticket?','This action will be logged permanently.','Deny');
  if(!ok)return;
  const fd=new FormData();fd.append('action','deny_ticket');fd.append('denial_reason',reason);
  Notify.loading('Denying...');
  const res=await Ajax.post(`ticket_view.php?id=${ticketId}`,fd,true);
  Notify.close();
  if(res.success){Notify.success('Denied!');setTimeout(()=>window.location.href=res.data?.redirect||'tickets.php',1200);}
  else Notify.error('Error',res.message);
}

async function addNote(){
  const note=document.getElementById('noteText').value.trim();
  if(!note){Notify.warning('Empty','Please write a note first.');return;}
  const fd=new FormData();fd.append('action','add_note');fd.append('note',note);
  Notify.loading('Adding...');
  const res=await Ajax.post(`ticket_view.php?id=${ticketId}`,fd,true);
  Notify.close();
  if(res.success){Notify.success('Note added!');setTimeout(()=>location.reload(),800);}
  else Notify.error('Error',res.message);
}

async function submitAssign(){
  const techId=document.getElementById('assignTechId').value;
  const fd=new FormData();fd.append('action','assign_tech');fd.append('technician_id',techId);
  Notify.loading('Assigning...');
  const res=await Ajax.post(`ticket_view.php?id=${ticketId}`,fd,true);
  Notify.close();
  if(res.success){Notify.success('Assigned!');setTimeout(()=>location.reload(),800);}
  else Notify.error('Error',res.message);
}

// ── Pure Second-Counter Timer ─────────────────────────────────────────────
// Strategy: count every second using setInterval starting from the server-
// computed initial value. All display is derived from a single integer
// (total seconds). No Date.now(), no timezone math, no drift.
<?php if($st === 'ongoing'): ?>
(function() {
    // Starting value: accumulated previous sessions + seconds already elapsed
    // when this page was served. Both come from the server.
    let totalSecs = <?= (int)$initialTotalSeconds ?>;

    // Expose to stopTimer() so it can send the exact count to the server
    window._timerTotalSecs = totalSecs;

    function pad(n) { return String(Math.floor(n)).padStart(2, '0'); }

    function render() {
        const h = Math.floor(totalSecs / 3600);
        const m = Math.floor((totalSecs % 3600) / 60);
        const s = totalSecs % 60;

        const eH = document.getElementById('timerH');
        const eM = document.getElementById('timerM');
        const eS = document.getElementById('timerS');
        if (eH) eH.textContent = pad(h);
        if (eM) eM.textContent = pad(m);
        if (eS) eS.textContent = pad(s);

        // Active Session label (only counts current session from page load)
        const sessionLabel = document.getElementById('sessionTimeLabel');
        if (sessionLabel) {
            const liveSecs = Math.max(0, totalSecs - <?= (int)$totalAccumulatedSeconds ?>);
            const sh = Math.floor(liveSecs / 3600);
            const sm = Math.floor((liveSecs % 3600) / 60);
            const ss = liveSecs % 60;
            sessionLabel.textContent =
                `Active Session: ${pad(sh)}:${pad(sm)}:${pad(ss)}`;
        }
    }

    render(); // Draw immediately on page load

    setInterval(function() {
        <?php if($running): ?>
        totalSecs++;                    // Add exactly 1 second per tick
        window._timerTotalSecs = totalSecs;
        render();
        <?php endif; ?>
    }, 1000);
})();
<?php endif; ?>
</script>
</body>
</html>
