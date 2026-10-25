import React, { useState, useEffect } from 'react';
import { AuthProvider, useAuth } from './hooks/useAuth';
import Sidebar from './components/Sidebar';
import Navbar from './components/Navbar';
import MedicineModal from './components/MedicineModal';
import StockInModal from './components/StockInModal';
import DispenseModal from './components/DispenseModal';
import AdjustStockModal from './components/AdjustStockModal';
import ReceiptModal from './components/ReceiptModal';

import Dashboard from './pages/Dashboard';
import Catalog from './pages/Catalog';
import Batches from './pages/Batches';
import Dispensing from './pages/Dispensing';
import Suppliers from './pages/Suppliers';
import Alerts from './pages/Alerts';
import Reports from './pages/Reports';
import UsersPage from './pages/Users';

import { api } from './services/api';

function MainApp() {
  const [currentTab, setCurrentTab] = useState('dashboard');
  const [alertCount, setAlertCount] = useState(0);

  // Modals state
  const [isMedModalOpen, setIsMedModalOpen] = useState(false);
  const [editingMedicine, setEditingMedicine] = useState(null);

  const [isStockInOpen, setIsStockInOpen] = useState(false);
  const [stockInMedId, setStockInMedId] = useState(null);

  const [isDispenseOpen, setIsDispenseOpen] = useState(false);
  const [dispenseMedId, setDispenseMedId] = useState(null);

  const [isAdjustOpen, setIsAdjustOpen] = useState(false);
  const [adjustBatch, setAdjustBatch] = useState(null);

  const [isReceiptOpen, setIsReceiptOpen] = useState(false);
  const [receiptData, setReceiptData] = useState(null);

  // Data reload trigger key
  const [refreshKey, setRefreshKey] = useState(0);

  useEffect(() => {
    loadAlertCount();
  }, [refreshKey]);

  async function loadAlertCount() {
    try {
      const activeAlerts = await api.getAlerts({ resolved: false });
      setAlertCount(activeAlerts.length);
    } catch (err) {
      console.warn('Could not load alerts count:', err);
    }
  }

  function handleTriggerRefresh() {
    setRefreshKey((k) => k + 1);
  }

  // Handlers for action triggers
  function handleOpenNewMedicine() {
    setEditingMedicine(null);
    setIsMedModalOpen(true);
  }

  function handleEditMedicine(med) {
    setEditingMedicine(med);
    setIsMedModalOpen(true);
  }

  function handleOpenStockIn(medId = null) {
    setStockInMedId(medId);
    setIsStockInOpen(true);
  }

  function handleOpenDispense(medId = null) {
    setDispenseMedId(medId);
    setIsDispenseOpen(true);
  }

  function handleOpenAdjust(batch) {
    setAdjustBatch(batch);
    setIsAdjustOpen(true);
  }

  function handleDispenseSuccess(receipt) {
    setReceiptData(receipt);
    setIsReceiptOpen(true);
    handleTriggerRefresh();
  }

  async function handleSaveMedicine(formData, medId) {
    if (medId) {
      await api.updateMedicine(medId, formData);
    } else {
      await api.createMedicine(formData);
    }
    handleTriggerRefresh();
  }

  return (
    <div className="app-container">
      {/* Left Sidebar */}
      <Sidebar
        currentTab={currentTab}
        setTab={setCurrentTab}
        alertCount={alertCount}
      />

      {/* Main Viewport */}
      <div className="main-viewport">
        {/* Top Navigation */}
        <Navbar
          onOpenNewMed={handleOpenNewMedicine}
          onOpenStockIn={() => handleOpenStockIn(null)}
          onOpenDispense={() => handleOpenDispense(null)}
          alertCount={alertCount}
          onAlertClick={() => setCurrentTab('alerts')}
        />

        {/* Dynamic Pages */}
        {currentTab === 'dashboard' && (
          <Dashboard
            key={refreshKey}
            onOpenNewMed={handleOpenNewMedicine}
            onOpenStockIn={() => handleOpenStockIn(null)}
            onOpenDispense={() => handleOpenDispense(null)}
            setTab={setCurrentTab}
          />
        )}

        {currentTab === 'catalog' && (
          <Catalog
            key={refreshKey}
            onOpenNewMed={handleOpenNewMedicine}
            onOpenStockInWithMed={(medId) => handleOpenStockIn(medId)}
            onOpenDispenseWithMed={(medId) => handleOpenDispense(medId)}
            onEditMedicine={handleEditMedicine}
          />
        )}

        {currentTab === 'batches' && (
          <Batches
            key={refreshKey}
            onOpenStockIn={() => handleOpenStockIn(null)}
            onOpenAdjustModal={handleOpenAdjust}
          />
        )}

        {currentTab === 'dispensing' && (
          <Dispensing
            key={refreshKey}
            onOpenDispense={() => handleOpenDispense(null)}
            onViewReceipt={(receipt) => {
              setReceiptData(receipt);
              setIsReceiptOpen(true);
            }}
          />
        )}

        {currentTab === 'suppliers' && <Suppliers key={refreshKey} />}

        {currentTab === 'alerts' && <Alerts key={refreshKey} />}

        {currentTab === 'reports' && <Reports key={refreshKey} />}

        {currentTab === 'users' && <UsersPage key={refreshKey} />}
      </div>

      {/* Modals */}
      <MedicineModal
        isOpen={isMedModalOpen}
        onClose={() => setIsMedModalOpen(false)}
        onSave={handleSaveMedicine}
        medicine={editingMedicine}
      />

      <StockInModal
        isOpen={isStockInOpen}
        onClose={() => setIsStockInOpen(false)}
        onStockInSuccess={handleTriggerRefresh}
        preselectedMedicineId={stockInMedId}
      />

      <DispenseModal
        isOpen={isDispenseOpen}
        onClose={() => setIsDispenseOpen(false)}
        onDispenseSuccess={handleDispenseSuccess}
      />

      <AdjustStockModal
        isOpen={isAdjustOpen}
        onClose={() => setIsAdjustOpen(false)}
        batch={adjustBatch}
        onAdjustSuccess={handleTriggerRefresh}
      />

      <ReceiptModal
        isOpen={isReceiptOpen}
        onClose={() => setIsReceiptOpen(false)}
        receipt={receiptData}
      />
    </div>
  );
}

export default function App() {
  return (
    <AuthProvider>
      <MainApp />
    </AuthProvider>
  );
}
