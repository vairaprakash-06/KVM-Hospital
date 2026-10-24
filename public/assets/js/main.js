/**
 * KVM Hospital Management System — NexusCare
 * Core Client-side Scripts & Real-time Polling
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Mobile Sidebar Toggle
    const sidebarToggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    if (sidebarToggleBtn && sidebar) {
        sidebarToggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('show');
        });
    }

    // 2. Auto-dismiss alerts after 5 seconds
    const flashAlerts = document.querySelectorAll('.alert-dismissible');
    flashAlerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 5000);
    });

    // 3. Client-side Table Filter / Search
    const searchInputs = document.querySelectorAll('[data-table-search]');
    searchInputs.forEach(input => {
        const targetTableId = input.getAttribute('data-table-search');
        const targetTable = document.getElementById(targetTableId);
        if (!targetTable) return;

        input.addEventListener('keyup', () => {
            const filter = input.value.toLowerCase();
            const rows = targetTable.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    });

    // 4. QueueFlow Auto-Polling if on Queue monitor
    const queueMonitorContainer = document.getElementById('queueFlowMonitor');
    if (queueMonitorContainer) {
        const doctorId = queueMonitorContainer.getAttribute('data-doctor-id') || '';
        const deptId = queueMonitorContainer.getAttribute('data-dept-id') || '';

        const pollQueue = async () => {
            try {
                const res = await fetch(`../../api/queue.php?doctor_id=${doctorId}&department_id=${deptId}`);
                if (!res.ok) return;
                const data = await res.json();
                if (data.success && data.html) {
                    queueMonitorContainer.innerHTML = data.html;
                }
            } catch (err) {
                console.warn('QueueFlow live poll notice:', err);
            }
        };

        // Poll every 12 seconds
        setInterval(pollQueue, 12000);
    }

    // 5. FlowLens Auto-refresh indicator
    const flowLensLivePill = document.getElementById('flowlensLiveBadge');
    if (flowLensLivePill) {
        setInterval(() => {
            flowLensLivePill.classList.toggle('opacity-50');
        }, 1500);
    }
});

/**
 * Toast Helper
 */
function showNexusToast(message, type = 'info') {
    const toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) return;

    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type} border-0 show mb-2`;
    toastEl.setAttribute('role', 'alert');
    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    toastContainer.appendChild(toastEl);
    setTimeout(() => toastEl.remove(), 4000);
}
