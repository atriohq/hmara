-- Add disablereplicator column to mail_user if not existing, and ensure correct type
ALTER TABLE `mail_user` ADD COLUMN IF NOT EXISTS `disablereplicator` enum('n','y') NOT NULL DEFAULT 'n';
ALTER TABLE `mail_user` MODIFY COLUMN `disablereplicator` enum('n','y') NOT NULL DEFAULT 'n';
