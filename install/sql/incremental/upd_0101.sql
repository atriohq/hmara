ALTER TABLE `web_database_user` ADD `database_password_sha2` varchar(70) DEFAULT NULL AFTER `database_password`;
ALTER TABLE `web_database_user` ADD `database_password_postgres` varchar(255) DEFAULT NULL AFTER `database_password_mongo`;
ALTER TABLE `client` ADD `limit_database_postgresql` INT NOT NULL DEFAULT '-1' AFTER `limit_database`;
ALTER TABLE `client_template` ADD `limit_database_postgresql` INT NOT NULL DEFAULT '-1' AFTER `limit_database`;
ALTER TABLE `server_php` ADD `php_cli_binary` varchar(255) DEFAULT NULL AFTER `php_fpm_socket_dir`;
ALTER TABLE `server_php` ADD `php_jk_section` varchar(255) DEFAULT NULL AFTER `php_cli_binary`;
ALTER TABLE `mail_domain` ADD `local_delivery` enum('n','y') NOT NULL DEFAULT 'y' AFTER `active`;
ALTER TABLE `sys_remoteaction` CHANGE `action_type` `action_type` VARCHAR(64) NOT NULL;
CREATE TABLE IF NOT EXISTS `sys_message` (
  `message_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `sys_userid` int(11) unsigned NOT NULL DEFAULT 0,
  `sys_groupid` int(11) unsigned NOT NULL DEFAULT 0,
  `sys_perm_user` VARCHAR(5) DEFAULT 'r',
  `sys_perm_group` VARCHAR(5) DEFAULT 'r',
  `sys_perm_other` VARCHAR(5) DEFAULT '',
  `message_state` enum('info','warning','error') NOT NULL DEFAULT 'info',
  `message_date` datetime NULL DEFAULT NULL,
  `message_ack` enum('y','n') NOT NULL DEFAULT 'n',
  `relation` varchar(255) NULL DEFAULT NULL,
  `message` TEXT DEFAULT NULL,
  PRIMARY KEY (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
