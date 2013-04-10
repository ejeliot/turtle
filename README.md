Turtle
======

A simple script for managing mysql database schema via raw SQL migration files.

Author/Copyright
================

Copyright (c) Brightfish Software (author, Ed Eliot)

License
=======

BSB License - see license file

Usage
=====

Create New Migration
--------------------

The following command creates a new empty migration file with a correctly structured filename and sequence number prepended to the start of the filename.

    ./migrate.php --config=[location of config file] --action=create --name="A Test Name"

This example will create

001.a-test-name.sql

Show New (Unapplied) Migrations
-------------------------------

    ./migrate.php --config=[clocation of config file] --action=show_new

Show Applied Migrations
-----------------------

    ./migrate.php --config=[location of config file] --action=show_applied

Show All Migrations
-------------------

    ./migrate.php --config=[location of config file] --action=show_all

Mark All
--------

Mark all migrations as applied (without actually applying them)

    ./migrate.php --config=[location of config file] --action=mark_all

Mark (a specific migration)
---------------------------

    ./migrate.php --config=[location of config file] --action=mark --filename=[filename excluding path]

Apply New (Unapplied) Migrations
--------------------------------

    ./migrate.php --config=[location of config file] --action=apply_new

Apply (a specific migration)
----------------------------

    ./migrate.php --config=[location of config file] --action=apply --filename=[filename excluding path]