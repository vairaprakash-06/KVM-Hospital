-- ====================================================================
-- KVM Hospital Management System — NexusCare
-- Seed Data: Demonstration & Testing Dataset
-- Default Password for all demo accounts: Password123!
-- ====================================================================

USE `kvm_hospital`;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Roles
INSERT INTO `roles` (`id`, `role_name`, `display_name`, `description`) VALUES
(1, 'super_admin', 'Super Admin', 'Full system configuration, security administration, and role-permission authority'),
(2, 'hospital_admin', 'Hospital Admin', 'Hospital operations, department oversight, staff assignments, and FlowLens bottleneck management'),
(3, 'doctor', 'Doctor', 'Clinical consultations, prescriptions, patient encounter management, and lab orders'),
(4, 'receptionist', 'Receptionist', 'Patient registration, appointment scheduling, and QueueFlow check-in desk'),
(5, 'nurse', 'Nurse', 'Bed management, patient observation, vital records, and inpatient care assistance'),
(6, 'lab_technician', 'Laboratory Technician', 'Lab specimen processing, test result entry, and quality approval'),
(7, 'pharmacist', 'Pharmacist', 'Medication inventory control, batch management, and prescription dispensing'),
(8, 'billing_staff', 'Billing Staff', 'Invoice generation, itemized charges, payments, and financial adjustments'),
(9, 'patient', 'Patient', 'Patient self-service portal: appointments, queue tokens, lab results, and CareTrail timeline');

-- 2. Permissions
INSERT INTO `permissions` (`id`, `permission_key`, `module`, `description`) VALUES
(1, 'system.manage_settings', 'admin', 'Manage global system settings'),
(2, 'users.manage', 'admin', 'Create, update, deactivate staff and user accounts'),
(3, 'roles.assign', 'admin', 'Assign and update user roles and permissions'),
(4, 'audit.view', 'trustledger', 'View system audit logs, security records, and changes'),
(5, 'flowlens.manage', 'flowlens', 'View and resolve operational bottlenecks and configure thresholds'),
(6, 'patients.view', 'patient', 'View patient profiles and demographic details'),
(7, 'patients.create', 'patient', 'Register new patients into the hospital'),
(8, 'patients.edit', 'patient', 'Update patient demographic information'),
(9, 'appointments.view', 'appointment', 'View hospital and doctor appointment calendars'),
(10, 'appointments.book', 'appointment', 'Book and reschedule appointments'),
(11, 'appointments.cancel', 'appointment', 'Cancel appointments with documented reason'),
(12, 'queue.manage', 'queueflow', 'Manage live QueueFlow tokens, call next patient, and update priority'),
(13, 'queue.view', 'queueflow', 'View live clinic queue monitors'),
(14, 'consultation.write', 'clinical', 'Record consultation notes, vitals, diagnoses, and treatment plans'),
(15, 'prescriptions.write', 'clinical', 'Issue medication prescriptions to patients'),
(16, 'prescriptions.dispense', 'pharmacy', 'Dispense prescriptions and deduct pharmacy stock'),
(17, 'pharmacy.inventory', 'pharmacy', 'Manage pharmacy catalog, stock levels, batches, and reorders'),
(18, 'lab.order', 'laboratory', 'Order diagnostic laboratory investigations'),
(19, 'lab.results_entry', 'laboratory', 'Enter laboratory test results and normal range values'),
(20, 'beds.manage', 'bedmatrix', 'Allocate, transfer, and release beds; manage cleaning status'),
(21, 'beds.view', 'bedmatrix', 'View interactive BedMatrix occupancy grid'),
(22, 'billing.create_invoice', 'billing', 'Generate invoices from consultations, labs, beds, and pharmacy'),
(23, 'billing.record_payment', 'billing', 'Record patient payments and financial adjustments'),
(24, 'reports.view', 'reports', 'View analytical reports, charts, and operational metrics'),
(25, 'caretrail.view', 'caretrail', 'View complete patient CareTrail journey history');

-- 3. Role-Permission Mappings
-- Super Admin (All Permissions 1 to 25)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;

-- Hospital Admin
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(2, 2), (2, 4), (2, 5), (2, 6), (2, 7), (2, 8), (2, 9), (2, 10), (2, 11),
(2, 13), (2, 20), (2, 21), (2, 22), (2, 23), (2, 24), (2, 25);

-- Doctor
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(3, 6), (3, 9), (3, 12), (3, 13), (3, 14), (3, 15), (3, 18), (3, 21), (3, 25);

-- Receptionist
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(4, 6), (4, 7), (4, 8), (4, 9), (4, 10), (4, 11), (4, 12), (4, 13), (4, 21), (4, 25);

-- Nurse
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(5, 6), (5, 9), (5, 13), (5, 14), (5, 20), (5, 21), (5, 25);

-- Laboratory Technician
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(6, 6), (6, 18), (6, 19), (6, 25);

-- Pharmacist
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(7, 6), (7, 16), (7, 17), (7, 25);

-- Billing Staff
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(8, 6), (8, 22), (8, 23), (8, 24), (8, 25);

-- Patient
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(9, 6), (9, 9), (9, 13), (9, 25);

-- 4. Users (Hash = Password123!)
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `account_status`, `created_at`) VALUES
(1, 'superadmin', 'superadmin@nexuscare.org', '$2y$10$dPrT4l7TQ.D5rVR/QyJHVeU.qhRtzadZ9PWu7rRfBzidVB1cJwbHK', 'active', NOW()),
(2, 'admin', 'admin@nexuscare.org', '$2y$10$dPrT4l7TQ.D5rVR/QyJHVeU.qhRtzadZ9PWu7rRfBzidVB1cJwbHK', 'active', NOW()),
(3, 'dr.sharma', 'rajesh.sharma@nexuscare.org', '$2y$10$dPrT4l7TQ.D5rVR/QyJHVeU.qhRtzadZ9PWu7rRfBzidVB1cJwbHK', 'active', NOW()),
(4, 'dr.priya', 'priya.patel@nexuscare.org', '$2y$10$dPrT4l7TQ.D5rVR/QyJHVeU.qhRtzadZ9PWu7rRfBzidVB1cJwbHK', 'active', NOW()),
(5, 'dr.anand', 'anand.kumar@nexuscare.org', '$2y$10$dPrT4l7TQ.D5rVR/QyJHVeU.qhRtzadZ9PWu7rRfBzidVB1cJwbHK', 'active', NOW()),
(6, 'receptionist', 'reception@nexuscare.org', '$2y$10$dPrT4l7TQ.D5rVR/QyJHVeU.qhRtzadZ9PWu7rRfBzidVB1cJwbHK', 'active', NOW()),
(7, 'nurse.sarah', 'nurse.sarah@nexuscare.org', '$2y$10$dPrT4l7TQ.D5rVR/QyJHVeU.qhRtzadZ9PWu7rRfBzidVB1cJwbHK', 'active', NOW()),
(8, 'labtech.vikram', 'lab@nexuscare.org', '$2y$10$dPrT4l7TQ.D5rVR/QyJHVeU.qhRtzadZ9PWu7rRfBzidVB1cJwbHK', 'active', NOW()),
(9, 'pharma.rohit', 'pharmacy@nexuscare.org', '$2y$10$dPrT4l7TQ.D5rVR/QyJHVeU.qhRtzadZ9PWu7rRfBzidVB1cJwbHK', 'active', NOW()),
(10, 'billing.sneha', 'billing@nexuscare.org', '$2y$10$dPrT4l7TQ.D5rVR/QyJHVeU.qhRtzadZ9PWu7rRfBzidVB1cJwbHK', 'active', NOW()),
(11, 'patient.arun', 'arun.verma@example.com', '$2y$10$dPrT4l7TQ.D5rVR/QyJHVeU.qhRtzadZ9PWu7rRfBzidVB1cJwbHK', 'active', NOW()),
(12, 'patient.sunita', 'sunita.rao@example.com', '$2y$10$dPrT4l7TQ.D5rVR/QyJHVeU.qhRtzadZ9PWu7rRfBzidVB1cJwbHK', 'active', NOW());

-- 5. User Role Assignments
INSERT INTO `user_role_assignments` (`user_id`, `role_id`, `assigned_by`) VALUES
(1, 1, 1), -- Super Admin
(2, 2, 1), -- Hospital Admin
(3, 3, 2), -- Dr. Rajesh Sharma (Doctor)
(4, 3, 2), -- Dr. Priya Patel (Doctor)
(5, 3, 2), -- Dr. Anand Kumar (Doctor)
(6, 4, 2), -- Receptionist
(7, 5, 2), -- Nurse Sarah
(8, 6, 2), -- Lab Tech Vikram
(9, 7, 2), -- Pharmacist Rohit
(10, 8, 2), -- Billing Sneha
(11, 9, 6), -- Patient Arun Verma
(12, 9, 6); -- Patient Sunita Rao

-- 6. Departments (89 Enterprise Clinical, Diagnostic & Operational Divisions)
INSERT INTO `departments` (`id`, `dept_code`, `name`, `description`, `location`, `phone_extension`, `status`) VALUES
(1, 'EMERG', 'Emergency Department', '24/7 Acute trauma, resuscitation, and critical emergency response', 'Ground Floor, Wing A', '101', 'active'),
(2, 'OPD', 'Outpatient Department', 'General consultations, follow-ups, and ambulatory patient care', 'Ground Floor, Wing B', '102', 'active'),
(3, 'IPD', 'Inpatient Department', 'Wards, acute medical/surgical monitoring, and post-op care', 'Floors 2 & 3', '103', 'active'),
(4, 'GENMED', 'General Medicine', 'Internal medicine, adult illnesses, lifestyle diseases, and checkups', 'First Floor, Suite 101', '104', 'active'),
(5, 'SURG', 'General Surgery', 'Elective and emergency surgical procedures and perioperative care', 'Floor 2, Surgical Wing', '105', 'active'),
(6, 'PED', 'Pediatrics', 'Comprehensive neonatal, child, and adolescent healthcare', 'First Floor, Wing C', '106', 'active'),
(7, 'OBGYN', 'Gynecology and Obstetrics', 'Maternal health, delivery suites, and gynecological surgeries', 'Floor 3, Maternity Wing', '107', 'active'),
(8, 'ORTHO', 'Orthopedics', 'Musculoskeletal disorders, bone fractures, and joint replacement', 'First Floor, Suite 108', '108', 'active'),
(9, 'CARD', 'Cardiology', 'Cardiac care, ECG, echocardiography, and hypertension management', 'First Floor, Heart Center', '109', 'active'),
(10, 'NEURO', 'Neurology', 'Neurological disorders, stroke intervention, and brain health', 'Floor 2, Neuro Suite', '110', 'active'),
(11, 'DERM', 'Dermatology', 'Skin conditions, allergy screenings, and dermatological care', 'Ground Floor, Suite 111', '111', 'active'),
(12, 'OPHTH', 'Ophthalmology', 'Vision testing, cataract evaluation, and eye health', 'Ground Floor, Suite 112', '112', 'active'),
(13, 'RAD', 'Radiology', 'X-Ray, CT Scan, Ultrasound, and diagnostic medical imaging', 'Basement 1, Diagnostic Hub', '113', 'active'),
(14, 'LAB', 'Laboratory', 'Automated hematology, biochemistry, and pathology testing', 'Basement 1, Core Lab', '114', 'active'),
(15, 'PHARM', 'Pharmacy', 'Inpatient and outpatient prescription dispensing and inventory', 'Ground Floor, Main Atrium', '115', 'active'),
(16, 'NEUROSURG', 'Neurosurgery', 'Complex cranial microsurgery, spinal cord decompression, and brain tumor resection', 'Floor 4, Surgical Tower', '116', 'active'),
(17, 'CTVS', 'Cardiothoracic and Vascular Surgery', 'Coronary artery bypass grafting, heart valve replacement, and thoracic aortic surgery', 'Floor 4, Cardiac Wing', '117', 'active'),
(18, 'PEDSURG', 'Pediatric Surgery', 'Congenital anomaly repair, pediatric trauma surgery, and pediatric laparoscopy', 'Floor 3, Pediatric Wing', '118', 'active'),
(19, 'PLASTSURG', 'Plastic & Reconstructive Surgery', 'Microvascular tissue transfer, burn reconstruction, craniofacial repair, and aesthetics', 'Floor 3, Cosmetic Suite', '119', 'active'),
(20, 'URO', 'Urology', 'Endourology, laser stone lithotripsy, robotic prostatectomy, and male fertility care', 'Floor 2, Urology Wing', '120', 'active'),
(21, 'SURGONC', 'Surgical Oncology', 'Comprehensive surgical excision of malignant tumors, staging laparotomy, and reconstruction', 'Floor 4, Oncology Center', '121', 'active'),
(22, 'GI_SURG', 'Surgical Gastroenterology', 'Hepato-pancreato-biliary surgeries, colorectal resections, and bariatric operations', 'Floor 2, Surgical Wing', '122', 'active'),
(23, 'ENT', 'Otorhinolaryngology (ENT)', 'Ear microsurgery, endoscopic sinus and skull base surgery, and laryngeal intervention', 'Ground Floor, Suite 123', '123', 'active'),
(24, 'MAXILLO', 'Oral and Maxillofacial Surgery', 'Facial trauma reconstruction, orthognathic jaw correction, and temporomandibular care', 'Ground Floor, Dental Suite', '124', 'active'),
(25, 'VASCULAR', 'Vascular Surgery', 'Peripheral bypass grafting, endovascular aneurysm repair, and varicose vein ablations', 'Floor 2, Vascular Lab', '125', 'active'),
(26, 'BARIATRIC', 'Bariatric & Metabolic Surgery', 'Laparoscopic sleeve gastrectomy, Roux-en-Y bypass, and multidisciplinary obesity care', 'Floor 3, Metabolic Suite', '126', 'active'),
(27, 'TRANSPLANT', 'Organ Transplant Surgery', 'Live-related and cadaveric renal and hepatic transplantation with immunosuppressive care', 'Floor 4, Transplant ICU', '127', 'active'),
(28, 'PODIATRY', 'Podiatric Surgery & Diabetic Foot', 'Diabetic limb salvage, transmetatarsal reconstruction, and chronic wound debridement', 'First Floor, Suite 128', '128', 'active'),
(29, 'NEPHRO', 'Nephrology', 'Acute kidney injury, chronic kidney disease, hemodialysis protocols, and hypertension', 'Floor 3, Renal Center', '129', 'active'),
(30, 'GASTRO', 'Gastroenterology & Hepatology', 'Diagnostic and therapeutic endoscopy, colonoscopy, ERCP, and cirrhosis management', 'Floor 2, Digestive Center', '130', 'active'),
(31, 'PULM', 'Pulmonology & Respiratory Medicine', 'COPD, severe bronchial asthma, interventional bronchoscopy, and sleep-disordered breathing', 'First Floor, Suite 131', '131', 'active'),
(32, 'ENDO', 'Endocrinology & Diabetology', 'Comprehensive diabetes titration, thyroid nodule evaluation, and metabolic endocrine care', 'First Floor, Suite 132', '132', 'active'),
(33, 'MEDONC', 'Medical Oncology', 'Systemic cancer chemotherapy, targeted molecular therapy, and personalized immunotherapy', 'Floor 4, Cancer Center', '133', 'active'),
(34, 'HEMATO', 'Clinical Hematology', 'Leukemia, lymphoma, multiple myeloma, thalassemia, and coagulation disorder management', 'Floor 4, Hematology Unit', '134', 'active'),
(35, 'RHEUM', 'Rheumatology', 'Autoimmune connective tissue disorders, rheumatoid arthritis, lupus, and biologic therapy', 'First Floor, Suite 135', '135', 'active'),
(36, 'INFECT', 'Infectious Diseases', 'Complex tropical infections, multi-drug resistant pathogens, and antimicrobial stewardship', 'Ground Floor, Wing D', '136', 'active'),
(37, 'PSYCH', 'Psychiatry & Behavioral Health', 'Clinical depression, bipolar disorder, adult psychosis, and psychiatric crisis intervention', 'Floor 2, Behavioral Suite', '137', 'active'),
(38, 'GERIATRIC', 'Geriatric Medicine', 'Comprehensive geriatric functional assessment, polypharmacy review, and dementia care', 'First Floor, Suite 138', '138', 'active'),
(39, 'ALLERGY', 'Allergy & Clinical Immunology', 'Skin prick allergy testing, food and drug challenges, and immunodeficiency evaluations', 'First Floor, Suite 139', '139', 'active'),
(40, 'PALLIATIVE', 'Palliative Care & Pain Medicine', 'Multimodal chronic cancer pain control, symptom palliation, and holistic family support', 'Floor 3, Care Suite', '140', 'active'),
(41, 'SLEEP', 'Sleep Medicine', 'Diagnostic overnight polysomnography, continuous positive airway pressure titration', 'Basement 1, Sleep Lab', '141', 'active'),
(42, 'GENETICS', 'Medical Genetics & Genomics', 'Pediatric and adult genetic counseling, hereditary cancer syndromes, and karyotyping', 'First Floor, Suite 142', '142', 'active'),
(43, 'TOXIC', 'Clinical Toxicology', 'Acute poisoning decontamination, organophosphate antidote administration, and envenomation', 'Ground Floor, Emergency Wing', '143', 'active'),
(44, 'ADDICTION', 'Addiction Medicine & De-Addiction', 'Medically supervised detoxification, addiction psychiatry, and relapse prevention counseling', 'Floor 2, De-Addiction Center', '144', 'active'),
(45, 'NICU', 'Neonatal Intensive Care', 'Level III advanced premature neonatal ventilation, surfactant therapy, and total parenteral nutrition', 'Floor 3, Maternity Wing', '145', 'active'),
(46, 'PICU', 'Pediatric Intensive Care', 'Pediatric severe sepsis resuscitation, neurotrauma monitoring, and multi-organ life support', 'Floor 3, Pediatric Tower', '146', 'active'),
(47, 'PED_CARD', 'Pediatric Cardiology', 'Congenital heart disease echocardiography, fetal cardiac scans, and pediatric arrhythmias', 'First Floor, Heart Center', '147', 'active'),
(48, 'PED_NEURO', 'Pediatric Neurology', 'Refractory childhood seizures, cerebral palsy rehabilitation, and neurometabolic screening', 'First Floor, Wing C', '148', 'active'),
(49, 'MAT_FETAL', 'Maternal-Fetal Medicine', 'Targeted fetal anomaly ultrasound, amniocentesis, and high-risk pregnancy co-management', 'Floor 3, Fetal Medicine', '149', 'active'),
(50, 'FERTILITY', 'Reproductive Medicine & IVF', 'Advanced in-vitro fertilization, intracytoplasmic sperm injection, and reproductive endocrinology', 'Floor 3, Fertility Center', '150', 'active'),
(51, 'ANESTH', 'Anesthesiology & Perioperative Care', 'Comprehensive pre-operative anesthetic optimization, neuroaxial blocks, and PACU monitoring', 'Floor 2, OT Complex', '151', 'active'),
(52, 'MICU', 'Medical Intensive Care Unit', 'Adult ARDS mechanical ventilation, invasive hemodynamic monitoring, and septic shock protocols', 'Floor 2, Critical Tower', '152', 'active'),
(53, 'SICU', 'Surgical Intensive Care Unit', 'Post-major surgical invasive hemodynamic stabilization and continuous cardiac index monitoring', 'Floor 2, Critical Tower', '153', 'active'),
(54, 'CCU', 'Coronary Care Unit', 'Acute myocardial infarction thrombolysis, intra-aortic balloon counterpulsation, and telemetry', 'First Floor, Heart Center', '154', 'active'),
(55, 'NEURO_ICU', 'Neuro Intensive Care Unit', 'Continuous intracranial pressure surveillance, post-thrombectomy neuro-monitoring, and sedation', 'Floor 2, Neuro Wing', '155', 'active'),
(56, 'TRAUMA_ICU', 'Trauma Intensive Care Unit', 'Massive transfusion protocol activation, rapid damage control monitoring, and spine precautions', 'Ground Floor, Trauma Wing', '156', 'active'),
(57, 'HISTOPATH', 'Histopathology & Cytology', 'Surgical specimen biopsy examination, frozen section analysis, and immunohistochemical staining', 'Basement 1, Core Lab', '157', 'active'),
(58, 'MICROBIO', 'Microbiology & Serology', 'Aerobic/anaerobic blood cultures, automated antimicrobial sensitivity, and fungal smears', 'Basement 1, Core Lab', '158', 'active'),
(59, 'BIOCHEM', 'Clinical Biochemistry', 'Automated high-throughput serum chemistry, cardiac troponin, and therapeutic drug monitoring', 'Basement 1, Core Lab', '159', 'active'),
(60, 'BLOODBANK', 'Transfusion Medicine & Blood Bank', 'Fractionation into PRBC, platelets, and FFP, apheresis donations, and cross-match verification', 'Ground Floor, Wing A', '160', 'active'),
(61, 'MOL_PATH', 'Molecular Pathology & RT-PCR', 'Quantitative real-time PCR for viral loads, somatic cancer gene sequencing, and infectious DNA', 'Basement 1, Molecular Lab', '161', 'active'),
(62, 'NUCL_MED', 'Nuclear Medicine & PET-CT', 'Whole-body oncologic PET-CT imaging, radioiodine thyroid therapy, and myocardial perfusion SPECT', 'Basement 2, Imaging Hub', '162', 'active'),
(63, 'INT_RAD', 'Interventional Radiology', 'Minimally invasive catheter embolization, radiofrequency tumor ablation, and peripheral stenting', 'Basement 1, Cath Lab', '163', 'active'),
(64, 'NEURO_DIAG', 'Neurophysiology Diagnostics', 'Routine/video electroencephalography (EEG), electromyography (EMG), and nerve conduction studies', 'Floor 2, Neuro Suite', '164', 'active'),
(65, 'CARD_DIAG', 'Non-Invasive Cardiac Diagnostics', '12-lead ECG, 2D speckle tracking echocardiography, treadmill stress test, and 24-hr Holter', 'First Floor, Heart Center', '165', 'active'),
(66, 'PULM_DIAG', 'Pulmonary Diagnostics & Spirometry', 'Body plethysmography, diffusion capacity (DLCO), fractional exhaled nitric oxide (FeNO), and 6MWT', 'First Floor, Suite 166', '166', 'active'),
(67, 'PHYSIO', 'Physical Therapy & Rehabilitation', 'Post-arthroplasty physical reconditioning, stroke motor retraining, and cardiopulmonary rehab', 'Ground Floor, Rehab Wing', '167', 'active'),
(68, 'OCCUP_THR', 'Occupational Therapy', 'Activities of daily living adaptation, pediatric sensory integration, and upper extremity splinting', 'Ground Floor, Rehab Wing', '168', 'active'),
(69, 'SPEECH_THR', 'Speech & Swallowing Therapy', 'Fiberoptic endoscopic evaluation of swallowing (FEES), aphasia recovery, and fluency therapy', 'Ground Floor, Suite 169', '169', 'active'),
(70, 'DIETETICS', 'Clinical Nutrition & Dietetics', 'Total parenteral feeding formulation, renal diabetic menu planning, and medical nutritional therapy', 'First Floor, Suite 170', '170', 'active'),
(71, 'RAD_ONC', 'Radiation Oncology', 'Image-guided radiation therapy (IGRT), intensity-modulated radiation (IMRT), and brachytherapy', 'Basement 2, Bunker 1', '171', 'active'),
(72, 'HYPERBARIC', 'Hyperbaric Oxygen Therapy', 'Multiplace hyperbaric chamber oxygen therapy for chronic non-healing diabetic foot wounds and radiation necrosis', 'Basement 1, Hyperbaric Suite', '172', 'active'),
(73, 'HEMODIAL', 'Daycare Hemodialysis Unit', 'High-flux bicarbonate outpatient hemodialysis stations and vascular access surveillance', 'Floor 3, Dialysis Wing', '173', 'active'),
(74, 'DAY_SURG', 'Ambulatory Day Surgery Unit', 'Short-stay same-day surgical preparation, minimal access procedures, and same-day recovery discharge', 'Floor 2, Day Surgery', '174', 'active'),
(75, 'DENTAL', 'Dental & Prosthodontics', 'Endodontics, maxillofacial prosthetics, periodontal therapy, and digital orthopantomography', 'Ground Floor, Suite 175', '175', 'active'),
(76, 'AUDIOLOGY', 'Audiology & Hearing Sciences', 'Pure tone audiometry, brainstem evoked response audiometry (BERA), and digital hearing aid programming', 'Ground Floor, Suite 176', '176', 'active'),
(77, 'OPTOMETRY', 'Optometry & Low Vision Care', 'Automated computer refraction, corneal topography, visual fields, and contact lens fitting', 'Ground Floor, Suite 177', '177', 'active'),
(78, 'MED_ADMIN', 'Medical Administration & Quality', 'NABH accreditation compliance, medical credentialing, clinical audit oversight, and hospital SOPs', 'Floor 5, Administrative Block', '178', 'active'),
(79, 'NURS_ADM', 'Nursing Administration & Services', 'Nursing staff deployment rosters, nurse-patient ratio management, and clinical bedside training', 'Floor 5, Nursing Directorate', '179', 'active'),
(80, 'MED_RECS', 'Medical Records & Health Informatics', 'ICD-10 clinical coding, physical chart archiving, electronic health record auditing, and medicolegal records', 'Basement 1, Records Wing', '180', 'active'),
(81, 'INFECT_CTL', 'Hospital Infection Prevention & Control', 'Healthcare-associated infection surveillance, environmental bioculture audits, and biohazard isolation protocol', 'Floor 5, Quality Cell', '181', 'active'),
(82, 'BIOMED', 'Biomedical Engineering', 'Medical device calibration, preventive equipment maintenance, breakdown repair, and electrical safety testing', 'Basement 2, Engineering Hub', '182', 'active'),
(83, 'CSSD', 'Central Sterile Services Department', 'Surgical instrument ultrasonic washing, ethylene oxide and high-pressure steam autoclaving of OT packs', 'Basement 1, CSSD Core', '183', 'active'),
(84, 'PAT_SERV', 'Patient Experience & Public Relations', 'Central admission facilitation, patient grievance redressal, multilingual navigation, and concierge helpdesks', 'Ground Floor, Main Lobby', '184', 'active'),
(85, 'MED_SOCIAL', 'Medical Social Work & Charity', 'Low-income government health scheme enrollment, indigent patient financial grants, and NGO rehabilitation links', 'Ground Floor, Wing B', '185', 'active'),
(86, 'BILL_ACCTS', 'Hospital Billing & Patient Accounts', 'Cashless insurance TPA pre-authorization, inpatient charge auditing, payment gateways, and discharge billing', 'Ground Floor, Billing Atrium', '186', 'active'),
(87, 'LEGAL_COMP', 'Medicolegal & Statutory Compliance', 'Clinical informed consent compliance, statutory regulatory reporting, organ donor legal clearance, police liaison', 'Floor 5, Legal Office', '187', 'active'),
(88, 'HOSP_IT', 'Health Informatics & IT Systems', 'Hospital ERP infrastructure, database replication, serverless deployment pipelines, and cybersecurity defense', 'Floor 5, IT Datacenter', '188', 'active'),
(89, 'FACILITIES', 'Facilities, Safety & Disaster Management', 'Medical gas pipeline system monitoring, fire safety automation, HVAC HEPA filtration, and code blue protocols', 'Basement 2, Plant Room', '189', 'active');

-- 7. Staff Profiles
INSERT INTO `staff_profiles` (`id`, `user_id`, `employee_id`, `first_name`, `last_name`, `gender`, `phone`, `department_id`, `designation`, `join_date`) VALUES
(1, 1, 'EMP-SA-001', 'Alexander', 'Wright', 'male', '+91 98765 00001', 2, 'Chief Information Officer', '2022-01-15'),
(2, 2, 'EMP-HA-002', 'Meenakshi', 'Sundaram', 'female', '+91 98765 00002', 2, 'Hospital Medical Director', '2022-03-01'),
(3, 6, 'EMP-REC-006', 'Pooja', 'Nair', 'female', '+91 98765 00006', 2, 'Head Receptionist', '2023-02-10'),
(4, 7, 'EMP-NUR-007', 'Sarah', 'Mathew', 'female', '+91 98765 00007', 3, 'Senior Charge Nurse', '2023-04-15'),
(5, 8, 'EMP-LAB-008', 'Vikram', 'Singh', 'male', '+91 98765 00008', 14, 'Senior Lab Technologist', '2023-01-20'),
(6, 9, 'EMP-PH-009', 'Rohit', 'Agarwal', 'male', '+91 98765 00009', 15, 'Chief Pharmacist', '2023-03-12'),
(7, 10, 'EMP-BIL-010', 'Sneha', 'Kapoor', 'female', '+91 98765 00010', 2, 'Senior Billing Executive', '2023-05-18');

-- 8. Doctor Profiles
INSERT INTO `doctor_profiles` (`id`, `user_id`, `doctor_code`, `first_name`, `last_name`, `specialization`, `qualification`, `license_number`, `consultation_fee`, `department_id`, `room_no`, `availability_status`, `avg_consultation_time_mins`) VALUES
(1, 3, 'DOC-CARD-001', 'Rajesh', 'Sharma', 'Interventional Cardiology', 'MBBS, MD (Medicine), DM (Cardiology)', 'MCI-CARD-44120', 800.00, 9, 'Room 109', 'available', 18),
(2, 4, 'DOC-MED-002', 'Priya', 'Patel', 'Internal Medicine & Diabetology', 'MBBS, MD (General Medicine)', 'MCI-MED-55210', 500.00, 4, 'Room 104', 'available', 15),
(3, 5, 'DOC-ORTHO-003', 'Anand', 'Kumar', 'Orthopedic Surgery & Traumatology', 'MBBS, MS (Orthopedics), MCh', 'MCI-ORT-77891', 750.00, 8, 'Room 108', 'available', 20);

-- Doctor secondary affiliations
INSERT INTO `doctor_departments` (`doctor_id`, `department_id`) VALUES
(1, 1), -- Dr Sharma on-call for Emergency
(2, 2), -- Dr Priya on-call for OPD
(3, 1); -- Dr Anand on-call for Emergency trauma

-- Doctor Schedules
INSERT INTO `doctor_availability` (`doctor_id`, `day_of_week`, `start_time`, `end_time`, `slot_duration_minutes`, `max_patients_per_session`, `status`) VALUES
(1, 'Monday', '09:00:00', '14:00:00', 20, 15, 'available'),
(1, 'Tuesday', '09:00:00', '14:00:00', 20, 15, 'available'),
(1, 'Wednesday', '09:00:00', '14:00:00', 20, 15, 'available'),
(1, 'Thursday', '09:00:00', '14:00:00', 20, 15, 'available'),
(1, 'Friday', '09:00:00', '14:00:00', 20, 15, 'available'),
(2, 'Monday', '08:30:00', '13:30:00', 15, 20, 'available'),
(2, 'Tuesday', '08:30:00', '13:30:00', 15, 20, 'available'),
(2, 'Wednesday', '08:30:00', '13:30:00', 15, 20, 'available'),
(2, 'Thursday', '08:30:00', '13:30:00', 15, 20, 'available'),
(2, 'Friday', '08:30:00', '13:30:00', 15, 20, 'available'),
(3, 'Monday', '10:00:00', '15:00:00', 20, 15, 'available'),
(3, 'Wednesday', '10:00:00', '15:00:00', 20, 15, 'available'),
(3, 'Friday', '10:00:00', '15:00:00', 20, 15, 'available');

-- 9. Patient Profiles
INSERT INTO `patient_profiles` (`id`, `user_id`, `patient_id`, `first_name`, `last_name`, `dob`, `gender`, `blood_group`, `phone`, `email`, `address`, `emergency_contact_name`, `emergency_contact_phone`, `allergies`, `medical_history`, `created_at`) VALUES
(1, 11, 'PAT-2026-0001', 'Arun', 'Verma', '1985-06-14', 'male', 'B+', '+91 98111 22334', 'arun.verma@example.com', '45 MG Road, Indiranagar, Bengaluru', 'Sunita Verma (Wife)', '+91 98111 99887', 'Penicillin', 'Mild hypertension since 2021', '2026-01-10 10:00:00'),
(2, 12, 'PAT-2026-0002', 'Sunita', 'Rao', '1992-09-22', 'female', 'O+', '+91 98222 33445', 'sunita.rao@example.com', '12 Lake View Road, Koramangala, Bengaluru', 'Kavita Rao (Mother)', '+91 98222 88776', 'Sulfa drugs', 'Seasonal asthma', '2026-01-15 11:30:00'),
(3, NULL, 'PAT-2026-0003', 'Mohammed', 'Farooq', '1974-03-08', 'male', 'A+', '+91 98333 44556', 'm.farooq@example.com', '88 Commercial Street, Bengaluru', 'Amina Farooq (Daughter)', '+91 98333 77665', 'None', 'Type 2 Diabetes Mellitus, Dyslipidemia', '2026-02-01 09:15:00'),
(4, NULL, 'PAT-2026-0004', 'Anita', 'Desai', '1968-11-30', 'female', 'AB+', '+91 98444 55667', 'anita.desai@example.com', '104 Brigade Gateway, Malleshwaram, Bengaluru', 'Ramesh Desai (Husband)', '+91 98444 66554', 'NSAIDs / Aspirin', 'Coronary artery disease, stent placed 2023', '2026-02-12 14:20:00'),
(5, NULL, 'PAT-2026-0005', 'Vikram', 'Malhotra', '2001-07-19', 'male', 'O-', '+91 98555 66778', 'vikram.m@example.com', '23 HSR Layout Sector 2, Bengaluru', 'Sanjay Malhotra (Father)', '+91 98555 55443', 'None', 'Sports fracture left tibia, under orthopedic rehab', '2026-02-20 16:45:00');

-- 10. Appointments
INSERT INTO `appointments` (`id`, `appointment_number`, `patient_id`, `doctor_id`, `department_id`, `appointment_date`, `appointment_time`, `reason`, `status`, `created_by`, `created_at`) VALUES
(1, 'APT-2026-0001', 1, 1, 9, CURDATE(), '09:30:00', 'Chest heaviness and routine cardiac review', 'checked_in', 6, NOW() - INTERVAL 2 HOUR),
(2, 'APT-2026-0002', 2, 2, 4, CURDATE(), '10:00:00', 'Fever, sore throat and fatigue for 3 days', 'in_consultation', 6, NOW() - INTERVAL 1 HOUR),
(3, 'APT-2026-0003', 3, 2, 4, CURDATE(), '10:30:00', 'Fasting blood glucose followup and prescription refill', 'confirmed', 6, NOW() - INTERVAL 3 HOUR),
(4, 'APT-2026-0004', 4, 1, 9, CURDATE(), '11:00:00', 'Post-stent annual cardiology evaluation', 'confirmed', 6, NOW() - INTERVAL 4 HOUR),
(5, 'APT-2026-0005', 5, 3, 8, CURDATE(), '11:30:00', 'Knee pain and left tibial rehab assessment', 'confirmed', 6, NOW() - INTERVAL 5 HOUR),
(6, 'APT-2026-0006', 1, 2, 4, CURDATE() + INTERVAL 2 DAY, '09:00:00', 'General health checkup', 'pending', 6, NOW());

-- 11. Patient Encounters
INSERT INTO `patient_encounters` (`id`, `encounter_number`, `patient_id`, `appointment_id`, `doctor_id`, `department_id`, `encounter_type`, `status`, `started_at`) VALUES
(1, 'ENC-2026-0001', 1, 1, 1, 9, 'outpatient', 'active', NOW() - INTERVAL 45 MINUTE),
(2, 'ENC-2026-0002', 2, 2, 2, 4, 'outpatient', 'active', NOW() - INTERVAL 20 MINUTE),
(3, 'ENC-2026-0003', 4, NULL, 1, 9, 'inpatient', 'active', NOW() - INTERVAL 2 DAY);

-- 12. Consultations & Diagnoses
INSERT INTO `consultations` (`id`, `encounter_id`, `patient_id`, `doctor_id`, `vitals_bp`, `vitals_pulse`, `vitals_temp`, `vitals_weight`, `vitals_spo2`, `clinical_notes`, `treatment_plan`, `follow_up_date`, `created_at`) VALUES
(1, 3, 4, 1, '138/88', 76, 98.4, 68.5, 98, 'Patient presented with mild exertional angina and shortness of breath. History of coronary stenting 2023. ECG showed sinus rhythm with non-specific ST changes.', 'Continue antiplatelet and statin regimen. Prescribe nitrates for emergency relief. Ordered Lipid Panel, Troponin-I, and 2D Echo.', CURDATE() + INTERVAL 14 DAY, NOW() - INTERVAL 2 DAY);

INSERT INTO `diagnoses` (`id`, `consultation_id`, `patient_id`, `diagnosis_code`, `diagnosis_title`, `diagnosis_type`, `notes`, `created_at`) VALUES
(1, 1, 4, 'I25.10', 'Atherosclerotic heart disease of native coronary artery', 'primary', 'Post-PCI follow-up with exertional symptoms', NOW() - INTERVAL 2 DAY),
(2, 1, 4, 'I10', 'Essential (primary) hypertension', 'secondary', 'Controlled on dual antihypertensives', NOW() - INTERVAL 2 DAY);

-- 13. Laboratory Catalog
INSERT INTO `lab_tests` (`id`, `test_code`, `test_name`, `department_id`, `standard_charge`, `normal_range`, `units`, `description`) VALUES
(1, 'LAB-CBC', 'Complete Blood Count (CBC)', 14, 450.00, 'Hb: 13-17 (M), 12-15 (F)', 'g/dL', 'RBC, WBC, Hemoglobin, Platelets, Hematocrit indices'),
(2, 'LAB-LIPID', 'Comprehensive Lipid Profile', 14, 850.00, 'Total Chol: <200, LDL: <100', 'mg/dL', 'Total Cholesterol, HDL, LDL, VLDL, Triglycerides'),
(3, 'LAB-FBS', 'Fasting Blood Sugar (FBS)', 14, 150.00, '70 - 99', 'mg/dL', 'Fasting blood glucose level after 8-10 hour fast'),
(4, 'LAB-HBA1C', 'Glycated Hemoglobin (HbA1c)', 14, 600.00, '< 5.7 (Normal), 5.7-6.4 (Prediabetes)', '%', '3-month average blood glucose control indicator'),
(5, 'LAB-LFT', 'Liver Function Test (LFT)', 14, 950.00, 'SGPT: <45, SGOT: <40, Bilirubin: <1.2', 'U/L', 'Bilirubin, SGOT, SGPT, ALP, Total Proteins, Albumin'),
(6, 'LAB-RFT', 'Renal Function Test (KFT/RFT)', 14, 750.00, 'Creatinine: 0.6-1.2, Urea: 15-45', 'mg/dL', 'Serum Creatinine, Blood Urea, Electrolytes, Uric Acid'),
(7, 'LAB-TROP', 'Cardiac Troponin-I Quantitative', 14, 1400.00, '< 0.04 (Negative)', 'ng/mL', 'Specific cardiac muscle biomarker for acute myocardial injury'),
(8, 'RAD-XRAY', 'Digital Chest X-Ray PA View', 13, 650.00, 'Cardiothoracic ratio normal', 'Visual', 'Evaluation of lung parenchyma, pleura, and cardiac silhouette');

-- Lab Orders & Results
INSERT INTO `lab_orders` (`id`, `order_number`, `patient_id`, `encounter_id`, `ordered_by_doctor_id`, `priority`, `status`, `clinical_notes`, `created_at`) VALUES
(1, 'LBO-2026-0001', 4, 3, 1, 'urgent', 'completed', 'Rule out acute coronary syndrome; evaluate lipid target', NOW() - INTERVAL 1 DAY),
(2, 'LBO-2026-0002', 1, 1, 1, 'routine', 'processing', 'Routine lipid screening and cardiac check', NOW() - INTERVAL 35 MINUTE);

INSERT INTO `lab_order_items` (`id`, `lab_order_id`, `test_id`, `status`, `result_value`, `reference_range`, `result_notes`, `tested_by`, `verified_by`, `completed_at`) VALUES
(1, 1, 7, 'completed', '0.01', '< 0.04', 'Negative for acute myocardial necrosis', 8, 2, NOW() - INTERVAL 22 HOUR),
(2, 1, 2, 'completed', 'Total Chol: 218, LDL: 132, HDL: 44, TG: 185', 'Total <200, LDL <100', 'Borderline elevated LDL cholesterol', 8, 2, NOW() - INTERVAL 22 HOUR),
(3, 2, 2, 'analyzing', NULL, 'Total <200, LDL <100', 'Sample in centrifuge and autoanalyzer', 8, NULL, NULL);

-- 14. Pharmacy Catalog & Stock
INSERT INTO `medicines` (`id`, `medicine_code`, `name`, `generic_name`, `category`, `unit`, `unit_price`, `stock_quantity`, `reorder_level`, `status`) VALUES
(1, 'MED-PCM-500', 'Paracetamol 500mg (Crocin)', 'Paracetamol', 'Tablet', 'strip (10s)', 35.00, 350, 50, 'active'),
(2, 'MED-AMX-500', 'Amoxicillin & Clavulanate 625mg (Augmentin)', 'Amoxicillin + Clavulanic Acid', 'Tablet', 'strip (6s)', 160.00, 180, 40, 'active'),
(3, 'MED-MET-500', 'Metformin 500mg SR (Glycomet)', 'Metformin Hydrochloride', 'Tablet', 'strip (10s)', 45.00, 240, 50, 'active'),
(4, 'MED-ATO-20', 'Atorvastatin 20mg (Atorva)', 'Atorvastatin Calcium', 'Tablet', 'strip (10s)', 120.00, 195, 30, 'active'),
(5, 'MED-PAN-40', 'Pantoprazole 40mg (Pan-40)', 'Pantoprazole Sodium', 'Tablet', 'strip (10s)', 85.00, 310, 40, 'active'),
(6, 'MED-AZI-500', 'Azithromycin 500mg (Azee)', 'Azithromycin', 'Tablet', 'strip (5s)', 130.00, 12, 30, 'active'), -- Low stock alert!
(7, 'MED-SOR-10', 'Sorbitrate 10mg (Isordil)', 'Isosorbide Dinitrate', 'Tablet', 'strip (10s)', 40.00, 85, 20, 'active'),
(8, 'MED-CEF-1G', 'Ceftriaxone 1g Injection (Monocef)', 'Ceftriaxone Sodium', 'Injection', 'vial', 95.00, 75, 25, 'active');

-- Medicine Batches
INSERT INTO `medicine_batches` (`id`, `medicine_id`, `batch_number`, `expiry_date`, `quantity`, `purchase_cost`, `created_at`) VALUES
(1, 1, 'PCM-2026-A1', '2027-12-31', 350, 22.00, NOW() - INTERVAL 30 DAY),
(2, 2, 'AUG-2026-B4', '2027-08-31', 180, 110.00, NOW() - INTERVAL 20 DAY),
(3, 4, 'ATV-2026-C2', '2027-05-31', 195, 75.00, NOW() - INTERVAL 15 DAY),
(4, 6, 'AZI-2025-E9', '2026-11-15', 12, 85.00, NOW() - INTERVAL 60 DAY); -- Near expiry alert!

-- 15. Prescriptions
INSERT INTO `prescriptions` (`id`, `encounter_id`, `consultation_id`, `patient_id`, `doctor_id`, `notes`, `status`, `created_at`) VALUES
(1, 3, 1, 4, 1, 'Take statin at bedtime. Use Sorbitrate sublingually only if acute chest discomfort occurs.', 'dispensed', NOW() - INTERVAL 2 DAY);

INSERT INTO `prescription_items` (`id`, `prescription_id`, `medicine_id`, `dosage`, `frequency`, `duration_days`, `quantity`, `instructions`) VALUES
(1, 1, 4, '20mg', '0-0-1 (Night)', 30, 3, 'After dinner before sleep'),
(2, 1, 7, '10mg', 'SOS (As needed)', 10, 1, 'Place sublingually under tongue for sudden chest tightness');

-- Pharmacy Orders & Dispensing
INSERT INTO `pharmacy_orders` (`id`, `order_number`, `prescription_id`, `patient_id`, `status`, `total_amount`, `created_at`) VALUES
(1, 'PHO-2026-0001', 1, 4, 'dispensed', 400.00, NOW() - INTERVAL 2 DAY);

INSERT INTO `pharmacy_order_items` (`id`, `pharmacy_order_id`, `medicine_id`, `batch_id`, `quantity`, `unit_price`, `total_price`) VALUES
(1, 1, 4, 3, 3, 120.00, 360.00),
(2, 1, 7, NULL, 1, 40.00, 40.00);

INSERT INTO `pharmacy_dispensing_records` (`id`, `pharmacy_order_id`, `dispensed_by`, `dispensing_notes`, `dispensed_at`) VALUES
(1, 1, 9, 'Dispensed with proper dosage counseling on bedtime statin adherence.', NOW() - INTERVAL 2 DAY);

-- 16. BedMatrix: Wards, Rooms & Beds (350 Beds Scaled Architecture)
INSERT INTO `wards` (`id`, `ward_code`, `name`, `ward_type`, `care_level`, `capacity`, `gender_designation`, `description`) VALUES
(1, 'ICU-CRIT', 'Intensive Coronary Care Unit (ICCU)', 'icu', 'critical', 30, 'all', 'Advanced continuous hemodynamic telemetry, mechanical ventilators, invasive arterial monitoring'),
(2, 'WARD-MGEN', 'Male General Ward', 'general', 'standard', 60, 'male_only', 'Post-operative and general medical recovery ward for adult male patients'),
(3, 'WARD-FGEN', 'Female General Ward', 'general', 'standard', 60, 'female_only', 'Inpatient medical and post-surgical recovery for adult female patients'),
(4, 'WARD-PED', 'Pediatric Care Ward', 'pediatric', 'intermediate', 40, 'pediatric', 'Child-friendly recovery rooms with 24/7 dedicated pediatric nursing'),
(5, 'WARD-POSTOP', 'Surgical Post-Op Recovery', 'post_op', 'intermediate', 40, 'all', 'Close surgical observation following major orthopedics and general surgeries'),
(6, 'WARD-MAT', 'Maternity & Neonatal Ward', 'maternity', 'standard', 40, 'female_only', 'Comprehensive obstetric delivery suites, postpartum recovery, and neonatal care'),
(7, 'WARD-ORTHO', 'Orthopedics & Trauma Care Ward', 'general', 'intermediate', 40, 'all', 'Specialized orthopedic traction beds, joint replacement recovery, and polytrauma care'),
(8, 'WARD-ISO', 'Isolation & Respiratory Unit', 'isolation', 'critical', 40, 'all', 'Negative-pressure isolation suites, specialized airborne infection control, and respiratory care');

-- Rooms
INSERT INTO `rooms` (`id`, `ward_id`, `room_number`, `room_type`, `standard_rate_per_day`) VALUES
(1, 1, 'ICU-101', 'single', 5000.00),
(2, 1, 'ICU-102', 'single', 5000.00),
(3, 2, 'MG-201', 'shared', 1200.00),
(4, 2, 'MG-202', 'shared', 1200.00),
(5, 3, 'FG-301', 'shared', 1200.00),
(6, 4, 'PED-401', 'double', 1800.00),
(7, 5, 'PO-501', 'single', 2500.00),
(8, 1, 'ICU-103', 'double', 5000.00),
(9, 1, 'ICU-104', 'double', 5000.00),
(10, 1, 'ICU-105', 'double', 5000.00),
(11, 1, 'ICU-106', 'double', 5000.00),
(12, 1, 'ICU-107', 'double', 5000.00),
(13, 1, 'ICU-108', 'double', 5000.00),
(14, 1, 'ICU-109', 'double', 5000.00),
(15, 1, 'ICU-110', 'double', 5000.00),
(16, 1, 'ICU-111', 'double', 5000.00),
(17, 1, 'ICU-112', 'double', 5000.00),
(18, 1, 'ICU-113', 'double', 5000.00),
(19, 1, 'ICU-114', 'double', 5000.00),
(20, 1, 'ICU-115', 'double', 5000.00),
(21, 2, 'MG-203', 'shared', 1200.00),
(22, 2, 'MG-204', 'shared', 1200.00),
(23, 2, 'MG-205', 'shared', 1200.00),
(24, 2, 'MG-206', 'shared', 1200.00),
(25, 2, 'MG-207', 'shared', 1200.00),
(26, 2, 'MG-208', 'shared', 1200.00),
(27, 2, 'MG-209', 'shared', 1200.00),
(28, 2, 'MG-210', 'shared', 1200.00),
(29, 2, 'MG-211', 'shared', 1200.00),
(30, 2, 'MG-212', 'shared', 1200.00),
(31, 2, 'MG-213', 'shared', 1200.00),
(32, 2, 'MG-214', 'shared', 1200.00),
(33, 2, 'MG-215', 'shared', 1200.00),
(34, 3, 'FG-302', 'shared', 1200.00),
(35, 3, 'FG-303', 'shared', 1200.00),
(36, 3, 'FG-304', 'shared', 1200.00),
(37, 3, 'FG-305', 'shared', 1200.00),
(38, 3, 'FG-306', 'shared', 1200.00),
(39, 3, 'FG-307', 'shared', 1200.00),
(40, 3, 'FG-308', 'shared', 1200.00),
(41, 3, 'FG-309', 'shared', 1200.00),
(42, 3, 'FG-310', 'shared', 1200.00),
(43, 3, 'FG-311', 'shared', 1200.00),
(44, 3, 'FG-312', 'shared', 1200.00),
(45, 3, 'FG-313', 'shared', 1200.00),
(46, 3, 'FG-314', 'shared', 1200.00),
(47, 3, 'FG-315', 'shared', 1200.00),
(48, 4, 'PED-402', 'shared', 1800.00),
(49, 4, 'PED-403', 'shared', 1800.00),
(50, 4, 'PED-404', 'shared', 1800.00),
(51, 4, 'PED-405', 'shared', 1800.00),
(52, 4, 'PED-406', 'shared', 1800.00),
(53, 4, 'PED-407', 'shared', 1800.00),
(54, 4, 'PED-408', 'shared', 1800.00),
(55, 4, 'PED-409', 'shared', 1800.00),
(56, 4, 'PED-410', 'shared', 1800.00),
(57, 5, 'PO-502', 'shared', 2500.00),
(58, 5, 'PO-503', 'shared', 2500.00),
(59, 5, 'PO-504', 'shared', 2500.00),
(60, 5, 'PO-505', 'shared', 2500.00),
(61, 5, 'PO-506', 'shared', 2500.00),
(62, 5, 'PO-507', 'shared', 2500.00),
(63, 5, 'PO-508', 'shared', 2500.00),
(64, 5, 'PO-509', 'shared', 2500.00),
(65, 5, 'PO-510', 'shared', 2500.00),
(66, 6, 'MAT-601', 'shared', 1800.00),
(67, 6, 'MAT-602', 'shared', 1800.00),
(68, 6, 'MAT-603', 'shared', 1800.00),
(69, 6, 'MAT-604', 'shared', 1800.00),
(70, 6, 'MAT-605', 'shared', 1800.00),
(71, 6, 'MAT-606', 'shared', 1800.00),
(72, 6, 'MAT-607', 'shared', 1800.00),
(73, 6, 'MAT-608', 'shared', 1800.00),
(74, 6, 'MAT-609', 'shared', 1800.00),
(75, 6, 'MAT-610', 'shared', 1800.00),
(76, 7, 'ORT-701', 'shared', 2000.00),
(77, 7, 'ORT-702', 'shared', 2000.00),
(78, 7, 'ORT-703', 'shared', 2000.00),
(79, 7, 'ORT-704', 'shared', 2000.00),
(80, 7, 'ORT-705', 'shared', 2000.00),
(81, 7, 'ORT-706', 'shared', 2000.00),
(82, 7, 'ORT-707', 'shared', 2000.00),
(83, 7, 'ORT-708', 'shared', 2000.00),
(84, 7, 'ORT-709', 'shared', 2000.00),
(85, 7, 'ORT-710', 'shared', 2000.00),
(86, 8, 'ISO-801', 'shared', 3500.00),
(87, 8, 'ISO-802', 'shared', 3500.00),
(88, 8, 'ISO-803', 'shared', 3500.00),
(89, 8, 'ISO-804', 'shared', 3500.00),
(90, 8, 'ISO-805', 'shared', 3500.00),
(91, 8, 'ISO-806', 'shared', 3500.00),
(92, 8, 'ISO-807', 'shared', 3500.00),
(93, 8, 'ISO-808', 'shared', 3500.00),
(94, 8, 'ISO-809', 'shared', 3500.00),
(95, 8, 'ISO-810', 'shared', 3500.00);

-- Beds (Statuses: available, occupied, reserved, cleaning, out_of_service)
INSERT INTO `beds` (`id`, `room_id`, `ward_id`, `bed_number`, `bed_type`, `care_level`, `status`, `daily_rate`, `created_at`) VALUES
(1, 1, 1, 'BED-ICU-01', 'ventilator_enabled', 'critical', 'occupied', 5500.00, NOW()),
(2, 1, 1, 'BED-ICU-02', 'ventilator_enabled', 'critical', 'available', 5500.00, NOW()),
(3, 2, 1, 'BED-ICU-03', 'icu_bed', 'critical', 'cleaning', 5000.00, NOW()),
(4, 2, 1, 'BED-ICU-04', 'icu_bed', 'critical', 'reserved', 5000.00, NOW()),
(5, 3, 2, 'BED-MG-01', 'standard', 'standard', 'available', 1200.00, NOW()),
(6, 3, 2, 'BED-MG-02', 'standard', 'standard', 'occupied', 1200.00, NOW()),
(7, 3, 2, 'BED-MG-03', 'standard', 'standard', 'available', 1200.00, NOW()),
(8, 4, 2, 'BED-MG-04', 'standard', 'standard', 'out_of_service', 1200.00, NOW()),
(9, 5, 3, 'BED-FG-01', 'standard', 'standard', 'available', 1200.00, NOW()),
(10, 5, 3, 'BED-FG-02', 'standard', 'standard', 'available', 1200.00, NOW()),
(11, 7, 5, 'BED-PO-01', 'standard', 'intermediate', 'available', 2500.00, NOW()),
(12, 7, 5, 'BED-PO-02', 'ventilator_enabled', 'critical', 'available', 3000.00, NOW()),
(13, 8, 1, 'BED-ICU-05', 'icu_bed', 'critical', 'available', 5200.00, NOW()),
(14, 8, 1, 'BED-ICU-06', 'ventilator_enabled', 'critical', 'occupied', 5200.00, NOW()),
(15, 9, 1, 'BED-ICU-07', 'icu_bed', 'critical', 'available', 5200.00, NOW()),
(16, 9, 1, 'BED-ICU-08', 'icu_bed', 'critical', 'available', 5200.00, NOW()),
(17, 10, 1, 'BED-ICU-09', 'ventilator_enabled', 'critical', 'available', 5200.00, NOW()),
(18, 10, 1, 'BED-ICU-10', 'icu_bed', 'critical', 'available', 5200.00, NOW()),
(19, 11, 1, 'BED-ICU-11', 'icu_bed', 'critical', 'available', 5200.00, NOW()),
(20, 11, 1, 'BED-ICU-12', 'ventilator_enabled', 'critical', 'occupied', 5200.00, NOW()),
(21, 12, 1, 'BED-ICU-13', 'icu_bed', 'critical', 'available', 5200.00, NOW()),
(22, 12, 1, 'BED-ICU-14', 'icu_bed', 'critical', 'available', 5200.00, NOW()),
(23, 13, 1, 'BED-ICU-15', 'ventilator_enabled', 'critical', 'available', 5200.00, NOW()),
(24, 13, 1, 'BED-ICU-16', 'icu_bed', 'critical', 'available', 5200.00, NOW()),
(25, 14, 1, 'BED-ICU-17', 'icu_bed', 'critical', 'cleaning', 5200.00, NOW()),
(26, 14, 1, 'BED-ICU-18', 'ventilator_enabled', 'critical', 'occupied', 5200.00, NOW()),
(27, 15, 1, 'BED-ICU-19', 'icu_bed', 'critical', 'available', 5200.00, NOW()),
(28, 15, 1, 'BED-ICU-20', 'icu_bed', 'critical', 'available', 5200.00, NOW()),
(29, 16, 1, 'BED-ICU-21', 'ventilator_enabled', 'critical', 'available', 5200.00, NOW()),
(30, 16, 1, 'BED-ICU-22', 'icu_bed', 'critical', 'available', 5200.00, NOW()),
(31, 17, 1, 'BED-ICU-23', 'icu_bed', 'critical', 'available', 5200.00, NOW()),
(32, 17, 1, 'BED-ICU-24', 'ventilator_enabled', 'critical', 'occupied', 5200.00, NOW()),
(33, 18, 1, 'BED-ICU-25', 'icu_bed', 'critical', 'available', 5200.00, NOW()),
(34, 18, 1, 'BED-ICU-26', 'icu_bed', 'critical', 'available', 5200.00, NOW()),
(35, 19, 1, 'BED-ICU-27', 'ventilator_enabled', 'critical', 'available', 5200.00, NOW()),
(36, 19, 1, 'BED-ICU-28', 'icu_bed', 'critical', 'available', 5200.00, NOW()),
(37, 20, 1, 'BED-ICU-29', 'icu_bed', 'critical', 'reserved', 5200.00, NOW()),
(38, 20, 1, 'BED-ICU-30', 'ventilator_enabled', 'critical', 'occupied', 5200.00, NOW()),
(39, 4, 2, 'BED-MG-05', 'standard', 'standard', 'available', 1200.00, NOW()),
(40, 4, 2, 'BED-MG-06', 'standard', 'standard', 'occupied', 1200.00, NOW()),
(41, 4, 2, 'BED-MG-07', 'standard', 'standard', 'available', 1200.00, NOW()),
(42, 4, 2, 'BED-MG-08', 'standard', 'standard', 'available', 1200.00, NOW()),
(43, 21, 2, 'BED-MG-09', 'standard', 'standard', 'available', 1200.00, NOW()),
(44, 21, 2, 'BED-MG-10', 'bariatric', 'standard', 'available', 1200.00, NOW()),
(45, 21, 2, 'BED-MG-11', 'standard', 'standard', 'available', 1200.00, NOW()),
(46, 21, 2, 'BED-MG-12', 'standard', 'standard', 'occupied', 1200.00, NOW()),
(47, 22, 2, 'BED-MG-13', 'standard', 'standard', 'available', 1200.00, NOW()),
(48, 22, 2, 'BED-MG-14', 'standard', 'standard', 'available', 1200.00, NOW()),
(49, 22, 2, 'BED-MG-15', 'standard', 'standard', 'available', 1200.00, NOW()),
(50, 22, 2, 'BED-MG-16', 'standard', 'standard', 'available', 1200.00, NOW()),
(51, 23, 2, 'BED-MG-17', 'standard', 'standard', 'cleaning', 1200.00, NOW()),
(52, 23, 2, 'BED-MG-18', 'standard', 'standard', 'occupied', 1200.00, NOW()),
(53, 23, 2, 'BED-MG-19', 'standard', 'standard', 'available', 1200.00, NOW()),
(54, 23, 2, 'BED-MG-20', 'bariatric', 'standard', 'available', 1200.00, NOW()),
(55, 24, 2, 'BED-MG-21', 'standard', 'standard', 'available', 1200.00, NOW()),
(56, 24, 2, 'BED-MG-22', 'standard', 'standard', 'available', 1200.00, NOW()),
(57, 24, 2, 'BED-MG-23', 'standard', 'standard', 'available', 1200.00, NOW()),
(58, 24, 2, 'BED-MG-24', 'standard', 'standard', 'occupied', 1200.00, NOW()),
(59, 25, 2, 'BED-MG-25', 'standard', 'standard', 'available', 1200.00, NOW()),
(60, 25, 2, 'BED-MG-26', 'standard', 'standard', 'available', 1200.00, NOW()),
(61, 25, 2, 'BED-MG-27', 'standard', 'standard', 'available', 1200.00, NOW()),
(62, 25, 2, 'BED-MG-28', 'standard', 'standard', 'available', 1200.00, NOW()),
(63, 26, 2, 'BED-MG-29', 'standard', 'standard', 'reserved', 1200.00, NOW()),
(64, 26, 2, 'BED-MG-30', 'bariatric', 'standard', 'occupied', 1200.00, NOW()),
(65, 26, 2, 'BED-MG-31', 'standard', 'standard', 'available', 1200.00, NOW()),
(66, 26, 2, 'BED-MG-32', 'standard', 'standard', 'available', 1200.00, NOW()),
(67, 27, 2, 'BED-MG-33', 'standard', 'standard', 'available', 1200.00, NOW()),
(68, 27, 2, 'BED-MG-34', 'standard', 'standard', 'cleaning', 1200.00, NOW()),
(69, 27, 2, 'BED-MG-35', 'standard', 'standard', 'available', 1200.00, NOW()),
(70, 27, 2, 'BED-MG-36', 'standard', 'standard', 'occupied', 1200.00, NOW()),
(71, 28, 2, 'BED-MG-37', 'standard', 'standard', 'available', 1200.00, NOW()),
(72, 28, 2, 'BED-MG-38', 'standard', 'standard', 'available', 1200.00, NOW()),
(73, 28, 2, 'BED-MG-39', 'standard', 'standard', 'available', 1200.00, NOW()),
(74, 28, 2, 'BED-MG-40', 'bariatric', 'standard', 'available', 1200.00, NOW()),
(75, 29, 2, 'BED-MG-41', 'standard', 'standard', 'available', 1200.00, NOW()),
(76, 29, 2, 'BED-MG-42', 'standard', 'standard', 'occupied', 1200.00, NOW()),
(77, 29, 2, 'BED-MG-43', 'standard', 'standard', 'available', 1200.00, NOW()),
(78, 29, 2, 'BED-MG-44', 'standard', 'standard', 'available', 1200.00, NOW()),
(79, 30, 2, 'BED-MG-45', 'standard', 'standard', 'available', 1200.00, NOW()),
(80, 30, 2, 'BED-MG-46', 'standard', 'standard', 'available', 1200.00, NOW()),
(81, 30, 2, 'BED-MG-47', 'standard', 'standard', 'out_of_service', 1200.00, NOW()),
(82, 30, 2, 'BED-MG-48', 'standard', 'standard', 'occupied', 1200.00, NOW()),
(83, 31, 2, 'BED-MG-49', 'standard', 'standard', 'available', 1200.00, NOW()),
(84, 31, 2, 'BED-MG-50', 'bariatric', 'standard', 'available', 1200.00, NOW()),
(85, 31, 2, 'BED-MG-51', 'standard', 'standard', 'cleaning', 1200.00, NOW()),
(86, 31, 2, 'BED-MG-52', 'standard', 'standard', 'available', 1200.00, NOW()),
(87, 32, 2, 'BED-MG-53', 'standard', 'standard', 'available', 1200.00, NOW()),
(88, 32, 2, 'BED-MG-54', 'standard', 'standard', 'occupied', 1200.00, NOW()),
(89, 32, 2, 'BED-MG-55', 'standard', 'standard', 'available', 1200.00, NOW()),
(90, 32, 2, 'BED-MG-56', 'standard', 'standard', 'available', 1200.00, NOW()),
(91, 33, 2, 'BED-MG-57', 'standard', 'standard', 'available', 1200.00, NOW()),
(92, 33, 2, 'BED-MG-58', 'standard', 'standard', 'reserved', 1200.00, NOW()),
(93, 33, 2, 'BED-MG-59', 'standard', 'standard', 'available', 1200.00, NOW()),
(94, 33, 2, 'BED-MG-60', 'bariatric', 'standard', 'occupied', 1200.00, NOW()),
(95, 5, 3, 'BED-FG-03', 'standard', 'standard', 'available', 1200.00, NOW()),
(96, 5, 3, 'BED-FG-04', 'standard', 'standard', 'available', 1200.00, NOW()),
(97, 34, 3, 'BED-FG-05', 'standard', 'standard', 'available', 1200.00, NOW()),
(98, 34, 3, 'BED-FG-06', 'standard', 'standard', 'occupied', 1200.00, NOW()),
(99, 34, 3, 'BED-FG-07', 'standard', 'standard', 'available', 1200.00, NOW()),
(100, 34, 3, 'BED-FG-08', 'standard', 'standard', 'available', 1200.00, NOW()),
(101, 35, 3, 'BED-FG-09', 'standard', 'standard', 'available', 1200.00, NOW()),
(102, 35, 3, 'BED-FG-10', 'standard', 'standard', 'available', 1200.00, NOW()),
(103, 35, 3, 'BED-FG-11', 'standard', 'standard', 'available', 1200.00, NOW()),
(104, 35, 3, 'BED-FG-12', 'standard', 'standard', 'occupied', 1200.00, NOW()),
(105, 36, 3, 'BED-FG-13', 'standard', 'standard', 'available', 1200.00, NOW()),
(106, 36, 3, 'BED-FG-14', 'standard', 'standard', 'available', 1200.00, NOW()),
(107, 36, 3, 'BED-FG-15', 'standard', 'standard', 'available', 1200.00, NOW()),
(108, 36, 3, 'BED-FG-16', 'standard', 'standard', 'available', 1200.00, NOW()),
(109, 37, 3, 'BED-FG-17', 'standard', 'standard', 'cleaning', 1200.00, NOW()),
(110, 37, 3, 'BED-FG-18', 'standard', 'standard', 'occupied', 1200.00, NOW()),
(111, 37, 3, 'BED-FG-19', 'standard', 'standard', 'available', 1200.00, NOW()),
(112, 37, 3, 'BED-FG-20', 'standard', 'standard', 'available', 1200.00, NOW()),
(113, 38, 3, 'BED-FG-21', 'standard', 'standard', 'available', 1200.00, NOW()),
(114, 38, 3, 'BED-FG-22', 'standard', 'standard', 'available', 1200.00, NOW()),
(115, 38, 3, 'BED-FG-23', 'standard', 'standard', 'available', 1200.00, NOW()),
(116, 38, 3, 'BED-FG-24', 'standard', 'standard', 'occupied', 1200.00, NOW()),
(117, 39, 3, 'BED-FG-25', 'standard', 'standard', 'available', 1200.00, NOW()),
(118, 39, 3, 'BED-FG-26', 'standard', 'standard', 'available', 1200.00, NOW()),
(119, 39, 3, 'BED-FG-27', 'standard', 'standard', 'available', 1200.00, NOW()),
(120, 39, 3, 'BED-FG-28', 'standard', 'standard', 'available', 1200.00, NOW()),
(121, 40, 3, 'BED-FG-29', 'standard', 'standard', 'reserved', 1200.00, NOW()),
(122, 40, 3, 'BED-FG-30', 'standard', 'standard', 'occupied', 1200.00, NOW()),
(123, 40, 3, 'BED-FG-31', 'standard', 'standard', 'available', 1200.00, NOW()),
(124, 40, 3, 'BED-FG-32', 'standard', 'standard', 'available', 1200.00, NOW()),
(125, 41, 3, 'BED-FG-33', 'standard', 'standard', 'available', 1200.00, NOW()),
(126, 41, 3, 'BED-FG-34', 'standard', 'standard', 'cleaning', 1200.00, NOW()),
(127, 41, 3, 'BED-FG-35', 'standard', 'standard', 'available', 1200.00, NOW()),
(128, 41, 3, 'BED-FG-36', 'standard', 'standard', 'occupied', 1200.00, NOW()),
(129, 42, 3, 'BED-FG-37', 'standard', 'standard', 'available', 1200.00, NOW()),
(130, 42, 3, 'BED-FG-38', 'standard', 'standard', 'available', 1200.00, NOW()),
(131, 42, 3, 'BED-FG-39', 'standard', 'standard', 'available', 1200.00, NOW()),
(132, 42, 3, 'BED-FG-40', 'standard', 'standard', 'available', 1200.00, NOW()),
(133, 43, 3, 'BED-FG-41', 'standard', 'standard', 'available', 1200.00, NOW()),
(134, 43, 3, 'BED-FG-42', 'standard', 'standard', 'occupied', 1200.00, NOW()),
(135, 43, 3, 'BED-FG-43', 'standard', 'standard', 'available', 1200.00, NOW()),
(136, 43, 3, 'BED-FG-44', 'standard', 'standard', 'available', 1200.00, NOW()),
(137, 44, 3, 'BED-FG-45', 'standard', 'standard', 'available', 1200.00, NOW()),
(138, 44, 3, 'BED-FG-46', 'standard', 'standard', 'available', 1200.00, NOW()),
(139, 44, 3, 'BED-FG-47', 'standard', 'standard', 'out_of_service', 1200.00, NOW()),
(140, 44, 3, 'BED-FG-48', 'standard', 'standard', 'occupied', 1200.00, NOW()),
(141, 45, 3, 'BED-FG-49', 'standard', 'standard', 'available', 1200.00, NOW()),
(142, 45, 3, 'BED-FG-50', 'standard', 'standard', 'available', 1200.00, NOW()),
(143, 45, 3, 'BED-FG-51', 'standard', 'standard', 'cleaning', 1200.00, NOW()),
(144, 45, 3, 'BED-FG-52', 'standard', 'standard', 'available', 1200.00, NOW()),
(145, 46, 3, 'BED-FG-53', 'standard', 'standard', 'available', 1200.00, NOW()),
(146, 46, 3, 'BED-FG-54', 'standard', 'standard', 'occupied', 1200.00, NOW()),
(147, 46, 3, 'BED-FG-55', 'standard', 'standard', 'available', 1200.00, NOW()),
(148, 46, 3, 'BED-FG-56', 'standard', 'standard', 'available', 1200.00, NOW()),
(149, 47, 3, 'BED-FG-57', 'standard', 'standard', 'available', 1200.00, NOW()),
(150, 47, 3, 'BED-FG-58', 'standard', 'standard', 'reserved', 1200.00, NOW()),
(151, 47, 3, 'BED-FG-59', 'standard', 'standard', 'available', 1200.00, NOW()),
(152, 47, 3, 'BED-FG-60', 'standard', 'standard', 'occupied', 1200.00, NOW()),
(153, 6, 4, 'BED-PED-01', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(154, 6, 4, 'BED-PED-02', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(155, 6, 4, 'BED-PED-03', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(156, 6, 4, 'BED-PED-04', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(157, 48, 4, 'BED-PED-05', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(158, 48, 4, 'BED-PED-06', 'pediatric', 'intermediate', 'occupied', 1800.00, NOW()),
(159, 48, 4, 'BED-PED-07', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(160, 48, 4, 'BED-PED-08', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(161, 49, 4, 'BED-PED-09', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(162, 49, 4, 'BED-PED-10', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(163, 49, 4, 'BED-PED-11', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(164, 49, 4, 'BED-PED-12', 'pediatric', 'intermediate', 'occupied', 1800.00, NOW()),
(165, 50, 4, 'BED-PED-13', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(166, 50, 4, 'BED-PED-14', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(167, 50, 4, 'BED-PED-15', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(168, 50, 4, 'BED-PED-16', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(169, 51, 4, 'BED-PED-17', 'pediatric', 'intermediate', 'cleaning', 1800.00, NOW()),
(170, 51, 4, 'BED-PED-18', 'pediatric', 'intermediate', 'occupied', 1800.00, NOW()),
(171, 51, 4, 'BED-PED-19', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(172, 51, 4, 'BED-PED-20', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(173, 52, 4, 'BED-PED-21', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(174, 52, 4, 'BED-PED-22', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(175, 52, 4, 'BED-PED-23', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(176, 52, 4, 'BED-PED-24', 'pediatric', 'intermediate', 'occupied', 1800.00, NOW()),
(177, 53, 4, 'BED-PED-25', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(178, 53, 4, 'BED-PED-26', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(179, 53, 4, 'BED-PED-27', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(180, 53, 4, 'BED-PED-28', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(181, 54, 4, 'BED-PED-29', 'pediatric', 'intermediate', 'reserved', 1800.00, NOW()),
(182, 54, 4, 'BED-PED-30', 'pediatric', 'intermediate', 'occupied', 1800.00, NOW()),
(183, 54, 4, 'BED-PED-31', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(184, 54, 4, 'BED-PED-32', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(185, 55, 4, 'BED-PED-33', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(186, 55, 4, 'BED-PED-34', 'pediatric', 'intermediate', 'cleaning', 1800.00, NOW()),
(187, 55, 4, 'BED-PED-35', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(188, 55, 4, 'BED-PED-36', 'pediatric', 'intermediate', 'occupied', 1800.00, NOW()),
(189, 56, 4, 'BED-PED-37', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(190, 56, 4, 'BED-PED-38', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(191, 56, 4, 'BED-PED-39', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(192, 56, 4, 'BED-PED-40', 'pediatric', 'intermediate', 'available', 1800.00, NOW()),
(193, 7, 5, 'BED-PO-03', 'standard', 'intermediate', 'available', 2500.00, NOW()),
(194, 7, 5, 'BED-PO-04', 'ventilator_enabled', 'critical', 'available', 3000.00, NOW()),
(195, 57, 5, 'BED-PO-05', 'standard', 'intermediate', 'available', 2500.00, NOW()),
(196, 57, 5, 'BED-PO-06', 'standard', 'intermediate', 'occupied', 2500.00, NOW()),
(197, 57, 5, 'BED-PO-07', 'standard', 'intermediate', 'available', 2500.00, NOW()),
(198, 57, 5, 'BED-PO-08', 'ventilator_enabled', 'critical', 'available', 3000.00, NOW()),
(199, 58, 5, 'BED-PO-09', 'standard', 'intermediate', 'available', 2500.00, NOW()),
(200, 58, 5, 'BED-PO-10', 'standard', 'intermediate', 'available', 2500.00, NOW()),
(201, 58, 5, 'BED-PO-11', 'standard', 'intermediate', 'available', 2500.00, NOW()),
(202, 58, 5, 'BED-PO-12', 'ventilator_enabled', 'critical', 'occupied', 3000.00, NOW()),
(203, 59, 5, 'BED-PO-13', 'standard', 'intermediate', 'available', 2500.00, NOW()),
(204, 59, 5, 'BED-PO-14', 'standard', 'intermediate', 'available', 2500.00, NOW()),
(205, 59, 5, 'BED-PO-15', 'standard', 'intermediate', 'available', 2500.00, NOW()),
(206, 59, 5, 'BED-PO-16', 'ventilator_enabled', 'critical', 'available', 3000.00, NOW()),
(207, 60, 5, 'BED-PO-17', 'standard', 'intermediate', 'cleaning', 2500.00, NOW()),
(208, 60, 5, 'BED-PO-18', 'standard', 'intermediate', 'occupied', 2500.00, NOW()),
(209, 60, 5, 'BED-PO-19', 'standard', 'intermediate', 'available', 2500.00, NOW()),
(210, 60, 5, 'BED-PO-20', 'ventilator_enabled', 'critical', 'available', 3000.00, NOW()),
(211, 61, 5, 'BED-PO-21', 'standard', 'intermediate', 'available', 2500.00, NOW()),
(212, 61, 5, 'BED-PO-22', 'standard', 'intermediate', 'available', 2500.00, NOW()),
(213, 61, 5, 'BED-PO-23', 'standard', 'intermediate', 'available', 2500.00, NOW()),
(214, 61, 5, 'BED-PO-24', 'ventilator_enabled', 'critical', 'occupied', 3000.00, NOW()),
(215, 62, 5, 'BED-PO-25', 'standard', 'intermediate', 'available', 2500.00, NOW()),
(216, 62, 5, 'BED-PO-26', 'standard', 'intermediate', 'available', 2500.00, NOW()),
(217, 62, 5, 'BED-PO-27', 'standard', 'intermediate', 'available', 2500.00, NOW()),
(218, 62, 5, 'BED-PO-28', 'ventilator_enabled', 'critical', 'available', 3000.00, NOW()),
(219, 63, 5, 'BED-PO-29', 'standard', 'intermediate', 'reserved', 2500.00, NOW()),
(220, 63, 5, 'BED-PO-30', 'standard', 'intermediate', 'occupied', 2500.00, NOW()),
(221, 63, 5, 'BED-PO-31', 'standard', 'intermediate', 'available', 2500.00, NOW()),
(222, 63, 5, 'BED-PO-32', 'ventilator_enabled', 'critical', 'available', 3000.00, NOW()),
(223, 64, 5, 'BED-PO-33', 'standard', 'intermediate', 'available', 2500.00, NOW()),
(224, 64, 5, 'BED-PO-34', 'standard', 'intermediate', 'cleaning', 2500.00, NOW()),
(225, 64, 5, 'BED-PO-35', 'standard', 'intermediate', 'available', 2500.00, NOW()),
(226, 64, 5, 'BED-PO-36', 'ventilator_enabled', 'critical', 'occupied', 3000.00, NOW()),
(227, 65, 5, 'BED-PO-37', 'standard', 'intermediate', 'available', 2500.00, NOW()),
(228, 65, 5, 'BED-PO-38', 'standard', 'intermediate', 'available', 2500.00, NOW()),
(229, 65, 5, 'BED-PO-39', 'standard', 'intermediate', 'available', 2500.00, NOW()),
(230, 65, 5, 'BED-PO-40', 'ventilator_enabled', 'critical', 'available', 3000.00, NOW()),
(231, 66, 6, 'BED-MAT-01', 'standard', 'standard', 'available', 1800.00, NOW()),
(232, 66, 6, 'BED-MAT-02', 'standard', 'standard', 'available', 1800.00, NOW()),
(233, 66, 6, 'BED-MAT-03', 'standard', 'standard', 'available', 1800.00, NOW()),
(234, 66, 6, 'BED-MAT-04', 'standard', 'standard', 'available', 1800.00, NOW()),
(235, 67, 6, 'BED-MAT-05', 'standard', 'standard', 'available', 1800.00, NOW()),
(236, 67, 6, 'BED-MAT-06', 'standard', 'standard', 'occupied', 1800.00, NOW()),
(237, 67, 6, 'BED-MAT-07', 'standard', 'standard', 'available', 1800.00, NOW()),
(238, 67, 6, 'BED-MAT-08', 'standard', 'standard', 'available', 1800.00, NOW()),
(239, 68, 6, 'BED-MAT-09', 'standard', 'standard', 'available', 1800.00, NOW()),
(240, 68, 6, 'BED-MAT-10', 'standard', 'standard', 'available', 1800.00, NOW()),
(241, 68, 6, 'BED-MAT-11', 'standard', 'standard', 'available', 1800.00, NOW()),
(242, 68, 6, 'BED-MAT-12', 'standard', 'standard', 'occupied', 1800.00, NOW()),
(243, 69, 6, 'BED-MAT-13', 'standard', 'standard', 'available', 1800.00, NOW()),
(244, 69, 6, 'BED-MAT-14', 'standard', 'standard', 'available', 1800.00, NOW()),
(245, 69, 6, 'BED-MAT-15', 'standard', 'standard', 'available', 1800.00, NOW()),
(246, 69, 6, 'BED-MAT-16', 'standard', 'standard', 'available', 1800.00, NOW()),
(247, 70, 6, 'BED-MAT-17', 'standard', 'standard', 'cleaning', 1800.00, NOW()),
(248, 70, 6, 'BED-MAT-18', 'standard', 'standard', 'occupied', 1800.00, NOW()),
(249, 70, 6, 'BED-MAT-19', 'standard', 'standard', 'available', 1800.00, NOW()),
(250, 70, 6, 'BED-MAT-20', 'standard', 'standard', 'available', 1800.00, NOW()),
(251, 71, 6, 'BED-MAT-21', 'standard', 'standard', 'available', 1800.00, NOW()),
(252, 71, 6, 'BED-MAT-22', 'standard', 'standard', 'available', 1800.00, NOW()),
(253, 71, 6, 'BED-MAT-23', 'standard', 'standard', 'available', 1800.00, NOW()),
(254, 71, 6, 'BED-MAT-24', 'standard', 'standard', 'occupied', 1800.00, NOW()),
(255, 72, 6, 'BED-MAT-25', 'standard', 'standard', 'available', 1800.00, NOW()),
(256, 72, 6, 'BED-MAT-26', 'standard', 'standard', 'available', 1800.00, NOW()),
(257, 72, 6, 'BED-MAT-27', 'standard', 'standard', 'available', 1800.00, NOW()),
(258, 72, 6, 'BED-MAT-28', 'standard', 'standard', 'available', 1800.00, NOW()),
(259, 73, 6, 'BED-MAT-29', 'standard', 'standard', 'reserved', 1800.00, NOW()),
(260, 73, 6, 'BED-MAT-30', 'standard', 'standard', 'occupied', 1800.00, NOW()),
(261, 73, 6, 'BED-MAT-31', 'standard', 'standard', 'available', 1800.00, NOW()),
(262, 73, 6, 'BED-MAT-32', 'standard', 'standard', 'available', 1800.00, NOW()),
(263, 74, 6, 'BED-MAT-33', 'standard', 'standard', 'available', 1800.00, NOW()),
(264, 74, 6, 'BED-MAT-34', 'standard', 'standard', 'cleaning', 1800.00, NOW()),
(265, 74, 6, 'BED-MAT-35', 'standard', 'standard', 'available', 1800.00, NOW()),
(266, 74, 6, 'BED-MAT-36', 'standard', 'standard', 'occupied', 1800.00, NOW()),
(267, 75, 6, 'BED-MAT-37', 'standard', 'standard', 'available', 1800.00, NOW()),
(268, 75, 6, 'BED-MAT-38', 'standard', 'standard', 'available', 1800.00, NOW()),
(269, 75, 6, 'BED-MAT-39', 'standard', 'standard', 'available', 1800.00, NOW()),
(270, 75, 6, 'BED-MAT-40', 'standard', 'standard', 'available', 1800.00, NOW()),
(271, 76, 7, 'BED-ORT-01', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(272, 76, 7, 'BED-ORT-02', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(273, 76, 7, 'BED-ORT-03', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(274, 76, 7, 'BED-ORT-04', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(275, 77, 7, 'BED-ORT-05', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(276, 77, 7, 'BED-ORT-06', 'standard', 'intermediate', 'occupied', 2000.00, NOW()),
(277, 77, 7, 'BED-ORT-07', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(278, 77, 7, 'BED-ORT-08', 'bariatric', 'intermediate', 'available', 2000.00, NOW()),
(279, 78, 7, 'BED-ORT-09', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(280, 78, 7, 'BED-ORT-10', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(281, 78, 7, 'BED-ORT-11', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(282, 78, 7, 'BED-ORT-12', 'standard', 'intermediate', 'occupied', 2000.00, NOW()),
(283, 79, 7, 'BED-ORT-13', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(284, 79, 7, 'BED-ORT-14', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(285, 79, 7, 'BED-ORT-15', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(286, 79, 7, 'BED-ORT-16', 'bariatric', 'intermediate', 'available', 2000.00, NOW()),
(287, 80, 7, 'BED-ORT-17', 'standard', 'intermediate', 'cleaning', 2000.00, NOW()),
(288, 80, 7, 'BED-ORT-18', 'standard', 'intermediate', 'occupied', 2000.00, NOW()),
(289, 80, 7, 'BED-ORT-19', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(290, 80, 7, 'BED-ORT-20', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(291, 81, 7, 'BED-ORT-21', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(292, 81, 7, 'BED-ORT-22', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(293, 81, 7, 'BED-ORT-23', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(294, 81, 7, 'BED-ORT-24', 'bariatric', 'intermediate', 'occupied', 2000.00, NOW()),
(295, 82, 7, 'BED-ORT-25', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(296, 82, 7, 'BED-ORT-26', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(297, 82, 7, 'BED-ORT-27', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(298, 82, 7, 'BED-ORT-28', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(299, 83, 7, 'BED-ORT-29', 'standard', 'intermediate', 'reserved', 2000.00, NOW()),
(300, 83, 7, 'BED-ORT-30', 'standard', 'intermediate', 'occupied', 2000.00, NOW()),
(301, 83, 7, 'BED-ORT-31', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(302, 83, 7, 'BED-ORT-32', 'bariatric', 'intermediate', 'available', 2000.00, NOW()),
(303, 84, 7, 'BED-ORT-33', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(304, 84, 7, 'BED-ORT-34', 'standard', 'intermediate', 'cleaning', 2000.00, NOW()),
(305, 84, 7, 'BED-ORT-35', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(306, 84, 7, 'BED-ORT-36', 'standard', 'intermediate', 'occupied', 2000.00, NOW()),
(307, 85, 7, 'BED-ORT-37', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(308, 85, 7, 'BED-ORT-38', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(309, 85, 7, 'BED-ORT-39', 'standard', 'intermediate', 'available', 2000.00, NOW()),
(310, 85, 7, 'BED-ORT-40', 'bariatric', 'intermediate', 'available', 2000.00, NOW()),
(311, 86, 8, 'BED-ISO-01', 'standard', 'critical', 'available', 3500.00, NOW()),
(312, 86, 8, 'BED-ISO-02', 'standard', 'critical', 'available', 3500.00, NOW()),
(313, 86, 8, 'BED-ISO-03', 'ventilator_enabled', 'critical', 'available', 3500.00, NOW()),
(314, 86, 8, 'BED-ISO-04', 'standard', 'critical', 'available', 3500.00, NOW()),
(315, 87, 8, 'BED-ISO-05', 'standard', 'critical', 'available', 3500.00, NOW()),
(316, 87, 8, 'BED-ISO-06', 'ventilator_enabled', 'critical', 'occupied', 3500.00, NOW()),
(317, 87, 8, 'BED-ISO-07', 'standard', 'critical', 'available', 3500.00, NOW()),
(318, 87, 8, 'BED-ISO-08', 'standard', 'critical', 'available', 3500.00, NOW()),
(319, 88, 8, 'BED-ISO-09', 'ventilator_enabled', 'critical', 'available', 3500.00, NOW()),
(320, 88, 8, 'BED-ISO-10', 'standard', 'critical', 'available', 3500.00, NOW()),
(321, 88, 8, 'BED-ISO-11', 'standard', 'critical', 'available', 3500.00, NOW()),
(322, 88, 8, 'BED-ISO-12', 'ventilator_enabled', 'critical', 'occupied', 3500.00, NOW()),
(323, 89, 8, 'BED-ISO-13', 'standard', 'critical', 'available', 3500.00, NOW()),
(324, 89, 8, 'BED-ISO-14', 'standard', 'critical', 'available', 3500.00, NOW()),
(325, 89, 8, 'BED-ISO-15', 'ventilator_enabled', 'critical', 'available', 3500.00, NOW()),
(326, 89, 8, 'BED-ISO-16', 'standard', 'critical', 'available', 3500.00, NOW()),
(327, 90, 8, 'BED-ISO-17', 'standard', 'critical', 'cleaning', 3500.00, NOW()),
(328, 90, 8, 'BED-ISO-18', 'ventilator_enabled', 'critical', 'occupied', 3500.00, NOW()),
(329, 90, 8, 'BED-ISO-19', 'standard', 'critical', 'available', 3500.00, NOW()),
(330, 90, 8, 'BED-ISO-20', 'standard', 'critical', 'available', 3500.00, NOW()),
(331, 91, 8, 'BED-ISO-21', 'ventilator_enabled', 'critical', 'available', 3500.00, NOW()),
(332, 91, 8, 'BED-ISO-22', 'standard', 'critical', 'available', 3500.00, NOW()),
(333, 91, 8, 'BED-ISO-23', 'standard', 'critical', 'available', 3500.00, NOW()),
(334, 91, 8, 'BED-ISO-24', 'ventilator_enabled', 'critical', 'occupied', 3500.00, NOW()),
(335, 92, 8, 'BED-ISO-25', 'standard', 'critical', 'available', 3500.00, NOW()),
(336, 92, 8, 'BED-ISO-26', 'standard', 'critical', 'available', 3500.00, NOW()),
(337, 92, 8, 'BED-ISO-27', 'ventilator_enabled', 'critical', 'available', 3500.00, NOW()),
(338, 92, 8, 'BED-ISO-28', 'standard', 'critical', 'available', 3500.00, NOW()),
(339, 93, 8, 'BED-ISO-29', 'standard', 'critical', 'reserved', 3500.00, NOW()),
(340, 93, 8, 'BED-ISO-30', 'ventilator_enabled', 'critical', 'occupied', 3500.00, NOW()),
(341, 93, 8, 'BED-ISO-31', 'standard', 'critical', 'available', 3500.00, NOW()),
(342, 93, 8, 'BED-ISO-32', 'standard', 'critical', 'available', 3500.00, NOW()),
(343, 94, 8, 'BED-ISO-33', 'ventilator_enabled', 'critical', 'available', 3500.00, NOW()),
(344, 94, 8, 'BED-ISO-34', 'standard', 'critical', 'cleaning', 3500.00, NOW()),
(345, 94, 8, 'BED-ISO-35', 'standard', 'critical', 'available', 3500.00, NOW()),
(346, 94, 8, 'BED-ISO-36', 'ventilator_enabled', 'critical', 'occupied', 3500.00, NOW()),
(347, 95, 8, 'BED-ISO-37', 'standard', 'critical', 'available', 3500.00, NOW()),
(348, 95, 8, 'BED-ISO-38', 'standard', 'critical', 'available', 3500.00, NOW()),
(349, 95, 8, 'BED-ISO-39', 'ventilator_enabled', 'critical', 'available', 3500.00, NOW()),
(350, 95, 8, 'BED-ISO-40', 'standard', 'critical', 'available', 3500.00, NOW());

-- Bed Resources
INSERT INTO `bed_resources` (`id`, `bed_id`, `resource_name`, `resource_type`, `status`) VALUES
(1, 1, 'Hamilton G5 Mechanical Ventilator', 'Ventilator', 'operational'),
(2, 1, 'Mindray BeneVision Multi-Parameter Monitor', 'Cardiac Monitor', 'operational'),
(3, 1, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(4, 2, 'Dräger Evita Infinity V500 Ventilator', 'Ventilator', 'operational'),
(5, 6, 'Wall Oxygen Central Port', 'Oxygen Port', 'operational'),
(6, 3, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(7, 3, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(8, 4, 'Hamilton C3 ICU Ventilator', 'Ventilator', 'operational'),
(9, 4, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(10, 4, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(11, 5, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(12, 5, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(13, 6, 'Hamilton C3 ICU Ventilator', 'Ventilator', 'operational'),
(14, 6, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(15, 6, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(16, 7, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(17, 7, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(18, 8, 'Hamilton C3 ICU Ventilator', 'Ventilator', 'operational'),
(19, 8, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(20, 8, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(21, 9, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(22, 9, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(23, 10, 'Hamilton C3 ICU Ventilator', 'Ventilator', 'operational'),
(24, 10, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(25, 10, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(26, 11, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(27, 11, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(28, 12, 'Hamilton C3 ICU Ventilator', 'Ventilator', 'operational'),
(29, 12, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(30, 12, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(31, 13, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(32, 13, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(33, 14, 'Hamilton C3 ICU Ventilator', 'Ventilator', 'operational'),
(34, 14, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(35, 14, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(36, 15, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(37, 15, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(38, 16, 'Hamilton C3 ICU Ventilator', 'Ventilator', 'operational'),
(39, 16, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(40, 16, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(41, 17, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(42, 17, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(43, 18, 'Hamilton C3 ICU Ventilator', 'Ventilator', 'operational'),
(44, 18, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(45, 18, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(46, 19, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(47, 19, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(48, 20, 'Hamilton C3 ICU Ventilator', 'Ventilator', 'operational'),
(49, 20, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(50, 20, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(51, 21, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(52, 21, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(53, 22, 'Hamilton C3 ICU Ventilator', 'Ventilator', 'operational'),
(54, 22, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(55, 22, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(56, 23, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(57, 23, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(58, 24, 'Hamilton C3 ICU Ventilator', 'Ventilator', 'operational'),
(59, 24, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(60, 24, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(61, 25, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(62, 25, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(63, 26, 'Hamilton C3 ICU Ventilator', 'Ventilator', 'operational'),
(64, 26, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(65, 26, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(66, 27, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(67, 27, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(68, 28, 'Hamilton C3 ICU Ventilator', 'Ventilator', 'operational'),
(69, 28, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(70, 28, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(71, 29, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(72, 29, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(73, 30, 'Hamilton C3 ICU Ventilator', 'Ventilator', 'operational'),
(74, 30, 'Mindray BeneVision Telemetry Monitor', 'Cardiac Monitor', 'operational'),
(75, 30, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(76, 311, 'GE Healthcare CARESCAPE Monitor', 'Cardiac Monitor', 'operational'),
(77, 312, 'GE Healthcare CARESCAPE Monitor', 'Cardiac Monitor', 'operational'),
(78, 312, 'Puritan Bennett 980 Ventilator', 'Ventilator', 'operational'),
(79, 313, 'GE Healthcare CARESCAPE Monitor', 'Cardiac Monitor', 'operational'),
(80, 314, 'GE Healthcare CARESCAPE Monitor', 'Cardiac Monitor', 'operational'),
(81, 315, 'GE Healthcare CARESCAPE Monitor', 'Cardiac Monitor', 'operational'),
(82, 315, 'Puritan Bennett 980 Ventilator', 'Ventilator', 'operational'),
(83, 316, 'GE Healthcare CARESCAPE Monitor', 'Cardiac Monitor', 'operational'),
(84, 317, 'GE Healthcare CARESCAPE Monitor', 'Cardiac Monitor', 'operational'),
(85, 318, 'GE Healthcare CARESCAPE Monitor', 'Cardiac Monitor', 'operational'),
(86, 318, 'Puritan Bennett 980 Ventilator', 'Ventilator', 'operational'),
(87, 319, 'GE Healthcare CARESCAPE Monitor', 'Cardiac Monitor', 'operational'),
(88, 320, 'GE Healthcare CARESCAPE Monitor', 'Cardiac Monitor', 'operational'),
(89, 321, 'GE Healthcare CARESCAPE Monitor', 'Cardiac Monitor', 'operational'),
(90, 321, 'Puritan Bennett 980 Ventilator', 'Ventilator', 'operational'),
(91, 322, 'GE Healthcare CARESCAPE Monitor', 'Cardiac Monitor', 'operational'),
(92, 323, 'GE Healthcare CARESCAPE Monitor', 'Cardiac Monitor', 'operational'),
(93, 324, 'GE Healthcare CARESCAPE Monitor', 'Cardiac Monitor', 'operational'),
(94, 324, 'Puritan Bennett 980 Ventilator', 'Ventilator', 'operational'),
(95, 325, 'GE Healthcare CARESCAPE Monitor', 'Cardiac Monitor', 'operational');

-- Inpatient Encounters for Occupied Beds
INSERT INTO `patient_encounters` (`id`, `encounter_number`, `patient_id`, `appointment_id`, `doctor_id`, `department_id`, `encounter_type`, `status`, `started_at`) VALUES
(4, 'ENC-2026-0004', 7, NULL, 3, 9, 'inpatient', 'active', NOW() - INTERVAL 5 DAY - INTERVAL 3 HOUR),
(5, 'ENC-2026-0005', 8, NULL, 3, 9, 'inpatient', 'active', NOW() - INTERVAL 1 DAY - INTERVAL 1 HOUR),
(6, 'ENC-2026-0006', 9, NULL, 3, 9, 'inpatient', 'active', NOW() - INTERVAL 7 DAY - INTERVAL 19 HOUR),
(7, 'ENC-2026-0007', 10, NULL, 3, 4, 'inpatient', 'active', NOW() - INTERVAL 3 DAY - INTERVAL 17 HOUR),
(8, 'ENC-2026-0008', 11, NULL, 3, 4, 'inpatient', 'active', NOW() - INTERVAL 9 DAY - INTERVAL 15 HOUR),
(9, 'ENC-2026-0009', 12, NULL, 2, 4, 'inpatient', 'active', NOW() - INTERVAL 1 DAY - INTERVAL 1 HOUR),
(10, 'ENC-2026-0010', 13, NULL, 2, 4, 'inpatient', 'active', NOW() - INTERVAL 7 DAY - INTERVAL 19 HOUR),
(11, 'ENC-2026-0011', 14, NULL, 2, 4, 'inpatient', 'active', NOW() - INTERVAL 3 DAY - INTERVAL 17 HOUR),
(12, 'ENC-2026-0012', 15, NULL, 2, 4, 'inpatient', 'active', NOW() - INTERVAL 9 DAY - INTERVAL 15 HOUR),
(13, 'ENC-2026-0013', 16, NULL, 2, 4, 'inpatient', 'active', NOW() - INTERVAL 5 DAY - INTERVAL 13 HOUR),
(14, 'ENC-2026-0014', 17, NULL, 2, 4, 'inpatient', 'active', NOW() - INTERVAL 1 DAY - INTERVAL 11 HOUR),
(15, 'ENC-2026-0015', 18, NULL, 2, 4, 'inpatient', 'active', NOW() - INTERVAL 7 DAY - INTERVAL 9 HOUR),
(16, 'ENC-2026-0016', 19, NULL, 2, 4, 'inpatient', 'active', NOW() - INTERVAL 3 DAY - INTERVAL 7 HOUR),
(17, 'ENC-2026-0017', 20, NULL, 2, 4, 'inpatient', 'active', NOW() - INTERVAL 9 DAY - INTERVAL 5 HOUR),
(18, 'ENC-2026-0018', 21, NULL, 2, 4, 'inpatient', 'active', NOW() - INTERVAL 5 DAY - INTERVAL 3 HOUR),
(19, 'ENC-2026-0019', 22, NULL, 3, 4, 'inpatient', 'active', NOW() - INTERVAL 9 DAY - INTERVAL 15 HOUR),
(20, 'ENC-2026-0020', 23, NULL, 3, 4, 'inpatient', 'active', NOW() - INTERVAL 5 DAY - INTERVAL 13 HOUR),
(21, 'ENC-2026-0021', 24, NULL, 3, 4, 'inpatient', 'active', NOW() - INTERVAL 1 DAY - INTERVAL 11 HOUR),
(22, 'ENC-2026-0022', 25, NULL, 3, 4, 'inpatient', 'active', NOW() - INTERVAL 7 DAY - INTERVAL 9 HOUR),
(23, 'ENC-2026-0023', 26, NULL, 3, 4, 'inpatient', 'active', NOW() - INTERVAL 3 DAY - INTERVAL 7 HOUR),
(24, 'ENC-2026-0024', 27, NULL, 3, 4, 'inpatient', 'active', NOW() - INTERVAL 9 DAY - INTERVAL 5 HOUR),
(25, 'ENC-2026-0025', 28, NULL, 3, 4, 'inpatient', 'active', NOW() - INTERVAL 5 DAY - INTERVAL 3 HOUR),
(26, 'ENC-2026-0026', 29, NULL, 3, 4, 'inpatient', 'active', NOW() - INTERVAL 1 DAY - INTERVAL 1 HOUR),
(27, 'ENC-2026-0027', 30, NULL, 3, 4, 'inpatient', 'active', NOW() - INTERVAL 7 DAY - INTERVAL 19 HOUR),
(28, 'ENC-2026-0028', 31, NULL, 3, 2, 'inpatient', 'active', NOW() - INTERVAL 3 DAY - INTERVAL 17 HOUR),
(29, 'ENC-2026-0029', 32, NULL, 3, 2, 'inpatient', 'active', NOW() - INTERVAL 9 DAY - INTERVAL 15 HOUR),
(30, 'ENC-2026-0030', 33, NULL, 3, 2, 'inpatient', 'active', NOW() - INTERVAL 5 DAY - INTERVAL 13 HOUR),
(31, 'ENC-2026-0031', 34, NULL, 3, 2, 'inpatient', 'active', NOW() - INTERVAL 1 DAY - INTERVAL 11 HOUR),
(32, 'ENC-2026-0032', 35, NULL, 3, 2, 'inpatient', 'active', NOW() - INTERVAL 7 DAY - INTERVAL 9 HOUR),
(33, 'ENC-2026-0033', 36, NULL, 3, 2, 'inpatient', 'active', NOW() - INTERVAL 3 DAY - INTERVAL 7 HOUR),
(34, 'ENC-2026-0034', 37, NULL, 3, 2, 'inpatient', 'active', NOW() - INTERVAL 9 DAY - INTERVAL 5 HOUR),
(35, 'ENC-2026-0035', 38, NULL, 2, 2, 'inpatient', 'active', NOW() - INTERVAL 7 DAY - INTERVAL 9 HOUR),
(36, 'ENC-2026-0036', 39, NULL, 2, 2, 'inpatient', 'active', NOW() - INTERVAL 3 DAY - INTERVAL 7 HOUR),
(37, 'ENC-2026-0037', 40, NULL, 2, 2, 'inpatient', 'active', NOW() - INTERVAL 9 DAY - INTERVAL 5 HOUR),
(38, 'ENC-2026-0038', 41, NULL, 2, 2, 'inpatient', 'active', NOW() - INTERVAL 5 DAY - INTERVAL 3 HOUR),
(39, 'ENC-2026-0039', 42, NULL, 2, 2, 'inpatient', 'active', NOW() - INTERVAL 1 DAY - INTERVAL 1 HOUR),
(40, 'ENC-2026-0040', 43, NULL, 2, 2, 'inpatient', 'active', NOW() - INTERVAL 7 DAY - INTERVAL 19 HOUR),
(41, 'ENC-2026-0041', 44, NULL, 3, 2, 'inpatient', 'active', NOW() - INTERVAL 7 DAY - INTERVAL 9 HOUR),
(42, 'ENC-2026-0042', 45, NULL, 3, 2, 'inpatient', 'active', NOW() - INTERVAL 3 DAY - INTERVAL 7 HOUR),
(43, 'ENC-2026-0043', 46, NULL, 3, 2, 'inpatient', 'active', NOW() - INTERVAL 9 DAY - INTERVAL 5 HOUR),
(44, 'ENC-2026-0044', 47, NULL, 3, 2, 'inpatient', 'active', NOW() - INTERVAL 5 DAY - INTERVAL 3 HOUR),
(45, 'ENC-2026-0045', 48, NULL, 3, 2, 'inpatient', 'active', NOW() - INTERVAL 1 DAY - INTERVAL 1 HOUR),
(46, 'ENC-2026-0046', 49, NULL, 3, 2, 'inpatient', 'active', NOW() - INTERVAL 7 DAY - INTERVAL 19 HOUR),
(47, 'ENC-2026-0047', 50, NULL, 1, 2, 'inpatient', 'active', NOW() - INTERVAL 7 DAY - INTERVAL 9 HOUR),
(48, 'ENC-2026-0048', 51, NULL, 1, 2, 'inpatient', 'active', NOW() - INTERVAL 3 DAY - INTERVAL 7 HOUR),
(49, 'ENC-2026-0049', 52, NULL, 1, 2, 'inpatient', 'active', NOW() - INTERVAL 9 DAY - INTERVAL 5 HOUR),
(50, 'ENC-2026-0050', 53, NULL, 1, 2, 'inpatient', 'active', NOW() - INTERVAL 5 DAY - INTERVAL 3 HOUR),
(51, 'ENC-2026-0051', 54, NULL, 1, 2, 'inpatient', 'active', NOW() - INTERVAL 1 DAY - INTERVAL 1 HOUR),
(52, 'ENC-2026-0052', 55, NULL, 1, 2, 'inpatient', 'active', NOW() - INTERVAL 7 DAY - INTERVAL 19 HOUR),
(53, 'ENC-2026-0053', 56, NULL, 2, 2, 'inpatient', 'active', NOW() - INTERVAL 7 DAY - INTERVAL 9 HOUR),
(54, 'ENC-2026-0054', 57, NULL, 2, 2, 'inpatient', 'active', NOW() - INTERVAL 3 DAY - INTERVAL 7 HOUR),
(55, 'ENC-2026-0055', 58, NULL, 2, 2, 'inpatient', 'active', NOW() - INTERVAL 9 DAY - INTERVAL 5 HOUR),
(56, 'ENC-2026-0056', 59, NULL, 2, 2, 'inpatient', 'active', NOW() - INTERVAL 5 DAY - INTERVAL 3 HOUR),
(57, 'ENC-2026-0057', 60, NULL, 2, 2, 'inpatient', 'active', NOW() - INTERVAL 1 DAY - INTERVAL 1 HOUR),
(58, 'ENC-2026-0058', 61, NULL, 2, 2, 'inpatient', 'active', NOW() - INTERVAL 7 DAY - INTERVAL 19 HOUR);

-- Active Bed Assignments
INSERT INTO `bed_assignments` (`id`, `bed_id`, `patient_id`, `encounter_id`, `admission_date`, `discharge_date`, `status`, `assigned_by`, `clinical_reason`) VALUES
(1, 1, 4, 3, NOW() - INTERVAL 2 DAY, NULL, 'active', 2, 'Critical post-stent hemodynamic monitoring and chest observation'),
(2, 6, 3, 2, NOW() - INTERVAL 1 DAY, NULL, 'active', 2, 'Hyperglycemic observation and insulin titration'),
(3, 14, 7, 4, NOW() - INTERVAL 5 DAY, NULL, 'active', 2, 'Acute coronary syndrome stabilization and telemetry surveillance'),
(4, 20, 8, 5, NOW() - INTERVAL 1 DAY, NULL, 'active', 2, 'Inpatient bacterial pneumonia broad-spectrum antibiotic course'),
(5, 26, 9, 6, NOW() - INTERVAL 7 DAY, NULL, 'active', 2, 'Acute ischemic stroke neuro-telemetry and secondary prophylaxis'),
(6, 32, 10, 7, NOW() - INTERVAL 3 DAY, NULL, 'active', 2, 'Bronchial asthma acute exacerbation nebulization and SpO2 monitoring'),
(7, 38, 11, 8, NOW() - INTERVAL 9 DAY, NULL, 'active', 2, 'Hypoxemic respiratory failure continuous bi-level positive airway pressure'),
(8, 40, 12, 9, NOW() - INTERVAL 1 DAY, NULL, 'active', 2, 'Acute ischemic stroke neuro-telemetry and secondary prophylaxis'),
(9, 46, 13, 10, NOW() - INTERVAL 7 DAY, NULL, 'active', 2, 'Bronchial asthma acute exacerbation nebulization and SpO2 monitoring'),
(10, 52, 14, 11, NOW() - INTERVAL 3 DAY, NULL, 'active', 2, 'Hypoxemic respiratory failure continuous bi-level positive airway pressure'),
(11, 58, 15, 12, NOW() - INTERVAL 9 DAY, NULL, 'active', 2, 'Severe diabetic ketoacidosis glycemic stabilization and hydration'),
(12, 64, 16, 13, NOW() - INTERVAL 5 DAY, NULL, 'active', 2, 'Hypertensive urgency blood pressure titration and organ screening'),
(13, 70, 17, 14, NOW() - INTERVAL 1 DAY, NULL, 'active', 2, 'Acute coronary syndrome stabilization and telemetry surveillance'),
(14, 76, 18, 15, NOW() - INTERVAL 7 DAY, NULL, 'active', 2, 'Inpatient bacterial pneumonia broad-spectrum antibiotic course'),
(15, 82, 19, 16, NOW() - INTERVAL 3 DAY, NULL, 'active', 2, 'Acute ischemic stroke neuro-telemetry and secondary prophylaxis'),
(16, 88, 20, 17, NOW() - INTERVAL 9 DAY, NULL, 'active', 2, 'Bronchial asthma acute exacerbation nebulization and SpO2 monitoring'),
(17, 94, 21, 18, NOW() - INTERVAL 5 DAY, NULL, 'active', 2, 'Hypoxemic respiratory failure continuous bi-level positive airway pressure'),
(18, 98, 22, 19, NOW() - INTERVAL 9 DAY, NULL, 'active', 2, 'Acute coronary syndrome stabilization and telemetry surveillance'),
(19, 104, 23, 20, NOW() - INTERVAL 5 DAY, NULL, 'active', 2, 'Inpatient bacterial pneumonia broad-spectrum antibiotic course'),
(20, 110, 24, 21, NOW() - INTERVAL 1 DAY, NULL, 'active', 2, 'Acute ischemic stroke neuro-telemetry and secondary prophylaxis'),
(21, 116, 25, 22, NOW() - INTERVAL 7 DAY, NULL, 'active', 2, 'Bronchial asthma acute exacerbation nebulization and SpO2 monitoring'),
(22, 122, 26, 23, NOW() - INTERVAL 3 DAY, NULL, 'active', 2, 'Hypoxemic respiratory failure continuous bi-level positive airway pressure'),
(23, 128, 27, 24, NOW() - INTERVAL 9 DAY, NULL, 'active', 2, 'Severe diabetic ketoacidosis glycemic stabilization and hydration'),
(24, 134, 28, 25, NOW() - INTERVAL 5 DAY, NULL, 'active', 2, 'Hypertensive urgency blood pressure titration and organ screening'),
(25, 140, 29, 26, NOW() - INTERVAL 1 DAY, NULL, 'active', 2, 'Acute coronary syndrome stabilization and telemetry surveillance'),
(26, 146, 30, 27, NOW() - INTERVAL 7 DAY, NULL, 'active', 2, 'Inpatient bacterial pneumonia broad-spectrum antibiotic course'),
(27, 152, 31, 28, NOW() - INTERVAL 3 DAY, NULL, 'active', 2, 'Acute ischemic stroke neuro-telemetry and secondary prophylaxis'),
(28, 158, 32, 29, NOW() - INTERVAL 9 DAY, NULL, 'active', 2, 'Bronchial asthma acute exacerbation nebulization and SpO2 monitoring'),
(29, 164, 33, 30, NOW() - INTERVAL 5 DAY, NULL, 'active', 2, 'Hypoxemic respiratory failure continuous bi-level positive airway pressure'),
(30, 170, 34, 31, NOW() - INTERVAL 1 DAY, NULL, 'active', 2, 'Severe diabetic ketoacidosis glycemic stabilization and hydration'),
(31, 176, 35, 32, NOW() - INTERVAL 7 DAY, NULL, 'active', 2, 'Hypertensive urgency blood pressure titration and organ screening'),
(32, 182, 36, 33, NOW() - INTERVAL 3 DAY, NULL, 'active', 2, 'Acute coronary syndrome stabilization and telemetry surveillance'),
(33, 188, 37, 34, NOW() - INTERVAL 9 DAY, NULL, 'active', 2, 'Inpatient bacterial pneumonia broad-spectrum antibiotic course'),
(34, 196, 38, 35, NOW() - INTERVAL 7 DAY, NULL, 'active', 2, 'Acute coronary syndrome stabilization and telemetry surveillance'),
(35, 202, 39, 36, NOW() - INTERVAL 3 DAY, NULL, 'active', 2, 'Inpatient bacterial pneumonia broad-spectrum antibiotic course'),
(36, 208, 40, 37, NOW() - INTERVAL 9 DAY, NULL, 'active', 2, 'Acute ischemic stroke neuro-telemetry and secondary prophylaxis'),
(37, 214, 41, 38, NOW() - INTERVAL 5 DAY, NULL, 'active', 2, 'Bronchial asthma acute exacerbation nebulization and SpO2 monitoring'),
(38, 220, 42, 39, NOW() - INTERVAL 1 DAY, NULL, 'active', 2, 'Hypoxemic respiratory failure continuous bi-level positive airway pressure'),
(39, 226, 43, 40, NOW() - INTERVAL 7 DAY, NULL, 'active', 2, 'Severe diabetic ketoacidosis glycemic stabilization and hydration'),
(40, 236, 44, 41, NOW() - INTERVAL 7 DAY, NULL, 'active', 2, 'Acute ischemic stroke neuro-telemetry and secondary prophylaxis'),
(41, 242, 45, 42, NOW() - INTERVAL 3 DAY, NULL, 'active', 2, 'Bronchial asthma acute exacerbation nebulization and SpO2 monitoring'),
(42, 248, 46, 43, NOW() - INTERVAL 9 DAY, NULL, 'active', 2, 'Hypoxemic respiratory failure continuous bi-level positive airway pressure'),
(43, 254, 47, 44, NOW() - INTERVAL 5 DAY, NULL, 'active', 2, 'Severe diabetic ketoacidosis glycemic stabilization and hydration'),
(44, 260, 48, 45, NOW() - INTERVAL 1 DAY, NULL, 'active', 2, 'Hypertensive urgency blood pressure titration and organ screening'),
(45, 266, 49, 46, NOW() - INTERVAL 7 DAY, NULL, 'active', 2, 'Acute coronary syndrome stabilization and telemetry surveillance'),
(46, 276, 50, 47, NOW() - INTERVAL 7 DAY, NULL, 'active', 2, 'Hypoxemic respiratory failure continuous bi-level positive airway pressure'),
(47, 282, 51, 48, NOW() - INTERVAL 3 DAY, NULL, 'active', 2, 'Severe diabetic ketoacidosis glycemic stabilization and hydration'),
(48, 288, 52, 49, NOW() - INTERVAL 9 DAY, NULL, 'active', 2, 'Hypertensive urgency blood pressure titration and organ screening'),
(49, 294, 53, 50, NOW() - INTERVAL 5 DAY, NULL, 'active', 2, 'Acute coronary syndrome stabilization and telemetry surveillance'),
(50, 300, 54, 51, NOW() - INTERVAL 1 DAY, NULL, 'active', 2, 'Inpatient bacterial pneumonia broad-spectrum antibiotic course'),
(51, 306, 55, 52, NOW() - INTERVAL 7 DAY, NULL, 'active', 2, 'Acute ischemic stroke neuro-telemetry and secondary prophylaxis'),
(52, 316, 56, 53, NOW() - INTERVAL 7 DAY, NULL, 'active', 2, 'Hypertensive urgency blood pressure titration and organ screening'),
(53, 322, 57, 54, NOW() - INTERVAL 3 DAY, NULL, 'active', 2, 'Acute coronary syndrome stabilization and telemetry surveillance'),
(54, 328, 58, 55, NOW() - INTERVAL 9 DAY, NULL, 'active', 2, 'Inpatient bacterial pneumonia broad-spectrum antibiotic course'),
(55, 334, 59, 56, NOW() - INTERVAL 5 DAY, NULL, 'active', 2, 'Acute ischemic stroke neuro-telemetry and secondary prophylaxis'),
(56, 340, 60, 57, NOW() - INTERVAL 1 DAY, NULL, 'active', 2, 'Bronchial asthma acute exacerbation nebulization and SpO2 monitoring'),
(57, 346, 61, 58, NOW() - INTERVAL 7 DAY, NULL, 'active', 2, 'Hypoxemic respiratory failure continuous bi-level positive airway pressure');


-- 17. QueueFlow Entries
INSERT INTO `queue_entries` (`id`, `token_number`, `patient_id`, `appointment_id`, `doctor_id`, `department_id`, `queue_date`, `check_in_time`, `consultation_start_time`, `completion_time`, `status`, `priority_level`, `priority_reason`, `estimated_wait_time_minutes`, `created_by`) VALUES
(1, 101, 2, 2, 2, 4, CURDATE(), NOW() - INTERVAL 25 MINUTE, NOW() - INTERVAL 10 MINUTE, NULL, 'in_consultation', 'normal', NULL, 0, 6),
(2, 102, 1, 1, 1, 9, CURDATE(), NOW() - INTERVAL 20 MINUTE, NULL, NULL, 'called', 'urgent', 'Exertional chest discomfort reported during vitals triage', 5, 6),
(3, 103, 3, 3, 2, 4, CURDATE(), NOW() - INTERVAL 15 MINUTE, NULL, NULL, 'waiting', 'normal', NULL, 15, 6),
(4, 104, 5, 5, 3, 8, CURDATE(), NOW() - INTERVAL 10 MINUTE, NULL, NULL, 'waiting', 'normal', NULL, 20, 6);

INSERT INTO `queue_priority_events` (`id`, `queue_entry_id`, `old_priority`, `new_priority`, `changed_by`, `reason`, `created_at`) VALUES
(1, 2, 'normal', 'urgent', 6, 'Nurse reported elevated BP 142/92 with chest tightness at check-in counter', NOW() - INTERVAL 18 MINUTE);

-- 18. CareTrail: Patient Journey Events
INSERT INTO `patient_journey_events` (`id`, `patient_id`, `encounter_id`, `event_type`, `event_title`, `event_description`, `department_id`, `source_table`, `source_id`, `severity`, `created_by`, `event_time`) VALUES
-- Patient 4 Journey
(1, 4, 3, 'registration', 'Patient Enrolled in NexusCare', 'Demographics, medical history, and emergency contact registered.', 2, 'patient_profiles', 4, 'info', 6, NOW() - INTERVAL 2 DAY - INTERVAL 2 HOUR),
(2, 4, 3, 'check_in', 'Emergency Triaged & Checked-In', 'Patient arrived at Emergency complaining of exertional chest pain.', 1, 'patient_encounters', 3, 'warning', 6, NOW() - INTERVAL 2 DAY - INTERVAL 1 HOUR),
(3, 4, 3, 'admitted', 'Inpatient Admission to ICCU', 'Admitted to Intensive Coronary Care Unit under Dr. Rajesh Sharma.', 9, 'bed_assignments', 1, 'critical', 2, NOW() - INTERVAL 2 DAY),
(4, 4, 3, 'bed_assigned', 'Bed Matrix Allocation: BED-ICU-01', 'Assigned ventilator-enabled bed with 24/7 cardiac telemetry.', 1, 'beds', 1, 'info', 7, NOW() - INTERVAL 2 DAY),
(5, 4, 3, 'consultation_completed', 'Cardiology Clinical Evaluation', 'Dr. Sharma completed evaluation; ordered urgent cardiac biomarkers and ECG.', 9, 'consultations', 1, 'info', 3, NOW() - INTERVAL 2 DAY + INTERVAL 30 MINUTE),
(6, 4, 3, 'lab_ordered', 'Urgent Cardiac Lab Orders', 'Troponin-I and Comprehensive Lipid Profile dispatched to Core Lab.', 14, 'lab_orders', 1, 'warning', 3, NOW() - INTERVAL 1 DAY - INTERVAL 2 HOUR),
(7, 4, 3, 'lab_completed', 'Cardiac Biomarkers Completed', 'Troponin-I negative (0.01 ng/mL). Lipid panel shows LDL 132 mg/dL.', 14, 'lab_orders', 1, 'success', 8, NOW() - INTERVAL 22 HOUR),
(8, 4, 3, 'prescription_issued', 'Cardiology Prescription Generated', 'Prescribed Atorvastatin 20mg and emergency Sorbitrate.', 9, 'prescriptions', 1, 'info', 3, NOW() - INTERVAL 2 DAY),
(9, 4, 3, 'medicine_dispensed', 'Pharmacy Medication Dispensed', 'Chief Pharmacist Rohit Agarwal dispensed verified medicines.', 15, 'pharmacy_orders', 1, 'success', 9, NOW() - INTERVAL 2 DAY + INTERVAL 1 HOUR),
-- Patient 1 Journey (Today)
(10, 1, 1, 'appointment_booked', 'Cardiology Consultation Booked', 'Appointment APT-2026-0001 booked for Dr. Rajesh Sharma.', 9, 'appointments', 1, 'info', 6, NOW() - INTERVAL 2 HOUR),
(11, 1, 1, 'check_in', 'Checked In at Reception Desk', 'Queue token #102 issued. Patient triaged to Waiting Lounge A.', 9, 'queue_entries', 2, 'info', 6, NOW() - INTERVAL 20 MINUTE),
(12, 1, 1, 'lab_ordered', 'Lipid Profile Lab Order Dispatched', 'Dr. Sharma ordered routine lipid check.', 14, 'lab_orders', 2, 'info', 3, NOW() - INTERVAL 35 MINUTE);

-- 19. FlowLens: Bottleneck Alerts
INSERT INTO `flowlens_alerts` (`id`, `alert_code`, `alert_type`, `department_id`, `severity`, `triggering_metric`, `observed_value`, `threshold_value`, `alert_title`, `alert_description`, `suggested_action`, `status`, `created_at`, `resolved_by`, `resolution_notes`, `resolved_at`) VALUES
(1, 'FL-2026-001', 'bed_occupancy_critical', 1, 'critical', 'Bed Occupancy Percentage', 83.33, 80.00, 'ICCU High Bed Saturation Alert', 'Intensive Coronary Care Unit occupancy reached 83.33% (5 of 6 active critical beds occupied or reserved).', 'Review clinically stable patients for transfer to step-down intermediate post-op beds.', 'active', NOW() - INTERVAL 3 HOUR, NULL, NULL, NULL),
(2, 'FL-2026-002', 'queue_wait_time', 4, 'high', 'Average Queue Wait Time (mins)', 32.50, 25.00, 'General Medicine Consultation Queue Delay', 'Wait time in General Medicine OPD exceeds acceptable threshold of 25 minutes due to seasonal fever patient influx.', 'Deploy second attending physician or activate relief OPD consultation room.', 'acknowledged', NOW() - INTERVAL 1 HOUR, NULL, NULL, NULL),
(3, 'FL-2026-003', 'lab_delay', 14, 'medium', 'Lab Turnaround Time (mins)', 75.00, 60.00, 'Biochemistry Analyzer Batch Queue Delay', 'Electrolyte and lipid test processing backlog currently at 75 minutes.', 'Prioritize urgent STAT cardiology orders and balance autoanalyzer reagent racks.', 'resolved', NOW() - INTERVAL 5 HOUR, 2, 'Added secondary laboratory technician on duty; turnaround reduced to 38 minutes.', NOW() - INTERVAL 1 HOUR);

INSERT INTO `flowlens_alert_history` (`id`, `alert_id`, `action_taken`, `action_by`, `notes`, `created_at`) VALUES
(1, 2, 'acknowledged', 2, 'Medical Director notified; checking physician roster.', NOW() - INTERVAL 45 MINUTE),
(2, 3, 'resolved', 2, 'Reagent racks adjusted; lab turnaround back under 40 mins.', NOW() - INTERVAL 1 HOUR);

-- Operational Metrics Snapshot
INSERT INTO `operational_metrics` (`metric_date`, `department_id`, `avg_wait_time_minutes`, `avg_consultation_time_minutes`, `total_patients_served`, `bed_occupancy_rate`, `pending_lab_orders_count`) VALUES
(CURDATE() - INTERVAL 1 DAY, 4, 22.50, 14.80, 42, 65.00, 4),
(CURDATE() - INTERVAL 1 DAY, 9, 18.20, 19.50, 28, 83.30, 2),
(CURDATE() - INTERVAL 1 DAY, 8, 16.00, 18.00, 24, 50.00, 1),
(CURDATE(), 4, 32.50, 15.20, 18, 70.00, 5),
(CURDATE(), 9, 19.00, 18.50, 12, 83.33, 3);

-- 20. Billing: Invoices, Items & Payments
INSERT INTO `invoices` (`id`, `invoice_number`, `patient_id`, `encounter_id`, `subtotal`, `discount_amount`, `tax_amount`, `total_amount`, `paid_amount`, `balance_amount`, `status`, `created_by`, `finalized_at`, `created_at`) VALUES
(1, 'INV-2026-0001', 4, 3, 14250.00, 500.00, 687.50, 14437.50, 10000.00, 4437.50, 'partially_paid', 10, NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 1 DAY),
(2, 'INV-2026-0002', 1, 1, 1650.00, 0.00, 82.50, 1732.50, 1732.50, 0.00, 'paid', 10, NOW() - INTERVAL 1 HOUR, NOW() - INTERVAL 1 HOUR);

INSERT INTO `invoice_items` (`id`, `invoice_id`, `item_type`, `item_description`, `quantity`, `unit_price`, `total_price`, `reference_id`) VALUES
(1, 1, 'bed_charge', 'ICCU Bed Day Rate (BED-ICU-01)', 2, 5500.00, 11000.00, 1),
(2, 1, 'consultation', 'Senior Cardiologist Inpatient Rounds', 2, 800.00, 1600.00, 1),
(3, 1, 'lab_test', 'Cardiac Biomarker Troponin-I + Lipid Panel', 1, 1250.00, 1250.00, 1),
(4, 1, 'pharmacy', 'Dispensed Cardiac Prescription Meds', 1, 400.00, 400.00, 1),
(5, 2, 'consultation', 'Cardiology Consultation Fee (Dr. Rajesh Sharma)', 1, 800.00, 800.00, 1),
(6, 2, 'lab_test', 'Comprehensive Lipid Profile Panel', 1, 850.00, 850.00, 2);

INSERT INTO `payments` (`id`, `payment_number`, `invoice_id`, `patient_id`, `amount`, `payment_method`, `transaction_reference`, `notes`, `received_by`, `payment_date`) VALUES
(1, 'PAY-2026-0001', 1, 4, 10000.00, 'insurance', 'STAR-HLTH-CLM-88910', 'Interim cashless hospitalization pre-auth deposit', 10, NOW() - INTERVAL 1 DAY),
(2, 'PAY-2026-0002', 2, 1, 1732.50, 'upi', 'UPI-REF-9928172654', 'Full payment for OPD consultation & routine lab test', 10, NOW() - INTERVAL 50 MINUTE);

-- 21. TrustLedger: Audit Logs & Security
INSERT INTO `audit_logs` (`id`, `user_id`, `role_name`, `action_type`, `entity_type`, `entity_id`, `ip_address`, `user_agent`, `description`, `created_at`) VALUES
(1, 1, 'super_admin', 'LOGIN', 'UserSession', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Super Admin Alexander Wright logged in securely.', NOW() - INTERVAL 6 HOUR),
(2, 6, 'receptionist', 'CREATE', 'PatientProfile', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Registered new patient Arun Verma (PAT-2026-0001).', NOW() - INTERVAL 5 HOUR),
(3, 6, 'receptionist', 'CREATE', 'Appointment', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Booked appointment APT-2026-0001 for Dr. Rajesh Sharma.', NOW() - INTERVAL 2 HOUR),
(4, 6, 'receptionist', 'OVERRIDE', 'QueuePriority', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Queue priority elevated to urgent: Chest tightness reported at triage desk.', NOW() - INTERVAL 18 MINUTE),
(5, 3, 'doctor', 'CREATE', 'Prescription', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Issued prescription for Patient Anita Desai (4).', NOW() - INTERVAL 2 DAY),
(6, 9, 'pharmacist', 'STATUS_CHANGE', 'PharmacyOrder', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Dispensed medication and updated stock inventory for Atorvastatin.', NOW() - INTERVAL 2 DAY),
(7, 2, 'hospital_admin', 'STATUS_CHANGE', 'FlowLensAlert', 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Resolved Biochemistry Analyzer delay alert with extra lab personnel.', NOW() - INTERVAL 1 HOUR);

INSERT INTO `login_attempts` (`username_or_email`, `ip_address`, `user_agent`, `status`, `attempt_time`) VALUES
('superadmin@nexuscare.org', '127.0.0.1', 'Mozilla/5.0 Chrome/120.0', 'success', NOW() - INTERVAL 6 HOUR),
('admin@nexuscare.org', '127.0.0.1', 'Mozilla/5.0 Chrome/120.0', 'success', NOW() - INTERVAL 4 HOUR),
('unknown_hacker@test.com', '192.168.1.105', 'Python-urllib/3.9', 'failed', NOW() - INTERVAL 3 HOUR);

SET FOREIGN_KEY_CHECKS = 1;
