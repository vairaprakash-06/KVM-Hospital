<?php
/**
 * KVM Hospital Management System — NexusCare
 * Core Module: Reports & Operations Analytics
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

require_auth();

$db = get_db();

// 1. Revenue metrics
$rev = $db->query("
    SELECT 
        SUM(total_amount) AS billed_total,
        SUM(paid_amount) AS collected_total,
        SUM(balance_amount) AS outstanding_total
    FROM `invoices`
")->fetch();

// 2. Appointment status distribution
$aptStats = $db->query("
    SELECT status, COUNT(*) AS cnt 
    FROM `appointments` 
    GROUP BY status
")->fetchAll();

// 3. Department workload (appointments + queue tokens)
$deptWorkload = $db->query("
    SELECT 
        d.name,
        COUNT(DISTINCT a.id) AS total_appts,
        COUNT(DISTINCT qe.id) AS total_queue
    FROM `departments` d
    LEFT JOIN `appointments` a ON d.id = a.department_id
    LEFT JOIN `queue_entries` qe ON d.id = qe.department_id
    GROUP BY d.id, d.name
    HAVING total_appts > 0 OR total_queue > 0
    ORDER BY total_appts DESC
")->fetchAll();

// 4. Bed Occupancy breakdown
$wardBeds = $db->query("
    SELECT 
        w.name,
        COUNT(b.id) AS total_beds,
        SUM(CASE WHEN b.status = 'occupied' THEN 1 ELSE 0 END) AS occupied,
        SUM(CASE WHEN b.status = 'available' THEN 1 ELSE 0 END) AS available
    FROM `wards` w
    JOIN `beds` b ON w.id = b.ward_id
    GROUP BY w.id, w.name
")->fetchAll();

// 5. Patient registrations by month
$monthlyReg = $db->query("
    SELECT 
        DATE_FORMAT(created_at, '%b %Y') AS reg_month,
        COUNT(*) AS count
    FROM `patient_profiles`
    GROUP BY reg_month
    ORDER BY MIN(created_at) ASC
")->fetchAll();

$pageTitle = 'Hospital Reports & Executive Analytics';
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<!-- Title and Action Bar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Hospital Reports & Executive Analytics</h4>
        <p class="text-secondary small mb-0">Cross-department relational reporting, revenue reconciliation, and clinical utilization</p>
    </div>
    
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print Report Summary
        </button>
    </div>
</div>

<!-- Revenue & Operational KPI Summary -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-4">
        <div class="stat-widget">
            <div class="stat-icon emerald">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div>
                <div class="stat-value text-success"><?= format_currency($rev['collected_total'] ?? 0) ?></div>
                <div class="stat-label">Total Realized Revenue</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="stat-widget">
            <div class="stat-icon danger">
                <i class="bi bi-clock-history"></i>
            </div>
            <div>
                <div class="stat-value text-danger"><?= format_currency($rev['outstanding_total'] ?? 0) ?></div>
                <div class="stat-label">Accounts Receivable Balance</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="stat-widget">
            <div class="stat-icon blue">
                <i class="bi bi-receipt"></i>
            </div>
            <div>
                <div class="stat-value text-primary"><?= format_currency($rev['billed_total'] ?? 0) ?></div>
                <div class="stat-label">Gross Billed Value</div>
            </div>
        </div>
    </div>
</div>

<!-- Analytics Charts -->
<div class="row g-4 mb-4">
    <!-- Chart 1: Department Workload -->
    <div class="col-lg-6">
        <div class="nexus-card h-100">
            <div class="nexus-card-header">
                <h6 class="fw-bold mb-0">Department Workload Distribution</h6>
                <span class="badge bg-light text-secondary border">Clinical Utilization</span>
            </div>
            <div class="nexus-card-body">
                <canvas id="deptWorkloadChart" style="max-height: 280px;"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart 2: Appointment Status Distribution -->
    <div class="col-lg-6">
        <div class="nexus-card h-100">
            <div class="nexus-card-header">
                <h6 class="fw-bold mb-0">Appointment Fulfillment Ratio</h6>
                <span class="badge bg-light text-secondary border">Appointment Matrix</span>
            </div>
            <div class="nexus-card-body">
                <canvas id="aptStatusChart" style="max-height: 280px;"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Data Tables -->
<div class="row g-4">
    <!-- Ward Occupancy Table -->
    <div class="col-lg-6">
        <div class="nexus-card h-100">
            <div class="nexus-card-header">
                <h6 class="fw-bold mb-0">Ward Capacity & Utilization Breakdown</h6>
            </div>
            <div class="table-responsive">
                <table class="table-nexus">
                    <thead>
                        <tr>
                            <th>Ward Name</th>
                            <th>Total Beds</th>
                            <th>Occupied</th>
                            <th>Available</th>
                            <th>Utilization Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($wardBeds as $wb): ?>
                            <?php $rate = round(((int)$wb['occupied'] / (int)$wb['total_beds']) * 100, 1); ?>
                            <tr>
                                <td><strong><?= e($wb['name']) ?></strong></td>
                                <td><?= (int)$wb['total_beds'] ?></td>
                                <td><span class="text-danger fw-bold"><?= (int)$wb['occupied'] ?></span></td>
                                <td><span class="text-success fw-bold"><?= (int)$wb['available'] ?></span></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-fill" style="height: 6px;">
                                            <div class="progress-bar <?= $rate >= 80 ? 'bg-danger' : 'bg-primary' ?>" style="width: <?= $rate ?>%;"></div>
                                        </div>
                                        <small class="fw-bold"><?= $rate ?>%</small>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Monthly Registrations Table -->
    <div class="col-lg-6">
        <div class="nexus-card h-100">
            <div class="nexus-card-header">
                <h6 class="fw-bold mb-0">Patient Intake Registrations</h6>
            </div>
            <div class="table-responsive">
                <table class="table-nexus">
                    <thead>
                        <tr>
                            <th>Enrollment Period</th>
                            <th>Registered Patients</th>
                            <th>Growth Indicator</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthlyReg as $mr): ?>
                            <tr>
                                <td><strong><?= e($mr['reg_month']) ?></strong></td>
                                <td><span class="fs-6 fw-bold text-primary"><?= (int)$mr['count'] ?> Patients</span></td>
                                <td><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Active Intake</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Department Workload Chart
        const deptCtx = document.getElementById('deptWorkloadChart');
        if (deptCtx) {
            const workloadData = <?= json_encode($deptWorkload) ?>;
            const labels = workloadData.map(d => d.name);
            const appts = workloadData.map(d => parseInt(d.total_appts));
            const queue = workloadData.map(d => parseInt(d.total_queue));

            new Chart(deptCtx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'Appointments', data: appts, backgroundColor: '#0284c7', borderRadius: 4 },
                        { label: 'Queue Entries', data: queue, backgroundColor: '#0d9488', borderRadius: 4 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        // Appointment Status Doughnut
        const aptCtx = document.getElementById('aptStatusChart');
        if (aptCtx) {
            const aptData = <?= json_encode($aptStats) ?>;
            const labels = aptData.map(a => a.status.toUpperCase());
            const counts = aptData.map(a => parseInt(a.cnt));

            new Chart(aptCtx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: counts,
                        backgroundColor: ['#0284c7', '#10b981', '#f59e0b', '#6366f1', '#ef4444', '#94a3b8']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }
    });
</script>

<?php require_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>
