import json
from datetime import datetime, date, timedelta, timezone
from typing import List, Dict, Any, Optional
from sqlalchemy.orm import Session
from sqlalchemy import func
from fastapi import HTTPException, status

from app import models, schemas

class PharmacyService:

    @staticmethod
    def get_stock_health(available_stock: int, reorder_level: int, nearest_expiry: Optional[date]) -> str:
        today = date.today()
        if available_stock <= 0:
            return "Out of Stock"
        if nearest_expiry and nearest_expiry < today:
            return "Expired"
        if nearest_expiry and (nearest_expiry - today).days <= 90:
            return "Expiring Soon"
        if available_stock <= reorder_level:
            return "Low Stock"
        return "Healthy"

    @staticmethod
    def sync_live_alerts(db: Session):
        """Calculates alerts directly from live database state and persists active notices."""
        today = date.today()
        ninety_days_ahead = today + timedelta(days=90)

        # 1. Low stock & Out of stock
        medicines = db.query(models.Medicine).filter(models.Medicine.is_active == True).all()
        for med in medicines:
            # Calculate live active non-expired stock
            active_batches = db.query(models.Batch).filter(
                models.Batch.medicine_id == med.id,
                models.Batch.status == "Active",
                models.Batch.current_quantity > 0
            ).all()
            total_stock = sum(b.current_quantity for b in active_batches)

            if total_stock == 0:
                # Out of stock alert
                existing = db.query(models.Alert).filter(
                    models.Alert.medicine_id == med.id,
                    models.Alert.alert_type == "OUT_OF_STOCK",
                    models.Alert.is_resolved == False
                ).first()
                if not existing:
                    db.add(models.Alert(
                        alert_type="OUT_OF_STOCK",
                        severity="CRITICAL",
                        medicine_id=med.id,
                        title=f"Out of Stock: {med.brand_name} ({med.code})",
                        message=f"Current stock is 0 {med.unit_of_measure}. Formulary item requires immediate replenishment."
                    ))
            elif total_stock <= med.reorder_level:
                # Low stock alert
                existing = db.query(models.Alert).filter(
                    models.Alert.medicine_id == med.id,
                    models.Alert.alert_type == "LOW_STOCK",
                    models.Alert.is_resolved == False
                ).first()
                if not existing:
                    db.add(models.Alert(
                        alert_type="LOW_STOCK",
                        severity="WARNING",
                        medicine_id=med.id,
                        title=f"Low Stock Warning: {med.brand_name}",
                        message=f"Available stock ({total_stock} {med.unit_of_measure}) is at or below reorder threshold ({med.reorder_level})."
                    ))

        # 2. Expiry alerts for batches with inventory
        batches = db.query(models.Batch).filter(models.Batch.current_quantity > 0).all()
        for batch in batches:
            if batch.expiry_date < today:
                # Mark batch expired if not already
                if batch.status != "Expired":
                    batch.status = "Expired"
                existing = db.query(models.Alert).filter(
                    models.Alert.batch_id == batch.id,
                    models.Alert.alert_type == "EXPIRED",
                    models.Alert.is_resolved == False
                ).first()
                if not existing:
                    db.add(models.Alert(
                        alert_type="EXPIRED",
                        severity="CRITICAL",
                        medicine_id=batch.medicine_id,
                        batch_id=batch.id,
                        title=f"Expired Batch: {batch.batch_number} ({batch.medicine.brand_name if batch.medicine else 'Medicine'})",
                        message=f"Batch {batch.batch_number} expired on {batch.expiry_date.strftime('%d-%b-%Y')}. Quarantine and discard immediately."
                    ))
            elif batch.expiry_date <= ninety_days_ahead:
                days_left = (batch.expiry_date - today).days
                existing = db.query(models.Alert).filter(
                    models.Alert.batch_id == batch.id,
                    models.Alert.alert_type == "NEAR_EXPIRY",
                    models.Alert.is_resolved == False
                ).first()
                if not existing:
                    db.add(models.Alert(
                        alert_type="NEAR_EXPIRY",
                        severity="WARNING",
                        medicine_id=batch.medicine_id,
                        batch_id=batch.id,
                        title=f"Near Expiry: Batch {batch.batch_number} ({days_left} days left)",
                        message=f"Batch {batch.batch_number} of {batch.medicine.brand_name if batch.medicine else 'Medicine'} expires on {batch.expiry_date.strftime('%d-%b-%Y')}."
                    ))

            # 3. Cold chain temperature excursion
            if batch.temperature_celsius is not None:
                if batch.temperature_celsius < 2.0 or batch.temperature_celsius > 8.0:
                    existing = db.query(models.Alert).filter(
                        models.Alert.batch_id == batch.id,
                        models.Alert.alert_type == "TEMP_EXCURSION",
                        models.Alert.is_resolved == False
                    ).first()
                    if not existing:
                        db.add(models.Alert(
                            alert_type="TEMP_EXCURSION",
                            severity="CRITICAL",
                            medicine_id=batch.medicine_id,
                            batch_id=batch.id,
                            title=f"Cold-Chain Deviation: Batch {batch.batch_number} ({batch.temperature_celsius}°C)",
                            message=f"Monitored cold storage temperature is {batch.temperature_celsius}°C (Required: 2.0°C - 8.0°C). Requires immediate pharmacist inspection."
                        ))

        db.commit()

    @staticmethod
    def get_fefo_batches(db: Session, medicine_id: int, required_qty: int) -> List[Dict[str, Any]]:
        """Returns batches ordered by First-Expiry-First-Out, skipping expired or quarantined batches."""
        today = date.today()
        batches = db.query(models.Batch).filter(
            models.Batch.medicine_id == medicine_id,
            models.Batch.status == "Active",
            models.Batch.current_quantity > 0,
            models.Batch.expiry_date >= today
        ).order_by(models.Batch.expiry_date.asc(), models.Batch.id.asc()).all()

        total_available = sum(b.current_quantity for b in batches)
        if total_available < required_qty:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail=f"Insufficient non-expired active stock. Requested: {required_qty}, Available: {total_available}"
            )

        allocations = []
        remaining = required_qty
        for b in batches:
            if remaining <= 0:
                break
            take = min(b.current_quantity, remaining)
            allocations.append({
                "batch": b,
                "quantity": take
            })
            remaining -= take

        return allocations

    @staticmethod
    def execute_dispensing(
        db: Session,
        req: schemas.DispenseRequest,
        current_user: models.User,
        client_ip: Optional[str] = "127.0.0.1"
    ) -> schemas.DispenseReceiptOut:
        """Atomic prescription dispensing workflow with strict FEFO / batch verification."""
        today = date.today()
        receipt_num = f"RCP-{datetime.now(timezone.utc).strftime('%Y%m%d')}-{db.query(models.DispensingRecord).count() + 101}"

        # 1. Authorization check: Prescription required medicines must have prescription details or pharmacist authorization
        dispensed_items_receipt = []
        subtotal = 0.0

        for item in req.items:
            med = db.query(models.Medicine).filter(models.Medicine.id == item.medicine_id).first()
            if not med or not med.is_active:
                raise HTTPException(status_code=400, detail=f"Medicine ID {item.medicine_id} not found or inactive")

            if med.requires_prescription and not req.prescription_number and not req.prescription_id:
                # Pharmacist can override with explicit clinical notes
                if current_user.role not in ["Hospital Administrator", "Pharmacist"]:
                    raise HTTPException(
                        status_code=403,
                        detail=f"{med.brand_name} is Prescription-Only (Schedule H/X). Requires valid prescription number or Pharmacist override."
                    )

            # Determine batch allocation
            if item.batch_id:
                # Specific batch requested
                batch = db.query(models.Batch).filter(models.Batch.id == item.batch_id).first()
                if not batch:
                    raise HTTPException(status_code=404, detail=f"Batch {item.batch_id} not found")
                if batch.status == "Quarantined":
                    raise HTTPException(status_code=400, detail=f"Cannot dispense from Quarantined batch {batch.batch_number}")
                if batch.status == "Expired" or batch.expiry_date < today:
                    raise HTTPException(status_code=400, detail=f"Cannot dispense from Expired batch {batch.batch_number} (Expired {batch.expiry_date})")
                if batch.current_quantity < item.quantity:
                    raise HTTPException(status_code=400, detail=f"Batch {batch.batch_number} has only {batch.current_quantity} available, requested {item.quantity}")

                allocations = [{"batch": batch, "quantity": item.quantity}]
            else:
                # FEFO selection
                allocations = PharmacyService.get_fefo_batches(db, med.id, item.quantity)

            # Deduct stock and log transactions atomically
            for alloc in allocations:
                b = alloc["batch"]
                qty = alloc["quantity"]

                # Safety check against negative stock
                if b.current_quantity < qty:
                    raise HTTPException(status_code=400, detail=f"Stock deduction violation: Batch {b.batch_number} insufficient")

                b.current_quantity -= qty
                if b.current_quantity == 0:
                    b.status = "Depleted"

                # Calculate medicine balance after
                med_total_stock = sum(
                    x.current_quantity for x in db.query(models.Batch).filter(
                        models.Batch.medicine_id == med.id,
                        models.Batch.status == "Active"
                    ).all()
                )

                line_total = round(qty * b.selling_price, 2)
                subtotal += line_total

                # Record stock movement transaction
                st = models.StockTransaction(
                    medicine_id=med.id,
                    batch_id=b.id,
                    transaction_type="DISPENSE",
                    quantity=-qty,
                    balance_after=med_total_stock,
                    unit_price=b.selling_price,
                    reason="Prescription Dispensed",
                    reference_id=req.prescription_number or receipt_num,
                    performed_by=current_user.full_name,
                    notes=req.notes or f"Dispensed to {req.patient_name}"
                )
                db.add(st)

                # Record dispensing row
                dr = models.DispensingRecord(
                    receipt_number=receipt_num,
                    prescription_id=req.prescription_id,
                    medicine_id=med.id,
                    batch_id=b.id,
                    quantity=qty,
                    unit_price=b.selling_price,
                    total_amount=line_total,
                    dispensed_by=current_user.full_name,
                    patient_name=req.patient_name,
                    doctor_name=req.doctor_name,
                    notes=req.notes
                )
                db.add(dr)

                dispensed_items_receipt.append(schemas.DispensedItemReceipt(
                    medicine_code=med.code,
                    medicine_name=med.brand_name,
                    batch_number=b.batch_number,
                    quantity=qty,
                    unit_price=b.selling_price,
                    total_price=line_total,
                    expiry_date=b.expiry_date
                ))

        # If a prescription is linked, update its status
        if req.prescription_id:
            prescription = db.query(models.Prescription).filter(models.Prescription.id == req.prescription_id).first()
            if prescription:
                prescription.status = "DISPENSED"

        # Log audit trail
        audit = models.AuditLog(
            user_id=current_user.username,
            action="DISPENSE",
            entity_name="DispensingRecord",
            entity_id=receipt_num,
            new_values=json.dumps({
                "patient": req.patient_name,
                "items_count": len(dispensed_items_receipt),
                "total": subtotal
            }),
            ip_address=client_ip
        )
        db.add(audit)

        db.commit()

        # Re-sync alerts in background
        PharmacyService.sync_live_alerts(db)

        tax_amount = round(subtotal * 0.05, 2)  # 5% GST for medicines
        grand_total = round(subtotal + tax_amount, 2)

        return schemas.DispenseReceiptOut(
            receipt_number=receipt_num,
            dispensing_date=datetime.now(timezone.utc),
            patient_name=req.patient_name,
            patient_id=req.patient_id or "DEMO-PATIENT",
            doctor_name=req.doctor_name,
            prescription_number=req.prescription_number,
            pharmacist_name=current_user.full_name,
            items=dispensed_items_receipt,
            subtotal=subtotal,
            tax_amount=tax_amount,
            grand_total=grand_total,
            notes=req.notes
        )

    @staticmethod
    def stock_in_batch(
        db: Session,
        data: schemas.BatchCreate,
        current_user: models.User,
        client_ip: Optional[str] = "127.0.0.1"
    ) -> models.Batch:
        """Receive new stock batch, update medicine ledger, and create transaction trail."""
        med = db.query(models.Medicine).filter(models.Medicine.id == data.medicine_id).first()
        if not med:
            raise HTTPException(status_code=404, detail="Medicine not found")

        # Check for duplicate batch number for same medicine
        existing = db.query(models.Batch).filter(
            models.Batch.medicine_id == data.medicine_id,
            models.Batch.batch_number == data.batch_number
        ).first()
        if existing:
            raise HTTPException(
                status_code=400,
                detail=f"Batch number '{data.batch_number}' already exists for this medicine. Use stock adjustment instead."
            )

        status_batch = "Active"
        today = date.today()
        if data.expiry_date < today:
            status_batch = "Expired"

        new_batch = models.Batch(
            medicine_id=data.medicine_id,
            batch_number=data.batch_number,
            supplier_id=data.supplier_id,
            purchase_price=data.purchase_price,
            selling_price=data.selling_price,
            initial_quantity=data.initial_quantity,
            current_quantity=data.initial_quantity,
            manufacturing_date=data.manufacturing_date,
            expiry_date=data.expiry_date,
            storage_location=data.storage_location,
            status=status_batch,
            temperature_celsius=data.temperature_celsius
        )
        db.add(new_batch)
        db.flush()

        # Calculate new total medicine stock
        total_med_stock = sum(
            b.current_quantity for b in db.query(models.Batch).filter(
                models.Batch.medicine_id == med.id,
                models.Batch.status == "Active"
            ).all()
        )

        # Log stock transaction
        st = models.StockTransaction(
            medicine_id=med.id,
            batch_id=new_batch.id,
            transaction_type="STOCK_IN",
            quantity=data.initial_quantity,
            balance_after=total_med_stock,
            unit_price=data.purchase_price,
            reason="Supplier Inward Batch Receipt",
            reference_id=f"BATCH-{new_batch.batch_number}",
            performed_by=current_user.full_name,
            notes=f"Received into {data.storage_location}"
        )
        db.add(st)

        # Audit log
        audit = models.AuditLog(
            user_id=current_user.username,
            action="STOCK_IN",
            entity_name="Batch",
            entity_id=str(new_batch.id),
            new_values=json.dumps({
                "batch_number": data.batch_number,
                "quantity": data.initial_quantity,
                "expiry_date": str(data.expiry_date)
            }),
            ip_address=client_ip
        )
        db.add(audit)

        db.commit()
        db.refresh(new_batch)

        PharmacyService.sync_live_alerts(db)
        return new_batch

    @staticmethod
    def adjust_batch_stock(
        db: Session,
        batch_id: int,
        req: schemas.BatchAdjustRequest,
        current_user: models.User,
        client_ip: Optional[str] = "127.0.0.1"
    ) -> models.Batch:
        """Physical inventory reconciliation with mandatory reason."""
        batch = db.query(models.Batch).filter(models.Batch.id == batch_id).first()
        if not batch:
            raise HTTPException(status_code=404, detail="Batch not found")

        old_qty = batch.current_quantity
        diff = req.new_quantity - old_qty
        if diff == 0:
            return batch

        batch.current_quantity = req.new_quantity
        if batch.current_quantity == 0 and batch.status == "Active":
            batch.status = "Depleted"
        elif batch.current_quantity > 0 and batch.status == "Depleted":
            batch.status = "Active"

        med = batch.medicine
        total_med_stock = sum(
            b.current_quantity for b in db.query(models.Batch).filter(
                models.Batch.medicine_id == med.id,
                models.Batch.status == "Active"
            ).all()
        )

        st = models.StockTransaction(
            medicine_id=med.id,
            batch_id=batch.id,
            transaction_type="ADJUSTMENT",
            quantity=diff,
            balance_after=total_med_stock,
            unit_price=batch.purchase_price,
            reason=req.reason,
            reference_id=f"ADJ-{batch.batch_number}",
            performed_by=current_user.full_name,
            notes=req.notes or f"Manual count adjustment ({old_qty} -> {req.new_quantity})"
        )
        db.add(st)

        audit = models.AuditLog(
            user_id=current_user.username,
            action="ADJUST_STOCK",
            entity_name="Batch",
            entity_id=str(batch.id),
            old_values=json.dumps({"current_quantity": old_qty}),
            new_values=json.dumps({"current_quantity": req.new_quantity, "reason": req.reason}),
            ip_address=client_ip
        )
        db.add(audit)

        db.commit()
        db.refresh(batch)

        PharmacyService.sync_live_alerts(db)
        return batch
