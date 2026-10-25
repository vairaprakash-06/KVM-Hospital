# KVM Hospital Management System — NexusCare
## Role-Based Access Control (RBAC) & Consolidated Portal Architecture

NexusCare implements strict server-side authorization. Access permissions are validated on every request via `require_role()` and `require_permission()`.

### Consolidated 3-Portal Architecture
To optimize administrative workflow and governance:
1. **Hospital Administrator (Universal All-Access Portal)**:
   - Full master access to all hospital modules, command centers, and operational stations:
     - **Reception Desk** (`/modules/reception/dashboard.php`)
     - **Nursing Station** (`/modules/nurse/dashboard.php`)
     - **Diagnostic Lab** (`/modules/laboratory/dashboard.php`)
     - **Pharmacy Unit** (`/modules/pharmacy/dashboard.php`)
     - **Billing Center** (`/modules/billing/dashboard.php`)
     - BedMatrix, QueueFlow, CareTrail, FlowLens, and TrustLedger.
2. **Doctor (Clinical Workspace Portal)**:
   - OPD consultation queue, patient clinical dossier, electronic prescriptions, lab order placement.
3. **Patient (Longitudinal Self-Service Portal)**:
   - Appointment booking, personal CareTrail timeline, test results, billing history, payment receipts.

> **Login Surface Restriction**: Individual staff logins (Receptionist, Nurse, Lab Tech, Pharmacist, Billing Staff) are consolidated directly into the Administrator login. Direct login access is exclusively reserved for **Admin**, **Doctor**, and **Patient**.
| **Organization & Master Data** | | | | | | | | | |
| Manage Departments & Wings | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Manage Doctor Profiles & Schedules | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Manage Bed & Ward Inventory | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Patient Registration & Scheduling** | | | | | | | | | |
| Register New Patients | ✅ | ✅ | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Edit Patient Demographics | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | Own |
| Book & Reschedule Appointments | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | Own |
| Patient Check-In & Queue Token | ✅ | ✅ | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| View Live Patient Waiting Queue | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | Own |
| Override Queue Priority (Triage) | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Clinical Encounters & Care** | | | | | | | | | |
| View Clinical Records / EMR | ✅ | ❌ | ✅ (Assigned) | ❌ | ✅ (Assigned) | ❌ | ❌ | ❌ | Own |
| Record Consultations & Diagnoses | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Issue Prescriptions | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Order Laboratory Diagnostic Tests | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| View CareTrail Journey Timeline | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | Own |
| Add Milestone CareTrail Event | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Inpatient BedMatrix Management** | | | | | | | | | |
| View Real-Time BedMatrix Grid | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Allocate / Admit Patient to Bed | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Transfer Patient Bed | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Discharge Patient & Send to Cleaning | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Complete Bed Sanitization Certification | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Laboratory Diagnostics** | | | | | | | | | |
| View Requisition Worklist | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| Collect Biological Specimen | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Enter Diagnostic Test Results | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| Verify & Release Lab Report | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| View Patient Lab Reports | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ❌ | ❌ | Own |
| **Pharmacy & Drug Inventory** | | | | | | | | | |
| Manage Medicine Master & Batches | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| Record Medicine Restocking | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| Review Doctor Prescriptions | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| Dispense Medicines (Atomic Stock Decr)| ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| View Low Stock & Expiry Alerts | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| **Billing & Payments** | | | | | | | | | |
| Generate Itemized Patient Invoice | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ |
| Add Consultation / Bed Charges | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ |
| Record Patient Payment & Receipt | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ |
| View Invoices & Receipts | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | Own |
| **Operational Intelligence & Analytics** | | | | | | | | | |
| View Hospital KPI Dashboards | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| View FlowLens Bottleneck Detection | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Configure FlowLens Alert Thresholds | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Resolve Operational Bottlenecks | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Export Operational Reports | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

---

### Multi-Role Conflict Resolution Policy

1. When a staff user holds multiple roles (e.g. `Doctor` + `Department Head`), their effective permissions are calculated as the **union** of all assigned role capabilities.
2. If an administrative user accesses a patient record without an active clinical assignment, their access is logged as an audited oversight event in `audit_logs` under the TrustLedger engine.
3. Patient role accounts are strictly partitioned; a patient can never access or query records belonging to another patient code.
