-- Add TOTP as 2FA type
ALTER TABLE `sys_user` CHANGE `otp_type` `otp_type` SET('none','email','totp') NOT NULL DEFAULT 'none';
