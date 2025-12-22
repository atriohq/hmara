# ISPConfig Automatic/Unattended Updates

This guide explains how to set up and use automatic (unattended) updates for ISPConfig servers.

## Overview

ISPConfig supports unattended updates using a configuration file that pre-answers all update prompts. This is useful for:

- Automated server maintenance
- Scripted updates across multiple servers
- CI/CD pipelines
- Reducing human error during updates

## Quick Start

1. **Create the autoinstall configuration file:**
   ```bash
   cp /usr/local/ispconfig/docs/autoinstall_samples/autoinstall.conf_sample.php /usr/local/ispconfig/server/lib/autoinstall.conf.php
   ```

2. **Edit the configuration file** with your settings (see Configuration section below)

3. **Run the update:**
   ```bash
   ispconfig_update.sh --autoinstall=/usr/local/ispconfig/server/lib/autoinstall.conf.php
   ```
   
   Or using the CLI:
   ```bash
   ispc update --autoinstall=/usr/local/ispconfig/server/lib/autoinstall.conf.php
   ```

## Configuration File

The configuration file can be either:
- **PHP format** (`autoinstall.conf.php`) - Recommended, allows dynamic values
- **INI format** (`autoinstall.ini`) - Simple key-value pairs

### PHP Format Example

```php
<?php
/* Update settings */
$autoupdate['do_backup'] = 'yes';                    // yes (default), no
$autoupdate['mysql_root_password'] = 'your_password';

/* Master/Slave setup (only needed for slave servers) */
$autoupdate['mysql_master_hostname'] = 'master.example.com';
$autoupdate['mysql_master_port'] = '3306';           // default: 3306
$autoupdate['mysql_master_root_user'] = 'root';
$autoupdate['mysql_master_root_password'] = 'master_password';
$autoupdate['mysql_master_database'] = 'dbispconfig';

/* Reconfiguration options */
$autoupdate['reconfigure_permissions_in_master_database'] = 'no';  // no (default), yes
$autoupdate['reconfigure_services'] = 'yes';         // yes (default), no
$autoupdate['ispconfig_port'] = '8080';              // default: 8080
$autoupdate['create_new_ispconfig_ssl_cert'] = 'no'; // no (default), yes
$autoupdate['reconfigure_crontab'] = 'yes';          // yes (default), no

/* SSL settings */
$autoupdate['create_ssl_server_certs'] = 'y';
$autoupdate['ignore_hostname_dns'] = 'n';
$autoupdate['ispconfig_postfix_ssl_symlink'] = 'y';
$autoupdate['ispconfig_pureftpd_ssl_symlink'] = 'y';

/* Service detection - accept changes automatically */
$autoupdate['svc_detect_change_mail_server'] = 'yes';
$autoupdate['svc_detect_change_web_server'] = 'yes';
$autoupdate['svc_detect_change_dns_server'] = 'yes';
$autoupdate['svc_detect_change_xmpp_server'] = 'yes';
$autoupdate['svc_detect_change_firewall_server'] = 'yes';
$autoupdate['svc_detect_change_vserver_server'] = 'yes';
$autoupdate['svc_detect_change_db_server'] = 'yes';
?>
```

### Configuration Options Reference

| Option | Values | Default | Description |
|--------|--------|---------|-------------|
| `do_backup` | yes, no | yes | Create backup before update |
| `mysql_root_password` | string | - | MySQL root password for local server |
| `mysql_master_hostname` | string | - | Master DB hostname (multiserver only) |
| `mysql_master_port` | string | 3306 | Master DB port (multiserver only) |
| `mysql_master_root_user` | string | root | Master DB root username |
| `mysql_master_root_password` | string | - | Master DB root password |
| `mysql_master_database` | string | dbispconfig | Master DB database name |
| `reconfigure_permissions_in_master_database` | yes, no | no | Reconfigure DB permissions |
| `reconfigure_services` | yes, no, selected | yes | Reconfigure all services |
| `ispconfig_port` | number | 8080 | ISPConfig web interface port |
| `create_new_ispconfig_ssl_cert` | yes, no | no | Generate new SSL certificate |
| `reconfigure_crontab` | yes, no | yes | Update crontab entries |
| `create_ssl_server_certs` | y, n | y | Create SSL server certificates |
| `ignore_hostname_dns` | y, n | n | Ignore hostname DNS check |
| `ispconfig_postfix_ssl_symlink` | y, n | y | Create Postfix SSL symlink |
| `ispconfig_pureftpd_ssl_symlink` | y, n | y | Create PureFTPd SSL symlink |
| `svc_detect_change_*` | yes, no | yes | Auto-accept service detection changes |

## Helper Script

A helper script is available to generate the configuration file:

```bash
/usr/local/ispconfig/server/scripts/generate_autoinstall_conf.sh
```

This script will:
1. Read your current ISPConfig configuration
2. Prompt for any sensitive values (passwords)
3. Generate a ready-to-use `autoinstall.conf.php` file

## Security Considerations

⚠️ **Important Security Notes:**

1. **Protect the configuration file** - It contains sensitive passwords:
   ```bash
   chmod 600 /usr/local/ispconfig/server/lib/autoinstall.conf.php
   chown root:root /usr/local/ispconfig/lib/scripts/autoinstall.conf.php
   ```

2. **Consider using environment variables** for passwords in the PHP config:
   ```php
   $autoupdate['mysql_root_password'] = getenv('MYSQL_ROOT_PASSWORD');
   ```

3. **Do not commit** the configuration file to version control

4. **Test updates** on a staging server before production

## Multiserver Setup

For multiserver setups, update servers in this order:

1. Enable maintenance mode on the master
2. Update the **master server first**
3. Update all slave servers
4. Disable maintenance mode

Each slave server needs the master database credentials in its configuration file.

## Cron-based Automatic Updates

To schedule automatic updates (use with caution):

```bash
# Edit root's crontab
crontab -e

# Add weekly update on Sunday at 3 AM
0 3 * * 0 /usr/local/bin/ispconfig_update.sh --autoinstall=/usr/local/ispconfig/server/lib/autoinstall.conf.php >> /var/log/ispconfig/update_cron.log 2>&1
```

**Recommendation:** Only use automated cron updates if you have:
- Proper backup systems in place
- Monitoring to detect update failures
- A staging environment to test updates first

## Troubleshooting

### Update fails with "Unable to connect to mysql server"
- Verify `mysql_root_password` is correct
- Check MySQL is running: `systemctl status mysql`

### Master database connection fails
- Verify master server is accessible from slave
- Check firewall rules allow MySQL connections
- Verify `mysql_master_*` credentials are correct

### Configuration file not found
- Use absolute path: `--autoinstall=/full/path/to/autoinstall.conf.php`
- Verify file exists and is readable by root

## See Also

- Sample configuration: `docs/autoinstall_samples/autoinstall.conf_sample.php`
- ISPConfig documentation: https://www.ispconfig.org/documentation/
