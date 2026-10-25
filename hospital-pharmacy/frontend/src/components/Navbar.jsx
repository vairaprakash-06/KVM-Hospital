import React from 'react';
import { Plus, PackagePlus, FileCheck, AlertTriangle, Shield } from 'lucide-react';
import { useAuth } from '../hooks/useAuth';

export default function Navbar({ onOpenNewMed, onOpenStockIn, onOpenDispense, alertCount = 0, onAlertClick }) {
  const { user } = useAuth();

  return (
    <header className="app-topbar">
      <div className="topbar-left">
        <h2>Pharmacy Inventory & Dispensing Management</h2>
        <p>Stock batch tracking, expiry monitoring, safe dispensing, and inventory reporting.</p>
      </div>

      <div className="topbar-actions">
        {/* Alerts Pill */}
        {alertCount > 0 && (
          <button
            onClick={onAlertClick}
            className="btn btn-secondary btn-sm"
            style={{ color: '#b91c1c', borderColor: '#fca5a5', background: '#fef2f2' }}
            title="Active Inventory Alerts"
          >
            <AlertTriangle size={15} />
            <span>{alertCount} Warning{alertCount > 1 ? 's' : ''}</span>
          </button>
        )}

        {/* Action Buttons as requested */}
        <button
          className="btn btn-blue-outline"
          onClick={onOpenDispense}
          id="btn-dispense-rx"
        >
          <FileCheck size={16} />
          <span>Dispense Prescription</span>
        </button>

        <button
          className="btn btn-teal"
          onClick={onOpenStockIn}
          id="btn-stock-in"
          style={{ background: '#0f766e' }}
        >
          <PackagePlus size={16} />
          <span>Stock In Batch</span>
        </button>

        <button
          className="btn btn-teal"
          onClick={onOpenNewMed}
          id="btn-new-medicine"
        >
          <Plus size={16} />
          <span>+ New Medicine</span>
        </button>
      </div>
    </header>
  );
}
