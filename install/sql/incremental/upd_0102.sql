-- Add last_password_change column to sys_user table
ALTER TABLE `sys_user` ADD COLUMN `last_password_change` DATE NULL DEFAULT (CURDATE());

-- Set default php_cli_binary and php_jk_section values for known PHP versions (#6938).
-- Only set the values if they are currently NULL - this will not work on all systems but it will not break anything more than it is with NULL.
UPDATE `server_php` sp
JOIN (
  SELECT server_id, `data`
  FROM `monitor_data`
  WHERE `type` = 'os_info'
) md
  ON md.server_id = sp.server_id
SET
  sp.`php_cli_binary` = CASE
    WHEN (sp.`php_cli_binary` IS NULL OR sp.`php_cli_binary` = '') THEN
      CASE
        -- RHEL derivatives -> /usr/bin/phpXY
        WHEN (
          md.`data` LIKE '%"name";s:%:"CentOS%"%' OR
          md.`data` LIKE '%"name";s:%:"Fedora%"%' OR
          md.`data` LIKE '%"name";s:%:"Rocky Linux"%' OR
          md.`data` LIKE '%"name";s:%:"AlmaLinux"%' OR
          md.`data` LIKE '%"name";s:%:"Red Hat%"%' OR
          md.`data` LIKE '%"name";s:%:"RHEL%"%' OR
          md.`data` LIKE '%"name";s:%:"Oracle Linux"%' OR
          md.`data` LIKE '%"name";s:%:"CloudLinux"%'
        ) THEN CASE sp.`name`
          WHEN 'PHP 5.6' THEN '/usr/bin/php56'
          WHEN 'PHP 7.0' THEN '/usr/bin/php70'
          WHEN 'PHP 7.1' THEN '/usr/bin/php71'
          WHEN 'PHP 7.2' THEN '/usr/bin/php72'
          WHEN 'PHP 7.3' THEN '/usr/bin/php73'
          WHEN 'PHP 7.4' THEN '/usr/bin/php74'
          WHEN 'PHP 8.0' THEN '/usr/bin/php80'
          WHEN 'PHP 8.1' THEN '/usr/bin/php81'
          WHEN 'PHP 8.2' THEN '/usr/bin/php82'
          WHEN 'PHP 8.3' THEN '/usr/bin/php83'
          WHEN 'PHP 8.4' THEN '/usr/bin/php84'
          ELSE sp.`php_cli_binary`
        END
        -- Default (non-RHEL) -> Debian/Ubuntu style /usr/bin/phpX.Y
        ELSE CASE sp.`name`
          WHEN 'PHP 5.6' THEN '/usr/bin/php5.6'
          WHEN 'PHP 7.0' THEN '/usr/bin/php7.0'
          WHEN 'PHP 7.1' THEN '/usr/bin/php7.1'
          WHEN 'PHP 7.2' THEN '/usr/bin/php7.2'
          WHEN 'PHP 7.3' THEN '/usr/bin/php7.3'
          WHEN 'PHP 7.4' THEN '/usr/bin/php7.4'
          WHEN 'PHP 8.0' THEN '/usr/bin/php8.0'
          WHEN 'PHP 8.1' THEN '/usr/bin/php8.1'
          WHEN 'PHP 8.2' THEN '/usr/bin/php8.2'
          WHEN 'PHP 8.3' THEN '/usr/bin/php8.3'
          WHEN 'PHP 8.4' THEN '/usr/bin/php8.4'
          ELSE sp.`php_cli_binary`
        END
      END
    ELSE sp.`php_cli_binary`
  END,
  sp.`php_jk_section` = CASE
    WHEN (sp.`php_jk_section` IS NULL OR sp.`php_jk_section` = '')
      THEN CASE sp.`name`
        WHEN 'PHP 5.6' THEN 'php5_6'
        WHEN 'PHP 7.0' THEN 'php7_0'
        WHEN 'PHP 7.1' THEN 'php7_1'
        WHEN 'PHP 7.2' THEN 'php7_2'
        WHEN 'PHP 7.3' THEN 'php7_3'
        WHEN 'PHP 7.4' THEN 'php7_4'
        WHEN 'PHP 8.0' THEN 'php8_0'
        WHEN 'PHP 8.1' THEN 'php8_1'
        WHEN 'PHP 8.2' THEN 'php8_2'
        WHEN 'PHP 8.3' THEN 'php8_3'
        WHEN 'PHP 8.4' THEN 'php8_4'
        ELSE sp.`php_jk_section`
      END
    ELSE sp.`php_jk_section`
  END
WHERE sp.`name` IN ('PHP 5.6','PHP 7.0','PHP 7.1','PHP 7.2','PHP 7.3','PHP 7.4',
                    'PHP 8.0','PHP 8.1','PHP 8.2','PHP 8.3','PHP 8.4')
  AND (
    sp.`php_cli_binary` IS NULL OR sp.`php_cli_binary` = '' OR
    sp.`php_jk_section` IS NULL OR sp.`php_jk_section` = ''
  );