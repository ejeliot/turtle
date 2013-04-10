# Turtle

A simple script for managing mysql database schema via raw SQL migration files.

## Authors/Copyright

Copyright (c) Brightfish Software Limited

Authors: Ed Eliot, Dmitry Vovk

## License

BSD License - see license file

## Config Format

Turtle gets settings from a config file. The config file uses the ini file format and can be supplied in one of 3 ways:

1. Modifying the default (turtle.conf) that is supplied with the distribution.
2. Setting the config path in an environment variable called `TURTLE_CONFIG`.
3. Specifying as a command line parameter `--config=<path to config file>`.

```ini
[mysql]
host = "127.0.0.1"
user = "root"
pass = ""
db = "testdb"
table = "migrations"
engine = "myisam"
charset = "utf8"

[migrations]
dir = "sample-migrations"
incLength = 3
```

The `host`, `user`, `pass` and `db` keys within the `mysql` section should be fairly self-explanatory. The `table` key specifies the name of the table you wish to use to store migration information. The `engine` and `charset` keys define what MySql table engine and character set should be used when creating the migrations table. They have no bearing on the table engine and character set used for the rest of the tables in your database. Setting `useTransations` to true will make Turtle wrap each migration run within a transaction. If the migration succeeds the transaction will be committed. If it fails for any reason the tranasction will be rolled back which will restore your database to the state it was in right before you tried to apply the failed migration. `useTranasctions` should only be set to true if you aren't using MyISAM tables in your database.

The `dir` key within the migrations section should contain the full path to your migration files. `incLength` determines what length the auto increment number assigned when creating a new migration should be padded to. For example when creating a migration with an auto increment of 1 it'll be padded to 001.

## Usage

### Create New Migration

The following command creates a new empty migration file with a correctly structured filename and sequence number prepended to the start of the filename.

    ./migrate.php create <migration_name>

### Show New (unapplied) Migrations

    ./migrate.php show new

### Show Applied Migrations

    ./migrate.php show applied

### Show All Migrations

    ./migrate.php show all

### Mark All

Mark all migrations as applied (without actually applying them)

    ./migrate.php mark all

### Mark (a specific migration)

    ./migrate.php mark <filename>

### Apply New (unapplied) Migrations

    ./migrate.php apply new

### Apply (a specific migration)

    ./migrate.php apply <filename>

## Change Log

### 0.0.2

Restructuring and improvements to command line parameters and usage:

* Command line parameters changed:
   - Commands are entered without prefix.
   - Options can be set with -- prefix.
   - Config file can be set using three ways:
      1. Using default config file: turtle.conf.
      2. Using environment variable TURTLE_CONFIG.
      3. Using command line parameter --config=<filename>.
* Use of internal mysqli method to run SQL migration instead of standalone mysql executable.
* Display failed query in multi query migrations.
* Extracted methods for messaging: 'error', 'success', 'message', and 'abort'.
* Always use COMMIT/ROLLBACK. Ignored with MyISAM, but works with InnoDB.
* Implemented functional dry run support.
* Added help message.
* Added automatic timestamping to migrations table scheme.
* Added storing of applied migration(s) to migration table.
* Fix minor issue with method name: 'get_full_path' instead of 'get_full_filename'.
* Fix minor issue with undefined variable $filename in method 'mark' (now '_mark').
* Added annotations.

### 0.0.1

Initial release