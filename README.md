#GDCBox V2

GDCBox helps you to
- collect data from your network and connected hardware,
- store the latest values in a local database,
- make data accesible via an api and
- transmit data to the GDC Global Data Cloud Storage

The GDCBox V2 was completely redesigned and rewritten
in PHP. GDCBox mainly consists of four parts:
- the GDCBox user interface
- the cronjobs collecting data
- the api and
- access to the AppStore to dynamiccaly expand your GDCBox

And for the developers there is a local AppStore included to
test their Apps.

GDCBox provides hardware specific Apps, e.g. for Raspberry
PI. get more information about [GDCBox on Raspberry PI at http://pi-io.com](http://pi-io.com).

GDCBox can easily expanded by building your own Apps.


(C) Ondics GmbH, 2012


##TODOs
- more Apps (Web-Grab-Apps!)
- complete App for WS500 
- control display devices and actors
- version vontrol of apps
- improve AppStore navigation


##AppStore

> Der AppStore liegt unter ./www/appstore und ist ein REST/JSON-Server,
> der die Befehle 'applist' (Liste mit verf�gbaren Apps) und 'download' (Eine App downloaden) kennt.
> Eine App ist der Code zum Anschluss eines Devices an die GDCBox. 
> Eine App besteht aus der Datei <app>.inc und liegt im Verzeichnis ./www/appstore/apps/<appname>

##GDCBox
The GDCBox modules are
- the Web-UI (./www/cgi-bin/gdcbox.php)
- the cronjob-script (./www/gdcbox/gdcbox_cronjob.php).
- the api (./www/cgi-bin/gdcbox-api.php)
GDCBox uses a local SQLite (Version 3)

##Develop your own Apps
>Die App-Architektur ist objektorientiert aufgebaut. Damit kann sich das Erstellen einer neuen
>App auf das Wesentliche beschr�nken. Ist eigentich ganz leicht!
>Eine neue App wird am Besten wie folgt erstellt:
>- Kopieren einer bestehenden App aus ./appstore/apps
>- Anpassen der neuen App
>Fertig!

For more about how to build your own Apps see [here](http://pi-io.com).


