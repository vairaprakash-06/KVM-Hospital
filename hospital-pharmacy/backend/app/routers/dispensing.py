from typing import List, Optional
from datetime import datetime, timezone
from fastapi import APIRouter, Depends, HTTPException, Query, Request
from sqlalchemy.orm import Session

from app.database import get_db
from app import models, schemas
from app.auth import get_current_user, require_roles
from app.services.pharmacy_service import PharmacyService

router = APIRouter(prefix="/api/dispensing", tags=["Prescription Dispensing"])

@router.get("/prescriptions", response_model=List[schemas.PrescriptionOut])
def list_prescriptions(
    status_filter: Optional[str] = Query(None, alias="status"),
    search: Optional[str] = None,
    db: Session = Depends(get_db)
):
    query = db.query(models.Prescription)
    if status_filter and status_filter != "All":
        query = query.filter(models.Prescription.status == status_filter)
    if search:
        search_term = f"%{search}%"
        query = query.filter(
            (models.Prescription.prescription_number.ilike(search_term)) |
            (models.Prescription.patient_name.ilike(search_term)) |
            (models.Prescription.patient_id.ilike(search_term))
        )
    return query.order_by(models.Prescription.created_at.desc()).all()

@router.get("/prescriptions/{rx_number}", response_model=schemas.PrescriptionOut)
def get_prescription(rx_number: str, db: Session = Depends(get_db)):
    rx = db.query(models.Prescription).filter(models.Prescription.prescription_number == rx_number).first()
    if not rx:
        raise HTTPException(status_code=404, detail="Prescription not found")
    return rx

@router.post("/dispense", response_model=schemas.DispenseReceiptOut)
def dispense_prescription(
    req: schemas.DispenseRequest,
    request: Request,
    db: Session = Depends(get_db),
    current_user: models.User = Depends(require_roles(["Hospital Administrator", "Pharmacist"]))
):
    client_ip = request.client.host if request.client else "127.0.0.1"
    receipt = PharmacyService.execute_dispensing(db, req, current_user, client_ip)
    return receipt

@router.get("/records", response_model=List[schemas.DispensingRecordOut])
def list_dispensing_records(
    search: Optional[str] = None,
    limit: int = 100,
    db: Session = Depends(get_db)
):
    query = db.query(models.DispensingRecord)
    if search:
        search_term = f"%{search}%"
        query = query.filter(
            (models.DispensingRecord.receipt_number.ilike(search_term)) |
            (models.DispensingRecord.patient_name.ilike(search_term))
        )
    records = query.order_by(models.DispensingRecord.created_at.desc()).limit(limit).all()

    output = []
    for r in records:
        output.append(schemas.DispensingRecordOut(
            id=r.id,
            receipt_number=r.receipt_number,
            prescription_id=r.prescription_id,
            medicine_id=r.medicine_id,
            medicine_name=r.medicine.brand_name if r.medicine else None,
            medicine_code=r.medicine.code if r.medicine else None,
            batch_id=r.batch_id,
            batch_number=r.batch.batch_number if r.batch else None,
            quantity=r.quantity,
            unit_price=r.unit_price,
            total_amount=r.total_amount,
            dispensed_by=r.dispensed_by,
            patient_name=r.patient_name,
            doctor_name=r.doctor_name,
            notes=r.notes,
            created_at=r.created_at
        ))
    return output

@router.get("/receipt/{receipt_number}", response_model=schemas.DispenseReceiptOut)
def get_receipt(receipt_number: str, db: Session = Depends(get_db)):
    records = db.query(models.DispensingRecord).filter(models.DispensingRecord.receipt_number == receipt_number).all()
    if not records:
        raise HTTPException(status_code=404, detail="Receipt not found")

    first = records[0]
    items = []
    subtotal = 0.0
    for r in records:
        items.append(schemas.DispensedItemReceipt(
            medicine_code=r.medicine.code if r.medicine else "MED",
            medicine_name=r.medicine.brand_name if r.medicine else "Medicine",
            batch_number=r.batch.batch_number if r.batch else "BATCH",
            quantity=r.quantity,
            unit_price=r.unit_price,
            total_price=r.total_amount,
            expiry_date=r.batch.expiry_date if r.batch else datetime.now(timezone.utc).date()
        ))
        subtotal += r.total_amount

    tax_amount = round(subtotal * 0.05, 2)
    grand_total = round(subtotal + tax_amount, 2)

    return schemas.DispenseReceiptOut(
        receipt_number=first.receipt_number,
        dispensing_date=first.created_at,
        patient_name=first.patient_name,
        patient_id="PAT-HOSPITAL",
        doctor_name=first.doctor_name,
        prescription_number=f"RX-{first.prescription_id}" if first.prescription_id else None,
        pharmacist_name=first.dispensed_by,
        items=items,
        subtotal=subtotal,
        tax_amount=tax_amount,
        grand_total=grand_total,
        notes=first.notes
    )
