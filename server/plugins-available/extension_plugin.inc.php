<?php

/*
Copyright (c) 2025, Till Brehm, ISPConfig UG
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright notice,
      this list of conditions and the following disclaimer in the documentation
      and/or other materials provided with the distribution.
    * Neither the name of ISPConfig nor the names of its contributors
      may be used to endorse or promote products derived from this software without
      specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

class extension_plugin {

	var $plugin_name = 'extension_plugin';
	var $class_name  = 'extension_plugin';

	//* This function is called during ispconfig installation to determine
	//  if a symlink shall be created for this plugin.
	public function onInstall() {
		global $conf;

		return true;

	}


	/*
	 	This function is called when the plugin is loaded
	*/

	public function onLoad() {
		global $app;

		//* Register for actions
		$app->plugins->registerAction('extension_install', $this->plugin_name, 'extension_action');
        $app->plugins->registerAction('extension_update', $this->plugin_name, 'extension_action');
        $app->plugins->registerAction('extension_uninstall', $this->plugin_name, 'extension_action');
        $app->plugins->registerAction('extension_enable', $this->plugin_name, 'extension_action');
        $app->plugins->registerAction('extension_disable', $this->plugin_name, 'extension_action');
        $app->plugins->registerAction('extension_list', $this->plugin_name, 'extension_action');
        $app->plugins->registerAction('extension_available', $this->plugin_name, 'extension_action');
		
	}

	//* Do a backup action
	public function extension_action($action_name, $data) {
		global $app, $conf;

		$extension_name = $data;

        $app->uses('extension_installer');
        $app->load('extension_installer_base');

        switch($action_name) {
            case 'extension_install':
                $app->extension_installer->install_extension($extension_name);
                break;
            case 'extension_update':
                $app->extension_installer->update_extension($extension_name);
                break;
            case 'extension_uninstall':
                $app->extension_installer->uninstall_extension($extension_name);
                break;
            case 'extension_enable':
                $app->extension_installer->enable_extension($extension_name);
                break;
            case 'extension_disable':
                $app->extension_installer->disable_extension($extension_name);
                break;
            case 'extension_list':
                $app->extension_installer->list_extensions();
                break;
            case 'extension_available':
                $app->extension_installer->list_available_extensions();
                break;
            default:
                return 'Error: Invalid action';
        }

        // handle errors
        $error_txt = '';
        if(!empty($app->extension_installer->getErrors())) {
            foreach($app->extension_installer->getErrors() as $error) {
                $error_txt .= $error . "\n";
            }
            $app->log('Extension installer error: ' . $error_txt, LOGLEVEL_WARNING);
            return 'error';
        }
		

		return 'ok';
	}
			
				
} // end class
