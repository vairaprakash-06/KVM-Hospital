import React, { useState, useEffect } from 'react';
import { Truck, Plus, Phone, Mail, MapPin, Clock, X, Check } from 'lucide-react';
import { api } from '../services/api';

export default function Suppliers() {
  const [suppliers, setSuppliers] = useState([]);
  const [showAddModal, setShowAddModal] = useState(false);
  const [formData, setFormData] = useState({
    name: '',
    contact_person: '',
    phone: '',
    email: '',
    address: '',
    lead_time_days: 3
  });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadSuppliers();
  }, []);

  async function loadSuppliers() {
    try {
      setLoading(true);
      const data = await api.getSuppliers();
      setSuppliers(data);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  }

  async function handleAddSupplier(e) {
    e.preventDefault();
    try {
      await api.createSupplier(formData);
      setShowAddModal(false);
      setFormData({ name: '', contact_person: '', phone: '', email: '', address: '', lead_time_days: 3 });
      loadSuppliers();
    } catch (err) {
      alert(err.message || 'Failed to create supplier');
    }
  }

  return (
    <div className="page-content">
      <div className="content-card">
        <div className="card-header">
          <div className="card-title">
            <h3>Registered Pharmaceutical Distributors & Sourcing Vendors</h3>
            <p>Approved pharmaceutical supply chain partners, emergency lead times, and procurement contact info</p>
          </div>
          <button className="btn btn-teal" onClick={() => setShowAddModal(true)}>
            <Plus size={16} />
            <span>+ Add Supplier</span>
          </button>
        </div>

        <div className="table-responsive">
          <table className="data-table">
            <thead>
              <tr>
                <th>Distributor Name</th>
                <th>Contact Representative</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Depot Address</th>
                <th>Standard Lead Time</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {suppliers.map((s) => (
                <tr key={s.id}>
                  <td>
                    <div style={{ fontWeight: 700, color: '#0f172a' }}>{s.name}</div>
                  </td>
                  <td style={{ fontWeight: 600 }}>{s.contact_person || 'Logistics Desk'}</td>
                  <td>
                    <span style={{ fontSize: '0.82rem', color: '#0284c7', display: 'flex', alignItems: 'center', gap: 4 }}>
                      <Phone size={13} /> {s.phone || 'N/A'}
                    </span>
                  </td>
                  <td>
                    <span style={{ fontSize: '0.82rem', color: '#475569', display: 'flex', alignItems: 'center', gap: 4 }}>
                      <Mail size={13} /> {s.email || 'N/A'}
                    </span>
                  </td>
                  <td style={{ fontSize: '0.8rem', color: '#64748b' }}>
                    <span style={{ display: 'flex', alignItems: 'center', gap: 4 }}>
                      <MapPin size={13} /> {s.address || 'Tamil Nadu'}
                    </span>
                  </td>
                  <td>
                    <span style={{ fontWeight: 700, color: '#0f172a' }}>
                      {s.lead_time_days} days
                    </span>
                  </td>
                  <td>
                    <span className={`status-badge ${s.status === 'Active' ? 'healthy' : 'out-of-stock'}`}>
                      {s.status}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Add Supplier Modal */}
      {showAddModal && (
        <div className="modal-backdrop">
          <div className="modal-dialog" style={{ maxWidth: 500 }}>
            <div className="modal-header">
              <h3>+ Register New Pharmaceutical Supplier</h3>
              <button onClick={() => setShowAddModal(false)} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#64748b' }}>
                <X size={20} />
              </button>
            </div>
            <form onSubmit={handleAddSupplier}>
              <div className="modal-body">
                <div className="form-group">
                  <label className="form-label">Company / Distributor Name *</label>
                  <input
                    type="text"
                    className="form-input"
                    value={formData.name}
                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                    required
                  />
                </div>
                <div className="form-row-2">
                  <div className="form-group">
                    <label className="form-label">Contact Person</label>
                    <input
                      type="text"
                      className="form-input"
                      value={formData.contact_person}
                      onChange={(e) => setFormData({ ...formData, contact_person: e.target.value })}
                    />
                  </div>
                  <div className="form-group">
                    <label className="form-label">Phone *</label>
                    <input
                      type="text"
                      className="form-input"
                      value={formData.phone}
                      onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                      required
                    />
                  </div>
                </div>
                <div className="form-row-2">
                  <div className="form-group">
                    <label className="form-label">Email</label>
                    <input
                      type="email"
                      className="form-input"
                      value={formData.email}
                      onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                    />
                  </div>
                  <div className="form-group">
                    <label className="form-label">Lead Time (Days)</label>
                    <input
                      type="number"
                      className="form-input"
                      value={formData.lead_time_days}
                      onChange={(e) => setFormData({ ...formData, lead_time_days: parseInt(e.target.value) || 1 })}
                      min="1"
                    />
                  </div>
                </div>
                <div className="form-group">
                  <label className="form-label">Depot Address</label>
                  <input
                    type="text"
                    className="form-input"
                    value={formData.address}
                    onChange={(e) => setFormData({ ...formData, address: e.target.value })}
                  />
                </div>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-secondary" onClick={() => setShowAddModal(false)}>
                  Cancel
                </button>
                <button type="submit" className="btn btn-teal">
                  <Check size={16} />
                  <span>Save Supplier</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
