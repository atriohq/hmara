<?php

/*
Copyright (c) 2024, Till Brehm, ISPConfig UG
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

class cronjob_monitor_sys_usage extends cronjob {

	// job schedule
	protected $_schedule = '* * * * *';
	protected $_run_at_new = true;

	private $_tools = null;

	/* this function is optional if it contains no custom code */
	public function onPrepare() {
		global $app;

		parent::onPrepare();
	}

	/* this function is optional if it contains no custom code */
	public function onBeforeRun() {
		global $app;

		return parent::onBeforeRun();
	}

	public function onRunJob() {
		global $app, $conf;

		/* used for all monitor cronjobs */
		$app->load('monitor_tools');
		$this->_tools = new monitor_tools();
		/* end global section for monitor cronjobs */

		/* the id of the server as int */
		$server_id = intval($conf['server_id']);
		

        // Get record with existing data points
        $type = 'sys_usage';
        $max_data_points = 15;
        $sql = "SELECT `data` FROM `monitor_data` WHERE `type` = ?";
        $rec = $app->db->queryOneRecord($sql, $type);

        if(!empty($rec)) {
            $data = unserialize($rec['data']);
        } else {
            $data = [];
        }

        // Set tstamp
        if(isset($data['tstamp'])) {
            $interval_seconds = time() - $data['tstamp'];
        } else {
            $interval_seconds = 60;
        }
        $data['tstamp'] = time();
 
        // Monitor load average in percent
        $load = $this->get_system_load();

        // Get the number of CPU cores using nproc
        $cores = $this->get_cpu_cores();

        // Calculate the load percentage
        if ($cores > 0) {
            $load_percent = round(($load / $cores) * 100, 2);
        } else {
            $load_percent = 0;
        }

        // Trim array size
        if(isset($data['load']) && count($data['load']) >= $max_data_points) {
            array_shift($data['load']);
        }

        $data['load'][] = $load_percent;
		
		// Trim time array size
        if(isset($data['time']) && count($data['time']) >= $max_data_points) {
            array_shift($data['time']);
        }

        $data['time'][] = date('H:i');

        // Monitor memory usage in percent
        $meminfo = $this->get_memory_info();

        // Extract total and available memory
        $total_mem = (int) filter_var($meminfo['MemTotal'], FILTER_SANITIZE_NUMBER_INT);
        $available_mem = (int) filter_var($meminfo['MemAvailable'], FILTER_SANITIZE_NUMBER_INT);

        // Calculate used memory and memory usage percentage
        $used_mem = $total_mem - $available_mem;
        $memory_usage_percent = round(($used_mem / $total_mem) * 100, 2);

        // Trim array size
        if(isset($data['mem']) && count($data['mem']) >= $max_data_points) {
            array_shift($data['mem']);
        }

        $data['mem'][] = $memory_usage_percent;

        // Get network bandwidth
        $interface = 'eth0';
        list($rx, $tx) = $this->get_network_bytes($interface);

        // Trim array size
        if(isset($data['net']) && count($data['net']) >= $max_data_points) {
            array_shift($data['net']);
        }

        // Calculate network bandwidth in bytes per second
        if(isset($data['rx']) && isset($data['tx'])) {
            $data['net'][] = [
                'rx' => ($rx - $data['rx']) / $interval_seconds,
                'tx' => ($tx - $data['tx']) / $interval_seconds
            ];

        }

        // Set absolute network bw value
        $data['rx'] = $rx;
        $data['tx'] = $tx;


        $res = array();
        $res['server_id'] = $server_id;
        $res['data'] = $data;
        $res['state'] = 'ok';

        /*
        * Insert the data into the database
            */
        if(empty($rec)) {
            $sql = 'INSERT INTO `monitor_data` (`server_id`, `type`, `created`, `data`, `state`) ' .
                'VALUES (' .
                $res['server_id'] . ', ' .
                "'" . $app->dbmaster->quote($type) . "', " .
                'UNIX_TIMESTAMP(), ' .
                "'" . $app->dbmaster->quote(serialize($res['data'])) . "', " .
                "'" . $res['state'] . "'" .
                ')';
            $app->dbmaster->query($sql);
        } else {
            $sql = "UPDATE `monitor_data` SET `data` = ?, `created` = ? WHERE `type` = ?";
            $app->dbmaster->query($sql,serialize($res['data']),time(),$type);
        }
        

        /* The new data is written, now we can delete the old one */
        $this->_tools->delOldRecords($type, $res['server_id']);


		parent::onRunJob();
	}

	/* this function is optional if it contains no custom code */
	public function onAfterRun() {
		global $app;

		parent::onAfterRun();
	}

    private function get_system_load() {
        $handle = fopen("/proc/loadavg", "r");
        if ($handle) {
            $contents = fread($handle, 100);
            fclose($handle);
            if(!empty($contents)) {
                $load = explode(' ', $contents)[0];
            } else {
                $load = 0;
            }
        } else {
            $load = 0;
        }
        return $load;
    }
        
    /**
     * get_cpu_cores
     *
     * @return int
     */

    private function get_cpu_cores() : int {
        return (int) shell_exec("nproc");
    }
        
    /**
     * get_memory_info
     *
     * @return array
     */
    
    private function get_memory_info() : array {
        $data = file_get_contents("/proc/meminfo");
        $meminfo = [];
        if(!empty($data)) {
            foreach (explode("\n", $data) as $line) {
                if(!empty($line)) {
                    list($key, $val) = explode(":", $line);
                    $meminfo[$key] = trim($val);
                }
            }
        }
        return $meminfo;
    }
    
    /**
     * get_network_bytes
     *
     * @param  mixed $interface
     * @return array
     */

    private function get_network_bytes($interface) : array {
        $data = file('/proc/net/dev');
        if(!empty($data)) {
            foreach ($data as $line) {
                if (strpos($line, $interface) !== false) {
                    $parts = preg_split('/\s+/', trim($line));
                    return [$parts[1], $parts[9]]; // RX bytes and TX bytes
                }
            }
        }
        return [0, 0];
    }

}