from typing import List, Optional
from datetime import date
from fastapi import APIRouter, Depends, HTTPException, Query, status
from sqlalchemy.orm import Session
from sqlalchemy import or_

from app.database import get_db
from app import models, schemas
from app.auth import get_current_user, require_roles
from app.services.pharmacy_service import PharmacyService

router = APIRouter(prefix="/api/medicines", tags=["Medicines Catalog & Ledger"])

@router.get("/categories/all", response_model=List[str])
def get_categories(db: Session = Depends(get_db)):
    results = db.query(models.Medicine.category).distinct().all()
    return [r[0] for r in results if r[0]]

@router.get("/manufacturers/all", response_model=List[str])
def get_manufacturers(db: Session = Depends(get_db)):
    results = db.query(models.Medicine.manufacturer).distinct().all()
    return [r[0] for r in results if r[0]]

@router.get("/", response_model=List[schemas.MedicineLedgerItem])
def list_medicines_ledger(
    search: Optional[str] = None,
    category: Optional[str] = None,
    manufacturer: Optional[str] = None,
    status_filter: Optional[str] = Query(None, alias="status"),
    db: Session = Depends(get_db)
):
    query = db.query(models.Medicine)

    if search:
        search_term = f"%{search}%"
        query = query.filter(
            or_(
                models.Medicine.brand_name.ilike(search_term),
                models.Medicine.generic_name.ilike(search_term),
                models.Medicine.code.ilike(search_term)
            )
        )

    if category and category != "All":
        query = query.filter(models.Medicine.category == category)

    if manufacturer and manufacturer != "All":
        query = query.filter(models.Medicine.manufacturer == manufacturer)

    medicines = query.order_by(models.Medicine.brand_name.asc()).all()

    today = date.today()
    ledger_items = []

    for med in medicines:
        # Fetch batches
        all_batches = db.query(models.Batch).filter(models.Batch.medicine_id == med.id).all()
        active_batches = [b for b in all_batches if b.status == "Active" and b.current_quantity > 0]
        
        available_stock = sum(b.current_quantity for b in active_batches)

        # Nearest expiry from non-depleted batches
        non_depleted = [b for b in all_batches if b.current_quantity > 0]
        nearest_expiry = min([b.expiry_date for b in non_depleted], default=None)

        # Base unit selling price: from first active batch or fallback
        unit_price = active_batches[0].selling_price if active_batches else (all_batches[0].selling_price if all_batches else 0.0)

        stock_health = PharmacyService.get_stock_health(available_stock, med.reorder_level, nearest_expiry)

        if status_filter and status_filter != "All":
            if status_filter.lower() != stock_health.lower():
                continue

        ledger_items.append(schemas.MedicineLedgerItem(
            id=med.id,
            code=med.code,
            brand_name=med.brand_name,
            generic_name=med.generic_name,
            category=med.category,
            strength=med.strength,
            dosage_form=med.dosage_form,
            manufacturer=med.manufacturer,
            unit_of_measure=med.unit_of_measure,
            pack_size=med.pack_size,
            reorder_level=med.reorder_level,
            requires_prescription=med.requires_prescription,
            is_active=med.is_active,
            available_stock=available_stock,
            unit_price=unit_price,
            stock_health=stock_health,
            nearest_expiry=nearest_expiry,
            batches_count=len(all_batches),
            batches=[
                schemas.BatchSimpleOut(
                    id=b.id,
                    batch_number=b.batch_number,
                    current_quantity=b.current_quantity,
                    selling_price=b.selling_price,
                    purchase_price=b.purchase_price,
                    expiry_date=b.expiry_date,
                    status=b.status,
                    storage_location=b.storage_location,
                    temperature_celsius=b.temperature_celsius
                ) for b in all_batches
            ]
        ))

    return ledger_items

@router.get("/{medicine_id}", response_model=schemas.MedicineOut)
def get_medicine(medicine_id: int, db: Session = Depends(get_db)):
    med = db.query(models.Medicine).filter(models.Medicine.id == medicine_id).first()
    if not med:
        raise HTTPException(status_code=404, detail="Medicine not found")
    return med

@router.post("/", response_model=schemas.MedicineOut)
def create_medicine(
    data: schemas.MedicineCreate,
    db: Session = Depends(get_db),
    current_user: models.User = Depends(require_roles(["Hospital Administrator", "Pharmacist"]))
):
    existing = db.query(models.Medicine).filter(models.Medicine.code == data.code).first()
    if existing:
        raise HTTPException(status_code=400, detail=f"Medicine code '{data.code}' already exists")

    new_med = models.Medicine(**data.dict())
    db.add(new_med)
    db.commit()
    db.refresh(new_med)

    PharmacyService.sync_live_alerts(db)
    return new_med

@router.put("/{medicine_id}", response_model=schemas.MedicineOut)
def update_medicine(
    medicine_id: int,
    data: schemas.MedicineUpdate,
    db: Session = Depends(get_db),
    current_user: models.User = Depends(require_roles(["Hospital Administrator", "Pharmacist"]))
):
    med = db.query(models.Medicine).filter(models.Medicine.id == medicine_id).first()
    if not med:
        raise HTTPException(status_code=404, detail="Medicine not found")

    update_data = data.dict(exclude_unset=True)
    for field, val in update_data.items():
        setattr(med, field, val)

    db.commit()
    db.refresh(med)
    PharmacyService.sync_live_alerts(db)
    return med

@router.post("/{medicine_id}/toggle-active")
def toggle_medicine_active(
    medicine_id: int,
    db: Session = Depends(get_db),
    current_user: models.User = Depends(require_roles(["Hospital Administrator", "Pharmacist"]))
):
    med = db.query(models.Medicine).filter(models.Medicine.id == medicine_id).first()
    if not med:
        raise HTTPException(status_code=404, detail="Medicine not found")

    med.is_active = not med.is_active
    db.commit()
    return {"id": med.id, "brand_name": med.brand_name, "is_active": med.is_active}

@router.delete("/{medicine_id}")
def delete_medicine(
    medicine_id: int,
    db: Session = Depends(get_db),
    current_user: models.User = Depends(require_roles(["Hospital Administrator"]))
):
    med = db.query(models.Medicine).filter(models.Medicine.id == medicine_id).first()
    if not med:
        raise HTTPException(status_code=404, detail="Medicine not found")

    # Safety constraint: Do not delete medicines that have transaction or batch history
    has_transactions = db.query(models.StockTransaction).filter(models.StockTransaction.medicine_id == medicine_id).first()
    has_batches = db.query(models.Batch).filter(models.Batch.medicine_id == medicine_id).first()

    if has_transactions or has_batches:
        raise HTTPException(
            status_code=400,
            detail="Cannot delete medicine with active inventory or historical transactions. Deactivate the medicine instead to maintain audit integrity."
        )

    db.delete(med)
    db.commit()
    return {"message": f"Medicine {med.brand_name} removed"}
