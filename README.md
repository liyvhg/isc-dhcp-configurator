## isc-dhcp-configurator ##

Web based configuration file editor for ISC DHCP server.

The main purpose of this project was to teach myself a bit about Angular JS. It '''does''' work,
however, so if you find it useful them I'm happy, but I'm unlikely to provide
any sort of support for it unless it's something quick and easy to advise upon or fix.

This project is incomplete and does not yet function.

### Installation ###

Place all files in a directory writable and accessible by your web server and browse to the corresponding URL. You require
an Internet connection as libraries are downloaded from various CDNs.

I've committed my own data file to the repository so you can see a good real-world example. If you want to start
from scratch, however, simply delete the data.sqlite file and reload; a new file will be created (your web server
software will require write permission on the directory).

### Requirements ###

* PHP 5.3.
* PHP SQLite3 extension. Installation of this varies from system to system.
* Web server requires write access to the installation directory.

### Things I might do to improve it if I can be arsed ###

* Fix my function which is supposed to remove un-used lines from parameters and reservations.
* Add configuration keyword suggestions.

### Plan ###
* add bootfile option (for docsis network)
* set some options READONLY
