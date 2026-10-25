import React, { useState, useEffect } from 'react';
import {
  Download,
  BarChart3,
  TrendingUp,
  FileSpreadsheet,
  Printer,
  History,
  Layers,
  IndianRupee
} from 'lucide-react';
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer
} from 'recharts';
import { api } from '../services/api';

export default function Reports() {
  const [categories, setCategories] = useState([]);
  const [history, setHistory] = useState([]);
  const [txType, setTxType] = useState('All');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadReports();
  }, [txType]);

  async function loadReports() {
    try {
      setLoading(true);
      const [catData, histData] = await Promise.all([
        api.getCategoryValuation(),
        api.getMovementHistory({ transaction_type: txType !== 'All' ? txType : undefined })
      ]);
      setCategories(catData);
      setHistory(histData);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  }

  function handleDownloadCSV(type) {
    window.open(`/api/reports/export-csv?type=${type}`, '_blank');
  }

  return (
    <div className="page-content">
      {/* Export Toolbar Card */}
      <div className="content-card" style={{ marginBottom: 20 }}>
        <div className="card-header">
          <div className="card-title">
            <h3>Pharmacy Reports, Valuation & CSV Export Center</h3>
            <p>Export authoritative medical regulatory reports, inventory movements, and stock reconciliations</p>
          </div>
          <div style={{ display: 'flex', gap: 10 }}>
            <button className="btn btn-secondary btn-sm" onClick={() => handleDownloadCSV('inventory')}>
              <Download size={14} />
              <span>Export Inventory CSV</span>
            </button>
            <button className="btn btn-secondary btn-sm" onClick={() => handleDownloadCSV('batches')}>
              <Download size={14} />
              <span>Export Batches CSV</span>
            </button>
            <button className="btn btn-secondary btn-sm" onClick={() => handleDownloadCSV('dispensing')}>
              <Download size={14} />
              <span>Export Dispensing CSV</span>
            </button>
          </div>
        </div>

        {/* Category Breakdown Table */}
        <div style={{ padding: 20 }}>
          <h4 style={{ fontSize: '0.94rem', fontWeight: 700, marginBottom: 12, color: '#0f172a' }}>
            Formulary Valuation by Category
          </h4>
          <div className="table-responsive">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Category</th>
                  <th>Formulary Item Count</th>
                  <th>Total Physical Units</th>
                  <th>Holding Valuation (INR)</th>
                  <th>Avg Unit Sourcing Cost</th>
                </tr>
              </thead>
              <tbody>
                {categories.map((c, i) => (
                  <tr key={i}>
                    <td style={{ fontWeight: 700 }}>{c.category}</td>
                    <td>{c.item_count} items</td>
                    <td style={{ fontWeight: 600 }}>{c.total_units.toLocaleString('en-IN')} units</td>
                    <td style={{ fontWeight: 800, color: '#047857' }}>₹{c.total_valuation.toLocaleString('en-IN')}</td>
                    <td style={{ color: '#475569' }}>
                      ₹{c.total_units > 0 ? (c.total_valuation / c.total_units).toFixed(2) : '0.00'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* Movement Audit History Ledger */}
      <div className="content-card">
        <div className="card-header">
          <div className="card-title">
            <h3>Inventory Movement & Transaction Audit Trail</h3>
            <p>Every inward receipt, prescription dispensing deduction, and physical count adjustment</p>
          </div>

          <select
            className="filter-select"
            value={txType}
            onChange={(e) => setTxType(e.target.value)}
          >
            <option value="All">All Movements</option>
            <option value="STOCK_IN">Inward Stock In</option>
            <option value="DISPENSE">Prescription Dispense</option>
            <option value="ADJUSTMENT">Reconciliation Adjustment</option>
          </select>
        </div>

        <div className="table-responsive">
          <table className="data-table">
            <thead>
              <tr>
                <th>Date / Time</th>
                <th>Type</th>
                <th>Medicine</th>
                <th>Batch</th>
                <th>Quantity</th>
                <th>Balance After</th>
                <th>Reference / Reason</th>
                <th>Performed By</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan="8" style={{ textAlign: 'center', padding: 24, color: '#94a3b8' }}>
                    Loading audit trail...
                  </td>
                </tr>
              ) : history.length === 0 ? (
                <tr>
                  <td colSpan="8" style={{ textAlign: 'center', padding: 24, color: '#94a3b8' }}>
                    No stock transactions recorded.
                  </td>
                </tr>
              ) : (
                history.map((tx) => (
                  <tr key={tx.id}>
                    <td style={{ fontSize: '0.78rem', color: '#64748b' }}>
                      {new Date(tx.created_at).toLocaleString('en-GB', { dateStyle: 'short', timeStyle: 'short' })}
                    </td>
                    <td>
                      <span
                        className="status-badge"
                        style={{
                          background: tx.transaction_type === 'STOCK_IN' ? '#ecfdf5' : tx.transaction_type === 'DISPENSE' ? '#f0f9ff' : '#fffbeb',
                          color: tx.transaction_type === 'STOCK_IN' ? '#047857' : tx.transaction_type === 'DISPENSE' ? '#0284c7' : '#b45309',
                          borderColor: tx.transaction_type === 'STOCK_IN' ? '#a7f3d0' : tx.transaction_type === 'DISPENSE' ? '#bae6fd' : '#fde68a'
                        }}
                      >
                        {tx.transaction_type}
                      </span>
                    </td>
                    <td style={{ fontWeight: 600 }}>{tx.medicine_name}</td>
                    <td><span className="code-pill">{tx.batch_number || 'N/A'}</span></td>
                    <td style={{ fontWeight: 700, color: tx.quantity > 0 ? '#15803d' : '#b91c1c' }}>
                      {tx.quantity > 0 ? `+${tx.quantity}` : tx.quantity}
                    </td>
                    <td style={{ fontWeight: 700 }}>{tx.balance_after}</td>
                    <td>
                      <div style={{ fontSize: '0.8rem', fontWeight: 600 }}>{tx.reason || tx.reference_id}</div>
                      {tx.notes && <div style={{ fontSize: '0.72rem', color: '#64748b' }}>{tx.notes}</div>}
                    </td>
                    <td style={{ fontSize: '0.8rem', color: '#475569' }}>{tx.performed_by}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
