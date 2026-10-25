import datetime
from datetime import date, timedelta
from sqlalchemy.orm import Session
from app.database import SessionLocal, init_db
from app import models
from app.auth import get_password_hash
from app.services.pharmacy_service import PharmacyService

def seed_database(db: Session):
    # Check if already seeded
    if db.query(models.User).count() > 0:
        return

    print("--- Seeding NexusCare Hospital Pharmacy Database ---")

    # 1. Seed Users
    users_data = [
        {
            "username": "admin",
            "email": "admin@nexuscare.kvm",
            "full_name": "Dr. Meenakshi Sundaram",
            "role": "Hospital Administrator",
            "password": "password123"
        },
        {
            "username": "pharmacist",
            "email": "pharmacist@nexuscare.kvm",
            "full_name": "Karthik Raj, M.Pharm",
            "role": "Pharmacist",
            "password": "password123"
        },
        {
            "username": "inventory",
            "email": "inventory@nexuscare.kvm",
            "full_name": "Saranya Devi",
            "role": "Inventory Staff",
            "password": "password123"
        }
    ]

    for u in users_data:
        db.add(models.User(
            username=u["username"],
            email=u["email"],
            full_name=u["full_name"],
            role=u["role"],
            hashed_password=get_password_hash(u["password"]),
            is_active=True
        ))
    db.commit()

    # 2. Seed Suppliers
    suppliers_data = [
        {"name": "Apex Healthcare Logistics", "contact_person": "R. Selvaraj", "phone": "+91 94431 22334", "email": "apex.orders@pharma.in", "address": "Guindy Industrial Estate, Chennai", "lead_time_days": 2},
        {"name": "MedLife Tamil Nadu Pharma", "contact_person": "Priya Lakshmi", "phone": "+91 98840 55667", "email": "supplies@medlifetn.com", "address": "Anna Salai, Coimbatore", "lead_time_days": 3},
        {"name": "Kavitha Surgical Corp", "contact_person": "Muthuvel K.", "phone": "+91 97910 88990", "email": "sales@kavithasurgicals.in", "address": "East Veli Street, Madurai", "lead_time_days": 4},
        {"name": "Premier BioTech Chennai", "contact_person": "Dr. Arun Prakash", "phone": "+91 94440 11223", "email": "biologics@premierbio.in", "address": "TIDEL Park, Tharamani, Chennai", "lead_time_days": 1},
        {"name": "SunCare LifeSciences", "contact_person": "Gokul Krishnan", "phone": "+91 98401 77889", "email": "dispatch@suncarelife.com", "address": "SIPCOT, Ranipet", "lead_time_days": 3}
    ]

    created_suppliers = []
    for s in suppliers_data:
        sup = models.Supplier(**s)
        db.add(sup)
        created_suppliers.append(sup)
    db.commit()

    # 3. Seed Medicines Catalog (35+ common hospital formulary medicines)
    catalog = [
        # General & Pain
        {"code": "PCM-500", "brand_name": "Paracetamol 500", "generic_name": "Paracetamol", "category": "General & Pain Relief", "strength": "500 mg", "dosage_form": "Tablet", "manufacturer": "Cipla Ltd", "unit": "strip", "pack": "10 tabs/strip", "reorder": 50, "rx": False, "storage": "Room temp below 25°C", "pur": 12.0, "sell": 24.0, "qty": 450, "mfg_m": 6, "exp_m": 24},
        {"code": "PCM-650", "brand_name": "Dolo-650", "generic_name": "Paracetamol", "category": "General & Pain Relief", "strength": "650 mg", "dosage_form": "Tablet", "manufacturer": "Micro Labs", "unit": "strip", "pack": "15 tabs/strip", "reorder": 80, "rx": False, "storage": "Room temp below 25°C", "pur": 18.0, "sell": 33.5, "qty": 600, "mfg_m": 4, "exp_m": 20},
        {"code": "IBU-400", "brand_name": "Brufen 400", "generic_name": "Ibuprofen", "category": "General & Pain Relief", "strength": "400 mg", "dosage_form": "Tablet", "manufacturer": "Abbott India", "unit": "strip", "pack": "15 tabs/strip", "reorder": 40, "rx": True, "storage": "Dry place below 25°C", "pur": 22.0, "sell": 38.0, "qty": 320, "mfg_m": 5, "exp_m": 22},
        {"code": "DIC-050", "brand_name": "Voveran 50", "generic_name": "Diclofenac Sodium", "category": "General & Pain Relief", "strength": "50 mg", "dosage_form": "Tablet", "manufacturer": "Novartis India", "unit": "strip", "pack": "10 tabs/strip", "reorder": 30, "rx": True, "storage": "Room temp below 25°C", "pur": 35.0, "sell": 58.0, "qty": 200, "mfg_m": 3, "exp_m": 21},
        {"code": "TRM-050", "brand_name": "Tramazac 50", "generic_name": "Tramadol Hydrochloride", "category": "General & Pain Relief", "strength": "50 mg", "dosage_form": "Capsule", "manufacturer": "Zydus Cadila", "unit": "strip", "pack": "10 caps/strip", "reorder": 20, "rx": True, "storage": "Controlled substance cabinet", "pur": 48.0, "sell": 85.0, "qty": 110, "mfg_m": 2, "exp_m": 18},

        # Antibiotics
        {"code": "AMX-500", "brand_name": "Novamox 500", "generic_name": "Amoxicillin", "category": "Antibiotics", "strength": "500 mg", "dosage_form": "Capsule", "manufacturer": "Cipla Ltd", "unit": "strip", "pack": "10 caps/strip", "reorder": 40, "rx": True, "storage": "Cool dry place", "pur": 55.0, "sell": 92.0, "qty": 280, "mfg_m": 3, "exp_m": 18},
        {"code": "AMC-625", "brand_name": "Augmentin 625 Duo", "generic_name": "Amoxicillin + Clavulanate Potassium", "category": "Antibiotics", "strength": "625 mg", "dosage_form": "Tablet", "manufacturer": "GSK India", "unit": "strip", "pack": "10 tabs/strip", "reorder": 50, "rx": True, "storage": "Moisture-proof below 25°C", "pur": 110.0, "sell": 185.0, "qty": 350, "mfg_m": 4, "exp_m": 14},
        {"code": "AZM-500", "brand_name": "Azithral 500", "generic_name": "Azithromycin", "category": "Antibiotics", "strength": "500 mg", "dosage_form": "Tablet", "manufacturer": "Alembic Pharma", "unit": "strip", "pack": "5 tabs/strip", "reorder": 35, "rx": True, "storage": "Room temp below 25°C", "pur": 65.0, "sell": 115.0, "qty": 240, "mfg_m": 3, "exp_m": 18},
        {"code": "CFX-200", "brand_name": "Taxim-O 200", "generic_name": "Cefixime", "category": "Antibiotics", "strength": "200 mg", "dosage_form": "Tablet", "manufacturer": "Alkem Labs", "unit": "strip", "pack": "10 tabs/strip", "reorder": 30, "rx": True, "storage": "Cool dry place", "pur": 75.0, "sell": 130.0, "qty": 180, "mfg_m": 2, "exp_m": 16},
        {"code": "CTX-100", "brand_name": "Monocef 1g", "generic_name": "Ceftriaxone Sodium", "category": "Antibiotics", "strength": "1 gm", "dosage_form": "Injection", "manufacturer": "Aristo Pharma", "unit": "vial", "pack": "1 vial + SWFI", "reorder": 60, "rx": True, "storage": "Store below 25°C protected from light", "pur": 42.0, "sell": 68.0, "qty": 400, "mfg_m": 2, "exp_m": 24},
        {"code": "CIP-500", "brand_name": "Cifran 500", "generic_name": "Ciprofloxacin", "category": "Antibiotics", "strength": "500 mg", "dosage_form": "Tablet", "manufacturer": "Sun Pharma", "unit": "strip", "pack": "10 tabs/strip", "reorder": 30, "rx": True, "storage": "Room temp", "pur": 32.0, "sell": 54.0, "qty": 210, "mfg_m": 5, "exp_m": 20},

        # Cardiovascular
        {"code": "AML-005", "brand_name": "Amlopres 5", "generic_name": "Amlodipine Besylate", "category": "Cardiovascular", "strength": "5 mg", "dosage_form": "Tablet", "manufacturer": "Cipla Ltd", "unit": "strip", "pack": "15 tabs/strip", "reorder": 45, "rx": True, "storage": "Dry place below 25°C", "pur": 28.0, "sell": 48.0, "qty": 380, "mfg_m": 6, "exp_m": 22},
        {"code": "ATV-020", "brand_name": "Atorva 20", "generic_name": "Atorvastatin Calcium", "category": "Cardiovascular", "strength": "20 mg", "dosage_form": "Tablet", "manufacturer": "Zydus Cadila", "unit": "strip", "pack": "15 tabs/strip", "reorder": 40, "rx": True, "storage": "Room temp below 25°C", "pur": 95.0, "sell": 160.0, "qty": 290, "mfg_m": 4, "exp_m": 20},
        {"code": "TLM-040", "brand_name": "Telma 40", "generic_name": "Telmisartan", "category": "Cardiovascular", "strength": "40 mg", "dosage_form": "Tablet", "manufacturer": "Glenmark Pharma", "unit": "strip", "pack": "15 tabs/strip", "reorder": 50, "rx": True, "storage": "Store below 30°C", "pur": 82.0, "sell": 142.0, "qty": 310, "mfg_m": 3, "exp_m": 24},
        {"code": "CLP-075", "brand_name": "Clopilet 75", "generic_name": "Clopidogrel", "category": "Cardiovascular", "strength": "75 mg", "dosage_form": "Tablet", "manufacturer": "Sun Pharma", "unit": "strip", "pack": "15 tabs/strip", "reorder": 30, "rx": True, "storage": "Room temp", "pur": 88.0, "sell": 150.0, "qty": 190, "mfg_m": 5, "exp_m": 19},
        {"code": "ENX-040", "brand_name": "Clexane 40mg/0.4ml", "generic_name": "Enoxaparin Sodium", "category": "Cardiovascular", "strength": "40 mg/0.4 ml", "dosage_form": "Prefilled Syringe", "manufacturer": "Sanofi India", "unit": "syringe", "pack": "1 PFS", "reorder": 25, "rx": True, "storage": "Do not freeze, store below 25°C", "pur": 380.0, "sell": 540.0, "qty": 65, "mfg_m": 2, "exp_m": 15},

        # Diabetes (with Cold Chain)
        {"code": "MET-500", "brand_name": "Glycomet 500", "generic_name": "Metformin Hydrochloride", "category": "Diabetes Care", "strength": "500 mg", "dosage_form": "Tablet", "manufacturer": "USV Ltd", "unit": "strip", "pack": "20 tabs/strip", "reorder": 60, "rx": True, "storage": "Room temp below 25°C", "pur": 30.0, "sell": 48.0, "qty": 420, "mfg_m": 6, "exp_m": 24},
        {"code": "MET-850", "brand_name": "Glycomet 850", "generic_name": "Metformin Hydrochloride", "category": "Diabetes Care", "strength": "850 mg", "dosage_form": "Tablet", "manufacturer": "USV Ltd", "unit": "strip", "pack": "15 tabs/strip", "reorder": 35, "rx": True, "storage": "Room temp below 25°C", "pur": 38.0, "sell": 62.0, "qty": 260, "mfg_m": 4, "exp_m": 22},
        {"code": "INS-REG", "brand_name": "Human Actrapid 100IU", "generic_name": "Human Insulin Regular", "category": "Diabetes Care", "strength": "100 IU/ml (10ml)", "dosage_form": "Vial", "manufacturer": "Novo Nordisk", "unit": "vial", "pack": "10 ml vial", "reorder": 30, "rx": True, "storage": "Cold storage 2°C to 8°C (Do Not Freeze)", "pur": 145.0, "sell": 220.0, "qty": 85, "mfg_m": 2, "exp_m": 12, "temp": 4.5},
        {"code": "INS-GLA", "brand_name": "Lantus Solostar", "generic_name": "Insulin Glargine", "category": "Diabetes Care", "strength": "100 IU/ml (3ml)", "dosage_form": "Prefilled Pen", "manufacturer": "Sanofi India", "unit": "pen", "pack": "3 ml cartridge", "reorder": 20, "rx": True, "storage": "Cold storage 2°C to 8°C (Do Not Freeze)", "pur": 620.0, "sell": 890.0, "qty": 45, "mfg_m": 3, "exp_m": 14, "temp": 5.1},

        # Gastrointestinal
        {"code": "PAN-040", "brand_name": "Pantocid 40", "generic_name": "Pantoprazole", "category": "Gastrointestinal", "strength": "40 mg", "dosage_form": "Tablet", "manufacturer": "Sun Pharma", "unit": "strip", "pack": "15 tabs/strip", "reorder": 50, "rx": True, "storage": "Room temp below 25°C", "pur": 75.0, "sell": 125.0, "qty": 360, "mfg_m": 5, "exp_m": 24},
        {"code": "OND-004", "brand_name": "Emeset 4", "generic_name": "Ondansetron", "category": "Gastrointestinal", "strength": "4 mg", "dosage_form": "Tablet", "manufacturer": "Cipla Ltd", "unit": "strip", "pack": "10 tabs/strip", "reorder": 40, "rx": True, "storage": "Protected from light", "pur": 28.0, "sell": 48.0, "qty": 290, "mfg_m": 4, "exp_m": 20},
        {"code": "ORS-001", "brand_name": "Electral ORS", "generic_name": "Oral Rehydration Salts (WHO Formula)", "category": "Gastrointestinal", "strength": "21.8 gm Sachet", "dosage_form": "Powder Sachet", "manufacturer": "FDC Ltd", "unit": "sachet", "pack": "1 sachet (for 1 Liter)", "reorder": 70, "rx": False, "storage": "Moisture-free room temp", "pur": 14.0, "sell": 22.0, "qty": 550, "mfg_m": 5, "exp_m": 30},
        {"code": "RAB-020", "brand_name": "Razo 20", "generic_name": "Rabeprazole Sodium", "category": "Gastrointestinal", "strength": "20 mg", "dosage_form": "Tablet", "manufacturer": "Dr. Reddy's", "unit": "strip", "pack": "15 tabs/strip", "reorder": 35, "rx": True, "storage": "Store below 25°C", "pur": 92.0, "sell": 155.0, "qty": 210, "mfg_m": 3, "exp_m": 18},

        # Allergy & Respiratory
        {"code": "CET-010", "brand_name": "Cetzine 10", "generic_name": "Cetirizine Hydrochloride", "category": "Allergy & Respiratory", "strength": "10 mg", "dosage_form": "Tablet", "manufacturer": "Dr. Reddy's", "unit": "strip", "pack": "10 tabs/strip", "reorder": 40, "rx": False, "storage": "Room temp below 25°C", "pur": 15.0, "sell": 28.0, "qty": 340, "mfg_m": 4, "exp_m": 22},
        {"code": "SAL-INH", "brand_name": "Asthalin 100mcg Inhaler", "generic_name": "Salbutamol Sulphate", "category": "Allergy & Respiratory", "strength": "100 mcg (200 MDI)", "dosage_form": "Inhaler", "manufacturer": "Cipla Ltd", "unit": "canister", "pack": "200 metered doses", "reorder": 25, "rx": True, "storage": "Below 30°C, keep away from direct sunlight", "pur": 98.0, "sell": 152.0, "qty": 140, "mfg_m": 3, "exp_m": 18},
        {"code": "BUD-RSP", "brand_name": "Budecort 0.5mg Respules", "generic_name": "Budesonide", "category": "Allergy & Respiratory", "strength": "0.5 mg/2 ml", "dosage_form": "Respule", "manufacturer": "Cipla Ltd", "unit": "pack", "pack": "5 respules/pack", "reorder": 30, "rx": True, "storage": "Do not freeze, protect from light", "pur": 120.0, "sell": 185.0, "qty": 160, "mfg_m": 4, "exp_m": 15},
        {"code": "MON-010", "brand_name": "Montair 10", "generic_name": "Montelukast Sodium", "category": "Allergy & Respiratory", "strength": "10 mg", "dosage_form": "Tablet", "manufacturer": "Cipla Ltd", "unit": "strip", "pack": "15 tabs/strip", "reorder": 30, "rx": True, "storage": "Room temp below 25°C", "pur": 115.0, "sell": 195.0, "qty": 175, "mfg_m": 3, "exp_m": 20},

        # Fluids & Emergency Stock
        {"code": "NS-500", "brand_name": "Normal Saline 0.9% (500ml)", "generic_name": "Sodium Chloride 0.9% IV Infusion", "category": "Fluids & Emergency", "strength": "0.9% w/v (500 ml)", "dosage_form": "IV Bottle", "manufacturer": "Baxter India", "unit": "bottle", "pack": "500 ml FFS bottle", "reorder": 80, "rx": True, "storage": "Room temp below 30°C", "pur": 26.0, "sell": 42.0, "qty": 500, "mfg_m": 2, "exp_m": 36},
        {"code": "RL-500", "brand_name": "Ringer's Lactate (500ml)", "generic_name": "Compound Sodium Lactate IV", "category": "Fluids & Emergency", "strength": "500 ml IV", "dosage_form": "IV Bottle", "manufacturer": "Baxter India", "unit": "bottle", "pack": "500 ml FFS bottle", "reorder": 80, "rx": True, "storage": "Room temp below 30°C", "pur": 28.0, "sell": 45.0, "qty": 480, "mfg_m": 3, "exp_m": 36},
        {"code": "D5-500", "brand_name": "Dextrose 5% IV (500ml)", "generic_name": "Dextrose Anhydrous 5%", "category": "Fluids & Emergency", "strength": "5% w/v (500 ml)", "dosage_form": "IV Bottle", "manufacturer": "Fresenius Kabi", "unit": "bottle", "pack": "500 ml bottle", "reorder": 60, "rx": True, "storage": "Room temp below 30°C", "pur": 29.0, "sell": 46.0, "qty": 380, "mfg_m": 2, "exp_m": 36},
        {"code": "ADR-001", "brand_name": "Adrenaline 1:1000 Inj", "generic_name": "Epinephrine / Adrenaline", "category": "Fluids & Emergency", "strength": "1 mg/ml (1 ml)", "dosage_form": "Ampoule", "manufacturer": "Neon Labs", "unit": "ampoule", "pack": "1 ml ampoule", "reorder": 40, "rx": True, "storage": "Crash cart / Protected from light below 25°C", "pur": 16.0, "sell": 28.0, "qty": 120, "mfg_m": 3, "exp_m": 15},
        {"code": "ATR-006", "brand_name": "Atropine Sulphate 0.6mg", "generic_name": "Atropine Sulphate", "category": "Fluids & Emergency", "strength": "0.6 mg/ml (1 ml)", "dosage_form": "Ampoule", "manufacturer": "Neon Labs", "unit": "ampoule", "pack": "1 ml ampoule", "reorder": 30, "rx": True, "storage": "Emergency tray below 25°C", "pur": 12.0, "sell": 22.0, "qty": 95, "mfg_m": 4, "exp_m": 18},

        # Wound Care & Surgical Supplies
        {"code": "PVI-100", "brand_name": "Betadine 10% Solution", "generic_name": "Povidone Iodine 10%", "category": "Wound Care & Consumables", "strength": "10% w/v (100 ml)", "dosage_form": "Solution", "manufacturer": "Win-Medicare", "unit": "bottle", "pack": "100 ml bottle", "reorder": 40, "rx": False, "storage": "Store below 25°C", "pur": 72.0, "sell": 115.0, "qty": 180, "mfg_m": 5, "exp_m": 24},
        {"code": "GZ-1010", "brand_name": "Sterile Gauze Swabs 10x10", "generic_name": "Surgical Absorbent Cotton Gauze", "category": "Wound Care & Consumables", "strength": "10 cm x 10 cm (12 Ply)", "dosage_form": "Pack", "manufacturer": "Datt Mediproducts", "unit": "pack", "pack": "10 sterile pouches/box", "reorder": 50, "rx": False, "storage": "Dry moisture-free store", "pur": 45.0, "sell": 75.0, "qty": 300, "mfg_m": 4, "exp_m": 36},
        {"code": "SYR-005", "brand_name": "Dispovan 5ml Syringe", "generic_name": "Hypodermic Syringe with 23G Needle", "category": "Wound Care & Consumables", "strength": "5 ml with 23G 1 inch", "dosage_form": "Piece", "manufacturer": "Hindustan Syringes (HMD)", "unit": "box", "pack": "100 pcs/box", "reorder": 20, "rx": False, "storage": "General sterile medical warehouse", "pur": 320.0, "sell": 480.0, "qty": 45, "mfg_m": 3, "exp_m": 48},
        {"code": "GLV-NIT", "brand_name": "Nitrile Exam Gloves (M)", "generic_name": "Non-Sterile Powder-Free Nitrile Gloves", "category": "Wound Care & Consumables", "strength": "Medium / Blue", "dosage_form": "Box", "manufacturer": "Sempermed", "unit": "box", "pack": "100 gloves/box", "reorder": 25, "rx": False, "storage": "Cool dry ventilated space", "pur": 260.0, "sell": 390.0, "qty": 55, "mfg_m": 6, "exp_m": 48},
        {"code": "TAP-MIC", "brand_name": "3M Micropore Surgical Tape", "generic_name": "Hypoallergenic Paper Surgical Tape", "category": "Wound Care & Consumables", "strength": "1 inch x 9.1 m", "dosage_form": "Roll", "manufacturer": "3M India", "unit": "box", "pack": "12 rolls/box", "reorder": 15, "rx": False, "storage": "Room temp dry", "pur": 280.0, "sell": 420.0, "qty": 28, "mfg_m": 4, "exp_m": 36},
        {"code": "CHX-500", "brand_name": "Microshield 4% Scrub", "generic_name": "Chlorhexidine Gluconate 4%", "category": "Wound Care & Consumables", "strength": "4% w/v (500 ml)", "dosage_form": "Bottle", "manufacturer": "Johnson & Johnson", "unit": "bottle", "pack": "500 ml pump bottle", "reorder": 20, "rx": False, "storage": "OT Scrub room dispenser", "pur": 190.0, "sell": 295.0, "qty": 35, "mfg_m": 3, "exp_m": 24},

        # Controlled Low-Stock item to demonstrate warning
        {"code": "SUCR-01", "brand_name": "Sucrafil O Gel", "generic_name": "Sucralfate + Oxetacaine", "category": "Gastrointestinal", "strength": "200 ml Suspension", "dosage_form": "Syrup", "manufacturer": "Fourrts India", "unit": "bottle", "pack": "200 ml", "reorder": 25, "rx": True, "storage": "Room temp below 25°C", "pur": 140.0, "sell": 210.0, "qty": 8, "mfg_m": 3, "exp_m": 18}
    ]

    today = date.today()

    for idx, c in enumerate(catalog, start=1):
        med = models.Medicine(
            code=c["code"],
            brand_name=c["brand_name"],
            generic_name=c["generic_name"],
            category=c["category"],
            strength=c["strength"],
            dosage_form=c["dosage_form"],
            manufacturer=c["manufacturer"],
            unit_of_measure=c["unit"],
            pack_size=c["pack"],
            reorder_level=c["reorder"],
            storage_conditions=c["storage"],
            requires_prescription=c["rx"],
            is_active=True
        )
        db.add(med)
        db.flush()

        sup = created_suppliers[idx % len(created_suppliers)]

        # Primary batch (healthy, valid)
        mfg_date = today - timedelta(days=c["mfg_m"] * 30)
        exp_date = today + timedelta(days=c["exp_m"] * 30)

        # Batch 1
        batch1 = models.Batch(
            medicine_id=med.id,
            batch_number=f"BN-{date.today().year}-{c['code']}-01",
            supplier_id=sup.id,
            purchase_price=c["pur"],
            selling_price=c["sell"],
            initial_quantity=c["qty"],
            current_quantity=c["qty"],
            manufacturing_date=mfg_date,
            expiry_date=exp_date,
            storage_location="Main Dispensary Shelf " + chr(65 + (idx % 6)) + str((idx % 9) + 1),
            status="Active",
            temperature_celsius=c.get("temp", None)
        )
        db.add(batch1)
        db.flush()

        # Record stock inward transaction
        db.add(models.StockTransaction(
            medicine_id=med.id,
            batch_id=batch1.id,
            transaction_type="STOCK_IN",
            quantity=c["qty"],
            balance_after=c["qty"],
            unit_price=c["pur"],
            reason="Opening Initial Stock Take",
            reference_id=f"INIT-{batch1.batch_number}",
            performed_by="System Initializer",
            notes="NexusCare Demo Formulary Baseline"
        ))

        # For a few specific items, add second batch:
        # Item 1: Near expiry batch (expiring in 45 days)
        if c["code"] == "AMX-500":
            near_exp = today + timedelta(days=45)
            batch_near = models.Batch(
                medicine_id=med.id,
                batch_number=f"BN-NEAR-{c['code']}",
                supplier_id=sup.id,
                purchase_price=c["pur"],
                selling_price=c["sell"],
                initial_quantity=50,
                current_quantity=30,
                manufacturing_date=today - timedelta(days=360),
                expiry_date=near_exp,
                storage_location="Fast-Move Bay 01",
                status="Active"
            )
            db.add(batch_near)
            db.flush()
            db.add(models.StockTransaction(
                medicine_id=med.id,
                batch_id=batch_near.id,
                transaction_type="STOCK_IN",
                quantity=50,
                balance_after=c["qty"] + 30,
                unit_price=c["pur"],
                reason="Supplemental Transfer",
                reference_id=f"INIT-{batch_near.batch_number}",
                performed_by="System Initializer"
            ))

        # Item 2: Expired batch (expired 15 days ago) to test block dispensing
        if c["code"] == "CET-010":
            past_exp = today - timedelta(days=15)
            batch_exp = models.Batch(
                medicine_id=med.id,
                batch_number=f"BN-EXP-{c['code']}",
                supplier_id=sup.id,
                purchase_price=c["pur"],
                selling_price=c["sell"],
                initial_quantity=20,
                current_quantity=15,
                manufacturing_date=today - timedelta(days=700),
                expiry_date=past_exp,
                storage_location="Quarantine Shelf Q-1",
                status="Expired"
            )
            db.add(batch_exp)

        # Item 3: Temperature excursion batch for Insulin Regular (e.g. 9.4°C)
        if c["code"] == "INS-REG":
            batch_temp = models.Batch(
                medicine_id=med.id,
                batch_number=f"BN-TEMP-{c['code']}",
                supplier_id=sup.id,
                purchase_price=c["pur"],
                selling_price=c["sell"],
                initial_quantity=25,
                current_quantity=20,
                manufacturing_date=today - timedelta(days=60),
                expiry_date=today + timedelta(days=300),
                storage_location="Cold Storage Unit B2",
                status="Active",
                temperature_celsius=9.4  # Excursion! > 8.0°C
            )
            db.add(batch_temp)

    db.commit()

    # 4. Seed Synthetic Prescriptions
    p1 = models.Prescription(
        prescription_number="RX-2026-001",
        patient_id="PAT-2026-0012",
        patient_name="Arjun Kumar",
        patient_age=38,
        patient_gender="Male",
        doctor_name="Dr. Arun Prakash",
        doctor_reg_no="TN-MED-44821",
        department="General Medicine",
        diagnosis="Acute Bronchitis & Pyrexia",
        status="PENDING",
        notes="Administer with full glass of water. Complete 5-day antibiotic course."
    )
    db.add(p1)
    db.flush()

    p2 = models.Prescription(
        prescription_number="RX-2026-002",
        patient_id="PAT-2026-0034",
        patient_name="Saranya Devi",
        patient_age=45,
        patient_gender="Female",
        doctor_name="Dr. Meena Kumari",
        doctor_reg_no="TN-MED-39102",
        department="Cardiology",
        diagnosis="Essential Hypertension & Hyperlipidemia",
        status="PENDING",
        notes="Monitor morning BP regularly. Take Atorvastatin at bedtime."
    )
    db.add(p2)
    db.flush()

    # Add items to prescription 1
    med_dolo = db.query(models.Medicine).filter(models.Medicine.code == "PCM-650").first()
    med_aug = db.query(models.Medicine).filter(models.Medicine.code == "AMC-625").first()
    med_ors = db.query(models.Medicine).filter(models.Medicine.code == "ORS-001").first()

    if med_dolo and med_aug and med_ors:
        db.add(models.PrescriptionItem(
            prescription_id=p1.id,
            medicine_id=med_dolo.id,
            dosage_instructions="1 tablet TDS after meals for 5 days",
            prescribed_quantity=15,
            dispensed_quantity=0,
            status="PENDING"
        ))
        db.add(models.PrescriptionItem(
            prescription_id=p1.id,
            medicine_id=med_aug.id,
            dosage_instructions="1 tablet BD after food for 5 days",
            prescribed_quantity=10,
            dispensed_quantity=0,
            status="PENDING"
        ))
        db.add(models.PrescriptionItem(
            prescription_id=p1.id,
            medicine_id=med_ors.id,
            dosage_instructions="Dissolve 1 sachet in 1 liter clean drinking water daily",
            prescribed_quantity=3,
            dispensed_quantity=0,
            status="PENDING"
        ))

    # Add items to prescription 2
    med_amlo = db.query(models.Medicine).filter(models.Medicine.code == "AML-005").first()
    med_ator = db.query(models.Medicine).filter(models.Medicine.code == "ATV-020").first()
    if med_amlo and med_ator:
        db.add(models.PrescriptionItem(
            prescription_id=p2.id,
            medicine_id=med_amlo.id,
            dosage_instructions="1 tablet OD morning after breakfast",
            prescribed_quantity=30,
            dispensed_quantity=0,
            status="PENDING"
        ))
        db.add(models.PrescriptionItem(
            prescription_id=p2.id,
            medicine_id=med_ator.id,
            dosage_instructions="1 tablet OD at night after dinner",
            prescribed_quantity=30,
            dispensed_quantity=0,
            status="PENDING"
        ))

    db.commit()

    # 5. Run Live Alerts Calculation
    PharmacyService.sync_live_alerts(db)

    print(f"--- Seeding Completed: {db.query(models.Medicine).count()} medicines, {db.query(models.Batch).count()} batches, {db.query(models.Supplier).count()} suppliers ---")

if __name__ == "__main__":
    init_db()
    db = SessionLocal()
    seed_database(db)
    db.close()
