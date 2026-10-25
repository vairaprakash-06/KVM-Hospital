import React, { useState, useEffect } from 'react';
import {
  Pill,
  IndianRupee,
  AlertTriangle,
  Clock,
  Layers,
  FileCheck,
  TrendingUp,
  PackagePlus,
  ArrowUpRight,
  ShieldAlert,
  ThermometerSnowflake
} from 'lucide-react';
import {
  AreaChart,
  Area,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer
} from 'recharts';
import StatCard from '../components/StatCard';
import { api } from '../services/api';

export default function Dashboard({ onOpenNewMed, onOpenStockIn, onOpenDispense, setTab }) {
  const [summary, setSummary] = useState(null);
  const [trends, setTrends] = useState([]);
  const [categories, setCategories] = useState([]);
  const [alerts, setAlerts] = useState([]);
  const [recentRecords, setRecentRecords] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadDashboardData();
  }, []);

  async function loadDashboardData() {
    try {
      setLoading(true);
      const [sumData, trendData, catData, alertData, recData] = await Promise.all([
        api.getDashboardSummary(),
        api.getDispensingTrends(),
        api.getCategoryValuation(),
        api.getAlerts({ resolved: false }),
        api.getDispensingRecords({ limit: 6 })
      ]);
      setSummary(sumData);
      setTrends(trendData);
      setCategories(catData.slice(0, 6));
      setAlerts(alertData.slice(0, 4));
      setRecentRecords(recData);
    } catch (err) {
      console.error('Error loading dashboard:', err);
    } finally {
      setLoading(false);
    }
  }

  function formatCurrency(val) {
    if (!val && val !== 0) return '₹ 0.00';
    return new Intl.NumberFormat('en-IN', {
      style: 'currency',
      currency: 'INR',
      maximumFractionDigits: 0
    }).format(val);
  }

  return (
    <div className="page-content">
      {/* Critical Excursion Banner if temperature or expired alert */}
      {alerts.some(a => a.alert_type === 'TEMP_EXCURSION') && (
        <div style={{ background: '#eff6ff', border: '1px solid #bfdbfe', borderRadius: 10, padding: '14px 18px', marginBottom: 20, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
            <div style={{ background: '#dbeafe', padding: 8, borderRadius: 8, color: '#1d4ed8' }}>
              <ThermometerSnowflake size={22} />
            </div>
            <div>
              <div style={{ fontWeight: 700, color: '#1e3a8a', fontSize: '0.92rem' }}>
                Cold-Chain Temperature Deviation Detected
              </div>
              <div style={{ fontSize: '0.78rem', color: '#3b82f6' }}>
                One or more refrigerated batches (Insulin/Biologicals) require pharmacist temperature reconciliation.
              </div>
            </div>
          </div>
          <button className="btn btn-secondary btn-sm" onClick={() => setTab('alerts')}>
            Inspect Cold Storage
          </button>
        </div>
      )}

      {/* KPI Cards Grid */}
      <div className="kpi-cards-grid">
        <StatCard
          label="Total Catalog Items"
          value={summary?.total_catalog_items ?? 0}
          subtext="Active hospital formulary items"
          icon={Pill}
          color="teal"
          onClick={() => setTab('catalog')}
        />
        <StatCard
          label="Total Inventory Valuation"
          value={formatCurrency(summary?.total_valuation_inr ?? 0)}
          subtext="Current batch purchase value"
          icon={IndianRupee}
          color="green"
          onClick={() => setTab('reports')}
        />
        <StatCard
          label="Low Stock Alerts"
          value={summary?.low_stock_alerts_count ?? 0}
          subtext={`${summary?.out_of_stock_count ?? 0} items completely out of stock`}
          icon={AlertTriangle}
          color={summary?.low_stock_alerts_count > 0 ? 'red' : 'teal'}
          onClick={() => setTab('alerts')}
        />
        <StatCard
          label="Batches Near Expiry"
          value={summary?.near_expiry_count ?? 0}
          subtext="Expiring within 90 days"
          icon={Clock}
          color={summary?.near_expiry_count > 0 ? 'amber' : 'teal'}
          onClick={() => setTab('batches')}
        />
      </div>

      {/* Charts Row */}
      <div style={{ display: 'grid', gridTemplateColumns: '1.6fr 1.2fr', gap: 20, marginBottom: 24 }}>
        {/* Trend Area Chart */}
        <div className="content-card" style={{ marginBottom: 0 }}>
          <div className="card-header">
            <div className="card-title">
              <h3>7-Day Dispensing Trend</h3>
              <p>Daily volume of patient prescription units dispensed</p>
            </div>
            <div style={{ fontSize: '0.78rem', fontWeight: 600, color: '#0d9488', background: '#f0fdfa', padding: '3px 8px', borderRadius: 4 }}>
              Active Pharmacy Flow
            </div>
          </div>
          <div style={{ padding: '20px 20px 10px', height: 260 }}>
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={trends}>
                <defs>
                  <linearGradient id="dispenseGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#0ea5e9" stopOpacity={0.4} />
                    <stop offset="95%" stopColor="#0ea5e9" stopOpacity={0.0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" vertical={false} />
                <XAxis dataKey="date" stroke="#94a3b8" fontSize={11} tickLine={false} />
                <YAxis stroke="#94a3b8" fontSize={11} tickLine={false} />
                <Tooltip
                  formatter={(val, name) => [name === 'revenue_inr' ? `₹${val}` : `${val} units`, name === 'revenue_inr' ? 'Revenue' : 'Units']}
                  contentStyle={{ borderRadius: 8, border: '1px solid #e2e8f0', fontSize: 12 }}
                />
                <Area type="monotone" dataKey="dispensed_units" stroke="#0ea5e9" strokeWidth={2.5} fillOpacity={1} fill="url(#dispenseGrad)" />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </div>

        {/* Category Valuation Bar Chart */}
        <div className="content-card" style={{ marginBottom: 0 }}>
          <div className="card-header">
            <div className="card-title">
              <h3>Inventory Valuation by Category</h3>
              <p>Formulary holding value distribution (INR)</p>
            </div>
          </div>
          <div style={{ padding: '20px 20px 10px', height: 260 }}>
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={categories} layout="vertical">
                <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" horizontal={false} />
                <XAxis type="number" stroke="#94a3b8" fontSize={10} tickFormatter={(v) => `₹${v/1000}k`} />
                <YAxis dataKey="category" type="category" stroke="#64748b" fontSize={10} width={90} tickLine={false} />
                <Tooltip
                  formatter={(v) => [`₹${v.toLocaleString('en-IN')}`, 'Valuation']}
                  contentStyle={{ borderRadius: 8, border: '1px solid #e2e8f0', fontSize: 12 }}
                />
                <Bar dataKey="total_valuation" fill="#0d9488" radius={[0, 4, 4, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>
      </div>

      {/* Two Column Section: Action Shortcuts & Recent Dispensing */}
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20 }}>
        {/* Quick Station Hub */}
        <div className="content-card">
          <div className="card-header">
            <div className="card-title">
              <h3>Operational Quick Hub</h3>
              <p>Direct triggers for routine pharmacy tasks</p>
            </div>
          </div>
          <div style={{ padding: 20, display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
            <div
              onClick={onOpenDispense}
              style={{ background: '#f0f9ff', border: '1px solid #bae6fd', borderRadius: 8, padding: 14, cursor: 'pointer', transition: 'all 0.15s ease' }}
            >
              <div style={{ display: 'flex', alignItems: 'center', gap: 8, color: '#0284c7', fontWeight: 700, fontSize: '0.88rem' }}>
                <FileCheck size={18} />
                <span>Dispense Rx</span>
              </div>
              <p style={{ fontSize: '0.74rem', color: '#475569', marginTop: 4 }}>
                FEFO-checked counter dispensing and thermal receipt printing.
              </p>
            </div>

            <div
              onClick={onOpenStockIn}
              style={{ background: '#f0fdfa', border: '1px solid #99f6e4', borderRadius: 8, padding: 14, cursor: 'pointer' }}
            >
              <div style={{ display: 'flex', alignItems: 'center', gap: 8, color: '#0d9488', fontWeight: 700, fontSize: '0.88rem' }}>
                <PackagePlus size={18} />
                <span>Stock In Batch</span>
              </div>
              <p style={{ fontSize: '0.74rem', color: '#475569', marginTop: 4 }}>
                Inward delivery receipt from registered distributors.
              </p>
            </div>

            <div
              onClick={onOpenNewMed}
              style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 8, padding: 14, cursor: 'pointer' }}
            >
              <div style={{ display: 'flex', alignItems: 'center', gap: 8, color: '#0f172a', fontWeight: 700, fontSize: '0.88rem' }}>
                <Pill size={18} />
                <span>+ New Medicine</span>
              </div>
              <p style={{ fontSize: '0.74rem', color: '#475569', marginTop: 4 }}>
                Register new chemical compound or dosage form.
              </p>
            </div>

            <div
              onClick={() => setTab('batches')}
              style={{ background: '#fffbeb', border: '1px solid #fde68a', borderRadius: 8, padding: 14, cursor: 'pointer' }}
            >
              <div style={{ display: 'flex', alignItems: 'center', gap: 8, color: '#b45309', fontWeight: 700, fontSize: '0.88rem' }}>
                <Clock size={18} />
                <span>Expiry Review</span>
              </div>
              <p style={{ fontSize: '0.74rem', color: '#475569', marginTop: 4 }}>
                Review batches approaching 90-day threshold.
              </p>
            </div>
          </div>
        </div>

        {/* Recent Dispensing Ledger */}
        <div className="content-card">
          <div className="card-header">
            <div className="card-title">
              <h3>Recent Dispensing Ledger</h3>
              <p>Real-time audit trail of filled orders</p>
            </div>
            <button className="btn btn-secondary btn-sm" onClick={() => setTab('dispensing')}>
              View All
            </button>
          </div>
          <div className="table-responsive">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Receipt</th>
                  <th>Patient</th>
                  <th>Medicine</th>
                  <th>Qty</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                {recentRecords.length === 0 ? (
                  <tr>
                    <td colSpan="5" style={{ textAlign: 'center', padding: 24, color: '#94a3b8' }}>
                      No recent dispensing records found.
                    </td>
                  </tr>
                ) : (
                  recentRecords.map((r) => (
                    <tr key={r.id}>
                      <td><span className="code-pill">{r.receipt_number}</span></td>
                      <td style={{ fontWeight: 600 }}>{r.patient_name}</td>
                      <td>{r.medicine_name}</td>
                      <td style={{ fontWeight: 700 }}>{r.quantity}</td>
                      <td style={{ fontWeight: 700, color: '#047857' }}>₹{r.total_amount?.toFixed(2)}</td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}
