<?php

if(!defined('INSTALLER_RUN')) die('Patch update file access violation.');

class upd_0100 extends installer_patch_update {

	public function onAfterSQL() {
		global $inst;

        // Remove old server plugins, unless they are currently enabled
        if(file_exists('/usr/local/ispconfig/server/plugins-available/nginx_reverseproxy_plugin.inc.php') && !is_link('/usr/local/ispconfig/server/plugins-enabled/nginx_reverseproxy_plugin.inc.php'))
            unlink('/usr/local/ispconfig/server/plugins-available/nginx_reverseproxy_plugin.inc.php');
        if(file_exists('/usr/local/ispconfig/server/plugins-available/bind_dlz_plugin.inc.php') && !is_link('/usr/local/ispconfig/server/plugins-enabled/bind_dlz_plugin.inc.php'))
            unlink('/usr/local/ispconfig/server/plugins-available/bind_dlz_plugin.inc.php');
	}

}
