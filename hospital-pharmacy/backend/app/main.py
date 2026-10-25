import os
from contextlib import asynccontextmanager
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.database import init_db, SessionLocal
from app.seed import seed_database
from app.routers import (
    users,
    medicines,
    batches,
    dispensing,
    suppliers,
    alerts,
    reports
)

@asynccontextmanager
async def lifespan(app: FastAPI):
    # Startup: Initialize tables and seed demonstration dataset
    init_db()
    db = SessionLocal()
    try:
        seed_database(db)
    finally:
        db.close()
    yield
    # Shutdown

app = FastAPI(
    title="NexusCare – Hospital Pharmacy Management System",
    description="Hospital Pharmacy Intelligence Platform: Stock batch tracking, expiry monitoring, safe dispensing, and inventory reporting.",
    version="1.0.0",
    lifespan=lifespan
)

# Enable CORS for frontend Vite dev server and production
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Register feature routers
app.include_router(users.router)
app.include_router(medicines.router)
app.include_router(batches.router)
app.include_router(dispensing.router)
app.include_router(suppliers.router)
app.include_router(alerts.router)
app.include_router(reports.router)

@app.get("/")
def root():
    return {
        "system": "NexusCare – Hospital Pharmacy Intelligence Platform",
        "version": "1.0.0",
        "status": "Operational",
        "documentation": "/docs"
    }

@app.get("/api/health")
def health():
    return {"status": "healthy", "service": "pharmacy-backend"}
