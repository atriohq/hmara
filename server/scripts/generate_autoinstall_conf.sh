#!/bin/bash
#
# ISPConfig Autoinstall Configuration Generator
# This script generates an autoinstall.conf.php file for unattended updates
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Default output path
OUTPUT_FILE="/usr/local/ispconfig/server/scripts/autoinstall.conf.php"
SAMPLE_FILE="/usr/local/ispconfig/server/lib/config.inc.php"

echo ""
echo "=============================================="
echo " ISPConfig Autoinstall Configuration Generator"
echo "=============================================="
echo ""

# Check if running as root
if [ "$(id -u)" != "0" ]; then
    echo -e "${RED}Error: This script must be run as root${NC}"
    exit 1
fi

# Check if ISPConfig is installed
if [ ! -f "$SAMPLE_FILE" ]; then
    echo -e "${RED}Error: ISPConfig does not appear to be installed${NC}"
    echo "Could not find: $SAMPLE_FILE"
    exit 1
fi

# Ask for output file location
echo -n "Output file path [$OUTPUT_FILE]: "
read -r user_output
if [ -n "$user_output" ]; then
    OUTPUT_FILE="$user_output"
fi

# Check if file already exists
if [ -f "$OUTPUT_FILE" ]; then
    echo -e "${YELLOW}Warning: File already exists: $OUTPUT_FILE${NC}"
    echo -n "Overwrite? (y/n) [n]: "
    read -r overwrite
    if [ "$overwrite" != "y" ] && [ "$overwrite" != "Y" ]; then
        echo "Aborted."
        exit 0
    fi
fi

# Read current configuration
echo ""
echo "Reading current ISPConfig configuration..."

# Source the config to get current values
DB_HOST=$(grep -oP "(?<=\\\$conf\['db_host'\] = ')[^']*" "$SAMPLE_FILE" 2>/dev/null || echo "localhost")
DB_PORT=$(grep -oP "(?<=\\\$conf\['db_port'\] = ')[^']*" "$SAMPLE_FILE" 2>/dev/null || echo "3306")

# Check for master/slave setup
DBMASTER_HOST=$(grep -oP "(?<=\\\$conf\['dbmaster_host'\] = ')[^']*" "$SAMPLE_FILE" 2>/dev/null || echo "")
DBMASTER_PORT=$(grep -oP "(?<=\\\$conf\['dbmaster_port'\] = ')[^']*" "$SAMPLE_FILE" 2>/dev/null || echo "3306")

IS_MULTISERVER="n"
if [ -n "$DBMASTER_HOST" ] && [ "$DBMASTER_HOST" != "$DB_HOST" ]; then
    IS_MULTISERVER="y"
    echo -e "${GREEN}Detected multiserver setup${NC}"
    echo "  Master host: $DBMASTER_HOST"
fi

echo ""
echo "=============================================="
echo " Configuration Options"
echo "=============================================="
echo ""

# Backup option
echo -n "Create backup before update? (yes/no) [yes]: "
read -r do_backup
do_backup=${do_backup:-yes}

# MySQL root password
echo ""
echo -e "${YELLOW}Note: MySQL root password is needed for database updates${NC}"
echo -n "MySQL root password: "
read -rs mysql_root_password
echo ""

# Master server settings (if multiserver)
if [ "$IS_MULTISERVER" = "y" ]; then
    echo ""
    echo "--- Master Server Settings ---"

    echo -n "Master MySQL hostname [$DBMASTER_HOST]: "
    read -r master_hostname
    master_hostname=${master_hostname:-$DBMASTER_HOST}

    echo -n "Master MySQL port [$DBMASTER_PORT]: "
    read -r master_port
    master_port=${master_port:-$DBMASTER_PORT}

    echo -n "Master MySQL root username [root]: "
    read -r master_root_user
    master_root_user=${master_root_user:-root}

    echo -n "Master MySQL root password: "
    read -rs master_root_password
    echo ""

    echo -n "Master MySQL database [dbispconfig]: "
    read -r master_database
    master_database=${master_database:-dbispconfig}
fi

# Reconfiguration options
echo ""
echo "--- Reconfiguration Options ---"

echo -n "Reconfigure permissions in master database? (yes/no) [no]: "
read -r reconfigure_permissions
reconfigure_permissions=${reconfigure_permissions:-no}

echo -n "Reconfigure services? (yes/no/selected) [yes]: "
read -r reconfigure_services
reconfigure_services=${reconfigure_services:-yes}

echo -n "ISPConfig port [8080]: "
read -r ispconfig_port
ispconfig_port=${ispconfig_port:-8080}

echo -n "Create new ISPConfig SSL certificate? (yes/no) [no]: "
read -r create_ssl_cert
create_ssl_cert=${create_ssl_cert:-no}

echo -n "Reconfigure crontab? (yes/no) [yes]: "
read -r reconfigure_crontab
reconfigure_crontab=${reconfigure_crontab:-yes}

# Generate the configuration file
echo ""
echo "Generating configuration file..."

cat > "$OUTPUT_FILE" << EOF
<?php
/**
 * ISPConfig Autoinstall/Autoupdate Configuration
 * Generated on: $(date)
 *
 * Usage:
 *   ispconfig_update.sh --autoinstall=$OUTPUT_FILE [--update-method=git-develop]
 *   or
 *   ispc update --autoinstall=$OUTPUT_FILE [--update-method=git-develop]
 */

/* Backup settings */
\$autoupdate['do_backup'] = '$do_backup';

/* MySQL credentials for local server */
\$autoupdate['mysql_root_password'] = '$mysql_root_password';

EOF

# Add master server settings if multiserver
if [ "$IS_MULTISERVER" = "y" ]; then
cat >> "$OUTPUT_FILE" << EOF
/* Master server settings (multiserver setup) */
\$autoupdate['mysql_master_hostname'] = '$master_hostname';
\$autoupdate['mysql_master_port'] = '$master_port';
\$autoupdate['mysql_master_root_user'] = '$master_root_user';
\$autoupdate['mysql_master_root_password'] = '$master_root_password';
\$autoupdate['mysql_master_database'] = '$master_database';

EOF
fi

cat >> "$OUTPUT_FILE" << EOF
/* Reconfiguration options */
\$autoupdate['reconfigure_permissions_in_master_database'] = '$reconfigure_permissions';
\$autoupdate['reconfigure_services'] = '$reconfigure_services';
\$autoupdate['ispconfig_port'] = '$ispconfig_port';
\$autoupdate['create_new_ispconfig_ssl_cert'] = '$create_ssl_cert';
\$autoupdate['reconfigure_crontab'] = '$reconfigure_crontab';

/* SSL settings */
\$autoupdate['create_ssl_server_certs'] = 'y';
\$autoupdate['ignore_hostname_dns'] = 'n';
\$autoupdate['ispconfig_postfix_ssl_symlink'] = 'y';
\$autoupdate['ispconfig_pureftpd_ssl_symlink'] = 'y';

/* Service detection - automatically accept detected changes */
\$autoupdate['svc_detect_change_mail_server'] = 'yes';
\$autoupdate['svc_detect_change_web_server'] = 'yes';
\$autoupdate['svc_detect_change_dns_server'] = 'yes';
\$autoupdate['svc_detect_change_xmpp_server'] = 'yes';
\$autoupdate['svc_detect_change_firewall_server'] = 'yes';
\$autoupdate['svc_detect_change_vserver_server'] = 'yes';
\$autoupdate['svc_detect_change_db_server'] = 'yes';

?>
EOF

# Set secure permissions
chmod 600 "$OUTPUT_FILE"
chown root:root "$OUTPUT_FILE"

echo ""
echo -e "${GREEN}=============================================="
echo " Configuration file created successfully!"
echo -e "==============================================${NC}"
echo ""
echo "File: $OUTPUT_FILE"
echo "Permissions: 600 (root only)"
echo ""
echo "To run an unattended update:"
echo -e "  ${YELLOW}ispconfig_update.sh --autoinstall=$OUTPUT_FILE${NC}"
echo ""
echo "Or using the CLI:"
echo -e "  ${YELLOW}ispc update --autoinstall=$OUTPUT_FILE${NC}"
echo ""
echo -e "${RED}IMPORTANT: This file contains sensitive passwords.${NC}"
echo "Keep it secure and do not share or commit to version control."
echo ""
