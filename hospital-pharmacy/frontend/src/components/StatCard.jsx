import React from 'react';

export default function StatCard({ label, value, subtext, icon: Icon, color = 'teal', onClick }) {
  return (
    <div className="kpi-card" onClick={onClick} style={{ cursor: onClick ? 'pointer' : 'default' }}>
      <div className={`kpi-icon-wrap kpi-icon-${color}`}>
        <Icon size={24} />
      </div>
      <div className="kpi-info">
        <div className="kpi-label">{label}</div>
        <div className="kpi-value">{value}</div>
        {subtext && <div className="kpi-subtext">{subtext}</div>}
      </div>
    </div>
  );
}
