## ISPConfig 3.3.1 Beta 1 Released

This version brings Debian 13 support with Dovecot 2.4 compatibility, RHEL 10 based distribution support (AlmaLinux 10, RockyLinux 10), PHP 8.5 compatibility, pgAdmin for PostgreSQL databases, improved DNS validation, and many bugfixes. It also fixes 3 security issues.

## What's new in ISPConfig 3.3.1 Beta 1?

### Debian 13 Support

ISPConfig 3.3.1 adds full support for Debian 13 (Trixie), including compatibility with Dovecot 2.4 which introduces an incompatible configuration language. The Dovecot configuration templates have been updated to work with the new 2.4 syntax.

### RHEL 10 Based Distribution Support

Support has been added for RHEL 10 based distributions including AlmaLinux 10 and RockyLinux 10.

### PHP 8.5 Compatibility

ISPConfig is now compatible with PHP 8.5. This includes fixes for deprecated functions like `mysqli_ping()` and non-canonical casts.

### pgAdmin for PostgreSQL Databases

A pgAdmin link has been added for PostgreSQL databases, similar to how phpMyAdmin works for MySQL/MariaDB databases. The phpMyAdmin link is now correctly hidden for PostgreSQL databases.

### DNS Record Validation Improvements

Improved validation for DNS records including better CNAME conflict detection, SRV record field validation, and DMARC record handling.

### Rspamd Greylisting Fixes

Fixed issues where rspamd greylisting settings from spamfilter policies were not being applied correctly to domain-level spam filter entries and user configurations.

### Let's Encrypt Improvements

- Removed OCSP stapling sections from vhosts as Let's Encrypt no longer supports OCSP
- Fixed default CA setting when acme.sh is installed from the Let's Encrypt class

### Extended CLI Resync Functionality

The Tools > Resync function has been extended in the ispc CLI command, allowing more flexible server synchronization from the command line.

### Improved Autoinstall Updates

Improved updating with autoinstall.conf.php including better documentation and the ability to pass --autoinstall option from ispconfig_update.sh and ispc update to update.php.

### Improved website logfile permissions

Website logfiles are now created with stricter permissions (640) and ownership (root:clientX).

### Security Fixes

This release addresses three security vulnerabilities that could allow privilege escalation under certain conditions. The issues affect theme handling, backup restoration, and backup download functionality. We strongly recommend all users to update to this version as soon as possible.

We would like to thank **SSD Secure Disclosure** for responsibly discovering and reporting these vulnerabilities.

### Backup System Changes

- Removed zip and rar archive formats from rootgz backup mode as they do not preserve file ownership. These formats will automatically fall back to tar_gzip when selected with rootgz mode. The zip and rar mode will stay available for userzip mode.

### Bugfixes & Minor Features

- Fixed DKIM config bug in amavis that could cause DKIM entries to be lost
- Fixed PowerDNS records not being created when domain is inactive
- Fixed PHP 8.3 compatibility issues
- Fixed false positive "Possible attack detected" for valid Nginx directive snippets
- Fixed trailing space in nginx vhost server_name property
- Fixed data cleanup when server is deleted (monitor_data and server_ip)
- Fixed sysdatalog processing with incorrect unixtime values
- Improved useragent for cronjobs (wget)
- Silenced z_php_fpm_incron_reload_plugin warnings when no PHP version is set
- Fixed CAA DNS entry name handling with TLD
- Improved email filter regex syntax validation
- Fixed stats/.htaccess file permissions
- Added more plugin events for interface plugins

Please see changelog for a full list of features and bugfixes:

https://git.ispconfig.org/ispconfig/ispconfig3/-/milestones/95

## Known issues

Please take a look at the bug tracker:

https://git.ispconfig.org/ispconfig/ispconfig3/-/issues?scope=all&utf8=%E2%9C%93&state=opened&label_name[]=Bug

You can report bugs at https://git.ispconfig.org/ispconfig/ispconfig3/issues

## Supported Linux distributions

- Debian 11 – 13 (recommended) and Debian testing
- Ubuntu 22.04 LTS – 24.04 LTS (recommended)
- AlmaLinux 8 – 10
- RockyLinux 8 – 10
- CentOS 8

## Download ISPConfig 3.3.1 Beta 1

https://www.ispconfig.org/downloads/ISPConfig-3.3.1b1.tar.gz

The installation instructions for ISPConfig can be found here:

https://www.ispconfig.org/ispconfig-3/documentation/

## How can I update to the ISPConfig 3.3.1 Beta 1?

Run the following commands as root user on your ISPConfig server:

```
cd /tmp
wget https://www.ispconfig.org/downloads/ISPConfig-3.3.1b1.tar.gz
tar xvfz ISPConfig-3.3.1b1.tar.gz
cd ispconfig3_install/install
php -q update.php
```
