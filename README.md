# 🏥 MedFlow - Outpatient Clinic Scheduling & Doctor Queue System

A clean, responsive, and lightweight clinic appointment scheduling and patient queue management system built with **PHP**, **Vanilla CSS**, and **MySQL/XAMPP**.

---

## 🌟 Key Modules & Features

### 1. 📋 Front Desk Scheduling (`index.php`)
- **Book Appointments & Walk-ins**: Capture patient details (Name, Phone, Age, Gender, Assigned Doctor, Slot Time, Reason).
- **Auto Token Generator**: Generates daily tokens (e.g. `T-101`, `T-102`).
- **Immediate Lobby Check-In**: Check-in button transitions patients from `Scheduled` to `Waiting` queue.
- **Doctor Status Board**: Shows live availability of all clinic doctors (`Available`, `Busy`, `On Break`, `Off Duty`).
- **Real-Time Notification Feed**: Alerts reception when a doctor calls a patient or finishes a consultation.
- **Search & Filters**: Search patient by name or phone; filter schedule by doctor and date.

### 2. 🩺 Doctor Consultation Interface (`doctor.php`)
- **Doctor Switcher / Profile**: Allows doctors to pick their profile and update duty status.
- **Active Consultation Card**: Highlights the patient currently in the examination room with their symptoms and time called.
- **📢 "Call Next Patient"**: Rings a hospital chime, updates patient status to `In-Consultation`, marks doctor as `Busy`, and updates public displays.
- **✅ Complete Consultation**: Enter clinical diagnosis notes and prescriptions, saves consultation history, and resets status to `Available`.
- **Waiting Room List**: Displays waiting queue with token numbers and wait reasons.

### 3. 📺 Public Waiting Hall TV Display (`display.php`)
- Designed for waiting room monitors / TVs.
- Shows **"Now Calling / Proceed to Room"** cards with Doctor Name and Room Number.
- Shows upcoming token sequence in the lobby with live digital clock.

### 4. 🔔 Real-time Audio Chimes & Notifications
- Uses **Web Audio API** (crystal sine/triangle hospital chime) without requiring external audio files.
- Background polling every 3.5 seconds for instant cross-tab synchronization.
- Floating toast popups for new arrivals, token calls, and completed visits.

---

## 🚀 How to Run with XAMPP

1. **Start XAMPP**:
   - Open **XAMPP Control Panel** (`C:\xampp\xampp-control.exe`).
   - Click **Start** for **Apache** and **MySQL**.

2. **Access the Application**:
   *(A directory junction has already been created in your `C:\xampp\htdocs\scheduler_system`)*
   - **Front Desk Module**: [http://localhost/scheduler_system/](http://localhost/scheduler_system/)
   - **Doctor Portal**: [http://localhost/scheduler_system/doctor.php](http://localhost/scheduler_system/doctor.php)
   - **Public TV Display**: [http://localhost/scheduler_system/display.php](http://localhost/scheduler_system/display.php)

3. **Database Auto-Setup**:
   - The system automatically creates the database (`clinic_scheduler`) and seeds sample doctors and appointments on the first visit!
   - Alternatively, you can manually import `database.sql` into **phpMyAdmin** ([http://localhost/phpmyadmin/](http://localhost/phpmyadmin/)).

---

## 📁 Project Structure

```
scheduler_system/
├── config/
│   └── db.php               # PDO database connection & auto-installer
├── assets/
│   ├── css/
│   │   └── style.css        # Clean medical design system & animations
│   └── js/
│       └── app.js           # Real-time polling, toasts & audio chime engine
├── api/
│   ├── actions.php          # Booking, Check-In, Call Patient, Complete actions
│   ├── notifications.php    # Polling & notification feed
│   └── get_data.php         # Live queue and doctor status feed
├── index.php                # Module 1: Front Desk Scheduling
├── doctor.php               # Module 2: Doctor Consultation Portal
├── display.php              # Module 3: Waiting Hall TV Screen
├── database.sql             # SQL Schema backup
└── README.md
```
