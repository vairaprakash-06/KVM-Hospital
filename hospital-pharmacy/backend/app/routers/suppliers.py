from typing import List, Optional
from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session

from app.database import get_db
from app import models, schemas
from app.auth import get_current_user, require_roles

router = APIRouter(prefix="/api/suppliers", tags=["Suppliers"])

@router.get("/", response_model=List[schemas.SupplierOut])
def list_suppliers(db: Session = Depends(get_db)):
    return db.query(models.Supplier).order_by(models.Supplier.name.asc()).all()

@router.post("/", response_model=schemas.SupplierOut)
def create_supplier(
    data: schemas.SupplierCreate,
    db: Session = Depends(get_db),
    current_user: models.User = Depends(require_roles(["Hospital Administrator", "Inventory Staff"]))
):
    existing = db.query(models.Supplier).filter(models.Supplier.name == data.name).first()
    if existing:
        raise HTTPException(status_code=400, detail="Supplier name already exists")

    new_supplier = models.Supplier(**data.dict())
    db.add(new_supplier)
    db.commit()
    db.refresh(new_supplier)
    return new_supplier

@router.put("/{supplier_id}", response_model=schemas.SupplierOut)
def update_supplier(
    supplier_id: int,
    data: schemas.SupplierCreate,
    db: Session = Depends(get_db),
    current_user: models.User = Depends(require_roles(["Hospital Administrator", "Inventory Staff"]))
):
    sup = db.query(models.Supplier).filter(models.Supplier.id == supplier_id).first()
    if not sup:
        raise HTTPException(status_code=404, detail="Supplier not found")

    for k, v in data.dict().items():
        setattr(sup, k, v)

    db.commit()
    db.refresh(sup)
    return sup

@router.post("/{supplier_id}/toggle-status")
def toggle_supplier_status(
    supplier_id: int,
    db: Session = Depends(get_db),
    current_user: models.User = Depends(require_roles(["Hospital Administrator"]))
):
    sup = db.query(models.Supplier).filter(models.Supplier.id == supplier_id).first()
    if not sup:
        raise HTTPException(status_code=404, detail="Supplier not found")

    sup.status = "Inactive" if sup.status == "Active" else "Active"
    db.commit()
    return {"id": sup.id, "name": sup.name, "status": sup.status}
