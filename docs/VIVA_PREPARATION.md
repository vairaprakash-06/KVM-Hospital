# KVM Hospital Management System — NexusCare
## DBMS Technical Viva & Project Review Preparation

This guide prepares students for college DBMS practical viva, project evaluations, and architecture defense.

---

### Part 1: Relational Database & SQL Concepts

#### Q1: Why did you choose MySQL with InnoDB instead of MyISAM or NoSQL?
**Answer:**
- **ACID Compliance:** InnoDB supports atomic transactions (`BEGIN`, `COMMIT`, `ROLLBACK`), which are essential for hospital operations like bed allocation, medicine dispensing, and billing.
- **Foreign Key Constraints:** InnoDB enforces declarative referential integrity (e.g. `ON DELETE RESTRICT` on clinical encounters and bills). MyISAM does not support foreign keys.
- **Row-Level Locking:** InnoDB locks individual rows (`SELECT ... FOR UPDATE`), enabling high concurrency without locking entire tables.
- **Crash Recovery:** InnoDB utilizes a write-ahead redo log (`ib_logfile`) to recover automatically from server crashes.

#### Q2: How does NexusCare prevent race conditions and double-booking in BedMatrix?
**Answer:**
- When allocating a bed, `BedAllocationService` begins a database transaction.
- It executes:
  ```sql
  SELECT id, status FROM beds WHERE id = ? FOR UPDATE;
  ```
- This places an exclusive row-level lock on the bed. If another concurrent request tries to reserve or allocate the same bed, it blocks until the first transaction completes.
- If the status is not `'available'`, the transaction throws a domain exception and rolls back immediately, guaranteeing that two patients can never be assigned the same bed simultaneously.

#### Q3: How do you handle non-negative inventory in the Pharmacy module?
**Answer:**
- At the database level, we defined a strict CHECK constraint:
  ```sql
  CONSTRAINT chk_med_stock CHECK (stock_quantity >= 0)
  ```
- Any SQL update that attempts to decrement stock below zero causes the MySQL engine to abort the statement with error code `4025 (CONSTRAINT_FAILED)`.
- At the application service level, dispensing is wrapped in a transaction that checks current stock before updating and logs an inventory adjustment record.

#### Q4: What normalization level does the database achieve?
**Answer:**
- The database satisfies **Third Normal Form (3NF)**:
  - **1NF:** All attributes are atomic (no repeating groups or comma-separated lists). Multi-valued items like medicines in prescriptions or tests in lab orders are separated into child tables (`prescription_items`, `lab_order_items`).
  - **2NF:** All non-key attributes are fully functionally dependent on the primary key (no partial dependencies).
  - **3NF:** No non-key attribute is transitively dependent on another non-key attribute (e.g., patient phone numbers are stored in `patient_profiles`, not duplicated in `appointments` or `invoices`).
- Intentional operational views (`vw_live_queue_status`, `vw_bed_occupancy_matrix`) provide pre-joined representations for performance without denormalizing base tables.

---

### Part 2: Differentiated Novel Features

#### Q5: What makes QueueFlow different from a standard queue display?
**Answer:**
- Traditional systems use static incremental ticket counters. QueueFlow incorporates:
  1. **Dynamic Waiting-Time Estimation:** Calculated from eligible patients ahead multiplied by the doctor's historical average consultation duration (`avg_consult_duration_min`).
  2. **Triage Priority Overrides:** Supports `urgent` and `emergency` priority elevation, accompanied by mandatory audited reasons stored in `queue_status_history`.
  3. **Near-Real-Time AJAX Polling:** Automatically refreshes queue displays every 12 seconds without full-page reloads.

#### Q6: Explain the architecture of CareTrail.
**Answer:**
- CareTrail is a longitudinal patient dossier. Every cross-department interaction (registration, appointment, queue check-in, doctor consultation, lab result release, bed admission, dispensing, invoice settlement) writes a milestone record to `patient_journey_events`.
- This creates an auditable chronological timeline linking all clinical and administrative encounters to their source tables via validated foreign keys.

#### Q7: How does FlowLens identify hospital bottlenecks without "fake AI"?
**Answer:**
- FlowLens uses an **explainable, deterministic rules engine** querying live database operational metrics against configured administrative thresholds (`flowlens_thresholds`):
  - Emergency department wait times exceeding 30 minutes.
  - Inpatient bed occupancy exceeding 85%.
  - Lab test orders remaining pending past turnaround targets.
- Every generated alert clearly cites the observed metric value, configured threshold, root cause, and concrete administrative recommendation.

#### Q8: What security and integrity capabilities does TrustLedger provide?
**Answer:**
- **Unified Audit Logging:** Captures actor user ID, role, action type (`INSERT`, `UPDATE`, `DELETE`, `OVERRIDE`), entity affected, and request IP.
- **Brute-Force Surveillance:** Records every login attempt in `login_attempts` and temporarily locks accounts after 5 consecutive failures.
- **Relational Integrity Auditing:** Live inspector queries MySQL `INFORMATION_SCHEMA.TABLE_CONSTRAINTS` to verify that foreign keys and check constraints are active and enforced.

---

### Part 3: Architecture & Security

#### Q9: How is Role-Based Access Control (RBAC) enforced?
**Answer:**
- All users authenticate through one common entry point (`public/login.php`).
- Passwords are verified using `password_verify()` against salted Bcrypt hashes generated via `password_hash()`.
- Upon successful login, the session ID is regenerated (`session_regenerate_id(true)`) to mitigate session fixation attacks.
- Middleware functions `requireAuth()`, `requireRole()`, and `requirePermission()` inspect `$_SESSION['user']['roles']` on every protected page and API endpoint. State changes require a valid cryptographic CSRF token.

#### Q10: How does the application handle Vercel deployment constraints?
**Answer:**
- Vercel is a serverless frontend edge platform that does not support long-running stateful PHP runtimes or direct persistent MySQL socket connections.
- We structured the architecture with decoupled deployment support:
  - **Frontend & Static Assets:** Served via Vercel with clean URL rewrites in `vercel.json`.
  - **Backend API & Web Server:** Hosted on a compatible PHP 8.x host (e.g. Render, Railway, or Apache VPS).
  - **Database:** Managed MySQL instance with TLS encryption.
