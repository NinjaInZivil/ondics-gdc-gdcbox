<?php

require_once("platforms.inc");

class Device {
    
    // default values, set in subclasses
    public $generic_device_specs = array();  // we need extenal acceess for appstore!
    protected $device_defaults = array();
    protected $device_config_defaults = array();
    // parameters from db
    public $device_values = array(); 
    public $device_config_values = array(); 
    // current status
    private $loaded = false;        // device_values loaded from db?
    public $error = "";             // status of last operation
    public $values = array();       // last values read from device

    function isLoaded() {
        return $this->loaded;
    }
   
    function setDefaultValues() {
        $this->generic_device_specs = array(
            'version'=>'0.0.0',
            'name'=>'',
            'name_long'=>'',
            'description'=>'',
            'url'=>'',
            'appfile'=>'',
            'platforms'=>'ANY',
        );  
        $this->device_defaults = array(
            'name'=>'',
            'description'=>'',
            'location'=>'',
            'interval_min'=>'5',
            'gdc_sid'=>'===***unset***===',
            'generic_device_name'=>'',
            'active'=>'no',
            'gdc_send'=>'no',
        );
        $this->device_config_defaults = array(
            'IP-Address'=>array('value'=>'','attribs'=>''),
            'SysInfo'=>array('value'=>'no','attribs'=>'visible:no'),
            'NumValues'=>array('value'=>'1','attribs'=>'visible:no'),
            '0_Description'=>array('value'=>'','attribs'=>''),
            '0_Formula'=>array('value'=>'$value','attribs'=>'')
        );
    }
    
    function checkDefaultValues() {
        // overwrite generice_device values
        if (!isset($this->generic_device_specs['version'])) die("init error: version missing");
        if (!isset($this->generic_device_specs['name']))    die("init error: name unset");
        if (!isset($this->generic_device_specs['appfile'])) die("init error: appfile unset");
        if (!isset($this->generic_device_specs['platforms'])) die("init error: platforms unset");

        // overwrite device_default values
        $this->device_defaults['name']=$this->generic_device_specs['name'];
        $this->device_defaults['generic_device_name']=$this->generic_device_specs['name'];
        $this->device_defaults['gdc_sid']="***===unset===***";
        
        // check device default values
        if ($this->device_config_defaults['NumValues']['value']<1) die("init error: NumValues<1");
    }    
    
    function createNewGenericDeviceInDB() {
        global $pdo;
        global $testmode;
        echo "<p>Device::createNewGenericDeviceInDB()</p>";
        $values="";
        $fieldnames="";
        foreach ( $this->generic_device_specs as $field => $value) {
            $fieldnames.=($fieldnames==""?"":", ").$field;
            //$values.=($values==""?"":", ").(is_int($value)?$value:("'".$value."'"));
            $values.=($values==""?"":", ")."'".$value."'";
        }
        $fieldnames.=",date_installed";
        $values.=", '".date("Y-m-d H:i:s")."'";
        $sql="INSERT INTO generic_devices (".$fieldnames.") VALUES (".$values.") ";
        if ($testmode) echo "<p>sql=".$sql."</p>";
        $pdo->exec($sql);
    }
    
    function removeGenericDeviceFromDB($generic_device_name) {
        global $apppath,$pdo, $env;
        global $testmode;
        
        // first: delete local files (read filename from db)
        $query = $pdo->prepare("SELECT appfile FROM generic_devices ".
                                "WHERE name='".$generic_device_name."'");
        $query->execute();
        if ($row = $query->fetch()) {
            if ($testmode) echo "<p>removing dir ".$env["apppath"]."/".$row['appfile'].'/</p>';
            shell_exec("cd ".$env["apppath"]." && rm -rf ".$env["apppath"]."/".$row['appfile']);
            unlink($env["apppath"]."/".$row['appfile'].".gz");
        } else {
            echo "<p>error: Device::removeGenericDeviceFromDB!";
            return false;
        }
        // second: remove all devices_values from these devices
        if ($testmode) echo "<p>removing values for all devices to be removed</p>";
        $pdo->exec("DELETE FROM device_values WHERE device_id IN ".
                   "( SELECT id FROM devices WHERE generic_device_name='".$generic_device_name."')");
        // third: remove all devices
        if ($testmode) echo "<p>removing configs for all devices to be removed</p>";
        $pdo->exec("DELETE FROM device_configs WHERE device_id IN ".
                   "( SELECT id FROM devices WHERE generic_device_name='".$generic_device_name."')");
        // fourth: remove generic_device
        if ($testmode) echo "<p>removing devices</p>";
        $pdo->exec("DELETE FROM devices WHERE generic_device_name='".$generic_device_name."'");
        // fifth: remove generic_device
        if ($testmode) echo "<p>removing generic_device</p>";
        $pdo->exec("DELETE FROM generic_devices WHERE name='".$generic_device_name."'");
        
        return true;
    }

    function createNewDeviceInDB() {
        global $pdo;
        global $testmode;

        // first create device
        $values="";
        $fieldnames="";
        foreach ( $this->device_defaults as $field => $value) {
            $fieldnames.=($fieldnames==""?"":", ").$field;
            //$values.=($values==""?"":", ").(is_int($value)?$value:("'".$value."'"));
            $values.=($values==""?"":", ")."'".$value."'";
        }
        $sql="INSERT INTO devices (".$fieldnames.") VALUES (".$values.")";
        $pdo->exec($sql);
        $device_id=$pdo->lastInsertId('id');
        // then create corresonding device_configs        
        foreach ( $this->device_config_defaults as $key => $valueattribs) {
            $value=$valueattribs['value'];
            $attribs=$valueattribs['attribs'];
            $sql="INSERT INTO device_configs (device_id,key,value,attribs) ".
                 "VALUES (".$device_id.",'".$key."','".$value."','".$attribs."')";
            if ($testmode) echo "<p>sql=".$sql."</p>";
            $pdo->exec($sql);
        }
        // then create one entry for all measured values of this device (format is
        // empty value "-" separated by "|")
        $valuesstring="";
        for($i=0; $i<$this->device_config_defaults['NumValues']['value'];$i++)
            $valuesstring.=($valuesstring==""?"":"|")."-";
        $sql="INSERT INTO device_values (device_id,timestamp,valuesstring) ".
             "VALUES (".$device_id.",'".date("Y-m-d H:i:s")."','".$valuesstring."')";
        if ($testmode) echo "<p>sql=".$sql."</p>";
        $pdo->exec($sql);        
        
        return $device_id;
    }

    function loadDeviceFromDB($device_id) {
        global $pdo;
        $this->loaded=false;
        // first get device-values...
        $query = $pdo->prepare("SELECT * FROM devices WHERE id=".$device_id);
        $query->execute();
        $row = $query->fetch(PDO::FETCH_ASSOC);
        if (!$row) return;
        foreach($row as $field => $value)
            $this->device_values[$field]=$value;
        // .. then get device-config-values...
        $query = $pdo->prepare("SELECT * FROM device_configs WHERE device_id=".$device_id.
                               " ORDER BY key ASC");
        $query->execute();
        while( $row = $query->fetch(PDO::FETCH_ASSOC) ) {
            $this->device_config_values[$row['key']]['value']=$row['value'];
            $this->device_config_values[$row['key']]['attribs']=$row['attribs'];
        }
        $this->loaded=true;
    }


    function saveDeviceToDB() {
        global $pdo;
        global $testmode;
        if (!$this->loaded) return;
        $sqlfields="";
        foreach ($this->device_values as $key => $value) {
            switch ($key) {
                case 'generic_device_name': break;
                case 'id': break;
                default: $sqlfields.=($sqlfields==""?"":", ").$key."='".$value."'";
            }
        }
        if ($testmode) echo "<p>".$sqlfields."</p>";
        $pdo->exec("UPDATE devices SET ".$sqlfields." WHERE id=".$this->device_values['id']);
        foreach ($this->device_config_values as $key => $valueattribs)  {
            if ($key!='NumValues')
                $pdo->exec("UPDATE device_configs SET value='".$valueattribs['value']."' ".
                     "WHERE device_id=".$this->device_values['id']." AND key='".$key."'");
        }
    }

    function removeDeviceFromDB($id) {
        global $pdo;
        global $testmode;
        // first: remove from device_configs
        if ($testmode) echo "<p>removing device configs... </p>";
        $pdo->exec("DELETE FROM device_configs WHERE device_id=".$id);
        // second: remove device
        if ($testmode) echo "<p>removing device</p>";
        $pdo->exec("DELETE FROM devices WHERE id=".$id);
        // third: remove values
        if ($testmode) echo "<p>removing values</p>";
        $pdo->exec("DELETE FROM device_values WHERE device_id=".$id);
        
    }
    
    // for a single value do some postprocessing: the value
    // is calculated with "n_Formula"-field
    function getValuesFromDevicePostprocessingValue($numValue,$value) {
        $error="";
        $phpcode='$value=('.$this->device_config_values[$numValue."_Formula"]['value'].');';
        $eval_ok=eval($phpcode);
        if ($eval_ok===FALSE)
            $error=" eval failed for value [$$numValue].";
        $this->values[$numValue]=$value;
        return $error;
    }
    
    function saveValuesToDB() {
        global $pdo,$env;
        global $testmode;
        $error="";
        $valuesstring=implode("|",$this->values);
        $sql="UPDATE device_values SET timestamp='".date("Y-m-d H:i:s")."',".
                     "valuesstring='".$valuesstring."' ".
                     "WHERE device_id=".$this->device_values['id'];
        
        //if ($testmode) echo "<p>sql=".$sql."</p>";
        
        // write sql to temp-file for deferred execution
        $mypid=getmypid();
        $tempfile=$env["tmp_dir"]."/gdcbox_cronjob.sql.$mypid";
        file_put_contents($tempfile,$sql);
        
        // now check, if there are some sql's to execute
        // -> but only execute them, if my pid is the biggest of
        // all currently running gdcbox_cronjob processes!
        // (to avoid database locking)
        $execute_sql=true;
        $procs_running=shell_exec("ps ax| grep ".$env["cronjob_script"]." | grep -v grep | grep -v $mypid");
        //echo "-------procs_running nacher (ohne $mypid):---------\n$procs_running\n---------\n";
        if ($procs_running!="") {
            $procs_array=explode("\n",$procs_running);
            // check if my process-id is minimum
            foreach ($procs_array as $process) {
                if (strlen(trim($process))) {
                    $process_parts=explode(" ",trim($process));
                    //echo "pid checked=$process_parts[0]\n";
                    if (is_numeric($process_parts[0]) && $process_parts[0]>$mypid) {
                        //echo "there is a bigger pid ($process_parts[0]) than mine ($mypid) running. breaking.\n";
                        $execute_sql=false;
                        break;
                    }
                }
            }
        }
        if ($execute_sql) {
            echo "my pid ($mypid) is the biggest pid. winning :-) now writing all sql's to db.\n";
            $count=0;
            foreach(glob($env["tmp_dir"]."/gdcbox_cronjob.sql.*") as $sqlfile) {     
                //echo "sqlfile executed: $sqlfile ";
                $sql=file_get_contents($sqlfile);
                //echo "[$sql] \n";
                $pdo->exec($sql);
                // remove file after sql execution
                unlink($sqlfile);
                $count++;
            }
            echo "ready: written $count sql's to database\n";
        } else {
            echo "ops, parallel running update. i'm loosing :-(\n";
        } 
        
/*  this code does'nt work. database does never get unlocked again :-( 
        // start transaction to avoid concurrent access in database
        // if there is an open db-update, then wait for 0,25 seconds and try again!
        $waiting = true; 
        while ($waiting) {
            $pdo->setAttribute(PDO::ATTR_TIMEOUT, 1);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            try {
                $pdo->exec("BEGIN EXCLUSIVE TRANSACTION");
                $pdo->exec($sql);
                echo "saveValuesToDB::db updated. waiting 0s\n"; sleep(0);
                $pdo->exec("COMMIT");
                $waiting = false;
            } catch(PDOException $e1) {
                $errorinfo=$pdo->errorInfo();
                echo "errorInfo=[".$errorinfo[2]."]\n";
                echo "saveValuesToDB::exception1 ".$e1->getMessage().".\n";
                if (stripos($e1->getMessage(), 'database is locked') !== false) {
                    echo "saveValuesToDB::db locked.waiting for 5s\n";
                    sleep(5);
                    
                    try {
                        echo "saveValuesToDB::trying to END TRANSATION\n";
                        $pdo->exec("COMMIT");
                    } catch(PDOException $e2) {
                        echo "saveValuesToDB::exception2 - [".$e2->getMessage()."] - ???!\n";
                    }
                    
                    $pdo=new PDO('sqlite:'.$env["database"]);
                }
            }
        }
*/
    }

    // function is static for performance reasons: we don't need
    // an instantiated object to get the values from db!
    static function loadValuesFromDB($device_id, &$values, &$timestamp) {
        global $pdo;
        $query = $pdo->prepare("SELECT timestamp,valuesstring FROM device_values ".
                               "WHERE device_id=".$device_id);
        $query->execute();
        $row = $query->fetch();
        if (!$row) { return "no values found in db for device $device_id";}
        $values=explode("|",$row['valuesstring']);
        $timestamp=$row['timestamp'];
  }

   

}

?>