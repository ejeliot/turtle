# Turtle

A simple script for managing mysql database schema via raw SQL migration files.

## Author/Copyright

Copyright (c) Brightfish Software (author, Ed Eliot)

## License

BSD License - see license file

## Config Format

Turtle gets many default settings from a config file. When running Turtle commands you specify the path to the config file you want to use. Here's an example config:

```ini
[mysql]
host = "127.0.0.1"
user = "root"
pass = ""
db = "testdb"
table = "migrations"
engine = "myisam"
charset = "utf8"
binary = "mysql"
useTransactions = false

[migrations]
dir = "sample-migrations"
incLength = 3
```

The `host`, `user`, `pass` and `db` keys within the `mysql` section should be fairly self-explanatory. The `table` key specifies the name of the table you wish to use to store migration information. The `engine` and `charset` keys define what MySql table engine and character set should be used when creating the migrations table. They have no bearing on the table engine and character set used for the rest of the tables in your database. Setting `useTransations` to true will make Turtle wrap each migration run within a transaction. If the migration succeeds the transaction will be committed. If it fails for any reason the tranasction will be rolled back which will restore your database to the state it was in right before you tried to apply the failed migration. `useTranasctions` should only be set to true if you aren't using MyISAM tables in your database.

The `dir` key within the migrations section should contain the full path to your migration files. `incLength` determines what length the auto increment number assigned when creating a new migration should be padded to. For example when creating a migration with an auto increment of 1 it'll be padded to 001.

## Usage

### Create New Migration

The following command creates a new empty migration file with a correctly structured filename and sequence number prepended to the start of the filename.

    ./migrate.php --config=[location of config file] --action=create --name="A Test Name"

This example will create

001.a-test-name.sql

### Show New (Unapplied) Migrations

    ./migrate.php --config=[clocation of config file] --action=show_new

### Show Applied Migrations

    ./migrate.php --config=[location of config file] --action=show_applied

### Show All Migrations

    ./migrate.php --config=[location of config file] --action=show_all

### Mark All

Mark all migrations as applied (without actually applying them)

    ./migrate.php --config=[location of config file] --action=mark_all

### Mark (a specific migration)

    ./migrate.php --config=[location of config file] --action=mark --filename=[filename excluding path]

### Apply New (Unapplied) Migrations

    ./migrate.php --config=[location of config file] --action=apply_new

### Apply (a specific migration)

    ./migrate.php --config=[location of config file] --action=apply --filename=[filename excluding path]