#!/usr/bin/php
<?php
    /**************************************************/
    /* Turtle - a simple DB migration script          */
    /*                                                */
    /* Authors:       Ed Eliot, Dmitry Vovk           */
    /* Copyright (c): Brightfish Software Limited     */
    /* Last Updated:  10th April 2013                 */
    /* License:       BSD (see included license file) */
    /**************************************************/

    require 'includes/console.inc.php';
    require 'includes/migrate.inc.php';

    new Brightfish\Turtle\Migrate($argv);
?>