# NexusCare – Hospital Pharmacy Inventory & Dispensing Management System

> **Hospital Pharmacy Intelligence Platform**  
> *Stock batch tracking, First-Expiry-First-Out (FEFO) dispensing, expiry monitoring, cold-chain temperature telemetry, and inventory reporting.*

---

## 1. System Overview

**NexusCare – Hospital Pharmacy Management System** is an enterprise-grade full-stack hospital pharmacy operations system engineered for hospital pharmacists, store managers, and clinical administrators.

### Core Architecture & Technologies
- **Frontend**: React 19 + Vite, Lucide React icons, Recharts interactive data visualization, Vanilla CSS design system.
- **Backend**: Python 3.14 + FastAPI REST API, SQLAlchemy 2.0 ORM, Pydantic v2 validation, OAuth2 JWT bearer authentication, direct BCrypt password hashing.
- **Database**: SQLite (`pharmacy.db`) with Foreign Key constraints enabled (`PRAGMA foreign_keys=ON`), atomic transactional consistency, and audit movement logs.

---

## 2. Key Features

1. **Clean Hospital Dashboard**:
   - 4 Live KPI Cards:
     1. Total Medicine Catalog Items
     2. Total Inventory Valuation in Indian Rupees (₹)
     3. Low Stock & Out-of-Stock Warnings
     4. Batches Near Expiry (within 90 days)
   - 7-Day Dispensing Volume & Revenue Trend chart (Recharts Area).
   - Inventory Valuation by Category chart (Recharts Horizontal Bar).
   - Operational Quick Hub for routine actions.
   - Real-time dispensing activity stream.

2. **Responsive Pharmacy Inventory Ledger**:
   - Real-time search across brand name, generic/chemical name, medicine code, and manufacturer.
   - Multi-factor filters by Category and Stock Health status:
     - 🟢 **Healthy Stock**
     - 🟡 **Low Stock Warning** (≤ reorder threshold)
     - 🔴 **Out of Stock** (0 units available)
     - 🟠 **Expiring Soon** (< 90 days)
     - ⛔ **Expired**
   - Collapsible sub-ledger for itemized batch inspection.
   - Quick action triggers: *Dispense*, *Stock In*, *Edit*, *Deactivate/Activate*.

3. **Batch Management & FEFO Dispensing**:
   - **FEFO (First-Expiry-First-Out)** algorithm auto-prioritizes the earliest active expiring batch to minimize hospital wastage.
   - **Safety Enforcement**:
     - Hard block preventing dispensing from **Expired** batches.
     - Hard block preventing dispensing from **Quarantined** batches.
     - Hard block preventing dispensing quantities greater than available batch stock (negative stock mathematically impossible).
     - Schedule H / Prescription-Required validation requiring doctor prescription or clinical pharmacist override.
   - **Physical Count Reconciliation**: Stock adjustment modal with mandatory compliance audit reasons.

4. **Safety & Alerts Engine**:
   - Real-time recalculation directly from live database records.
   - Monitors low stock, out of stock, near expiry (< 90 days), expired batches, and cold-chain temperature deviations (< 2°C or > 8°C for insulin).
   - 1-click pharmacist acknowledgement and resolution.

5. **Thermal Receipt Printing & Dispensing Records**:
   - Printable thermal clinical receipt with hospital header, license numbers, GST (5%), batch codes, and pharmacist verification.
   - Full historical audit trail with CSV export capability.

6. **Role-Based Access Control (RBAC)**:
   - **Hospital Administrator**: Full access to all formulary items, user administration, and reports.
   - **Pharmacist**: Prescription verification, FEFO dispensing, thermal receipts, and safety alerts.
   - **Inventory Staff**: Inward batch stock-in, storage bay placement, and count adjustments.

---

## 3. Demonstration Credentials

Pre-loaded accounts available via fast-switcher:

| Role | Username | Password | Access Scope |
|---|---|---|---|
| **Hospital Administrator** | `admin` | `password123` | Complete Super Admin & Clinical Access |
| **Pharmacist** | `pharmacist` | `password123` | Prescription Dispensing & Safety Alerts |
| **Inventory Staff** | `inventory` | `password123` | Inward Stock Receipt & Physical Count Reconciliation |

---

## 4. Quick Start: Windows PowerShell Commands

### Step 1: Initialize Database & Demonstration Dataset
Run the automatic seed script to populate over 40 hospital formulary items, suppliers, batches, and demo prescriptions:

```powershell
cd hospital-pharmacy\backend
python -m app.seed
```

### Step 2: Run Backend Server (FastAPI)
Launch the backend REST API on port `8000`:

```powershell
cd hospital-pharmacy\backend
python -m uvicorn app.main:app --host 0.0.0.0 --port 8000 --reload
```
- API Base URL: `http://localhost:8000`
- Interactive Swagger Documentation: `http://localhost:8000/docs`
- Healthcheck Endpoint: `http://localhost:8000/api/health`

### Step 3: Run Frontend Server (React + Vite)
In a second terminal window, launch the Vite dev server:

```powershell
cd hospital-pharmacy\frontend
npm run dev
```
- Frontend UI: `http://localhost:5173`

---

## 5. Running the Backend Test Suite

To verify all business rules, FEFO sorting, safety blocks against expired batches, and negative stock prevention:

```powershell
cd hospital-pharmacy\backend
python tests_backend.py
```

Expected output:
```
........
----------------------------------------------------------------------
Ran 8 tests in 0.498s

OK
```

---

## 6. Project File Directory

```
hospital-pharmacy/
├── .env.example
├── README.md
├── backend/
│   ├── pharmacy.db                     # SQLite Database
│   ├── requirements.txt                # Python backend dependencies
│   ├── tests_backend.py                # Unit test suite
│   └── app/
│       ├── __init__.py
│       ├── main.py                     # FastAPI application entrypoint
│       ├── database.py                 # SQLite engine & session management
│       ├── models.py                   # SQLAlchemy ORM models
│       ├── schemas.py                  # Pydantic v2 schemas
│       ├── auth.py                     # JWT token & BCrypt RBAC security
│       ├── seed.py                     # Formulary dataset seed script
│       ├── routers/
│       │   ├── __init__.py
│       │   ├── users.py                # Authentication & user administration
│       │   ├── medicines.py            # Medicine catalog & inventory ledger
│       │   ├── batches.py              # Stock batches & adjustment
│       │   ├── dispensing.py           # Prescription dispensing & receipts
│       │   ├── suppliers.py            # Supplier registry
│       │   ├── alerts.py               # Real-time safety alerts
│       │   └── reports.py              # Reports, charts telemetry, and CSV exports
│       └── services/
│           ├── __init__.py
│           └── pharmacy_service.py     # FEFO logic, transactions, and live alerts
└── frontend/
    ├── index.html
    ├── package.json
    ├── vite.config.js
    └── src/
        ├── main.jsx
        ├── App.jsx                     # Top-level application container
        ├── index.css                   # Custom hospital design system
        ├── components/
        │   ├── Navbar.jsx              # Header with action triggers
        │   ├── Sidebar.jsx             # Dark navy hospital sidebar
        │   ├── StatCard.jsx            # KPI summary card
        │   ├── MedicineModal.jsx       # Add/Edit medicine modal
        │   ├── StockInModal.jsx        # Inward batch receipt modal
        │   ├── DispenseModal.jsx       # FEFO prescription dispensing modal
        │   ├── AdjustStockModal.jsx    # Physical count reconciliation modal
        │   └── ReceiptModal.jsx        # Printable thermal pharmacy receipt
        ├── pages/
        │   ├── Dashboard.jsx           # Command center & charts
        │   ├── Catalog.jsx             # Full inventory ledger
        │   ├── Batches.jsx             # Stock batches management
        │   ├── Dispensing.jsx          # Prescription queue & dispensing history
        │   ├── Suppliers.jsx           # Distributor registry
        │   ├── Alerts.jsx              # Safety alerts center
        │   ├── Reports.jsx             # Valuation & CSV exports
        │   └── Users.jsx               # Staff directory & demo switcher
        ├── hooks/
        │   └── useAuth.jsx             # Auth context & demo switcher
        └── services/
            └── api.js                  # Frontend API client
```

---

*Notice: This system is built for clinical operations demonstration and evaluation. Clinical deployment requires authorized institutional protocol verification.*
