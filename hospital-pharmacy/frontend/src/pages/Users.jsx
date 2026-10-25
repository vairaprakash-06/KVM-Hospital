import React, { useState, useEffect } from 'react';
import { Users, UserPlus, Shield, CheckCircle, XCircle, Key, Lock } from 'lucide-react';
import { api } from '../services/api';
import { useAuth } from '../hooks/useAuth';

export default function UsersPage() {
  const { user: currentUser, loginDemo } = useAuth();
  const [users, setUsers] = useState([]);
  const [showAddModal, setShowAddModal] = useState(false);
  const [formData, setFormData] = useState({
    username: '',
    email: '',
    full_name: '',
    role: 'Pharmacist',
    password: ''
  });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadUsers();
  }, []);

  async function loadUsers() {
    try {
      setLoading(true);
      const data = await api.getUsers();
      setUsers(data);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  }

  async function handleCreateUser(e) {
    e.preventDefault();
    try {
      await api.createUser(formData);
      setShowAddModal(false);
      setFormData({ username: '', email: '', full_name: '', role: 'Pharmacist', password: '' });
      loadUsers();
    } catch (err) {
      alert(err.message || 'Failed to create user');
    }
  }

  async function handleToggleUser(id) {
    try {
      await api.toggleUserActive(id);
      loadUsers();
    } catch (err) {
      alert(err.message || 'Action failed');
    }
  }

  return (
    <div className="page-content">
      {/* Demo Credentials Quick-Switch Card */}
      <div className="content-card" style={{ marginBottom: 20 }}>
        <div className="card-header">
          <div className="card-title">
            <h3>⚡ VIVA & Evaluation Demo Accounts</h3>
            <p>One-click authentication switcher for clinical role-based access testing</p>
          </div>
        </div>
        <div style={{ padding: 20, display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))', gap: 14 }}>
          {/* Admin card */}
          <div style={{ background: '#f8fafc', border: '1px solid #cbd5e1', borderRadius: 10, padding: 16 }}>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 8 }}>
              <span style={{ fontWeight: 800, color: '#0f172a' }}>Hospital Administrator</span>
              <span style={{ fontSize: '0.68rem', fontWeight: 700, background: '#0ea5e9', color: '#fff', padding: '2px 6px', borderRadius: 4 }}>
                SUPER ACCESS
              </span>
            </div>
            <div style={{ fontSize: '0.8rem', color: '#475569', marginBottom: 12 }}>
              <div><strong>User:</strong> admin</div>
              <div><strong>Pass:</strong> password123</div>
              <div style={{ fontSize: '0.74rem', color: '#64748b', marginTop: 4 }}>Full permissions: formulary additions, user admin, audit logs, dispensing.</div>
            </div>
            <button
              className="btn btn-teal btn-sm"
              style={{ width: '100%', background: '#0284c7' }}
              onClick={() => loginDemo('admin')}
            >
              Sign In as Administrator
            </button>
          </div>

          {/* Pharmacist card */}
          <div style={{ background: '#f0fdfa', border: '1px solid #99f6e4', borderRadius: 10, padding: 16 }}>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 8 }}>
              <span style={{ fontWeight: 800, color: '#0f766e' }}>Clinical Pharmacist</span>
              <span style={{ fontSize: '0.68rem', fontWeight: 700, background: '#0d9488', color: '#fff', padding: '2px 6px', borderRadius: 4 }}>
                CLINICAL
              </span>
            </div>
            <div style={{ fontSize: '0.8rem', color: '#475569', marginBottom: 12 }}>
              <div><strong>User:</strong> pharmacist</div>
              <div><strong>Pass:</strong> password123</div>
              <div style={{ fontSize: '0.74rem', color: '#64748b', marginTop: 4 }}>Prescription verification, FEFO dispensing, stock alerts, thermal receipts.</div>
            </div>
            <button
              className="btn btn-teal btn-sm"
              style={{ width: '100%' }}
              onClick={() => loginDemo('pharmacist')}
            >
              Sign In as Pharmacist
            </button>
          </div>

          {/* Inventory staff card */}
          <div style={{ background: '#fffbeb', border: '1px solid #fde68a', borderRadius: 10, padding: 16 }}>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 8 }}>
              <span style={{ fontWeight: 800, color: '#92400e' }}>Inventory Staff</span>
              <span style={{ fontSize: '0.68rem', fontWeight: 700, background: '#d97706', color: '#fff', padding: '2px 6px', borderRadius: 4 }}>
                WAREHOUSE
              </span>
            </div>
            <div style={{ fontSize: '0.8rem', color: '#475569', marginBottom: 12 }}>
              <div><strong>User:</strong> inventory</div>
              <div><strong>Pass:</strong> password123</div>
              <div style={{ fontSize: '0.74rem', color: '#64748b', marginTop: 4 }}>Inward stock receipt, batch placement, count reconciliation adjustments.</div>
            </div>
            <button
              className="btn btn-teal btn-sm"
              style={{ width: '100%', background: '#d97706' }}
              onClick={() => loginDemo('inventory')}
            >
              Sign In as Store Staff
            </button>
          </div>
        </div>
      </div>

      {/* Staff Directory Table */}
      <div className="content-card">
        <div className="card-header">
          <div className="card-title">
            <h3>Staff Directory & RBAC Matrix</h3>
            <p>Active pharmacy staff credentials, roles, and status</p>
          </div>
          {currentUser?.role === 'Hospital Administrator' && (
            <button className="btn btn-teal" onClick={() => setShowAddModal(true)}>
              <UserPlus size={16} />
              <span>+ Add Staff Member</span>
            </button>
          )}
        </div>

        <div className="table-responsive">
          <table className="data-table">
            <thead>
              <tr>
                <th>Username</th>
                <th>Full Name</th>
                <th>Email Address</th>
                <th>Assigned Role</th>
                <th>Status</th>
                <th>Registered Date</th>
                {currentUser?.role === 'Hospital Administrator' && <th style={{ textAlign: 'right' }}>Action</th>}
              </tr>
            </thead>
            <tbody>
              {users.map((u) => (
                <tr key={u.id}>
                  <td><span className="code-pill">{u.username}</span></td>
                  <td style={{ fontWeight: 700 }}>{u.full_name}</td>
                  <td>{u.email}</td>
                  <td>
                    <span
                      style={{
                        fontSize: '0.76rem',
                        fontWeight: 700,
                        padding: '3px 8px',
                        borderRadius: 4,
                        background: u.role === 'Hospital Administrator' ? '#0ea5e9' : u.role === 'Pharmacist' ? '#0d9488' : '#d97706',
                        color: '#fff'
                      }}
                    >
                      {u.role}
                    </span>
                  </td>
                  <td>
                    <span className={`status-badge ${u.is_active ? 'healthy' : 'out-of-stock'}`}>
                      {u.is_active ? 'Active' : 'Deactivated'}
                    </span>
                  </td>
                  <td style={{ fontSize: '0.8rem', color: '#64748b' }}>
                    {new Date(u.created_at).toLocaleDateString('en-GB')}
                  </td>
                  {currentUser?.role === 'Hospital Administrator' && (
                    <td style={{ textAlign: 'right' }}>
                      {u.id !== currentUser.id && (
                        <button
                          className="btn btn-secondary btn-sm"
                          onClick={() => handleToggleUser(u.id)}
                          style={{ color: u.is_active ? '#dc2626' : '#16a34a' }}
                        >
                          {u.is_active ? 'Deactivate' : 'Activate'}
                        </button>
                      )}
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Add User Modal */}
      {showAddModal && (
        <div className="modal-backdrop">
          <div className="modal-dialog" style={{ maxWidth: 500 }}>
            <div className="modal-header">
              <h3>+ Add Hospital Staff Account</h3>
            </div>
            <form onSubmit={handleCreateUser}>
              <div className="modal-body">
                <div className="form-group">
                  <label className="form-label">Full Name *</label>
                  <input
                    type="text"
                    className="form-input"
                    value={formData.full_name}
                    onChange={(e) => setFormData({ ...formData, full_name: e.target.value })}
                    required
                  />
                </div>
                <div className="form-row-2">
                  <div className="form-group">
                    <label className="form-label">Username *</label>
                    <input
                      type="text"
                      className="form-input"
                      value={formData.username}
                      onChange={(e) => setFormData({ ...formData, username: e.target.value })}
                      required
                    />
                  </div>
                  <div className="form-group">
                    <label className="form-label">Role *</label>
                    <select
                      className="form-select"
                      value={formData.role}
                      onChange={(e) => setFormData({ ...formData, role: e.target.value })}
                    >
                      <option value="Pharmacist">Pharmacist</option>
                      <option value="Inventory Staff">Inventory Staff</option>
                      <option value="Hospital Administrator">Hospital Administrator</option>
                    </select>
                  </div>
                </div>
                <div className="form-group">
                  <label className="form-label">Email Address *</label>
                  <input
                    type="email"
                    className="form-input"
                    value={formData.email}
                    onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                    required
                  />
                </div>
                <div className="form-group">
                  <label className="form-label">Password *</label>
                  <input
                    type="password"
                    className="form-input"
                    value={formData.password}
                    onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                    required
                  />
                </div>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-secondary" onClick={() => setShowAddModal(false)}>
                  Cancel
                </button>
                <button type="submit" className="btn btn-teal">
                  Create Staff Account
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
