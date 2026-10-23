// assets/js/app.js - Real-time notification manager & audio chime engine

class NotificationManager {
  constructor(recipientType = 'frontdesk', doctorId = null) {
    this.recipientType = recipientType;
    this.doctorId = doctorId;
    this.lastNotificationId = 0;
    this.soundEnabled = true;
    this.audioCtx = null;
    this.unreadCount = 0;

    this.initAudio();
    this.bindDomEvents();
    this.startPolling();
  }

  initAudio() {
    // Web Audio API crystal chime synthesizer
    const AudioContext = window.AudioContext || window.webkitAudioContext;
    if (AudioContext) {
      this.audioCtx = new AudioContext();
    }
  }

  playChime(type = 'default') {
    if (!this.soundEnabled) return;

    try {
      if (this.audioCtx && this.audioCtx.state === 'suspended') {
        this.audioCtx.resume();
      }
      if (!this.audioCtx) return;

      const now = this.audioCtx.currentTime;
      const osc1 = this.audioCtx.createOscillator();
      const osc2 = this.audioCtx.createOscillator();
      const gainNode = this.audioCtx.createGain();

      osc1.type = 'sine';
      osc2.type = 'triangle';

      if (type === 'call') {
        // High-low-high alert chime for calling patient (e.g. airport/hospital style)
        osc1.frequency.setValueAtTime(587.33, now); // D5
        osc1.frequency.setValueAtTime(880, now + 0.18); // A5
        osc1.frequency.setValueAtTime(1174.66, now + 0.36); // D6

        gainNode.gain.setValueAtTime(0.3, now);
        gainNode.gain.exponentialRampToValueAtTime(0.001, now + 0.9);

        osc1.connect(gainNode);
        gainNode.connect(this.audioCtx.destination);
        osc1.start(now);
        osc1.stop(now + 0.9);
      } else {
        // Pleasant double-bell ping for new check-in or appointment
        osc1.frequency.setValueAtTime(523.25, now); // C5
        osc1.frequency.setValueAtTime(659.25, now + 0.15); // E5

        gainNode.gain.setValueAtTime(0.25, now);
        gainNode.gain.exponentialRampToValueAtTime(0.001, now + 0.6);

        osc1.connect(gainNode);
        gainNode.connect(this.audioCtx.destination);
        osc1.start(now);
        osc1.stop(now + 0.6);
      }
    } catch (e) {
      console.warn('Audio play restricted or failed', e);
    }
  }

  bindDomEvents() {
    // Sound toggle
    const soundBtn = document.getElementById('soundToggleBtn');
    if (soundBtn) {
      soundBtn.addEventListener('click', () => {
        this.soundEnabled = !this.soundEnabled;
        soundBtn.classList.toggle('active', this.soundEnabled);
        soundBtn.innerHTML = this.soundEnabled ? '🔔 Sound: ON' : '🔕 Sound: OFF';
        if (this.soundEnabled) this.playChime('test');
      });
    }

    // Notification dropdown toggle
    const notifBtn = document.getElementById('notifBtn');
    const notifDropdown = document.getElementById('notifDropdown');
    if (notifBtn && notifDropdown) {
      notifBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        notifDropdown.classList.toggle('show');
        if (notifDropdown.classList.contains('show')) {
          this.markAllAsRead();
        }
      });

      document.addEventListener('click', (e) => {
        if (!notifDropdown.contains(e.target) && !notifBtn.contains(e.target)) {
          notifDropdown.classList.remove('show');
        }
      });
    }

    // First user gesture unlocks Web Audio
    document.addEventListener('click', () => {
      if (this.audioCtx && this.audioCtx.state === 'suspended') {
        this.audioCtx.resume();
      }
    }, { once: true });
  }

  async startPolling() {
    // Initial fetch
    await this.fetchNotifications(true);

    // Poll every 3.5 seconds
    setInterval(() => {
      this.fetchNotifications(false);
    }, 3500);
  }

  async fetchNotifications(isInitial = false) {
    try {
      let url = `api/notifications.php?recipient_type=${this.recipientType}`;
      if (this.doctorId) url += `&doctor_id=${this.doctorId}`;
      if (!isInitial && this.lastNotificationId > 0) {
        url += `&last_id=${this.lastNotificationId}`;
      }

      const res = await fetch(url);
      const data = await res.json();

      if (data.success && data.notifications) {
        if (data.notifications.length > 0) {
          const newest = data.notifications[0];
          
          if (!isInitial && newest.id > this.lastNotificationId) {
            // We have a new notification!
            this.playChime(newest.type);
            this.showToast(newest.title, newest.message, newest.type);

            // Trigger custom event so page can update its queue table
            window.dispatchEvent(new CustomEvent('new-notification', { detail: newest }));
          }

          this.lastNotificationId = Math.max(this.lastNotificationId, ...data.notifications.map(n => parseInt(n.id)));
        }

        this.updateBadge(data.unread_count);
        this.renderDropdown(data.notifications);
      }
    } catch (err) {
      console.error('Polling error', err);
    }
  }

  updateBadge(count) {
    const badge = document.getElementById('notifBadge');
    if (!badge) return;
    this.unreadCount = count;
    if (count > 0) {
      badge.textContent = count > 99 ? '99+' : count;
      badge.style.display = 'block';
    } else {
      badge.style.display = 'none';
    }
  }

  renderDropdown(notifications) {
    const list = document.getElementById('notifList');
    if (!list) return;

    if (!notifications || notifications.length === 0) {
      list.innerHTML = `<div style="padding: 1.5rem; text-align: center; color: #94a3b8; font-size: 0.85rem;">No notifications yet.</div>`;
      return;
    }

    list.innerHTML = notifications.map(n => {
      let icon = '🔔';
      if (n.type === 'call') icon = '📢';
      if (n.type === 'checkin') icon = '🚶';
      if (n.type === 'completed') icon = '✅';
      if (n.type === 'new') icon = '📅';

      return `
        <div class="notif-item ${n.is_read == 0 ? 'unread' : ''}">
          <div class="notif-icon">${icon}</div>
          <div class="notif-content">
            <h5>${this.escapeHtml(n.title)}</h5>
            <p>${this.escapeHtml(n.message)}</p>
            <time>${new Date(n.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</time>
          </div>
        </div>
      `;
    }).join('');
  }

  async markAllAsRead() {
    try {
      const formData = new FormData();
      formData.append('action', 'mark_read');
      let url = `api/notifications.php?recipient_type=${this.recipientType}`;
      if (this.doctorId) url += `&doctor_id=${this.doctorId}`;

      await fetch(url, {
        method: 'POST',
        body: formData
      });
      this.updateBadge(0);
    } catch (e) {
      console.error('Failed to mark notifications read', e);
    }
  }

  showToast(title, message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      document.body.appendChild(container);
    }

    let icon = '🔔';
    if (type === 'call') icon = '📢';
    else if (type === 'checkin') icon = '🚶';
    else if (type === 'completed') icon = '✅';

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
      <div class="toast-icon">${icon}</div>
      <div class="toast-body">
        <h4>${this.escapeHtml(title)}</h4>
        <p>${this.escapeHtml(message)}</p>
      </div>
    `;

    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateX(50px)';
      toast.style.transition = 'all 0.3s ease';
      setTimeout(() => toast.remove(), 300);
    }, 4500);
  }

  escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>"']/g, function(m) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
  }
}

// Modal helper functions
function openModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.classList.add('show');
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.classList.remove('show');
}
