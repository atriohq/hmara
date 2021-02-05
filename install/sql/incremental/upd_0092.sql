-- add field for enhanced SSL handling
ALTER TABLE `web_domain` ADD  `ssl_valid_until` timestamp NULL DEFAULT NULL AFTER `ssl`;
-- end of fixes