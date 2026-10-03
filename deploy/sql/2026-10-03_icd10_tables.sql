-- ICD-10 master + OPD/IPD junction tables (required by OPD patient screen).

CREATE TABLE IF NOT EXISTS `icd10_groups` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `group_name` varchar(255) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `icd10_codes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `icd_code` varchar(20) NOT NULL,
  `icd_description` varchar(500) NOT NULL,
  `group_id` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `icd10_codes_group_id` (`group_id`),
  KEY `icd10_codes_icd_code` (`icd_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `opd_icd10_codes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `opd_id` int unsigned NOT NULL,
  `icd_code_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `opd_icd10_codes_opd_id` (`opd_id`),
  KEY `opd_icd10_codes_icd_code_id` (`icd_code_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ipd_icd10_codes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ipd_id` int unsigned NOT NULL,
  `icd_code_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ipd_icd10_codes_ipd_id` (`ipd_id`),
  KEY `ipd_icd10_codes_icd_code_id` (`icd_code_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
