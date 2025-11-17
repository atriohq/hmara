-- #6846 migrate from utf8(mb3) to utf8mb4
-- change database default charset
ALTER DATABASE `dbispconfig` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- convert all tables to utf8mb4
DROP PROCEDURE IF EXISTS convert_all_tables;
DELIMITER $$
CREATE PROCEDURE convert_all_tables()
BEGIN
  DECLARE done INT DEFAULT FALSE;
  DECLARE tbl_name VARCHAR(255);
  DECLARE cur CURSOR FOR
    SELECT TABLE_NAME
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_TYPE = 'BASE TABLE';
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
  OPEN cur;
  read_loop: LOOP
    FETCH cur INTO tbl_name;
    IF done THEN
      LEAVE read_loop;
    END IF;
    SET @sql_stmt = CONCAT(
      'ALTER TABLE `', tbl_name, '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
    );
    PREPARE stmt FROM @sql_stmt;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END LOOP;
  CLOSE cur;
END$$
DELIMITER ;
CALL convert_all_tables();
DROP PROCEDURE convert_all_tables;

-- convert leftover utf8/utf8mb3 columns to utf8mb4
DROP PROCEDURE IF EXISTS convert_utf8_columns;
DELIMITER $$
CREATE PROCEDURE convert_utf8_columns()
BEGIN
  DECLARE done INT DEFAULT FALSE;
  DECLARE tbl VARCHAR(255);
  DECLARE col VARCHAR(255);
  DECLARE col_type TEXT;
  DECLARE is_nullable VARCHAR(3);
  DECLARE col_default TEXT;
  DECLARE extra TEXT;
  DECLARE cur CURSOR FOR
    SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND CHARACTER_SET_NAME IN ('utf8', 'utf8mb3')
      AND EXTRA NOT LIKE '%VIRTUAL%';
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
  OPEN cur;
  col_loop: LOOP
    FETCH cur INTO tbl, col, col_type, is_nullable, col_default, extra;
    IF done THEN
      LEAVE col_loop;
    END IF;
    SET @sql_stmt = CONCAT(
      'ALTER TABLE `', tbl, '` CHANGE `', col, '` `', col, '` ',
      col_type,
      ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
      IF(is_nullable = 'NO', ' NOT NULL', ''),
      IF(col_default IS NOT NULL, CONCAT(' DEFAULT ', QUOTE(col_default)), ''),
      ';'
    );
    PREPARE stmt FROM @sql_stmt;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END LOOP;
  CLOSE cur;
END$$
DELIMITER ;
CALL convert_utf8_columns();
DROP PROCEDURE convert_utf8_columns;

-- Login links
CREATE TABLE IF NOT EXISTS `autologin_tokens` (
                `token` varchar(128) NOT NULL PRIMARY KEY,
                `sys_userid` int NOT NULL,
                `expires` datetime NULL,
                `created_by` varchar(20) NULL,
                `ip` varchar(45) NULL,
                `used` tinyint(1) NOT NULL DEFAULT 0,
                `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
