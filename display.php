<?php
// display.php - Public Waiting Hall / TV Token Call Display
require_once __DIR__ . '/config/db.php';
$pdo = getDBConnection();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Clinic Live Token Display | MedFlow</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    body {
      background-color: #0b132b;
      color: #ffffff;
      padding: 1.5rem;
    }
    .display-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem 1.5rem;
      background: #1c2541;
      border-radius: var(--radius-md);
      margin-bottom: 2rem;
      border: 1px solid #3a506b;
    }
    .live-clock {
      font-family: 'JetBrains Mono', monospace;
      font-size: 1.6rem;
      font-weight: 700;
      color: #38bdf8;
    }
    .calling-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    .call-card {
      background: linear-gradient(145deg, #1e293b, #0f172a);
      border: 2px solid #38bdf8;
      border-radius: var(--radius-lg);
      padding: 2rem;
      text-align: center;
      box-shadow: 0 10px 30px rgba(56, 189, 248, 0.2);
      animation: pulseGlow 2.5s infinite;
    }
    @keyframes pulseGlow {
      0%, 100% { box-shadow: 0 0 20px rgba(56, 189, 248, 0.2); }
      50% { box-shadow: 0 0 35px rgba(56, 189, 248, 0.45); }
    }
    .room-badge {
      font-size: 1.25rem;
      font-weight: 800;
      color: #cbd5e1;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .waiting-bar {
      background: #1c2541;
      border-radius: var(--radius-lg);
      padding: 1.5rem;
      border: 1px solid #3a506b;
    }
    .token-chip {
      background: #0f172a;
      border: 1px solid #38bdf8;
      color: #38bdf8;
      font-family: 'JetBrains Mono', monospace;
      font-size: 1.25rem;
      font-weight: 700;
      padding: 0.5rem 1rem;
      border-radius: var(--radius-sm);
    }
  </style>
</head>
<body>

  <div class="display-header">
    <div style="display: flex; align-items: center; gap: 0.75rem;">
      <span style="font-size: 2rem;">🏥</span>
      <div>
        <h1 style="font-size: 1.5rem; font-weight: 800; color: #ffffff;">MedFlow Outpatient Clinic</h1>
        <span style="font-size: 0.85rem; color: #94a3b8;">Patient Queue & Token Call System</span>
      </div>
    </div>
    <div style="display: flex; align-items: center; gap: 1.5rem;">
      <button id="soundToggleBtn" class="sound-toggle-btn active" style="background: #1e293b; color: #38bdf8; border-color: #38bdf8;">
        🔔 Audio Alert: ON
      </button>
      <div id="liveTime" class="live-clock">--:--:--</div>
    </div>
  </div>

  <div style="margin-bottom: 1.5rem;">
    <h2 style="font-size: 1.25rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem;">
      📢 Now Consulting / Proceed To Room
    </h2>
    <div id="nowCallingGrid" class="calling-grid">
      <!-- In-Consultation / Calling Tokens -->
      <div style="grid-column: 1 / -1; text-align: center; padding: 2.5rem; color: #64748b; font-size: 1.1rem; background: #1c2541; border-radius: var(--radius-md);">
        Waiting for doctor to call next token...
      </div>
    </div>
  </div>

  <div class="waiting-bar">
    <h3 style="font-size: 1.1rem; font-weight: 700; color: #f59e0b; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
      ⏳ Next Tokens in Waiting Lobby
    </h3>
    <div id="nextTokensList" style="display: flex; flex-wrap: wrap; gap: 1rem;">
      <span style="color: #64748b;">No waiting tokens.</span>
    </div>
  </div>

  <script src="assets/js/app.js"></script>
  <script>
    const notifManager = new NotificationManager('all', null);

    // Update Live Clock
    function updateClock() {
      const now = new Date();
      document.getElementById('liveTime').textContent = now.toLocaleTimeString();
    }
    setInterval(updateClock, 1000);
    updateClock();

    // Fetch and render display data
    async function loadDisplayData() {
      try {
        const res = await fetch('api/get_data.php?date=<?= date('Y-m-d') ?>');
        const data = await res.json();
        if (data.success && data.appointments) {
          const consulting = data.appointments.filter(a => a.status === 'In-Consultation');
          const waiting = data.appointments.filter(a => a.status === 'Waiting');

          // Render consulting cards
          const grid = document.getElementById('nowCallingGrid');
          if (consulting.length > 0) {
            grid.innerHTML = consulting.map(c => `
              <div class="call-card">
                <div class="room-badge">${c.doctor_name} • ${c.room_number}</div>
                <div class="display-token">${c.token_number}</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: #ffffff;">${c.patient_name}</div>
              </div>
            `).join('');
          } else {
            grid.innerHTML = `
              <div style="grid-column: 1 / -1; text-align: center; padding: 2.5rem; color: #64748b; font-size: 1.1rem; background: #1c2541; border-radius: var(--radius-md);">
                Waiting for doctor to call next token...
              </div>
            `;
          }

          // Render waiting tokens
          const waitBox = document.getElementById('nextTokensList');
          if (waiting.length > 0) {
            waitBox.innerHTML = waiting.map(w => `
              <div class="token-chip">
                <span>${w.token_number}</span>
                <span style="font-size: 0.85rem; color: #94a3b8; margin-left: 0.4rem;">(${w.doctor_name.split(' ')[1] || w.doctor_name})</span>
              </div>
            `).join('');
          } else {
            waitBox.innerHTML = `<span style="color: #64748b;">No waiting tokens in lobby.</span>`;
          }
        }
      } catch (err) {
        console.error('Error fetching display data', err);
      }
    }

    // Refresh display every 3 seconds
    setInterval(loadDisplayData, 3000);
    loadDisplayData();

    // Sound chime on new call
    window.addEventListener('new-notification', (e) => {
      loadDisplayData();
    });
  </script>
</body>
</html>
