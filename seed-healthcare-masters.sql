-- =============================================================================
-- Qubex Track — Healthcare industry master / setup defaults
-- Applied after database.sql on install or reset-local-db.sh
-- Safe to re-run: clears listed master tables then re-inserts curated defaults.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Clear dependent masters first, then parents
DELETE FROM `bed`;
DELETE FROM `bed_group`;
DELETE FROM `charges`;
DELETE FROM `charge_categories`;
DELETE FROM `medicine_dosage`;
DELETE FROM `symptoms`;
DELETE FROM `finding`;
DELETE FROM `operation`;
DELETE FROM `blood_bank_products`;
DELETE FROM `department`;
DELETE FROM `specialist`;
DELETE FROM `staff_designation`;
DELETE FROM `leave_types`;
DELETE FROM `floor`;
DELETE FROM `bed_type`;
DELETE FROM `charge_units`;
DELETE FROM `tax_category`;
DELETE FROM `income_head`;
DELETE FROM `expense_head`;
DELETE FROM `medicine_category`;
DELETE FROM `medicine_group`;
DELETE FROM `dose_duration`;
DELETE FROM `dose_interval`;
DELETE FROM `pharmacy_company`;
DELETE FROM `medicine_supplier`;
DELETE FROM `unit`;
DELETE FROM `pathology_category`;
DELETE FROM `lab`;
DELETE FROM `symptoms_classification`;
DELETE FROM `finding_category`;
DELETE FROM `operation_category`;
DELETE FROM `visitors_purpose`;
DELETE FROM `source`;
DELETE FROM `complaint_type`;
DELETE FROM `global_shift`;
DELETE FROM `referral_category`;
DELETE FROM `organisation`;

-- -----------------------------------------------------------------------------
-- 1. Organisation structure
-- -----------------------------------------------------------------------------
INSERT INTO `department` (`id`, `department_name`, `is_active`) VALUES
(1, 'Outpatient (OPD)', 'yes'),
(2, 'Inpatient (IPD)', 'yes'),
(3, 'Emergency / Casualty', 'yes'),
(4, 'Cardiology', 'yes'),
(5, 'Orthopaedics', 'yes'),
(6, 'Paediatrics', 'yes'),
(7, 'Obstetrics & Gynaecology', 'yes'),
(8, 'General Medicine', 'yes'),
(9, 'General Surgery', 'yes'),
(10, 'ENT', 'yes'),
(11, 'Ophthalmology', 'yes'),
(12, 'Dermatology', 'yes'),
(13, 'Psychiatry', 'yes'),
(14, 'Radiology', 'yes'),
(15, 'Pathology / Laboratory', 'yes'),
(16, 'Pharmacy', 'yes'),
(17, 'Blood Bank', 'yes'),
(18, 'Operation Theatre', 'yes'),
(19, 'Physiotherapy', 'yes'),
(20, 'Administration', 'yes');

INSERT INTO `specialist` (`id`, `specialist_name`, `is_active`) VALUES
(1, 'General Physician', 'yes'),
(2, 'Cardiologist', 'yes'),
(3, 'Orthopaedic Surgeon', 'yes'),
(4, 'Paediatrician', 'yes'),
(5, 'Obstetrician & Gynaecologist', 'yes'),
(6, 'General Surgeon', 'yes'),
(7, 'ENT Specialist', 'yes'),
(8, 'Ophthalmologist', 'yes'),
(9, 'Dermatologist', 'yes'),
(10, 'Psychiatrist', 'yes'),
(11, 'Neurologist', 'yes'),
(12, 'Nephrologist', 'yes'),
(13, 'Pulmonologist', 'yes'),
(14, 'Gastroenterologist', 'yes'),
(15, 'Urologist', 'yes'),
(16, 'Radiologist', 'yes'),
(17, 'Pathologist', 'yes'),
(18, 'Anaesthesiologist', 'yes'),
(19, 'Emergency Medicine', 'yes'),
(20, 'Dentist', 'yes');

INSERT INTO `staff_designation` (`id`, `designation`, `is_active`) VALUES
(1, 'Doctor', 'yes'),
(2, 'Consultant', 'yes'),
(3, 'Resident Doctor', 'yes'),
(4, 'Nurse', 'yes'),
(5, 'Staff Nurse', 'yes'),
(6, 'Nursing Supervisor', 'yes'),
(7, 'Receptionist', 'yes'),
(8, 'Pharmacist', 'yes'),
(9, 'Lab Technician', 'yes'),
(10, 'Radiology Technician', 'yes'),
(11, 'OT Technician', 'yes'),
(12, 'Accountant', 'yes'),
(13, 'Billing Executive', 'yes'),
(14, 'HR Executive', 'yes'),
(15, 'Administrator', 'yes'),
(16, 'Ward Boy / Attendant', 'yes'),
(17, 'Ambulance Driver', 'yes'),
(18, 'Housekeeping', 'yes');

INSERT INTO `leave_types` (`id`, `type`, `is_active`) VALUES
(1, 'Casual Leave', 'yes'),
(2, 'Sick Leave', 'yes'),
(3, 'Earned Leave', 'yes'),
(4, 'Maternity Leave', 'yes'),
(5, 'Paternity Leave', 'yes'),
(6, 'Compensatory Off', 'yes'),
(7, 'Unpaid Leave', 'yes');

INSERT INTO `global_shift` (`id`, `name`, `start_time`, `end_time`) VALUES
(1, 'Morning', '08:00:00', '14:00:00'),
(2, 'Afternoon', '14:00:00', '20:00:00'),
(3, 'Evening', '16:00:00', '22:00:00'),
(4, 'Night', '20:00:00', '08:00:00'),
(5, 'Full Day', '09:00:00', '17:00:00');

-- -----------------------------------------------------------------------------
-- 2. Blood bank — groups + components
-- -----------------------------------------------------------------------------
INSERT INTO `blood_bank_products` (`id`, `name`, `is_blood_group`) VALUES
(1, 'A+', 1),
(2, 'A-', 1),
(3, 'B+', 1),
(4, 'B-', 1),
(5, 'AB+', 1),
(6, 'AB-', 1),
(7, 'O+', 1),
(8, 'O-', 1),
(9, 'Whole Blood', 2),
(10, 'Packed Red Blood Cells (PRBC)', 2),
(11, 'Fresh Frozen Plasma (FFP)', 2),
(12, 'Platelet Concentrate', 2),
(13, 'Cryoprecipitate', 2),
(14, 'Plasma', 2);

-- -----------------------------------------------------------------------------
-- 3. IPD floors, bed types, groups, sample beds
-- -----------------------------------------------------------------------------
INSERT INTO `floor` (`id`, `name`, `description`) VALUES
(1, 'Ground Floor', 'Emergency, OPD, Reception, Pharmacy'),
(2, 'First Floor', 'General wards and semi-private rooms'),
(3, 'Second Floor', 'Private rooms and maternity'),
(4, 'Third Floor', 'ICU, HDU, OT complex');

INSERT INTO `bed_type` (`id`, `name`) VALUES
(1, 'General'),
(2, 'Semi-Private'),
(3, 'Private'),
(4, 'Deluxe / Suite'),
(5, 'ICU'),
(6, 'HDU'),
(7, 'NICU'),
(8, 'Isolation');

INSERT INTO `bed_group` (`id`, `name`, `color`, `description`, `floor`, `is_active`) VALUES
(1, 'General Male Ward', '#4CAF50', 'Male general ward beds', 2, 1),
(2, 'General Female Ward', '#E91E63', 'Female general ward beds', 2, 1),
(3, 'Semi-Private Ward', '#2196F3', 'Shared rooms (2 beds)', 2, 1),
(4, 'Private Rooms', '#9C27B0', 'Single occupancy private rooms', 3, 1),
(5, 'Maternity Ward', '#FF9800', 'Labour and postnatal beds', 3, 1),
(6, 'ICU', '#F44336', 'Intensive care unit', 4, 1),
(7, 'HDU', '#FF5722', 'High dependency unit', 4, 1),
(8, 'NICU', '#00BCD4', 'Neonatal intensive care', 4, 1),
(9, 'Isolation Ward', '#607D8B', 'Infectious disease isolation', 1, 1),
(10, 'Emergency Observation', '#795548', 'Casualty observation beds', 1, 1);

INSERT INTO `bed` (`name`, `bed_type_id`, `bed_group_id`, `is_active`) VALUES
('GM-01', 1, 1, 'yes'), ('GM-02', 1, 1, 'yes'), ('GM-03', 1, 1, 'yes'), ('GM-04', 1, 1, 'yes'),
('GF-01', 1, 2, 'yes'), ('GF-02', 1, 2, 'yes'), ('GF-03', 1, 2, 'yes'), ('GF-04', 1, 2, 'yes'),
('SP-01', 2, 3, 'yes'), ('SP-02', 2, 3, 'yes'), ('SP-03', 2, 3, 'yes'), ('SP-04', 2, 3, 'yes'),
('PR-01', 3, 4, 'yes'), ('PR-02', 3, 4, 'yes'), ('PR-03', 3, 4, 'yes'), ('PR-04', 3, 4, 'yes'),
('MW-01', 2, 5, 'yes'), ('MW-02', 2, 5, 'yes'), ('MW-03', 3, 5, 'yes'),
('ICU-01', 5, 6, 'yes'), ('ICU-02', 5, 6, 'yes'), ('ICU-03', 5, 6, 'yes'), ('ICU-04', 5, 6, 'yes'),
('HDU-01', 6, 7, 'yes'), ('HDU-02', 6, 7, 'yes'),
('NICU-01', 7, 8, 'yes'), ('NICU-02', 7, 8, 'yes'),
('ISO-01', 8, 9, 'yes'), ('ISO-02', 8, 9, 'yes'),
('ER-01', 1, 10, 'yes'), ('ER-02', 1, 10, 'yes'), ('ER-03', 1, 10, 'yes');

-- -----------------------------------------------------------------------------
-- 4. Tax, charge units, categories, sample charges
-- -----------------------------------------------------------------------------
INSERT INTO `tax_category` (`id`, `name`, `percentage`) VALUES
(1, 'Nil / Exempt', 0.00),
(2, 'GST 5%', 5.00),
(3, 'GST 12%', 12.00),
(4, 'GST 18%', 18.00);

INSERT INTO `charge_units` (`id`, `unit`, `is_active`) VALUES
(1, 'Per Visit', 1),
(2, 'Per Day', 1),
(3, 'Per Test', 1),
(4, 'Per Procedure', 1),
(5, 'Per Unit', 1),
(6, 'Per Km', 1),
(7, 'Per Hour', 1);

-- charge_type_id: 1 Appointment, 2 OPD, 3 IPD, 4 Pathology, 5 Radiology,
-- 6 Blood Bank, 7 Ambulance, 8 Procedures, 9 Investigations, 11 Operations, 12 Others
INSERT INTO `charge_categories` (`id`, `charge_type_id`, `name`, `description`, `short_code`, `is_default`) VALUES
(1, 1, 'Appointment Fee', 'Online / walk-in appointment booking fee', 'APPT', 'yes'),
(2, 2, 'OPD Consultation', 'Outpatient doctor consultation charges', 'OPD', 'yes'),
(3, 3, 'Room Rent', 'IPD room and bed charges', 'ROOM', 'yes'),
(4, 3, 'IPD Nursing Care', 'Nursing and ward care charges', 'NURS', 'no'),
(5, 4, 'Pathology Tests', 'Laboratory investigation charges', 'PATH', 'yes'),
(6, 5, 'Radiology Tests', 'Imaging investigation charges', 'RADIO', 'yes'),
(7, 6, 'Blood Components', 'Blood bank issue charges', 'BLOOD', 'yes'),
(8, 7, 'Ambulance Service', 'Patient transport charges', 'AMB', 'yes'),
(9, 8, 'Clinical Procedures', 'Minor and ward procedures', 'PROC', 'yes'),
(10, 11, 'Operation Theatre', 'OT and surgical procedure charges', 'OT', 'yes'),
(11, 9, 'Investigations', 'Other diagnostic investigations', 'INV', 'yes'),
(12, 12, 'Miscellaneous', 'Other hospital charges', 'MISC', 'yes');

INSERT INTO `charges` (`charge_category_id`, `tax_category_id`, `charge_unit_id`, `name`, `standard_charge`, `date`, `description`, `status`) VALUES
(1, 1, 1, 'New Appointment', 100.00, CURDATE(), 'Standard appointment booking', 'active'),
(1, 1, 1, 'Follow-up Appointment', 50.00, CURDATE(), 'Follow-up appointment booking', 'active'),
(2, 1, 1, 'General Physician Consultation', 400.00, CURDATE(), 'OPD consultation - General Physician', 'active'),
(2, 1, 1, 'Specialist Consultation', 700.00, CURDATE(), 'OPD consultation - Specialist', 'active'),
(2, 1, 1, 'Emergency Consultation', 1000.00, CURDATE(), 'Emergency OPD / casualty consultation', 'active'),
(2, 1, 1, 'Follow-up Consultation', 250.00, CURDATE(), 'OPD follow-up within 7 days', 'active'),
(3, 1, 2, 'General Ward Bed / Day', 800.00, CURDATE(), 'General ward room rent per day', 'active'),
(3, 1, 2, 'Semi-Private Room / Day', 1500.00, CURDATE(), 'Semi-private room rent per day', 'active'),
(3, 1, 2, 'Private Room / Day', 2500.00, CURDATE(), 'Private room rent per day', 'active'),
(3, 1, 2, 'Deluxe Room / Day', 4000.00, CURDATE(), 'Deluxe / suite rent per day', 'active'),
(3, 1, 2, 'ICU Bed / Day', 8000.00, CURDATE(), 'ICU bed charge per day', 'active'),
(3, 1, 2, 'HDU Bed / Day', 5000.00, CURDATE(), 'HDU bed charge per day', 'active'),
(3, 1, 2, 'NICU Bed / Day', 6000.00, CURDATE(), 'NICU bed charge per day', 'active'),
(4, 1, 2, 'Nursing Charges / Day', 300.00, CURDATE(), 'Daily nursing care', 'active'),
(5, 2, 3, 'Complete Blood Count (CBC)', 350.00, CURDATE(), 'Haematology - CBC', 'active'),
(5, 2, 3, 'Blood Glucose (FBS / RBS)', 120.00, CURDATE(), 'Biochemistry - Glucose', 'active'),
(5, 2, 3, 'Lipid Profile', 600.00, CURDATE(), 'Biochemistry - Lipid profile', 'active'),
(5, 2, 3, 'Liver Function Test (LFT)', 700.00, CURDATE(), 'Biochemistry - LFT', 'active'),
(5, 2, 3, 'Kidney Function Test (KFT)', 650.00, CURDATE(), 'Biochemistry - KFT / RFT', 'active'),
(5, 2, 3, 'Thyroid Profile (T3/T4/TSH)', 800.00, CURDATE(), 'Endocrine - Thyroid', 'active'),
(5, 2, 3, 'Urine Routine', 150.00, CURDATE(), 'Clinical pathology - Urine R/M', 'active'),
(5, 2, 3, 'HbA1c', 500.00, CURDATE(), 'Biochemistry - HbA1c', 'active'),
(6, 2, 3, 'X-Ray Chest PA', 400.00, CURDATE(), 'Radiology - Chest X-Ray', 'active'),
(6, 2, 3, 'X-Ray (Single View)', 350.00, CURDATE(), 'Radiology - Single view X-Ray', 'active'),
(6, 2, 3, 'Ultrasound Abdomen', 900.00, CURDATE(), 'Radiology - USG Abdomen', 'active'),
(6, 2, 3, 'ECG', 250.00, CURDATE(), 'Cardiology - ECG', 'active'),
(6, 3, 3, 'CT Scan (Plain)', 3500.00, CURDATE(), 'Radiology - CT plain', 'active'),
(6, 3, 3, 'MRI (Plain)', 5500.00, CURDATE(), 'Radiology - MRI plain', 'active'),
(7, 1, 5, 'PRBC Issue', 1500.00, CURDATE(), 'Blood bank - Packed RBC', 'active'),
(7, 1, 5, 'FFP Issue', 800.00, CURDATE(), 'Blood bank - Fresh frozen plasma', 'active'),
(7, 1, 5, 'Platelet Issue', 1200.00, CURDATE(), 'Blood bank - Platelets', 'active'),
(8, 1, 6, 'Ambulance (Local)', 25.00, CURDATE(), 'Ambulance charge per km (local)', 'active'),
(8, 1, 7, 'Ambulance Waiting', 200.00, CURDATE(), 'Ambulance waiting per hour', 'active'),
(9, 1, 4, 'Wound Dressing', 200.00, CURDATE(), 'Minor procedure - dressing', 'active'),
(9, 1, 4, 'IV Cannulation', 150.00, CURDATE(), 'Minor procedure - IV line', 'active'),
(9, 1, 4, 'Catheterisation', 400.00, CURDATE(), 'Minor procedure - catheter', 'active'),
(9, 1, 4, 'Nebulisation', 150.00, CURDATE(), 'Respiratory therapy', 'active'),
(10, 1, 4, 'Minor OT Procedure', 5000.00, CURDATE(), 'Minor OT package (base)', 'active'),
(10, 1, 4, 'Major OT Procedure', 15000.00, CURDATE(), 'Major OT package (base)', 'active'),
(10, 1, 7, 'OT Theatre Charge / Hour', 2000.00, CURDATE(), 'OT room charge per hour', 'active'),
(12, 1, 1, 'Medical Certificate', 200.00, CURDATE(), 'Issue of medical certificate', 'active'),
(12, 1, 1, 'Registration Fee', 50.00, CURDATE(), 'One-time patient registration', 'active');

-- -----------------------------------------------------------------------------
-- 5. Finance heads
-- -----------------------------------------------------------------------------
INSERT INTO `income_head` (`id`, `income_category`, `description`, `is_active`, `is_deleted`) VALUES
(1, 'OPD Income', 'Outpatient consultation and related income', 'yes', 'no'),
(2, 'IPD Income', 'Inpatient admission and room income', 'yes', 'no'),
(3, 'Pharmacy Income', 'Medicine sales', 'yes', 'no'),
(4, 'Pathology Income', 'Laboratory test income', 'yes', 'no'),
(5, 'Radiology Income', 'Imaging test income', 'yes', 'no'),
(6, 'Blood Bank Income', 'Blood component issue income', 'yes', 'no'),
(7, 'Ambulance Income', 'Ambulance service income', 'yes', 'no'),
(8, 'OT / Procedure Income', 'Surgery and procedure income', 'yes', 'no'),
(9, 'Other Income', 'Miscellaneous hospital income', 'yes', 'no');

INSERT INTO `expense_head` (`id`, `exp_category`, `description`, `is_active`, `is_deleted`) VALUES
(1, 'Salaries & Wages', 'Staff salary and wages', 'yes', 'no'),
(2, 'Medicines & Consumables', 'Pharmacy purchase and medical consumables', 'yes', 'no'),
(3, 'Utilities', 'Electricity, water, internet, gas', 'yes', 'no'),
(4, 'Maintenance & Repairs', 'Building and equipment maintenance', 'yes', 'no'),
(5, 'Laboratory Reagents', 'Pathology / lab reagent purchase', 'yes', 'no'),
(6, 'Housekeeping', 'Cleaning and laundry', 'yes', 'no'),
(7, 'Marketing', 'Advertising and patient outreach', 'yes', 'no'),
(8, 'Professional Fees', 'Consultant / visiting doctor fees', 'yes', 'no'),
(9, 'Tax & Compliance', 'Statutory taxes and filings', 'yes', 'no'),
(10, 'Miscellaneous Expense', 'Other operating expenses', 'yes', 'no');

-- -----------------------------------------------------------------------------
-- 6. Pharmacy masters (Setup → Pharmacy)
-- Screens: Category, Supplier, Dosage, Dose Interval, Dose Duration,
--          Unit, Company, Medicine Group
-- -----------------------------------------------------------------------------
INSERT INTO `medicine_category` (`id`, `medicine_category`) VALUES
(1, 'Tablet'),
(2, 'Capsule'),
(3, 'Syrup / Suspension'),
(4, 'Injection'),
(5, 'Ointment / Cream / Gel'),
(6, 'Drops (Eye / Ear / Nasal)'),
(7, 'Inhaler / Nebulizer'),
(8, 'Powder / Sachet'),
(9, 'Surgical Consumable'),
(10, 'IV Fluid'),
(11, 'Suppository'),
(12, 'Patch / Transdermal'),
(13, 'Lotion / Solution'),
(14, 'Vaccine');

INSERT INTO `medicine_group` (`id`, `group_name`) VALUES
(1, 'Antibiotics'),
(2, 'Analgesics / NSAIDs'),
(3, 'Antipyretics'),
(4, 'Antihistamines'),
(5, 'Antacids / GI'),
(6, 'Antidiabetics'),
(7, 'Antihypertensives'),
(8, 'Vitamins & Supplements'),
(9, 'Corticosteroids'),
(10, 'Cardiac Drugs'),
(11, 'Respiratory Drugs'),
(12, 'Antiseptics / Disinfectants'),
(13, 'Antifungals'),
(14, 'Antivirals'),
(15, 'Anticoagulants'),
(16, 'Thyroid Drugs'),
(17, 'Psychiatric / CNS'),
(18, 'Hormones'),
(19, 'Oncology / Cytotoxics'),
(20, 'Vaccines & Immunoglobulins');

INSERT INTO `dose_interval` (`id`, `name`) VALUES
(1, 'Once daily (OD)'),
(2, 'Twice daily (BD)'),
(3, 'Thrice daily (TDS)'),
(4, 'Four times daily (QID)'),
(5, 'Every 4 hours'),
(6, 'Every 6 hours'),
(7, 'Every 8 hours'),
(8, 'Every 12 hours'),
(9, 'SOS / As needed'),
(10, 'STAT (Immediate)'),
(11, 'Before food'),
(12, 'After food'),
(13, 'With food'),
(14, 'At bedtime (HS)'),
(15, 'Weekly'),
(16, 'Alternate day');

INSERT INTO `dose_duration` (`id`, `name`) VALUES
(1, '1 Day'),
(2, '2 Days'),
(3, '3 Days'),
(4, '5 Days'),
(5, '7 Days'),
(6, '10 Days'),
(7, '14 Days'),
(8, '21 Days'),
(9, '1 Month'),
(10, '2 Months'),
(11, '3 Months'),
(12, 'Until further advice'),
(13, 'Single dose');

INSERT INTO `unit` (`id`, `unit_name`, `unit_type`) VALUES
(1, 'mg', 'pharmacy'),
(2, 'g', 'pharmacy'),
(3, 'ml', 'pharmacy'),
(4, 'mcg', 'pharmacy'),
(5, 'IU', 'pharmacy'),
(6, 'Tablet', 'pharmacy'),
(7, 'Capsule', 'pharmacy'),
(8, 'Vial', 'pharmacy'),
(9, 'Ampoule', 'pharmacy'),
(10, 'Bottle', 'pharmacy'),
(11, 'Strip', 'pharmacy'),
(12, 'Tube', 'pharmacy'),
(13, 'Sachet', 'pharmacy'),
(14, 'Drop', 'pharmacy'),
(15, 'Puff', 'pharmacy'),
(16, 'Pack', 'pharmacy'),
(17, 'Litre', 'pharmacy'),
(18, '%', 'patho'),
(19, 'g/dL', 'patho'),
(20, 'mg/dL', 'patho'),
(21, 'mmol/L', 'patho'),
(22, 'U/L', 'patho'),
(23, 'cells/cumm', 'patho'),
(24, 'x10^3/uL', 'patho'),
(25, 'Film', 'radio'),
(26, 'Study', 'radio'),
(27, 'Series', 'radio');

INSERT INTO `medicine_dosage` (`medicine_category_id`, `dosage`, `units_id`) VALUES
-- Tablet
(1, '5', 1), (1, '10', 1), (1, '25', 1), (1, '50', 1), (1, '100', 1),
(1, '250', 1), (1, '500', 1), (1, '650', 1), (1, '1', 6),
-- Capsule
(2, '250', 1), (2, '500', 1), (2, '1', 7),
-- Syrup / Suspension
(3, '5', 3), (3, '10', 3), (3, '15', 3),
-- Injection
(4, '1', 8), (4, '1', 9), (4, '2', 3), (4, '5', 3),
-- Ointment / Cream
(5, '15', 2), (5, '20', 2), (5, '30', 2),
-- Drops
(6, '5', 3), (6, '10', 3), (6, '1', 14),
-- Inhaler / Nebulizer
(7, '1', 15), (7, '2', 15), (7, '2.5', 3),
-- Powder / Sachet
(8, '1', 13), (8, '5', 2),
-- IV Fluid
(10, '100', 3), (10, '500', 3), (10, '1000', 3),
-- Suppository
(11, '125', 1), (11, '250', 1),
-- Vaccine
(14, '0.5', 3), (14, '1', 8);

INSERT INTO `pharmacy_company` (`id`, `company_name`) VALUES
(1, 'Sun Pharmaceutical Industries'),
(2, 'Cipla Ltd'),
(3, 'Dr. Reddy''s Laboratories'),
(4, 'Lupin Ltd'),
(5, 'Abbott India'),
(6, 'GlaxoSmithKline (GSK)'),
(7, 'Pfizer India'),
(8, 'Alkem Laboratories'),
(9, 'Zydus Cadila'),
(10, 'Intas Pharmaceuticals'),
(11, 'Mankind Pharma'),
(12, 'Sanofi India'),
(13, 'Torrent Pharmaceuticals'),
(14, 'Aurobindo Pharma'),
(15, 'Biocon'),
(16, 'Baxter India'),
(17, 'Fresenius Kabi'),
(18, 'Nestlé Health Science'),
(19, 'Himalaya Wellness'),
(20, 'Generic / Local Manufacturer');

INSERT INTO `medicine_supplier` (`id`, `supplier`, `contact`, `supplier_person`, `supplier_person_contact`, `supplier_drug_licence`, `address`) VALUES
(1, 'Metro Pharma Distributors', '1800-200-1001', 'Rajesh Kumar', '9876500001', 'DL-MH-20-123456', 'Andheri East, Mumbai'),
(2, 'Apollo Pharmacy Wholesale', '1800-200-1002', 'Priya Sharma', '9876500002', 'DL-KA-21-234567', 'Koramangala, Bengaluru'),
(3, 'MedPlus Distribution Hub', '1800-200-1003', 'Amit Patel', '9876500003', 'DL-GJ-22-345678', 'Satellite, Ahmedabad'),
(4, 'Hospital Central Stores', '022-40001234', 'Store In-charge', '9876500004', 'IN-HOUSE', 'Hospital Campus Store'),
(5, 'City Chemist Supply Co.', '022-40005678', 'Suresh Nair', '9876500005', 'DL-MH-23-456789', 'Dadar West, Mumbai'),
(6, 'National Pharma Agency', '011-40007890', 'Neha Gupta', '9876500006', 'DL-DL-24-567890', 'Okhla, New Delhi'),
(7, 'LifeCare Medical Distributors', '040-40009000', 'Vikram Reddy', '9876500007', 'DL-TS-25-678901', 'Hitech City, Hyderabad'),
(8, 'IV Fluids & Surgical Mart', '033-40001111', 'Ananya Das', '9876500008', 'DL-WB-26-789012', 'Salt Lake, Kolkata');

-- -----------------------------------------------------------------------------
-- 7. Diagnostics categories
-- -----------------------------------------------------------------------------
INSERT INTO `pathology_category` (`id`, `category_name`) VALUES
(1, 'Haematology'),
(2, 'Biochemistry'),
(3, 'Clinical Pathology'),
(4, 'Microbiology'),
(5, 'Serology / Immunology'),
(6, 'Histopathology'),
(7, 'Cytology'),
(8, 'Molecular Diagnostics');

INSERT INTO `lab` (`id`, `lab_name`) VALUES
(1, 'X-Ray'),
(2, 'Ultrasound (USG)'),
(3, 'CT Scan'),
(4, 'MRI'),
(5, 'Mammography'),
(6, 'ECG'),
(7, 'Echo / 2D Echo'),
(8, 'Doppler'),
(9, 'DEXA / Bone Density'),
(10, 'Fluoroscopy');

-- -----------------------------------------------------------------------------
-- 8. Clinical symptoms & findings
-- -----------------------------------------------------------------------------
INSERT INTO `symptoms_classification` (`id`, `symptoms_type`) VALUES
(1, 'General'),
(2, 'Respiratory'),
(3, 'Gastrointestinal'),
(4, 'Cardiovascular'),
(5, 'Neurological'),
(6, 'Musculoskeletal'),
(7, 'Genitourinary'),
(8, 'ENT / Eye'),
(9, 'Dermatological'),
(10, 'Obstetric / Gynaecological');

INSERT INTO `symptoms` (`symptoms_title`, `description`, `type`) VALUES
('Fever', 'Elevated body temperature', '1'),
('Fatigue / Weakness', 'Generalised tiredness', '1'),
('Loss of Appetite', 'Reduced desire to eat', '1'),
('Weight Loss', 'Unintentional weight loss', '1'),
('Cough', 'Dry or productive cough', '2'),
('Shortness of Breath', 'Dyspnoea / breathlessness', '2'),
('Chest Congestion', 'Respiratory congestion', '2'),
('Sore Throat', 'Pharyngeal pain', '2'),
('Nausea / Vomiting', 'GI upset with vomiting', '3'),
('Abdominal Pain', 'Pain in abdomen', '3'),
('Diarrhoea', 'Loose stools', '3'),
('Constipation', 'Infrequent bowel movements', '3'),
('Chest Pain', 'Cardiac or non-cardiac chest pain', '4'),
('Palpitations', 'Awareness of heartbeat', '4'),
('Swelling of Legs', 'Pedal oedema', '4'),
('Headache', 'Cephalalgia', '5'),
('Dizziness', 'Light-headedness / vertigo', '5'),
('Seizure', 'Convulsion episode', '5'),
('Joint Pain', 'Arthralgia', '6'),
('Back Pain', 'Lumbago / spinal pain', '6'),
('Burning Micturition', 'Dysuria', '7'),
('Frequent Urination', 'Polyuria / frequency', '7'),
('Ear Pain', 'Otalgia', '8'),
('Vision Blurring', 'Reduced visual clarity', '8'),
('Skin Rash', 'Cutaneous eruption', '9'),
('Itching', 'Pruritus', '9'),
('Menstrual Irregularity', 'Abnormal menstrual cycle', '10'),
('Lower Abdominal Pain', 'Pelvic / lower abdomen pain', '10');

INSERT INTO `finding_category` (`id`, `category`) VALUES
(1, 'General Examination'),
(2, 'Respiratory System'),
(3, 'Cardiovascular System'),
(4, 'Abdomen'),
(5, 'Central Nervous System'),
(6, 'ENT'),
(7, 'Musculoskeletal');

INSERT INTO `finding` (`name`, `description`, `finding_category_id`) VALUES
('Afebrile', 'No fever at examination', 1),
('Febrile', 'Fever present', 1),
('Pallor Present', 'Anaemic appearance', 1),
('Icterus Present', 'Jaundice noted', 1),
('Lymphadenopathy', 'Enlarged lymph nodes', 1),
('Normal Breath Sounds', 'Clear bilateral air entry', 2),
('Wheeze', 'Expiratory wheeze heard', 2),
('Crepitations', 'Crackles on auscultation', 2),
('Reduced Air Entry', 'Diminished breath sounds', 2),
('S1 S2 Normal', 'Normal heart sounds', 3),
('Murmur Present', 'Cardiac murmur auscultated', 3),
('Tachycardia', 'Heart rate elevated', 3),
('Soft Abdomen', 'Abdomen soft, non-tender', 4),
('Abdominal Tenderness', 'Tenderness on palpation', 4),
('Hepatomegaly', 'Enlarged liver', 4),
('Oriented to Time Place Person', 'Normal sensorium', 5),
('Neck Stiffness', 'Meningeal sign', 5),
('Tonsillar Enlargement', 'Enlarged tonsils', 6),
('TM Intact', 'Tympanic membrane intact', 6),
('Restricted Joint Movement', 'Reduced ROM', 7),
('Local Tenderness (Joint)', 'Joint tenderness', 7);

-- -----------------------------------------------------------------------------
-- 9. Operation Theatre
-- -----------------------------------------------------------------------------
INSERT INTO `operation_category` (`id`, `category`, `is_active`) VALUES
(1, 'General Surgery', 'yes'),
(2, 'Orthopaedic Surgery', 'yes'),
(3, 'Obstetrics & Gynaecology', 'yes'),
(4, 'ENT Surgery', 'yes'),
(5, 'Ophthalmic Surgery', 'yes'),
(6, 'Urology', 'yes'),
(7, 'Cardiac Surgery', 'yes'),
(8, 'Neurosurgery', 'yes'),
(9, 'Plastic / Cosmetic Surgery', 'yes'),
(10, 'Dental / Maxillofacial', 'yes');

INSERT INTO `operation` (`operation`, `category_id`, `is_active`) VALUES
('Appendicectomy', 1, 'yes'),
('Hernia Repair', 1, 'yes'),
('Cholecystectomy (Laparoscopic)', 1, 'yes'),
('Wound Debridement', 1, 'yes'),
('Fracture Fixation (ORIF)', 2, 'yes'),
('Knee Arthroscopy', 2, 'yes'),
('Caesarean Section', 3, 'yes'),
('Dilatation & Curettage (D&C)', 3, 'yes'),
('Tonsillectomy', 4, 'yes'),
('Septoplasty', 4, 'yes'),
('Cataract Surgery (Phaco)', 5, 'yes'),
('Circumcision', 6, 'yes'),
('TURP', 6, 'yes'),
('Tooth Extraction', 10, 'yes');

-- -----------------------------------------------------------------------------
-- 10. Front office / CRM
-- -----------------------------------------------------------------------------
INSERT INTO `visitors_purpose` (`id`, `visitors_purpose`, `description`) VALUES
(1, 'Patient Visit', 'Visiting admitted patient'),
(2, 'Consultation Enquiry', 'Enquiry about OPD consultation'),
(3, 'Admission Enquiry', 'Enquiry about IPD admission'),
(4, 'Report Collection', 'Collecting lab / radiology reports'),
(5, 'Billing / Payment', 'Bill settlement or insurance query'),
(6, 'Meeting Staff', 'Meeting doctor or hospital staff'),
(7, 'Vendor / Delivery', 'Supply or courier delivery'),
(8, 'Complaint', 'Registering a complaint');

INSERT INTO `source` (`id`, `source`, `description`) VALUES
(1, 'Walk-in', 'Direct walk-in patient'),
(2, 'Doctor Referral', 'Referred by external doctor'),
(3, 'Hospital Referral', 'Referred by another hospital'),
(4, 'Online / Website', 'Booked or enquired online'),
(5, 'Mobile App', 'Came via mobile application'),
(6, 'Social Media', 'Facebook / Instagram / ads'),
(7, 'Camp / Outreach', 'Health camp or outreach'),
(8, 'Staff Referral', 'Referred by hospital staff'),
(9, 'Insurance / TPA', 'Via insurance network');

INSERT INTO `complaint_type` (`id`, `complaint_type`, `description`) VALUES
(1, 'Waiting Time', 'Long waiting or delay'),
(2, 'Billing Dispute', 'Billing or payment related'),
(3, 'Staff Behaviour', 'Behaviour of staff / doctor'),
(4, 'Cleanliness', 'Hygiene or housekeeping'),
(5, 'Clinical Care', 'Quality of medical care'),
(6, 'Facility / Amenities', 'Room, food, parking, etc.'),
(7, 'Report Delay', 'Lab / radiology report delay'),
(8, 'Other', 'Other complaints');

INSERT INTO `referral_category` (`id`, `name`, `is_active`) VALUES
(1, 'Doctor', 1),
(2, 'Hospital', 1),
(3, 'Agent / Facilitator', 1),
(4, 'Corporate', 1),
(5, 'Online Partner', 1),
(6, 'Self / Walk-in', 1);

INSERT INTO `organisation` (`id`, `organisation_name`, `code`, `contact_no`, `address`, `contact_person_name`, `contact_person_phone`) VALUES
(1, 'Sample TPA - HealthSecure', 'TPA001', '1800123456', 'Sample Address, City', 'TPA Desk', '9876543210'),
(2, 'Sample Insurance - CarePlus', 'INS001', '1800654321', 'Sample Address, City', 'Claims Desk', '9876543211');

SET FOREIGN_KEY_CHECKS = 1;

-- Reset AUTO_INCREMENT hints (optional consistency after explicit IDs)
ALTER TABLE `department` AUTO_INCREMENT = 21;
ALTER TABLE `specialist` AUTO_INCREMENT = 21;
ALTER TABLE `staff_designation` AUTO_INCREMENT = 19;
ALTER TABLE `leave_types` AUTO_INCREMENT = 8;
ALTER TABLE `global_shift` AUTO_INCREMENT = 6;
ALTER TABLE `blood_bank_products` AUTO_INCREMENT = 15;
ALTER TABLE `floor` AUTO_INCREMENT = 5;
ALTER TABLE `bed_type` AUTO_INCREMENT = 9;
ALTER TABLE `bed_group` AUTO_INCREMENT = 11;
ALTER TABLE `tax_category` AUTO_INCREMENT = 5;
ALTER TABLE `charge_units` AUTO_INCREMENT = 8;
ALTER TABLE `charge_categories` AUTO_INCREMENT = 13;
ALTER TABLE `income_head` AUTO_INCREMENT = 10;
ALTER TABLE `expense_head` AUTO_INCREMENT = 11;
ALTER TABLE `medicine_category` AUTO_INCREMENT = 15;
ALTER TABLE `medicine_group` AUTO_INCREMENT = 21;
ALTER TABLE `dose_interval` AUTO_INCREMENT = 17;
ALTER TABLE `dose_duration` AUTO_INCREMENT = 14;
ALTER TABLE `unit` AUTO_INCREMENT = 28;
ALTER TABLE `pharmacy_company` AUTO_INCREMENT = 21;
ALTER TABLE `medicine_supplier` AUTO_INCREMENT = 9;
ALTER TABLE `pathology_category` AUTO_INCREMENT = 9;
ALTER TABLE `lab` AUTO_INCREMENT = 11;
ALTER TABLE `symptoms_classification` AUTO_INCREMENT = 11;
ALTER TABLE `finding_category` AUTO_INCREMENT = 8;
ALTER TABLE `operation_category` AUTO_INCREMENT = 11;
ALTER TABLE `visitors_purpose` AUTO_INCREMENT = 9;
ALTER TABLE `source` AUTO_INCREMENT = 10;
ALTER TABLE `complaint_type` AUTO_INCREMENT = 9;
ALTER TABLE `referral_category` AUTO_INCREMENT = 7;
ALTER TABLE `organisation` AUTO_INCREMENT = 3;
