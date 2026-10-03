-- Required by admin shell theme preferences. Must exist on template + every tenant DB.
CREATE TABLE IF NOT EXISTS `user_theme_preferences` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `user_type` enum('staff','patient') NOT NULL DEFAULT 'staff',
  `theme_preset` varchar(32) DEFAULT NULL,
  `text_size` varchar(16) DEFAULT NULL,
  `density` varchar(16) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_theme_preferences_user_unique` (`user_id`,`user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
