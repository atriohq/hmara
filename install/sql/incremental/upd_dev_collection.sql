ALTER TABLE `web_domain` ADD `force_http11` ENUM('n','y') NOT NULL DEFAULT 'n' AFTER `disable_symlinknotowner`;
