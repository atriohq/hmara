ALTER TABLE `server_php` ADD `php_cli_binary` varchar(255) DEFAULT NULL AFTER `php_fpm_socket_dir`;
INSERT IGNORE INTO `dns_ssl_ca` (`id`, `sys_userid`, `sys_groupid`, `sys_perm_user`, `sys_perm_group`, `sys_perm_other`, `active`, `ca_name`, `ca_issue`, `ca_wildcard`, `ca_iodef`, `ca_critical`) VALUES
(NULL, 1, 1, 'riud', 'riud', '', 'Y', 'Amazon Trust Services', 'amazontrust.com', 'Y', '', 0);
