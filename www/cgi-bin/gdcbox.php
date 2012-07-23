<!--#!/usr/bin/php-cgi-->
<?php
    /*
    //  GDCBox Frontend
    //  (C) Ondics,2012
    */

    $gdcbox_version="0.1";
    $testmode=FALSE; // einblenden von "Test" in Fu�zeile und "logout" bei Info.
    $testmsg="";    // wird eingeblendet im footer

    // basepath and baseurl depend on machine (default is gdcbox);
    $basepath="/www-wifunbox";  
    $baseurl="";
    $machine="srv1";
    if ($machine=="srv1") {
        // ...for use on srv1.ondics.de
        $basepath="/home/clauss/git-repos/ondics-gdc-gdcbox/www";
        $baseurl="/gdcbox";
    }
    // now the real paths
    $database=$basepath.'/gdcbox/gdcbox-db.sqlite';
    $classinc=$basepath.'/gdcbox/classes.inc';
    $myurl=$baseurl."/cgi-bin/gdcbox.php";
    
    // appstore access
    $appstore_url="http://srv1.ondics.de/gdcbox/appstore/appstore.php";
    $appstore_user="appstore";
    $appstore_pass="appstore";
    $apppath=$basepath."/gdcbox/apps";
    
    // cronjob-script
    $cronjob_script='gdcbox_cronjob.php';
    $cronjob_script_path=$basepath.'/gdcbox/'.$cronjob_script;
    // cronjob logging
    $cronjob_logfile="/tmp/gdcbox_cronjob.log";
    

    // datenbankzugriff herstellen
    if (! ($pdo=new PDO('sqlite:'.$database)) ) {
        echo "<html><body>Fehler: DB-Zugriff</body></html>";
        exit;
    } 


    // eine neue session beginnen bzw. mit alter (serverseitiger) weiterarbeiten
    //session_start();

    function getValueFromURLSave($key) {
        return isset($_GET[$key])?htmlentities($_GET[$key],ENT_QUOTES):''; 
    }


    require_once($classinc);


    // page selector
    $action=isset($_GET['action'])?htmlentities($_GET['action'],ENT_QUOTES):'main';

    echo '<!DOCTYPE html PUBLIC "-//WAPFORUM//DTD XHTML Mobile 1.0//EN" "http://www.wapforum.org/DTD/xhtml-mobile10.dtd">';
    echo '<html xmlns="http://www.w3.org/1999/xhtml" lang="de" xml:lang="de">';
    echo '<head>';
    echo '<title>GDCBox</title>';
    echo '</head>';
    echo '<body>';
          
    // fuer navigation im footer
    $zurueckaction='.';

            
    if ($action=='main') {

        // Date and time are unset if system is powered on
        if (strtotime(date("Y-m-d")) < strtotime("2012-05-01") ) {
            echo '<p>Date & Time seem not to is not be set. </p>';
            echo '<p>Set Date & Time now:</p>';
            echo '<form action="'.$myurl.'" method="get">';
            echo '<table><tr><td>Date</td><td>Day <input name="day" size="2"> '.
                 'Month <input name="month" size="2"> '.
                 'Year <input name="year" value="2012" size="4"></td></tr>';
            echo '<tr><td>Time</td><td>Hour <input name="hour" size="2"> '.
                 'Minute <input name="min" size="2"></td></tr>';
            echo '<tr><td><input type="hidden" name="action" value="setdatetime">';
            echo '<input type="submit" value=" Datum und Zeit setzen "></td></tr></table>';
            echo "</form>\n";
        }

        // display all devices installed
        echo '<h2>Installed Devices</h2>';
        echo '<p>Devices available on this GDCBox:</p>';
        $query = $pdo->prepare("SELECT * FROM devices ORDER BY name ASC");
        $query->execute();
        $row = $query->fetch();
        echo '<p>';
        echo '<table border="0" cellspacing="10">';
        if (!$row) {
            echo '<tr><p>No devices installed<p>';
        } else {
            // layouting: 3 apps in one row (width 130 px)
            $appcount=1;
            $columns=3;
            do {
                if ($appcount % $columns == 1) echo '<tr>';
                echo '<td bgcolor="#ddd"><table border="0" cellspacing="10" width="130" >';
                echo '<tr><td style="font-size:small">'.$row['description'].'</td></tr>';
                echo '<tr><td>';
                // configure
                echo '<form action="'.$myurl.'" method="get">';
                echo '<input type="hidden" name="action" value="configuredevice">';
                echo '<input type="hidden" name="device_id" value="'.$row['id'].'">';
                echo '<input type="submit" value=" Configure... "></form>';
                echo '</td></tr>';
                echo '<tr><td align="center" style="font-weight:bold">'.$row['name'].'</td></tr>';
                echo '<tr><td style="font-size:x-small">'.
                     '<a href="'.$myurl.'?action=removedevice&device_id='.$row['id'].'">'.
                     'Remove Device</a></td></tr>';
                echo '</table></td>';
                if ($appcount % $columns == 0) echo '</tr>';
                $appcount++;
                
            }  while ($row = $query->fetch() );
            if ($appcount % $columns != 0) echo '</tr>';
            echo "</table>\n";
        }
        //echo '</table>';
        echo '</p>';
        echo '<p><a href="'.$myurl.'?action=makenewdevice">Make new Device</a></p>';
        $query = $pdo->prepare("SELECT count(*) FROM generic_devices");
        $query->execute();
        $row = $query->fetch();
        switch ($row[0]) {
            case 0: echo '<p>Currently there is <b>no app</b> installed.<br>'; break;
            case 1: echo '<p>Currently there is <b>one app</b> installed.<br>'; break;
            default: echo '<p>Currently there are <b>'.$row[0].' apps</b> installed. '; 
        }
        echo 'Goto <a href="'.$myurl.'?action=appstore">GDCBox AppStore</a> to manage your Apps.</p>';

        echo '<h2>Operation Mode</h2>';
        $cronjobs=(int)shell_exec('crontab -l| grep gdcbox |wc -l');
        echo '<p>GDCBox is <span style="color:'.($cronjobs>0?'green">':'red">not').' running.</span></p>';
        // start/stop button
        echo '<p><form action="'.$myurl.'" method="get">';
        echo '<input type="hidden" name="action" value="gdcbox_'.($cronjobs>0?'stop':'start').'">';
        echo '<input type="submit" value=" '.($cronjobs>0?'Stop':'Start').' "></form></p>';
        
        echo '<h2>GDCBox Administration</h2>';

        echo '<p><table border="1">';
        echo '<tr><td>Current Date & Time</td><td> <b>'.date("Y-m-d H:i:s").'</b></td></tr>';
        $data = shell_exec('uptime');
        $uptime = explode(' up ', $data);
        $uptime = explode(',', $uptime[1]);
        $uptime = $uptime[0].', '.$uptime[1];
        echo '<tr><td>Uptime</td><td> <b>'.$uptime.'</b></td></tr>';
        echo '<tr><td>IP-Address</td><td><b>'.$_SERVER['SERVER_ADDR'].'</b></td></tr>';
        echo '<tr><td>Current User</td><td><b>'.shell_exec('whoami').'</b></td></tr>';

        echo "</table></p>\n";

        echo '<h2>System Testing</h2>';
        echo '<ul>';
        echo '<li><a href="'.$myurl.'?action=test-dbdump">Show Database Contents</a></li>';
        echo '<li><a href="'.$myurl.'?action=test-cronjoblogfile">Show Cronjobs-Logfile</a></li>';
        echo '</ul>';
        


    } else if ($action=='setdatetime') {

        echo "<h2>Set Date&Time</h2>\n";
        
        $year=$_GET['year'];
        $month=$_GET['month'];
        $day=$_GET['day'];
        $hour=$_GET['hour'];
        $min=$_GET['min'];
        if (is_numeric($year) && is_numeric($month) && is_numeric($day)
            && is_numeric($hour) && is_numeric($min)
            && $year>=2012 && $month>0 && $month<=12  && $day>0 && $day<=31
            && $hour>=0 && $hour<=23 && $min>=0 && $min<=59) {
            $newdatetime=sprintf("%04d.%02d.%02d-%02d:%02d:00",$year,$month,$day,$hour,$min);
            system("date ".$newdatetime);
            echo "<p>Date and Time set to ".$newdatetime."</p>";
        } else {
            echo "<p>error: Date or Time invalid</p>";   
        }
        
    } else if ($action=='appstore') {

        echo "<h2>GDCBox AppStore</h2>\n";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $appstore_url."?action=applist");
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY); 
        curl_setopt($ch, CURLOPT_USERPWD, $appstore_user.':'.$appstore_pass); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $return = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $applist=array();
        if ($httpCode=="200") {
            $applist=json_decode($return,true);
            /*
             echo "\n<pre><code>\n";
            echo var_dump($applist);
            echo "\n</code></pre>\n";
            */
            echo '<table border="1">';
            echo '<tr><th>App</th><th>Version</th><th>Actions</th></tr>';
            foreach ($applist as $app ) {
                echo '<tr><td>'.$app['name'].'</td><td>'.$app['ver'].'</td>';
                $query = $pdo->prepare("SELECT name,name_long FROM generic_devices ".
                                       "WHERE name='".$app['name']."'");
                $query->execute();
                $row = $query->fetch();
                // is app already installed?
                if ($row) {  
                    // yes: add action "remove"
                    echo '<td><form action="'.$myurl.'" method="get">';
                    echo '<input type="hidden" name="action" value="appstore_appremove">';
                    echo '<input type="hidden" name="name" value="'.$app['name'].'">';
                    echo '<input type="hidden" name="name_long" value="'.$row['name_long'].'">';
                    echo '<input type="submit" value=" Remove... "></form></td></tr>';
                } else {
                    // no: add action "download&install"
                    echo '<td><form action="'.$myurl.'" method="get">';
                    echo '<input type="hidden" name="action" value="appstore_appinstall">';
                    echo '<input type="hidden" name="name" value="'.$app['name'].'">';
                    echo '<input type="hidden" name="file" value="'.$app['file'].'">';
                    echo '<input type="submit" value=" Download & Install "></form></td></tr>';                
                }
            }
            echo '</table>';
        }

    } else if ($action=='appstore_appinstall') {

        echo "<h2>GDCBox AppStore - Installation</h2>\n";
        
        $name=isset($_GET['name'])?htmlentities($_GET['name'],ENT_QUOTES):'';
        $file=isset($_GET['file'])?htmlentities($_GET['file'],ENT_QUOTES):'';
        if ($name=="" || $file=="") {
            echo "<p>error: name or file app is missing</p>";
        } else {
            echo "<p>Downloading App <b>".$file."</b> ... ";
            $appfile=$apppath."/".$file;
            $fp = fopen($appfile, "wb");
            if (!$fp) {
                echo "<p>error: fileopen failed for '".$appfile."'</p>";
            } else {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $appstore_url."?action=download&appfile=".$file);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY); 
                curl_setopt($ch, CURLOPT_USERPWD, $appstore_user.':'.$appstore_pass); 
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                $httpBody=curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($httpCode=="200") {
                    fwrite($fp,$httpBody);
                    fclose($fp);
                    echo "done (".strlen($httpBody)." Bytes)</p>";
                    echo "<p>Installing App <b>".$name."</b> from ".$appfile." ... </p>";
                    require_once($appfile);
                    // classname ist filename ohne endung!
                    $classname=substr($file,0,strpos($file,"."));
                    // jetzt object erstellen...
                    $device = new $classname();
                    // ... und in db als generic_device speichern!
                    $device->setDefaultValues();
                    $device->createNewGenericDeviceInDB();
                    echo "done</p>";
                    unset($device);
                } else {
                    fclose($fp);
                    echo "<p>error: downloading failed</p>";
                }
                
            }
        }

    } else if ($action=='appstore_appremove') {

        $name=isset($_GET['name'])?htmlentities($_GET['name'],ENT_QUOTES):'';
        $name_long=isset($_GET['name_long'])?htmlentities($_GET['name_long'],ENT_QUOTES):'';

        echo "<h2>GDCBox AppStore - Remove installed App</h2>\n";
        echo "<p>When removing this App, all configured devices are removed, too.</p>";
        echo "<p>Do you really want to remove the App '".$name_long."'?</p>";
        
        echo '<td><form action="'.$myurl.'" method="get">';
        echo '<input type="hidden" name="action" value="appstore_appremove_ok">';
        echo '<input type="hidden" name="name" value="'.$name.'">';
        echo '<input type="hidden" name="name_long" value="'.$name_long.'">';
        echo '<input type="submit" value=" Yep, please! "></form></td></tr>';

    } else if ($action=='appstore_appremove_ok') {

        $name=isset($_GET['name'])?htmlentities($_GET['name'],ENT_QUOTES):'';
        $name_long=isset($_GET['name_long'])?htmlentities($_GET['name_long'],ENT_QUOTES):'';

        echo "<h2>GDCBox AppStore - Remove installed App</h2>\n";
        echo "<p>Removing App '".$name_long."' from your GDCBox now...</p>";
        $device=new Device();
        $device->removeGenericDeviceFromDB($name);
        echo "<p>...done. App is removed";

    } else if ($action=='makenewdevice') {

        echo '<h2>Make new Device</h2>';
        echo '<p>Device Apps available on this GDCBox:</p>';

        $query = $pdo->prepare("SELECT * FROM generic_devices ORDER BY name ASC");
        $query->execute();
        $row = $query->fetch();
        if (!$row) {
            echo '<p>No Device Apps available<p>';
        } else {
            echo '<table border="1">';
            echo '<tr><th>Device</th><th>Description</th><th>Version</th><th>Actions</th></tr>';
            do {
                echo '<tr><td>'.$row['name'].'</td>';
                echo '<td>'.$row['name_long'].'<br>'.$row['description'];
                if ($row['url']!='') 
                    echo '<br><a href="'.$row['url'].'">'.$row['url'].'</a>';
                echo '<td>'.$row['version'].'</td>';
                
                // Make new Device
                echo '<td><form action="'.$myurl.'" method="get">';
                echo '<input type="hidden" name="action" value="makenewdevice_ok">';
                echo '<input type="hidden" name="name" value="'.$row['name'].'">';
                echo '<input type="hidden" name="name_long" value="'.$row['name_long'].'">';
                echo '<input type="hidden" name="appfile" value="'.$row['appfile'].'">';
                echo '<input type="submit" value=" Create a new '.$row['name'].' Device "></form></td>';                
                echo '</tr>';
                
            }  while ($row = $query->fetch() );
            echo "</table>\n";
        }
        echo '<p>Device not found? Check the <a href="'.$myurl.'?action=appstore">GDCBox AppStore</a></p>';

    } else if ($action=='makenewdevice_ok') {

        $name=isset($_GET['name'])?htmlentities($_GET['name'],ENT_QUOTES):'';
        $name_long=isset($_GET['name_long'])?htmlentities($_GET['name_long'],ENT_QUOTES):'';
        $appfile=isset($_GET['appfile'])?htmlentities($_GET['appfile'],ENT_QUOTES):'';

        echo '<h2>Make new Device</h2>';
        echo '<p>Create a new Device of type <b>'.$name_long.'</b> ...</p>';

        // get appfile
        require_once($apppath."/".$appfile);
        // classname ist filename ohne endung!
        $classname=substr($appfile,0,strpos($appfile,"."));
        // jetzt object erstellen...
        $device = new $classname();
        $device->setDefaultValues();
        // ... und in db als generic_device speichern!
        $id=$device->createNewDeviceInDB();
        echo "done</p>";
        
        echo '<p>...done. Device can be <a href="'.$myurl.
             '?action=configuredevice&device_id='.$id.'">configured</a> now.</p>';
             
        unset($device);


    } else if ($action=='configuredevice') {

        echo '<h2>Configure Device</h2>';

        $device_id=getValueFromURLSave('device_id');
        
        $device=new Device();
        $device->loadDeviceFromDB($device_id);
        // show device params
        echo '<form action="'.$myurl.'" method="get">';
        echo '<input type="hidden" name="action" value="configuredevice_ok">';
        echo '<input type="hidden" name="device_id" value="'.$device_id.'">';
        echo '<table border="1">';
        echo '<tr><th>Parameter</th><th>Value</th></tr>';
        foreach ($device->device_values as $key => $value) {
            echo "<tr>";
            switch ($key) {
                case 'id':
                    echo '<td>'.$key.'</td><td>'.$value.'</td>';break;
                case 'generic_device_name':
                    echo '<td>App</td><td><a href="xxx">'.$value.'</a></td>';break;
                default:
                    echo '<td>'.$key.'</td><td><input type="text" size="50" name="'.$key.
                         '" value="'.$value.'"></td>';
            }
            echo "</tr>\n";
        }
        
        // show device_configs
        foreach ( $device->device_config_values as $key => $value) {
            switch ($key) {
                case 'NumValues': break;
                default: 
                    echo '<tr><td>'.$key.'</td><td><input type="text" size="50" name="'.
                         $key.'" value="'.$value.'"></td></tr>';
            }
            echo "\n";    
        }
        echo "</table>";
        echo '<p><input type="submit" value=" Save Changes "></form></p>';


    } else if ($action=='configuredevice_ok') {

        echo '<h2>Configure Device</h2>';
        $device_id=getValueFromURLSave('device_id');
        $device=new Device();
        $device->loadDeviceFromDB($device_id);
        //echo "<p>vorher:</p>"; var_dump($device->device_values); echo "<p></p>";
        foreach ($device->device_values as $key => $value ) 
            if ($key!='id' && $key!='generic_device_name')
                $device->device_values[$key]=html_entity_decode(getValueFromURLSave($key));
        //echo "<p>nacher:</p>"; var_dump($device->device_values); echo "<p></p>";
        //echo "<p>vorher:</p>"; var_dump($device->device_config_values); echo "<p></p>";
        foreach ($device->device_config_values as $key => $value) 
            if ($key!='NumValues')
                $device->device_config_values[$key]=html_entity_decode(getValueFromURLSave($key));
        //echo "<p>nacher:</p>"; var_dump($device->device_config_values); echo "<p></p>";
        

        $device->saveDeviceToDB();
        echo '<p>Saved.</p>';
        

    } else if ($action=='removedevice') {

        echo '<h2>Remove Device</h2>';

        $device_id=getValueFromURLSave('device_id');
        
        $device=new Device();
        $device->removeDeviceFromDB($device_id);
        echo '<p>Device removed.</p>';

    } else if ($action=='gdcbox_start') {

        echo '<h2>Starting GDCBox</h2>';

        $query = $pdo->prepare("SELECT id,interval_min,name FROM devices ORDER BY id ASC");
        $query->execute();
        // to reduce load, each minute only one processes is started 
        $start_min=0;
        $tmpfile='/tmp/gdcbox.'.getmypid();
        shell_exec('crontab -l > '.$tmpfile);
        $line_end=" # gdcbox\n"; 
        file_put_contents($tmpfile,"################".$line_end,FILE_APPEND);
        file_put_contents($tmpfile,"gdcbox_logfile=".$cronjob_logfile.$line_end,FILE_APPEND);
        file_put_contents($tmpfile,"gdcbox_cronjob=".$cronjob_script_path.$line_end,FILE_APPEND);
        while ( $row = $query->fetch() ) {
            $cronjob='*/'.$row['interval_min'].' * * * * $gdcbox_cronjob '.$row['id'].
                     ' >> $gdcbox_logfile 2>&1 # '.$row['name'].$line_end;
            echo '<p>adding cronjob ['.$cronjob.']</p>';
            file_put_contents($tmpfile,$cronjob,FILE_APPEND);
            $start_min++;
        }

        if ($start_min) {
            shell_exec('crontab '.$tmpfile);
            unlink($tmpfile);
            echo "<p>GDCBox started ($start_min Device Processes running)</p>";
        } else {
            echo '<p>Nothing to be started. Install devices first.<p>';
        }

    } else if ($action=='gdcbox_stop') {

        echo '<h2>Stopping GDCBox</h2>';

        $cronjobs=(int)shell_exec('crontab -l|grep gdcbox |wc -l');
        echo "<p>Currently $cronjobs device".($cronjobs!=1?'s are':'is').' running</p>';
        echo "<p>Stopping all devices ...";
        $tmpfile='/tmp/gdcbox.'.getmypid();
        shell_exec('crontab -l|grep -v gdcbox > '.$tmpfile);
        shell_exec('crontab '.$tmpfile);
        unlink($tmpfile);
        echo 'done</p>';
        
        echo "<p>GDCBox stopped.</p>";

    } else if ($action=='gdcbox-info') {

        echo '<h3>About GDCBox</h3>';
        echo '<p>The GDCBox ... </p>';
        echo '<p>Questions? Please check the <a href=".$baseurl."/gdcbox/faq.html>GDCBox FAQ</a></p>';
        echo '<p>GDCBox Version: '.$gdcbox_version.'</p>';
        echo '<p">The GDCBox is a Product of Ondics GmbH</p>';

    } else if ($action=='test-dbdump') {

        function dbdump($dbtable) {
            global $pdo;
            echo '<p>'.$dbtable.'</p>';
            $query = $pdo->prepare("select * from " . $dbtable);
            $query->execute();
            if ($row=$query->fetch(PDO::FETCH_ASSOC)) {
                echo '<table border="1"><tr>';
                foreach ($row as $field => $value) echo '<th>'.$field.'</th>';
                echo '</tr>';
                do {
                    echo '<tr>';
                    foreach ($row as $field => $value) echo '<td>'.$value.'</td>';
                    echo '</tr>';
                } while ($row=$query->fetch(PDO::FETCH_ASSOC));
                echo '</table>';
            } else
                echo "<p>--- leer ---</p>";
        }

        echo '<h3>DB-Dump</h3>';
        dbdump("generic_devices");
        dbdump("devices");
        dbdump("device_configs");
    
    } else if ($action=='test-cronjoblogfile') {
        echo '<h3>Cronjob-Logfile</h3>';
        echo "<p>Last 20 Lines of ".$cronjob_logfile."</p>\n";
        echo "<pre><code>";
        echo shell_exec("tail -n 20 ". $cronjob_logfile);
        echo "</code></pre>";
    }

    $_SESSION['lastaction']=$action;  // speichern, um bei reloads dopplung zu verhindern
    
    if ( $action != 'main' )
        echo '<p><a href="'.$myurl.'?action=main">Back</a></p>';
    

    // db-connectivity schlie�en
    unset($pdo);
    
    echo '<table width="80%" border="0" style="font-size:smaller">';
    echo '<tr><td colspan="3"><hr></td></tr>';
    echo '<tr><td align="left">'.date("H:i").'</td>';
    echo '<td align="center">'.($testmode?'Test':'').'</td>';
    echo '<td align="right"><a href="'.$myurl.'?action=gdcbox-info">About GDCBox</a></td></tr>';
    echo (($testmode && $testmsg)?'<tr><td colspan="3" align="left">'.$testmsg.'</td></tr>':'');    
    echo '</table>';
?>
</body>
</html>
