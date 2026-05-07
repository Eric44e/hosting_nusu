<?php
require_once 'config.php';
requireLogin();

// ── AJAX handlers ──────────────────────────────────────────
if (isAjax()) {
  $action = $_POST['action'] ?? $_GET['action'] ?? '';

  // Create ticket
  if ($action === 'create') {
    $clientId   = (int)$_POST['client_id'];
    $serviceId  = (int)$_POST['service_type_id'];
    $title      = sanitize($_POST['title'] ?? '');
    $desc       = sanitize($_POST['description'] ?? '');
    $priority   = $_POST['priority'] ?? 'medium';
    $location   = sanitize($_POST['location'] ?? '');

    if (!$clientId || !$title) jsonResponse(false, 'Client and title are required.');

    // Sequential 4-digit number
    $lastNum   = (int)$pdo->query("SELECT MAX(CAST(SUBSTRING(ticket_number,4) AS UNSIGNED)) FROM tickets WHERE ticket_number LIKE 'TK-%'")->fetchColumn();
    $ticketNum = 'TK-' . str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
    $stmt = $pdo->prepare("INSERT INTO tickets
      (ticket_number,client_id,service_type_id,title,description,priority,location,status,created_by)
      VALUES (?,?,?,?,?,?,?,'pending',?)");
    $stmt->execute([$ticketNum,$clientId,$serviceId,$title,$desc,$priority,$location,$_SESSION['staff_id']]);
    $ticketId = $pdo->lastInsertId();

    // Auto-assign technician if system finds a match
    require_once 'modules/TicketManager.php';
    $tm = new TicketManager($pdo);
    $tm->autoAssignTechnician($ticketId, $_SESSION['staff_id']);

    $pdo->prepare("INSERT INTO ticket_logs(ticket_id,status,notes,staff_id) VALUES(?,'pending','Ticket created',?)")
        ->execute([$ticketId, $_SESSION['staff_id']]);

    jsonResponse(true, "Ticket $ticketNum created!", ['reload'=>true]);
  }

  // Assign technician
  if ($action === 'assign') {
    $ticketId = (int)$_POST['ticket_id'];
    $techId   = (int)$_POST['technician_id'];
    $stmt = $pdo->prepare("UPDATE tickets SET technician_id=?,status='assigned',assigned_at=NOW() WHERE id=?");
    $stmt->execute([$techId,$ticketId]);
    $log = $pdo->prepare("INSERT INTO ticket_logs(ticket_id,status,notes,staff_id) VALUES(?,'assigned','Technician assigned',?)");
    $log->execute([$ticketId,$_SESSION['staff_id']]);
    jsonResponse(true, 'Technician assigned successfully!', ['reload'=>true]);
  }

  // Assign to me (for technicians)
  if ($action === 'assign_to_me') {
    $ticketId = (int)$_POST['ticket_id'];
    $stmt = $pdo->prepare("SELECT id FROM technicians WHERE staff_id=? AND status='active'");
    $stmt->execute([$_SESSION['staff_id']]);
    $techId = $stmt->fetchColumn();
    if (!$techId) jsonResponse(false, 'You are not an active technician.');
    
    $stmt = $pdo->prepare("UPDATE tickets SET technician_id=?,status='assigned',assigned_at=NOW() WHERE id=?");
    $stmt->execute([$techId,$ticketId]);
    $log = $pdo->prepare("INSERT INTO ticket_logs(ticket_id,status,notes,staff_id) VALUES(?,'assigned','Technician self-assigned',?)");
    $log->execute([$ticketId,$_SESSION['staff_id']]);
    jsonResponse(true, 'Ticket assigned to you!', ['reload'=>true]);
  }

  // Update status
  if ($action === 'update_status') {
    $ticketId = (int)$_POST['ticket_id'];
    $status   = $_POST['status'];
    $allowed  = ['pending','assigned','confirmed','ongoing','completed','closed','denied'];
    if (!in_array($status,$allowed)) jsonResponse(false,'Invalid status.');

    // Status transition rules
    $current = $pdo->prepare("SELECT status FROM tickets WHERE id=?");
    $current->execute([$ticketId]); $curStatus = $current->fetchColumn();
    $transitions = [
      'pending'   => ['assigned','denied'],
      'assigned'  => ['confirmed','denied'],
      'confirmed' => ['ongoing','denied'],
      'ongoing'   => ['completed'],
      'completed' => ['closed'],
      'closed'    => [],
      'denied'    => ['assigned'],
    ];
    // Admins can do any transition; others must follow rules
    if (!hasRole('admin') && !in_array($status, $transitions[$curStatus] ?? [])) {
      jsonResponse(false, 'Invalid status transition from '.ucfirst($curStatus).' to '.ucfirst($status).'.');
    }
    // Denied requires reason
    if ($status === 'denied') {
      $reason = sanitize($_POST['denial_reason'] ?? '');
      if (!$reason) jsonResponse(false, 'Please provide a reason for denying this ticket.');
      $pdo->prepare("UPDATE tickets SET status='denied',denial_reason=? WHERE id=".$ticketId)->execute([$reason]);
      $pdo->prepare("INSERT INTO ticket_logs(ticket_id,status,notes,staff_id) VALUES(?,?,?,?)")
         ->execute([$ticketId,'denied','Ticket denied: '.$reason,$_SESSION['staff_id']]);
      jsonResponse(true,'Ticket denied.',['reload'=>true]);
    }

    $extra = ''; $params = [$status,$ticketId];
    if ($status==='completed') { $extra=',completed_at=NOW()'; }
    if ($status==='closed')    { $extra=',closed_at=NOW()'; }
    if ($status==='assigned')  { $extra=',assigned_at=NOW()'; }
    if ($status==='ongoing')   { $extra=',started_at=NOW()'; }
    $pdo->prepare("UPDATE tickets SET status=?$extra WHERE id=?")->execute($params);
    $pdo->prepare("INSERT INTO ticket_logs(ticket_id,status,notes,staff_id) VALUES(?,?,?,?)")
       ->execute([$ticketId,$status,"Status changed to $status",$_SESSION['staff_id']]);
    jsonResponse(true,'Status updated!',['reload'=>true]);
  }

  // Delete ticket
  if ($action === 'delete') {
    $id = (int)$_POST['id'];
    // Only allow deletion of pending tickets
    $stmt = $pdo->prepare("DELETE FROM tickets WHERE id=? AND status='pending'");
    $stmt->execute([$id]);
    if ($stmt->rowCount() > 0) {
        jsonResponse(true,'Ticket deleted.',['reload'=>true]);
    } else {
        jsonResponse(false,'Only pending tickets can be deleted.');
    }
  }

  // Get ticket detail
  if ($action === 'detail' && isset($_GET['id'])) {
    $t = $pdo->prepare("SELECT t.*,c.full_name client_name,c.phone client_phone,c.address client_address,
      st.name service_name,s.full_name tech_name
      FROM tickets t
      LEFT JOIN clients c ON c.id=t.client_id
      LEFT JOIN service_types st ON st.id=t.service_type_id
      LEFT JOIN technicians tech ON tech.id=t.technician_id
      LEFT JOIN staff s ON s.id=tech.staff_id
      WHERE t.id=?");
    $t->execute([(int)$_GET['id']]);
    $ticket = $t->fetch();
    $logs   = $pdo->prepare("SELECT tl.*,s.full_name staff_name FROM ticket_logs tl LEFT JOIN staff s ON s.id=tl.staff_id WHERE tl.ticket_id=? ORDER BY tl.created_at DESC");
    $logs->execute([(int)$_GET['id']]);
    jsonResponse(true,'OK',['ticket'=>$ticket,'logs'=>$logs->fetchAll()]);
  }

  jsonResponse(false,'Unknown action');
}

// ── Filters ────────────────────────────────────────────────
$statusFilter = $_GET['status'] ?? 'all';
$search       = $_GET['q'] ?? '';
$page         = max(1,(int)($_GET['page']??1));
$perPage      = 15;
$offset       = ($page-1)*$perPage;

$where = '1=1';
$params = [];
if ($statusFilter !== 'all') { $where .= ' AND t.status=?'; $params[]=$statusFilter; }
if ($search) { $where .= ' AND (t.ticket_number LIKE ? OR c.full_name LIKE ? OR st.name LIKE ?)'; $like="%$search%"; $params[]=$like;$params[]=$like;$params[]=$like; }

$total = $pdo->prepare("SELECT COUNT(*) FROM tickets t LEFT JOIN clients c ON c.id=t.client_id LEFT JOIN service_types st ON st.id=t.service_type_id WHERE $where");
$total->execute($params); $totalRows = $total->fetchColumn();
$totalPages = ceil($totalRows/$perPage);

$stmt = $pdo->prepare("SELECT t.*,c.full_name client_name,st.name service_name,s.full_name tech_name
  FROM tickets t
  LEFT JOIN clients c ON c.id=t.client_id
  LEFT JOIN service_types st ON st.id=t.service_type_id
  LEFT JOIN technicians tech ON tech.id=t.technician_id
  LEFT JOIN staff s ON s.id=tech.staff_id
  WHERE $where ORDER BY t.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$tickets = $stmt->fetchAll();

// Data for dropdowns
$clients     = $pdo->query("SELECT id,client_code,full_name FROM clients WHERE status='active' ORDER BY full_name")->fetchAll();
$services    = $pdo->query("SELECT id,name FROM service_types WHERE status='active'")->fetchAll();
$technicians = $pdo->query("SELECT t.id,s.full_name,t.specialization FROM technicians t JOIN staff s ON s.id=t.staff_id WHERE t.status='active'")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tickets — NUSU LTD</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<style>
    .card { background: #000 !important; color: #fff !important; border: 1px solid rgba(255,255,255,0.1) !important; }
    .table-wrap { border: none !important; }
    table th { color: #FF7A00 !important; border-bottom: 1px solid rgba(255,255,255,0.1) !important; }
    table td { border-bottom: 1px solid rgba(255,255,255,0.05) !important; color: #fff !important; }
    .filter-bar { background: #111 !important; border: 1px solid rgba(255,255,255,0.1) !important; }
    .filter-search input { background: transparent !important; color: #fff !important; }
</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="app-wrapper">
<?php include 'includes/layout.php'; ?>
<main class="main-content fade-in">

  <div class="page-header">
    <div class="page-header-left">
      <h1>Sales &amp; Tickets</h1>
      <p>Manage all service tickets and assignments</p>
    </div>
    <div class="page-actions">
      <!-- Create Ticket removed per request -->
    </div>
  </div>

  <!-- Status Tab Filters -->
  <div style="display:flex;gap:.5rem;margin-bottom:1.2rem;flex-wrap:wrap">
    <?php foreach(['all','pending','assigned','confirmed','ongoing','completed','closed','denied'] as $s): ?>
    <a href="?status=<?= $s ?>&q=<?= urlencode($search) ?>"
       class="btn btn-sm <?= $statusFilter===$s ? 'btn-primary' : 'btn-outline' ?>">
      <?= $s==='ongoing' ? 'In Progress' : ucfirst($s) ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Filter Bar -->
  <div class="filter-bar">
    <form method="GET" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
      <input type="hidden" name="status" value="<?= $statusFilter ?>">
      <div class="filter-search">
        <i class="fas fa-search"></i>
        <input type="text" name="q" placeholder="Search tickets, client..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <button type="submit" class="btn btn-outline btn-sm"><i class="fas fa-search"></i> Search</button>
      <?php if($search||$statusFilter!=='all'): ?>
      <a href="tickets.php" class="btn btn-sm btn-outline"><i class="fas fa-times"></i> Clear</a>
      <?php endif; ?>
    </form>
    <span style="margin-left:auto;color:var(--muted);font-size:.82rem"><?= number_format($totalRows) ?> ticket(s) found</span>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table id="ticketsTable">
        <thead>
          <tr>
            <th>Ticket ID</th><th>Client</th><th>Service</th>
            <th>Priority</th><th>Status</th><th>Technician</th>
            <th>Date</th><th>Amount</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if(empty($tickets)): ?>
        <tr><td colspan="9"><div class="empty-state"><i class="fas fa-ticket-alt"></i><p>No tickets found.</p></div></td></tr>
        <?php else: foreach($tickets as $t): ?>
        <tr>
          <td><a href="ticket_view.php?id=<?= $t['id'] ?>" class="table-link"><?= $t['ticket_number'] ?></a></td>
          <td><?= htmlspecialchars($t['client_name']) ?></td>
          <td style="font-size:.82rem"><?= htmlspecialchars($t['service_name']??'—') ?></td>
          <td><?= priorityBadge($t['priority']) ?></td>
          <td><?= statusBadge($t['status']) ?></td>
          <td style="font-size:.82rem"><?= $t['tech_name'] ? htmlspecialchars($t['tech_name']) : '<span style="color:var(--muted)">Unassigned</span>' ?></td>
          <td style="font-size:.78rem;color:var(--muted)"><?= date('M j, Y',strtotime($t['created_at'])) ?></td>
          <td style="font-weight:600;color:var(--accent)"><?= $t['total_amount']>0 ? formatMoney($t['total_amount']) : '—' ?></td>
          <td>
            <div style="display:flex;gap:.4rem">
              <a href="ticket_view.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline btn-icon" title="View"><i class="fas fa-eye"></i></a>
              <?php if($t['status']==='pending'): ?>
                <?php if(hasRole('admin', 'sales')): ?>
                  <button class="btn btn-sm btn-primary btn-icon" onclick="openAssign(<?= $t['id'] ?>)" title="Assign"><i class="fas fa-user-plus"></i></button>
                <?php elseif(hasRole('technician')): ?>
                  <button class="btn btn-sm btn-success btn-icon" onclick="assignToMe(<?= $t['id'] ?>)" title="Assign to me"><i class="fas fa-hand-paper"></i></button>
                <?php endif; ?>
                <?php if(hasRole('admin')): ?>
                  <button class="btn btn-sm btn-danger btn-icon" onclick="deleteTicket(<?= $t['id'] ?>, '<?= $t['ticket_number'] ?>')" title="Delete"><i class="fas fa-trash"></i></button>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if($totalPages>1): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.4rem;border-top:1px solid var(--border)">
      <span style="font-size:.82rem;color:var(--muted)">
        Showing <?= min($offset+1,$totalRows) ?>–<?= min($offset+$perPage,$totalRows) ?> of <?= $totalRows ?>
      </span>
      <div class="pagination">
        <?php for($p=1;$p<=$totalPages;$p++): ?>
        <a href="?status=<?= $statusFilter ?>&q=<?= urlencode($search) ?>&page=<?= $p ?>"
           class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</main>
</div>

<!-- ── New Ticket Modal ── -->
<div class="modal-overlay" id="newTicketModal">
<div class="modal modal-lg">
  <div class="modal-header">
    <span class="modal-title"><i class="fas fa-ticket-alt" style="margin-right:.5rem;color:var(--primary)"></i> Create New Ticket</span>
    <button class="modal-close"><i class="fas fa-times"></i></button>
  </div>
  <div class="modal-body">
    <form id="newTicketForm">
      <input type="hidden" name="action" value="create">
      <div class="form-row">
        <div class="form-group">
          <label>Client *</label>
          <select name="client_id" class="form-control" required>
            <option value="">Select Client</option>
            <?php foreach($clients as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['full_name']) ?> (<?= $c['client_code'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Service Type</label>
          <select name="service_type_id" class="form-control">
            <option value="">Select Service</option>
            <?php foreach($services as $s): ?>
            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Ticket Title *</label>
        <input type="text" name="title" class="form-control" placeholder="e.g. Electrical Installation - Main Panel" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Priority</label>
          <select name="priority" class="form-control">
            <option value="low">Low</option>
            <option value="medium" selected>Medium</option>
            <option value="high">High</option>
            <option value="urgent">Urgent</option>
          </select>
        </div>
        <div class="form-group">
          <label>Location / Address</label>
          <input type="text" name="location" class="form-control" placeholder="Service location">
        </div>
      </div>
      <div class="form-group">
        <label>Description</label>
        <textarea name="description" class="form-control" rows="3" placeholder="Describe the service request..."></textarea>
      </div>
    </form>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline" onclick="Modal.close('newTicketModal')">Cancel</button>
    <button class="btn btn-primary" onclick="submitNewTicket()"><i class="fas fa-save"></i> Create Ticket</button>
  </div>
</div>
</div>

<!-- ── Assign Technician Modal ── -->
<div class="modal-overlay" id="assignModal">
<div class="modal">
  <div class="modal-header">
    <span class="modal-title"><i class="fas fa-user-plus" style="margin-right:.5rem;color:var(--success)"></i> Assign Technician</span>
    <button class="modal-close"><i class="fas fa-times"></i></button>
  </div>
  <div class="modal-body">
    <form id="assignForm">
      <input type="hidden" name="action" value="assign">
      <input type="hidden" name="ticket_id" id="assignTicketId">
      <div class="form-group">
        <label>Select Technician *</label>
        <select name="technician_id" class="form-control" required>
          <option value="">Choose a technician</option>
          <?php foreach($technicians as $tech): ?>
          <option value="<?= $tech['id'] ?>"><?= htmlspecialchars($tech['full_name']) ?> — <?= htmlspecialchars($tech['specialization']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline" onclick="Modal.close('assignModal')">Cancel</button>
    <button class="btn btn-success" onclick="submitAssign()"><i class="fas fa-check"></i> Assign</button>
  </div>
</div>
</div>

<!-- ── Quick Status Modal ── -->
<div class="modal-overlay" id="statusModal">
<div class="modal">
  <div class="modal-header">
    <span class="modal-title"><i class="fas fa-sync-alt" style="margin-right:.5rem;color:var(--warning)"></i> Update Status</span>
    <button class="modal-close"><i class="fas fa-times"></i></button>
  </div>
  <div class="modal-body">
    <form id="statusForm">
      <input type="hidden" name="action" value="update_status">
      <input type="hidden" name="ticket_id" id="statusTicketId">
      <div class="form-group">
        <label>New Status</label>
        <select name="status" id="statusSelect" class="form-control">
          <option value="pending">Pending</option>
          <option value="assigned">Assigned</option>
          <option value="ongoing">Ongoing</option>
          <option value="completed">Completed</option>
          <option value="closed">Closed</option>
          <option value="denied">Denied</option>
        </select>
      </div>
    </form>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline" onclick="Modal.close('statusModal')">Cancel</button>
    <button class="btn btn-warning" onclick="submitStatus()"><i class="fas fa-save"></i> Update</button>
  </div>
</div>
</div>

<script src="assets/js/main.js"></script>
<script>
async function submitNewTicket() {
  const fd = new FormData(document.getElementById('newTicketForm'));
  Notify.loading('Creating ticket...');
  try {
    const res = await Ajax.post('tickets.php', fd, true);
    Notify.close();
    if (res.success) { Notify.success('Done!', res.message); setTimeout(()=>location.reload(),1200); }
    else Notify.error('Error', res.message);
  } catch(e){ Notify.error('Network Error'); }
}

function openAssign(ticketId) {
  document.getElementById('assignTicketId').value = ticketId;
  Modal.open('assignModal');
}
async function submitAssign() {
  const fd = new FormData(document.getElementById('assignForm'));
  Notify.loading('Assigning...');
  try {
    const res = await Ajax.post('tickets.php', fd, true);
    Notify.close();
    if (res.success) { Notify.success('Assigned!', res.message); setTimeout(()=>location.reload(),1200); }
    else Notify.error('Error', res.message);
  } catch(e){ Notify.error('Network Error'); }
}

async function assignToMe(ticketId) {
  const ok = await Notify.confirm('Assign to Me?', 'Do you want to assign this ticket to yourself?', 'Yes, assign it');
  if (!ok) return;
  const fd = new FormData();
  fd.append('action', 'assign_to_me');
  fd.append('ticket_id', ticketId);
  Notify.loading('Assigning...');
  try {
    const res = await Ajax.post('tickets.php', fd, true);
    Notify.close();
    if (res.success) { Notify.success('Assigned!', res.message); setTimeout(()=>location.reload(),1200); }
    else Notify.error('Error', res.message);
  } catch(e){ Notify.error('Network Error'); }
}

function quickStatus(ticketId, current) {
  document.getElementById('statusTicketId').value = ticketId;
  document.getElementById('statusSelect').value = current;
  Modal.open('statusModal');
}
async function submitStatus() {
  const fd = new FormData(document.getElementById('statusForm'));
  Notify.loading('Updating...');
  try {
    const res = await Ajax.post('tickets.php', fd, true);
    Notify.close();
    if (res.success) { Notify.success('Updated!', res.message); setTimeout(()=>location.reload(),1200); }
    else Notify.error('Error', res.message);
  } catch(e){ Notify.error('Network Error'); }
}

async function deleteTicket(id, num) {
  const ok = await Notify.confirmDelete(`ticket ${num}`);
  if (!ok) return;
  try {
    const fd = new FormData(); fd.append('action','delete'); fd.append('id',id);
    const res = await Ajax.post('tickets.php', fd, true);
    if (res.success) { Notify.success('Deleted!', res.message); setTimeout(()=>location.reload(),1200); }
    else Notify.error('Error', res.message);
  } catch(e){ Notify.error('Network Error'); }
}
</script>
</body>
</html>
