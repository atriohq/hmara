<?php

class extension_installer_base {

    protected $extension_basedir = '/usr/local/ispconfig/extensions';
    protected $ispconfig_dir = '/usr/local/ispconfig';

	public function __construct() {
		
	}

    //* Function to install the extension
	public function install() {
		
	}

    //* Function to update the extension
	public function update() {
		
	}

    //* Function to uninstall the extension
	public function uninstall() {
		
	}

    // enable extension
    public function enable() {
        
    }

    // disable extension
    public function disable() {
        
    }
}