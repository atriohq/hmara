<?php
// Set the path to the form definition file.
$tform_def_file = 'form/web_git.tform.php';

// include the core configuration and application classes
require_once('../../lib/config.inc.php');
require_once('../../lib/app.inc.php');

// Check the  module permissions and redirect if not allowed.
if(!stristr($_SESSION['s']['user']['modules'],'vcs')) {
    header('Location: ../index.php');
    die;
}

// Load the templating and form classes
$app->uses('tpl,tform,tform_actions');
$app->load('tform_actions');

// Create a class page_action that extends the tform_actions base class
class page_action extends tform_actions {

    	//Customisations for the page actions will be defined here
	function onAfterInsert()
        {
                global $app, $conf;
		//
        }

	/* Get the log file  */
	function onLoad() {
		global $app, $conf;

		$this->id = $app->functions->intval($_REQUEST["id"]);
		$filename = $this->id.".log";
		$log = "No data logged for this repository";

		$array_logs = $app->db->queryAllArray("SELECT data FROM monitor_data WHERE type = ?", "log_web_git_" . $this->id);
		if(count($array_logs) > 0) {
			$log = '';
			foreach($array_logs as $datalog) {
				$log .= unserialize($datalog).PHP_EOL;
			}
		}

		$app->tpl->setVar("log", nl2br($log));
		parent::onLoad();
	}
}

// Create the new page object
$page = new page_action();

// Start the page rendering and action handling
$page->onLoad();

?>
