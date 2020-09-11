-- Purge APS
DROP TABLE 'aps_instances';
DROP TABLE 'aps_instances_settings';
DROP TABLE 'aps_packages';
DROP TABLE 'aps_settings';
ALTER TABLE 'client' DROP COLUMN limit_aps;
ALTER TABLE 'client_template' DROP COLUMN limit_aps;
