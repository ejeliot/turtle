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
        $this->console = new Console();
        // Check if config file is set in ENV
        ($envConfig = getenv('TURTLE_CONFIG')) ? $this->options['config'] = $envConfig : 0;
        // Get options
        $this->options = array_merge(
            $this->options,
            getopt('', array(
                'config:',
                'dry-run',
            ))
        );
        // Get commands
        $args = array_slice($argv, 1);
        // Commands do not start with a dash
        foreach ($args as $key => $arg) {
            if ($arg{0} === '-') {
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
        $message.= $this->console->format('  apply new|<file_name>', 'white');
        $message.= '      Applies migration(s).' . PHP_EOL . PHP_EOL;
        $message.= 'Available options:' . PHP_EOL . PHP_EOL;
        $message.= $this->console->format('  --config=<config_file>', 'white');
        $message.= '      Use <config_file>. Default config file is turtle.conf. Can also be set by environment variable TURTLE_CONFIG.' . PHP_EOL . PHP_EOL;
        $message.= $this->console->format('  --dry-run', 'white');
        $message.= '      Do not run any SQL queries, display them instead.';
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
            if (preg_match('/^([0-9]+)[\._-][a-z0-9_-]+\.sql$/i', $filename, $matches)) {
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
            'create table if not exists %s (' . PHP_EOL .
            '  filename varchar(250) not null,' . PHP_EOL .
            '  script text,' . PHP_EOL .
            '  date_applied timestamp not null default current_timestamp on update current_timestamp,' . PHP_EOL .
            '  primary key(filename)' . PHP_EOL .
            ') engine=%s default charset=%s',
            $this->config['mysql']['table'],
            $this->config['mysql']['engine'],
            $this->config['mysql']['charset']
        );
        if (isset($this->options['dry-run'])) {
            $this->message($query);
        } else {
            $this->db->query($query);
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
        $filename = $this->db->real_escape_string($filename);
        if ($result = $this->db->query(sprintf("
            select
                date_applied
            from
                %s
            where
                filename = '%s'
        ", $this->config['mysql']['table'], $filename))) {
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
            "insert into %s (filename, script) values ('%s', '%s')",
            $this->config['mysql']['table'],
            $this->db->real_escape_string($filename),
            $this->db->real_escape_string(file_get_contents($this->get_full_path($filename)))
        );
        if (!$this->db->query($query)) {
            $this->abort($this->db->error);
        }
    }

    /**
     * Command: create <filename>
     *
     * @param string $name
     */
    protected function create($name) {
        if ($this->config['migrations']['incFormat'] == 'timestamp') {
            $incValue = time();
        } else {
            $incValue = str_pad(
                $this->migrationsMax + 1,
                $this->config['migrations']['incLength'],
                '0',
                STR_PAD_LEFT
            );
        }

        $filename = sprintf(
            '%s.%s.sql',
            $incValue,
            $this->create_filename($name)
        );

        if (@touch($this->get_full_path($filename))) {
            $this->success(sprintf('Created new migration file: %s', $filename));
        } else {
            $this->abort('Aborting, unable to write file');
        }
    }

    /**
     * Show command fabric
     * To add new show command, create new method named "show_your_new_command_name"
     *
     * @param string $param
     */
    protected function show($param) {
        $method = 'show_' . $param;
        if (method_exists($this, $method)) {
            $this->$method();
        } else {
            $this->error('Invalid command argument!');
            $this->help();
        }
    }

    /**
     * Command: show new
     */
    protected function show_new() {
        foreach ($this->migrations as $filename => $dateApplied) {
            if (is_null($dateApplied)) {
                $this->message(sprintf('[ ] %s', $filename));
            }
        }
    }

    /**
     * Command: show applied
     */
    protected function show_applied() {
        foreach ($this->migrations as $filename => $dateApplied) {
            if (!is_null($dateApplied)) {
                $this->success(sprintf('[x] %s on %s', $filename, $dateApplied));
            }
        }
    }

    /**
     * Command: show all
     */
    protected function show_all() {
        foreach ($this->migrations as $filename => $dateApplied) {
            echo $this->console->format(sprintf(
                "[%s] %s",
                (!is_null($dateApplied) ? 'x' : ' '),
                $filename
            ), (!is_null($dateApplied) ? 'green' : 'grey'));
        }
    }

    /**
     * Mark command fabric
     *
     * @param string $param
     */
    protected function mark($param) {
        $method = 'mark_' . $param;
        if (method_exists($this, $method)) {
            $this->$method();
        } else {
            $this->_mark($param);
        }
    }

    /**
     * Marks all migrations as applied
     * Command: mark all
     */
    protected function mark_all() {
        foreach ($this->migrations as $filename => $dateApplied) {
            if (is_null($dateApplied)) {
                $this->success(sprintf("Marking %s as applied", $filename));
                $this->db_mark_applied($filename);
            }
        }
    }

    /**
     * Mark the migration as applied
     * Command: mark <filename>
     *
     * @param string $filename
     */
    protected function _mark($filename) {
        if (!is_null($this->migrations[$filename])) {
            $this->abort(sprintf('Aborting, %s already applied', $this->options['filename']));
        }
        $this->error(sprintf('Marking %s as applied', $filename));
        $this->db_mark_applied($filename);
    }

    /**
     * Apply command fabric
     * Command: apply <filename>
     *
     * @param string $param
     */
    protected function apply($param) {
        $method = 'apply_' . $param;
        if (method_exists($this, $method)) {
            $this->$method();
        } else {
            $this->_apply($param);
        }
    }

    /**
     * Applies all new migrations
     * Command: apply new
     */
    protected function apply_new() {
        foreach ($this->migrations as $filename => $dateApplied) {
            if (is_null($dateApplied)) {
                if ($this->mysql_cmd($filename)) {
                    $this->db_mark_applied($filename);
                } else {
                    $this->abort(sprintf('Aborting, %s failed so no further migrations will be applied', $filename));
                }
            }
        }
    }

    protected function _apply($filename) {
        if (!array_key_exists($filename, $this->migrations)) {
            $this->abort(sprintf("Aborting, %s doesn't exist", $filename));
        }
        if (!is_null($this->migrations[$this->options['filename']])) {
            $this->abort(sprintf('Aborting, %s already applied', $filename));
        }
        if ($this->mysql_cmd($this->options['filename'])) {
            $this->db_mark_applied($this->options['filename']);
        }
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
                $this->db->rollback();
                $this->error(sprintf("An error occurred while applying %s: %s", $filename, $this->db->error));
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