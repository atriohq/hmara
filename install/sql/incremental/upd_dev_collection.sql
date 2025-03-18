-- Add last_password_change column to sys_user table
ALTER TABLE `sys_user` ADD COLUMN `last_password_change` DATE NULL DEFAULT CURDATE();

-- Add TOTP as 2FA type
ALTER TABLE `sys_user` CHANGE `otp_type` `otp_type` SET('none','email','totp') NOT NULL DEFAULT 'none';
