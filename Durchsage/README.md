# Durchsage
Das Durchsage Modul bietet die Möglichkeit von AWS Polly erzeugte Audio Daten über Sonos oder unter Windows den Media Player wiederzugeben.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Von AWS Polly erzeugte Audiodaten über ein Sonos Player oder den "Symcon" Media Player wiedergeben
* Lautstärke der Durchsage ist einstellbar
* Durchsage bei Änderung der Text Variable. Alternativ über angebotene Funktion

### 2. Voraussetzungen

- IP-Symcon ab Version 5.4

### 3. Software-Installation

* Über den Module Store das 'Durchsage'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen: `https://github.com/symcon/Durchsage`

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'Durchsage'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name                    | Beschreibung
----------------------- | ------------------
Text-to-Speeach Instanz | AWS Polly Instanz, welche zum erstellen der Durchsage genutzt werden soll. Als Ausgabeformat muss mp3 ausgewählt sein. <br>Sonos kann nur mit den Abtastraten 16000 Hz, 22050 Hz, 24000 Hz, 32000 Hz, 44100 Hz, 48000 Hz genutzt werden
Ausgabegerät            | Typ der Ausgabeinstanz
Symcon IP               | Die IP-Addresse, unter welcher der Sonos Player IP-Symcon erreicht.
Sonos/Media Player      | Instanz, über welche die Durchsage widergegeben wird
Lautstärke              | Sonos: Die Lautstärkenänderung der Durchsage (0 &rarr; keine Änderung , 50 &rarr; 50, +10 &rarr; um 10 lauter) <br>Media Player: die Lautstärke der Durchsage in Prozent (wird nicht zurückgesetzt)


### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Name     | Typ     | Beschreibung
-------- | ------- | ------------
Text     | String  | Text welcher für die Durchsage genutzt wird. Bei Aktualisierung der Variable wird der Inhalt wiedergegeben.

#### Profile

Es werden keine zusätzlichen Profile hinzugefügt

### 6. WebFront

Die Funktionalität, die das Modul im WebFront bietet.

### 7. PHP-Befehlsreferenz

`void DS_Play(integer $InstanzID, string $Text);`
Spielt den Text als Durchsage ab.

Beispiel:
`DS_Play(12345, "Dies ist ein Text");`
