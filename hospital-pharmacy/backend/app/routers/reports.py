from typing import List, Optional
import io
import csv
from datetime import datetime, date, timedelta
from fastapi import APIRouter, Depends, Query, Response
from sqlalchemy.orm import Session
from sqlalchemy import func

from app.database import get_db
from app import models, schemas
from app.services.pharmacy_service import PharmacyService

router = APIRouter(prefix="/api/reports", tags=["Reports & Intelligence Analytics"])

@router.get("/dashboard-summary", response_model=schemas.DashboardSummary)
def get_dashboard_summary(db: Session = Depends(get_db)):
    today = date.today()
    ninety_days = today + timedelta(days=90)

    # 1. Total Catalog items
    total_catalog = db.query(models.Medicine).filter(models.Medicine.is_active == True).count()

    # 2. Total Inventory Valuation (sum of current_quantity * purchase_price for all non-depleted batches)
    batches = db.query(models.Batch).filter(models.Batch.current_quantity > 0).all()
    total_valuation = sum(b.current_quantity * b.purchase_price for b in batches)

    # 3. Low stock and out of stock
    medicines = db.query(models.Medicine).filter(models.Medicine.is_active == True).all()
    low_stock_count = 0
    out_of_stock_count = 0

    for m in medicines:
        stock = sum(
            b.current_quantity for b in db.query(models.Batch).filter(
                models.Batch.medicine_id == m.id,
                models.Batch.status == "Active"
            ).all()
        )
        if stock == 0:
            out_of_stock_count += 1
        elif stock <= m.reorder_level:
            low_stock_count += 1

    # 4. Batches near expiry (current_quantity > 0, expiry between today and 90 days)
    near_expiry_count = db.query(models.Batch).filter(
        models.Batch.current_quantity > 0,
        models.Batch.expiry_date >= today,
        models.Batch.expiry_date <= ninety_days
    ).count()

    # 5. Active batches
    active_batches_count = db.query(models.Batch).filter(
        models.Batch.status == "Active",
        models.Batch.current_quantity > 0
    ).count()

    # 6. Today dispensing stats
    today_start = datetime.combine(today, datetime.min.time())
    today_records = db.query(models.DispensingRecord).filter(
        models.DispensingRecord.created_at >= today_start
    ).all()
    today_dispensed_count = sum(r.quantity for r in today_records)
    today_revenue_inr = sum(r.total_amount for r in today_records)

    return schemas.DashboardSummary(
        total_catalog_items=total_catalog,
        total_valuation_inr=round(total_valuation, 2),
        low_stock_alerts_count=low_stock_count,
        near_expiry_count=near_expiry_count,
        out_of_stock_count=out_of_stock_count,
        active_batches_count=active_batches_count,
        today_dispensed_count=today_dispensed_count,
        today_revenue_inr=round(today_revenue_inr, 2)
    )

@router.get("/category-valuation", response_model=List[schemas.CategoryStockSummary])
def get_category_valuation(db: Session = Depends(get_db)):
    categories = db.query(models.Medicine.category).distinct().all()
    result = []

    for cat_tuple in categories:
        cat_name = cat_tuple[0]
        meds = db.query(models.Medicine).filter(models.Medicine.category == cat_name).all()
        med_ids = [m.id for m in meds]

        cat_batches = db.query(models.Batch).filter(
            models.Batch.medicine_id.in_(med_ids),
            models.Batch.current_quantity > 0
        ).all()

        total_units = sum(b.current_quantity for b in cat_batches)
        total_val = sum(b.current_quantity * b.purchase_price for b in cat_batches)

        result.append(schemas.CategoryStockSummary(
            category=cat_name,
            item_count=len(meds),
            total_units=total_units,
            total_valuation=round(total_val, 2)
        ))

    result.sort(key=lambda x: x.total_valuation, reverse=True)
    return result

@router.get("/movement-history", response_model=List[schemas.StockTransactionOut])
def get_stock_movement_history(
    transaction_type: Optional[str] = None,
    medicine_id: Optional[int] = None,
    limit: int = 100,
    db: Session = Depends(get_db)
):
    query = db.query(models.StockTransaction)
    if transaction_type and transaction_type != "All":
        query = query.filter(models.StockTransaction.transaction_type == transaction_type)
    if medicine_id:
        query = query.filter(models.StockTransaction.medicine_id == medicine_id)

    txs = query.order_by(models.StockTransaction.created_at.desc()).limit(limit).all()

    output = []
    for t in txs:
        output.append(schemas.StockTransactionOut(
            id=t.id,
            medicine_id=t.medicine_id,
            medicine_name=t.medicine.brand_name if t.medicine else None,
            medicine_code=t.medicine.code if t.medicine else None,
            batch_id=t.batch_id,
            batch_number=t.batch.batch_number if t.batch else None,
            transaction_type=t.transaction_type,
            quantity=t.quantity,
            balance_after=t.balance_after,
            unit_price=t.unit_price,
            reason=t.reason,
            reference_id=t.reference_id,
            performed_by=t.performed_by,
            notes=t.notes,
            created_at=t.created_at
        ))
    return output

@router.get("/dispensing-trends")
def get_dispensing_trends(db: Session = Depends(get_db)):
    # Returns 7-day or monthly activity trends for chart
    today = date.today()
    trend_data = []

    for i in range(6, -1, -1):
        day = today - timedelta(days=i)
        day_start = datetime.combine(day, datetime.min.time())
        day_end = datetime.combine(day, datetime.max.time())

        records = db.query(models.DispensingRecord).filter(
            models.DispensingRecord.created_at >= day_start,
            models.DispensingRecord.created_at <= day_end
        ).all()

        dispensed_qty = sum(r.quantity for r in records)
        revenue = sum(r.total_amount for r in records)

        trend_data.append({
            "date": day.strftime("%d %b"),
            "dispensed_units": dispensed_qty,
            "revenue_inr": round(revenue, 2),
            "prescriptions_count": len(records)
        })

    return trend_data

@router.get("/export-csv")
def export_csv(type: str = Query("inventory"), db: Session = Depends(get_db)):
    output = io.StringIO()
    writer = csv.writer(output)

    if type == "inventory":
        writer.writerow(["Code", "Brand Name", "Generic Name", "Category", "Dosage Form", "Available Units", "Reorder Level", "Unit Price (INR)", "Status"])
        meds = db.query(models.Medicine).all()
        for m in meds:
            batches = db.query(models.Batch).filter(models.Batch.medicine_id == m.id, models.Batch.status == "Active").all()
            stock = sum(b.current_quantity for b in batches)
            price = batches[0].selling_price if batches else 0.0
            health = PharmacyService.get_stock_health(stock, m.reorder_level, None)
            writer.writerow([m.code, m.brand_name, m.generic_name, m.category, f"{m.dosage_form} {m.strength}", stock, m.reorder_level, f"₹{price}", health])
        filename = f"nexuscare_inventory_{date.today().strftime('%Y%m%d')}.csv"

    elif type == "batches":
        writer.writerow(["Batch Number", "Medicine Code", "Brand Name", "Supplier", "Purchase Price", "Selling Price", "Current Qty", "Mfg Date", "Expiry Date", "Location", "Status"])
        batches = db.query(models.Batch).all()
        for b in batches:
            writer.writerow([b.batch_number, b.medicine.code if b.medicine else "", b.medicine.brand_name if b.medicine else "", b.supplier.name if b.supplier else "", b.purchase_price, b.selling_price, b.current_quantity, b.manufacturing_date, b.expiry_date, b.storage_location, b.status])
        filename = f"nexuscare_batches_{date.today().strftime('%Y%m%d')}.csv"

    elif type == "dispensing":
        writer.writerow(["Receipt Number", "Date", "Patient Name", "Medicine", "Batch", "Quantity", "Unit Price", "Total (INR)", "Dispensed By"])
        records = db.query(models.DispensingRecord).order_by(models.DispensingRecord.created_at.desc()).all()
        for r in records:
            writer.writerow([r.receipt_number, r.created_at.strftime("%Y-%m-%d %H:%M"), r.patient_name, r.medicine.brand_name if r.medicine else "", r.batch.batch_number if r.batch else "", r.quantity, r.unit_price, r.total_amount, r.dispensed_by])
        filename = f"nexuscare_dispensing_{date.today().strftime('%Y%m%d')}.csv"

    output.seek(0)
    return Response(
        content=output.getvalue(),
        media_type="text/csv",
        headers={"Content-Disposition": f"attachment; filename={filename}"}
    )
