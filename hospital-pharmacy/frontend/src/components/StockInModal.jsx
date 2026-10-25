import React, { useState, useEffect } from 'react';
import { X, PackagePlus, AlertCircle } from 'lucide-react';
import { api } from '../services/api';

export default function StockInModal({ isOpen, onClose, onStockInSuccess, preselectedMedicineId = null }) {
  const [medicines, setMedicines] = useState([]);
  const [suppliers, setSuppliers] = useState([]);
  const [formData, setFormData] = useState({
    medicine_id: '',
    batch_number: '',
    supplier_id: '',
    purchase_price: '',
    selling_price: '',
    initial_quantity: '',
    manufacturing_date: '',
    expiry_date: '',
    storage_location: 'Main Dispensary Shelf A1',
    temperature_celsius: ''
  });
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (isOpen) {
      loadDependencies();
      const today = new Date().toISOString().split('T')[0];
      const nextYear = new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
      
      setFormData({
        medicine_id: preselectedMedicineId || '',
        batch_number: `BN-${new Date().getFullYear()}-${Math.floor(1000 + Math.random() * 9000)}`,
        supplier_id: '',
        purchase_price: '',
        selling_price: '',
        initial_quantity: '100',
        manufacturing_date: today,
        expiry_date: nextYear,
        storage_location: 'Main Dispensary Shelf A1',
        temperature_celsius: ''
      });
      setError('');
    }
  }, [isOpen, preselectedMedicineId]);

  async function loadDependencies() {
    try {
      const [medList, supList] = await Promise.all([
        api.getMedicines({ is_active: true }),
        api.getSuppliers()
      ]);
      setMedicines(medList);
      setSuppliers(supList);
      if (!preselectedMedicineId && medList.length > 0) {
        setFormData(prev => ({ ...prev, medicine_id: medList[0].id }));
      }
      if (supList.length > 0) {
        setFormData(prev => ({ ...prev, supplier_id: supList[0].id }));
      }
    } catch (err) {
      console.error(err);
    }
  }

  if (!isOpen) return null;

  async function handleSubmit(e) {
    e.preventDefault();
    if (!formData.medicine_id || !formData.batch_number || !formData.purchase_price || !formData.selling_price || !formData.initial_quantity) {
      setError('Please fill in all mandatory fields.');
      return;
    }
    if (new Date(formData.expiry_date) <= new Date(formData.manufacturing_date)) {
      setError('Expiry date must be in the future beyond manufacturing date.');
      return;
    }

    setLoading(true);
    setError('');

    try {
      const payload = {
        medicine_id: parseInt(formData.medicine_id),
        batch_number: formData.batch_number,
        supplier_id: formData.supplier_id ? parseInt(formData.supplier_id) : null,
        purchase_price: parseFloat(formData.purchase_price),
        selling_price: parseFloat(formData.selling_price),
        initial_quantity: parseInt(formData.initial_quantity),
        manufacturing_date: formData.manufacturing_date,
        expiry_date: formData.expiry_date,
        storage_location: formData.storage_location,
        temperature_celsius: formData.temperature_celsius ? parseFloat(formData.temperature_celsius) : null
      };

      await api.stockInBatch(payload);
      onStockInSuccess();
      onClose();
    } catch (err) {
      setError(err.message || 'Failed to record batch receipt');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="modal-backdrop">
      <div className="modal-dialog">
        <div className="modal-header">
          <h3>📦 Stock In Inward Medicine Batch</h3>
          <button onClick={onClose} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#64748b' }}>
            <X size={20} />
          </button>
        </div>

        <form onSubmit={handleSubmit}>
          <div className="modal-body">
            {error && (
              <div style={{ background: '#fef2f2', border: '1px solid #fecaca', color: '#b91c1c', padding: '10px 14px', borderRadius: 6, fontSize: '0.84rem' }}>
                <AlertCircle size={16} style={{ display: 'inline', marginRight: 6, verticalAlign: 'middle' }} />
                {error}
              </div>
            )}

            <div className="form-group">
              <label className="form-label">Select Medicine *</label>
              <select
                className="form-select"
                value={formData.medicine_id}
                onChange={(e) => setFormData({ ...formData, medicine_id: e.target.value })}
                required
              >
                <option value="">-- Choose Medicine --</option>
                {medicines.map((m) => (
                  <option key={m.id} value={m.id}>
                    {m.code} - {m.brand_name} ({m.generic_name}) - [{m.dosage_form} {m.strength}]
                  </option>
                ))}
              </select>
            </div>

            <div className="form-row-2">
              <div className="form-group">
                <label className="form-label">Batch / Lot Number *</label>
                <input
                  type="text"
                  className="form-input"
                  value={formData.batch_number}
                  onChange={(e) => setFormData({ ...formData, batch_number: e.target.value })}
                  placeholder="e.g. BN-2026-904"
                  required
                />
              </div>

              <div className="form-group">
                <label className="form-label">Supplier / Distributor</label>
                <select
                  className="form-select"
                  value={formData.supplier_id}
                  onChange={(e) => setFormData({ ...formData, supplier_id: e.target.value })}
                >
                  <option value="">-- Direct Sourcing --</option>
                  {suppliers.map((s) => (
                    <option key={s.id} value={s.id}>{s.name}</option>
                  ))}
                </select>
              </div>
            </div>

            <div className="form-row-3">
              <div className="form-group">
                <label className="form-label">Purchase Price (₹) *</label>
                <input
                  type="number"
                  step="0.01"
                  className="form-input"
                  value={formData.purchase_price}
                  onChange={(e) => setFormData({ ...formData, purchase_price: e.target.value })}
                  placeholder="e.g. 45.00"
                  required
                />
              </div>

              <div className="form-group">
                <label className="form-label">Selling MRP (₹) *</label>
                <input
                  type="number"
                  step="0.01"
                  className="form-input"
                  value={formData.selling_price}
                  onChange={(e) => setFormData({ ...formData, selling_price: e.target.value })}
                  placeholder="e.g. 78.00"
                  required
                />
              </div>

              <div className="form-group">
                <label className="form-label">Received Quantity *</label>
                <input
                  type="number"
                  className="form-input"
                  value={formData.initial_quantity}
                  onChange={(e) => setFormData({ ...formData, initial_quantity: e.target.value })}
                  min="1"
                  placeholder="Units"
                  required
                />
              </div>
            </div>

            <div className="form-row-2">
              <div className="form-group">
                <label className="form-label">Manufacturing Date *</label>
                <input
                  type="date"
                  className="form-input"
                  value={formData.manufacturing_date}
                  onChange={(e) => setFormData({ ...formData, manufacturing_date: e.target.value })}
                  required
                />
              </div>

              <div className="form-group">
                <label className="form-label">Expiry Date *</label>
                <input
                  type="date"
                  className="form-input"
                  value={formData.expiry_date}
                  onChange={(e) => setFormData({ ...formData, expiry_date: e.target.value })}
                  required
                />
              </div>
            </div>

            <div className="form-row-2">
              <div className="form-group">
                <label className="form-label">Dispensary Storage Shelf / Bay</label>
                <input
                  type="text"
                  className="form-input"
                  value={formData.storage_location}
                  onChange={(e) => setFormData({ ...formData, storage_location: e.target.value })}
                  placeholder="e.g. Shelf B3 / Cold Room Unit 2"
                />
              </div>

              <div className="form-group">
                <label className="form-label">Cold-Chain Temp (°C, optional)</label>
                <input
                  type="number"
                  step="0.1"
                  className="form-input"
                  value={formData.temperature_celsius}
                  onChange={(e) => setFormData({ ...formData, temperature_celsius: e.target.value })}
                  placeholder="e.g. 4.5 for Insulin (2-8°C)"
                />
              </div>
            </div>
          </div>

          <div className="modal-footer">
            <button type="button" className="btn btn-secondary" onClick={onClose}>
              Cancel
            </button>
            <button type="submit" className="btn btn-teal" disabled={loading}>
              <PackagePlus size={16} />
              <span>{loading ? 'Receiving...' : 'Confirm Stock Receipt'}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
