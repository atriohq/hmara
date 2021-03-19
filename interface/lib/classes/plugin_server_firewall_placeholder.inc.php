<?php

/*
Copyright (c) 2020, Florian Schaal, schaal @it UG
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

class plugin_server_firewall_placeholder extends plugin_base {

	var $module;
	var $form;
	var $tab;
	var $record_id;
	var $formdef;
	var $options;

	function onShow() {
		global $app;

		if($_SESSION['s']['user']['typ'] != 'admin') die('This function needs admin priveliges');

		$listTpl = new tpl;
		$listTpl->newTemplate('templates/server_config_firewall_placeholder_edit.htm');

		//* Get the data
		$temp = $app->db->queryOneRecord('SELECT `firewall_placeholder` FROM `server` WHERE `server_id` = ?', $this->form->id);
		$data = json_decode($temp['firewall_placeholder'], true);
		foreach($data as $idx=>$val) {
			$records[$idx] = implode(',',$val);
		}
		if(is_array($records)) {
			foreach($records as $service=>$ports) {
				$rec['service'] = $app->functions->htmlentities($service);
				$rec['ports'] = $ports;
				$records_new[] = $rec;
			}
		}
		$listTpl->setLoop('records',@$records_new);
		$listTpl->setVar('parent_id',$this->form->id);


                // Setting Returnto information in the session
                $list_name = 'server_firewall_placeholder';
                $_SESSION['s']['list'][$list_name]['parent_id'] = $this->form->id;
                $_SESSION['s']['list'][$list_name]['parent_name'] = $app->tform->formDef['name'];
                $_SESSION['s']['list'][$list_name]['parent_tab'] = $_SESSION['s']['form']['tab'];
                $_SESSION['s']['list'][$list_name]['parent_script'] = $app->tform->formDef['action'];
                $_SESSION['s']['form']["return_to"] = $list_name;
		return $listTpl->grab();
	} 

	function onUpdate() {
		global $app;

		$dataRecord = $this->form->dataRecord;
		$server_id = intval($dataRecord['id']);
		$temp = $app->db->queryOneRecord('SELECT `firewall_placeholder` FROM `server` WHERE `server_id` = ?', $server_id);
		$data = json_decode($temp['firewall_placeholder'], true);
		$update = false;
		$error = '';
		foreach($data as $idx=>$val) {
			//* validate updates
			if($dataRecord[$idx] != implode(',',$val)) {
				$check = explode(',',$dataRecord[$idx]);
				foreach($check as $_idx=>$validate) {
					$validate = trim($validate);
					if($validate != '') {
						if(!preg_match('/^\d{1,5}(?::\d{1,5})?(?:,\d{1,5}(?::\d{1,5})?)*$/', $validate)) {
							$error .= "Invalide value $validate for $idx <br>";
						} else {
							$dataRecord[$_idx] = $validate;
						}
					}
				}
				$data[$idx] = explode(',',$dataRecord[$_idx]);
				$update = true;
			}
		}

		if($error != '') {
			$app->error($error);
		}

		if($update) {
			$app->db->query('UPDATE `server` SET `firewall_placeholder` = ? WHERE `server_id` = ?', json_encode($data), $server_id);
			$firewall = $app->db->queryOneRecord('SELECT * FROM `firewall` WHERE `server_id` = ? AND `active` = ?', $server_id, 'y');
			if($firewall) {
				$app->db->datalogUpdate('firewall', $firewall, 'firewall_id', $firewall['firewall_id'], true);
			}
		}
			
	}

}

