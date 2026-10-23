<?php
// doctor.php - Doctor Interface Module
require_once __DIR__ . '/config/db.php';
$pdo = getDBConnection();

$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 1;
$selectedDate = date('Y-m-d');

$doctors = [];
$currentDoctor = null;
$inConsultation = null;
$waitingPatients = [];
$scheduledPatients = [];
$completedPatients = [];

if ($pdo) {
    // Fetch all doctors
    $docStmt = $pdo->query("SELECT * FROM `doctors` ORDER BY `id` ASC");
    $doctors = $docStmt->fetchAll();

    if (!empty($doctors)) {
        // If selected doctor doesn't exist, pick the first
        $docIds = array_column($doctors, 'id');
        if (!in_array($doctorId, $docIds)) {
            $doctorId = $doctors[0]['id'];
        }

        foreach ($doctors as $d) {
            if ($d['id'] == $doctorId) {
                $currentDoctor = $d;
                break;
            }
        }

        // Fetch appointments for this doctor today
        $appStmt = $pdo->prepare("SELECT * FROM `appointments` WHERE `doctor_id` = ? AND `appointment_date` = ? ORDER BY `appointment_time` ASC, `id` ASC");
        $appStmt->execute([$doctorId, $selectedDate]);
        $allApps = $appStmt->fetchAll();

        foreach ($allApps as $app) {
            if ($app['status'] === 'In-Consultation') {
                $inConsultation = $app;
            } elseif ($app['status'] === 'Waiting') {
                $waitingPatients[] = $app;
            } elseif ($app['status'] === 'Scheduled') {
                $scheduledPatients[] = $app;
            } elseif ($app['status'] === 'Completed') {
                $completedPatients[] = $app;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Doctor Portal | MedFlow Scheduler</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .consultation-hero {
      background: linear-gradient(135deg, #0f172a, #1e293b);
      color: #ffffff;
      border-radius: var(--radius-lg);
      padding: 1.75rem;
      margin-bottom: 2rem;
      border: 1px solid #334155;
      box-shadow: var(--shadow-md);
      position: relative;
      overflow: hidden;
    }
    .consultation-hero::before {
      content: '';
      position: absolute;
      top: -50px;
      right: -50px;
      width: 180px;
      height: 180px;
      background: rgba(14, 165, 233, 0.15);
      border-radius: 50%;
      filter: blur(40px);
    }
    .active-patient-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1rem;
    }
    .active-token-badge {
      font-family: 'JetBrains Mono', monospace;
      font-size: 1.5rem;
      font-weight: 800;
      color: #38bdf8;
      background: rgba(56, 189, 248, 0.15);
      padding: 0.3rem 0.85rem;
      border-radius: var(--radius-sm);
      border: 1px solid rgba(56, 189, 248, 0.3);
    }
    .doctor-picker {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      background: #f1f5f9;
      padding: 0.4rem 0.8rem;
      border-radius: var(--radius-md);
      border: 1px solid var(--border);
    }
    .status-select {
      background: #ffffff;
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 0.3rem 0.75rem;
      font-size: 0.82rem;
      font-weight: 700;
      color: var(--text-main);
      cursor: pointer;
    }
  </style>
</head>
<body>

  <!-- Top Navbar -->
  <header class="navbar">
    <div class="nav-container">
      <a href="doctor.php" class="brand">
        <div class="brand-icon" style="background: linear-gradient(135deg, #0d9488, #14b8a6);">🩺</div>
        <div class="brand-text">
          <h1>MedFlow Clinic</h1>
          <span>Doctor Consultation Portal</span>
        </div>
      </a>

      <!-- Navigation Tabs -->
      <nav class="nav-links">
        <a href="index.php" class="nav-link">
          <span>📋</span> Front Desk
        </a>
        <a href="doctor.php" class="nav-link active">
          <span>🩺</span> Doctor Interface
        </a>
        <a href="display.php" class="nav-link" target="_blank">
          <span>📺</span> Waiting Hall Display ↗
        </a>
      </nav>

      <!-- Right Header Actions -->
      <div class="nav-actions">
        <!-- Switch Doctor Selector -->
        <form method="GET" action="doctor.php" class="doctor-picker">
          <span style="font-size: 0.82rem; font-weight: 600; color: var(--text-muted);">Doctor:</span>
          <select name="doctor_id" class="form-control" style="padding: 0.25rem 0.5rem; font-size: 0.85rem; border: none; background: transparent; font-weight: 700;" onchange="this.form.submit()">
            <?php foreach ($doctors as $d): ?>
              <option value="<?= $d['id'] ?>" <?= $doctorId == $d['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['room_number']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </form>

        <button id="soundToggleBtn" class="sound-toggle-btn active" title="Toggle sound alerts">
          🔔 Sound: ON
        </button>

        <div class="notif-wrapper">
          <button id="notifBtn" class="notif-btn" title="Doctor Notifications">
            🔔
            <span id="notifBadge" class="notif-badge" style="display: none;">0</span>
          </button>
          
          <div id="notifDropdown" class="notif-dropdown">
            <div class="notif-header">
              <h4>Doctor Alerts</h4>
              <span style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($currentDoctor['name'] ?? 'Doctor') ?></span>
            </div>
            <div id="notifList" class="notif-list">
              <!-- Populated by JS -->
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>

  <main class="main-wrapper">

    <?php if ($currentDoctor): ?>
      <!-- Doctor Banner & Status -->
      <div style="background: #ffffff; border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 1.25rem 1.75rem; margin-bottom: 1.75rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; box-shadow: var(--shadow-sm);">
        <div style="display: flex; align-items: center; gap: 1rem;">
          <div style="width: 50px; height: 50px; border-radius: 50%; background: #ccfbf1; color: #0f766e; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700;">
            👨‍⚕️
          </div>
          <div>
            <h2 style="font-size: 1.35rem; font-weight: 800; color: #0f172a;"><?= htmlspecialchars($currentDoctor['name']) ?></h2>
            <p style="font-size: 0.85rem; color: var(--text-muted);">
              <strong><?= htmlspecialchars($currentDoctor['specialty']) ?></strong> • <?= htmlspecialchars($currentDoctor['room_number']) ?>
            </p>
          </div>
        </div>

        <!-- Doctor Status Controls -->
        <div style="display: flex; align-items: center; gap: 0.75rem;">
          <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);">Current Status:</span>
          <select id="doctorStatusSelect" class="status-select" onchange="updateDoctorStatus(this.value)">
            <option value="Available" <?= $currentDoctor['status'] === 'Available' ? 'selected' : '' ?>>🟢 Available</option>
            <option value="Busy" <?= $currentDoctor['status'] === 'Busy' ? 'selected' : '' ?>>🔴 In Consultation (Busy)</option>
            <option value="On Break" <?= $currentDoctor['status'] === 'On Break' ? 'selected' : '' ?>>☕ On Break</option>
            <option value="Off Duty" <?= $currentDoctor['status'] === 'Off Duty' ? 'selected' : '' ?>>⚪ Off Duty</option>
          </select>
        </div>
      </div>
    <?php endif; ?>

    <!-- Active Consultation Section -->
    <?php if ($inConsultation): ?>
      <div class="consultation-hero">
        <div class="active-patient-header">
          <div style="display: flex; align-items: center; gap: 1rem;">
            <span class="active-token-badge"><?= htmlspecialchars($inConsultation['token_number']) ?></span>
            <div>
              <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #38bdf8; font-weight: 700;">CURRENTLY IN CONSULTATION</span>
              <h3 style="font-size: 1.45rem; font-weight: 800; color: #ffffff;"><?= htmlspecialchars($inConsultation['patient_name']) ?></h3>
            </div>
          </div>
          <button class="btn btn-success" onclick="openModal('completeModal')">
            <span>✅</span> Complete Consultation
          </button>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1); font-size: 0.9rem;">
          <div>
            <span style="color: #94a3b8; font-size: 0.75rem;">PHONE / CONTACT:</span>
            <p style="font-weight: 600;"><?= htmlspecialchars($inConsultation['patient_phone']) ?></p>
          </div>
          <div>
            <span style="color: #94a3b8; font-size: 0.75rem;">AGE & GENDER:</span>
            <p style="font-weight: 600;"><?= $inConsultation['patient_age'] ? $inConsultation['patient_age'] . ' years' : 'N/A' ?> (<?= htmlspecialchars($inConsultation['patient_gender']) ?>)</p>
          </div>
          <div>
            <span style="color: #94a3b8; font-size: 0.75rem;">TIME CALLED:</span>
            <p style="font-weight: 600; color: #38bdf8;"><?= $inConsultation['called_at'] ? date('g:i A', strtotime($inConsultation['called_at'])) : 'Just now' ?></p>
          </div>
          <div>
            <span style="color: #94a3b8; font-size: 0.75rem;">REASON / SYMPTOMS:</span>
            <p style="font-weight: 600;"><?= htmlspecialchars($inConsultation['symptoms'] ?: 'None specified') ?></p>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div style="background: #f8fafc; border: 2px dashed var(--border); border-radius: var(--radius-lg); padding: 1.75rem; text-align: center; margin-bottom: 2rem;">
        <span style="font-size: 2rem;">🛋️</span>
        <h3 style="font-size: 1.15rem; font-weight: 700; color: #334155; margin-top: 0.5rem;">No Patient In Consultation</h3>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
          <?php if (!empty($waitingPatients)): ?>
            There are <strong><?= count($waitingPatients) ?></strong> patients waiting outside. Click <strong>"Call Next Patient"</strong> below to begin consultation.
          <?php else: ?>
            Waiting room is clear. Front Desk will check in arriving patients.
          <?php endif; ?>
        </p>
      </div>
    <?php endif; ?>

    <!-- Two-Column Queue Layout -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.75rem;">
      
      <!-- Column 1: Waiting Room (Checked-In) -->
      <section class="card">
        <div class="card-header">
          <div style="display: flex; align-items: center; gap: 0.6rem;">
            <h2 class="card-title">⏳ Waiting Room Queue</h2>
            <span style="font-size: 0.8rem; background: var(--warning-light); color: #b45309; padding: 0.2rem 0.65rem; border-radius: 999px; font-weight: 700;">
              <?= count($waitingPatients) ?> Waiting
            </span>
          </div>
        </div>

        <div class="card-body" style="padding: 1.25rem;">
          <?php if (empty($waitingPatients)): ?>
            <div style="text-align: center; padding: 2rem; color: var(--text-muted); font-size: 0.9rem;">
              No patients currently waiting in the lobby.
            </div>
          <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 0.85rem;">
              <?php foreach ($waitingPatients as $index => $patient): ?>
                <div style="border: 1px solid var(--border); border-radius: var(--radius-md); padding: 1rem 1.15rem; background: #ffffff; display: flex; align-items: center; justify-content: space-between; gap: 1rem; box-shadow: var(--shadow-sm); transition: var(--transition);">
                  <div style="display: flex; align-items: center; gap: 0.85rem;">
                    <span class="token-pill" style="font-size: 1rem; padding: 0.35rem 0.75rem;"><?= htmlspecialchars($patient['token_number']) ?></span>
                    <div>
                      <h4 style="font-size: 0.95rem; font-weight: 700; color: #0f172a;"><?= htmlspecialchars($patient['patient_name']) ?></h4>
                      <p style="font-size: 0.78rem; color: var(--text-muted);">
                        <?= $patient['patient_age'] ? $patient['patient_age'] . ' yrs • ' : '' ?><?= htmlspecialchars($patient['patient_gender']) ?> • Slot: <?= date('g:i A', strtotime($patient['appointment_time'])) ?>
                      </p>
                      <?php if ($patient['symptoms']): ?>
                        <p style="font-size: 0.75rem; color: #475569; margin-top: 2px;"><em><?= htmlspecialchars($patient['symptoms']) ?></em></p>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div>
                    <button class="btn btn-primary btn-sm" onclick="callPatient(<?= $patient['id'] ?>, '<?= htmlspecialchars(addslashes($patient['token_number'])) ?>', '<?= htmlspecialchars(addslashes($patient['patient_name'])) ?>')">
                      <span>📢</span> Call Next
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- Column 2: Scheduled Later Today & Completed -->
      <div>
        <!-- Scheduled Upcoming -->
        <section class="card" style="margin-bottom: 1.5rem;">
          <div class="card-header">
            <h2 class="card-title">📅 Upcoming Appointments Today</h2>
            <span style="font-size: 0.8rem; background: var(--info-light); color: var(--info); padding: 0.2rem 0.65rem; border-radius: 999px; font-weight: 700;">
              <?= count($scheduledPatients) ?>
            </span>
          </div>
          <div class="card-body" style="padding: 1rem;">
            <?php if (empty($scheduledPatients)): ?>
              <p style="color: var(--text-muted); font-size: 0.85rem; text-align: center; padding: 1rem;">No other pending appointments today.</p>
            <?php else: ?>
              <div style="display: flex; flex-direction: column; gap: 0.6rem;">
                <?php foreach ($scheduledPatients as $p): ?>
                  <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.65rem 0.85rem; background: #f8fafc; border-radius: var(--radius-sm); border: 1px solid var(--border);">
                    <div>
                      <strong style="font-size: 0.88rem;"><?= htmlspecialchars($p['patient_name']) ?></strong>
                      <span style="font-size: 0.75rem; color: var(--text-muted); margin-left: 0.5rem;">(<?= htmlspecialchars($p['token_number']) ?>)</span>
                    </div>
                    <span style="font-size: 0.8rem; font-weight: 600; color: var(--primary);">
                      <?= date('g:i A', strtotime($p['appointment_time'])) ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </section>

        <!-- Completed History -->
        <section class="card">
          <div class="card-header">
            <h2 class="card-title">✅ Completed Today</h2>
            <span style="font-size: 0.8rem; background: #e2e8f0; color: #475569; padding: 0.2rem 0.65rem; border-radius: 999px; font-weight: 700;">
              <?= count($completedPatients) ?>
            </span>
          </div>
          <div class="card-body" style="padding: 1rem; max-height: 250px; overflow-y: auto;">
            <?php if (empty($completedPatients)): ?>
              <p style="color: var(--text-muted); font-size: 0.85rem; text-align: center; padding: 1rem;">No consultations completed yet today.</p>
            <?php else: ?>
              <div style="display: flex; flex-direction: column; gap: 0.6rem;">
                <?php foreach ($completedPatients as $p): ?>
                  <div style="padding: 0.65rem 0.85rem; background: #f8fafc; border-radius: var(--radius-sm); border-left: 3px solid var(--success);">
                    <div style="display: flex; justify-content: space-between; font-size: 0.85rem;">
                      <strong><?= htmlspecialchars($p['patient_name']) ?> <span style="font-size: 0.75rem; color: var(--text-muted);">(<?= htmlspecialchars($p['token_number']) ?>)</span></strong>
                      <span style="font-size: 0.75rem; color: var(--text-muted);"><?= date('g:i A', strtotime($p['updated_at'])) ?></span>
                    </div>
                    <?php if ($p['prescriptions'] || $p['doctor_notes']): ?>
                      <p style="font-size: 0.78rem; color: #64748b; margin-top: 3px;">
                        <em><?= htmlspecialchars($p['doctor_notes'] ?: $p['prescriptions']) ?></em>
                      </p>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </section>
      </div>

    </div>

  </main>

  <!-- Complete Consultation Modal -->
  <?php if ($inConsultation): ?>
  <div id="completeModal" class="modal-backdrop">
    <div class="modal">
      <div class="modal-header">
        <h3 class="modal-title">🩺 Complete Consultation: <?= htmlspecialchars($inConsultation['patient_name']) ?></h3>
        <button class="close-btn" onclick="closeModal('completeModal')">&times;</button>
      </div>
      <div class="modal-body">
        <form onsubmit="handleCompleteSubmit(event)">
          <input type="hidden" name="action" value="complete_consultation">
          <input type="hidden" name="id" value="<?= $inConsultation['id'] ?>">

          <div class="form-group">
            <label>Diagnosis & Clinical Notes</label>
            <textarea name="doctor_notes" class="form-control" rows="3" placeholder="e.g. Mild viral pharyngitis, vitals stable, advice rest and hydration..."></textarea>
          </div>

          <div class="form-group">
            <label>Prescriptions / Medication</label>
            <textarea name="prescriptions" class="form-control" rows="2" placeholder="e.g. Paracetamol 500mg TDS x 3 days, Vitamin C daily"></textarea>
          </div>

          <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
            <button type="button" class="btn btn-outline" onclick="closeModal('completeModal')">Back</button>
            <button type="submit" class="btn btn-success" id="finishConsultBtn">Finalize & Finish</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <script src="assets/js/app.js"></script>
  <script>
    const currentDoctorId = <?= (int)$doctorId ?>;
    
    // Initialize Notification Manager specifically for this doctor
    const notifManager = new NotificationManager('doctor', currentDoctorId);

    // Auto-refresh when front desk checks in patient or updates schedule
    window.addEventListener('new-notification', (e) => {
      setTimeout(() => location.reload(), 1200);
    });

    // Call Patient into consultation room
    async function callPatient(id, token, name) {
      if (!confirm(`Call Token ${token} (${name}) into your consultation room?`)) return;

      const fd = new FormData();
      fd.append('action', 'call_patient');
      fd.append('id', id);

      try {
        const res = await fetch('api/actions.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
          notifManager.playChime('call');
          notifManager.showToast('Calling Patient', `Token ${token} called to room.`, 'call');
          setTimeout(() => location.reload(), 700);
        } else {
          alert(data.error || 'Failed to call patient.');
        }
      } catch (e) {
        alert('Server communication error.');
      }
    }

    // Handle Complete Consultation
    async function handleCompleteSubmit(e) {
      e.preventDefault();
      const form = e.target;
      const btn = document.getElementById('finishConsultBtn');
      btn.disabled = true;
      btn.textContent = 'Saving...';

      const fd = new FormData(form);

      try {
        const res = await fetch('api/actions.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
          closeModal('completeModal');
          notifManager.showToast('Consultation Complete', 'Record saved and status reset to Available.', 'completed');
          setTimeout(() => location.reload(), 800);
        } else {
          alert(data.error || 'Failed to complete consultation.');
          btn.disabled = false;
          btn.textContent = 'Finalize & Finish';
        }
      } catch (err) {
        alert('Network or server error.');
        btn.disabled = false;
        btn.textContent = 'Finalize & Finish';
      }
    }

    // Update Doctor Duty Status
    async function updateDoctorStatus(newStatus) {
      const fd = new FormData();
      fd.append('action', 'set_doctor_status');
      fd.append('doctor_id', currentDoctorId);
      fd.append('status', newStatus);

      try {
        await fetch('api/actions.php', { method: 'POST', body: fd });
        notifManager.showToast('Status Updated', `Doctor status set to ${newStatus}.`, 'info');
      } catch (e) {
        console.error('Failed to update status', e);
      }
    }
  </script>
</body>
</html>
