ALTER TABLE `web_database_user` ADD `database_password_sha2` varchar(70) DEFAULT NULL AFTER `database_password`;

ALTER TABLE `mail_domain` ADD `local_delivery` enum('n','y') NOT NULL DEFAULT 'y' AFTER `active`;
