<?php

    $apppath="/home/clauss/git-repos/ondics-gdc-gdcbox/appstore/apps";

    // hier sind alle app gelistet. die zugeh�rige klasse ist <file> ohne endung.
    // sp�ter k�nnen anstelle .inc auch .zip dazukommen f�r mehrere dateien (incl.
    // bilder etc.)
    $applist = array (
        array( 'name'=>'Lufft WS600', 'ver'=>'0.0.1', 'file'=>'lufft_ws600.inc' ),
        array( 'name'=>'Lufft L2P',   'ver'=>'0.0.1', 'file'=>'lufft_l2p.inc' ),        
        array( 'name'=>'AVR Net IO',  'ver'=>'0.0.1', 'file'=>'avrnetio.inc' ),        
        array( 'name'=>'Testdevice',  'ver'=>'0.0.1', 'file'=>'testdevice.inc' )
    );


    switch ($_GET['action']) {
        case 'applist': 
            echo json_encode($applist);
            break;
        case 'download':
            $appfile=$_GET["appfile"];
            // pr�fen, ob app auch in applist ist.
            $i=count($applist);
            while (--$i>=0)
                if ($applist[$i]['file'] == $appfile) break;
            if ($i<0) die("<p>error: app ".$appfile." nicht gefunden<p>");
            // jetzt download starten            
            header("Content-Length: ".filesize($apppath."/".$appfile));
            header('Content-Type: application/x-download');
            header('Content-Disposition: attachment; filename="'.$appfile.'"');
            header('Content-Transfer-Encoding: binary');
            $fp=fopen($apppath."/".$appfile,"rb");
            while($fp && !feof($fp) && (connection_status()==0)) {
                print(fread($fp, filesize($apppath."/".$appfile)));
                flush();
            }
            fclose($fp);
            break;
        default:
            die("<p>error in appstore: unknown command</p>");
    }

?>
