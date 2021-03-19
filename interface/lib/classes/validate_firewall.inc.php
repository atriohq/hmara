<?php

/**
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


class validate_firewall {

	function get_error($errmsg) {
		global $app;
		if(isset($app->tform->wordbook[$errmsg])) {
			return $app->tform->wordbook[$errmsg]."<br>\r\n";
		} else {
			 return $errmsg."<br>\r\n";
		}
	}

	function check_firewall($field_name, $field_value, $validator) {
		global $app;

		$temp = $app->db->queryOneRecord('SELECT firewall_placeholder FROM server WHERE server_id = ?', intval($_POST['server_id']));
		$records = json_decode($temp['firewall_placeholder'], true);
		foreach($records as $idx=>$val) $placeholder[] = '{'.$idx.'}';
		$placeholder[] = '{AUTO}';

		if($field_value != '') {
//			print_R($placeholder);
			$temp = str_replace($placeholder, '', $field_value);
			$ports = explode(',', $temp);
			$ports = array_filter($ports, function($value) { return !is_null($value) && $value !== ''; });
			if(!empty($ports)) {
				$regex = '/^\d{1,5}(?::\d{1,5})?(?:,\d{1,5}(?::\d{1,5})?)*$/';
				if(!preg_match($regex, implode(',', $ports))) return $this->get_error($validator['errmsg']);
			}
		}
	}

}

