ALTER TABLE `web_database_user` ADD `database_password_sha2` varchar(70) DEFAULT NULL AFTER `database_password`;
ALTER TABLE `server_php` ADD `php_cli_binary` varchar(255) DEFAULT NULL AFTER `php_fpm_socket_dir`;
ALTER TABLE `server_php` ADD `php_jk_section` varchar(255) DEFAULT NULL AFTER `php_cli_binary`;
ALTER TABLE `mail_domain` ADD `local_delivery` enum('n','y') NOT NULL DEFAULT 'y' AFTER `active`;
