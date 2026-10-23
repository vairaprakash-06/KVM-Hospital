# KVM Hospital Management System — NexusCare
## Internal AJAX & REST API Documentation

All API endpoints require session-based authentication or explicit authorization headers. JSON is returned with standardized HTTP status codes.

---

### Standard Response Format

#### Success Response (HTTP 200 / 201)
```json
{
  "success": true,
  "message": "Operation completed successfully.",
  "data": { ... },
  "timestamp": "2026-10-23T16:00:00Z"
}
```

#### Error Response (HTTP 400 / 401 / 403 / 404 / 500)
```json
{
  "success": false,
  "error": "Human-readable error description.",
  "code": "INVALID_PARAMETERS",
  "timestamp": "2026-10-23T16:00:00Z"
}
```

---

### 1. QueueFlow Live Endpoints

#### `GET /api/queue.php?action=live_status`
Returns real-time queue metrics, currently calling token, waiting list, and dynamic waiting time estimation.

- **Authentication:** Required (Any authenticated staff or logged-in patient)
- **Parameters:**
  - `department_id` (optional, integer): Filter by department.
  - `doctor_id` (optional, integer): Filter by specific doctor.
- **Example Response:**
```json
{
  "success": true,
  "data": {
    "summary": {
      "total_today": 18,
      "currently_waiting": 5,
      "in_consultation": 1,
      "completed": 12,
      "average_wait_minutes": 14
    },
    "current_token": {
      "token_number": 6,
      "patient_name": "Arun Kumar",
      "doctor_name": "Dr. Rajesh Sharma",
      "department_name": "Cardiology",
      "priority": "normal",
      "status": "in_consultation"
    },
    "waiting_list": [
      {
        "id": 7,
        "token_number": 7,
        "patient_code": "PAT-2024-0002",
        "patient_name": "Meera Patel",
        "priority": "urgent",
        "estimated_wait_minutes": 15,
        "check_in_time": "10:15 AM"
      }
    ]
  }
}
```

#### `POST /api/queue.php?action=override_priority`
Elevates or de-escalates a patient's queue priority with mandatory recorded reason.

- **Authentication:** Required (`queue.override` permission: Doctor, Nurse, Admin)
- **Body (form-data or JSON):**
  - `csrf_token`: Session CSRF token
  - `queue_id`: Integer ID of the queue entry
  - `priority`: `'normal'` | `'urgent'` | `'emergency'`
  - `reason`: String justification for audit log
- **Example Response:**
```json
{
  "success": true,
  "message": "Queue priority updated to urgent and audited in TrustLedger.",
  "queue_id": 7,
  "priority": "urgent"
}
```

---

### 2. BedMatrix Live Endpoints

#### `GET /api/beds.php?action=matrix`
Returns the status of all hospital beds grouped by ward and care level.

- **Authentication:** Required (Staff role)
- **Parameters:**
  - `ward_id` (optional, integer)
  - `care_level` (optional, string: `standard`, `intermediate`, `intensive`, `isolation`)
- **Example Response:**
```json
{
  "success": true,
  "data": {
    "total_beds": 35,
    "available": 14,
    "occupied": 16,
    "cleaning": 3,
    "maintenance": 2,
    "occupancy_rate_pct": 53.3,
    "wards": [
      {
        "ward_id": 1,
        "ward_name": "Intensive Coronary Care Unit (ICCU)",
        "care_level": "intensive",
        "beds": [
          { "id": 1, "bed_number": "ICU-01", "status": "occupied", "patient": "Rahul Verma" },
          { "id": 2, "bed_number": "ICU-02", "status": "available", "patient": null },
          { "id": 3, "bed_number": "ICU-03", "status": "cleaning", "patient": null }
        ]
      }
    ]
  }
}
```

---

### 3. FlowLens Real-Time Surveillance Endpoints

#### `GET /api/flowlens.php?action=active_alerts`
Polls current hospital bottlenecks detected by the explainable rules engine.

- **Authentication:** Required (Admin, Dept Head)
- **Example Response:**
```json
{
  "success": true,
  "alerts_count": 2,
  "alerts": [
    {
      "id": 1,
      "severity": "critical",
      "department": "Emergency",
      "metric": "queue_waiting_time_minutes",
      "observed": 48.5,
      "threshold": 30.0,
      "message": "Emergency Department waiting time exceeds critical threshold of 30 mins.",
      "suggested_action": "Deploy backup triage physician immediately.",
      "created_at": "2026-10-23 14:30:00"
    }
  ]
}
```

---

### 4. Security & Error Codes

| HTTP Status | Error Code | Description |
|---|---|---|
| `400 Bad Request` | `VALIDATION_FAILED` | Missing or invalid input parameters. |
| `401 Unauthorized`| `AUTH_REQUIRED` | No active session detected; redirect to `login.php`. |
| `403 Forbidden` | `ACCESS_DENIED` | Insufficient role capabilities or CSRF token mismatch. |
| `409 Conflict` | `DOUBLE_BOOKING` | Bed or appointment slot is already allocated. |
| `422 Unprocessable`| `STOCK_INSUFFICIENT` | Requested dispensing exceeds physical stock. |
| `500 Server Error` | `TRANSACTION_FAILED`| PDO rolled back; database state untouched. |
