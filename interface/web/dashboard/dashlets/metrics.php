<?php

class dashlet_metrics {
	
	function show() {
		global $app;
		
        /*
		if ($_SESSION["s"]["user"]["typ"] != 'admin') {
			return '';
		}
        */

		//* Loading Template
		$app->uses('tpl');
		
		$tpl = new tpl;
		$tpl->newTemplate("dashlets/templates/metrics.htm");
		
		$wb       = array();
		$lng_file = 'lib/lang/' . $_SESSION['s']['language'] . '_dashlet_metrics.lng';
		if (is_file($lng_file)) {
			include $lng_file;
		} elseif (is_file('lib/lang/en_dashlet_metrics.lng')) {
			include 'lib/lang/en_dashlet_metrics.lng';
		}
		$tpl->setVar($wb);
		
		// Get monitor data
        $rec = $app->db->queryOneRecord("SELECT `data`FROM `monitor_data` WHERE `type` = 'sys_usage' ORDER BY `created` DESC LIMIT 0,1");
		$data = unserialize($rec['data']);

        if(isset($data['load']) && is_array($data['load'])) {
            $tpl->setVar('loadchart_data', implode(', ',$data['load']));
        }

        if(isset($data['mem']) && is_array($data['mem'])) {
            $tpl->setVar('memchart_data', implode(', ',$data['mem']));
        }

        $label = [];
        $n = 1;
        if(isset($data['net']) && is_array($data['net'])) {
            $rx = [];
            $tx = [];
            foreach($data['net'] as $val) {
                $rx[] = $val['rx'];
                $tx[] = $val['tx'];
                $label[] = $n;
                $n++;
            }
            $tpl->setVar('rxchart_data', implode(', ',$rx));
            $tpl->setVar('txchart_data', implode(', ',$tx));
        }
        //$tpl->setVar('label', implode(', ',$label));
		
		if(isset($data['time']) && is_array($data['time'])) {
			foreach($data['time'] as $key => $val) {
				$data['time'][$key] = "'".$val."'";
			}
            $tpl->setVar('label', implode(', ',$data['time']));
        }
		
		$tpl->setVar('tablelayout', $_SESSION['s']['user']['table_layout']);

        $tpl->setVar('loadchart_label',$wb['loadchart_label']);
        $tpl->setVar('memchart_label',$wb['memchart_label']);
        $tpl->setVar('rxchart_label',$wb['rxchart_label']);
        $tpl->setVar('txchart_label',$wb['txchart_label']);
        $tpl->setvar('label_chart_title',$wb['label_chart_title']);
		
		return $tpl->grab();
	}
	
}
