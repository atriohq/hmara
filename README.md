# ISPConfig - Hosting Control Panel

- Manage multiple servers from one control panel
- Web server management (Apache2 and nginx)
- Mail server management (with virtual mail users)
- DNS server management (BIND and MyDNS)
- Virtualization (OpenVZ)
- Administrator, reseller and client login
- Configuration mirroring and clusters
- Open Source software (BSD license)
- 
# ISPConfig-vcs

Easy version control system integration in ISPConfig 3.

This is a very early version, so only git repositories can be created for the moment.


## What is this for?
This plugin integrates Git repositories for the ISPConfig Sites.

You have the option to 'git clone' a repository on the %document_root%/web folder of the websites.
The first time you add the repo it tries to 'git clone'-it creating a temporal folder in /tmp/web_git/[web_git_id]/.
After the clone, it changes permissions to every file with the user and group of the website. Then it moves all the content to the %document_root%/web folder.

Every time you change the values of a repository (via ISPConfig form) it checks if there is a git repository created in the folder.
If not, it tries to clone it. If yes, performs a 'git pull' and changes and chowns all the web folder.

'git pull' log is stored and can be viewed inside a Git repository register.


## Requirements
* ISPConfig 3
* Admin user
* git
* Git repository url (HTTP/HTTPS)


## Instalation

I recommend you test this plugin in a non-production enviroment. 

```
#Get sources
git clone http://git.funcli.net:3000/funcli/ISPConfig-vcs.git /tmp/vcs
cd /tmp/vcs

#Import database table
mysql -uroot -p dbispconfig < database.sql

#Add files to ISPConfig
cp -r interface /usr/local/ispconfig/
cp -r server /usr/local/ispconfig/

#Enable module and plugin
ln -s /usr/local/ispconfig/server/mods-available/vcs_module.inc.php /usr/local/ispconfig/server/mods-enabled/vcs_module.inc.php
ln -s /usr/local/ispconfig/server/plugins-available/vcs_plugin.inc.php /usr/local/ispconfig/server/plugins-enabled/vcs_plugin.inc.php
```