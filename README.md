# Hmara

Hmara is a hosting control panel based on ISPConfig, focused on modernization, improved UX, and extensibility.

> This project is currently based on ISPConfig (BSD-3-Clause licensed).

## Features
- Manage multiple servers from one control panel
- Single server, multiserver and mirrored clusters.
- Webserver management
- Mailserver management
- DNS server management
- Virtualization (OpenVZ)
- Administrator, reseller, client and mailuser login
- Open Source software ([BSD license](LICENSE))

## Supported daemons
- HTTP: Apache2 and NGINX
- HTTP stats: Webalizer, GoAccess and AWStats
- Let's Encrypt: Acme.sh and certbot
- SMTP: Postfix
- POP3/IMAP: Dovecot
- Spamfilter: Rspamd and Amavis
- FTP: PureFTPD
- DNS: BIND9 and PowerDNS[^1]
- Database: MariaDB and MySQL

[^1]: not actively tested

## Supported operating systems
- Debian 11 - 13, and testing
- Ubuntu 22.04 - 24.04
- CentOS 8
- AlmaLinux 8 - 10
- Rocky Linux 8 - 10

## Supported PHP versions
Multiple PHP versions are supported on the same server for hosted sites. But for the panel itself it's advised to stay with the version that comes with the OS.

In general the Hmara panel supports PHP 7.4 - 8.4

## Auto-install script
Documentation will be updated for Hmara in future releases.

## Migration tool
Hmara inherits migration capabilities from ISPConfig.
Support for additional systems will be improved in future versions.

## Documentation
Documentation for Hmara will be published as the project evolves.

## Contributing
Hmara is an evolving project built on ISPConfig.

Initial focus is on:
- UI modernization
- modular architecture
- improved developer experience

Contributions are welcome.

## License

Hmara is currently based on ISPConfig and distributed under the BSD-3-Clause license.

Original ISPConfig code is © ISPConfig authors.  
Modifications are © Atrio.