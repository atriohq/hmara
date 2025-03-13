<?php

/*
Copyright (c) 2025, Till Brehm, projektfarm Gmbh
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

class extension_module {

	var $module_name = 'extension_module';
	var $class_name = 'extension_module';
	var $actions_available = [];
	var $tables = [];

	var $ext_dir = '/usr/local/ispconfig/extensions';

	/*
	  This function is called during ispconfig installation to determine
	  if a symlink shall be created for this plugin.
	*/
	function onInstall() {

		// Create the ext directory if it does not exist
		if(!is_dir($this->ext_dir)) {
			mkdir($this->ext_dir, 0750);
			chown($this->ext_dir, 'root');
			chgrp($this->ext_dir, 'ispconfig');	
		}
		
		return true;

	}

	/*
	 	This function is called when the module is loaded
	*/

	function onLoad() {
		global $app;

		// Find all table lists of active extensions
		if(is_dir($this->ext_dir)) {
			$table_includes = glob($this->ext_dir.'/*/tables.php');
			if(is_array($table_includes)) {
				foreach($table_includes as $file) {
					$dir = dirname($file);
					// include only tables of active extensions
					if(file_exists($dir.'/active')) {

						// Include the tables.php file of the ISPConfig extension
						include $file;

						// Loop trough all tables and register events for them.
						if(isset($tables) && is_array($tables)) {
							foreach($tables as $table) {
				
								// Add insert, update and delete action for each table
								$this->actions_available[] = $table.'_insert';
								$this->actions_available[] = $table.'_update';
								$this->actions_available[] = $table.'_delete';
				
								// add table to tables array
								$this->tables[] = $table;
				
								// Register table hook so process function of this module
								// gets called when a change in this table happens
								$app->modules->registerTableHook($table, $this->module_name, 'process');
				
							}
						}
					}
				}
			}
		}

		/*
		Annonce the actions that where provided by this module, so plugins
		can register on them.
		*/

		$app->plugins->announceEvents($this->module_name, $this->actions_available);

	}

	/*
	 This function is called when a change in one of the registered tables is detected.
	 The function then raises the events for the plugins.
	*/

	function process($tablename, $action, $data) {
		global $app;

		if(in_array($tablename,$this->tables)) {
			if($action == 'i') $app->plugins->raiseEvent($tablename.'_insert', $data);
			if($action == 'u') $app->plugins->raiseEvent($tablename.'_update', $data);
			if($action == 'd') $app->plugins->raiseEvent($tablename.'_delete', $data);
		}

	}

} // end class
