CREATE TABLE `domain_verification` (
  `domain_id` int(11) NOT NULL AUTO_INCREMENT,
  `sys_userid` int(11) NOT NULL,
  `sys_groupid` int(11) NOT NULL,
  `sys_perm_user` varchar(5) DEFAULT NULL,
  `sys_perm_group` varchar(5) DEFAULT NULL,
  `sys_perm_other` varchar(5) DEFAULT NULL,
  `domain` varchar(255) NOT NULL,
  `dns_auth_record` varchar(255) NOT NULL,
  `record_created` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`domain_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

ALTER TABLE `domain` ADD `domain_type_flag` VARCHAR(1) NULL DEFAULT 'n' AFTER `domain`;
