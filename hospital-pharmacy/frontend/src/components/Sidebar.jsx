import React from 'react';
import {
  LayoutDashboard,
  Pill,
  Layers,
  FileText,
  Truck,
  AlertTriangle,
  BarChart3,
  Users,
  ShieldCheck,
  LogOut,
  Hospital
} from 'lucide-react';
import { useAuth } from '../hooks/useAuth';

export default function Sidebar({ currentTab, setTab, alertCount = 0 }) {
  const { user, loginDemo, logout } = useAuth();

  const navSections = [
    {
      title: 'Command Center',
      items: [
        { id: 'dashboard', label: 'Dashboard', icon: LayoutDashboard }
      ]
    },
    {
      title: 'Pharmacy Operations',
      items: [
        { id: 'catalog', label: 'Medicine Catalog', icon: Pill },
        { id: 'batches', label: 'Stock Batches', icon: Layers },
        { id: 'dispensing', label: 'Dispense Rx', icon: FileText }
      ]
    },
    {
      title: 'Inventory Intelligence',
      items: [
        { id: 'alerts', label: 'Safety Alerts', icon: AlertTriangle, badge: alertCount },
        { id: 'reports', label: 'Reports & Analytics', icon: BarChart3 },
        { id: 'suppliers', label: 'Suppliers', icon: Truck }
      ]
    },
    {
      title: 'Administration',
      items: [
        { id: 'users', label: 'Staff & Roles', icon: Users }
      ]
    }
  ];

  return (
    <aside className="app-sidebar">
      {/* Brand Header */}
      <div className="sidebar-header">
        <div className="sidebar-brand-icon">
          <Hospital size={22} />
        </div>
        <div className="sidebar-brand-text">
          <h1>
            NexusCare <span className="version-tag">OS</span>
          </h1>
          <p>KVM Pharmacy Intelligence</p>
        </div>
      </div>

      {/* Nav List */}
      <nav className="sidebar-nav">
        {navSections.map((sec, idx) => (
          <div key={idx}>
            <div className="nav-section-title">{sec.title}</div>
            <ul className="nav-links">
              {sec.items.map((item) => {
                const Icon = item.icon;
                const isActive = currentTab === item.id;
                return (
                  <li key={item.id}>
                    <button
                      className={`nav-item-btn ${isActive ? 'active' : ''}`}
                      onClick={() => setTab(item.id)}
                    >
                      <div className="nav-left">
                        <Icon size={18} />
                        <span>{item.label}</span>
                      </div>
                      {item.badge > 0 && (
                        <span className="nav-badge">{item.badge}</span>
                      )}
                    </button>
                  </li>
                );
              })}
            </ul>
          </div>
        ))}
      </nav>

      {/* Quick Demo Switcher & User Footer */}
      <div className="sidebar-footer">
        <div style={{ marginBottom: 12 }}>
          <div style={{ fontSize: '0.68rem', color: '#64748b', fontWeight: 700, textTransform: 'uppercase', marginBottom: 6 }}>
            ⚡ Fast Demo Switcher
          </div>
          <div style={{ display: 'flex', gap: 4 }}>
            <button
              onClick={() => loginDemo('admin')}
              className="btn btn-sm"
              style={{
                flex: 1,
                fontSize: '0.68rem',
                padding: '4px 6px',
                background: user?.role === 'Hospital Administrator' ? '#0ea5e9' : '#1e293b',
                color: '#fff',
                border: 'none'
              }}
              title="Super Admin / Hospital Admin"
            >
              Admin
            </button>
            <button
              onClick={() => loginDemo('pharmacist')}
              className="btn btn-sm"
              style={{
                flex: 1,
                fontSize: '0.68rem',
                padding: '4px 6px',
                background: user?.role === 'Pharmacist' ? '#0d9488' : '#1e293b',
                color: '#fff',
                border: 'none'
              }}
              title="Clinical Pharmacist"
            >
              Pharm
            </button>
            <button
              onClick={() => loginDemo('inventory')}
              className="btn btn-sm"
              style={{
                flex: 1,
                fontSize: '0.68rem',
                padding: '4px 6px',
                background: user?.role === 'Inventory Staff' ? '#d97706' : '#1e293b',
                color: '#fff',
                border: 'none'
              }}
              title="Inventory Staff"
            >
              Store
            </button>
          </div>
        </div>

        <div className="user-mini-card">
          <div className="user-avatar-circle">
            {user?.full_name ? user.full_name[0] : 'U'}
          </div>
          <div className="user-info-text">
            <div className="user-name">{user?.full_name || 'Pharmacist'}</div>
            <div className="user-role-badge">{user?.role || 'Staff'}</div>
          </div>
          <button
            onClick={logout}
            style={{ background: 'transparent', border: 'none', color: '#64748b', cursor: 'pointer' }}
            title="Sign out"
          >
            <LogOut size={16} />
          </button>
        </div>
      </div>
    </aside>
  );
}
