from typing import List, Optional
from datetime import date, timedelta
from fastapi import APIRouter, Depends, HTTPException, Query, Request
from sqlalchemy.orm import Session

from app.database import get_db
from app import models, schemas
from app.auth import get_current_user, require_roles
from app.services.pharmacy_service import PharmacyService

router = APIRouter(prefix="/api/batches", tags=["Stock Batches & Inward Movement"])

@router.get("/", response_model=List[schemas.BatchOut])
def list_batches(
    medicine_id: Optional[int] = None,
    status_filter: Optional[str] = Query(None, alias="status"),
    near_expiry_only: Optional[bool] = False,
    db: Session = Depends(get_db)
):
    query = db.query(models.Batch)

    if medicine_id:
        query = query.filter(models.Batch.medicine_id == medicine_id)

    if status_filter and status_filter != "All":
        query = query.filter(models.Batch.status == status_filter)

    if near_expiry_only:
        today = date.today()
        ninety_days = today + timedelta(days=90)
        query = query.filter(
            models.Batch.current_quantity > 0,
            models.Batch.expiry_date >= today,
            models.Batch.expiry_date <= ninety_days
        )

    batches = query.order_by(models.Batch.expiry_date.asc()).all()

    output = []
    for b in batches:
        output.append(schemas.BatchOut(
            id=b.id,
            medicine_id=b.medicine_id,
            medicine_name=b.medicine.brand_name if b.medicine else None,
            medicine_code=b.medicine.code if b.medicine else None,
            batch_number=b.batch_number,
            supplier_id=b.supplier_id,
            supplier_name=b.supplier.name if b.supplier else "Direct Hospital Sourcing",
            purchase_price=b.purchase_price,
            selling_price=b.selling_price,
            initial_quantity=b.initial_quantity,
            current_quantity=b.current_quantity,
            manufacturing_date=b.manufacturing_date,
            expiry_date=b.expiry_date,
            storage_location=b.storage_location,
            status=b.status,
            temperature_celsius=b.temperature_celsius,
            created_at=b.created_at
        ))
    return output

@router.post("/", response_model=schemas.BatchOut)
def stock_in_batch(
    data: schemas.BatchCreate,
    request: Request,
    db: Session = Depends(get_db),
    current_user: models.User = Depends(require_roles(["Hospital Administrator", "Pharmacist", "Inventory Staff"]))
):
    client_ip = request.client.host if request.client else "127.0.0.1"
    batch = PharmacyService.stock_in_batch(db, data, current_user, client_ip)
    return schemas.BatchOut(
        id=batch.id,
        medicine_id=batch.medicine_id,
        medicine_name=batch.medicine.brand_name if batch.medicine else None,
        medicine_code=batch.medicine.code if batch.medicine else None,
        batch_number=batch.batch_number,
        supplier_id=batch.supplier_id,
        supplier_name=batch.supplier.name if batch.supplier else "Direct Hospital Sourcing",
        purchase_price=batch.purchase_price,
        selling_price=batch.selling_price,
        initial_quantity=batch.initial_quantity,
        current_quantity=batch.current_quantity,
        manufacturing_date=batch.manufacturing_date,
        expiry_date=batch.expiry_date,
        storage_location=batch.storage_location,
        status=batch.status,
        temperature_celsius=batch.temperature_celsius,
        created_at=batch.created_at
    )

@router.post("/{batch_id}/adjust", response_model=schemas.BatchOut)
def adjust_batch(
    batch_id: int,
    req: schemas.BatchAdjustRequest,
    request: Request,
    db: Session = Depends(get_db),
    current_user: models.User = Depends(require_roles(["Hospital Administrator", "Pharmacist", "Inventory Staff"]))
):
    client_ip = request.client.host if request.client else "127.0.0.1"
    batch = PharmacyService.adjust_batch_stock(db, batch_id, req, current_user, client_ip)
    return schemas.BatchOut(
        id=batch.id,
        medicine_id=batch.medicine_id,
        medicine_name=batch.medicine.brand_name if batch.medicine else None,
        medicine_code=batch.medicine.code if batch.medicine else None,
        batch_number=batch.batch_number,
        supplier_id=batch.supplier_id,
        supplier_name=batch.supplier.name if batch.supplier else None,
        purchase_price=batch.purchase_price,
        selling_price=batch.selling_price,
        initial_quantity=batch.initial_quantity,
        current_quantity=batch.current_quantity,
        manufacturing_date=batch.manufacturing_date,
        expiry_date=batch.expiry_date,
        storage_location=batch.storage_location,
        status=batch.status,
        temperature_celsius=batch.temperature_celsius,
        created_at=batch.created_at
    )

@router.post("/{batch_id}/toggle-quarantine")
def toggle_quarantine(
    batch_id: int,
    db: Session = Depends(get_db),
    current_user: models.User = Depends(require_roles(["Hospital Administrator", "Pharmacist"]))
):
    batch = db.query(models.Batch).filter(models.Batch.id == batch_id).first()
    if not batch:
        raise HTTPException(status_code=404, detail="Batch not found")

    if batch.status == "Quarantined":
        batch.status = "Active" if batch.current_quantity > 0 else "Depleted"
    else:
        batch.status = "Quarantined"

    db.commit()
    PharmacyService.sync_live_alerts(db)
    return {"id": batch.id, "batch_number": batch.batch_number, "status": batch.status}
