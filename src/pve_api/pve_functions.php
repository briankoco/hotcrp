<?php
# Load Curl API

include_once("pve_api.php");

function get_vm_connect_config($conf) {
	$pve_api_config = array(
		// Access Data for PVE Node
		"pve_server"	=> $conf->opt("pve_server"),
		"pve_port"	=> $conf->opt("pve_port"), // PVE Port
		"pve_api"	=> $conf->opt("pve_api"), // PVE Port
		"pve_user"	=> $conf->opt("pve_user"), // PVE Username
		"pve_pass"	=> $conf->opt("pve_pass"), // PVE Password
		
		"vm_node"	=> $conf->opt("pve_default_node"), // Name of PVE Node
		"vm_type"	=> $conf->opt("pve_default_type"), // qemu = KVM or LXC = lxc
		"vm_pool"	=> $conf->opt("pve_pool"), // qemu = KVM or LXC = lxc
		"vm_prefix" => $conf->opt("pve_prefix"),
		"vm_suffix" => $conf->opt("pve_suffix"),
		"vm_defaults" => $conf->opt("pve_vms"),
		"vm_nodes" => $conf->opt("pve_nodes"),
	);
	foreach ($conf->opt("pve_nodes")[$pve_api_config['vm_node']]['vms'] as $type => $templateid ) {
		$pve_api_config['vm_types'][$type] = $templateid['template'];
	};

	$pve_api_config["api_pve_login"] = api_pve_login( $pve_api_config["pve_server"], $pve_api_config["pve_port"], $pve_api_config["pve_user"], $pve_api_config["pve_pass"] );
	$pve_api_config["pve_ticket"] = $pve_api_config["api_pve_login"][ 'data' ][ 'ticket' ];
	$pve_api_config["pve_CSRFPreventionToken"] = $pve_api_config["api_pve_login"][ 'data' ][ 'CSRFPreventionToken' ];
	// VNC urlencode Ticket ID
	$pve_api_config["pve_ticket2"] = urlencode( $pve_api_config["pve_ticket"] );
	return $pve_api_config;
};


/**
 * Generate a random string, using a cryptographically secure 
 * pseudorandom number generator (random_int)
 * 
 * For PHP 7, random_int is a PHP core function
 * For PHP 5.x, depends on https://github.com/paragonie/random_compat
 * 
 * @param int $length      How many characters do we want?
 * @param string $keyspace A string of all possible characters
 *                         to select from
 * @return string
 */
function random_str(
    $length,
    $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
) {
	$str = '';
	$max = mb_strlen($keyspace, '8bit') - 1;
	if ($max < 1) {
		throw new Exception('$keyspace must be at least two characters long');
	}
	for ($i = 0; $i < $length; ++$i) {
		$str .= $keyspace[random_int(0, $max)];
	}
	return $str;
}

function get_next_vmid($config) {
	$params = [];
	$next_vmid = api_pve_con( $config["pve_server"], $config["pve_port"], "api2/json/cluster/nextid", "GET", $params, $config["pve_ticket"], $config["pve_CSRFPreventionToken"] );
	return $next_vmid['data'];
};

function create_cluster_user($org, $id, $db, $file)
{
    $username=$org . $id;
    $pass=substr(md5(rand()), 0, 7);
    $result = Dbl::qe($db, "select email, firstName, lastName, affiliation, country from ContactInfo WHERE contactID = ?;", $id);

    while (($row = $result->fetch_row())) {

    $retval = 1;
    $output = "";
    $cmd="stdbuf -oL aecuser user " . $username . " " . $row[0] . " \"" . $row[1] . " " . $row[2] . "\" \"" . $row[3] . "\" \"" . $row[4] . "\"  " . $pass . "  " . $org . " 2>&1 >> $file &\n"; //
    $myfile = fopen($file, "a");
    fwrite($myfile, $cmd);
    fclose($myfile);
   // exec($cmd, $output, $retval);
    error_log("Cmd $cmd retval $retval");
     
    // Insert into db
    if ($retval == 0)
     {  
       $query="insert into ClusterUsers (contactId, username, password) values (" . $id  . ",'" . $username . "','" . $pass . "')";
       Dbl::qe($db,$query);
     }
    }
};

/*
 * Update VM config for specific VM
 * @return updated config
 */
function update_vm_config($vmid, $config, $db) {
	$result = Dbl::qe($db, "SELECT * FROM UserVMs WHERE vmid = ? AND active = 1;", $vmid);
	$result = $result->fetch_assoc();
	$config['pve_server'] = $result['vmcluster'];
	$config['vm_node'] = $result['vmnode'];
	return $config;
};


function get_cluster_load($config, $db) {
	$vm_counts_global = array();
	foreach ($config['vm_nodes'] as $node => $settings) {
		$maxmem = $settings["maxmem_gb"];
		$maxcpu = $settings["maxcpu_core"];
		$curcpu = 0;
		$curmem = 0;
		$vm_type_counts = array();
		$vm_type_free = array();
		
		$vm_counts_global[$node] = array();
		foreach ($settings['vms'] as $type => $vmsettings) {
			
			$result = Dbl::qe($db, "SELECT * FROM UserVMs WHERE vmnode = ? AND vmtype = ? AND active = 1;", $node, $type);
			$vm_type_counts[$type] = 0;
			foreach ($result as $key => $vm) {
				$curcpu += $config['vm_defaults'][$type]['cores'];
				$curmem += $config['vm_defaults'][$type]['mem'];
				$vm_type_counts[$type] += 1;
			};
		};
		$freemem = $maxmem - $curmem;
		$freecpu = $maxcpu - $curcpu;
		foreach ($vm_type_counts as $type => $count) {
			
			$resource_mem_free = $freemem / $config['vm_defaults'][$type]['mem'];
			$resource_cpu_free = $freecpu / $config['vm_defaults'][$type]['cores'];
			$limit_free = $config['vm_nodes'][$node]['vms'][$type]['max'] - $count;
			if ($limit_free >= $resource_mem_free) {
				$limit_free = $resource_mem_free;
			};
			
			if ($limit_free >= $resource_cpu_free) {
				$limit_free = $resource_cpu_free;
			};
			if ($limit_free < 0) {
				$limit_free = 0;
			};
			$vm_counts_global[$node][$type] = $limit_free;

		};

	};
	$vm_counts_total = array();
	foreach ($vm_counts_global as $node => $type) {
		foreach($type as $k => $cnt) {
			$vm_counts_total[$k] = 0;
		};
	};
	foreach ($vm_counts_global as $node => $type) {
		foreach($type as $k => $cnt) {
			$vm_counts_total[$k] += $cnt;
		};
	};
	$return_array = array(
		'nodes' => $vm_counts_global,
		'stats' => $vm_counts_total,
	);
	return $return_array;
}

/*
 * Update VM config for specific VM
 * @return updated config
 */
function select_cluster_node($vmtype, $config, $db, $load) {
	$cur_free = 0;
	$cur_node = '';
	foreach ($load['nodes'] as $node => $stats ) {
		if ($stats[$vmtype] > $cur_free) {
			$cur_node = $node;
			$cur_free = $stats[$vmtype];
		}
	};
	error_log("Least loaded node for ".$vmtype." is ".$cur_node." with ".$cur_free." free.\n");
	$config['vm_node'] = $cur_node;
	foreach ($config["vm_nodes"] as $node => $node_cfg ) {
		foreach ($node_cfg['vms'] as $template_type => $template ) {
			if ($node == $cur_node){
				$config['vm_types'][$template_type] = $template['template'];
			}
		}
	};

	return $config;
};


/*
 * Initial setup of a VM, i.e., setting an ssh-key and password
 */
function initialize_vm($vmid, $config) {
	$password = random_str(15);
	
	// get current cloud_init configuration
	// $params = ['type' => 'user'];
	// $vm_cloudinit = api_pve_con( $config["pve_server"], $config["pve_port"], "api2/json/nodes/".$config["vm_node"]."/".$config['vm_type']."/".$vmid."/cloudinit/dump", "GET", $params, $config["pve_ticket"], $config["pve_CSRFPreventionToken"] );
	// set password
	$params = ['cipassword' => $password ];
	$new_vm = api_pve_con( $config["pve_server"], $config["pve_port"], "api2/json/nodes/".$config["vm_node"]."/".$config['vm_type']."/".$vmid."/config", "PUT", $params, $config["pve_ticket"], $config["pve_CSRFPreventionToken"] );
	
	return $password;
};

/*
 * Clones a new VM for a given type with a generated vmid and name
 */ 
function create_new_vm($type, $config, $db, $cluster_load) {
	
	
	// get next vmid
	$vmid = get_next_vmid($config);
	
	// generate vm name
	$vm_name = $config['vm_prefix'].$vmid.'-'.$type.$config['vm_suffix'];
	
	// get ideal cluster node
	$config = select_cluster_node($type, $config, $db, $cluster_load);
	
	// select correct template
	$vm_template = $config['vm_types'][$type];
	error_log("Creating VM".$vmid." on cluster ".$config["pve_server"]." node ".$config['vm_node']." from template ".$vm_template.";\n");
	
	// clone VM
	$params = ['newid' => $vmid, 'name' => $vm_name, 'target' => $config['vm_node'], 'full' => '0', 'pool' => $config['vm_pool']];
	
	$new_vm = api_pve_con( $config["pve_server"], $config["pve_port"], "api2/json/nodes/".$config["vm_node"]."/".$config['vm_type']."/".$vm_template."/clone", "POST", $params, $config["pve_ticket"], $config["pve_CSRFPreventionToken"] );
	
	$vm_status = get_vm_status($vmid, $config);
	// Wait for the VM to be ready
	$loopcounter = 0;
	while (!is_array($vm_status['data'])) {
		sleep(2);
		$vm_status = get_vm_status($vmid, $config);
		$loopcounter += 1;
		if ($loopcounter > 10) {
			error_log("Creation of VM failed in create!\n");
			return false;
		};
	}
	$loopcounter = 0;
	while (!array_key_exists('status', $vm_status['data'])) {
		sleep(2);
		$vm_status = get_vm_status($vmid, $config);
		$loopcounter += 1;
		if ($loopcounter > 10) {
			error_log("Creation of VM failed in get status!\n");
			return false;
		};
	};
	$init_vm_pw = initialize_vm($vmid, $config);
	
	
	$vm_data = array(
		'vmid' => $vmid,
		'vmnode' => $config['vm_node'],
		'vmcluster' => $config['pve_server'],
		'vmname' => $vm_name,
		'password' => $init_vm_pw,
	);
	$vm_data['vm_start'] = start_vm($vmid, $config);
	
	return $vm_data;
};

function get_vm_status($vmid, $config) {
	$params = [];
	$vm_status = api_pve_con( $config["pve_server"], $config["pve_port"], "api2/json/nodes/".$config["vm_node"]."/".$config['vm_type']."/".$vmid."/status/current", "GET", $params, $config["pve_ticket"], $config["pve_CSRFPreventionToken"] );
	return $vm_status;
}

function get_vm_info($vmid, $config) {
	$params = [];
	$vm_info = api_pve_con( $config["pve_server"], $config["pve_port"], "api2/json/nodes/".$config["vm_node"]."/".$config['vm_type']."/".$vmid."/agent/info", "GET", $params, $config["pve_ticket"], $config["pve_CSRFPreventionToken"] );
	return $vm_info;
}

function get_vm_ifstat($vmid, $config) {
	$params = [];
	$vm_ifstat = api_pve_con( $config["pve_server"], $config["pve_port"], "api2/json/nodes/".$config["vm_node"]."/".$config['vm_type']."/".$vmid."/agent/network-get-interfaces", "GET", $params, $config["pve_ticket"], $config["pve_CSRFPreventionToken"] );
	$ip_data = array();
	foreach ($vm_ifstat['data']['result'] as $resid => $res) {
		if ($res['name'] == 'eth0') {
			$ip_data['mac'] = $res['hardware-address'];
			foreach ($res['ip-addresses'] as $ipid => $ip) {
				if ((stripos($ip['ip-address'], '141.39.22') !== false) || !(stripos($ip['ip-address'], 'fe80::') !== false)) {
					$ip_data[$ip['ip-address-type']] = $ip['ip-address'];
				};
			};
		};
	};
	return $ip_data;
}

function start_vm($vmid, $config) {
	$params = [];
	$vm_start = api_pve_con( $config["pve_server"], $config["pve_port"], "api2/json/nodes/".$config["vm_node"]."/".$config['vm_type']."/".$vmid."/status/start", "POST", $params, $config["pve_ticket"], $config["pve_CSRFPreventionToken"] );
	return $vm_start;
};

function stop_vm($vmid, $config) {
	$params = [];
	$vm_stop = api_pve_con( $config["pve_server"], $config["pve_port"], "api2/json/nodes/".$config["vm_node"]."/".$config['vm_type']."/".$vmid."/status/stop", "POST", $params, $config["pve_ticket"], $config["pve_CSRFPreventionToken"] );
	return $vm_stop;
};

function reset_vm($vmid, $config) {
	$params = [];
	$vm_reset = api_pve_con( $config["pve_server"], $config["pve_port"], "api2/json/nodes/".$config["vm_node"]."/".$config['vm_type']."/".$vmid."/status/reset", "POST", $params, $config["pve_ticket"], $config["pve_CSRFPreventionToken"] );
	return $vm_reset;
};

function reset_vm_password($vmid, $config) {
	$password = random_str(15);
	
	$params = ['username' => 'artifacts', 'password' => $password ];
	$vm_reset_pw = api_pve_con( $config["pve_server"], $config["pve_port"], "api2/json/nodes/".$config["vm_node"]."/".$config['vm_type']."/".$vmid."/agent/set-user-password", "POST", $params, $config["pve_ticket"], $config["pve_CSRFPreventionToken"] );
	return $password;

};


/*
 * Technically returns an iframe with access to a virtual console. Sadly not really working due to cookie issues.
 */
function get_console_url($vmid, $config) {
	$params = ['generate-password' => 1, 'websocket' => 1];
	$vm_console_config = api_pve_con( $config["pve_server"], $config["pve_port"], "api2/json/nodes/".$config["vm_node"]."/".$config['vm_type']."/".$vmid."/vncproxy", "POST", $params, $config["pve_ticket"], $config["pve_CSRFPreventionToken"] );
	$params = ['vncticket' => $vm_console_config['data']['ticket'], 'port' => $vm_console_config['data']['port']];
	$vm_websocket = api_pve_con( $config["pve_server"], $config["pve_port"], "api2/json/nodes/".$config["vm_node"]."/".$config['vm_type']."/".$vmid."/vncwebsocket", "GET", $params, $config["pve_ticket"], $config["pve_CSRFPreventionToken"] );

	$src_href = 'novnc/?autoconnect=1&reconnect=0&host='.$config["pve_api"].'&encrypt=1&path=/websockify&password='.urlencode($vm_console_config['data']['password']).'&shared=1&resize=scale';

	return '<iframe src="'.$src_href.'" frameborder="0" scrolling="no" width="100%" height="640px"></iframe>';
};

function get_node() {

};

function delete_vm($vmid, $config) {
	$vm_status = get_vm_status($vmid, $config);
	if ($vm_status['data']['status'] == 'running') {
		stop_vm($vmid, $config);
		sleep(2);
		$vm_status = get_vm_status($vmid, $config);
	};

	$params = ['purge' => 1];
	$vm_delete = api_pve_con( $config["pve_server"], $config["pve_port"], "api2/json/nodes/".$config["vm_node"]."/".$config['vm_type']."/".$vmid, "DELETE", $params, $config["pve_ticket"], $config["pve_CSRFPreventionToken"] );
	return $vm_delete;
};

?>
