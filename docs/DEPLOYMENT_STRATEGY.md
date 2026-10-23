# KVM Hospital Management System — NexusCare
## Production & Hybrid Vercel Deployment Architecture

This guide provides instructions for deploying **NexusCare** locally on XAMPP and in production using a decoupled Vercel + PHP Backend + Managed MySQL architecture.

---

### 1. Local Development (XAMPP / Apache / MySQL)

#### Prerequisites
- XAMPP with PHP 8.x and MySQL / MariaDB 10.4+
- Web browser (Chrome, Edge, Firefox)

#### Installation Steps
1. **Clone or Copy Codebase:**
   Place the project folder into your XAMPP web root:
   ```bash
   C:\xampp\htdocs\kvm-hospital
   ```
2. **Start Services:**
   Open the XAMPP Control Panel and start **Apache** and **MySQL**.
3. **Import Database:**
   - Open phpMyAdmin at `http://localhost/phpmyadmin`
   - Create a database named `kvm_hospital` with collation `utf8mb4_unicode_ci`
   - Import `database/schema.sql`
   - Import `database/seed.sql`
   *(Alternatively, run from terminal:*
   ```bash
   mysql -u root kvm_hospital < database/schema.sql
   mysql -u root kvm_hospital < database/seed.sql
   ```
4. **Configure Environment:**
   Copy `.env.example` to `.env` and verify database credentials:
   ```ini
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=kvm_hospital
   DB_USERNAME=root
   DB_PASSWORD=
   ```
5. **Launch Application:**
   Navigate to:
   ```
   http://localhost/kvm-hospital/public/
   ```
   *(Or run the PHP built-in server:*
   ```bash
   php -S 127.0.0.1:8000 -t public/
   ```
   *and visit `http://127.0.0.1:8000/`)*

---

### 2. Vercel + PHP Backend + Managed MySQL Architecture

#### The Vercel Reality
Vercel is fundamentally a serverless edge platform designed for Node.js, Next.js, and static assets. Standard PHP applications requiring persistent sessions, server-side PDO connections, and background Apache/Nginx webservers cannot run natively on standard Vercel without community serverless shims.

#### Recommended Hybrid Architecture
```
┌─────────────────────────────────┐
│          VERCEL EDGE            │
│  - Static Assets (CSS, JS, SVG) │
│  - Public HTML landing pages    │
│  - Reverse Proxy (vercel.json)  │
└────────────────┬────────────────┘
                 │ Authenticated HTTPS / CORS
                 ▼
┌─────────────────────────────────┐
│     COMPATIBLE PHP 8 HOST       │
│  - Render / Railway / Apache    │
│  - PHP 8.x PDO + Sessions       │
│  - Business Logic & RBAC        │
│  - QueueFlow / BedMatrix Engine │
└────────────────┬────────────────┘
                 │ Encrypted TLS (Port 3306)
                 ▼
┌─────────────────────────────────┐
│      MANAGED MYSQL 8.0          │
│  - PlanetScale / Aiven / RDS    │
│  - 47 Tables, InnoDB Engine     │
│  - Foreign Keys & Constraints   │
└─────────────────────────────────┘
```

#### Step 1: Deploy MySQL Database
1. Provision a MySQL 8.x instance on a managed cloud provider (e.g. Aiven, AWS RDS, DigitalOcean).
2. Connect using MySQL Workbench or CLI and execute `database/schema.sql` followed by `database/seed.sql`.
3. Configure IP allowlisting or SSL/TLS certificate requirements.

#### Step 2: Deploy PHP Backend
1. Deploy the PHP application to a platform that supports PHP 8 (e.g. Render, Railway, Heroku, or an Ubuntu VPS with Apache/PHP).
2. Configure environment variables in the host dashboard:
   - `DB_HOST`: Managed database hostname
   - `DB_DATABASE`: `kvm_hospital`
   - `DB_USERNAME`: Managed database user
   - `DB_PASSWORD`: Managed database password
   - `SESSION_SECURE`: `true`
   - `SESSION_SAMESITE`: `None` (for cross-domain cookie sharing)
   - `FRONTEND_URL`: `https://your-app.vercel.app`

#### Step 3: Configure Vercel Frontend
1. Connect your Git repository to Vercel.
2. In `vercel.json`, configure rewrites to point `/api/` to your hosted PHP backend:
   ```json
   {
     "version": 2,
     "routes": [
       { "src": "/assets/(.*)", "dest": "/public/assets/$1" },
       { "src": "/api/(.*)", "dest": "https://your-backend.railway.app/api/$1" },
       { "src": "/(.*)", "dest": "/public/$1" }
     ]
   }
   ```
3. Deploy to Vercel and verify that CORS headers and cookies persist across requests.

---

### 3. Post-Deployment Verification Checklist

- [ ] Database connectivity verified via PDO singleton.
- [ ] Landing page loads with real statistics queried from the database.
- [ ] Login functions with demo credentials (`admin` / `Password123!`).
- [ ] Session cookie is created with `HttpOnly` and `SameSite` flags.
- [ ] BedMatrix ward grid displays live bed occupancy.
- [ ] QueueFlow updates without JavaScript console errors.
- [ ] Role-Based Access Control restricts unauthorized URL traversal.
- [ ] TrustLedger logs administrative actions to `audit_logs`.
