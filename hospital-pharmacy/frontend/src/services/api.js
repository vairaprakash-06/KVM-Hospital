const API_BASE = '/api';

function getHeaders() {
  const token = localStorage.getItem('nexuscare_token');
  const headers = {
    'Content-Type': 'application/json',
  };
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }
  return headers;
}

async function request(endpoint, options = {}) {
  const config = {
    ...options,
    headers: {
      ...getHeaders(),
      ...(options.headers || {})
    }
  };

  const res = await fetch(`${API_BASE}${endpoint}`, config);
  if (!res.ok) {
    const errorData = await res.json().catch(() => ({ detail: res.statusText }));
    throw new Error(errorData.detail || 'Request failed');
  }
  return res.json();
}

export const api = {
  // Auth & Users
  login: (username, password) =>
    request('/users/login', {
      method: 'POST',
      body: JSON.stringify({ username, password })
    }),
  getMe: () => request('/users/me'),
  getUsers: () => request('/users/'),
  createUser: (data) => request('/users/', { method: 'POST', body: JSON.stringify(data) }),
  toggleUserActive: (id) => request(`/users/${id}/toggle-active`, { method: 'POST' }),

  // Dashboard & Analytics
  getDashboardSummary: () => request('/reports/dashboard-summary'),
  getCategoryValuation: () => request('/reports/category-valuation'),
  getMovementHistory: (params = {}) => {
    const q = new URLSearchParams(params).toString();
    return request(`/reports/movement-history?${q}`);
  },
  getDispensingTrends: () => request('/reports/dispensing-trends'),

  // Medicines
  getMedicines: (params = {}) => {
    const q = new URLSearchParams(params).toString();
    return request(`/medicines/?${q}`);
  },
  getCategories: () => request('/medicines/categories/all'),
  getManufacturers: () => request('/medicines/manufacturers/all'),
  createMedicine: (data) => request('/medicines/', { method: 'POST', body: JSON.stringify(data) }),
  updateMedicine: (id, data) => request(`/medicines/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
  toggleMedicineActive: (id) => request(`/medicines/${id}/toggle-active`, { method: 'POST' }),

  // Batches
  getBatches: (params = {}) => {
    const q = new URLSearchParams(params).toString();
    return request(`/batches/?${q}`);
  },
  stockInBatch: (data) => request('/batches/', { method: 'POST', body: JSON.stringify(data) }),
  adjustBatch: (id, data) => request(`/batches/${id}/adjust`, { method: 'POST', body: JSON.stringify(data) }),
  toggleQuarantine: (id) => request(`/batches/${id}/toggle-quarantine`, { method: 'POST' }),

  // Dispensing & Prescriptions
  getPrescriptions: (params = {}) => {
    const q = new URLSearchParams(params).toString();
    return request(`/dispensing/prescriptions?${q}`);
  },
  getPrescription: (rxNumber) => request(`/dispensing/prescriptions/${rxNumber}`),
  dispense: (data) => request('/dispensing/dispense', { method: 'POST', body: JSON.stringify(data) }),
  getDispensingRecords: (params = {}) => {
    const q = new URLSearchParams(params).toString();
    return request(`/dispensing/records?${q}`);
  },
  getReceipt: (receiptNumber) => request(`/dispensing/receipt/${receiptNumber}`),

  // Suppliers
  getSuppliers: () => request('/suppliers/'),
  createSupplier: (data) => request('/suppliers/', { method: 'POST', body: JSON.stringify(data) }),

  // Alerts
  getAlerts: (params = {}) => {
    const q = new URLSearchParams(params).toString();
    return request(`/alerts/?${q}`);
  },
  resolveAlert: (id) => request(`/alerts/${id}/resolve`, { method: 'POST' }),
  syncAlerts: () => request('/alerts/sync', { method: 'POST' })
};
