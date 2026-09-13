-- =============================================================================
-- Qubex Track — Clinical Setup masters
-- Pathology / Radiology / Vitals / Symptoms / Findings
-- Applied after seed-healthcare-masters.sql (safe to re-run)
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM `pathology_parameterdetails`;
DELETE FROM `radiology_parameterdetails`;
DELETE FROM `pathology`;
DELETE FROM `radio`;
DELETE FROM `pathology_parameter`;
DELETE FROM `radiology_parameter`;
DELETE FROM `vitals` WHERE `is_system` = 0;
DELETE FROM `symptoms`;
DELETE FROM `finding`;
DELETE FROM `symptoms_classification`;
DELETE FROM `finding_category`;
DELETE FROM `unit` WHERE `id` BETWEEN 28 AND 40;

INSERT INTO `unit` (`id`, `unit_name`, `unit_type`) VALUES
(28, 'µIU/mL', 'patho'),
(29, 'ng/mL', 'patho'),
(30, 'pg/mL', 'patho'),
(31, 'fL', 'patho'),
(32, 'mm/hr', 'patho'),
(33, 'sec', 'patho'),
(34, 'ratio', 'patho'),
(35, 'bpm', 'radio'),
(36, '% EF', 'radio'),
(37, 'mGy', 'radio'),
(38, 'HU', 'radio'),
(39, 'mm', 'radio'),
(40, 'cm', 'radio');

-- -----------------------------------------------------------------------------
-- Pathology parameters (Setup → Pathology → Parameter)
-- Units: 18=% 19=g/dL 20=mg/dL 21=mmol/L 22=U/L 23=cells/cumm 24=x10^3/uL
-- -----------------------------------------------------------------------------
INSERT INTO `pathology_parameter`
(`id`, `parameter_name`, `test_value`, `reference_range`, `range_from`, `range_to`, `gender`, `unit`, `description`) VALUES
(1,  'Haemoglobin',             '', '13.0-17.0',   '13.0', '17.0', '', 19, 'Adult male reference; female typically 12-15 g/dL'),
(2,  'RBC Count',               '', '4.5-5.5',     '4.5',  '5.5',  '', 24, 'Red blood cell count'),
(3,  'WBC Count',               '', '4000-11000',  '4000', '11000','', 23, 'Total leucocyte count'),
(4,  'Platelet Count',          '', '150-450',     '150',  '450',  '', 24, 'Platelets x10^3/uL'),
(5,  'Haematocrit (PCV)',       '', '40-50',       '40',   '50',   '', 18, 'Packed cell volume %'),
(6,  'MCV',                     '', '80-100',      '80',   '100',  '', 31, 'Mean corpuscular volume'),
(7,  'MCH',                     '', '27-33',       '27',   '33',   '', 30, 'Mean corpuscular haemoglobin'),
(8,  'MCHC',                    '', '32-36',       '32',   '36',   '', 19, 'Mean corpuscular Hb concentration'),
(9,  'Neutrophils',             '', '40-75',       '40',   '75',   '', 18, 'Differential count %'),
(10, 'Lymphocytes',             '', '20-40',       '20',   '40',   '', 18, 'Differential count %'),
(11, 'Eosinophils',             '', '1-6',         '1',    '6',    '', 18, 'Differential count %'),
(12, 'Monocytes',               '', '2-10',        '2',    '10',   '', 18, 'Differential count %'),
(13, 'ESR',                     '', '0-20',        '0',    '20',   '', 32, 'Erythrocyte sedimentation rate'),
(14, 'Blood Glucose (Fasting)', '', '70-100',      '70',   '100',  '', 20, 'FBS'),
(15, 'Blood Glucose (Random)',  '', '70-140',      '70',   '140',  '', 20, 'RBS'),
(16, 'HbA1c',                   '', '4.0-5.6',     '4.0',  '5.6',  '', 18, 'Glycated haemoglobin %'),
(17, 'Total Cholesterol',       '', '125-200',     '125',  '200',  '', 20, 'Lipid profile'),
(18, 'HDL Cholesterol',         '', '40-60',       '40',   '60',   '', 20, 'HDL'),
(19, 'LDL Cholesterol',         '', '0-100',       '0',    '100',  '', 20, 'LDL'),
(20, 'Triglycerides',           '', '0-150',       '0',    '150',  '', 20, 'TG'),
(21, 'SGOT / AST',              '', '0-40',        '0',    '40',   '', 22, 'Liver enzyme'),
(22, 'SGPT / ALT',              '', '0-40',        '0',    '40',   '', 22, 'Liver enzyme'),
(23, 'Alkaline Phosphatase',    '', '40-129',      '40',   '129',  '', 22, 'ALP'),
(24, 'Total Bilirubin',         '', '0.2-1.2',     '0.2',  '1.2',  '', 20, 'LFT'),
(25, 'Direct Bilirubin',        '', '0.0-0.3',     '0.0',  '0.3',  '', 20, 'LFT'),
(26, 'Total Protein',           '', '6.0-8.3',     '6.0',  '8.3',  '', 19, 'LFT'),
(27, 'Albumin',                 '', '3.5-5.0',     '3.5',  '5.0',  '', 19, 'LFT'),
(28, 'Urea',                    '', '15-40',       '15',   '40',   '', 20, 'KFT'),
(29, 'Creatinine',              '', '0.6-1.3',     '0.6',  '1.3',  '', 20, 'KFT'),
(30, 'Uric Acid',               '', '3.5-7.2',     '3.5',  '7.2',  '', 20, 'KFT'),
(31, 'Sodium',                  '', '135-145',     '135',  '145',  '', 21, 'Electrolyte'),
(32, 'Potassium',               '', '3.5-5.1',     '3.5',  '5.1',  '', 21, 'Electrolyte'),
(33, 'Chloride',                '', '98-107',      '98',   '107',  '', 21, 'Electrolyte'),
(34, 'TSH',                     '', '0.4-4.0',     '0.4',  '4.0',  '', 28, 'Thyroid stimulating hormone'),
(35, 'T3',                      '', '80-200',      '80',   '200',  '', 29, 'Triiodothyronine'),
(36, 'T4',                      '', '5.0-12.0',    '5.0',  '12.0', '', 29, 'Thyroxine'),
(37, 'Urine Colour',            '', 'Pale yellow', '',     '',     '', NULL, 'Physical examination'),
(38, 'Urine Appearance',        '', 'Clear',       '',     '',     '', NULL, 'Physical examination'),
(39, 'Urine Albumin',           '', 'Nil',         '',     '',     '', NULL, 'Chemical examination'),
(40, 'Urine Sugar',             '', 'Nil',         '',     '',     '', NULL, 'Chemical examination'),
(41, 'Urine RBC',               '', '0-2',         '0',    '2',    '', 23, 'Microscopy / HPF'),
(42, 'Urine WBC',               '', '0-5',         '0',    '5',    '', 23, 'Microscopy / HPF');

-- -----------------------------------------------------------------------------
-- Radiology parameters (Setup → Radiology → Parameter)
-- unit stored as unit.id string (app joins unit table)
-- -----------------------------------------------------------------------------
INSERT INTO `radiology_parameter`
(`id`, `parameter_name`, `test_value`, `reference_range`, `range_from`, `range_to`, `gender`, `unit`, `description`) VALUES
(1,  'Technique',           '', 'Standard',              '', '', '', '26', 'Imaging technique used'),
(2,  'Clinical Indication', '', 'As provided',           '', '', '', '26', 'Reason for study'),
(3,  'Findings',            '', 'Normal',                '', '', '', '26', 'Radiologist findings'),
(4,  'Impression',          '', 'Normal study',          '', '', '', '26', 'Final impression'),
(5,  'Contrast Used',       '', 'None',                  '', '', '', '26', 'Contrast media details'),
(6,  'Radiation Dose',      '', '0-5',                   '0', '5', '', '37', 'Approximate dose in mGy'),
(7,  'Heart Rate',          '', '60-100',                '60', '100', '', '35', 'ECG / Echo heart rate'),
(8,  'Ejection Fraction',   '', '55-70',                 '55', '70', '', '36', 'Echo LV ejection fraction'),
(9,  'Lesion Size',         '', '0-50',                  '0', '50', '', '39', 'Measured lesion size mm'),
(10, 'Views Taken',         '', 'PA / Lateral',          '', '', '', '25', 'X-Ray views'),
(11, 'Organ Visualised',    '', 'Adequate',              '', '', '', '26', 'USG organ visualisation'),
(12, 'Recommendation',      '', 'Clinical correlation',  '', '', '', '26', 'Follow-up advice');

-- -----------------------------------------------------------------------------
-- Sample pathology tests + parameter mapping (charges 99–106)
-- Categories: 1 Haematology, 2 Biochemistry, 3 Clinical Pathology
-- -----------------------------------------------------------------------------
INSERT INTO `pathology`
(`id`, `test_name`, `short_name`, `test_type`, `pathology_category_id`, `unit`, `sub_category`, `report_days`, `method`, `charge_id`) VALUES
(1, 'Complete Blood Count (CBC)',  'CBC',   'pathology', 1, '', 'Blood', '1', 'Automated analyser', 99),
(2, 'Blood Glucose (FBS / RBS)',   'GLU',   'pathology', 2, '', 'Blood', '1', 'Hexokinase / GOD-POD', 100),
(3, 'Lipid Profile',               'LIPID', 'pathology', 2, '', 'Blood', '1', 'Enzymatic', 101),
(4, 'Liver Function Test (LFT)',   'LFT',   'pathology', 2, '', 'Blood', '1', 'Spectrophotometry', 102),
(5, 'Kidney Function Test (KFT)',  'KFT',   'pathology', 2, '', 'Blood', '1', 'Spectrophotometry', 103),
(6, 'Thyroid Profile (T3/T4/TSH)', 'THY',   'pathology', 2, '', 'Blood', '1', 'CLIA / ELISA', 104),
(7, 'Urine Routine',               'URINE', 'pathology', 3, '', 'Urine', '1', 'Dipstick + Microscopy', 105),
(8, 'HbA1c',                       'HBA1C', 'pathology', 2, '', 'Blood', '1', 'HPLC / Immunoassay', 106);

INSERT INTO `pathology_parameterdetails` (`pathology_id`, `pathology_parameter_id`) VALUES
(1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),(1,11),(1,12),
(2,14),(2,15),
(3,17),(3,18),(3,19),(3,20),
(4,21),(4,22),(4,23),(4,24),(4,25),(4,26),(4,27),
(5,28),(5,29),(5,30),(5,31),(5,32),(5,33),
(6,34),(6,35),(6,36),
(7,37),(7,38),(7,39),(7,40),(7,41),(7,42),
(8,16);

-- -----------------------------------------------------------------------------
-- Sample radiology tests + parameter mapping (charges 107–112)
-- lab categories: 1 X-Ray, 2 USG, 3 CT, 4 MRI, 6 ECG
-- -----------------------------------------------------------------------------
INSERT INTO `radio`
(`id`, `test_name`, `short_name`, `test_type`, `radiology_category_id`, `sub_category`, `report_days`, `charge_id`) VALUES
(1, 'X-Ray Chest PA',      'XR-CHEST',  'radiology', 1, 'Chest',   '1', 107),
(2, 'X-Ray (Single View)', 'XR-1V',     'radiology', 1, 'General', '1', 108),
(3, 'Ultrasound Abdomen',  'USG-ABD',   'radiology', 2, 'Abdomen', '1', 109),
(4, 'ECG',                 'ECG',       'radiology', 6, 'Cardiac', '0', 110),
(5, 'CT Scan (Plain)',     'CT-PLAIN',  'radiology', 3, 'CT',      '1', 111),
(6, 'MRI (Plain)',         'MRI-PLAIN', 'radiology', 4, 'MRI',     '2', 112);

INSERT INTO `radiology_parameterdetails` (`radiology_id`, `radiology_parameter_id`) VALUES
(1,1),(1,2),(1,3),(1,4),(1,10),(1,12),
(2,1),(2,2),(2,3),(2,4),(2,10),(2,12),
(3,1),(3,2),(3,3),(3,4),(3,11),(3,12),
(4,1),(4,2),(4,3),(4,4),(4,7),(4,12),
(5,1),(5,2),(5,3),(5,4),(5,5),(5,6),(5,12),
(6,1),(6,2),(6,3),(6,4),(6,5),(6,12);

-- -----------------------------------------------------------------------------
-- Vitals (Setup → Vitals) — keep system rows; add extras
-- -----------------------------------------------------------------------------
INSERT INTO `vitals` (`name`, `reference_range`, `unit`, `is_system`) VALUES
('SpO2', '95 - 100', '%', 0),
('Respiratory Rate', '12 - 20', 'breaths/min', 0),
('BMI', '18.5 - 24.9', 'kg/m2', 0),
('Pain Score', '0 - 10', 'score', 0),
('Blood Sugar (Capillary)', '70 - 140', 'mg/dL', 0),
('GCS', '3 - 15', 'score', 0),
('Head Circumference', '30 - 60', 'cm', 0),
('Oxygen Flow', '0 - 15', 'L/min', 0);

-- -----------------------------------------------------------------------------
-- Symptoms (Setup → Symptoms)
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
(10, 'Obstetric / Gynaecological'),
(11, 'Psychiatric / Behavioural'),
(12, 'Endocrine / Metabolic');

INSERT INTO `symptoms` (`symptoms_title`, `description`, `type`) VALUES
('Fever', 'Elevated body temperature', '1'),
('Fatigue / Weakness', 'Generalised tiredness', '1'),
('Loss of Appetite', 'Reduced desire to eat', '1'),
('Weight Loss', 'Unintentional weight loss', '1'),
('Night Sweats', 'Excessive sweating at night', '1'),
('Malaise', 'General discomfort', '1'),
('Cough', 'Dry or productive cough', '2'),
('Shortness of Breath', 'Dyspnoea / breathlessness', '2'),
('Chest Congestion', 'Respiratory congestion', '2'),
('Sore Throat', 'Pharyngeal pain', '2'),
('Haemoptysis', 'Coughing up blood', '2'),
('Wheezing', 'Audible wheeze / whistling breath', '2'),
('Nausea / Vomiting', 'GI upset with vomiting', '3'),
('Abdominal Pain', 'Pain in abdomen', '3'),
('Diarrhoea', 'Loose stools', '3'),
('Constipation', 'Infrequent bowel movements', '3'),
('Heartburn', 'Acid reflux / pyrosis', '3'),
('Jaundice', 'Yellowish discoloration', '3'),
('Chest Pain', 'Cardiac or non-cardiac chest pain', '4'),
('Palpitations', 'Awareness of heartbeat', '4'),
('Swelling of Legs', 'Pedal oedema', '4'),
('Syncope', 'Fainting / loss of consciousness', '4'),
('Headache', 'Cephalalgia', '5'),
('Dizziness', 'Light-headedness / vertigo', '5'),
('Seizure', 'Convulsion episode', '5'),
('Weakness of Limbs', 'Motor weakness', '5'),
('Numbness / Tingling', 'Paraesthesia', '5'),
('Joint Pain', 'Arthralgia', '6'),
('Back Pain', 'Lumbago / spinal pain', '6'),
('Neck Pain', 'Cervical pain', '6'),
('Muscle Cramps', 'Involuntary muscle contraction', '6'),
('Burning Micturition', 'Dysuria', '7'),
('Frequent Urination', 'Polyuria / frequency', '7'),
('Oliguria', 'Reduced urine output', '7'),
('Haematuria', 'Blood in urine', '7'),
('Ear Pain', 'Otalgia', '8'),
('Hearing Loss', 'Reduced hearing', '8'),
('Vision Blurring', 'Reduced visual clarity', '8'),
('Nasal Discharge', 'Rhinorrhoea', '8'),
('Skin Rash', 'Cutaneous eruption', '9'),
('Itching', 'Pruritus', '9'),
('Ulcer / Wound', 'Skin ulceration', '9'),
('Menstrual Irregularity', 'Abnormal menstrual cycle', '10'),
('Lower Abdominal Pain', 'Pelvic / lower abdomen pain', '10'),
('Vaginal Discharge', 'Abnormal discharge', '10'),
('Anxiety', 'Excessive worry / restlessness', '11'),
('Insomnia', 'Difficulty sleeping', '11'),
('Low Mood', 'Depressive symptoms', '11'),
('Excessive Thirst', 'Polydipsia', '12'),
('Excessive Hunger', 'Polyphagia', '12'),
('Heat Intolerance', 'Thyroid-related symptom', '12');

-- -----------------------------------------------------------------------------
-- Findings (Setup → Findings)
-- -----------------------------------------------------------------------------
INSERT INTO `finding_category` (`id`, `category`) VALUES
(1, 'General Examination'),
(2, 'Respiratory System'),
(3, 'Cardiovascular System'),
(4, 'Abdomen'),
(5, 'Central Nervous System'),
(6, 'ENT'),
(7, 'Musculoskeletal'),
(8, 'Eye'),
(9, 'Skin');

INSERT INTO `finding` (`name`, `description`, `finding_category_id`) VALUES
('Afebrile', 'No fever at examination', 1),
('Febrile', 'Fever present', 1),
('Pallor Present', 'Anaemic appearance', 1),
('Icterus Present', 'Jaundice noted', 1),
('Cyanosis', 'Bluish discoloration', 1),
('Clubbing', 'Digital clubbing', 1),
('Lymphadenopathy', 'Enlarged lymph nodes', 1),
('Dehydration', 'Signs of dehydration', 1),
('Normal Breath Sounds', 'Clear bilateral air entry', 2),
('Wheeze', 'Expiratory wheeze heard', 2),
('Crepitations', 'Crackles on auscultation', 2),
('Reduced Air Entry', 'Diminished breath sounds', 2),
('Bronchial Breathing', 'Bronchial breath sounds', 2),
('S1 S2 Normal', 'Normal heart sounds', 3),
('Murmur Present', 'Cardiac murmur auscultated', 3),
('Tachycardia', 'Heart rate elevated', 3),
('Bradycardia', 'Heart rate reduced', 3),
('Pedal Oedema', 'Bilateral leg swelling', 3),
('Soft Abdomen', 'Abdomen soft, non-tender', 4),
('Abdominal Tenderness', 'Tenderness on palpation', 4),
('Hepatomegaly', 'Enlarged liver', 4),
('Splenomegaly', 'Enlarged spleen', 4),
('Ascites', 'Free fluid in abdomen', 4),
('Oriented to Time Place Person', 'Normal sensorium', 5),
('Neck Stiffness', 'Meningeal sign', 5),
('Plantars Extensor', 'Babinski positive', 5),
('Tonsillar Enlargement', 'Enlarged tonsils', 6),
('TM Intact', 'Tympanic membrane intact', 6),
('DNS / Deviated Nasal Septum', 'Nasal septum deviation', 6),
('Restricted Joint Movement', 'Reduced ROM', 7),
('Local Tenderness (Joint)', 'Joint tenderness', 7),
('Swelling of Joint', 'Joint effusion / swelling', 7),
('Pupils Equal Reactive', 'PERLA', 8),
('Conjunctival Congestion', 'Red eye', 8),
('Skin Rash Present', 'Visible cutaneous rash', 9),
('Wound / Ulcer Present', 'Open wound or ulcer', 9);

SET FOREIGN_KEY_CHECKS = 1;

ALTER TABLE `pathology_parameter` AUTO_INCREMENT = 43;
ALTER TABLE `radiology_parameter` AUTO_INCREMENT = 13;
ALTER TABLE `pathology` AUTO_INCREMENT = 9;
ALTER TABLE `radio` AUTO_INCREMENT = 7;
ALTER TABLE `unit` AUTO_INCREMENT = 41;
ALTER TABLE `symptoms_classification` AUTO_INCREMENT = 13;
ALTER TABLE `finding_category` AUTO_INCREMENT = 10;
