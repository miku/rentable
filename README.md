
    ____ ____ _  _ ___ ____ ___  _    ____ 
    |__/ |___ |\ |  |  |__| |__] |    |___ 
    |  \ |___ | \|  |  |  | |__] |___ |___ 

is the CC5 ([Coding Contest #5](http://www.coding-contest.de/)) submission for PHP category by Martin Czygan.

* Google Docs Key: `0AhlhQsr_yVQ4dFFOTVEtX3hKU0Q2azcyczNUckNybVE`
* Google Docs Document: https://docs.google.com/spreadsheet/ccc?key=0AhlhQsr_yVQ4dFFOTVEtX3hKU0Q2azcyczNUckNybVE#gid=0
* Github Repository: https://github.com/miku/rentable
* Screenshots: http://imgur.com/a/Ta067

INSTALL
=======

To bootstrap PHP dependencies (using composer):

    $ curl -sS https://getcomposer.org/installer | php
    $ ./composer.phar install

To download the JS dependencies (with bower - https://github.com/bower/bower):

    $ bower install jquery modernizr knockout

Allow apache to write to the cache, logs and the database (sqlite3):

    $ mkdir -p logs cache db
    $ chmod 777 logs cache db

On Mac OS X you can link to your webservers document root like this:

    $ ln -s `pwd` /Library/WebServer/Documents/

Then you can access the site on http://localhost/rentable/.

Syncing with Google Docs
========================

To create the database (sqlite3 in `db/rentable.db`), click on the link
that says [Sync with Google now](http://localhost/rentable/sync). After
that the objects should be in the database and should be visible on the map.

Navigating
==========

To navigate the map, enter some location into the text field.

Availibility
============

To see whether an object is available or not, click on the marker on the map.
It will tell you the availability for 

* today,
* tomorrow and
* the next 10 days

Missing features
================

* no filter for available objects
* no live reservations

Technology Stack
================

* Slim PHP web framework
* Redbean ORM
* Leaflet Maps
* Open Street Map data
* jQuery, Knockoutjs
* Toast CSS Grid

