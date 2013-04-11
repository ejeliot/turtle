#!/usr/bin/php
<?php

/**************************************************/
/* Turtle - a simple DB migration script          */
/*                                                */
/* Authors:       Ed Eliot, Dmitry Vovk           */
/* Copyright (c): Brightfish Software Limited     */
/* Last Updated:  11th April 2013                 */
/* License:       BSD (see included license file) */
/* Version:       0.0.4                           */
/**************************************************/

require 'includes/console.inc.php';
require 'includes/migrate.inc.php';

new Brightfish\Turtle\Migrate($argv);
