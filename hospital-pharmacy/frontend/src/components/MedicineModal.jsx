import React, { useState, useEffect } from 'react';
import { X, Check } from 'lucide-react';

export default function MedicineModal({ isOpen, onClose, onSave, medicine = null }) {
  const [formData, setFormData] = useState({
    code: '',
    brand_name: '',
    generic_name: '',
    category: 'General & Pain Relief',
    strength: '',
    dosage_form: 'Tablet',
    manufacturer: '',
    unit_of_measure: 'strip',
    pack_size: '10 tabs/strip',
    reorder_level: 50,
    storage_conditions: 'Room temp below 25°C',
    requires_prescription: true
  });
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (medicine) {
      setFormData({
        code: medicine.code || '',
        brand_name: medicine.brand_name || '',
        generic_name: medicine.generic_name || '',
        category: medicine.category || 'General & Pain Relief',
        strength: medicine.strength || '',
        dosage_form: medicine.dosage_form || 'Tablet',
        manufacturer: medicine.manufacturer || '',
        unit_of_measure: medicine.unit_of_measure || 'strip',
        pack_size: medicine.pack_size || '10 tabs/strip',
        reorder_level: medicine.reorder_level || 50,
        storage_conditions: medicine.storage_conditions || 'Room temp below 25°C',
        requires_prescription: medicine.requires_prescription !== undefined ? medicine.requires_prescription : true
      });
    } else {
      setFormData({
        code: `MED-${Math.floor(100 + Math.random() * 900)}`,
        brand_name: '',
        generic_name: '',
        category: 'General & Pain Relief',
        strength: '',
        dosage_form: 'Tablet',
        manufacturer: '',
        unit_of_measure: 'strip',
        pack_size: '10 tabs/strip',
        reorder_level: 50,
        storage_conditions: 'Room temp below 25°C in a dry place',
        requires_prescription: true
      });
    }
    setError('');
  }, [medicine, isOpen]);

  if (!isOpen) return null;

  async function handleSubmit(e) {
    e.preventDefault();
    if (!formData.brand_name || !formData.generic_name || !formData.code) {
      setError('Medicine Code, Brand Name, and Generic Name are mandatory.');
      return;
    }
    setLoading(true);
    setError('');
    try {
      await onSave(formData, medicine?.id);
      onClose();
    } catch (err) {
      setError(err.message || 'Failed to save medicine record');
    } finally {
      setLoading(false);
    }
  }

  const categories = [
    'General & Pain Relief',
    'Antibiotics',
    'Cardiovascular',
    'Diabetes Care',
    'Gastrointestinal',
    'Allergy & Respiratory',
    'Fluids & Emergency',
    'Wound Care & Consumables'
  ];

  const forms = ['Tablet', 'Capsule', 'Syrup', 'Injection', 'Inhaler', 'Solution', 'Powder Sachet', 'Box', 'Roll', 'Piece'];

  return (
    <div className="modal-backdrop">
      <div className="modal-dialog">
        <div className="modal-header">
          <h3>{medicine ? 'Edit Medicine Details' : '+ Add New Medicine to Formulary'}</h3>
          <button onClick={onClose} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#64748b' }}>
            <X size={20} />
          </button>
        </div>

        <form onSubmit={handleSubmit}>
          <div className="modal-body">
            {error && (
              <div style={{ background: '#fef2f2', border: '1px solid #fecaca', color: '#b91c1c', padding: '10px 14px', borderRadius: 6, fontSize: '0.84rem' }}>
                {error}
              </div>
            )}

            <div className="form-row-2">
              <div className="form-group">
                <label className="form-label">Medicine Code *</label>
                <input
                  type="text"
                  className="form-input"
                  value={formData.code}
                  onChange={(e) => setFormData({ ...formData, code: e.target.value })}
                  placeholder="e.g. PCM-650"
                  required
                  disabled={!!medicine}
                />
              </div>

              <div className="form-group">
                <label className="form-label">Category *</label>
                <select
                  className="form-select"
                  value={formData.category}
                  onChange={(e) => setFormData({ ...formData, category: e.target.value })}
                >
                  {categories.map((c) => (
                    <option key={c} value={c}>{c}</option>
                  ))}
                </select>
              </div>
            </div>

            <div className="form-row-2">
              <div className="form-group">
                <label className="form-label">Brand Name *</label>
                <input
                  type="text"
                  className="form-input"
                  value={formData.brand_name}
                  onChange={(e) => setFormData({ ...formData, brand_name: e.target.value })}
                  placeholder="e.g. Dolo-650"
                  required
                />
              </div>

              <div className="form-group">
                <label className="form-label">Generic / Chemical Name *</label>
                <input
                  type="text"
                  className="form-input"
                  value={formData.generic_name}
                  onChange={(e) => setFormData({ ...formData, generic_name: e.target.value })}
                  placeholder="e.g. Paracetamol"
                  required
                />
              </div>
            </div>

            <div className="form-row-3">
              <div className="form-group">
                <label className="form-label">Dosage Form</label>
                <select
                  className="form-select"
                  value={formData.dosage_form}
                  onChange={(e) => setFormData({ ...formData, dosage_form: e.target.value })}
                >
                  {forms.map((f) => (
                    <option key={f} value={f}>{f}</option>
                  ))}
                </select>
              </div>

              <div className="form-group">
                <label className="form-label">Strength</label>
                <input
                  type="text"
                  className="form-input"
                  value={formData.strength}
                  onChange={(e) => setFormData({ ...formData, strength: e.target.value })}
                  placeholder="e.g. 650 mg / 10 ml"
                  required
                />
              </div>

              <div className="form-group">
                <label className="form-label">Reorder Threshold</label>
                <input
                  type="number"
                  className="form-input"
                  value={formData.reorder_level}
                  onChange={(e) => setFormData({ ...formData, reorder_level: parseInt(e.target.value) || 0 })}
                  min="1"
                />
              </div>
            </div>

            <div className="form-row-2">
              <div className="form-group">
                <label className="form-label">Manufacturer *</label>
                <input
                  type="text"
                  className="form-input"
                  value={formData.manufacturer}
                  onChange={(e) => setFormData({ ...formData, manufacturer: e.target.value })}
                  placeholder="e.g. Cipla Ltd / Sun Pharma"
                  required
                />
              </div>

              <div className="form-group">
                <label className="form-label">Pack Size / Dispense Unit</label>
                <input
                  type="text"
                  className="form-input"
                  value={formData.pack_size}
                  onChange={(e) => setFormData({ ...formData, pack_size: e.target.value })}
                  placeholder="e.g. 15 tabs/strip"
                />
              </div>
            </div>

            <div className="form-group">
              <label className="form-label">Storage Conditions</label>
              <input
                type="text"
                className="form-input"
                value={formData.storage_conditions}
                onChange={(e) => setFormData({ ...formData, storage_conditions: e.target.value })}
                placeholder="e.g. Room temp below 25°C or Cold storage 2°C - 8°C"
              />
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginTop: 4 }}>
              <input
                type="checkbox"
                id="req-rx"
                checked={formData.requires_prescription}
                onChange={(e) => setFormData({ ...formData, requires_prescription: e.target.checked })}
                style={{ width: 16, height: 16 }}
              />
              <label htmlFor="req-rx" style={{ fontSize: '0.84rem', fontWeight: 600, color: '#334155' }}>
                Requires Valid Physician Prescription (Schedule H / X)
              </label>
            </div>
          </div>

          <div className="modal-footer">
            <button type="button" className="btn btn-secondary" onClick={onClose}>
              Cancel
            </button>
            <button type="submit" className="btn btn-teal" disabled={loading}>
              <Check size={16} />
              <span>{loading ? 'Saving...' : medicine ? 'Save Changes' : 'Register Medicine'}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
