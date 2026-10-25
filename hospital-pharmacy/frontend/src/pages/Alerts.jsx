import React, { useState, useEffect } from 'react';
import {
  AlertTriangle,
  ShieldAlert,
  CheckCircle,
  RefreshCw,
  ThermometerSnowflake,
  PackageX,
  Clock
} from 'lucide-react';
import { api } from '../services/api';

export default function Alerts() {
  const [alerts, setAlerts] = useState([]);
  const [filterType, setFilterType] = useState('All');
  const [filterSeverity, setFilterSeverity] = useState('All');
  const [includeResolved, setIncludeResolved] = useState(false);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadAlerts();
  }, [filterType, filterSeverity, includeResolved]);

  async function loadAlerts() {
    try {
      setLoading(true);
      const params = {};
      if (filterType !== 'All') params.alert_type = filterType;
      if (filterSeverity !== 'All') params.severity = filterSeverity;
      if (!includeResolved) params.resolved = false;

      const data = await api.getAlerts(params);
      setAlerts(data);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  }

  async function handleResolve(alertId) {
    try {
      await api.resolveAlert(alertId);
      loadAlerts();
    } catch (err) {
      alert('Failed to resolve alert: ' + err.message);
    }
  }

  async function handleSync() {
    try {
      setLoading(true);
      await api.syncAlerts();
      loadAlerts();
    } catch (err) {
      alert('Sync failed: ' + err.message);
    }
  }

  function getAlertIcon(type) {
    switch (type) {
      case 'TEMP_EXCURSION':
        return <ThermometerSnowflake size={20} color="#2563eb" />;
      case 'EXPIRED':
      case 'OUT_OF_STOCK':
        return <PackageX size={20} color="#dc2626" />;
      case 'NEAR_EXPIRY':
        return <Clock size={20} color="#d97706" />;
      default:
        return <AlertTriangle size={20} color="#ea580c" />;
    }
  }

  return (
    <div className="page-content">
      <div className="content-card">
        <div className="card-header">
          <div className="card-title">
            <h3>Pharmacy Safety & Inventory Intelligence Alerts</h3>
            <p>Calculated live from real-time database batch records, shelf thresholds, and cold-chain monitors</p>
          </div>
          <button className="btn btn-secondary btn-sm" onClick={handleSync} disabled={loading}>
            <RefreshCw size={14} className={loading ? 'animate-spin' : ''} />
            <span>Force Live DB Recalculation</span>
          </button>
        </div>

        {/* Filters */}
        <div className="filter-bar">
          <select
            className="filter-select"
            value={filterType}
            onChange={(e) => setFilterType(e.target.value)}
          >
            <option value="All">All Alert Classifications</option>
            <option value="OUT_OF_STOCK">Out of Stock</option>
            <option value="LOW_STOCK">Low Stock Warning</option>
            <option value="NEAR_EXPIRY">Expiring Soon (&lt; 90 days)</option>
            <option value="EXPIRED">Expired Stock</option>
            <option value="TEMP_EXCURSION">Cold-Chain Temperature Deviation</option>
          </select>

          <select
            className="filter-select"
            value={filterSeverity}
            onChange={(e) => setFilterSeverity(e.target.value)}
          >
            <option value="All">All Severities</option>
            <option value="CRITICAL">Critical</option>
            <option value="WARNING">Warning</option>
            <option value="INFO">Informational</option>
          </select>

          <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginLeft: 'auto' }}>
            <input
              type="checkbox"
              id="chk-resolved"
              checked={includeResolved}
              onChange={(e) => setIncludeResolved(e.target.checked)}
            />
            <label htmlFor="chk-resolved" style={{ fontSize: '0.82rem', color: '#475569', cursor: 'pointer' }}>
              Show Acknowledged / Resolved
            </label>
          </div>
        </div>

        {/* Alert List */}
        <div style={{ padding: 20, display: 'flex', flexDirection: 'column', gap: 12 }}>
          {loading ? (
            <div style={{ textAlign: 'center', padding: 32, color: '#94a3b8' }}>
              Calculating safety alerts against live database records...
            </div>
          ) : alerts.length === 0 ? (
            <div style={{ textAlign: 'center', padding: 32, color: '#15803d' }}>
              <CheckCircle size={36} style={{ margin: '0 auto 8px', color: '#16a34a' }} />
              <div style={{ fontWeight: 700 }}>Zero Active Inventory Alerts</div>
              <p style={{ fontSize: '0.8rem', color: '#64748b' }}>
                All hospital formulary items are within safe stock and expiry parameters.
              </p>
            </div>
          ) : (
            alerts.map((a) => (
              <div
                key={a.id}
                style={{
                  background: a.severity === 'CRITICAL' ? '#fef2f2' : a.severity === 'WARNING' ? '#fffbeb' : '#f0f9ff',
                  border: `1px solid ${a.severity === 'CRITICAL' ? '#fecaca' : a.severity === 'WARNING' ? '#fde68a' : '#bae6fd'}`,
                  borderRadius: 10,
                  padding: 16,
                  display: 'flex',
                  alignItems: 'flex-start',
                  justifyContent: 'space-between',
                  gap: 16,
                  opacity: a.is_resolved ? 0.6 : 1
                }}
              >
                <div style={{ display: 'flex', alignItems: 'flex-start', gap: 12 }}>
                  <div style={{ padding: 6, background: '#ffffff', borderRadius: 8, border: '1px solid #e2e8f0' }}>
                    {getAlertIcon(a.alert_type)}
                  </div>
                  <div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                      <span style={{ fontWeight: 800, color: '#0f172a', fontSize: '0.94rem' }}>
                        {a.title}
                      </span>
                      <span
                        style={{
                          fontSize: '0.68rem',
                          fontWeight: 700,
                          padding: '1px 6px',
                          borderRadius: 4,
                          background: a.severity === 'CRITICAL' ? '#dc2626' : a.severity === 'WARNING' ? '#d97706' : '#0284c7',
                          color: '#ffffff'
                        }}
                      >
                        {a.severity}
                      </span>
                    </div>

                    <p style={{ fontSize: '0.84rem', color: '#334155', marginTop: 4 }}>
                      {a.message}
                    </p>

                    <div style={{ display: 'flex', gap: 16, marginTop: 8, fontSize: '0.74rem', color: '#64748b' }}>
                      {a.medicine_name && <span>Medicine: <strong>{a.medicine_name}</strong></span>}
                      {a.batch_number && <span>Batch: <strong>{a.batch_number}</strong></span>}
                      <span>Logged: {new Date(a.created_at).toLocaleString()}</span>
                    </div>
                  </div>
                </div>

                <div>
                  {!a.is_resolved ? (
                    <button
                      className="btn btn-secondary btn-sm"
                      onClick={() => handleResolve(a.id)}
                      title="Acknowledge and mark resolved"
                    >
                      <CheckCircle size={14} color="#16a34a" />
                      <span>Acknowledge</span>
                    </button>
                  ) : (
                    <span style={{ fontSize: '0.75rem', color: '#16a34a', fontWeight: 600 }}>
                      ✓ Resolved
                    </span>
                  )}
                </div>
              </div>
            ))
          )}
        </div>
      </div>
    </div>
  );
}
