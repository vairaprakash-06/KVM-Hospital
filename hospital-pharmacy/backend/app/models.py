from datetime import datetime, date, timezone
from sqlalchemy import (
    Column, Integer, String, Float, Boolean, DateTime, Date, ForeignKey, Text
)
from sqlalchemy.orm import relationship
from app.database import Base

def now_utc():
    return datetime.now(timezone.utc)

class User(Base):
    __tablename__ = "users"

    id = Column(Integer, primary_key=True, index=True)
    username = Column(String(50), unique=True, index=True, nullable=False)
    email = Column(String(100), unique=True, index=True, nullable=False)
    full_name = Column(String(100), nullable=False)
    role = Column(String(50), nullable=False)  # 'Hospital Administrator', 'Pharmacist', 'Inventory Staff'
    hashed_password = Column(String(255), nullable=False)
    is_active = Column(Boolean, default=True)
    created_at = Column(DateTime, default=now_utc)

class Supplier(Base):
    __tablename__ = "suppliers"

    id = Column(Integer, primary_key=True, index=True)
    name = Column(String(150), nullable=False, index=True)
    contact_person = Column(String(100), nullable=True)
    phone = Column(String(30), nullable=True)
    email = Column(String(100), nullable=True)
    address = Column(String(255), nullable=True)
    lead_time_days = Column(Integer, default=3)
    status = Column(String(20), default="Active")  # 'Active', 'Inactive'
    created_at = Column(DateTime, default=now_utc)

    batches = relationship("Batch", back_populates="supplier")

class Medicine(Base):
    __tablename__ = "medicines"

    id = Column(Integer, primary_key=True, index=True)
    code = Column(String(50), unique=True, index=True, nullable=False)
    brand_name = Column(String(150), nullable=False, index=True)
    generic_name = Column(String(150), nullable=False, index=True)
    category = Column(String(100), nullable=False, index=True)
    strength = Column(String(50), nullable=False)
    dosage_form = Column(String(50), nullable=False)  # Tablet, Syrup, Injection, Inhaler, Ointment, etc.
    manufacturer = Column(String(150), nullable=False)
    unit_of_measure = Column(String(30), default="strip")
    pack_size = Column(String(50), default="10 tablets/strip")
    reorder_level = Column(Integer, default=50)
    storage_conditions = Column(String(100), default="Store below 25°C in a dry place")
    requires_prescription = Column(Boolean, default=True)
    is_active = Column(Boolean, default=True)
    created_at = Column(DateTime, default=now_utc)
    updated_at = Column(DateTime, default=now_utc, onupdate=now_utc)

    batches = relationship("Batch", back_populates="medicine", cascade="all, delete-orphan")
    transactions = relationship("StockTransaction", back_populates="medicine")
    prescription_items = relationship("PrescriptionItem", back_populates="medicine")
    dispensing_records = relationship("DispensingRecord", back_populates="medicine")
    alerts = relationship("Alert", back_populates="medicine")

class Batch(Base):
    __tablename__ = "batches"

    id = Column(Integer, primary_key=True, index=True)
    medicine_id = Column(Integer, ForeignKey("medicines.id", ondelete="CASCADE"), nullable=False, index=True)
    batch_number = Column(String(50), nullable=False, index=True)
    supplier_id = Column(Integer, ForeignKey("suppliers.id"), nullable=True)
    purchase_price = Column(Float, nullable=False)
    selling_price = Column(Float, nullable=False)
    initial_quantity = Column(Integer, nullable=False)
    current_quantity = Column(Integer, nullable=False, default=0)
    manufacturing_date = Column(Date, nullable=False)
    expiry_date = Column(Date, nullable=False, index=True)
    storage_location = Column(String(100), default="Main Dispensary Shelf A1")
    status = Column(String(30), default="Active")  # 'Active', 'Quarantined', 'Depleted', 'Expired'
    temperature_celsius = Column(Float, nullable=True)  # For cold-chain monitoring (e.g. 2-8°C)
    created_at = Column(DateTime, default=now_utc)
    updated_at = Column(DateTime, default=now_utc, onupdate=now_utc)

    medicine = relationship("Medicine", back_populates="batches")
    supplier = relationship("Supplier", back_populates="batches")
    transactions = relationship("StockTransaction", back_populates="batch")
    dispensing_records = relationship("DispensingRecord", back_populates="batch")
    alerts = relationship("Alert", back_populates="batch")

class StockTransaction(Base):
    __tablename__ = "stock_transactions"

    id = Column(Integer, primary_key=True, index=True)
    medicine_id = Column(Integer, ForeignKey("medicines.id"), nullable=False, index=True)
    batch_id = Column(Integer, ForeignKey("batches.id"), nullable=True, index=True)
    transaction_type = Column(String(30), nullable=False)  # 'STOCK_IN', 'DISPENSE', 'ADJUSTMENT', 'RETURN', 'DISCARD'
    quantity = Column(Integer, nullable=False)  # positive for addition, negative for deduction
    balance_after = Column(Integer, nullable=False)
    unit_price = Column(Float, default=0.0)
    reason = Column(String(100), nullable=True)  # E.g. "Physical Inventory Discrepancy", "Routine Dispense"
    reference_id = Column(String(100), nullable=True)  # RX-1234, PO-5678, ADJ-901
    performed_by = Column(String(100), nullable=False)
    notes = Column(Text, nullable=True)
    created_at = Column(DateTime, default=now_utc, index=True)

    medicine = relationship("Medicine", back_populates="transactions")
    batch = relationship("Batch", back_populates="transactions")

class Prescription(Base):
    __tablename__ = "prescriptions"

    id = Column(Integer, primary_key=True, index=True)
    prescription_number = Column(String(50), unique=True, index=True, nullable=False)
    patient_id = Column(String(50), nullable=False, index=True)
    patient_name = Column(String(100), nullable=False)
    patient_age = Column(Integer, nullable=True)
    patient_gender = Column(String(20), nullable=True)
    doctor_name = Column(String(100), nullable=False)
    doctor_reg_no = Column(String(50), nullable=True)
    department = Column(String(100), nullable=True)
    diagnosis = Column(String(255), nullable=True)
    status = Column(String(30), default="PENDING")  # 'PENDING', 'DISPENSED', 'PARTIALLY_DISPENSED', 'CANCELLED'
    notes = Column(Text, nullable=True)
    created_at = Column(DateTime, default=now_utc, index=True)

    items = relationship("PrescriptionItem", back_populates="prescription", cascade="all, delete-orphan")
    dispensing_records = relationship("DispensingRecord", back_populates="prescription")

class PrescriptionItem(Base):
    __tablename__ = "prescription_items"

    id = Column(Integer, primary_key=True, index=True)
    prescription_id = Column(Integer, ForeignKey("prescriptions.id", ondelete="CASCADE"), nullable=False)
    medicine_id = Column(Integer, ForeignKey("medicines.id"), nullable=False)
    dosage_instructions = Column(String(150), nullable=False)  # "1 tablet TDS after meals x 5 days"
    prescribed_quantity = Column(Integer, nullable=False)
    dispensed_quantity = Column(Integer, default=0)
    status = Column(String(30), default="PENDING")

    prescription = relationship("Prescription", back_populates="items")
    medicine = relationship("Medicine", back_populates="prescription_items")

class DispensingRecord(Base):
    __tablename__ = "dispensing_records"

    id = Column(Integer, primary_key=True, index=True)
    receipt_number = Column(String(50), unique=True, index=True, nullable=False)
    prescription_id = Column(Integer, ForeignKey("prescriptions.id"), nullable=True)
    medicine_id = Column(Integer, ForeignKey("medicines.id"), nullable=False)
    batch_id = Column(Integer, ForeignKey("batches.id"), nullable=False)
    quantity = Column(Integer, nullable=False)
    unit_price = Column(Float, nullable=False)
    total_amount = Column(Float, nullable=False)
    dispensed_by = Column(String(100), nullable=False)
    patient_name = Column(String(100), nullable=False)
    doctor_name = Column(String(100), nullable=True)
    notes = Column(Text, nullable=True)
    created_at = Column(DateTime, default=now_utc, index=True)

    prescription = relationship("Prescription", back_populates="dispensing_records")
    medicine = relationship("Medicine", back_populates="dispensing_records")
    batch = relationship("Batch", back_populates="dispensing_records")

class Alert(Base):
    __tablename__ = "alerts"

    id = Column(Integer, primary_key=True, index=True)
    alert_type = Column(String(50), nullable=False, index=True)  # 'LOW_STOCK', 'OUT_OF_STOCK', 'NEAR_EXPIRY', 'EXPIRED', 'TEMP_EXCURSION'
    severity = Column(String(20), default="WARNING")  # 'INFO', 'WARNING', 'CRITICAL'
    medicine_id = Column(Integer, ForeignKey("medicines.id"), nullable=True)
    batch_id = Column(Integer, ForeignKey("batches.id"), nullable=True)
    title = Column(String(200), nullable=False)
    message = Column(Text, nullable=False)
    is_resolved = Column(Boolean, default=False, index=True)
    created_at = Column(DateTime, default=now_utc, index=True)

    medicine = relationship("Medicine", back_populates="alerts")
    batch = relationship("Batch", back_populates="alerts")

class AuditLog(Base):
    __tablename__ = "audit_logs"

    id = Column(Integer, primary_key=True, index=True)
    user_id = Column(String(100), nullable=False)
    action = Column(String(50), nullable=False, index=True)  # 'CREATE_MEDICINE', 'STOCK_IN', 'DISPENSE', 'ADJUST_STOCK', 'DEACTIVATE'
    entity_name = Column(String(50), nullable=False)
    entity_id = Column(String(50), nullable=True)
    old_values = Column(Text, nullable=True)
    new_values = Column(Text, nullable=True)
    ip_address = Column(String(50), nullable=True)
    created_at = Column(DateTime, default=now_utc, index=True)
