<?php
// index.php - Front Desk Scheduling Module
require_once __DIR__ . '/config/db.php';
$pdo = getDBConnection();

$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedDoctor = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;

$doctors = [];
$appointments = [];
$stats = ['total' => 0, 'waiting' => 0, 'consulting' => 0, 'completed' => 0];

if ($pdo) {
    // Doctors
    $docStmt = $pdo->query("SELECT * FROM `doctors` ORDER BY `name` ASC");
    $doctors = $docStmt->fetchAll();

    // Appointments for date
    $sql = "SELECT a.*, d.name as doctor_name, d.specialty as doctor_specialty, d.room_number 
            FROM `appointments` a 
            JOIN `doctors` d ON a.doctor_id = d.id 
            WHERE a.appointment_date = ? ";
    $params = [$selectedDate];

    if ($selectedDoctor > 0) {
        $sql .= "AND a.doctor_id = ? ";
        $params[] = $selectedDoctor;
    }

    $sql .= "ORDER BY 
        CASE 
            WHEN a.status = 'In-Consultation' THEN 1
            WHEN a.status = 'Waiting' THEN 2
            WHEN a.status = 'Scheduled' THEN 3
            WHEN a.status = 'Completed' THEN 4
            ELSE 5 
        END, 
        a.appointment_time ASC, a.id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();

    foreach ($appointments as $app) {
        $stats['total']++;
        if ($app['status'] === 'Waiting') $stats['waiting']++;
        elseif ($app['status'] === 'In-Consultation') $stats['consulting']++;
        elseif ($app['status'] === 'Completed') $stats['completed']++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Front Desk Scheduling | MedFlow Scheduler</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

  <!-- Top Navbar -->
  <header class="navbar">
    <div class="nav-container">
      <a href="index.php" class="brand">
        <div class="brand-icon">🏥</div>
        <div class="brand-text">
          <h1>MedFlow Clinic</h1>
          <span>Front Desk Scheduler</span>
        </div>
      </a>

      <!-- Navigation Tabs -->
      <nav class="nav-links">
        <a href="index.php" class="nav-link active">
          <span>📋</span> Front Desk
        </a>
        <a href="doctor.php" class="nav-link">
          <span>🩺</span> Doctor Interface
        </a>
        <a href="display.php" class="nav-link" target="_blank">
          <span>📺</span> Waiting Hall Display ↗
        </a>
      </nav>

      <!-- Right Header Actions -->
      <div class="nav-actions">
        <button id="soundToggleBtn" class="sound-toggle-btn active" title="Toggle audio chime">
          🔔 Sound: ON
        </button>

        <div class="notif-wrapper">
          <button id="notifBtn" class="notif-btn" title="Notifications">
            🔔
            <span id="notifBadge" class="notif-badge" style="display: none;">0</span>
          </button>
          
          <div id="notifDropdown" class="notif-dropdown">
            <div class="notif-header">
              <h4>Reception Notifications</h4>
              <span style="font-size: 0.75rem; color: var(--text-muted);">Real-time</span>
            </div>
            <div id="notifList" class="notif-list">
              <!-- Populated by JS -->
            </div>
          </div>
        </div>

        <button class="btn btn-primary btn-sm" onclick="openModal('bookModal')">
          <span>+</span> Book Patient
        </button>
      </div>
    </div>
  </header>

  <main class="main-wrapper">

    <?php if (!$pdo): ?>
      <div style="background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 1.25rem; border-radius: 12px; margin-bottom: 2rem;">
        <h3 style="font-weight: 700; margin-bottom: 0.5rem;">Database Connection Notice</h3>
        <p>Could not connect to MySQL. Please ensure <strong>Apache</strong> and <strong>MySQL</strong> are started in the <strong>XAMPP Control Panel</strong>.</p>
        <p style="margin-top: 0.5rem; font-size: 0.85rem;">Default config: Host: <code>127.0.0.1:3306</code>, User: <code>root</code>, Password: <em>(empty)</em>.</p>
      </div>
    <?php endif; ?>

    <!-- Summary Statistics -->
    <section class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon total">📅</div>
        <div class="stat-info">
          <h3 id="statTotal"><?= $stats['total'] ?></h3>
          <p>Total Bookings Today</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon waiting">⏳</div>
        <div class="stat-info">
          <h3 id="statWaiting"><?= $stats['waiting'] ?></h3>
          <p>In Waiting Room</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon consulting">🩺</div>
        <div class="stat-info">
          <h3 id="statConsulting"><?= $stats['consulting'] ?></h3>
          <p>In Consultation</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon completed">✅</div>
        <div class="stat-info">
          <h3 id="statCompleted"><?= $stats['completed'] ?></h3>
          <p>Completed Visits</p>
        </div>
      </div>
    </section>

    <!-- Main Dashboard Grid -->
    <div class="dashboard-layout">
      
      <!-- Left Sidebar: Doctors on Duty & Quick Filters -->
      <aside>
        <div class="card" style="margin-bottom: 1.5rem;">
          <div class="card-header">
            <h2 class="card-title">👨‍⚕️ Doctors On Duty</h2>
          </div>
          <div class="card-body" style="padding: 1rem;">
            <?php if (empty($doctors)): ?>
              <p style="color: var(--text-muted); font-size: 0.85rem;">No doctors registered.</p>
            <?php else: ?>
              <?php foreach ($doctors as $doc): ?>
                <div class="doctor-badge-card">
                  <div class="doc-meta">
                    <h4><?= htmlspecialchars($doc['name']) ?></h4>
                    <span><?= htmlspecialchars($doc['specialty']) ?> • <?= htmlspecialchars($doc['room_number']) ?></span>
                  </div>
                  <span class="doc-status-pill <?= str_replace(' ', '-', $doc['status']) ?>">
                    <?= htmlspecialchars($doc['status']) ?>
                  </span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Filter Card -->
        <div class="card">
          <div class="card-header">
            <h2 class="card-title">🔍 Filter Schedule</h2>
          </div>
          <div class="card-body">
            <form method="GET" action="index.php">
              <div class="form-group">
                <label>Date</label>
                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($selectedDate) ?>" onchange="this.form.submit()">
              </div>
              <div class="form-group">
                <label>Doctor</label>
                <select name="doctor_id" class="form-control" onchange="this.form.submit()">
                  <option value="0">All Doctors</option>
                  <?php foreach ($doctors as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $selectedDoctor == $d['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['specialty']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php if ($selectedDate !== date('Y-m-d') || $selectedDoctor > 0): ?>
                <a href="index.php" class="btn btn-outline btn-sm btn-block">Reset Filters</a>
              <?php endif; ?>
            </form>
          </div>
        </div>
      </aside>

      <!-- Right Main: Live Patient Queue -->
      <section>
        <div class="card">
          <div class="card-header">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
              <h2 class="card-title">📋 Live Patient Queue</h2>
              <span style="font-size: 0.8rem; background: var(--primary-light); color: var(--primary); padding: 0.2rem 0.6rem; border-radius: 999px; font-weight: 700;">
                <?= count($appointments) ?> Patients
              </span>
            </div>
            <div style="display: flex; gap: 0.5rem;">
              <input type="text" id="queueSearch" placeholder="Search patient or phone..." class="form-control" style="width: 220px; padding: 0.4rem 0.75rem; font-size: 0.85rem;" onkeyup="filterQueueTable()">
              <button class="btn btn-outline btn-sm" onclick="location.reload()" title="Refresh Queue">🔄</button>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table" id="queueTable">
              <thead>
                <tr>
                  <th>Token</th>
                  <th>Patient Details</th>
                  <th>Doctor & Room</th>
                  <th>Time</th>
                  <th>Status</th>
                  <th style="text-align: right;">Front Desk Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($appointments)): ?>
                  <tr>
                    <td colspan="6" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                      No appointments booked for this date yet. Click <strong>"+ Book Patient"</strong> above to schedule!
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($appointments as $app): ?>
                    <tr id="row-<?= $app['id'] ?>">
                      <td>
                        <span class="token-pill"><?= htmlspecialchars($app['token_number']) ?></span>
                      </td>
                      <td>
                        <div style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($app['patient_name']) ?></div>
                        <div style="font-size: 0.78rem; color: var(--text-muted);">
                          <?= htmlspecialchars($app['patient_phone']) ?> • <?= $app['patient_age'] ? $app['patient_age'] . ' yrs' : 'N/A' ?> (<?= htmlspecialchars($app['patient_gender']) ?>)
                        </div>
                        <?php if (!empty($app['symptoms'])): ?>
                          <div style="font-size: 0.75rem; color: #475569; margin-top: 3px;">
                            <em>Reason: <?= htmlspecialchars($app['symptoms']) ?></em>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div style="font-weight: 600;"><?= htmlspecialchars($app['doctor_name']) ?></div>
                        <div style="font-size: 0.78rem; color: var(--text-muted);"><?= htmlspecialchars($app['room_number']) ?></div>
                      </td>
                      <td>
                        <strong><?= date('g:i A', strtotime($app['appointment_time'])) ?></strong>
                      </td>
                      <td>
                        <span class="status-badge <?= $app['status'] ?>">
                          <?= htmlspecialchars($app['status']) ?>
                        </span>
                      </td>
                      <td style="text-align: right;">
                        <div style="display: inline-flex; gap: 0.4rem; justify-content: flex-end;">
                          <?php if ($app['status'] === 'Scheduled'): ?>
                            <button class="btn btn-success btn-sm" onclick="checkInPatient(<?= $app['id'] ?>, '<?= htmlspecialchars(addslashes($app['patient_name'])) ?>')">
                              <span>🚶</span> Check-In
                            </button>
                          <?php elseif ($app['status'] === 'Waiting'): ?>
                            <span style="font-size: 0.75rem; color: #b45309; font-weight: 600; padding: 0.4rem 0.6rem; background: var(--warning-light); border-radius: 6px;">
                              Waiting for Doctor
                            </span>
                          <?php elseif ($app['status'] === 'In-Consultation'): ?>
                            <span style="font-size: 0.75rem; color: #047857; font-weight: 600; padding: 0.4rem 0.6rem; background: var(--success-light); border-radius: 6px;">
                              In Room Now
                            </span>
                          <?php elseif ($app['status'] === 'Completed'): ?>
                            <span style="font-size: 0.75rem; color: #475569; font-weight: 600;">
                              Done
                            </span>
                          <?php endif; ?>

                          <?php if ($app['status'] !== 'Completed' && $app['status'] !== 'Cancelled'): ?>
                            <button class="btn btn-outline btn-sm" style="color: var(--danger); border-color: #fecaca;" onclick="cancelAppointment(<?= $app['id'] ?>, '<?= htmlspecialchars(addslashes($app['token_number'])) ?>')" title="Cancel Appointment">
                              ✕
                            </button>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

    </div>
  </main>

  <!-- Book Appointment Modal -->
  <div id="bookModal" class="modal-backdrop">
    <div class="modal">
      <div class="modal-header">
        <h3 class="modal-title">📝 Book Patient Appointment</h3>
        <button class="close-btn" onclick="closeModal('bookModal')">&times;</button>
      </div>
      <div class="modal-body">
        <form id="bookingForm" onsubmit="handleBookingSubmit(event)">
          <input type="hidden" name="action" value="book_appointment">

          <div class="form-group">
            <label>Patient Full Name *</label>
            <input type="text" name="patient_name" class="form-control" placeholder="e.g. John Doe" required>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Phone Number *</label>
              <input type="tel" name="patient_phone" class="form-control" placeholder="+1 555-0199" required>
            </div>
            <div class="form-group">
              <label>Age & Gender</label>
              <div style="display: grid; grid-template-columns: 1fr 1.3fr; gap: 0.4rem;">
                <input type="number" name="patient_age" class="form-control" placeholder="Age" min="1" max="120">
                <select name="patient_gender" class="form-control">
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                  <option value="Other">Other</option>
                </select>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label>Select Doctor *</label>
            <select name="doctor_id" class="form-control" required>
              <option value="">-- Choose Assigned Doctor --</option>
              <?php foreach ($doctors as $d): ?>
                <option value="<?= $d['id'] ?>">
                  <?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['specialty']) ?> - <?= htmlspecialchars($d['room_number']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Appointment Date</label>
              <input type="date" name="appointment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
              <label>Time Slot</label>
              <input type="time" name="appointment_time" class="form-control" value="<?= date('H:i') ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label>Symptoms / Visit Reason</label>
            <textarea name="symptoms" class="form-control" rows="2" placeholder="Brief reason for consultation..."></textarea>
          </div>

          <div class="form-group">
            <label class="form-check">
              <input type="checkbox" name="immediate_checkin" value="1" checked>
              <span>Patient is present in lobby (Auto Check-In to Waiting Queue)</span>
            </label>
          </div>

          <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
            <button type="button" class="btn btn-outline" onclick="closeModal('bookModal')">Cancel</button>
            <button type="submit" class="btn btn-primary" id="saveBookingBtn">Generate Token & Book</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="assets/js/app.js"></script>
  <script>
    // Initialize Notification Manager for Front Desk
    const notifManager = new NotificationManager('frontdesk', null);

    // Auto-refresh queue when doctors call patient or finish
    window.addEventListener('new-notification', (e) => {
      // Refresh page data smoothly
      setTimeout(() => location.reload(), 1200);
    });

    // Check-in Patient
    async function checkInPatient(id, name) {
      if (!confirm(`Mark ${name} as present and send to Waiting Room?`)) return;

      const fd = new FormData();
      fd.append('action', 'check_in');
      fd.append('id', id);

      try {
        const res = await fetch('api/actions.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
          notifManager.showToast('Patient Checked In', `${name} is now in the doctor's queue.`, 'checkin');
          setTimeout(() => location.reload(), 600);
        } else {
          alert(data.error || 'Check-in failed');
        }
      } catch (err) {
        alert('Network error communicating with server.');
      }
    }

    // Cancel Appointment
    async function cancelAppointment(id, token) {
      if (!confirm(`Are you sure you want to cancel appointment ${token}?`)) return;

      const fd = new FormData();
      fd.append('action', 'cancel_appointment');
      fd.append('id', id);

      try {
        const res = await fetch('api/actions.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
          location.reload();
        } else {
          alert(data.error || 'Failed to cancel');
        }
      } catch (err) {
        alert('Network error.');
      }
    }

    // Handle Form Submit
    async function handleBookingSubmit(e) {
      e.preventDefault();
      const form = e.target;
      const btn = document.getElementById('saveBookingBtn');
      btn.disabled = true;
      btn.textContent = 'Booking...';

      const fd = new FormData(form);

      try {
        const res = await fetch('api/actions.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
          closeModal('bookModal');
          notifManager.playChime('new');
          notifManager.showToast('Appointment Booked', `Token ${data.token} assigned.`, 'checkin');
          setTimeout(() => location.reload(), 800);
        } else {
          alert(data.error || 'Error booking appointment.');
          btn.disabled = false;
          btn.textContent = 'Generate Token & Book';
        }
      } catch (err) {
        alert('Network or server error.');
        btn.disabled = false;
        btn.textContent = 'Generate Token & Book';
      }
    }

    // Filter queue table
    function filterQueueTable() {
      const input = document.getElementById('queueSearch').value.toLowerCase();
      const rows = document.querySelectorAll('#queueTable tbody tr');
      rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(input) ? '' : 'none';
      });
    }
  </script>
</body>
</html>
