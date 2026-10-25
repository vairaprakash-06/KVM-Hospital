import React, { useState, useEffect } from 'react';
import {
  Search,
  Filter,
  Plus,
  PackagePlus,
  FileCheck,
  Edit2,
  ChevronDown,
  ChevronUp,
  Layers,
  Power
} from 'lucide-react';
import { api } from '../services/api';
import { useAuth } from '../hooks/useAuth';

export default function Catalog({ onOpenNewMed, onOpenStockInWithMed, onOpenDispenseWithMed, onEditMedicine }) {
  const { user } = useAuth();
  const [medicines, setMedicines] = useState([]);
  const [categories, setCategories] = useState([]);
  const [manufacturers, setManufacturers] = useState([]);

  // Filters
  const [search, setSearch] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('All');
  const [selectedStatus, setSelectedStatus] = useState('All');
  const [selectedManufacturer, setSelectedManufacturer] = useState('All');

  // Expanded batch rows
  const [expandedMedId, setExpandedMedId] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadFilterOptions();
  }, []);

  useEffect(() => {
    loadMedicines();
  }, [search, selectedCategory, selectedStatus, selectedManufacturer]);

  async function loadFilterOptions() {
    try {
      const [cats, mans] = await Promise.all([
        api.getCategories(),
        api.getManufacturers()
      ]);
      setCategories(['All', ...cats]);
      setManufacturers(['All', ...mans]);
    } catch (err) {
      console.error(err);
    }
  }

  async function loadMedicines() {
    try {
      setLoading(true);
      const params = {};
      if (search) params.search = search;
      if (selectedCategory !== 'All') params.category = selectedCategory;
      if (selectedStatus !== 'All') params.status = selectedStatus;
      if (selectedManufacturer !== 'All') params.manufacturer = selectedManufacturer;

      const data = await api.getMedicines(params);
      setMedicines(data);
    } catch (err) {
      console.error('Failed to load medicines ledger:', err);
    } finally {
      setLoading(false);
    }
  }

  async function handleToggleActive(medId) {
    if (!window.confirm('Are you sure you want to toggle the active status of this formulary item?')) return;
    try {
      await api.toggleMedicineActive(medId);
      loadMedicines();
    } catch (err) {
      alert(err.message || 'Action failed');
    }
  }

  function getStatusClass(health) {
    switch (health) {
      case 'Healthy': return 'healthy';
      case 'Low Stock': return 'low-stock';
      case 'Out of Stock': return 'out-of-stock';
      case 'Expiring Soon': return 'expiring-soon';
      case 'Expired': return 'expired';
      default: return 'healthy';
    }
  }

  return (
    <div className="page-content">
      {/* Search & Comprehensive Filters Bar */}
      <div className="content-card" style={{ marginBottom: 16 }}>
        <div className="filter-bar">
          <div className="search-input-wrap">
            <Search size={16} className="search-icon" />
            <input
              type="text"
              className="search-input"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search by brand name, generic name, code, manufacturer..."
            />
          </div>

          <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
            <select
              className="filter-select"
              value={selectedCategory}
              onChange={(e) => setSelectedCategory(e.target.value)}
            >
              <option value="All">All Categories</option>
              {categories.filter(c => c !== 'All').map(c => (
                <option key={c} value={c}>{c}</option>
              ))}
            </select>

            <select
              className="filter-select"
              value={selectedStatus}
              onChange={(e) => setSelectedStatus(e.target.value)}
            >
              <option value="All">All Stock Health</option>
              <option value="Healthy">🟢 Healthy Stock</option>
              <option value="Low Stock">🟡 Low Stock Warning</option>
              <option value="Out of Stock">🔴 Out of Stock</option>
              <option value="Expiring Soon">🟠 Expiring Soon (&lt;90d)</option>
              <option value="Expired">⛔ Expired</option>
            </select>

            <select
              className="filter-select"
              value={selectedManufacturer}
              onChange={(e) => setSelectedManufacturer(e.target.value)}
            >
              <option value="All">All Manufacturers</option>
              {manufacturers.filter(m => m !== 'All').map(m => (
                <option key={m} value={m}>{m}</option>
              ))}
            </select>
          </div>
        </div>

        {/* Ledger Table */}
        <div className="table-responsive">
          <table className="data-table">
            <thead>
              <tr>
                <th>Code</th>
                <th>Brand & Generic Name</th>
                <th>Category</th>
                <th>Dosage & Strength</th>
                <th>Unit Price</th>
                <th>Available Stock</th>
                <th>Reorder</th>
                <th>Stock Health</th>
                <th>Nearest Expiry</th>
                <th style={{ textAlign: 'right' }}>Quick Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan="10" style={{ textAlign: 'center', padding: 32, color: '#94a3b8' }}>
                    Loading formulary inventory records...
                  </td>
                </tr>
              ) : medicines.length === 0 ? (
                <tr>
                  <td colSpan="10" style={{ textAlign: 'center', padding: 32, color: '#94a3b8' }}>
                    No medicines match the selected search criteria.
                  </td>
                </tr>
              ) : (
                medicines.map((med) => (
                  <React.Fragment key={med.id}>
                    <tr style={{ opacity: med.is_active ? 1 : 0.6 }}>
                      {/* Code */}
                      <td>
                        <span className="code-pill">{med.code}</span>
                      </td>

                      {/* Brand & Generic */}
                      <td>
                        <div className="med-title-col">
                          <span className="med-brand-name">{med.brand_name}</span>
                          <span className="med-generic-name">{med.generic_name}</span>
                        </div>
                      </td>

                      {/* Category */}
                      <td>
                        <span style={{ fontSize: '0.8rem', color: '#475569' }}>{med.category}</span>
                      </td>

                      {/* Dosage Form & Strength */}
                      <td>
                        <div style={{ display: 'flex', flexDirection: 'column' }}>
                          <span style={{ fontWeight: 600 }}>{med.dosage_form}</span>
                          <span style={{ fontSize: '0.75rem', color: '#64748b' }}>{med.strength}</span>
                        </div>
                      </td>

                      {/* Unit Price */}
                      <td style={{ fontWeight: 700, color: '#0f172a' }}>
                        ₹{med.unit_price?.toFixed(2)}
                      </td>

                      {/* Available Stock */}
                      <td>
                        <div style={{ fontWeight: 800, fontSize: '0.94rem', color: med.available_stock === 0 ? '#b91c1c' : '#0f172a' }}>
                          {med.available_stock.toLocaleString('en-IN')} <span style={{ fontSize: '0.75rem', fontWeight: 500, color: '#64748b' }}>{med.unit_of_measure}</span>
                        </div>
                      </td>

                      {/* Reorder Level */}
                      <td style={{ color: '#64748b', fontWeight: 600 }}>
                        {med.reorder_level}
                      </td>

                      {/* Stock Health */}
                      <td>
                        <span className={`status-badge ${getStatusClass(med.stock_health)}`}>
                          {med.stock_health}
                        </span>
                      </td>

                      {/* Nearest Expiry */}
                      <td>
                        {med.nearest_expiry ? (
                          <span style={{ fontSize: '0.8rem', fontWeight: 600 }}>
                            {new Date(med.nearest_expiry).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })}
                          </span>
                        ) : (
                          <span style={{ fontSize: '0.75rem', color: '#94a3b8' }}>None</span>
                        )}
                      </td>

                      {/* Quick Actions */}
                      <td style={{ textAlign: 'right' }}>
                        <div style={{ display: 'flex', gap: 6, justifyContent: 'flex-end', alignItems: 'center' }}>
                          {/* Dispense button */}
                          <button
                            className="btn btn-blue-outline btn-sm"
                            onClick={() => onOpenDispenseWithMed(med.id)}
                            disabled={med.available_stock <= 0}
                            title="Dispense from earliest batch (FEFO)"
                          >
                            <FileCheck size={14} />
                            <span>Dispense</span>
                          </button>

                          {/* Stock In button */}
                          <button
                            className="btn btn-teal btn-sm"
                            onClick={() => onOpenStockInWithMed(med.id)}
                            title="Stock In New Batch"
                          >
                            <PackagePlus size={14} />
                          </button>

                          {/* Toggle Batches Drawer */}
                          <button
                            className="btn btn-secondary btn-sm"
                            onClick={() => setExpandedMedId(expandedMedId === med.id ? null : med.id)}
                            title="View registered batches"
                          >
                            <Layers size={14} />
                            {expandedMedId === med.id ? <ChevronUp size={14} /> : <ChevronDown size={14} />}
                          </button>

                          {/* Edit button */}
                          <button
                            className="btn btn-secondary btn-sm"
                            onClick={() => onEditMedicine(med)}
                            title="Edit details"
                          >
                            <Edit2 size={14} />
                          </button>

                          {/* Deactivate / Activate button */}
                          {user?.role === 'Hospital Administrator' && (
                            <button
                              className="btn btn-secondary btn-sm"
                              onClick={() => handleToggleActive(med.id)}
                              style={{ color: med.is_active ? '#dc2626' : '#16a34a' }}
                              title={med.is_active ? 'Deactivate formulary item' : 'Activate formulary item'}
                            >
                              <Power size={13} />
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>

                    {/* Sub-table: Batches Breakdown */}
                    {expandedMedId === med.id && (
                      <tr style={{ background: '#f8fafc' }}>
                        <td colSpan="10" style={{ padding: '12px 24px' }}>
                          <div style={{ background: '#ffffff', border: '1px solid #e2e8f0', borderRadius: 8, padding: 12 }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 8, fontSize: '0.8rem', fontWeight: 700, color: '#0f172a' }}>
                              <span>Batches in Storage for {med.brand_name} ({med.batches?.length || 0} batches)</span>
                              <span style={{ color: '#64748b' }}>Storage: {med.storage_conditions || 'Room Temp'}</span>
                            </div>
                            {med.batches?.length === 0 ? (
                              <div style={{ fontSize: '0.8rem', color: '#94a3b8' }}>No batches registered yet. Click &quot;Stock In&quot; to add inventory.</div>
                            ) : (
                              <table style={{ width: '100%', fontSize: '0.78rem', borderCollapse: 'collapse' }}>
                                <thead>
                                  <tr style={{ textAlign: 'left', color: '#64748b', borderBottom: '1px solid #e2e8f0' }}>
                                    <th style={{ paddingBottom: 4 }}>Batch No</th>
                                    <th style={{ paddingBottom: 4 }}>Current Qty</th>
                                    <th style={{ paddingBottom: 4 }}>Purchase (INR)</th>
                                    <th style={{ paddingBottom: 4 }}>MRP (INR)</th>
                                    <th style={{ paddingBottom: 4 }}>Expiry Date</th>
                                    <th style={{ paddingBottom: 4 }}>Location</th>
                                    <th style={{ paddingBottom: 4 }}>Status</th>
                                  </tr>
                                </thead>
                                <tbody>
                                  {med.batches?.map((b) => (
                                    <tr key={b.id} style={{ borderBottom: '1px solid #f1f5f9' }}>
                                      <td style={{ paddingTop: 4 }}><span className="code-pill">{b.batch_number}</span></td>
                                      <td style={{ paddingTop: 4, fontWeight: 700 }}>{b.current_quantity}</td>
                                      <td style={{ paddingTop: 4 }}>₹{b.purchase_price}</td>
                                      <td style={{ paddingTop: 4, fontWeight: 600 }}>₹{b.selling_price}</td>
                                      <td style={{ paddingTop: 4 }}>{b.expiry_date}</td>
                                      <td style={{ paddingTop: 4 }}>{b.storage_location}</td>
                                      <td style={{ paddingTop: 4 }}>
                                        <span className={`status-badge ${getStatusClass(b.status === 'Active' ? 'Healthy' : b.status === 'Expired' ? 'Expired' : 'Quarantined')}`}>
                                          {b.status}
                                        </span>
                                      </td>
                                    </tr>
                                  ))}
                                </tbody>
                              </table>
                            )}
                          </div>
                        </td>
                      </tr>
                    )}
                  </React.Fragment>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
