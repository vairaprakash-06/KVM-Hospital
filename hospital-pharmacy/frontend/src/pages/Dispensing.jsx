import React, { useState, useEffect } from 'react';
import {
  FileCheck,
  Printer,
  Clock,
  User,
  CheckCircle2,
  AlertCircle,
  FileText
} from 'lucide-react';
import { api } from '../services/api';

export default function Dispensing({ onOpenDispense, onViewReceipt }) {
  const [prescriptions, setPrescriptions] = useState([]);
  const [records, setRecords] = useState([]);
  const [activeTab, setActiveTab] = useState('prescriptions'); // 'prescriptions' or 'records'
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadData();
  }, []);

  async function loadData() {
    try {
      setLoading(true);
      const [rxList, recList] = await Promise.all([
        api.getPrescriptions(),
        api.getDispensingRecords({ limit: 50 })
      ]);
      setPrescriptions(rxList);
      setRecords(recList);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  }

  async function handlePrintReceipt(receiptNumber) {
    try {
      const receipt = await api.getReceipt(receiptNumber);
      onViewReceipt(receipt);
    } catch (err) {
      alert('Failed to load receipt: ' + err.message);
    }
  }

  return (
    <div className="page-content">
      {/* Tab Navigation header */}
      <div style={{ display: 'flex', gap: 12, marginBottom: 18 }}>
        <button
          className={`btn ${activeTab === 'prescriptions' ? 'btn-teal' : 'btn-secondary'}`}
          onClick={() => setActiveTab('prescriptions')}
        >
          <Clock size={16} />
          <span>Pending Doctor Prescriptions ({prescriptions.filter(p => p.status === 'PENDING').length})</span>
        </button>

        <button
          className={`btn ${activeTab === 'records' ? 'btn-teal' : 'btn-secondary'}`}
          onClick={() => setActiveTab('records')}
        >
          <FileText size={16} />
          <span>Dispensing Audit Ledger ({records.length})</span>
        </button>

        <div style={{ marginLeft: 'auto' }}>
          <button className="btn btn-blue-outline" onClick={onOpenDispense}>
            <FileCheck size={16} />
            <span>+ New Counter Dispense</span>
          </button>
        </div>
      </div>

      {activeTab === 'prescriptions' ? (
        <div className="content-card">
          <div className="card-header">
            <div className="card-title">
              <h3>Inpatient & Outpatient Prescription Queue</h3>
              <p>Prescriptions issued by attending physicians awaiting clinical pharmacist verification</p>
            </div>
          </div>

          <div className="table-responsive">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Prescription #</th>
                  <th>Patient Details</th>
                  <th>Attending Physician</th>
                  <th>Department / Diagnosis</th>
                  <th>Prescribed Medicines</th>
                  <th>Status</th>
                  <th style={{ textAlign: 'right' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {prescriptions.length === 0 ? (
                  <tr>
                    <td colSpan="7" style={{ textAlign: 'center', padding: 32, color: '#94a3b8' }}>
                      No prescriptions in queue.
                    </td>
                  </tr>
                ) : (
                  prescriptions.map((rx) => (
                    <tr key={rx.id} style={{ background: rx.status === 'DISPENSED' ? '#f8fafc' : '#ffffff' }}>
                      <td><span className="code-pill">{rx.prescription_number}</span></td>
                      <td>
                        <div style={{ fontWeight: 700 }}>{rx.patient_name}</div>
                        <div style={{ fontSize: '0.74rem', color: '#64748b' }}>
                          ID: {rx.patient_id} • {rx.patient_age ? `${rx.patient_age}y` : ''} {rx.patient_gender}
                        </div>
                      </td>
                      <td>
                        <div style={{ fontWeight: 600 }}>{rx.doctor_name}</div>
                        <div style={{ fontSize: '0.74rem', color: '#64748b' }}>{rx.doctor_reg_no}</div>
                      </td>
                      <td>
                        <div style={{ fontWeight: 600, color: '#0f172a' }}>{rx.department || 'General'}</div>
                        <div style={{ fontSize: '0.74rem', color: '#64748b' }}>{rx.diagnosis}</div>
                      </td>
                      <td>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                          {rx.items?.map((it, idx) => (
                            <div key={idx} style={{ fontSize: '0.78rem' }}>
                              <span style={{ fontWeight: 700 }}>{it.prescribed_quantity}x</span> {it.medicine_name || 'Medicine'}
                              <span style={{ color: '#64748b', fontSize: '0.72rem', marginLeft: 4 }}>({it.dosage_instructions})</span>
                            </div>
                          ))}
                        </div>
                      </td>
                      <td>
                        <span className={`status-badge ${rx.status === 'DISPENSED' ? 'healthy' : 'low-stock'}`}>
                          {rx.status}
                        </span>
                      </td>
                      <td style={{ textAlign: 'right' }}>
                        {rx.status === 'PENDING' ? (
                          <button
                            className="btn btn-blue-outline btn-sm"
                            onClick={onOpenDispense}
                          >
                            <FileCheck size={14} />
                            <span>Dispense Rx</span>
                          </button>
                        ) : (
                          <span style={{ fontSize: '0.78rem', color: '#16a34a', fontWeight: 600 }}>
                            ✓ Completed
                          </span>
                        )}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      ) : (
        <div className="content-card">
          <div className="card-header">
            <div className="card-title">
              <h3>Dispensing History Ledger</h3>
              <p>Historical audit trail of all dispensed hospital medicines with print receipts</p>
            </div>
          </div>

          <div className="table-responsive">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Receipt #</th>
                  <th>Date & Time</th>
                  <th>Patient Name</th>
                  <th>Medicine</th>
                  <th>Batch Number</th>
                  <th>Dispensed Qty</th>
                  <th>Unit Rate</th>
                  <th>Total (INR)</th>
                  <th>Pharmacist</th>
                  <th style={{ textAlign: 'right' }}>Receipt</th>
                </tr>
              </thead>
              <tbody>
                {records.length === 0 ? (
                  <tr>
                    <td colSpan="10" style={{ textAlign: 'center', padding: 32, color: '#94a3b8' }}>
                      No dispensing transactions recorded yet.
                    </td>
                  </tr>
                ) : (
                  records.map((r) => (
                    <tr key={r.id}>
                      <td><span className="code-pill">{r.receipt_number}</span></td>
                      <td style={{ fontSize: '0.78rem', color: '#64748b' }}>
                        {new Date(r.created_at).toLocaleString('en-GB', { dateStyle: 'short', timeStyle: 'short' })}
                      </td>
                      <td style={{ fontWeight: 600 }}>{r.patient_name}</td>
                      <td>
                        <div style={{ fontWeight: 700 }}>{r.medicine_name}</div>
                        <div style={{ fontSize: '0.72rem', color: '#64748b' }}>{r.medicine_code}</div>
                      </td>
                      <td><span className="code-pill">{r.batch_number}</span></td>
                      <td style={{ fontWeight: 700 }}>{r.quantity}</td>
                      <td>₹{r.unit_price?.toFixed(2)}</td>
                      <td style={{ fontWeight: 800, color: '#047857' }}>₹{r.total_amount?.toFixed(2)}</td>
                      <td style={{ fontSize: '0.8rem', color: '#475569' }}>{r.dispensed_by}</td>
                      <td style={{ textAlign: 'right' }}>
                        <button
                          className="btn btn-secondary btn-sm"
                          onClick={() => handlePrintReceipt(r.receipt_number)}
                          title="Print Receipt"
                        >
                          <Printer size={13} />
                        </button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
}
