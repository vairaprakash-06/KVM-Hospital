import React, { useState, useEffect } from 'react';
import { X, SlidersHorizontal, AlertTriangle } from 'lucide-react';
import { api } from '../services/api';

export default function AdjustStockModal({ isOpen, onClose, batch, onAdjustSuccess }) {
  const [newQuantity, setNewQuantity] = useState('');
  const [reason, setReason] = useState('Physical Inventory Reconciliation');
  const [notes, setNotes] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (batch && isOpen) {
      setNewQuantity(batch.current_quantity.toString());
      setReason('Physical Inventory Reconciliation');
      setNotes('');
      setError('');
    }
  }, [batch, isOpen]);

  if (!isOpen || !batch) return null;

  const currentQty = batch.current_quantity;
  const parsedNew = parseInt(newQuantity) || 0;
  const diff = parsedNew - currentQty;

  const reasons = [
    'Physical Inventory Reconciliation',
    'Breakage / Leakage / Damage',
    'Routine Pharmacy Cycle Count',
    'Return to Supplier / Recall',
    'Internal Ward Transfer Correction'
  ];

  async function handleSubmit(e) {
    e.preventDefault();
    if (newQuantity === '' || isNaN(newQuantity) || parsedNew < 0) {
      setError('Please provide a valid non-negative count.');
      return;
    }
    if (!reason) {
      setError('A mandatory adjustment reason is required for compliance audit logging.');
      return;
    }

    setLoading(true);
    setError('');

    try {
      await api.adjustBatch(batch.id, {
        new_quantity: parsedNew,
        reason: reason,
        notes: notes || `Stock adjusted from ${currentQty} to ${parsedNew}`
      });
      onAdjustSuccess();
      onClose();
    } catch (err) {
      setError(err.message || 'Stock adjustment failed');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="modal-backdrop">
      <div className="modal-dialog" style={{ maxWidth: 500 }}>
        <div className="modal-header">
          <h3>⚖️ Physical Inventory Count Adjustment</h3>
          <button onClick={onClose} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#64748b' }}>
            <X size={20} />
          </button>
        </div>

        <form onSubmit={handleSubmit}>
          <div className="modal-body">
            {error && (
              <div style={{ background: '#fef2f2', border: '1px solid #fecaca', color: '#b91c1c', padding: '10px 14px', borderRadius: 6, fontSize: '0.84rem' }}>
                <AlertTriangle size={15} style={{ display: 'inline', marginRight: 6, verticalAlign: 'middle' }} />
                {error}
              </div>
            )}

            <div style={{ background: '#f8fafc', padding: 12, borderRadius: 8, border: '1px solid #e2e8f0', fontSize: '0.84rem' }}>
              <div><strong>Medicine:</strong> {batch.medicine_name || 'Medicine'} ({batch.medicine_code})</div>
              <div><strong>Batch Number:</strong> <span className="code-pill">{batch.batch_number}</span></div>
              <div><strong>Expiry Date:</strong> {batch.expiry_date}</div>
              <div><strong>Recorded System Stock:</strong> <span style={{ fontWeight: 700, color: '#0ea5e9' }}>{currentQty} units</span></div>
            </div>

            <div className="form-group">
              <label className="form-label">Verified Physical Stock Count *</label>
              <input
                type="number"
                className="form-input"
                value={newQuantity}
                onChange={(e) => setNewQuantity(e.target.value)}
                min="0"
                required
              />
              <div style={{ fontSize: '0.78rem', marginTop: 4, color: diff > 0 ? '#15803d' : diff < 0 ? '#b91c1c' : '#64748b' }}>
                Variance: {diff > 0 ? `+${diff} units (Surplus)` : diff < 0 ? `${diff} units (Shrinkage/Loss)` : '0 units (No Change)'}
              </div>
            </div>

            <div className="form-group">
              <label className="form-label">Mandatory Audit Reason *</label>
              <select
                className="form-select"
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                required
              >
                {reasons.map((r) => (
                  <option key={r} value={r}>{r}</option>
                ))}
              </select>
            </div>

            <div className="form-group">
              <label className="form-label">Audit Notes & Inspection Reference</label>
              <input
                type="text"
                className="form-input"
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                placeholder="e.g. Broken ampoule discovered during morning audit"
              />
            </div>
          </div>

          <div className="modal-footer">
            <button type="button" className="btn btn-secondary" onClick={onClose}>
              Cancel
            </button>
            <button type="submit" className="btn btn-teal" disabled={loading}>
              <SlidersHorizontal size={16} />
              <span>{loading ? 'Reconciling...' : 'Confirm Audit Adjustment'}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
