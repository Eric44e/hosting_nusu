<?php
require_once 'config.php';
require_once 'includes/qr_helper.php';
requireLogin();

if (isAjax()) {
    $clientId  = (int)$_POST['client_id'];
    $serviceId = (int)$_POST['service_type_id'];
    $title     = sanitize($_POST['title'] ?? '');
    $desc      = sanitize($_POST['description'] ?? '');
    $priority  = $_POST['priority'] ?? 'medium';
    $location  = sanitize($_POST['location'] ?? '');
    $techId    = (int)($_POST['technician_id'] ?? 0);
    $hours     = (float)($_POST['estimated_hours'] ?? 1);
    
    // Get service base rate in RWF
    $serviceStmt = $pdo->prepare("SELECT base_rate FROM service_types WHERE id = ?");
    $serviceStmt->execute([$serviceId]);
    $serviceData = $serviceStmt->fetch();
    $baseRate = $serviceData ? (float)$serviceData['base_rate'] : 0;
    $service_cost = $hours * $baseRate;

    if (!$clientId || !$title) jsonResponse(false, 'Client and title are required.');

    // Auto-generate ticket number
    $lastNum = $pdo->query("SELECT MAX(CAST(SUBSTRING(ticket_number,4) AS UNSIGNED)) FROM tickets")->fetchColumn();
    $ticketNum = 'TK-' . str_pad(($lastNum + 1), 4, '0', STR_PAD_LEFT);

    $pdo->prepare("INSERT INTO tickets
        (ticket_number,client_id,service_type_id,title,description,priority,location,status,technician_id,assigned_at,created_by,service_cost,total_amount)
        VALUES (?,?,?,?,?,?,?,'pending',?,?,?,?,?)")
        ->execute([$ticketNum, $clientId, $serviceId, $title, $desc, $priority, $location,
            $techId ?: null, $techId ? date('Y-m-d H:i:s') : null, $_SESSION['staff_id'], $service_cost, $service_cost]);

    $ticketId = $pdo->lastInsertId();

    if ($techId) {
        $pdo->prepare("UPDATE tickets SET status='assigned' WHERE id=?")->execute([$ticketId]);
    }

    // Log creation
    $pdo->prepare("INSERT INTO ticket_logs(ticket_id,status,notes,staff_id) VALUES(?,'pending','Ticket created',?)")
        ->execute([$ticketId, $_SESSION['staff_id']]);

    // Notification
    $pdo->prepare("INSERT INTO notifications(staff_id,type,title,body) VALUES(?,?,?,?)")
        ->execute([$_SESSION['staff_id'], 'ticket', "New ticket $ticketNum created",
            "Ticket for service: " . sanitize($_POST['title'] ?? '')]);

    jsonResponse(true, "Ticket $ticketNum created successfully!", ['redirect' => "ticket_view.php?id=$ticketId"]);
}

$clients     = $pdo->query("SELECT id,client_code,full_name,phone,city FROM clients WHERE status='active' ORDER BY full_name")->fetchAll();
$services    = $pdo->query("SELECT id,name,base_rate,description FROM service_types WHERE status='active'")->fetchAll();
$technicians = $pdo->query("SELECT t.id,s.full_name,t.specialization,t.status FROM technicians t JOIN staff s ON s.id=t.staff_id WHERE t.status='active' ORDER BY s.full_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>New Ticket — NUSU LTD</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<style>
    .card { background: #000 !important; color: #fff !important; border: 1px solid rgba(255,255,255,0.1) !important; }
    .wiz-step { background:rgba(255,255,255,.02) !important; color:rgba(255,255,255,0.4) !important; border-color:rgba(255,255,255,0.1) !important; }
    .wiz-step.active { background:rgba(255,122,0,0.1) !important; color:#FF7A00 !important; border-color:#FF7A00 !important; }
    .client-card, .service-card { background:#111 !important; border-color:rgba(255,255,255,0.1) !important; color:#fff !important; }
    .client-card:hover, .service-card:hover { border-color:#FF7A00 !important; background:rgba(255,122,0,0.05) !important; }
    .client-card.selected, .service-card.selected { border-color:#FF7A00 !important; background:rgba(255,122,0,0.1) !important; }
    .form-control, select, textarea { background:#111 !important; color:#fff !important; border:1px solid rgba(255,255,255,0.1) !important; }
    .summary-row { border-bottom:1px solid rgba(255,255,255,0.05) !important; }
    h1, h2, h3, h4, h5 { color:#fff !important; }
</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
.step-wizard { display:flex; align-items:center; gap:0; margin-bottom:2rem; }
.wiz-step { display:flex; align-items:center; gap:.6rem; padding:.7rem 1.5rem; font-size:.85rem; font-weight:600; color:var(--muted); background:rgba(255,255,255,.04); border:1px solid var(--border); cursor:pointer; transition:all .2s; }
.wiz-step:first-child { border-radius:10px 0 0 10px; }
.wiz-step:last-child  { border-radius:0 10px 10px 0; }
.wiz-step.active { background:var(--primary-light); color:var(--primary); border-color:var(--primary); }
.wiz-step.done   { background:var(--success-light);  color:var(--success); border-color:var(--success); }
.wiz-step .step-num { width:22px; height:22px; border-radius:50%; background:currentColor; color:#fff; display:flex; align-items:center; justify-content:center; font-size:.7rem; font-weight:700; }
.wiz-step.active .step-num,.wiz-step.done .step-num { background:currentColor; }

.wiz-section { display:none; }
.wiz-section.active { display:block; }

.client-card {
  border:2px solid var(--border); border-radius:12px; padding:1rem; cursor:pointer;
  transition:all .2s; display:flex; align-items:center; gap:.8rem;
}
.client-card:hover { border-color:var(--primary); background:var(--primary-light); }
.client-card.selected { border-color:var(--primary); background:var(--primary-light); }

.service-card {
  border:2px solid var(--border); border-radius:12px; padding:1rem; cursor:pointer;
  transition:all .2s; text-align:center;
}
.service-card:hover { border-color:var(--accent); background:var(--accent-light); }
.service-card.selected { border-color:var(--accent); background:var(--accent-light); }
.service-icon-wrap { width:48px; height:48px; border-radius:12px; background:var(--accent-light); display:flex; align-items:center; justify-content:center; margin:0 auto .6rem; font-size:1.3rem; color:var(--accent); }

.summary-row { display:flex; justify-content:space-between; padding:.55rem 0; border-bottom:1px solid var(--border); font-size:.88rem; }
.summary-row:last-child { border-bottom:none; }
</style>
</head>
<body>
<div class="app-wrapper">
<?php include 'includes/layout.php'; ?>
<main class="main-content fade-in">

  <div class="page-header">
    <div class="page-header-left"><h1>Create New Ticket</h1><p>Follow the steps to create a service ticket</p></div>
    <div class="page-actions">
      <a href="tickets.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back to Tickets</a>
    </div>
  </div>

  <!-- Wizard Steps -->
  <div class="step-wizard">
    <div class="wiz-step active" id="wiz1"><div class="step-num">1</div> Select Client</div>
    <div class="wiz-step" id="wiz2"><div class="step-num">2</div> Service Details</div>
    <div class="wiz-step" id="wiz3"><div class="step-num">3</div> Assign &amp; Confirm</div>
  </div>

  <form id="newTicketForm">
    <!-- STEP 1: Select Client -->
    <div class="wiz-section active" id="sec1">
      <div style="margin-bottom:1.2rem">
        <div class="card">
          <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <div class="card-title">Select Existing Client</div>
            <button type="button" class="btn btn-sm btn-outline" onclick="Modal.open('newClientModal')">
              <i class="fas fa-user-plus"></i> Client not registered?
            </button>
          </div>
          <div class="card-body">
            <div class="filter-search" style="margin-bottom:1rem">
              <i class="fas fa-search"></i>
              <input type="text" id="clientSearchInput" placeholder="Search clients..." oninput="filterClients()">
            </div>
            <div style="max-height:350px;overflow-y:auto;display:flex;flex-direction:column;gap:.5rem" id="clientCards">
              <?php foreach($clients as $c): ?>
              <div class="client-card" onclick="selectClient(<?= $c['id'] ?>,'<?= htmlspecialchars($c['full_name'],ENT_QUOTES) ?>','<?= htmlspecialchars($c['client_code'],ENT_QUOTES) ?>')" data-name="<?= strtolower($c['full_name']) ?>">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($c['full_name']) ?>&background=FF7A00&color=fff&size=36&bold=true" style="width:36px;height:36px;border-radius:10px">
                <div>
                  <div style="font-weight:600;font-size:.9rem"><?= htmlspecialchars($c['full_name']) ?></div>
                  <div style="font-size:.75rem;color:var(--muted)"><?= $c['client_code'] ?> · <?= htmlspecialchars($c['city']??'') ?></div>
                </div>
                <i class="fas fa-check-circle" style="margin-left:auto;color:var(--primary);display:none" class="check-icon"></i>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <input type="hidden" name="client_id" id="selectedClientId">
      <div id="selectedClientBadge" style="display:none;padding:.8rem;background:var(--success-light);border-radius:10px;color:var(--success);font-size:.875rem;margin-bottom:1rem">
        <i class="fas fa-check-circle"></i> Selected: <strong id="selectedClientName"></strong>
      </div>
      <button type="button" class="btn btn-primary" onclick="goStep(2)"><i class="fas fa-arrow-right"></i> Next: Service Details</button>
    </div>

    <!-- STEP 2: Service Details -->
    <div class="wiz-section" id="sec2">
      <div class="grid-2" style="margin-bottom:1.2rem">
        <div class="card">
          <div class="card-header"><div class="card-title">Select Service Type</div></div>
          <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem">
            <?php
            $svcIcons = ['fas fa-plug','fas fa-tools','fas fa-faucet','fas fa-box','fas fa-wrench','fas fa-bolt'];
            foreach($services as $i=>$s): ?>
            <div class="service-card" onclick="selectService(<?= $s['id'] ?>,'<?= htmlspecialchars($s['name'],ENT_QUOTES) ?>',<?= $s['base_rate'] ?>)" id="svc_<?= $s['id'] ?>">
              <div class="service-icon-wrap"><i class="<?= $svcIcons[$i%6] ?>"></i></div>
              <div style="font-size:.875rem;font-weight:600"><?= htmlspecialchars($s['name']) ?></div>
              <div style="font-size:.75rem;color:var(--muted);margin-top:.2rem">Base: FRW <?= number_format($s['base_rate'],0) ?>/hr</div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Ticket Details</div></div>
          <div class="card-body">
            <input type="hidden" name="service_type_id" id="selectedServiceId">
            <div class="form-group">
              <label>Ticket Title *</label>
              <input type="text" name="title" id="ticketTitle" class="form-control" required placeholder="Brief description of the issue">
            </div>
            <div class="form-group">
              <label>Priority</label>
              <select name="priority" class="form-control">
                <option value="low">🟢 Low</option>
                <option value="medium" selected>🟡 Medium</option>
                <option value="high">🟠 High</option>
                <option value="urgent">🔴 Urgent</option>
              </select>
            </div>
            <div class="form-group">
              <label>Service Location</label>
              <input type="text" name="location" class="form-control" placeholder="Where is the service needed?">
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Estimated Hours</label>
                <input type="number" name="estimated_hours" id="ticketHours" class="form-control" value="1" min="0.1" step="0.1" placeholder="e.g. 2.5" oninput="calcHoursAndCost()">
              </div>
              <div class="form-group">
                <label>Est. Service Cost (FRW)</label>
                <input type="number" name="service_cost" id="ticketServiceCost" class="form-control" value="0" readonly style="background:var(--card2);opacity:.7">
              </div>
            </div>
            <div class="form-group">
              <label>Description</label>
              <textarea name="description" class="form-control" rows="4" placeholder="Detailed description of the service request..."></textarea>
            </div>
          </div>
        </div>
      </div>
      <div style="display:flex;gap:.8rem">
        <button type="button" class="btn btn-outline" onclick="goStep(1)"><i class="fas fa-arrow-left"></i> Back</button>
        <button type="button" class="btn btn-primary" onclick="goStep(3)"><i class="fas fa-arrow-right"></i> Next: Assign &amp; Review</button>
      </div>
    </div>

    <!-- STEP 3: Assign & Confirm -->
    <div class="wiz-section" id="sec3">
      <div class="grid-2">
        <div class="card">
          <div class="card-header"><div class="card-title">Assign Technician (Optional)</div></div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:.6rem;max-height:350px;overflow-y:auto">
            <div class="client-card <?= !$technicians?'':'' ?>" onclick="selectTech(0,'No Assignment')" id="tech_0" style="border-color:var(--border)">
              <div style="width:36px;height:36px;border-radius:10px;background:var(--card2);display:flex;align-items:center;justify-content:center;color:var(--muted)"><i class="fas fa-user-slash"></i></div>
              <div><div style="font-weight:600;font-size:.88rem">Assign Later</div><div style="font-size:.73rem;color:var(--muted)">Leave unassigned for now</div></div>
            </div>
            <?php foreach($technicians as $t): ?>
            <div class="client-card" onclick="selectTech(<?= $t['id'] ?>,'<?= htmlspecialchars($t['full_name'],ENT_QUOTES) ?>')" id="tech_<?= $t['id'] ?>">
              <img src="https://ui-avatars.com/api/?name=<?= urlencode($t['full_name']) ?>&background=FF7A00&color=fff&size=36&bold=true" style="width:36px;height:36px;border-radius:10px">
              <div>
                <div style="font-weight:600;font-size:.88rem"><?= htmlspecialchars($t['full_name']) ?></div>
                <div style="font-size:.73rem;color:var(--muted)"><?= htmlspecialchars($t['specialization']??'') ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Ticket Summary</div></div>
          <div class="card-body">
            <div class="summary-row"><span style="color:var(--muted)">Client</span><strong id="sum_client">—</strong></div>
            <div class="summary-row"><span style="color:var(--muted)">Service Type</span><strong id="sum_service">—</strong></div>
            <div class="summary-row"><span style="color:var(--muted)">Title</span><strong id="sum_title">—</strong></div>
            <div class="summary-row"><span style="color:var(--muted)">Priority</span><strong id="sum_priority">—</strong></div>
            <div class="summary-row"><span style="color:var(--muted)">Estimated Hours</span><strong id="sum_hours">0</strong></div>
            <div class="summary-row"><span style="color:var(--muted)">Service Cost</span><strong id="sum_cost">FRW 0</strong></div>
            <div class="summary-row"><span style="color:var(--muted)">Technician</span><strong id="sum_tech">Not Assigned</strong></div>
          </div>
          <div style="padding:1.2rem 1.4rem;border-top:1px solid var(--border)">
            <input type="hidden" name="technician_id" id="selectedTechId" value="0">
            <div style="display:flex;gap:.8rem">
              <button type="button" class="btn btn-outline" onclick="goStep(2)"><i class="fas fa-arrow-left"></i> Back</button>
              <button type="button" class="btn btn-primary" style="flex:1" onclick="submitTicket()">
                <i class="fas fa-ticket-alt"></i> Create Ticket
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </form>

  <!-- Company QR Code removed per request -->

</main>
</div>

<!-- New Client Modal -->
<div class="modal-overlay" id="newClientModal">
<div class="modal">
  <div class="modal-header">
    <span class="modal-title">Create New Client</span>
    <button class="modal-close" onclick="Modal.close('newClientModal')"><i class="fas fa-times"></i></button>
  </div>
  <div class="modal-body">
    <div class="form-group"><label>Full Name *</label><input type="text" id="nc_name" class="form-control" placeholder="Client full name"></div>
    <div class="form-row">
      <div class="form-group"><label>Phone</label><input type="text" id="nc_phone" class="form-control" placeholder="+1 555 0000"></div>
      <div class="form-group"><label>Email</label><input type="email" id="nc_email" class="form-control" placeholder="email@example.com"></div>
    </div>
    <div class="form-group"><label>Address</label><input type="text" id="nc_address" class="form-control" placeholder="Street address"></div>
  </div>
  <div class="modal-footer">
    <button type="button" class="btn btn-outline" onclick="Modal.close('newClientModal')">Cancel</button>
    <button type="button" class="btn btn-primary" onclick="createAndSelectClient()"><i class="fas fa-user-plus"></i> Create &amp; Select</button>
  </div>
</div>
</div>

<script src="assets/js/main.js"></script>
<script>
let step = 1;
let selectedClient = { id:null, name:'' };
let selectedService = { id:null, name:'', rate:0 };
let selectedTech = { id:0, name:'Not Assigned' };

function goStep(n) {
  if (n === 2) {
    if (!selectedClient.id) { Notify.warning('Select Client','Please select a client to continue.'); return; }
  }
  if (n === 3) {
    const title = document.getElementById('ticketTitle').value.trim();
    if (!selectedService.id) { Notify.warning('Select Service','Please select a service type.'); return; }
    if (!title) { Notify.warning('Add Title','Please provide a ticket title.'); return; }
    updateSummary();
  }
  // Update wizard UI
  step = n;
  document.querySelectorAll('.wiz-step').forEach((el, i) => {
    el.classList.remove('active','done');
    if (i+1 < n) el.classList.add('done');
    if (i+1 === n) el.classList.add('active');
  });
  document.querySelectorAll('.wiz-section').forEach((el, i) => {
    el.classList.toggle('active', i+1 === n);
  });
  window.scrollTo({top:0, behavior:'smooth'});
}

function selectClient(id, name, code) {
  selectedClient = {id, name};
  document.getElementById('selectedClientId').value = id;
  document.getElementById('selectedClientName').textContent = `${name} (${code})`;
  document.getElementById('selectedClientBadge').style.display = 'block';
  document.querySelectorAll('.client-card').forEach(el => {
    el.classList.toggle('selected', el.onclick?.toString().includes(`(${id},`));
  });
}

function filterClients() {
  const q = document.getElementById('clientSearchInput').value.toLowerCase();
  document.querySelectorAll('#clientCards .client-card').forEach(el => {
    el.style.display = el.dataset.name.includes(q) ? '' : 'none';
  });
}

function selectService(id, name, rate) {
  selectedService = {id, name, rate};
  document.getElementById('selectedServiceId').value = id;
  document.querySelectorAll('.service-card').forEach(el => el.classList.remove('selected'));
  document.getElementById(`svc_${id}`).classList.add('selected');
  calcHoursAndCost();
}

function selectTech(id, name) {
  selectedTech = {id, name};
  document.getElementById('selectedTechId').value = id;
  document.querySelectorAll('[id^="tech_"]').forEach(el => el.classList.remove('selected'));
  document.getElementById(`tech_${id}`).classList.add('selected');
  document.getElementById('sum_tech').textContent = id ? name : 'Not Assigned';
}

function updateSummary() {
  document.getElementById('sum_client').textContent   = selectedClient.name;
  document.getElementById('sum_service').textContent  = selectedService.name;
  document.getElementById('sum_title').textContent    = document.getElementById('ticketTitle').value;
  const pri = document.querySelector('[name=priority]').value;
  document.getElementById('sum_priority').textContent = pri.charAt(0).toUpperCase()+pri.slice(1);
  document.getElementById('sum_hours').textContent    = document.getElementById('ticketHours').value;
  document.getElementById('sum_cost').textContent     = 'FRW ' + parseFloat(document.getElementById('ticketServiceCost').value).toLocaleString('en-US', {maximumFractionDigits: 0});
}

function calcHoursAndCost() {
  const hours = parseFloat(document.getElementById('ticketHours').value) || 0;
  const baseRate = selectedService.rate || 0;
  document.getElementById('ticketServiceCost').value = (hours * baseRate).toFixed(2);
}

async function createAndSelectClient() {
  const name = document.getElementById('nc_name').value.trim();
  if (!name) return Notify.warning('Name Required','Please enter a client name.');
  const fd = new FormData();
  fd.append('action','create'); fd.append('full_name',name);
  fd.append('phone', document.getElementById('nc_phone').value);
  fd.append('email', document.getElementById('nc_email').value);
  fd.append('address', document.getElementById('nc_address').value);
  Notify.loading('Creating client...');
  const res = await Ajax.post('clients.php', fd, true);
  Notify.close();
  if (res.success) {
    Notify.success('Client Created!','Now selecting them automatically...');
    Modal.close('newClientModal');
    const newId = res.client_id;
    if(newId) {
        const html = `<div class="client-card" onclick="selectClient(${newId},'${name.replace(/'/g, "\\'")}','NEW')" data-name="${name.toLowerCase()}">
                <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=FF7A00&color=fff&size=36&bold=true" style="width:36px;height:36px;border-radius:10px">
                <div>
                  <div style="font-weight:600;font-size:.9rem">${name}</div>
                  <div style="font-size:.75rem;color:var(--muted)">NEW</div>
                </div>
              </div>`;
        document.getElementById('clientCards').insertAdjacentHTML('afterbegin', html);
        selectClient(newId, name, 'NEW');
    }
  } else Notify.error('Error', res.message);
}

async function submitTicket() {
  const fd = new FormData(document.getElementById('newTicketForm'));
  if (!fd.get('client_id')) return Notify.error('Error','No client selected.');
  if (!fd.get('service_type_id')) return Notify.error('Error','No service selected.');
  if (!fd.get('title')) return Notify.error('Error','Ticket title is required.');
  Notify.loading('Creating ticket...');
  const res = await Ajax.post('ticket_new.php', fd, true);
  Notify.close();
  if (res.success) {
    await Swal.fire({icon:'success',title:'Ticket Created!',text:res.message,background:'#1a2235',color:'#e2e8f0',confirmButtonColor:'#1a6cff'});
    window.location.href = res.redirect;
  } else Notify.error('Error', res.message);
}

// Initialize — select "Assign Later" by default
document.getElementById('tech_0').classList.add('selected');
</script>
</body>
</html>
