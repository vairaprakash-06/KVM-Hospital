# KVM Hospital Management System — NexusCare
## Automated Test Execution & Integrity Report

This report documents the verification and test suite execution conducted on the `kvm_hospital` database and application codebase.

---

### Test Suite Execution Summary

- **Test Runner:** `tests/run_tests.php`
- **Environment:** PHP 8.0.30 ZTS x64 on Windows | MySQL / MariaDB 10.4.32 (InnoDB)
- **Database:** `kvm_hospital`
- **Total Tests Executed:** 11
- **Passed:** 11
- **Failed:** 0
- **Execution Status:** 100% SUCCESS

---

### Detailed Test Results

| Test ID | Test Scenario | Module / Component | Expected Behavior | Actual Result | Status |
|:---:|---|---|---|---|:---:|
| `T01` | Password Verification with Hash | Auth (`password_verify`) | Matches seeded Bcrypt hash for `Password123!` | Password validated successfully | **PASS** |
| `T02` | Invalid Password Rejection | Auth (`password_verify`) | Rejects incorrect string | Rejected as expected | **PASS** |
| `T03` | Brute Force Lockout Surveillance | Security (`login_attempts`) | Inserts attempt into audit table | Recorded in DB with status `'failed'` | **PASS** |
| `T04` | Atomic Patient Code Generation | Patient Management | Formats unique `PAT-YYYY-XXXX` | Generated code follows strict sequence pattern | **PASS** |
| `T05` | Dynamic Queue Waiting Calculation | QueueFlow (`QueueService`) | Multiplies eligible patients ahead by doctor's average consult time | Dynamic wait calculation matches expectation | **PASS** |
| `T06` | Double-Booking Prevention | BedMatrix (`BedAllocationService`) | Rejects assignment when bed status != 'available' | Exception thrown: "Bed is not available" | **PASS** |
| `T07` | Bed Sanitization Workflow | BedMatrix (`BedAllocationService`) | Transitions discharged bed through cleaning to available | Status successfully updated to `'available'` | **PASS** |
| `T08` | Longitudinal Event Ingestion | CareTrail (`CareTrailService`) | Creates chronological audit entry in `patient_journey_events` | Record committed with matching patient ID | **PASS** |
| `T09` | Explainable Bottleneck Detection | FlowLens (`FlowLensService`) | Evaluates operational metrics and raises alert with root cause | Alert raised with severity, observed & threshold | **PASS** |
| `T10` | TrustLedger Audit Logging | TrustLedger (`AuditService`) | Writes mutation to `audit_logs` | Immutable audit log verified in database | **PASS** |
| `T11` | MySQL Engine CHECK Constraint | MySQL InnoDB (`chk_med_stock`) | Blocks inventory decrement below zero | MySQL throws error 4025 (CONSTRAINT_FAILED) | **PASS** |

---

### Transaction & Concurrency Verification

#### 1. BedMatrix Row-Level Lock (`SELECT ... FOR UPDATE`)
```sql
START TRANSACTION;
SELECT id, status FROM beds WHERE id = 1 FOR UPDATE;
-- Locks bed row exclusively. Concurrent transactions attempting to reserve bed 1 block until COMMIT.
INSERT INTO bed_assignments (bed_id, patient_id, ...) VALUES (1, 3, ...);
UPDATE beds SET status = 'occupied' WHERE id = 1;
COMMIT;
```
- **Integrity Result:** Zero overlapping active assignments confirmed across all wards.

#### 2. Pharmacy Non-Negative Stock Constraint
```sql
UPDATE medicines SET stock_quantity = -5 WHERE id = 1;
-- Output: ERROR 4025 (23000): CONSTRAINT `chk_med_stock` failed for `kvm_hospital`.`medicines`
```
- **Integrity Result:** Zero negative inventory entries possible at the SQL engine level.
