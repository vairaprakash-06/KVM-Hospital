# KVM Hospital Management System — NexusCare
### Advanced Full-Stack Relational DBMS Project | Enterprise Healthcare ERP

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.x_InnoDB-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3.2-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)
![Chart.js](https://img.shields.io/badge/Chart.js-Analytics-FF6384?style=for-the-badge&logo=chartdotjs&logoColor=white)
![Build](https://img.shields.io/badge/Tests-11%2F11_Passing-success?style=for-the-badge)

---

## 1. Project Introduction
**KVM Hospital Management System — NexusCare** is an advanced, production-grade, centralized hospital management information system developed for a comprehensive college DBMS demonstration, practical review, technical viva, and production deployment.

NexusCare connects every hospital division — Outpatient, Inpatient, Emergency, Pharmacy, Diagnostic Laboratory, and Billing — through **one shared, relational MySQL database (`kvm_hospital`)** and **one common authentication system**.

---

## 2. Problem Statement
Contemporary hospital systems frequently suffer from siloed departmental software. Reception registers patients, doctors write notes in detached desktop tools, pharmacies maintain independent spreadsheets, and billing operates on isolated invoicing software. This architectural fragmentation causes:
- Lost patient records and duplicate entries.
- Double-booked appointments and inpatient beds.
- Medication dispensing errors caused by unverified inventory.
- Unmonitored waiting times leading to emergency room crowding.
- Lack of accountability and audit trails for compliance.

---

## 3. Project Objectives
1. Provide a single, unified database schema in MySQL InnoDB enforcing strict referential integrity.
2. Deliver a unified authentication portal servicing 9 hospital roles without duplicate login forms.
3. Eliminate operational bottlenecks through 5 differentiated features: **QueueFlow**, **BedMatrix**, **CareTrail**, **FlowLens**, and **TrustLedger**.
4. Guarantee transactional safety (zero double-bookings, non-negative stock, auditable adjustments).
5. Deliver a healthcare SaaS UI with responsive charts and live operational metrics.

---

## 4. Existing System Limitations
| Feature | Traditional College HMS | NexusCare Implementation |
|---|---|---|
| **Architecture** | Scattered flat tables or disparate DBs | 47 normalized 3NF InnoDB tables with foreign keys |
| **Authentication** | Separate login forms per department | One common portal with server-side role assignment |
| **Queue Management** | Static paper or dummy numbers | Dynamic wait calculation, near-real-time polling, auditable triage overrides |
| **Bed Management** | Static form selection; race conditions | Row-level `SELECT ... FOR UPDATE` transactional allocation |
| **Audit & Security** | Plain-text passwords, zero logging | Bcrypt hashes, CSRF tokens, brute-force defense, TrustLedger audit |
| **Patient Dossier** | Fragmented across departments | Unified CareTrail timeline aggregating all encounters |
| **Bottlenecks** | Manually calculated or ignored | Rule-based FlowLens surveillance with explainable root causes |

---

## 5. Proposed System
NexusCare replaces isolated departmental software with an interconnected, transaction-safe ERP web application:
- **Centralized Data Lake:** Single database with 47 tables, 15 departments, and 2 operational views.
- **Role-Based Workflows:** Specialized dashboards tailored to 9 distinct healthcare roles.
- **Real-Time Operational Intelligence:** Dynamic token queues and bed matrices updating via AJAX.

---

## 6. Novel Design Choices & Five Advanced Engines
NexusCare goes beyond simple CRUD operations with five clinical intelligence features:

1. **QueueFlow (Live Patient Flow Intelligence):**
   - Atomic daily token generation per physician (`uq_doctor_daily_token`).
   - Dynamic estimated waiting time calculated from eligible patients ahead and doctor's historical consultation duration.
   - Audited priority elevation (`normal`, `urgent`, `emergency`) with recorded medical justification.
   - Near-real-time client-side polling every 12 seconds.

2. **BedMatrix (Dynamic Bed & Resource Allocation):**
   - Visual ward grid tracking occupancy across Standard, Semi-Private, ICU, and CCU care levels.
   - Concurrency-safe allocation with row-level locking (`FOR UPDATE`) preventing double-booking.
   - Bed lifecycle states: `Available` → `Occupied` → `Cleaning` → `Sanitized / Ready`.

3. **CareTrail (Unified Patient Journey Timeline):**
   - Longitudinal patient timeline aggregating all encounters across appointments, triage, doctor consultations, lab tests, prescriptions, bed transfers, and invoice settlements.

4. **FlowLens (Hospital Bottleneck Detection):**
   - Explainable, deterministic operational surveillance querying live database records against configurable thresholds.
   - Flags delays in outpatient waiting, elevated bed occupancy (>85%), and pending lab backlogs.
   - Provides concrete root-cause explanations and operational resolutions.

5. **TrustLedger (Smart DBMS Audit & Data Integrity):**
   - Unified audit logging recording actor, role, action, entity, IP address, and changed values.
   - Brute-force surveillance with account lockout after 5 consecutive failures.
   - Active foreign-key constraint inspector querying `INFORMATION_SCHEMA.TABLE_CONSTRAINTS`.

---

## 7. Technology Stack
- **Backend:** PHP 8.x (Native PDO, Session Management, Prepared Statements)
- **Database:** Dual-Engine Architecture
  - **Production:** Centralized MySQL 8.x / MariaDB 10.4+ (InnoDB Engine, Foreign Keys, CHECK Constraints, Row Locks)
  - **Serverless / Demo / Viva Review:** High-performance Embedded SQLite with automatic fallback and pre-seeded dataset for seamless zero-configuration execution on Vercel
- **Frontend:** HTML5, CSS3, JavaScript ES6
- **UI Framework:** Bootstrap 5.3.2 + Bootstrap Icons
- **Visualizations:** Chart.js 4.4.1
- **Testing:** Native PHP Automated CLI Test Runner (`tests/run_tests.php`)
- **Hosting Strategy:** Local XAMPP; Hybrid Vercel Frontend + Compatible PHP Host + Cloud MySQL

---

## 8. System Architecture
```
┌────────────────────────────────────────────────────────┐
│                   WEB CLIENT BROWSER                   │
│   Responsive Bootstrap 5 UI  •  Chart.js  •  AJAX Polling│
└───────────────────────────┬────────────────────────────┘
                            │ HTTPS / Session Cookies
┌───────────────────────────▼────────────────────────────┐
│                    PHP 8.x BACKEND                     │
│  - Common Login & Session Hardening                    │
│  - Server-Side Role-Based Access Control (RBAC)        │
│  - CSRF Defense & Input Normalization                  │
│  - Core Services: QueueFlow, BedMatrix, FlowLens       │
└───────────────────────────┬────────────────────────────┘
                            │ PDO Prepared Statements
┌───────────────────────────▼────────────────────────────┐
│              CENTRALIZED MYSQL DATABASE                │
│                     (kvm_hospital)                     │
│  - 47 Relational Tables (InnoDB)                       │
│  - Foreign Keys & Referential Locks                    │
│  - Row-Level Locking (SELECT ... FOR UPDATE)           │
│  - CHECK Constraints (chk_med_stock, chk_inv_totals)   │
└────────────────────────────────────────────────────────┘
```

---

## 9. Entity-Relationship (ER) Diagram
A detailed ER diagram and foreign key specification are provided in [`docs/ERD.md`](file:///c:/Users/74185/.gemini/antigravity-ide/scratch/kvm-hospital/docs/ERD.md).

---

## 10. Database Schema & Relationships
The centralized relational catalog is documented in [`docs/DATA_DICTIONARY.md`](file:///c:/Users/74185/.gemini/antigravity-ide/scratch/kvm-hospital/docs/DATA_DICTIONARY.md).
- **Schema File:** [`database/schema.sql`](file:///c:/Users/74185/.gemini/antigravity-ide/scratch/kvm-hospital/database/schema.sql) (47 tables, 2 views).
- **Seed File:** [`database/seed.sql`](file:///c:/Users/74185/.gemini/antigravity-ide/scratch/kvm-hospital/database/seed.sql) (Fictional demo data across all modules).

---

## 11. Module Descriptions
1. **Patient Management:** Code generation (`PAT-YYYY-XXXX`), demographics, emergency contacts, history.
2. **Staff & Doctors:** Profile credentials, departmental assignments, consultation fee rates.
3. **Departments:** 15 clinical and administrative divisions.
4. **Appointments:** Multi-state scheduling (`pending`, `confirmed`, `checked_in`, `completed`).
5. **Consultations & EMR:** Chief complaints, physical examination, diagnosis, prescriptions.
6. **Laboratory:** Diagnostic orders, sample collection tracking, technician result verification.
7. **Pharmacy:** Medication directory, batch expiry tracking, non-negative inventory dispensing.
8. **BedMatrix & Inpatient:** Concurrency-safe bed allocation, transfer, and sanitization.
9. **Billing & Invoices:** Itemized invoices (`INV-YYYY-XXXX`), line-item aggregation, payment receipts.
10. **Reports & Analytics:** Live Chart.js visualizations for revenue, department workload, and bed occupancy.

---

## 12. Common Login & Role-Based Workflow
All users authenticate via `public/login.php`. No separate forms exist for doctors or nurses.
1. User enters username/email and password.
2. System checks brute-force lockout status in `login_attempts`.
3. Account is fetched and verified via `password_verify()`.
4. Role assignments are loaded from `user_role_assignments`.
5. Session ID is regenerated (`session_regenerate_id(true)`).
6. User is redirected to their role-specific dashboard:
   - Super Admin → `modules/admin/super_admin_dashboard.php`
   - Hospital Admin → `modules/admin/dashboard.php`
   - Doctor → `modules/doctor/dashboard.php`
   - Receptionist → `modules/reception/dashboard.php`
   - Nurse → `modules/nurse/dashboard.php`
   - Laboratory Technician → `modules/laboratory/dashboard.php`
   - Pharmacist → `modules/pharmacy/dashboard.php`
   - Billing Staff → `modules/billing/dashboard.php`
   - Patient → `modules/patient/dashboard.php`

---

## 13. Installation on XAMPP
1. Move the `kvm-hospital` folder to `C:\xampp\htdocs\kvm-hospital`.
2. Start **Apache** and **MySQL** via the XAMPP Control Panel.
3. Create database `kvm_hospital` in phpMyAdmin (`http://localhost/phpmyadmin`).
4. Import `database/schema.sql` and `database/seed.sql`.
5. Copy `.env.example` to `.env` and verify database credentials.
6. Open your browser and navigate to `http://localhost/kvm-hospital/public/`.

---

## 14. Database Import Instructions (CLI)
```powershell
# Create database
mysql -u root -e "CREATE DATABASE IF NOT EXISTS kvm_hospital CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import schema and seed data
Get-Content database/schema.sql | mysql -u root kvm_hospital
Get-Content database/seed.sql | mysql -u root kvm_hospital
```

---

## 15. Demo Accounts
All pre-seeded demo accounts share the password: **`Password123!`**

Access is consolidated into three primary entry points, with the Hospital Administrator having universal control over all clinical stations:

| Portal / Role | Username | Email | Operational Access & Capabilities |
|---|---|---|---|
| **Hospital Administrator** | `admin` (or `superadmin`) | `admin@nexuscare.org` | **Universal Master Access:** Hospital Command Center, BedMatrix, QueueFlow, CareTrail, FlowLens, TrustLedger, plus **all 5 operational stations**: Reception Desk, Nursing Station, Diagnostic Lab, Pharmacy Unit, and Billing Center. |
| **Doctor** | `dr.sharma` | `dr.sharma@nexuscare.org` | Clinical Workspace: Assigned appointments, OPD patient consultations, electronic prescriptions, diagnostic test orders. |
| **Patient** | `patient.arun` | `arun.verma@example.com` | Patient Self-Service Portal: Personal appointment scheduling, live queue tracking, CareTrail journey dossier, lab reports, billing invoices. |

*Note: Direct staff logins (Receptionist, Nurse, Lab Tech, Pharmacist, Billing) are consolidated directly into the Administrator login. The login page provides quick-fill buttons for Administrator, Doctor, and Patient.*

---

## 16. Testing Instructions
Run the automated test suite from the terminal:
```bash
php tests/run_tests.php
```
**Results:** 11 of 11 tests pass with zero failures. Detailed report in [`docs/TEST_REPORT.md`](file:///c:/Users/74185/.gemini/antigravity-ide/scratch/kvm-hospital/docs/TEST_REPORT.md).

---

## 17. Vercel & Backend Deployment Architecture
Detailed hybrid deployment instructions are provided in [`docs/DEPLOYMENT_STRATEGY.md`](file:///c:/Users/74185/.gemini/antigravity-ide/scratch/kvm-hospital/docs/DEPLOYMENT_STRATEGY.md) and [`vercel.json`](file:///c:/Users/74185/.gemini/antigravity-ide/scratch/kvm-hospital/vercel.json).

---

## 18. Environment Configuration
Configuration is managed through environment variables or `app/config/database.php`. See [`.env.example`](file:///c:/Users/74185/.gemini/antigravity-ide/scratch/kvm-hospital/.env.example).

---

## 19. Security Considerations
- **SQL Injection Prevention:** 100% prepared statements via PDO.
- **Password Security:** Salted hashes generated with `password_hash()` and verified with `password_verify()`.
- **CSRF Defense:** Cryptographic session tokens validated on state-changing requests.
- **XSS Mitigation:** Output sanitization using `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- **Brute-Force Lockout:** Accounts throttled after 5 consecutive failed attempts.
- **Audit Trails:** TrustLedger logs administrative actions.

---

## 20. Limitations
- AJAX polling uses near-real-time intervals (12 seconds) rather than WebSockets.
- Payment processing utilizes demonstration cash/card records rather than a production gateway.
- Cloud deployment requires a PHP-capable host alongside Vercel for frontend assets.

---

## 21. Future Enhancements
- Biometric patient check-in kiosks.
- HL7 / FHIR data exchange protocols for external electronic health record integration.
- Automated SMS and WhatsApp notifications for queue token updates.

---

## 22. Screenshots to Capture for Project Viva
1. **Public Landing Page:** Showcasing live database KPI widgets.
2. **Common Login Portal:** Demonstrating role selection and brute-force protection.
3. **QueueFlow Monitor:** Live token sequence, wait time estimates, and triage modal.
4. **BedMatrix Grid:** Real-time ward visualization and bed status cards.
5. **CareTrail Dossier:** Longitudinal patient journey milestone timeline.
6. **FlowLens Dashboard:** Bottleneck alerts with root-cause explanations and Chart.js graphs.
7. **TrustLedger:** Audit activity log and active foreign key constraint verification.

---

## 23. Viva Preparation Questions
A comprehensive question-and-answer guide for technical viva defense is available in [`docs/VIVA_PREPARATION.md`](file:///c:/Users/74185/.gemini/antigravity-ide/scratch/kvm-hospital/docs/VIVA_PREPARATION.md).
