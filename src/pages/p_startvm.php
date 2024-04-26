<?php
// pages/p_offline.php -- HotCRP offline review management page
// Copyright (c) 2006-2023 Eddie Kohler; see LICENSE.

class StartVm_Page {
    /** @var Conf */
    public $conf;
    /** @var Contact */
    public $user;
    /** @var Qrequest */
    public $qreq;
    /** @var MessageSet */
    private $ms;

    function __construct(Contact $user, Qrequest $qreq) {
        $this->conf = $user->conf;
        $this->user = $user;
        $this->qreq = $qreq;
        $this->ms = new MessageSet;
	$this->pid = $_GET['pid'];
    }

    function get_log($file){

    echo '<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>';
    echo '<script type="text/javascript" src="scripts/test.js"></script>';

    echo '<script>
     var a;
     a=setInterval(fun("'  . $_GET['createhash'] . '"), 3000);
    </script>';

    echo "Called with " . $_GET['createhash'];
    $messages=[];
    $logfile = fopen($file, "r") or die(json_encode(array_push($messages, "Error")));
    $count = 0;
    while(!feof($logfile) || $count < $_SESSION['pos']){
    	$tmp = fgets($logfile);
    	$count++;
    }

    echo "Count " . $count . " file " . $file . " pos " . $_SESSION['pos'];
   
    // if the session var that holds the offset position is not set 
    // or has value more than the lines of text file
    if (!isset($_SESSION['pos']) || $_SESSION['pos'] >= $count) {
	        $_SESSION['pos'] = 0; // back to the beginning
    	    } 


    // the array that holds the logs data
    $messages = array(); 

    // move the pointer to the current position
    fseek($logfile, $_SESSION['pos']);
    
    // read the file
     while(!feof($logfile)){
       $msg = fgets($logfile);
       	$msg1 = explode("\n", $msg);
       	array_push($messages, $msg1);
       	$_SESSION['pos']++;
     }

    // return the array
    echo json_encode($messages);

    fclose($logfile);
    }

    // this is useful for post stuff
    function handle_post_request() {
        if ($this->qreq->action == 'updatevm' ) {
            $this->update_vm($this->user, $this->qreq, $this->qreq->vmid, $this->qreq->paperid, $this->qreq->rev_access, $this->qreq->aut_access);
        } elseif ($this->qreq->action == 'start' ) {
            $this->call_vm_action($this->user, $this->qreq, $this->qreq->vmid, $this->qreq->action);
        } elseif ($this->qreq->action == 'stop' ) {
            $this->call_vm_action($this->user, $this->qreq, $this->qreq->vmid, $this->qreq->action);
        } elseif ($this->qreq->action == 'reset' ) {
            $this->call_vm_action($this->user, $this->qreq, $this->qreq->vmid, $this->qreq->action);
        } elseif ($this->qreq->action == 'delete' ) {
            $this->call_vm_action($this->user, $this->qreq, $this->qreq->vmid, $this->qreq->action);
        };
    }

    function print() {
        $conf = $this->conf;
        $this->qreq->print_header("Create VM", "createvm");

        echo '<p>Use this page to download review forms, or to upload review forms you’ve already filled out.</p>';
        if (!$this->user->can_clickthrough("review")) {
            echo '<div class="js-clickthrough-container">';
            echo '</div>';
        }


        $this->qreq->print_footer();
    }
    
    function update_vm(Contact $user, Qrequest $qreq, $vmid, $paperid, $rev_access, $aut_access ) {
        
        if ($paperid == '-') {
            $paperid = NULL;
            $rev_access = 'false';
            $aut_access = 'false';
        };
        if (!($db = $user->conf->contactdb())) {
            $db = $user->conf->dblink;
        }
        $result = Dbl::qe($db, "select vmid from UserVMs WHERE vmid = ? and contactId = ? and active = 1;", $vmid, $user->contactId);
        if (!$result->fetch_assoc()) {
            echo '<p>You do not have access to this VM.</p>';
        } else {
            if ($rev_access == 'true') {
                $rev_access = 1;
            } else {
                $rev_access = 0;
            };
            if ($aut_access == 'true') {
                $aut_access = 1;
            } else {
                $aut_access = 0;
            };
            $update = Dbl::qe($db, "UPDATE UserVMs SET paperId=?, reviewerVisible=?, authorVisible=? WHERE vmid = ? and contactId = ? and active = 1;", $paperid, $rev_access, $aut_access, $vmid, $user->contactId);
        };
    }

    function create_vm(Contact $user, Qrequest $qreq) {

        if (!($db = $user->conf->contactdb())) {
            $db = $user->conf->dblink;
        }
        $result = Dbl::qe($db, "select vmid from UserVMs WHERE createhash = ?;", $_GET['createhash']);
        if ($result->fetch_assoc()) {
            $qreq->print_header("VM Already Created", "createvm");

            echo '<p>You already created a VM with this request; You might have reloaded the page. Please go back to the homepage and select the type of VM to create.</p>';

            $qreq->print_footer();
        } else {
            include_once('src/pve_api/pve_functions.php');
            $qreq->print_header("Creating a New VM", "createvm");

	    $people=[];
	    $result = Dbl::qe($db, "select authorInformation from Paper WHERE paperID = ?;", $this->pid);
	    
	    while (($row = $result->fetch_row())) {
	    $strings = preg_split('/\s+/',$row[0]);
	    foreach ($strings as $s)
	    {
		if (str_contains($s, "@"))
		{
			 $resulti = Dbl::qe($db, "select contactId from ContactInfo WHERE email = ?;", $s);
			 while (($rowi = $resulti->fetch_row())) {
			   $id = $rowi[0];
		    	   array_push($people, $id);
		          }
		}
	     }
	   }
	    $result = Dbl::qe($db, "select contactId from PaperReview WHERE paperID = ?;", $this->pid);

            while (($row = $result->fetch_row())) {

	    	  $id = $row[0];
		  array_push($people, $id);
	    }
	    $cmd = "bash firestartvm " . $this->pid . " small ";
	    foreach ($people as $p)
	    {
		$cmd = $cmd . " " . $p;
       	    }
	    echo '<p><textarea id="startvm_log" name="startvm_log" rows="4" cols="50"></textarea><p>';
	    $_SESSION["filename"] = $_GET['createhash'];

	    // count the lines exist in the file
	    $file = 'data/'. $_SESSION["filename"];
	    $result=exec("touch " . $file);
	    $cmd = $cmd . " 2>&1 >> " . $file;
	    $cmd = "echo \"" . $cmd . "\" | at -m now";
	    echo "Cmd $cmd result of touch is $result";
	    $this->get_log($file);
	    $output = shell_exec($cmd);
	    echo "Output " . $output;

            $vmconfig = get_vm_connect_config($this->conf);
            $cluster_load = get_cluster_load($vmconfig, $db);
            if ($cluster_load['stats'][$_GET['vm-types']] < 1) {
                echo '<p><b>Cannot create VM:</b> Currently all VMs of this type are allocated!</p>';
            } else { 
                $new_vm = create_new_vm($_GET['vm-types'], $vmconfig, $db, $cluster_load);
                if ($new_vm) {
                    $result = Dbl::qe($db, "INSERT INTO UserVMs (vmid, vmtype, vmnode, vmcluster, createHash, contactId) VALUES (?, ?, ?, ?, ?, ?);", $new_vm['vmid'], $_GET['vm-types'], $new_vm['vmnode'], $new_vm['vmcluster'], $_GET['createhash'], $user->contactId );
                    if ($_GET['vm-types'] == 'gpu') {
                        echo '<p>We created a new VM for you. Give it a few minutes to get setup. You can then see the IP address to which you can connect via SSH on the main page.<br><br>To connect use "ssh artifacts@'.$new_vm['vmname'].'"<br><br></p>';
                        echo '<br><br>On a GPU the following tools are pre-installed to check that CUDA is working:<br><br><code>cuda-bandwidthTest<br>cuda-deviceQuery<br>cuda-deviceQueryDrv<br>cuda-topologyQuery<br></code><br><br>';
                    } else {
                        echo '<p>We created a new VM for you. Give it a few minutes to get setup. You can then see the IP address to which you can connect via SSH on the main page.<br><br>To connect use "ssh artifacts@'.$new_vm['vmname'].'"<br><br></p>';
    
                    };
                    echo '<table>';
                    echo '<tr>';
                    echo '<td><b>Hostname: </b></td>';
                    echo '<td>'.$new_vm['vmname'].'</td>';
                    echo '</tr>';
                    echo '<tr>';
                    echo '<tr>';
                    echo '<td><b>Username: </b></td>';
                    echo '<td>artifacts</td>';
                    echo '</tr>';
                    echo '<td><b>Password: </b></td>';
                    echo '<td>'.$new_vm['password'].'</td>';
                    echo '</tr>';
                    echo '</table><br><br>';
                    echo '<p><b>Please note down the password!</b> If you forget to do that, you can later request a new password via the VM interface on the main page.</p>';
                } else {
                    echo '<p><b>Something went wrong!</b> Please contact an administrator.</p>';
                }
            }
        }
    }

    function reset_vm_pw(Contact $user, Qrequest $qreq, $vmid) {
        if (!($db = $user->conf->contactdb())) {
            $db = $user->conf->dblink;
        }
        $result = Dbl::qe($db, "SELECT * FROM UserVMs WHERE contactId = ? AND active = 1 AND vmid = ? UNION SELECT UserVMs.* FROM PaperReview,UserVMs WHERE PaperReview.paperId = UserVMs.paperId AND PaperReview.contactId = ? AND UserVMs.reviewerVisible = 1 AND UserVMs.active = 1 AND UserVMs.vmid = ? UNION SELECT UserVMs.* FROM Paper,UserVMs WHERE authorInformation LIKE ".Dbl::utf8ci("'%\t?ls\t%'")." AND Paper.paperId = UserVMs.paperId AND UserVMs.active = 1 AND UserVMs.authorVisible = 1 AND UserVMs.vmid = ? ORDER BY vmid;", $user->contactId, $vmid, $user->contactId, $vmid, $user->email, $vmid);
        if (!$result->fetch_assoc()) {
            $qreq->print_header("Access Denied", "createvm");

            echo '<p>You do not have access to this VM.</p>';

            $qreq->print_footer();
        } else {
            include_once('src/pve_api/pve_functions.php');
            $vmconfig = get_vm_connect_config($this->conf);
            $vmconfig = update_vm_config($vmid, $vmconfig, $db);
            $new_pass = reset_vm_password($vmid, $vmconfig);
            $vm_status = get_vm_status($vmid, $vmconfig);
            if ($new_pass) {
                $qreq->print_header("Password Reset", "createvm");

                echo '<p>We reset the password for your VM. You can now login again with "ssh artifacts@'.$vm_status['data']['name'].'" using the details below:<br><br></p>';
                echo '<table>';
                echo '<tr>';
                echo '<td><b>Hostname: </b></td>';
                echo '<td>'.$vm_status['data']['name'].'</td>';
                echo '</tr>';
                echo '<tr>';
                echo '<tr>';
                echo '<td><b>Username: </b></td>';
                echo '<td>artifacts</td>';
                echo '</tr>';
                echo '<td><b>Password: </b></td>';
                echo '<td>'.$new_pass.'</td>';
                echo '</tr>';
                echo '</table><br><br>';
                echo '<p><b>Please note down the password!</b> If you forget to do that, you can later request a new password via the VM interface on the main page.</p>';
            } else {
                echo '<p><b>Something went wrong!</b> Please contact an administrator.</p>';
            }
        }
    }

    function call_vm_action(Contact $user, Qrequest $qreq, $vmid, $action) {
        if (!($db = $user->conf->contactdb())) {
            $db = $user->conf->dblink;
        }
        if ($action == 'console') {
            $result = Dbl::qe($db, "SELECT * FROM UserVMs WHERE contactId = ? AND active = 1 AND vmid = ? UNION SELECT UserVMs.* FROM PaperReview,UserVMs WHERE PaperReview.paperId = UserVMs.paperId AND PaperReview.contactId = ? AND UserVMs.reviewerVisible = 1 AND UserVMs.active = 1 AND UserVMs.vmid = ? UNION SELECT UserVMs.* FROM Paper,UserVMs WHERE authorInformation LIKE ".Dbl::utf8ci("'%\t?ls\t%'")." AND Paper.paperId = UserVMs.paperId AND UserVMs.active = 1 AND UserVMs.authorVisible = 1 AND UserVMs.vmid = ? ORDER BY vmid;", $user->contactId, $vmid, $user->contactId, $vmid, $user->email, $vmid);
        } else {
            $result = Dbl::qe($db, "select vmid from UserVMs WHERE vmid = ? and contactId = ? and active = 1;", $vmid, $user->contactId);
        };
        if (!$result->fetch_assoc()) {
            $qreq->print_header("Access Denied", "createvm");

            echo '<p>You do not have access to this VM.</p>';

            $qreq->print_footer();
        } else {
            include_once('src/pve_api/pve_functions.php');
	    echo "Starting";
            $vmconfig = get_vm_connect_config($this->conf);
            $vmconfig = update_vm_config($vmid, $vmconfig, $db);
            $vm_status = get_vm_status($vmid, $vmconfig);
            if ($action == 'start') {
                $qreq->print_header("Starting VM", "createvm");
                $action_result = start_vm($vmid, $vmconfig);
                if ($action_result) {
                    echo 'Your VM '.$vm_status['data']['name'].' has been started.';
                } else {
                    echo '<p><b>Something went wrong!</b> Please contact an administrator.</p>';
                }
            } elseif ($action == 'stop') {
                $qreq->print_header("Stopping VM", "createvm");
                $action_result = stop_vm($vmid, $vmconfig);
                if ($action_result) {
                    echo 'Your VM '.$vm_status['data']['name'].' has been stopped.';
                } else {
                    echo '<p><b>Something went wrong!</b> Please contact an administrator.</p>';
                }
            } elseif ($action == 'reset') {
                $qreq->print_header("Resetting VM", "createvm");
                $action_result = reset_vm($vmid, $vmconfig);
                if ($action_result) {
                    echo 'Your VM '.$vm_status['data']['name'].' has been reset.';
                } else {
                    echo '<p><b>Something went wrong!</b> Please contact an administrator.</p>';
                }
            } elseif ($action == 'delete') {
                $qreq->print_header("Removing VM", "createvm");
                $result = Dbl::qe($db, "UPDATE UserVMs SET active = 0 WHERE vmid = ? and contactId = ? AND active = 1;", $vmid, $user->contactId);
                $action_result = delete_vm($vmid, $vmconfig);
                if ($action_result) {
                    echo 'Your VM '.$vm_status['data']['name'].' has been removed.';
                } else {
                    echo '<p><b>Something went wrong!</b> Please contact an administrator.</p>';
                }
            } elseif ($action == 'console') {
                $qreq->print_header("VM Console", "createvm");
                $action_result = get_console_url($vmid, $vmconfig);
                if ($action_result) {
                    print($action_result);
                } else {
                    echo '<p><b>Something went wrong!</b> Please contact an administrator.</p>';
                }
            } else { 
                $qreq->print_header("Unknown Action", "createvm");
                echo '<p>You requested an unknown action.</p>';
                return;
            }
        }
    }
    static function go(Contact $user, Qrequest $qreq) {
        if (!$user->email) {
            $user->escape();

        //} else if (!$user->is_reviewer() || !$user->is_vm_user()) {
        //} else if (!$user->is_reviewer() || !$user->is_user()) {
        //    Multiconference::fail($qreq, 403, ["title" => "Create VM"], "<0>You are not allowed to start a VM for this conference!");
        }

        if ($qreq->post && $qreq->post_empty()) {
            $user->conf->post_missing_msg();
        }
        $op = new StartVm_Page($user, $qreq);

        if ($qreq->post && $qreq->valid_post()) {
            $op->handle_post_request();
        } elseif (array_key_exists('action', $_GET)) {
            if ($_GET['action'] == 'create' && array_key_exists('createhash', $_GET)) {
                $op->create_vm($user, $qreq);
            } elseif ($_GET['action'] == 'resetpw' && array_key_exists('vmid', $_GET)) {
                $op->reset_vm_pw($user, $qreq, $_GET['vmid']);
            } elseif (array_key_exists('action', $_GET) && array_key_exists('vmid', $_GET)) {
                $op->call_vm_action($user, $qreq, $_GET['vmid'], $_GET['action']);
            };
        };
    }
}
