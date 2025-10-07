-- Add last_password_change column to sys_user table
ALTER TABLE `sys_user` ADD COLUMN `last_password_change` DATE NULL DEFAULT CURDATE();

-- Set default php_cli_binary and php_jk_section values for known PHP versions (#6938).
-- Only set the values if they are currently NULL - this will not work on all systems but it will not break anything more than it is with NULL.
UPDATE `server_php`
SET
  `php_cli_binary` = CASE
    WHEN `php_cli_binary` IS NULL AND `name` = 'PHP 5.6' THEN '/usr/bin/php5.6'
    WHEN `php_cli_binary` IS NULL AND `name` = 'PHP 7.0' THEN '/usr/bin/php7.0'
    WHEN `php_cli_binary` IS NULL AND `name` = 'PHP 7.1' THEN '/usr/bin/php7.1'
    WHEN `php_cli_binary` IS NULL AND `name` = 'PHP 7.2' THEN '/usr/bin/php7.2'
    WHEN `php_cli_binary` IS NULL AND `name` = 'PHP 7.3' THEN '/usr/bin/php7.3'
    WHEN `php_cli_binary` IS NULL AND `name` = 'PHP 7.4' THEN '/usr/bin/php7.4'
    WHEN `php_cli_binary` IS NULL AND `name` = 'PHP 8.0' THEN '/usr/bin/php8.0'
    WHEN `php_cli_binary` IS NULL AND `name` = 'PHP 8.1' THEN '/usr/bin/php8.1'
    WHEN `php_cli_binary` IS NULL AND `name` = 'PHP 8.2' THEN '/usr/bin/php8.2'
    WHEN `php_cli_binary` IS NULL AND `name` = 'PHP 8.3' THEN '/usr/bin/php8.3'
    WHEN `php_cli_binary` IS NULL AND `name` = 'PHP 8.4' THEN '/usr/bin/php8.4'
    ELSE `php_cli_binary`
  END,
  `php_jk_section` = CASE
    WHEN `php_jk_section` IS NULL AND `name` = 'PHP 5.6' THEN 'php5_6'
    WHEN `php_jk_section` IS NULL AND `name` = 'PHP 7.0' THEN 'php7_0'
    WHEN `php_jk_section` IS NULL AND `name` = 'PHP 7.1' THEN 'php7_1'
    WHEN `php_jk_section` IS NULL AND `name` = 'PHP 7.2' THEN 'php7_2'
    WHEN `php_jk_section` IS NULL AND `name` = 'PHP 7.3' THEN 'php7_3'
    WHEN `php_jk_section` IS NULL AND `name` = 'PHP 7.4' THEN 'php7_4'
    WHEN `php_jk_section` IS NULL AND `name` = 'PHP 8.0' THEN 'php8_0'
    WHEN `php_jk_section` IS NULL AND `name` = 'PHP 8.1' THEN 'php8_1'
    WHEN `php_jk_section` IS NULL AND `name` = 'PHP 8.2' THEN 'php8_2'
    WHEN `php_jk_section` IS NULL AND `name` = 'PHP 8.3' THEN 'php8_3'
    WHEN `php_jk_section` IS NULL AND `name` = 'PHP 8.4' THEN 'php8_4'
    ELSE `php_jk_section`
  END
WHERE `name` IN (
  'PHP 5.6','PHP 7.0','PHP 7.1','PHP 7.2','PHP 7.3','PHP 7.4',
  'PHP 8.0','PHP 8.1','PHP 8.2','PHP 8.3','PHP 8.4'
)
AND (`php_cli_binary` IS NULL OR `php_jk_section` IS NULL);