import React from 'react';
import { X, Printer, CheckCircle2 } from 'lucide-react';

export default function ReceiptModal({ isOpen, onClose, receipt }) {
  if (!isOpen || !receipt) return null;

  return (
    <div className="modal-backdrop">
      <div className="modal-dialog" style={{ maxWidth: 540 }}>
        <div className="modal-header">
          <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
            <CheckCircle2 size={20} color="#10b981" />
            <h3 style={{ fontSize: '1.05rem' }}>Dispensing Successful</h3>
          </div>
          <button onClick={onClose} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#64748b' }}>
            <X size={20} />
          </button>
        </div>

        <div className="modal-body">
          {/* Printable Thermal Receipt Card */}
          <div className="receipt-paper" id="dispensing-receipt-printable">
            <div className="receipt-header">
              <h4>KVM HOSPITAL - NEXUSCARE</h4>
              <div style={{ fontSize: '0.8rem', fontWeight: 600 }}>Central Pharmacy & Clinical Dispensing Unit</div>
              <div style={{ fontSize: '0.72rem', color: '#475569' }}>Madurai - Chennai NH, Tamil Nadu, India</div>
              <div style={{ fontSize: '0.72rem', color: '#475569' }}>Lic No: TN-PHARM-2026-8891 | GSTIN: 33AAAAA0000A1Z5</div>
            </div>

            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.78rem', marginBottom: 6 }}>
              <div><strong>Receipt:</strong> {receipt.receipt_number}</div>
              <div><strong>Date:</strong> {new Date(receipt.dispensing_date).toLocaleString()}</div>
            </div>

            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.78rem', marginBottom: 10 }}>
              <div><strong>Patient:</strong> {receipt.patient_name} ({receipt.patient_id})</div>
              {receipt.doctor_name && <div><strong>Doctor:</strong> {receipt.doctor_name}</div>}
            </div>

            <div style={{ borderTop: '1px dashed #94a3b8', borderBottom: '1px dashed #94a3b8', padding: '8px 0', margin: '8px 0' }}>
              <table style={{ width: '100%', fontSize: '0.76rem', borderCollapse: 'collapse' }}>
                <thead>
                  <tr style={{ textAlign: 'left', borderBottom: '1px solid #cbd5e1' }}>
                    <th style={{ paddingBottom: 4 }}>Item</th>
                    <th style={{ paddingBottom: 4 }}>Batch</th>
                    <th style={{ paddingBottom: 4, textAlign: 'center' }}>Qty</th>
                    <th style={{ paddingBottom: 4, textAlign: 'right' }}>Rate</th>
                    <th style={{ paddingBottom: 4, textAlign: 'right' }}>Amount</th>
                  </tr>
                </thead>
                <tbody>
                  {receipt.items?.map((it, i) => (
                    <tr key={i}>
                      <td style={{ paddingTop: 4 }}>
                        <div style={{ fontWeight: 700 }}>{it.medicine_name}</div>
                        <div style={{ fontSize: '0.68rem', color: '#64748b' }}>{it.medicine_code}</div>
                      </td>
                      <td style={{ paddingTop: 4, fontSize: '0.7rem' }}>{it.batch_number}</td>
                      <td style={{ paddingTop: 4, textAlign: 'center' }}>{it.quantity}</td>
                      <td style={{ paddingTop: 4, textAlign: 'right' }}>₹{it.unit_price.toFixed(2)}</td>
                      <td style={{ paddingTop: 4, textAlign: 'right', fontWeight: 600 }}>₹{it.total_price.toFixed(2)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div style={{ display: 'flex', flexDirection: 'column', gap: 4, fontSize: '0.8rem', textAlign: 'right', marginTop: 8 }}>
              <div>Subtotal: <strong>₹{receipt.subtotal?.toFixed(2)}</strong></div>
              <div>GST (5%): <strong>₹{receipt.tax_amount?.toFixed(2)}</strong></div>
              <div style={{ fontSize: '1rem', fontWeight: 800, color: '#0f172a', borderTop: '1px solid #cbd5e1', paddingTop: 4 }}>
                Net Payable: ₹{receipt.grand_total?.toFixed(2)}
              </div>
            </div>

            <div style={{ borderTop: '1px dashed #94a3b8', marginTop: 14, paddingTop: 8, fontSize: '0.7rem', textAlign: 'center', color: '#475569' }}>
              <div>Dispensed by: <strong>{receipt.pharmacist_name || 'Pharmacist'}</strong></div>
              <div>Keep medicines stored as indicated. Verify seal before use.</div>
              <div style={{ marginTop: 4, fontSize: '0.65rem' }}>*** NexusCare Demonstration Medical Record ***</div>
            </div>
          </div>
        </div>

        <div className="modal-footer">
          <button className="btn btn-secondary" onClick={onClose}>
            Close
          </button>
          <button className="btn btn-teal" onClick={() => window.print()}>
            <Printer size={16} />
            <span>Print Receipt</span>
          </button>
        </div>
      </div>
    </div>
  );
}
