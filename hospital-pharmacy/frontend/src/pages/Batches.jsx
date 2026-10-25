import React, { useState, useEffect } from 'react';
import {
  Layers,
  Clock,
  AlertTriangle,
  SlidersHorizontal,
  ShieldBan,
  ShieldCheck,
  PackagePlus,
  Search
} from 'lucide-react';
import { api } from '../services/api';

export default function Batches({ onOpenStockIn, onOpenAdjustModal }) {
  const [batches, setBatches] = useState([]);
  const [filterStatus, setFilterStatus] = useState('All');
  const [nearExpiryOnly, setNearExpiryOnly] = useState(false);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadBatches();
  }, [filterStatus, nearExpiryOnly]);

  async function loadBatches() {
    try {
      setLoading(true);
      const params = {};
      if (filterStatus !== 'All') params.status = filterStatus;
      if (nearExpiryOnly) params.near_expiry_only = true;

      const data = await api.getBatches(params);
      setBatches(data);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  }

  async function handleToggleQuarantine(batchId) {
    try {
      await api.toggleQuarantine(batchId);
      loadBatches();
    } catch (err) {
      alert(err.message || 'Quarantine toggle failed');
    }
  }

  const filtered = batches.filter((b) => {
    if (!search) return true;
    const s = search.toLowerCase();
    return (
      b.batch_number.toLowerCase().includes(s) ||
      (b.medicine_name && b.medicine_name.toLowerCase().includes(s)) ||
      (b.medicine_code && b.medicine_code.toLowerCase().includes(s)) ||
      (b.storage_location && b.storage_location.toLowerCase().includes(s))
    );
  });

  return (
    <div className="page-content">
      {/* Batches Header Card */}
      <div className="content-card">
        <div className="card-header">
          <div className="card-title">
            <h3>Pharmacy Stock Batches & FEFO Ledger</h3>
            <p>Individual batch tracking, storage location bays, and cold-chain temperature telemetry</p>
          </div>
          <button className="btn btn-teal" onClick={onOpenStockIn}>
            <PackagePlus size={16} />
            <span>Stock In New Batch</span>
          </button>
        </div>

        {/* Filter Controls */}
        <div className="filter-bar">
          <div className="search-input-wrap">
            <Search size={16} className="search-icon" />
            <input
              type="text"
              className="search-input"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search by batch number, medicine, bay..."
            />
          </div>

          <div style={{ display: 'flex', gap: 10 }}>
            <select
              className="filter-select"
              value={filterStatus}
              onChange={(e) => {
                setFilterStatus(e.target.value);
                setNearExpiryOnly(false);
              }}
            >
              <option value="All">All Statuses</option>
              <option value="Active">Active In Stock</option>
              <option value="Quarantined">Quarantined / Held</option>
              <option value="Expired">Expired</option>
              <option value="Depleted">Depleted (0 Units)</option>
            </select>

            <button
              className={`btn btn-sm ${nearExpiryOnly ? 'btn-teal' : 'btn-secondary'}`}
              onClick={() => {
                setNearExpiryOnly(!nearExpiryOnly);
                setFilterStatus('All');
              }}
            >
              <Clock size={14} />
              <span>Expiring Within 90 Days</span>
            </button>
          </div>
        </div>

        {/* Batches Table */}
        <div className="table-responsive">
          <table className="data-table">
            <thead>
              <tr>
                <th>Batch Number</th>
                <th>Medicine / Formulary</th>
                <th>Supplier</th>
                <th>Purchase</th>
                <th>Selling MRP</th>
                <th>Current Qty</th>
                <th>Mfg Date</th>
                <th>Expiry Date</th>
                <th>Bay / Location</th>
                <th>Status</th>
                <th style={{ textAlign: 'right' }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan="11" style={{ textAlign: 'center', padding: 32, color: '#94a3b8' }}>
                    Loading stock batch inventory...
                  </td>
                </tr>
              ) : filtered.length === 0 ? (
                <tr>
                  <td colSpan="11" style={{ textAlign: 'center', padding: 32, color: '#94a3b8' }}>
                    No batches found matching filter criteria.
                  </td>
                </tr>
              ) : (
                filtered.map((b) => {
                  const isExpired = new Date(b.expiry_date) < new Date();
                  return (
                    <tr key={b.id} style={{ background: b.status === 'Quarantined' ? '#faf5ff' : isExpired ? '#fdf2f8' : '#ffffff' }}>
                      <td>
                        <span className="code-pill">{b.batch_number}</span>
                      </td>

                      <td>
                        <div className="med-title-col">
                          <span className="med-brand-name">{b.medicine_name}</span>
                          <span className="med-generic-name">{b.medicine_code}</span>
                        </div>
                      </td>

                      <td style={{ fontSize: '0.8rem', color: '#475569' }}>
                        {b.supplier_name || 'Direct Procurement'}
                      </td>

                      <td>₹{b.purchase_price?.toFixed(2)}</td>
                      <td style={{ fontWeight: 700 }}>₹{b.selling_price?.toFixed(2)}</td>

                      <td>
                        <span style={{ fontWeight: 800, fontSize: '0.94rem', color: b.current_quantity === 0 ? '#b91c1c' : '#0f172a' }}>
                          {b.current_quantity} <span style={{ fontSize: '0.74rem', color: '#64748b', fontWeight: 500 }}>/ {b.initial_quantity}</span>
                        </span>
                      </td>

                      <td style={{ fontSize: '0.8rem', color: '#64748b' }}>{b.manufacturing_date}</td>

                      <td>
                        <span style={{ fontSize: '0.82rem', fontWeight: 700, color: isExpired ? '#b91c1c' : '#0f172a' }}>
                          {b.expiry_date}
                        </span>
                      </td>

                      <td>
                        <span style={{ fontSize: '0.78rem', color: '#334155', background: '#f1f5f9', padding: '2px 6px', borderRadius: 4 }}>
                          {b.storage_location}
                        </span>
                        {b.temperature_celsius !== null && (
                          <span style={{ display: 'block', fontSize: '0.72rem', color: (b.temperature_celsius < 2 || b.temperature_celsius > 8) ? '#dc2626' : '#0d9488', fontWeight: 600, marginTop: 2 }}>
                            ❄️ {b.temperature_celsius}°C
                          </span>
                        )}
                      </td>

                      <td>
                        <span className={`status-badge ${b.status === 'Active' ? 'healthy' : b.status === 'Expired' ? 'expired' : b.status === 'Quarantined' ? 'quarantined' : 'low-stock'}`}>
                          {b.status}
                        </span>
                      </td>

                      <td style={{ textAlign: 'right' }}>
                        <div style={{ display: 'flex', gap: 6, justifyContent: 'flex-end' }}>
                          <button
                            className="btn btn-secondary btn-sm"
                            onClick={() => onOpenAdjustModal(b)}
                            title="Reconcile physical count"
                          >
                            <SlidersHorizontal size={13} />
                            <span>Adjust</span>
                          </button>

                          <button
                            className="btn btn-secondary btn-sm"
                            onClick={() => handleToggleQuarantine(b.id)}
                            style={{ color: b.status === 'Quarantined' ? '#16a34a' : '#7e22ce' }}
                            title={b.status === 'Quarantined' ? 'Release Quarantine' : 'Hold / Quarantine Batch'}
                          >
                            {b.status === 'Quarantined' ? <ShieldCheck size={14} /> : <ShieldBan size={14} />}
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
