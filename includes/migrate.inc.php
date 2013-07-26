<?php
namespace Brightfish\Turtle;

class Migrate {
    // Options
    protected $options = array(
        'config' => 'turtle.conf', // default config file
    );
    protected $config;
    protected $migrations = array();
    protected $migrationsMax;
    /** @var \mysqli */
    protected $db;
    /** @var \Brightfish\Turtle\Console */
    protected $console;

    public function __construct($argv) {
        // Check if config file is set in ENV
        ($envConfig = getenv('TURTLE_CONFIG')) ? $this->options['config'] = $envConfig : 0;
        // Get options
        $this->options = array_merge(
            $this->options,
            getopt('', array(
                'config:',
                'dry-run',
                'no-colour',
                'verbose',
            ))
        );
        $this->console = new Console(isset($this->options['no-colour']));
        // Get commands
        $args = array_slice($argv, 1);
        // Commands do not start with a dash
        foreach ($args as $key => $arg) {
            if ($arg{0} === '-' && $arg !== '-') {
                unset($args[$key]);
            }
        }
        $cmd = array_shift($args);
        $param = array_shift($args);
        // Execute command if we have method for it
        if (method_exists($this, $cmd) && !empty($param)) {
            if (isset($this->options['dry-run'])) {
                $this->success('Running dry');
            }
            $this->load_config();
            $this->load_db();
            $this->load_migrations();
            $this->$cmd($param);
        } else {
            $this->help();
        }
    }

    public function __destruct() {
        if (!is_null($this->db)) {
            $this->db->commit();
            $this->db->close();
        }
    }

    /**
     * Display help
     */
    protected function help() {
        $message = 'Usage: ./migrate.php [options] command argument' . PHP_EOL . PHP_EOL;
        $message.= 'Available commands:' . PHP_EOL . PHP_EOL;
        $message.= $this->console->format('  create <file_name>', 'white');
        $message.= '      Creates new migration file.' . PHP_EOL . PHP_EOL;
        $message.= $this->console->format('  show new|applied|all', 'white');
        $message.= '      Shows migrations files.' . PHP_EOL . PHP_EOL;
        $message.= $this->console->format('  mark all|<file_name>', 'white');
        $message.= '      Marks migration(s) as applied.' . PHP_EOL . PHP_EOL;
        $message.= $this->console->format('  unmark all|<file_name>', 'white');
        $message.= '      Unmarks migration(s) as applied.' . PHP_EOL . PHP_EOL;
        $message.= $this->console->format('  apply new|<file_name>', 'white');
        $message.= '      Applies migration(s).' . PHP_EOL . PHP_EOL;
        $message.= $this->console->format('  dump <table_name>|%', 'white');
        $message.= '      Dumps schema of <table_name> or all tables.' . PHP_EOL . PHP_EOL;
        $message.= $this->console->format('  log <datetime>', 'white');
        $message.= '      Dumps all schema altering queries applied since the given date/time.' . PHP_EOL . PHP_EOL;
        $message.= 'Available options:' . PHP_EOL . PHP_EOL;
        $message.= $this->console->format('  --config=<config_file>', 'white');
        $message.= '      Use <config_file>. Default config file is turtle.conf. Can also be set with environment variable TURTLE_CONFIG.' . PHP_EOL . PHP_EOL;
        $message.= $this->console->format('  --dry-run', 'white');
        $message.= '      Do not run any SQL queries, display them instead.' . PHP_EOL . PHP_EOL;
        $message.= $this->console->format('  --no-colour', 'white');
        $message.= '      Suppress console colours.' . PHP_EOL . PHP_EOL;
        $message.= $this->console->format('  --verbose', 'white');
        $message.= '      Show more detailed messaging.';
        $this->message($message);
    }

    /**
     * Loads config file
     */
    protected function load_config() {
        if (!is_readable($this->options['config'])) {
            $this->abort("Aborting, specified config file isn't readable");
        }
        if (!($this->config = parse_ini_file($this->options['config'], true))) {
            $this->abort('Aborting, error reading config file');
        }
    }

    /**
     * Saves config file
     */
    protected function save_config() {
        $file = fopen($this->options['config'], 'w+');
        foreach ($this->config as $name => $section) {
            fwrite($file, sprintf('[%s]', $name) . PHP_EOL);
            foreach ($section as $key => $parameter) {
                fwrite($file, sprintf('%s = "%s"', $key, $parameter) . PHP_EOL);
            }
        }
        fclose($file);
    }

    /**
     * Load migrations from migrations directory files
     */
    protected function load_migrations() {
        if (empty($this->config['migrations']['dir'])) {
            $this->abort('Aborting, migrations directory missing from config file');
        }
        if (!is_dir($this->config['migrations']['dir'])) {
            $this->abort("Aborting, migrations directory doesn't exist");
        }
        $this->migrationsMax = 0;
        $dir = dir($this->config['migrations']['dir']);
        while (($filename = $dir->read()) !== false) {
            if (preg_match('/^(\d+)\.[a-z][a-z0-9._-]*[a-z0-9]\.sql$/', $filename, $matches)) {
                $this->migrations[$filename] = $this->db_migration_applied($filename);
                if ($matches[1] > $this->migrationsMax) {
                    $this->migrationsMax = $matches[1];
                }
            }
        }
        ksort($this->migrations);
    }

    /**
     * Connect to database and call create table method
     */
    protected function load_db() {
        $this->db = @new \mysqli(
            $this->config['mysql']['host'],
            $this->config['mysql']['user'],
            $this->config['mysql']['pass'],
            $this->config['mysql']['db']
        );
        if (mysqli_connect_error()) {
            $this->abort(sprintf(
                'Aborting, unable to connect to database (%d: %s)',
                mysqli_connect_errno(),
                mysqli_connect_error()
            ));
        }
        $this->db->set_charset($this->config['mysql']['charset']);
        $this->db_create_table();
    }

    /**
     * Create migrations table if it does not exist yet
     */
    protected function db_create_table() {
        $query = sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (' . PHP_EOL .
            '  `filename` VARCHAR(250) NOT NULL,' . PHP_EOL .
            '  `script` TEXT,' . PHP_EOL .
            '  `date_applied` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,' . PHP_EOL .
            '  PRIMARY KEY(`filename`)' . PHP_EOL .
            ') ENGINE=%s DEFAULT CHARSET=%s',
            $this->config['mysql']['table'],
            $this->config['mysql']['engine'],
            $this->config['mysql']['charset']
        );
        if (isset($this->options['dry-run'])) {
            $this->message($query);
        } else {
            $this->query($query);
        }
    }

    /**
     * Return date when the migration was applied
     *
     * @param string $filename
     * @return null
     */
    protected function db_migration_applied($filename) {
        $dateApplied = null;
        $query = sprintf(
            'SELECT `date_applied` FROM `%s` WHERE `filename` = "%s"',
            $this->config['mysql']['table'],
            $this->db->real_escape_string($filename)
        );
        if ($result = $this->query($query)) {
            if ($details = $result->fetch_assoc()) {
                $dateApplied = $details['date_applied'];
            }
            $result->free();
        }
        return $dateApplied;
    }

    /**
     * Insert migration filename into table as applied one
     *
     * @param string $filename
     */
    protected function db_mark_applied($filename) {
        $query = sprintf(
            'INSERT INTO `%s` (`filename`, `script`) VALUES ("%s", "%s")',
            $this->config['mysql']['table'],
            $this->db->real_escape_string($filename),
            $this->db->real_escape_string(file_get_contents($this->get_full_path($filename)))
        );
        if (!$this->query($query)) {
            $this->abort($this->db->error);
        }
    }

    /**
     * Remove migration filename from table
     *
     * @param string $filename
     */
    protected function db_unmark_applied($filename) {
        $query = sprintf(
            'DELETE FROM `%s` WHERE `filename` = "%s"',
            $this->config['mysql']['table'],
            $this->db->real_escape_string($filename)
        );
        if (!$this->query($query)) {
            $this->abort($this->db->error);
        }
    }

    /**
     * Runs SQL query
     *
     * @param string $query
     * @return bool|\mysqli_result
     */
    protected function query($query) {
        if (isset($this->options['verbose'])) {
            $this->warning($query);
        }
        $result = $this->db->query($query);
        if ($error = $this->db->error) {
            $this->error($error);
        }
        return $result;
    }

    /**
     * Run SQL migrations from file
     * Multiple queries are supported
     * Rolls back if possible (actually works with InnoDB only)
     *
     * @param string $filename
     * @return bool
     */
    protected function mysql_cmd($filename) {
        $startTime = microtime(true);
        $queries = file_get_contents($this->get_full_path($filename));
        if (isset($this->options['dry-run'])) {
            $this->message($queries);
            return true;
        } else {
            $this->db->autocommit(false);
            $i = 1;
            if (isset($this->options['verbose'])) {
                $this->warning($queries);
            }
            if ($this->db->multi_query($queries)) {
                do {
                    $i++;
                    if ($result = $this->db->store_result()) {
                        $result->free();
                    }
                } while (@$this->db->next_result());
                if ($error = $this->db->error) {
                    $this->db->rollback();
                    $this->abort(sprintf('Error at query %d in %s: %s', $i, $filename, $error));
                }
                $this->db->commit();
                $this->success(sprintf('%s applied in %f seconds', $filename, (microtime(true) - $startTime)));
                return true;
            } else {
                $this->error(sprintf("An error occurred while applying %s: %s", $filename, $this->db->error));
                $this->db->rollback();
                return false;
            }
        }
    }

    /**
     * Create formatted migration file name
     *
     * @param string $input
     * @return string
     */
    protected function create_filename($input) {
        $filename = trim($input);
        $filename = preg_replace('/[^a-z0-9 -]/i', '', $filename);
        $filename = str_replace(' ', '-', $filename);
        $filename = strtolower($filename);
        return $filename;
    }

    /**
     * Get fully qualified path of a migration file
     *
     * @param string $filename
     * @return string
     */
    protected function get_full_path($filename) {
        return rtrim($this->config['migrations']['dir'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
    }

    /* Messaging methods */

    protected function abort($message) {
        $this->error($message);
        die;
    }

    protected function error($message) {
        echo $this->console->format($message, 'red');
    }

    protected function warning($message) {
        echo $this->console->format($message, 'yellow');
    }

    protected function success($message) {
        echo $this->console->format($message, 'green');
    }

    protected function message($message) {
        echo $this->console->format($message, 'gray');
    }
}
