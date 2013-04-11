<?php
namespace Brightfish\Turtle;

class Commands extends Migrate {

    public function __construct($argv) {
        parent::__construct($argv);
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
            $this->abort(sprintf('Aborting, %s already applied', $filename));
        }
        $this->success(sprintf('Marking %s as applied', $filename));
        $this->db_mark_applied($filename);
    }

    /**
     * Unmark command fabric
     *
     * @param string $param
     */
    protected function unmark($param) {
        $method = 'unmark_' . $param;
        if (method_exists($this, $method)) {
            $this->$method();
        } else {
            $this->_unmark($param);
        }
    }

    /**
     * Unmarks all migrations as applied
     * Command: unmark all
     */
    protected function unmark_all() {
        foreach ($this->migrations as $filename => $dateApplied) {
            if (!is_null($dateApplied)) {
                $this->success(sprintf("Unmarking %s as applied", $filename));
                $this->db_unmark_applied($filename);
            }
        }
    }

    /**
     * Unmark the migration as applied
     * Command: unmark <filename>
     *
     * @param string $filename
     */
    protected function _unmark($filename) {
        if (is_null($this->migrations[$filename])) {
            $this->abort(sprintf('Aborting, %s not applied', $filename));
        }
        $this->success(sprintf('Unmarking %s as applied', $filename));
        $this->db_unmark_applied($filename);
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

    /**
     * Apply migration from filename
     * Command: apply <filename>
     *
     * @param string $filename
     */
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
     * Dumps database objects
     *
     * @param string $param
     */
    public function dump($param) {
        if ($param === '%') {
            $this->dump_all();
        } else {
            $this->_dump($param);
        }
    }

    /**
     * Dumps table schema
     * Command: dump <table_name>
     *
     * @param string $param
     */
    protected function _dump($param) {
        $query = sprintf('SHOW CREATE TABLE `%s`', $this->db->real_escape_string($param));
        $result = $this->query($query);
        $table = array_pop($result->fetch_array()) . ';' . PHP_EOL;
        echo $table;
    }

    /**
     * Dumps all tables except migration
     * Command: dump %
     */
    protected function dump_all() {
        $query = sprintf(
            'SHOW TABLES WHERE `Tables_in_%s` NOT LIKE "%s"',
            $this->config['mysql']['db'],
            $this->config['mysql']['table']
        );
        $result = $this->query($query);
        while ($table = $result->fetch_assoc()) {
            $tableName = array_pop($table);
            $this->_dump($tableName);
        }
    }

    /**
     * Dumps set of schema altering queries since given point of time.
     * Given "-" as timestamp, will try to use the value saved in config by previous call
     *
     * Command: log <datetime>|-
     */
    public function log($param) {
        $dateTime = date('Y-m-d H:i:s', $this->get_timestamp($param));
        $trackCommands = array(
            'CREATE TABLE', 'ALTER TABLE', 'DROP TABLE', 'RENAME TABLE',
            'CREATE INDEX', 'DROP INDEX',
            'CREATE TABLESPACE', 'ALTER TABLESPACE', 'DROP TABLESPACE',
            'CREATE VIEW', 'ALTER VIEW', 'DROP VIEW',
            'CREATE FUNCTION', 'ALTER FUNCTION', 'DROP FUNCTION',
            'CREATE PROCEDURE', 'ALTER PROCEDURE', 'DROP PROCEDURE',
            'CREATE EVENT', 'ALTER EVENT', 'DROP EVENT',
        );
        $track = '^(' . implode('|', $trackCommands) . ')';
        $query = sprintf(
            'SELECT `command_type`, `argument` FROM `mysql`.`general_log` WHERE ' .
            '(`command_type` = "Query" AND UPPER(`argument`) REGEXP "%s" AND `argument` NOT LIKE "%% EXISTS `%s`%%") '.
            'OR (`command_type` = "Init DB") ' .
            'AND `event_time` > "%s" ' .
            'ORDER BY `event_time` ASC',
            $track,
            $this->config['mysql']['table'],
            $dateTime
        );
        $this->config['migrations']['lastMigration'] = date('Y-m-d H:i:s');
        $result = $this->query($query);
        $db = '';
        while ($table = $result->fetch_assoc()) {
            if ($table['command_type'] === 'Init DB') {
                $db = $table['argument'];
                continue;
            }
            if ($table['command_type'] === 'Query' && $db === $this->config['mysql']['db']) {
                $this->message($table['argument'] . ';');
            }
        }
        $this->save_config();
    }

    /**
     * Tries to figure out timestamp
     *
     * @param string $param
     * @return int
     */
    protected function get_timestamp($param) {
        if ($param === '-') {
            if (empty($this->config['migrations']['lastMigration'])) {
                $this->abort('Cannot use "-", no previous migration time is present!');
            } else {
                return strtotime($this->config['migrations']['lastMigration']);
            }
        } else {
            return strtotime($param);
        }
    }
}
