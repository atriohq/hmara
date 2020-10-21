ALTER TABLE `client_template` ADD `limit_access_mail_autoresponder` ENUM('n','y') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'n' AFTER `web_servers`;
ALTER TABLE `client_template` ADD `limit_access_mail_filter` ENUM('n','y') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'n' AFTER `limit_access_mail_autoresponder`;
ALTER TABLE `client_template` ADD `limit_access_mail_custom_rules` ENUM('n','y') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'n' AFTER `limit_access_mail_filter`; 
ALTER TABLE `client_template` ADD `limit_access_mail_backup` ENUM('n','y') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'n' AFTER `limit_access_mail_custom_rules`;
