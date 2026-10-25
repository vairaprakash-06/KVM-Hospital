from typing import List, Optional
from fastapi import APIRouter, Depends, HTTPException, Query
from sqlalchemy.orm import Session

from app.database import get_db
from app import models, schemas
from app.auth import get_current_user, require_roles
from app.services.pharmacy_service import PharmacyService

router = APIRouter(prefix="/api/alerts", tags=["Inventory Alerts & Warnings"])

@router.get("/", response_model=List[schemas.AlertOut])
def list_alerts(
    alert_type: Optional[str] = None,
    severity: Optional[str] = None,
    resolved: Optional[bool] = False,
    db: Session = Depends(get_db)
):
    # Ensure alerts are fresh against live database state
    PharmacyService.sync_live_alerts(db)

    query = db.query(models.Alert)
    if resolved is not None:
        query = query.filter(models.Alert.is_resolved == resolved)
    if alert_type and alert_type != "All":
        query = query.filter(models.Alert.alert_type == alert_type)
    if severity and severity != "All":
        query = query.filter(models.Alert.severity == severity)

    alerts = query.order_by(models.Alert.created_at.desc()).all()

    output = []
    for a in alerts:
        output.append(schemas.AlertOut(
            id=a.id,
            alert_type=a.alert_type,
            severity=a.severity,
            medicine_id=a.medicine_id,
            medicine_name=a.medicine.brand_name if a.medicine else None,
            batch_id=a.batch_id,
            batch_number=a.batch.batch_number if a.batch else None,
            title=a.title,
            message=a.message,
            is_resolved=a.is_resolved,
            created_at=a.created_at
        ))
    return output

@router.post("/{alert_id}/resolve")
def resolve_alert(
    alert_id: int,
    db: Session = Depends(get_db),
    current_user: models.User = Depends(require_roles(["Hospital Administrator", "Pharmacist"]))
):
    alert = db.query(models.Alert).filter(models.Alert.id == alert_id).first()
    if not alert:
        raise HTTPException(status_code=404, detail="Alert not found")

    alert.is_resolved = True
    db.commit()
    return {"message": "Alert marked as acknowledged / resolved", "id": alert_id}

@router.post("/sync")
def force_sync(db: Session = Depends(get_db)):
    PharmacyService.sync_live_alerts(db)
    return {"message": "Alerts synchronized with live inventory state"}
