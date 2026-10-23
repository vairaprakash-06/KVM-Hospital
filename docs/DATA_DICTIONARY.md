# KVM Hospital Management System — NexusCare
## Comprehensive Data Dictionary

This document details the complete relational database catalog for `kvm_hospital`, implemented in MySQL 8.x/MariaDB with the InnoDB storage engine and `utf8mb4` encoding.

---

### Table of Contents
1. [Authentication & Authorization](#1-authentication--authorization)
2. [Hospital Organization & Profiles](#2-hospital-organization--profiles)
3. [Patient Care & Encounters](#3-patient-care--encounters)
4. [QueueFlow (Live Patient Flow Intelligence)](#4-queueflow-live-patient-flow-intelligence)
5. [BedMatrix (Dynamic Bed & Resource Allocation)](#5-bedmatrix-dynamic-bed--resource-allocation)
6. [Clinical Records & Pharmacy](#6-clinical-records--pharmacy)
7. [Laboratory Management](#7-laboratory-management)
8. [CareTrail (Unified Patient Journey Timeline)](#8-caretrail-unified-patient-journey-timeline)
9. [FlowLens (Hospital Bottleneck Detection)](#9-flowlens-hospital-bottleneck-detection)
10. [TrustLedger & Security](#10-trustledger--security)
11. [Billing & Financial Management](#11-billing--financial-management)

---

### 1. Authentication & Authorization

#### `roles`
Stores standard system roles.
| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT | Unique role identifier |
| `role_name` | VARCHAR(50) | UNIQUE, NOT NULL | Role code (e.g. `super_admin`, `doctor`, `nurse`) |
| `display_name` | VARCHAR(100) | NOT NULL | Human-readable title |
| `description` | TEXT | NULL | Operational scope description |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Record creation timestamp |

#### `permissions`
Granular operational capabilities.
| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT | Primary key |
| `permission_key` | VARCHAR(80) | UNIQUE, NOT NULL | Machine code (e.g. `patient.create`, `bed.assign`) |
| `description` | VARCHAR(255) | NOT NULL | Detailed action permission description |

#### `role_permissions`
Many-to-many junction mapping capabilities to roles.
| Column | Type | Constraints | Description |
|---|---|---|---|
| `role_id` | INT UNSIGNED | FK -> `roles.id` ON DELETE CASCADE | Target role |
| `permission_id` | INT UNSIGNED | FK -> `permissions.id` ON DELETE CASCADE | Granted permission |
| *PK* | (role_id, permission_id) | Composite Primary Key | Prevents duplicate assignments |

#### `users`
Unified authentication accounts for all 9 departments and patients.
| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT | User ID |
| `username` | VARCHAR(60) | UNIQUE, NOT NULL | Login handle |
| `email` | VARCHAR(150) | UNIQUE, NOT NULL | Registered email |
| `password_hash` | VARCHAR(255) | NOT NULL | Secure Argon2id / Bcrypt hash |
| `account_status` | ENUM | NOT NULL, DEFAULT 'active' | `'active'`, `'inactive'`, `'suspended'`, `'locked'` |
| `failed_login_count`| TINYINT UNSIGNED | DEFAULT 0 | Counter for brute-force lockouts |
| `lockout_until` | DATETIME | NULL | Timestamp until login is throttled |
| `last_login_at` | DATETIME | NULL | Timestamp of last successful session |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Registration date |
| `updated_at` | TIMESTAMP | ON UPDATE CURRENT_TIMESTAMP | Last profile modification |

#### `user_role_assignments`
Assigns one or more authorized roles to each user.
| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT | Assignment ID |
| `user_id` | INT UNSIGNED | FK -> `users.id` ON DELETE CASCADE | Associated user |
| `role_id` | INT UNSIGNED | FK -> `roles.id` ON DELETE CASCADE | Assigned role |
| `assigned_by` | INT UNSIGNED | FK -> `users.id` NULL | Super Admin who granted role |
| `assigned_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Role grant timestamp |
| *Constraint* | UNIQUE (user_id, role_id) | Unique | Prohibits duplicate role grants |

---

### 2. Hospital Organization & Profiles

#### `departments`
Clinical, diagnostic, and administrative departments.
| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT | Department ID |
| `code` | VARCHAR(20) | UNIQUE, NOT NULL | Department code (e.g. `CARDIO`, `EMERG`) |
| `name` | VARCHAR(100) | NOT NULL | Full department title |
| `type` | ENUM | NOT NULL | `'clinical'`, `'diagnostic'`, `'support'`, `'admin'` |
| `building` | VARCHAR(50) | NULL | Hospital wing / tower |
| `floor` | VARCHAR(20) | NULL | Floor level |
| `is_active` | TINYINT(1) | DEFAULT 1 | Operational status flag |

#### `staff_profiles`
Administrative and support staff details.
| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT | Staff profile ID |
| `user_id` | INT UNSIGNED | FK -> `users.id` UNIQUE | Associated user account |
| `department_id` | INT UNSIGNED | FK -> `departments.id` | Primary department |
| `staff_code` | VARCHAR(30) | UNIQUE, NOT NULL | Institutional employee code |
| `first_name` | VARCHAR(60) | NOT NULL | Given name |
| `last_name` | VARCHAR(60) | NOT NULL | Family name |
| `phone` | VARCHAR(25) | NOT NULL | Contact telephone |
| `designation` | VARCHAR(100) | NOT NULL | Official hospital title |

#### `doctor_profiles`
Medical doctor credentials, schedules, and fee rates.
| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT | Doctor profile ID |
| `user_id` | INT UNSIGNED | FK -> `users.id` UNIQUE | Doctor's user account |
| `department_id` | INT UNSIGNED | FK -> `departments.id` | Medical department |
| `doctor_code` | VARCHAR(30) | UNIQUE, NOT NULL | Medical registration identifier |
| `first_name` | VARCHAR(60) | NOT NULL | Given name |
| `last_name` | VARCHAR(60) | NOT NULL | Family name |
| `specialization`| VARCHAR(120) | NOT NULL | Clinical specialty |
| `qualification` | VARCHAR(120) | NOT NULL | Academic degrees (e.g. MD, MS, MBBS) |
| `consultation_fee`| DECIMAL(10,2) | NOT NULL, DEFAULT 500.00 | Standard Outpatient fee |
| `avg_consult_duration_min` | TINYINT UNSIGNED | DEFAULT 15 | Baseline consultation duration for QueueFlow |
| `is_available` | TINYINT(1) | DEFAULT 1 | Real-time availability indicator |

#### `patient_profiles`
Unified patient demographics, medical identifiers, and emergency contacts.
| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT | Patient profile ID |
| `user_id` | INT UNSIGNED | FK -> `users.id` UNIQUE, NULL | Optional linked patient portal account |
| `patient_code` | VARCHAR(30) | UNIQUE, NOT NULL | System code: `PAT-YYYY-XXXX` |
| `first_name` | VARCHAR(60) | NOT NULL | Given name |
| `last_name` | VARCHAR(60) | NOT NULL | Surname |
| `date_of_birth` | DATE | NOT NULL | Birth date |
| `gender` | ENUM | NOT NULL | `'male'`, `'female'`, `'other'` |
| `blood_group` | ENUM | NULL | `'A+'`, `'A-'`, `'B+'`, `'B-'`, `'AB+'`, `'AB-'`, `'O+'`, `'O-'` |
| `phone` | VARCHAR(25) | NOT NULL | Primary contact phone |
| `email` | VARCHAR(120) | NULL | Contact email |
| `emergency_contact_name` | VARCHAR(100) | NOT NULL | Emergency contact person |
| `emergency_contact_phone`| VARCHAR(25) | NOT NULL | Emergency contact phone |
| `allergies` | TEXT | NULL | Documented drug/environmental allergies |

---

### 3. Patient Care & Encounters

#### `patient_encounters`
Container grouping an entire episode of care across consultations, lab orders, beds, and billing.
| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT | Encounter ID |
| `patient_id` | INT UNSIGNED | FK -> `patient_profiles.id` | Patient record |
| `encounter_type` | ENUM | NOT NULL | `'outpatient'`, `'inpatient'`, `'emergency'` |
| `status` | ENUM | NOT NULL | `'in_progress'`, `'completed'`, `'cancelled'` |
| `started_at` | DATETIME | NOT NULL | Encounter commencement |
| `ended_at` | DATETIME | NULL | Encounter conclusion |

#### `appointments`
Outpatient visit bookings and slot management.
| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT | Appointment ID |
| `appointment_code` | VARCHAR(30) | UNIQUE, NOT NULL | Code: `APT-YYYY-XXXX` |
| `patient_id` | INT UNSIGNED | FK -> `patient_profiles.id` | Patient |
| `doctor_id` | INT UNSIGNED | FK -> `doctor_profiles.id` | Assigned doctor |
| `department_id` | INT UNSIGNED | FK -> `departments.id` | Clinic department |
| `appointment_date` | DATE | NOT NULL | Scheduled appointment date |
| `appointment_time` | TIME | NOT NULL | Scheduled appointment slot |
| `status` | ENUM | NOT NULL | `'pending'`, `'confirmed'`, `'checked_in'`, `'in_consultation'`, `'completed'`, `'cancelled'`, `'no_show'` |
| `reason` | VARCHAR(255) | NULL | Chief complaint / purpose |
| `created_by` | INT UNSIGNED | FK -> `users.id` | Staff who booked slot |

---

### 4. QueueFlow (Live Patient Flow Intelligence)

#### `queue_entries`
Real-time token queue for outpatient consultations.
| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT | Queue entry ID |
| `token_number` | SMALLINT UNSIGNED | NOT NULL | Daily token number for doctor/department |
| `queue_date` | DATE | NOT NULL | Date of queue session |
| `patient_id` | INT UNSIGNED | FK -> `patient_profiles.id` | Queued patient |
| `appointment_id` | INT UNSIGNED | FK -> `appointments.id` NULL | Linked appointment |
| `doctor_id` | INT UNSIGNED | FK -> `doctor_profiles.id` | Assigned consulting doctor |
| `department_id` | INT UNSIGNED | FK -> `departments.id` | Clinic department |
| `status` | ENUM | NOT NULL | `'waiting'`, `'called'`, `'in_consultation'`, `'completed'`, `'skipped'`, `'cancelled'` |
| `priority` | ENUM | NOT NULL, DEFAULT 'normal'| `'normal'`, `'urgent'`, `'emergency'` |
| `priority_reason`| VARCHAR(255) | NULL | Audited justification for priority override |
| `check_in_time` | DATETIME | NOT NULL | Check-in timestamp |
| `call_time` | DATETIME | NULL | Timestamp doctor called token |
| `consult_start_time` | DATETIME | NULL | Consultation start |
| `consult_end_time` | DATETIME | NULL | Consultation completion |
| `estimated_wait_minutes` | INT | DEFAULT 0 | Dynamically calculated queue wait time |
| *Constraint* | UNIQUE (doctor_id, queue_date, token_number) | Unique per Doctor/Day | Guarantees atomic non-conflicting tokens |

#### `queue_status_history`
Lifecycle auditing of every state transition in the queue.
| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT | Audit row ID |
| `queue_entry_id`| INT UNSIGNED | FK -> `queue_entries.id` ON DELETE CASCADE | Associated token |
| `previous_status`| ENUM | NULL | Status prior to change |
| `new_status` | ENUM | NOT NULL | Updated queue status |
| `changed_by` | INT UNSIGNED | FK -> `users.id` | Staff member responsible |
| `changed_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Timestamp of state transition |

---

### 5. BedMatrix (Dynamic Bed & Resource Allocation)

#### `wards` & `rooms`
Physical hospital infrastructure hierarchy.
- **`wards`**: `id`, `name`, `code`, `ward_type` (`'general'`, `'icu'`, `'ccu'`, `'pediatric'`, `'post_op'`), `department_id`, `base_daily_rate`.
- **`rooms`**: `id`, `ward_id`, `room_number`, `floor`, `room_type`.

#### `beds`
Hospital bed inventory with care-level and occupancy tracking.
| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT | Bed identifier |
| `room_id` | INT UNSIGNED | FK -> `rooms.id` | Physical room |
| `bed_number` | VARCHAR(20) | NOT NULL | Bed designation (e.g. `ICU-B01`) |
| `care_level` | ENUM | NOT NULL | `'standard'`, `'intermediate'`, `'intensive'`, `'isolation'` |
| `status` | ENUM | NOT NULL, DEFAULT 'available' | `'available'`, `'occupied'`, `'reserved'`, `'cleaning'`, `'maintenance'` |
| `is_active` | TINYINT(1) | DEFAULT 1 | Whether bed is commissioned |
| *Constraint* | UNIQUE (room_id, bed_number) | Unique | No duplicate bed numbers per room |

#### `bed_assignments`
Transactional bed occupancy ledger with concurrency protection.
| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT | Assignment ID |
| `bed_id` | INT UNSIGNED | FK -> `beds.id` | Allocated bed |
| `patient_id` | INT UNSIGNED | FK -> `patient_profiles.id` | Admitted patient |
| `encounter_id` | INT UNSIGNED | FK -> `patient_encounters.id` | Clinical encounter |
| `admission_date`| DATETIME | NOT NULL | Check-in timestamp |
| `discharge_date`| DATETIME | NULL | Check-out / transfer timestamp |
| `status` | ENUM | NOT NULL | `'active'`, `'transferred'`, `'discharged'` |
| `daily_rate` | DECIMAL(10,2) | NOT NULL | Applied daily bed charge |
| `assigned_by` | INT UNSIGNED | FK -> `users.id` | Nurse/Admin who admitted patient |

---

### 6. Clinical Records & Pharmacy

#### `consultations`
Clinical encounter diagnosis and physical findings.
| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT | Consultation record ID |
| `encounter_id` | INT UNSIGNED | FK -> `patient_encounters.id` | Patient encounter |
| `appointment_id`| INT UNSIGNED | FK -> `appointments.id` NULL | Originating appointment |
| `patient_id` | INT UNSIGNED | FK -> `patient_profiles.id` | Patient |
| `doctor_id` | INT UNSIGNED | FK -> `doctor_profiles.id` | Consulting physician |
| `symptoms` | TEXT | NOT NULL | Reported subjective complaints |
| `diagnosis` | TEXT | NOT NULL | Primary clinical diagnosis |
| `clinical_notes`| TEXT | NULL | Objective assessment & treatment plan |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Consultation timestamp |

#### `prescriptions` & `prescription_items`
Medication orders generated by doctors.
- **`prescriptions`**: `id`, `consultation_id`, `patient_id`, `doctor_id`, `notes`, `created_at`.
- **`prescription_items`**: `id`, `prescription_id`, `medicine_id`, `dosage`, `frequency`, `duration_days`, `instructions`.

#### `medicines` & `medicine_batches`
Pharmacy inventory with strict stock and expiration management.
- **`medicines`**: `id`, `name`, `generic_name`, `category`, `unit_price`, `stock_quantity`, `reorder_level`.
  - *CHECK constraint:* `chk_med_stock (stock_quantity >= 0)`.
- **`medicine_batches`**: `id`, `medicine_id`, `batch_number`, `expiry_date`, `quantity_available`.
- **`pharmacy_dispensing_records`**: `id`, `prescription_item_id`, `dispensed_quantity`, `dispensed_by`, `dispensed_at`.

---

### 7. Laboratory Management

#### `lab_tests`
Diagnostic test directory and reference intervals.
| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT | Test ID |
| `test_code` | VARCHAR(30) | UNIQUE, NOT NULL | Catalog code (e.g. `CBC`, `LIPID`, `HbA1c`) |
| `name` | VARCHAR(120) | NOT NULL | Full descriptive name |
| `category` | VARCHAR(60) | NOT NULL | Biochemistry, Hematology, Microbiology |
| `standard_charge`| DECIMAL(10,2) | NOT NULL | Cost in INR |
| `reference_range`| VARCHAR(255) | NULL | Normal diagnostic bounds |
| `turnaround_target_hours` | SMALLINT | DEFAULT 4 | FlowLens benchmark TAT |

#### `lab_orders` & `lab_order_items`
Lab requisitions, sample collection tracking, and technician results.
- **`lab_orders`**: `id`, `encounter_id`, `patient_id`, `doctor_id`, `status` (`'ordered'`, `'collected'`, `'processing'`, `'completed'`, `'cancelled'`), `order_date`.
- **`lab_order_items`**: `id`, `lab_order_id`, `test_id`, `result_value`, `result_status` (`'normal'`, `'abnormal'`, `'critical'`), `technician_notes`, `verified_by`.

---

### 8. CareTrail (Unified Patient Journey Timeline)

#### `patient_journey_events`
Longitudinal chronological ledger spanning every clinical and administrative event.
| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT | Event record ID |
| `patient_id` | INT UNSIGNED | FK -> `patient_profiles.id` ON DELETE CASCADE | Associated patient |
| `encounter_id` | INT UNSIGNED | FK -> `patient_encounters.id` NULL | Linked care episode |
| `event_category`| ENUM | NOT NULL | `'registration'`, `'appointment'`, `'queue'`, `'consultation'`, `'lab'`, `'pharmacy'`, `'admission'`, `'transfer'`, `'billing'`, `'discharge'` |
| `event_title` | VARCHAR(120) | NOT NULL | Concise headline |
| `event_description` | TEXT | NULL | Detailed narrative description |
| `department_id` | INT UNSIGNED | FK -> `departments.id` NULL | Originating department |
| `actor_user_id` | INT UNSIGNED | FK -> `users.id` NULL | User who caused or logged the event |
| `source_table` | VARCHAR(50) | NULL | Provenance table name |
| `source_id` | INT UNSIGNED | NULL | Originating record foreign key |
| `event_timestamp`| DATETIME | NOT NULL | Real-world occurrence time |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Ingestion timestamp |

---

### 9. FlowLens (Hospital Bottleneck Detection)

#### `flowlens_thresholds`
Configurable operational benchmark criteria.
| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT | Primary key |
| `metric_name` | VARCHAR(80) | UNIQUE, NOT NULL | Metric code (e.g. `queue_waiting_time_minutes`) |
| `department_id` | INT UNSIGNED | FK -> `departments.id` NULL | Department scope (NULL = hospital-wide) |
| `warning_threshold` | DECIMAL(10,2) | NOT NULL | Warning trigger point |
| `critical_threshold`| DECIMAL(10,2) | NOT NULL | Critical alarm trigger point |
| `unit` | VARCHAR(30) | NOT NULL | Metric unit (`minutes`, `percent`, `orders`) |

#### `flowlens_alerts`
Active and resolved bottleneck surveillance notifications.
| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT | Alert ID |
| `metric_name` | VARCHAR(80) | NOT NULL | Triggering metric |
| `department_id` | INT UNSIGNED | FK -> `departments.id` NULL | Impacted department |
| `severity` | ENUM | NOT NULL | `'warning'`, `'critical'` |
| `observed_value`| DECIMAL(10,2) | NOT NULL | Value observed during surveillance |
| `threshold_value`| DECIMAL(10,2)| NOT NULL | Benchmark breached |
| `alert_message` | VARCHAR(255) | NOT NULL | Clear explanation of root cause |
| `suggested_action`| VARCHAR(255) | NULL | Actionable recommendation |
| `status` | ENUM | NOT NULL, DEFAULT 'open' | `'open'`, `'acknowledged'`, `'resolved'` |
| `resolved_by` | INT UNSIGNED | FK -> `users.id` NULL | Hospital administrator who resolved alert |
| `resolved_at` | DATETIME | NULL | Timestamp of resolution |

---

### 10. TrustLedger & Security

#### `audit_logs`
Immutable compliance and security activity log.
| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT | Audit ID |
| `user_id` | INT UNSIGNED | FK -> `users.id` NULL | Acting user |
| `user_role` | VARCHAR(50) | NULL | Role held at time of action |
| `action_type` | VARCHAR(50) | NOT NULL | `INSERT`, `UPDATE`, `DELETE`, `LOGIN`, `OVERRIDE` |
| `entity_type` | VARCHAR(60) | NOT NULL | Target entity (e.g. `bed_assignments`, `queue_entries`) |
| `entity_id` | INT UNSIGNED | NULL | Target primary key |
| `old_values` | JSON | NULL | Previous state snapshot |
| `new_values` | JSON | NULL | Updated state snapshot |
| `ip_address` | VARCHAR(45) | NULL | Request IP |
| `user_agent` | VARCHAR(255) | NULL | Browser / device client header |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Audit commit timestamp |

#### `login_attempts`
Brute-force security monitoring table.
| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT | Primary key |
| `ip_address` | VARCHAR(45) | NOT NULL | Client IP |
| `username_entered`| VARCHAR(60) | NOT NULL | Attempted username/email |
| `attempt_status`| ENUM | NOT NULL | `'success'`, `'failed'`, `'blocked'` |
| `attempted_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Timestamp of login attempt |

---

### 11. Billing & Financial Management

#### `invoices` & `invoice_items`
Itemized patient billing with exact decimal precision.
- **`invoices`**: `id`, `invoice_code` (`INV-YYYY-XXXX`), `encounter_id`, `patient_id`, `subtotal`, `discount_amount`, `tax_amount`, `total_amount`, `paid_amount`, `balance_amount`, `status` (`'draft'`, `'issued'`, `'partially_paid'`, `'paid'`, `'cancelled'`), `due_date`.
  - *CHECK constraint:* `chk_inv_totals (total_amount >= 0)`.
- **`invoice_items`**: `id`, `invoice_id`, `item_type` (`'consultation'`, `'bed'`, `'lab'`, `'pharmacy'`, `'procedure'`), `description`, `unit_price`, `quantity`, `total_price`.
- **`payments`**: `id`, `invoice_id`, `payment_code`, `amount_paid`, `payment_method` (`'cash'`, `'card'`, `'upi'`, `'insurance'`), `transaction_ref`, `recorded_by`, `payment_date`.
