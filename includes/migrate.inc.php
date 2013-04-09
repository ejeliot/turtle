<?php
    namespace Brightfish\Turtle;

    class Migrate {
        protected $options;
        protected $config;
        protected $migrations;
        protected $migrationsMax;
        protected $db;
        protected $console;

        public function __construct() {
            $this->console = new Console();
            $this->options = getopt('', array(
                'config:',
                'action:',
                'dryrun',
                'name::',
                'filename::'
            ));

            if (empty($this->options['config']) || empty($this->options['action'])) {
                die($this->console->format(
                    'Usage: ./migrate.php --config=[location of config file] --action=[action to run]',
                    'red'
                ));
            }

            $this->load_config();
            $this->load_db();
            $this->load_migrations();

            switch ($this->options['action']) {
                case 'create':
                    $this->create();
                    break;
                case 'show_new':
                    $this->show_new();
                    break;
                case 'show_applied':
                    $this->show_applied();
                    break;
                case 'show_all':
                    $this->show_all();
                    break;
                case 'mark_all':
                    $this->mark_all();
                    break;
                case 'mark':
                    $this->mark();
                    break;
                case 'apply_new':
                    $this->apply_new();
                    break;
                case 'apply':
                    $this->apply();
                    break;
                default:
                    die($this->console->format('Aborting, invalid action specified', 'red'));
            }
        }

        public function __destruct() {
            @$this->db->close();
        }

        protected function load_config() {
            if (!is_file($this->options['config'])) {
                die($this->console->format("Aborting, specified config file doesn't exist", 'red'));
            }

            if (!($this->config = parse_ini_file($this->options['config'], true))) {
                die($this->console->format('Aborting, error reading config file', 'red'));
            }
        }

        protected function load_migrations() {
            if (empty($this->config['migrations']['dir'])) {
                die($this->console->format('Aborting, migrations directory missing from config file', 'red'));
            }

            if (!is_dir($this->config['migrations']['dir'])) {
                die($this->console->format("Aborting, migrations directory doesn't exist", 'red'));
            }

            $this->migrations = array();
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

        protected function load_db() {
            $this->db = @new \mysqli(
                $this->config['mysql']['host'],
                $this->config['mysql']['user'],
                $this->config['mysql']['pass'],
                $this->config['mysql']['db']
            );

            if (mysqli_connect_error()) {
                die($this->console->format(sprintf(
                    'Aborting, unable to connect to database (%d: %s)',
                    mysqli_connect_errno(),
                    mysqli_connect_error()
                ), 'red'));
            }

            $this->db->set_charset($this->config['mysql']['charset']);
            $this->db_create_table();
        }

        protected function db_create_table() {
            $this->db->query(sprintf(
                '
                    create table if not exists %s (
                        filename varchar(250) not null,
                        date_applied datetime not null,
                        primary key(filename)
                    ) engine=%s default charset=%s
                ',
                $this->config['mysql']['table'],
                $this->config['mysql']['engine'],
                $this->config['mysql']['charset']
            ));
        }

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

        protected function db_mark_applied($filename) {
            $filename = $this->db->real_escape_string($filename);

            $this->db->query(sprintf("
                insert into %s (
                    filename, date_applied
                ) values (
                    '%s', now()
                )
            ", $this->config['mysql']['table'], $filename));
        }

        protected function create() {
            if (empty($this->options['name'])) {
                die($this->console->format('Aborting, no name specified for the new migration file', 'red'));
            }

            $filename = sprintf(
                '%s.%s.sql',
                str_pad(
                    $this->migrationsMax + 1,
                    $this->config['migrations']['incLength'],
                    '0',
                    STR_PAD_LEFT
                ),
                $this->create_filename($this->options['name'])
            );

            if (@touch($this->get_full_filename($filename))) {
                echo $this->console->format(sprintf('Created new migration file: %s', $filename), 'green');
            } else {
                die($this->console->format('Aborting, unable to write file', 'red'));
            }
        }

        protected function show_new() {
            if (!empty($this->migrations)) {
                foreach ($this->migrations as $filename => $dateApplied) {
                    if (is_null($dateApplied)) {
                        echo $this->console->format(sprintf('( ) %s', $filename), 'grey');
                    }
                }
            }
        }

        protected function show_applied() {
            if (!empty($this->migrations)) {
                foreach ($this->migrations as $filename => $dateApplied) {
                    if (!is_null($dateApplied)) {
                        echo $this->console->format(sprintf('(x) %s on %s', $filename, $dateApplied), 'green');
                    }
                }
            }
        }

        protected function show_all() {
            if (!empty($this->migrations)) {
                foreach ($this->migrations as $filename => $dateApplied) {
                    echo $this->console->format(sprintf(
                        "(%s) %s",
                        (!is_null($dateApplied) ? 'x' : ' '),
                        $filename
                    ), (!is_null($dateApplied) ? 'green' : 'grey'));
                }
            }
        }

        protected function mark_all() {
            if (!empty($this->migrations)) {
                foreach ($this->migrations as $filename => $dateApplied) {
                    if (is_null($dateApplied)) {
                        echo $this->console->format(sprintf("Marking %s as applied", $filename), 'green');
                        $this->db_mark_applied($filename);
                    }
                }
            }
        }


        protected function mark() {
            if (empty($this->options['filename'])) {
                die($this->console->format('Aborting, no migration specified', 'red'));
            }

            if (!array_key_exists($this->options['filename'], $this->migrations)) {
                die($this->console->format(sprintf("Aborting, %s doesn't exist", $this->options['filename']), 'red'));
            }

            if (!is_null($this->migrations[$this->options['filename']])) {
                die($this->console->format(sprintf(
                    'Aborting, %s already applied',
                    $this->options['filename']
                ), 'red'));
            }

            printf($this->console->format(sprintf('Marking %s as applied', $filename), 'red'));
            $this->db_mark_applied($this->options['filename']);
        }

        protected function apply_new() {
            if (!empty($this->migrations)) {
                foreach ($this->migrations as $filename => $dateApplied) {
                    if (is_null($dateApplied)) {
                        if ($this->mysql_cmd($filename)) {
                            $this->db_mark_applied($filename);
                        } else {
                            die($this->console->format(sprintf(
                                'Aborting, %s failed so no further migrations will be applied',
                                $filename
                            ), 'red'));
                        }
                    }
                }
            }
        }

        protected function apply() {
            if (empty($this->options['filename'])) {
                die($this->console->format('Aborting, no migration specified', 'red'));
            }

            if (!array_key_exists($this->options['filename'], $this->migrations)) {
                die($this->console->format(sprintf("Aborting, %s doesn't exist", $this->options['filename']), 'red'));
            }

            if (!is_null($this->migrations[$this->options['filename']])) {
                die($this->console->format(sprintf(
                    'Aborting, %s already applied',
                    $this->options['filename']
                ), 'red'));
            }

            if ($this->mysql_cmd($this->options['filename'])) {
                $this->db_mark_applied($this->options['filename']);
            }
        }

        protected function mysql_cmd($filename) {
            if (empty($this->config['mysql']['binary'])) {
                die($this->console->format('Aborting, mysql binary path not specified', 'red'));
            }

            if (!file_exists($this->config['mysql']['binary'])) {
                die($this->console->format("Aborting, mysql binary doesn't exist", 'red'));
            }

            $startTime = microtime(true);

            if (!empty($this->config['mysql']['useTransactions'])) {
                $this->db->autocommit(false);
            }

            if (trim($this->config['mysql']['pass']) == '') {
                exec(sprintf(
                    '%s -u%s %s < %s 2>&1',
                    $this->config['mysql']['binary'],
                    $this->config['mysql']['user'],
                    $this->config['mysql']['db'],
                    escapeshellarg($this->get_full_filename($filename))
                ), $result, $code);
            } else {
                exec(sprintf(
                    '%s -u%s -p%s %s < %s 2>&1',
                    $this->config['mysql']['binary'],
                    $this->config['mysql']['user'],
                    $this->config['mysql']['pass'],
                    $this->config['mysql']['db'],
                    escapeshellarg($this->get_full_filename($filename))
                ), $result, $code);
            }

            if ($code == 0) {
                if (!empty($this->config['mysql']['useTransactions'])) {
                    $this->db->commit();
                }

                echo $this->console->format(sprintf('%s applied in %f seconds', $filename, (microtime(true) - $startTime)), 'green');
            } else {
                if (!empty($this->config['mysql']['useTransactions'])) {
                    $this->db->rollback();
                }

                echo $this->console->format(sprintf("An error occurred while applying %s", $filename), 'red');
                echo $this->console->format(str_pad('-', 30, '-', STR_PAD_LEFT), 'red');
                echo $this->console->format(implode("\n", $result), 'red');
                echo $this->console->format(str_pad('-', 30, '-', STR_PAD_LEFT), 'red');
            }

            return $code == 0;
        }

        protected function create_filename($input) {
            $filename = trim($input);
            $filename = preg_replace('/[^a-z0-9 -]/i', '', $filename);
            $filename = str_replace(' ', '-', $filename);
            $filename = strtolower($filename);

            return $filename;
        }

        protected function get_full_path($filename) {
            return rtrim($this->config['migrations']['dir'], '/')."/$filename";
        }
    }
?>