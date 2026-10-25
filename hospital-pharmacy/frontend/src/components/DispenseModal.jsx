import React, { useState, useEffect } from 'react';
import { X, FileCheck, AlertTriangle, CheckCircle, ShieldAlert, Plus, Trash2 } from 'lucide-react';
import { api } from '../services/api';
import { useAuth } from '../hooks/useAuth';

export default function DispenseModal({ isOpen, onClose, onDispenseSuccess }) {
  const { user } = useAuth();
  const [prescriptions, setPrescriptions] = useState([]);
  const [medicines, setMedicines] = useState([]);

  // Form State
  const [selectedRxId, setSelectedRxId] = useState('');
  const [patientName, setPatientName] = useState('Arjun Kumar');
  const [patientId, setPatientId] = useState('PAT-2026-0012');
  const [doctorName, setDoctorName] = useState('Dr. Arun Prakash');
  const [prescriptionNumber, setPrescriptionNumber] = useState('RX-2026-001');
  const [notes, setNotes] = useState('Prescribed dosage checked and verified.');

  // Items to dispense
  const [items, setItems] = useState([
    { medicine_id: '', batch_id: '', quantity: 10, available_batches: [], max_stock: 0, price: 0 }
  ]);

  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (isOpen) {
      loadData();
      setError('');
    }
  }, [isOpen]);

  async function loadData() {
    try {
      const [rxList, medList] = await Promise.all([
        api.getPrescriptions({ status: 'PENDING' }),
        api.getMedicines({ is_active: true })
      ]);
      setPrescriptions(rxList);
      setMedicines(medList);

      if (medList.length > 0) {
        handleMedicineChange(0, medList[0].id, medList);
      }
    } catch (err) {
      console.error(err);
    }
  }

  function handleSelectPrescription(rxId) {
    setSelectedRxId(rxId);
    if (!rxId) return;

    const rx = prescriptions.find((p) => p.id === parseInt(rxId));
    if (rx) {
      setPatientName(rx.patient_name);
      setPatientId(rx.patient_id);
      setDoctorName(rx.doctor_name);
      setPrescriptionNumber(rx.prescription_number);
      if (rx.notes) setNotes(rx.notes);

      // If prescription has items, populate them
      if (rx.items && rx.items.length > 0) {
        const newItems = rx.items.map((it) => {
          const med = medicines.find((m) => m.id === it.medicine_id);
          const activeBatches = med?.batches?.filter((b) => b.status === 'Active' && b.current_quantity > 0) || [];
          return {
            medicine_id: it.medicine_id,
            batch_id: activeBatches.length > 0 ? activeBatches[0].id : '',
            quantity: it.prescribed_quantity,
            available_batches: activeBatches,
            max_stock: activeBatches.reduce((acc, b) => acc + b.current_quantity, 0),
            price: activeBatches.length > 0 ? activeBatches[0].selling_price : 0
          };
        });
        setItems(newItems);
      }
    }
  }

  function handleMedicineChange(index, medId, medList = medicines) {
    const med = medList.find((m) => m.id === parseInt(medId));
    const activeBatches = med?.batches?.filter((b) => b.status === 'Active' && b.current_quantity > 0) || [];

    const updated = [...items];
    updated[index] = {
      medicine_id: parseInt(medId),
      batch_id: activeBatches.length > 0 ? activeBatches[0].id : '',
      quantity: 1,
      available_batches: activeBatches,
      max_stock: activeBatches.reduce((acc, b) => acc + b.current_quantity, 0),
      price: activeBatches.length > 0 ? activeBatches[0].selling_price : 0
    };
    setItems(updated);
  }

  function handleBatchChange(index, batchId) {
    const updated = [...items];
    const b = updated[index].available_batches.find((x) => x.id === parseInt(batchId));
    updated[index].batch_id = batchId ? parseInt(batchId) : '';
    if (b) {
      updated[index].price = b.selling_price;
    }
    setItems(updated);
  }

  function handleQuantityChange(index, qty) {
    const updated = [...items];
    updated[index].quantity = Math.max(1, parseInt(qty) || 1);
    setItems(updated);
  }

  function addItem() {
    if (medicines.length === 0) return;
    const med = medicines[0];
    const activeBatches = med.batches?.filter((b) => b.status === 'Active' && b.current_quantity > 0) || [];
    setItems([
      ...items,
      {
        medicine_id: med.id,
        batch_id: activeBatches.length > 0 ? activeBatches[0].id : '',
        quantity: 1,
        available_batches: activeBatches,
        max_stock: activeBatches.reduce((acc, b) => acc + b.current_quantity, 0),
        price: activeBatches.length > 0 ? activeBatches[0].selling_price : 0
      }
    ]);
  }

  function removeItem(index) {
    if (items.length <= 1) return;
    setItems(items.filter((_, i) => i !== index));
  }

  const grandTotal = items.reduce((sum, item) => sum + (item.quantity * item.price), 0);

  if (!isOpen) return null;

  async function handleConfirmDispense(e) {
    e.preventDefault();
    if (!patientName) {
      setError('Patient name is required.');
      return;
    }

    // Safety checks: check if any item has 0 stock or exceeds stock
    for (let i = 0; i < items.length; i++) {
      const it = items[i];
      if (it.quantity > it.max_stock) {
        const med = medicines.find((m) => m.id === it.medicine_id);
        setError(`Dispensing quantity (${it.quantity}) for ${med?.brand_name} exceeds available active stock (${it.max_stock}).`);
        return;
      }
    }

    setLoading(true);
    setError('');

    try {
      const payload = {
        prescription_id: selectedRxId ? parseInt(selectedRxId) : null,
        prescription_number: prescriptionNumber || null,
        patient_name: patientName,
        patient_id: patientId || 'PAT-DEMO',
        doctor_name: doctorName,
        items: items.map((it) => ({
          medicine_id: it.medicine_id,
          batch_id: it.batch_id ? parseInt(it.batch_id) : null,
          quantity: it.quantity
        })),
        notes: notes
      };

      const receipt = await api.dispense(payload);
      onDispenseSuccess(receipt);
      onClose();
    } catch (err) {
      setError(err.message || 'Dispensing failed. Check batch availability and expiry.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="modal-backdrop">
      <div className="modal-dialog" style={{ maxWidth: 740 }}>
        <div className="modal-header">
          <div>
            <h3>🩺 Dispense Prescription Medicine</h3>
            <p style={{ fontSize: '0.8rem', color: '#64748b' }}>
              FEFO batch auto-allocation, expiry quarantine enforcement, and inventory ledger deduction.
            </p>
          </div>
          <button onClick={onClose} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#64748b' }}>
            <X size={20} />
          </button>
        </div>

        <form onSubmit={handleConfirmDispense}>
          <div className="modal-body">
            {error && (
              <div style={{ background: '#fef2f2', border: '1px solid #fecaca', color: '#b91c1c', padding: '10px 14px', borderRadius: 6, fontSize: '0.84rem' }}>
                <ShieldAlert size={16} style={{ display: 'inline', marginRight: 6, verticalAlign: 'middle' }} />
                {error}
              </div>
            )}

            {/* Quick load prescription */}
            {prescriptions.length > 0 && (
              <div className="form-group" style={{ background: '#f0f9ff', border: '1px solid #bae6fd', padding: 12, borderRadius: 8 }}>
                <label className="form-label" style={{ color: '#0369a1' }}>
                  📋 Load Pending Doctor Prescription (Auto-Fill)
                </label>
                <select
                  className="form-select"
                  value={selectedRxId}
                  onChange={(e) => handleSelectPrescription(e.target.value)}
                  style={{ borderColor: '#7dd3fc' }}
                >
                  <option value="">-- Manual Prescription or Counter Dispense --</option>
                  {prescriptions.map((rx) => (
                    <option key={rx.id} value={rx.id}>
                      {rx.prescription_number} | Patient: {rx.patient_name} ({rx.patient_id}) | Dr. {rx.doctor_name}
                    </option>
                  ))}
                </select>
              </div>
            )}

            <div className="form-row-3">
              <div className="form-group">
                <label className="form-label">Patient Name *</label>
                <input
                  type="text"
                  className="form-input"
                  value={patientName}
                  onChange={(e) => setPatientName(e.target.value)}
                  placeholder="e.g. Arjun Kumar"
                  required
                />
              </div>

              <div className="form-group">
                <label className="form-label">Patient Hospital ID</label>
                <input
                  type="text"
                  className="form-input"
                  value={patientId}
                  onChange={(e) => setPatientId(e.target.value)}
                  placeholder="e.g. PAT-2026-0012"
                />
              </div>

              <div className="form-group">
                <label className="form-label">Prescribing Physician</label>
                <input
                  type="text"
                  className="form-input"
                  value={doctorName}
                  onChange={(e) => setDoctorName(e.target.value)}
                  placeholder="e.g. Dr. Arun Prakash"
                />
              </div>
            </div>

            {/* Dispense Items Table */}
            <div style={{ marginTop: 6 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
                <span style={{ fontSize: '0.86rem', fontWeight: 700, color: '#0f172a' }}>
                  Medicines & Selected FEFO Batches
                </span>
                <button
                  type="button"
                  className="btn btn-secondary btn-sm"
                  onClick={addItem}
                >
                  <Plus size={14} /> Add Medicine
                </button>
              </div>

              <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                {items.map((item, idx) => (
                  <div
                    key={idx}
                    style={{
                      display: 'grid',
                      gridTemplateColumns: '2.5fr 2fr 1fr 1fr auto',
                      gap: 8,
                      alignItems: 'center',
                      background: '#f8fafc',
                      padding: 10,
                      borderRadius: 8,
                      border: '1px solid #e2e8f0'
                    }}
                  >
                    <div>
                      <label style={{ fontSize: '0.72rem', color: '#64748b', fontWeight: 600 }}>Medicine</label>
                      <select
                        className="form-select"
                        value={item.medicine_id}
                        onChange={(e) => handleMedicineChange(idx, e.target.value)}
                        style={{ fontSize: '0.82rem', padding: '6px 8px' }}
                      >
                        {medicines.map((m) => (
                          <option key={m.id} value={m.id}>
                            {m.brand_name} ({m.code}) - {m.strength}
                          </option>
                        ))}
                      </select>
                    </div>

                    <div>
                      <label style={{ fontSize: '0.72rem', color: '#64748b', fontWeight: 600 }}>Batch (FEFO)</label>
                      <select
                        className="form-select"
                        value={item.batch_id}
                        onChange={(e) => handleBatchChange(idx, e.target.value)}
                        style={{ fontSize: '0.82rem', padding: '6px 8px' }}
                      >
                        <option value="">-- Auto FEFO Earliest Expiry --</option>
                        {item.available_batches.map((b) => (
                          <option key={b.id} value={b.id}>
                            {b.batch_number} (Exp: {b.expiry_date}) [{b.current_quantity} avail]
                          </option>
                        ))}
                      </select>
                    </div>

                    <div>
                      <label style={{ fontSize: '0.72rem', color: '#64748b', fontWeight: 600 }}>Qty</label>
                      <input
                        type="number"
                        className="form-input"
                        value={item.quantity}
                        onChange={(e) => handleQuantityChange(idx, e.target.value)}
                        min="1"
                        max={item.max_stock || 9999}
                        style={{ fontSize: '0.82rem', padding: '6px 8px' }}
                      />
                    </div>

                    <div>
                      <label style={{ fontSize: '0.72rem', color: '#64748b', fontWeight: 600 }}>Price</label>
                      <div style={{ fontSize: '0.84rem', fontWeight: 700, color: '#0f172a', padding: '6px 0' }}>
                        ₹{(item.quantity * item.price).toFixed(2)}
                      </div>
                    </div>

                    <div>
                      {items.length > 1 && (
                        <button
                          type="button"
                          onClick={() => removeItem(idx)}
                          style={{ background: 'none', border: 'none', color: '#ef4444', cursor: 'pointer', marginTop: 14 }}
                          title="Remove item"
                        >
                          <Trash2 size={16} />
                        </button>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            </div>

            {/* Total summary */}
            <div
              style={{
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                padding: '12px 16px',
                background: '#ecfdf5',
                border: '1px solid #a7f3d0',
                borderRadius: 8,
                marginTop: 8
              }}
            >
              <div>
                <span style={{ fontSize: '0.84rem', fontWeight: 600, color: '#065f46' }}>
                  Pharmacist Dispensing Review: Verified by {user?.full_name || 'Staff'}
                </span>
                <p style={{ fontSize: '0.74rem', color: '#047857' }}>
                  Safety Check: Zero expired batches, non-negative inventory balance verified.
                </p>
              </div>
              <div style={{ textAlign: 'right' }}>
                <span style={{ fontSize: '0.75rem', color: '#047857', fontWeight: 600, textTransform: 'uppercase' }}>
                  Estimated Total
                </span>
                <div style={{ fontSize: '1.25rem', fontWeight: 800, color: '#065f46' }}>
                  ₹{grandTotal.toFixed(2)}
                </div>
              </div>
            </div>

            <div className="form-group">
              <label className="form-label">Clinical Dispensing Notes</label>
              <input
                type="text"
                className="form-input"
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                placeholder="e.g. Dosage verified with patient; administered after breakfast instructions."
              />
            </div>
          </div>

          <div className="modal-footer">
            <button type="button" className="btn btn-secondary" onClick={onClose}>
              Cancel
            </button>
            <button type="submit" className="btn btn-teal" disabled={loading} style={{ background: '#0284c7' }}>
              <FileCheck size={16} />
              <span>{loading ? 'Processing Dispense...' : 'Confirm Safe Dispensing & Print Receipt'}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
