-- Run this whole file once in HeidiSQL (Query tab, then F9).
-- It uses the same database as your project (mysql).

CREATE TABLE IF NOT EXISTS site_settings (
  `name`  VARCHAR(50) PRIMARY KEY,
  `value` VARCHAR(100) NOT NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO site_settings VALUES ('attendance_open', '0');

CREATE TABLE IF NOT EXISTS attendance_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id VARCHAR(14) NOT NULL,
  att_date DATE NOT NULL,
  att_time TIME NOT NULL,
  UNIQUE KEY one_per_day (student_id, att_date)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Last line: adds the role column. If HeidiSQL says "Duplicate column name 'role'",
-- the column already exists and you can ignore that message.
ALTER TABLE `project data` ADD COLUMN `role` VARCHAR(10) NOT NULL DEFAULT 'student';

-- Then make yourself admin (put your own 14-digit National ID) and run it:
-- UPDATE `project data` SET `role` = 'admin' WHERE `ID` = 'YOUR_14_DIGIT_ID';
