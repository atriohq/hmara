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

class extension_cli extends cli {

    private $app;

    public function __construct() {
        global $app;
        $this->app = $app;
        
        $cmd_opt = [];
        $cmd_opt['extension'] = 'showHelp';
        $cmd_opt['extension:install'] = 'install';
        $cmd_opt['extension:update'] = 'update';
        $cmd_opt['extension:uninstall'] = 'uninstall';
        $cmd_opt['extension:enable'] = 'enable';
        $cmd_opt['extension:disable'] = 'disable';
        $cmd_opt['extension:list'] = 'list';
        $cmd_opt['extension:available'] = 'list_available';
        $this->addCmdOpt($cmd_opt);
    }

    public function showHelp($arg) {
        global $conf;

        $this->swriteln("---------------------------------");
        $this->swriteln("- Available commandline options -");
        $this->swriteln("---------------------------------");
        $this->swriteln("ispc extension install <extension_name> - Install the ISPConfig extension <extension_name>.");
        $this->swriteln("ispc extension install <extension_name> <version> - Install the ISPConfig extension <extension_name> version <version>.");
        $this->swriteln("ispc extension update <extension_name> - Update the ISPConfig extension <extension_name>.");
        $this->swriteln("ispc extension uninstall <extension_name> - Uninstall the ISPConfig extension <extension_name>.");
        $this->swriteln("ispc extension enable <extension_name> - Enable the ISPConfig extension <extension_name>.");
        $this->swriteln("ispc extension disable <extension_name> - Disable the ISPConfig extension <extension_name>.");
        $this->swriteln("ispc extension list - List all installed extensions.");
        $this->swriteln("ispc extension available - List all available extensions.");
        $this->swriteln("---------------------------------");
        $this->swriteln();
    }

    public function install($arg) {
        global $app, $conf;

        // Get extension name
        $extension_name = $arg[0];

        // Load extension installer
        $app->log('Installing extension '.$extension_name, LOGLEVEL_DEBUG);

        $app->uses('extension_installer');
        $app->load('extension_installer_base');

        if(isset($arg[1])) {
            $version = $arg[1];
        } else {
            $version = null;
        }

        // Check if extension name is valid
        if (!$app->extension_installer->checkExtensionName($extension_name, $version)) {
            // Show errors
            if(!empty($app->extension_installer->getErrors())) {
                $this->swriteln();
                foreach($app->extension_installer->getErrors() as $error) {
                    $this->swriteln($error);
                }
                $this->swriteln();
                die();
            }
        }

        // check version
        if(!empty($version) && !preg_match('/^[0-9\.]{1,10}$/',$version)) {
            $this->swriteln();
            $this->swriteln('Error: Version contains invalid characters.');
            $this->swriteln();
            die();
        }

        // Install extension
        $app->extension_installer->install_extension($extension_name, $version);

        // Show errors
        if(!empty($app->extension_installer->getErrors())) {
            $this->swriteln();
            foreach($app->extension_installer->getErrors() as $error) {
                $this->swriteln($error);
            }
            $this->swriteln();
            die();
        }

        // scan extensions
        $app->extension_installer->scan_extensions();

        // Show success message
        $this->swriteln();
        $this->swriteln('Extension - '.$extension_name.' - has been installed.');
        $this->swriteln();
    }

    public function update($arg) {
        global $app, $conf;

        // Get extension name
        $extension_name = $arg[0];

        if(isset($arg[1])) {
            $version = $arg[1];
        } else {
            $version = null;
        }

        // Load extension installer
        $app->log('Updating extension '.$extension_name, LOGLEVEL_DEBUG);
        $app->uses('extension_installer');
        $app->load('extension_installer_base');

        // Check if extension name is valid
        if (!$app->extension_installer->checkExtensionName($extension_name, $version)) {
            // Show errors
            if(!empty($app->extension_installer->getErrors())) {
                $this->swriteln();
                foreach($app->extension_installer->getErrors() as $error) {
                    $this->swriteln($error);
                }
                $this->swriteln();
                die();
            }
        }

        // check version
        if(!empty($version) && !preg_match('/^[0-9\.]{1,10}$/',$version)) {
            $this->swriteln();
            $this->swriteln('Error: Version contains invalid characters.');
            $this->swriteln();
            die();
        }

        // Update extension
        $app->extension_installer->update_extension($extension_name, $version);

        // Show errors
        if(!empty($app->extension_installer->getErrors())) {
            $this->swriteln();
            foreach($app->extension_installer->getErrors() as $error) {
                $this->swriteln($error);
            }
            $this->swriteln();
            die();
        }

        // scan extensions
        $app->extension_installer->scan_extensions();

        // Show success message
        $this->swriteln();
        $this->swriteln('Extension - '.$extension_name.' - has been updated.');
        $this->swriteln();
    }

    public function uninstall($arg) {
        global $app, $conf;

        // Get extension name
        $extension_name = $arg[0];

        // Load extension installer
        $app->log('Uninstalling extension '.$extension_name, LOGLEVEL_DEBUG);
        $app->uses('extension_installer');
        $app->load('extension_installer_base');

        // Check if extension name is valid
        if (!$app->extension_installer->checkExtensionName($extension_name)) {
            // Show errors
            if(!empty($app->extension_installer->getErrors())) {
                $this->swriteln();
                foreach($app->extension_installer->getErrors() as $error) {
                    $this->swriteln($error);
                }
                $this->swriteln();
                die();
            }
        }

        // Uninstall extension
        $app->extension_installer->uninstall_extension($extension_name);

        // Show errors
        if(!empty($app->extension_installer->getErrors())) {
            $this->swriteln();
            foreach($app->extension_installer->getErrors() as $error) {
                $this->swriteln($error);
            }
            $this->swriteln();
            die();
        }

        // scan extensions
        $app->extension_installer->scan_extensions();

        // Show success message
        $this->swriteln();
        $this->swriteln('Extension - '.$extension_name.' - has been uninstalled.');
        $this->swriteln();
    }

    public function enable($arg) {
        global $app, $conf;

        // Get extension name
        $extension_name = $arg[0];

        // Load extension installer
        $app->log('Enabling extension '.$extension_name, LOGLEVEL_DEBUG);
        $app->uses('extension_installer');
        $app->load('extension_installer_base');

        // Check if extension name is valid
        if (!$app->extension_installer->checkExtensionName($extension_name)) {
            // Show errors
            if(!empty($app->extension_installer->getErrors())) {
                $this->swriteln();
                foreach($app->extension_installer->getErrors() as $error) {
                    $this->swriteln($error);
                }
                $this->swriteln();
                die();
            }
        }

        // Enable extension
        $app->extension_installer->enable_extension($extension_name);

        // Show errors
        if(!empty($app->extension_installer->getErrors())) {
            $this->swriteln();
            foreach($app->extension_installer->getErrors() as $error) {
                $this->swriteln($error);
            }
            $this->swriteln();
            die();
        }

        // scan extensions
        $app->extension_installer->scan_extensions();

        // Show success message
        $this->swriteln();
        $this->swriteln('Extension - '.$extension_name.' - has been enabled.');
        $this->swriteln();
    }

    public function disable($arg) {
        global $app, $conf;

        // Get extension name
        $extension_name = $arg[0];

        // Load extension installer
        $app->log('Disabling extension '.$extension_name, LOGLEVEL_DEBUG);
        $app->uses('extension_installer');
        $app->load('extension_installer_base');

        // Check if extension name is valid
        if (!$app->extension_installer->checkExtensionName($extension_name)) {
            // Show errors
            if(!empty($app->extension_installer->getErrors())) {
                $this->swriteln();
                foreach($app->extension_installer->getErrors() as $error) {
                    $this->swriteln($error);
                }
                $this->swriteln();
                die();
            }
        }

        // Disable extension
        $app->extension_installer->disable_extension($extension_name);

        // Show errors
        if(!empty($app->extension_installer->getErrors())) {
            $this->swriteln();
            foreach($app->extension_installer->getErrors() as $error) {
                $this->swriteln($error);
            }
            $this->swriteln();
            die();
        }

        // scan extensions
        $app->extension_installer->scan_extensions();

        // Show success message
        $this->swriteln();
        $this->swriteln('Extension - '.$extension_name.' - has been disabled.');
        $this->swriteln();
    }

    public function list($arg) {
        global $app;

        $app->log('Listing installed extensions', LOGLEVEL_DEBUG);
        $app->uses('extension_installer');
        $app->load('extension_installer_base');

        $extensions = $app->extension_installer->scan_extensions();
        if(empty($extensions)) {
            $this->swriteln('No extensions installed.');
            return;
        }

        // Display a header
        $this->swriteln();
        $this->swriteln("\033[1m" . "ISPConfig Extension Manager - Installed Extensions" . "\033[0m");
        $this->swriteln();
        $this->swriteln("Time: " . date('Y-m-d H:i:s'));
        $this->swriteln();

        // Get available extensions form repo API server in json format
        $url = 'https://repo.ispconfig.com/api/v1/list/';
        $response = file_get_contents($url);
        $available_extensions = json_decode($response, true);

        if(empty($available_extensions)) {
            $this->swriteln('No extensions available.');
            return;
        }

        // Define ANSI color codes
        $ansi_reset = "\033[0m";
        $bold = "\033[1m";
        $green = "\033[32m";
        $red = "\033[31m";
        
        // Very simple table with fixed spacing
        $this->swriteln($bold . "Name                Version     Status       Description" . $ansi_reset);
        $this->swriteln("--------------------------------------------------------------------");
        
        foreach($extensions as $extension) {
            // Format each field
            $name = $extension['name'];
            // Truncate long names
            if (strlen($name) > 18) {
                $name = substr($name, 0, 15) . '...';
            }
            
            $version = $extension['version'] ?: 'Unknown';
            // Truncate long versions
            if (strlen($version) > 10) {
                $version = substr($version, 0, 7) . '...';
            }
            
            $status = $extension['active'] ? $green . 'Active' . $ansi_reset : $red . 'Inactive' . $ansi_reset;

            // get description from available extensions
            foreach($available_extensions as $available_extension) {
                if($available_extension['name'] == $name) {
                    $title = $available_extension['title'];
                    break;
                }
            }
            
            // Output each row with fixed column positions
            $this->swrite($bold . $name . $ansi_reset);
            // Add padding after name
            $this->swrite(str_repeat(' ', max(0, 20 - strlen($name))));
            
            $this->swrite($version);
            // Add padding after version
            $this->swrite(str_repeat(' ', max(0, 12 - strlen($version))));
            
            $this->swrite($status);
            // Add padding after status (accounting for ANSI codes)
            $status_text = $extension['active'] ? 'Active' : 'Inactive';
            $this->swrite(str_repeat(' ', max(0, 13 - strlen($status_text))));
            
            $this->swriteln($title);
        }
        
        // Display a footer with helpful information
        $this->swriteln();
        $this->swriteln("Use " . $bold . "ispconfig extension:enable <name>" . $ansi_reset . " to enable an extension");
        $this->swriteln("Use " . $bold . "ispconfig extension:disable <name>" . $ansi_reset . " to disable an extension");
        $this->swriteln();
    }

    public function list_available($arg) {
        global $app;

        $app->log('Listing available extensions', LOGLEVEL_DEBUG);

        $app->uses('extension_installer');
        $app->load('extension_installer_base');

        // Get available extensions form repo API server in json format
        $url = $app->extension_installer->getRepoListUrl();
        $response = file_get_contents($url);
        $extensions = json_decode($response, true);

        if(empty($extensions)) {
            $this->swriteln('No extensions available.');
            return;
        }

        // Display a header
        $this->swriteln();
        $this->swriteln("\033[1m" . "ISPConfig Extension Manager - Available Extensions" . "\033[0m");
        $this->swriteln();
        $this->swriteln("Time: " . date('Y-m-d H:i:s'));
        $this->swriteln();

        // Define ANSI color codes
        $ansi_reset = "\033[0m";
        $bold = "\033[1m";
        $green = "\033[32m";
        $red = "\033[31m";
        
        // Very simple table with fixed spacing
        $this->swriteln($bold . "Name                Version     License      Description" . $ansi_reset);
        $this->swriteln("--------------------------------------------------------------------");
        
        foreach($extensions as $extension) {
            // Format each field
            $name = $extension['name'];
            // Truncate long names
            if (strlen($name) > 18) {
                $name = substr($name, 0, 15) . '...';
            }
            
            $version = $extension['version'] ?: 'Unknown';
            // Truncate long versions
            if (strlen($version) > 10) {
                $version = substr($version, 0, 7) . '...';
            }
            
            $license = isset($extension['license']) ? $extension['license'] : 'Unknown';
            // Truncate long license
            if (strlen($license) > 10) {
                $license = substr($license, 0, 7) . '...';
            }
            
            $title = isset($extension['title']) ? $extension['title'] : 'No title available';
            
            // Output each row with fixed column positions
            $this->swrite($bold . $name . $ansi_reset);
            // Add padding after name
            $this->swrite(str_repeat(' ', max(0, 20 - strlen($name))));
            
            $this->swrite($version);
            // Add padding after version
            $this->swrite(str_repeat(' ', max(0, 12 - strlen($version))));
            
            $this->swrite($license);
            // Add padding after license
            $this->swrite(str_repeat(' ', max(0, 13 - strlen($license))));
            
            $this->swriteln($title);
        }
        
        // Display a footer with helpful information
        $this->swriteln();
        $this->swriteln("Use " . $bold . "ispconfig extension:install <name>" . $ansi_reset . " to install an extension");
        $this->swriteln("Use " . $bold . "ispconfig extension:enable <name>" . $ansi_reset . " to enable an extension");
        $this->swriteln("Use " . $bold . "ispconfig extension:disable <name>" . $ansi_reset . " to disable an extension");
        $this->swriteln();
    }

}
