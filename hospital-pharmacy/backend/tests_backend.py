import unittest
from datetime import date, timedelta
from app.database import SessionLocal
from app import models, schemas
from app.services.pharmacy_service import PharmacyService
from app.auth import verify_password, get_password_hash, create_access_token

class TestPharmacyBackend(unittest.TestCase):

    def setUp(self):
        self.db = SessionLocal()

    def tearDown(self):
        self.db.close()

    def test_01_users_and_passwords(self):
        admin = self.db.query(models.User).filter(models.User.username == "admin").first()
        self.assertIsNotNone(admin)
        self.assertEqual(admin.role, "Hospital Administrator")
        self.assertTrue(verify_password("password123", admin.hashed_password))

        pharm = self.db.query(models.User).filter(models.User.username == "pharmacist").first()
        self.assertIsNotNone(pharm)
        self.assertEqual(pharm.role, "Pharmacist")

    def test_02_catalog_count(self):
        count = self.db.query(models.Medicine).count()
        self.assertGreaterEqual(count, 30, "Database should contain at least 30 hospital formulary medicines")

    def test_03_fefo_batch_selection(self):
        med = self.db.query(models.Medicine).filter(models.Medicine.code == "AMX-500").first()
        self.assertIsNotNone(med)
        allocations = PharmacyService.get_fefo_batches(self.db, med.id, 20)
        self.assertTrue(len(allocations) > 0)
        # Verify the earliest expiring batch was picked first
        first_batch = allocations[0]["batch"]
        self.assertEqual(first_batch.batch_number, "BN-NEAR-AMX-500")

    def test_04_block_expired_dispensing(self):
        # Batch BN-EXP-CET-010 is expired
        exp_batch = self.db.query(models.Batch).filter(models.Batch.batch_number == "BN-EXP-CET-010").first()
        self.assertIsNotNone(exp_batch)

        admin = self.db.query(models.User).filter(models.User.username == "pharmacist").first()
        req = schemas.DispenseRequest(
            patient_name="Test Patient",
            items=[schemas.DispenseItemRequest(medicine_id=exp_batch.medicine_id, batch_id=exp_batch.id, quantity=1)]
        )

        with self.assertRaises(Exception) as ctx:
            PharmacyService.execute_dispensing(self.db, req, admin)
        self.assertIn("Expired", str(ctx.exception))

    def test_05_block_negative_stock(self):
        med = self.db.query(models.Medicine).first()
        admin = self.db.query(models.User).filter(models.User.username == "pharmacist").first()
        req = schemas.DispenseRequest(
            patient_name="Test Patient",
            items=[schemas.DispenseItemRequest(medicine_id=med.id, quantity=999999)]
        )

        with self.assertRaises(Exception) as ctx:
            PharmacyService.execute_dispensing(self.db, req, admin)
        self.assertIn("Insufficient", str(ctx.exception))

    def test_06_dispense_and_stock_deduction(self):
        med = self.db.query(models.Medicine).filter(models.Medicine.code == "PCM-500").first()
        batch = self.db.query(models.Batch).filter(models.Batch.medicine_id == med.id, models.Batch.status == "Active").first()
        initial_qty = batch.current_quantity

        pharm = self.db.query(models.User).filter(models.User.username == "pharmacist").first()
        req = schemas.DispenseRequest(
            prescription_number="TEST-RX-999",
            patient_name="Vigneshwaran M.",
            doctor_name="Dr. Arun Prakash",
            items=[schemas.DispenseItemRequest(medicine_id=med.id, batch_id=batch.id, quantity=5)],
            notes="Demo dispense test"
        )
        receipt = PharmacyService.execute_dispensing(self.db, req, pharm)
        self.assertIsNotNone(receipt.receipt_number)
        self.db.refresh(batch)
        self.assertEqual(batch.current_quantity, initial_qty - 5)

    def test_07_stock_adjustment(self):
        batch = self.db.query(models.Batch).filter(models.Batch.status == "Active").first()
        old_qty = batch.current_quantity
        admin = self.db.query(models.User).filter(models.User.username == "admin").first()

        req = schemas.BatchAdjustRequest(new_quantity=old_qty + 10, reason="Physical Inventory Reconciliation")
        updated = PharmacyService.adjust_batch_stock(self.db, batch.id, req, admin)
        self.assertEqual(updated.current_quantity, old_qty + 10)

    def test_08_live_alerts_sync(self):
        PharmacyService.sync_live_alerts(self.db)
        alerts = self.db.query(models.Alert).filter(models.Alert.is_resolved == False).all()
        self.assertTrue(len(alerts) > 0)
        types = [a.alert_type for a in alerts]
        self.assertIn("EXPIRED", types)
        self.assertIn("NEAR_EXPIRY", types)
        self.assertIn("TEMP_EXCURSION", types)

if __name__ == "__main__":
    unittest.main()
