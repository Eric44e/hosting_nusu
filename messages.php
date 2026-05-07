<?php
require_once 'config.php';
requireLogin();

if (isAjax()) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    if ($action === 'send') {
        $receiverId = (int)$_POST['receiver_id'];
        $message    = sanitize($_POST['message'] ?? '');
        if (!$message) jsonResponse(false,'Message cannot be empty.');
        $pdo->prepare("INSERT INTO messages(sender_id,receiver_id,message) VALUES(?,?,?)")
            ->execute([$_SESSION['staff_id'],$receiverId,$message]);
        jsonResponse(true,'Sent!',['message_id'=>$pdo->lastInsertId()]);
    }
    if ($action === 'load' && isset($_GET['with'])) {
        $withId = (int)$_GET['with'];
        $msgs = $pdo->prepare("SELECT m.*,s.full_name sender_name FROM messages m
            JOIN staff s ON s.id=m.sender_id
            WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)
            ORDER BY m.created_at ASC LIMIT 50");
        $msgs->execute([$_SESSION['staff_id'],$withId,$withId,$_SESSION['staff_id']]);
        // Mark as read
        $pdo->prepare("UPDATE messages SET is_read=1 WHERE sender_id=? AND receiver_id=?")->execute([$withId,$_SESSION['staff_id']]);
        jsonResponse(true,'OK',['messages'=>$msgs->fetchAll()]);
    }
    jsonResponse(false,'Unknown');
}

$staffList = $pdo->query("SELECT s.id,s.full_name,s.role,s.status,
    (SELECT COUNT(*) FROM messages WHERE sender_id=s.id AND receiver_id={$_SESSION['staff_id']} AND is_read=0) unread
    FROM staff s WHERE s.id != {$_SESSION['staff_id']} AND s.status='active' ORDER BY full_name")->fetchAll();
$chatWith = isset($_GET['with']) ? (int)$_GET['with'] : ($staffList[0]['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Messages — NUSU Management System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<style>
.chat-layout { display:grid; grid-template-columns:300px 1fr; gap:1rem; height:calc(100vh - var(--nav-h) - 3.6rem); padding: 0.5rem; }
.chat-sidebar { background:#000; border:1px solid rgba(255,122,0,0.2); border-radius:16px; overflow-y:auto; color:#fff; }
.chat-main { background:#000; border:1px solid rgba(255,122,0,0.2); border-radius:16px; display:flex; flex-direction:column; color:#fff; }
.chat-contact { display:flex; align-items:center; gap:.8rem; padding:.9rem 1.2rem; cursor:pointer; transition:all .2s ease; border-bottom:1px solid rgba(255,255,255,0.05); }
.chat-contact:hover { background:rgba(255,122,0,0.1); transform:translateX(3px); }
.chat-contact.active { background:rgba(255,122,0,0.2); border-left: 3px solid #FF7A00; }
.chat-contact .c-name { font-size:.875rem; font-weight:600; color:#fff; }
.chat-contact .c-role { font-size:.72rem; color:rgba(255,255,255,0.6); margin-top:2px; }
.chat-header { display:flex; align-items:center; gap:1rem; padding:1.2rem 1.4rem; border-bottom:1px solid rgba(255,255,255,0.1); flex-shrink:0; background:#000; border-radius:16px 16px 0 0; }
.chat-body { flex:1; overflow-y:auto; padding:1.5rem; display:flex; flex-direction:column; gap:.8rem; background:#111; }
.msg-bubble { max-width:70%; padding:.8rem 1.2rem; border-radius:18px; font-size:.875rem; line-height:1.5; position:relative; }
.msg-bubble.mine { background:#FF7A00; color:#fff; align-self:flex-end; border-bottom-right-radius:4px; }
.msg-bubble.theirs { background:#222; border:1px solid rgba(255,255,255,0.1); align-self:flex-start; border-bottom-left-radius:4px; color:#fff; }
.msg-time { font-size:.65rem; opacity:.7; margin-top:.4rem; text-align:right; }
.chat-footer { padding:1.2rem; border-top:1px solid rgba(255,255,255,0.1); display:flex; gap:.8rem; align-items:center; flex-shrink:0; background:#000; border-radius:0 0 16px 16px; }
.chat-input { flex:1; background:#222; border:1px solid rgba(255,255,255,0.1); border-radius:18px; padding:.8rem 1.2rem; font-size:.875rem; resize:none; color:#fff; }
.chat-btn { background:#FF7A00; border:none; color:white; width:46px; height:46px; border-radius:23px; display:flex; align-items:center; justify-content:center; cursor:pointer; }
.chat-btn:hover { transform:scale(1.05); box-shadow:0 6px 15px rgba(26,108,255,0.4); }
</style>
</head>
<body>
<?php include 'includes/layout.php'; ?>
<main class="main-content fade-in" style="padding-bottom:0">
  <div class="page-header" style="margin-bottom:1rem">
    <div class="page-header-left"><h1>Messaging</h1><p>Internal team communication</p></div>
  </div>
  <div class="chat-layout">
    <!-- Contacts -->
    <div class="chat-sidebar">
      <div style="padding:1rem 1.2rem;border-bottom:1px solid var(--border)">
        <div class="filter-search"><i class="fas fa-search"></i><input type="text" id="contactSearch" placeholder="Search staff..."></div>
      </div>
      <div id="contactList">
      <?php foreach($staffList as $s): ?>
      <div class="chat-contact <?= $s['id']==$chatWith?'active':'' ?>" onclick="loadChat(<?= $s['id'] ?>, '<?= htmlspecialchars($s['full_name'],ENT_QUOTES) ?>')">
        <img src="https://ui-avatars.com/api/?name=<?= urlencode($s['full_name']) ?>&background=FF7A00&color=fff&size=36&bold=true" style="width:36px;height:36px;border-radius:10px">
        <div style="flex:1;min-width:0">
          <div class="c-name"><?= htmlspecialchars($s['full_name']) ?></div>
          <div class="c-role"><?= ucfirst($s['role']) ?></div>
        </div>
        <?php if($s['unread']>0): ?><span class="badge badge-danger" style="font-size:.65rem"><?= $s['unread'] ?></span><?php endif; ?>
      </div>
      <?php endforeach; ?>
      </div>
    </div>

    <!-- Chat Window -->
    <div class="chat-main">
      <div class="chat-header" id="chatHeader">
        <img src="https://ui-avatars.com/api/?name=Select+Staff&background=1a2235&color=fff&size=38&bold=true" id="chatAvatar" style="width:38px;height:38px;border-radius:10px">
        <div><div id="chatName" style="font-weight:700">Select a conversation</div><div id="chatRole" style="font-size:.78rem;color: #FF7A00"></div></div>
      </div>
      <div class="chat-body" id="chatBody">
        <div style="text-align:center;color:var(--muted);margin:auto;font-size:.875rem"><i class="fas fa-comment-dots" style="font-size:2rem;display:block;margin-bottom:.8rem;opacity:.3"></i>Select a contact to start messaging</div>
      </div>
      <div class="chat-footer">
        <textarea class="chat-input" id="chatInput" rows="1" placeholder="Type a message..." onkeydown="handleKey(event)"></textarea>
        <button class="chat-btn" onclick="sendMsg()"><i class="fas fa-paper-plane"></i></button>
      </div>
    </div>
  </div>
</main>
</div>
<script src="assets/js/main.js"></script>
<script>
let currentReceiverId = <?= $chatWith ?>;
let currentReceiverName = '';
const myId = <?= $_SESSION['staff_id'] ?>;
let pollInterval = null;

function loadChat(receiverId, name) {
  currentReceiverId = receiverId;
  currentReceiverName = name;
  document.querySelectorAll('.chat-contact').forEach(el => el.classList.remove('active'));
  event?.currentTarget?.classList.add('active');
  document.getElementById('chatName').textContent = name;
  document.getElementById('chatAvatar').src = `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=1a6cff&color=fff&size=38&bold=true`;
  fetchMessages();
  if (pollInterval) clearInterval(pollInterval);
  pollInterval = setInterval(fetchMessages, 4000);
}

async function fetchMessages() {
  if (!currentReceiverId) return;
  const res = await Ajax.get(`messages.php?action=load&with=${currentReceiverId}`);
  if (!res.success) return;
  const body = document.getElementById('chatBody');
  if (!res.messages.length) {
    body.innerHTML = `<div style="text-align:center;color:var(--muted);margin:auto;font-size:.875rem">No messages yet. Say hi! 👋</div>`;
    return;
  }
  const prevScroll = body.scrollTop === (body.scrollHeight - body.clientHeight);
  body.innerHTML = res.messages.map(m => {
    const mine = parseInt(m.sender_id) === myId;
    const time = new Date(m.created_at).toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'});
    return `<div style="display:flex;flex-direction:column;align-items:${mine?'flex-end':'flex-start'}">
      ${!mine ? `<div style="font-size:.72rem;color:var(--muted);margin-bottom:.2rem;margin-left:.5rem">${m.sender_name}</div>` : ''}
      <div class="msg-bubble ${mine?'mine':'theirs'}">${escapeHtml(m.message)}<div class="msg-time">${time}</div></div>
    </div>`;
  }).join('');
  body.scrollTop = body.scrollHeight;
}

async function sendMsg() {
  const input = document.getElementById('chatInput');
  const msg = input.value.trim();
  if (!msg || !currentReceiverId) return;
  input.value = '';
  const fd = new FormData();
  fd.append('action','send'); 
  fd.append('receiver_id',currentReceiverId); 
  fd.append('message',msg);
  try {
    const res = await Ajax.post('messages.php', fd, true);
    if (res.success) {
      fetchMessages();
    } else {
      Notify.error('Error', res.message || 'Failed to send message');
    }
  } catch (err) {
    Notify.error('Error', 'Failed to send message. Please try again.');
  }
}

function handleKey(e) {
  if (e.key==='Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); }
}

function escapeHtml(str) {
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
}

// Contact search
document.getElementById('contactSearch').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.chat-contact').forEach(el => {
    el.style.display = el.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});

// Auto-load first contact
if (currentReceiverId) {
  const firstContact = document.querySelector(`.chat-contact`);
  if (firstContact) {
    const name = firstContact.querySelector('.c-name').textContent;
    document.getElementById('chatName').textContent = name;
    document.getElementById('chatAvatar').src = `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=FF7A00&color=fff&size=38&bold=true`;
    currentReceiverName = name;
    fetchMessages();
    pollInterval = setInterval(fetchMessages, 4000);
  }
}
</script>
</body>
</html>
