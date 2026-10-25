from typing import List, Optional
from datetime import datetime, date
from pydantic import BaseModel, Field

# --- AUTH & USER ---
class UserLogin(BaseModel):
    username: str
    password: str

class UserCreate(BaseModel):
    username: str
    email: str
    full_name: str
    role: str
    password: str

class UserOut(BaseModel):
    id: int
    username: str
    email: str
    full_name: str
    role: str
    is_active: bool
    created_at: datetime

    class Config:
        from_attributes = True

class Token(BaseModel):
    access_token: str
    token_type: str
    user: UserOut

class TokenData(BaseModel):
    username: Optional[str] = None
    role: Optional[str] = None

# --- SUPPLIER ---
class SupplierBase(BaseModel):
    name: str
    contact_person: Optional[str] = None
    phone: Optional[str] = None
    email: Optional[str] = None
    address: Optional[str] = None
    lead_time_days: int = 3
    status: str = "Active"

class SupplierCreate(SupplierBase):
    pass

class SupplierOut(SupplierBase):
    id: int
    created_at: datetime

    class Config:
        from_attributes = True

# --- MEDICINE ---
class MedicineBase(BaseModel):
    code: str
    brand_name: str
    generic_name: str
    category: str
    strength: str
    dosage_form: str
    manufacturer: str
    unit_of_measure: str = "strip"
    pack_size: str = "10 tablets/strip"
    reorder_level: int = 50
    storage_conditions: str = "Store below 25°C in a dry place"
    requires_prescription: bool = True
    is_active: bool = True

class MedicineCreate(MedicineBase):
    pass

class MedicineUpdate(BaseModel):
    brand_name: Optional[str] = None
    generic_name: Optional[str] = None
    category: Optional[str] = None
    strength: Optional[str] = None
    dosage_form: Optional[str] = None
    manufacturer: Optional[str] = None
    unit_of_measure: Optional[str] = None
    pack_size: Optional[str] = None
    reorder_level: Optional[int] = None
    storage_conditions: Optional[str] = None
    requires_prescription: Optional[bool] = None
    is_active: Optional[bool] = None

class BatchSimpleOut(BaseModel):
    id: int
    batch_number: str
    current_quantity: int
    selling_price: float
    purchase_price: float
    expiry_date: date
    status: str
    storage_location: str
    temperature_celsius: Optional[float] = None

    class Config:
        from_attributes = True

class MedicineOut(MedicineBase):
    id: int
    created_at: datetime
    updated_at: Optional[datetime] = None

    class Config:
        from_attributes = True

class MedicineLedgerItem(BaseModel):
    id: int
    code: str
    brand_name: str
    generic_name: str
    category: str
    strength: str
    dosage_form: str
    manufacturer: str
    unit_of_measure: str
    pack_size: str
    reorder_level: int
    requires_prescription: bool
    is_active: bool
    available_stock: int
    unit_price: float
    stock_health: str  # 'Healthy' (green), 'Low Stock' (amber/red), 'Out of Stock' (red), 'Expiring Soon' (amber), 'Expired' (red)
    nearest_expiry: Optional[date] = None
    batches_count: int
    batches: List[BatchSimpleOut] = []

# --- BATCH ---
class BatchCreate(BaseModel):
    medicine_id: int
    batch_number: str
    supplier_id: Optional[int] = None
    purchase_price: float = Field(..., gt=0)
    selling_price: float = Field(..., gt=0)
    initial_quantity: int = Field(..., gt=0)
    manufacturing_date: date
    expiry_date: date
    storage_location: str = "Main Dispensary Shelf A1"
    temperature_celsius: Optional[float] = None

class BatchOut(BaseModel):
    id: int
    medicine_id: int
    medicine_name: Optional[str] = None
    medicine_code: Optional[str] = None
    batch_number: str
    supplier_id: Optional[int] = None
    supplier_name: Optional[str] = None
    purchase_price: float
    selling_price: float
    initial_quantity: int
    current_quantity: int
    manufacturing_date: date
    expiry_date: date
    storage_location: str
    status: str
    temperature_celsius: Optional[float] = None
    created_at: datetime

    class Config:
        from_attributes = True

class BatchAdjustRequest(BaseModel):
    new_quantity: int = Field(..., ge=0)
    reason: str = Field(..., min_length=3)  # Mandatory adjustment reason
    notes: Optional[str] = None

# --- STOCK TRANSACTIONS ---
class StockTransactionOut(BaseModel):
    id: int
    medicine_id: int
    medicine_name: Optional[str] = None
    medicine_code: Optional[str] = None
    batch_id: Optional[int] = None
    batch_number: Optional[str] = None
    transaction_type: str
    quantity: int
    balance_after: int
    unit_price: float
    reason: Optional[str] = None
    reference_id: Optional[str] = None
    performed_by: str
    notes: Optional[str] = None
    created_at: datetime

    class Config:
        from_attributes = True

# --- DISPENSING & PRESCRIPTIONS ---
class PrescriptionItemCreate(BaseModel):
    medicine_id: int
    dosage_instructions: str
    prescribed_quantity: int

class PrescriptionCreate(BaseModel):
    prescription_number: str
    patient_id: str
    patient_name: str
    patient_age: Optional[int] = None
    patient_gender: Optional[str] = None
    doctor_name: str
    doctor_reg_no: Optional[str] = None
    department: Optional[str] = None
    diagnosis: Optional[str] = None
    notes: Optional[str] = None
    items: List[PrescriptionItemCreate]

class PrescriptionItemOut(BaseModel):
    id: int
    medicine_id: int
    medicine_name: Optional[str] = None
    medicine_code: Optional[str] = None
    dosage_instructions: str
    prescribed_quantity: int
    dispensed_quantity: int
    status: str

    class Config:
        from_attributes = True

class PrescriptionOut(BaseModel):
    id: int
    prescription_number: str
    patient_id: str
    patient_name: str
    patient_age: Optional[int] = None
    patient_gender: Optional[str] = None
    doctor_name: str
    doctor_reg_no: Optional[str] = None
    department: Optional[str] = None
    diagnosis: Optional[str] = None
    status: str
    notes: Optional[str] = None
    created_at: datetime
    items: List[PrescriptionItemOut] = []

    class Config:
        from_attributes = True

class DispenseItemRequest(BaseModel):
    medicine_id: int
    batch_id: Optional[int] = None  # if not specified, system uses FEFO auto-selection
    quantity: int = Field(..., gt=0)

class DispenseRequest(BaseModel):
    prescription_id: Optional[int] = None
    prescription_number: Optional[str] = None
    patient_name: str
    patient_id: Optional[str] = "DEMO-PATIENT"
    doctor_name: Optional[str] = "Dr. Internal Medicine"
    items: List[DispenseItemRequest]
    notes: Optional[str] = None

class DispensedItemReceipt(BaseModel):
    medicine_code: str
    medicine_name: str
    batch_number: str
    quantity: int
    unit_price: float
    total_price: float
    expiry_date: date

class DispenseReceiptOut(BaseModel):
    receipt_number: str
    dispensing_date: datetime
    patient_name: str
    patient_id: str
    doctor_name: Optional[str] = None
    prescription_number: Optional[str] = None
    pharmacist_name: str
    items: List[DispensedItemReceipt]
    subtotal: float
    tax_amount: float
    grand_total: float
    notes: Optional[str] = None

class DispensingRecordOut(BaseModel):
    id: int
    receipt_number: str
    prescription_id: Optional[int] = None
    medicine_id: int
    medicine_name: Optional[str] = None
    medicine_code: Optional[str] = None
    batch_id: int
    batch_number: Optional[str] = None
    quantity: int
    unit_price: float
    total_amount: float
    dispensed_by: str
    patient_name: str
    doctor_name: Optional[str] = None
    notes: Optional[str] = None
    created_at: datetime

    class Config:
        from_attributes = True

# --- ALERTS ---
class AlertOut(BaseModel):
    id: int
    alert_type: str
    severity: str
    medicine_id: Optional[int] = None
    medicine_name: Optional[str] = None
    batch_id: Optional[int] = None
    batch_number: Optional[str] = None
    title: str
    message: str
    is_resolved: bool
    created_at: datetime

    class Config:
        from_attributes = True

# --- DASHBOARD & REPORTS ---
class DashboardSummary(BaseModel):
    total_catalog_items: int
    total_valuation_inr: float
    low_stock_alerts_count: int
    near_expiry_count: int
    out_of_stock_count: int
    active_batches_count: int
    today_dispensed_count: int
    today_revenue_inr: float

class CategoryStockSummary(BaseModel):
    category: str
    item_count: int
    total_units: int
    total_valuation: float

class MonthlyDispenseTrend(BaseModel):
    month: str
    total_dispensed: int
    total_revenue: float
