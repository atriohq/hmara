-- Add last_password_change column to sys_user table
ALTER TABLE `sys_user` ADD COLUMN `last_password_change` DATE NULL DEFAULT CURDATE();