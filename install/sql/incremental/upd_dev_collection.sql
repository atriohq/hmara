-- #6846 migrate from utf8(mb3) to utf8mb4
-- database
ALTER DATABASE CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- individual tables
SELECT CONCAT(
  'ALTER TABLE `', TABLE_NAME, '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
)
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_TYPE = 'BASE TABLE';
-- individual columns
SELECT CONCAT(
  'ALTER TABLE `', TABLE_NAME, '` CHANGE `', COLUMN_NAME, '` `', COLUMN_NAME, '` ',
  COLUMN_TYPE, 
  ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
  IF(IS_NULLABLE = 'NO', ' NOT NULL', ''),
  IF(COLUMN_DEFAULT IS NOT NULL, CONCAT(' DEFAULT \'', COLUMN_DEFAULT, '\''), ''),
  ';'
)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND CHARACTER_SET_NAME = 'utf8';