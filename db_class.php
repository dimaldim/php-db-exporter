<?php

class Export_DB
{
    var $host;
    var $user;
    var $pass;
    var $db;
    var $con;
    var $max_rows;
    var $dir;
    var $charset;
    var $backup_path;
    var $output;

    /**
     * Initializing constructor
     */
    public function __construct($host, $user, $pass, $db, $max_rows = null, $dir = null, $charset = 'utf8')
    {
        if (empty($max_rows)) $max_rows = 10000;
        if (empty($dir)) $dir = '.';
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
        $this->db = $db;
        $this->con = $this->connect();
        $this->max_rows = $max_rows;
        $this->dir = $dir;
        $this->charset = $charset;
        $this->backup_path = "{$this->dir}/{$this->db}_" . date("Y_m_d_H_i");
        $this->output = '';
    }
////////////////////////////////////////////////////////////

    /**
     * @return mysqli
     */
    protected function connect()
    {
        try {
            $con = new mysqli($this->host, $this->user, $this->pass, $this->db);
            if ($con->connect_error) {
                throw new Exception("Couldn't connect: {$con->connect_error}");
            }
            $con->set_charset('utf8');
        } catch (Exception $e) {
            die($e->getMessage());
        }
        return $con;
    }
////////////////////////////////////////////////////////////

    /**
     * @return bool
     */
    public function backupTables()
    {
        //Initializing database tables
        $tables = $this->get_tables();
        //////////////////////////////

        //Create database query
        $sql = "CREATE DATABASE IF NOT EXISTS `{$this->db}`;\n\n";
        foreach ($tables as $table) {
            $this->print_to_user("Backing up table {$table}" . str_repeat('.', 50 - strlen($table)), 0);
            $sql .= "USE `{$this->db}`;\n\n";
            $sql .= "SET foreign_key_checks = 0;\n\n";
            //Create table
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n\n";
            $create_sql = $this->con->query("SHOW CREATE TABLE `{$table}`;")->fetch_row()[1];
            $sql .= "{$create_sql};\n\n";

            $table_rows = $this->con->query("SELECT COUNT(*) FROM `{$table}`")->fetch_row()[0];
            $num_files = ceil($table_rows / $this->max_rows); // Calculate how many files we need to generate

            //Generate data
            $this->insert_into($num_files, $table, $sql);
            $this->print_to_user("OK");
            //////////////////////////////
        }
        return true;
    }
////////////////////////////////////////////////////////////

    /**
     * @return array
     */
    public function get_tables()
    {
        $tables = [];
        $result = $this->con->query('SHOW TABLES');
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0]; // Fill tables
        }

        return $tables;
    }
////////////////////////////////////////////////////////////

    /**
     * @param $num_files
     * @param $table
     * @param $sql
     */
    public function insert_into($num_files, $table, &$sql)
    {
        if ($num_files == 0) {
            $this->save($sql, "{$table}.sql");
        } else {
            for ($i = 1; $i <= $num_files; $i++) {
                $query = "SELECT * FROM `{$table}` LIMIT " . ($i * $this->max_rows - $this->max_rows) . ",{$this->max_rows}";
                $result = $this->con->query($query);
                $num_rows = $result->num_rows;
                $num_fields = $result->field_count;

                if ($i > 1) $sql = '';
                $sql .= "INSERT INTO `{$table}` VALUES ";

                for ($j = 0; $j < $num_fields; $j++) {
                    $row_count = 1;
                    while ($row = $result->fetch_row()) {
                        $sql .= '(';
                        for ($k = 0; $k < $num_fields; $k++) {
                            if (isset($row[$k])) {
                                $row[$k] = addslashes($row[$k]);
                                $row[$k] = str_replace("\n", "\\n", $row[$k]);
                                $row[$k] = str_replace("\r", "\\r", $row[$k]);
                                $row[$k] = str_replace("\f", "\\f", $row[$k]);
                                $row[$k] = str_replace("\t", "\\t", $row[$k]);
                                $row[$k] = str_replace("\v", "\\v", $row[$k]);
                                $row[$k] = str_replace("\a", "\\a", $row[$k]);
                                $row[$k] = str_replace("\b", "\\i", $row[$k]);
                                if ($row[$k] == 'true' or $row[$k] == 'false' or preg_match('/^-?[0-9]+$/', $row[$k]) or $row[$k] == 'NULL' or $row[$k] == 'null') {
                                    $sql .= $row[$k];
                                } else {
                                    $sql .= '"' . $row[$k] . '"';
                                }
                            } else {
                                $sql .= 'NULL';
                            }

                            if ($k < ($num_fields - 1)) {
                                $sql .= ',';
                            }
                        }

                        if ($row_count == $num_rows) {
                            $row_count = 0;
                            $sql .= ");\n";
                        } else {
                            $sql .= "),\n";
                        }

                        $row_count++;
                    }
                }
                if ($i > 1) {
                    $this->save($sql, "{$table}_{$i}.sql");
                } else {
                    $this->save($sql, "{$table}.sql");
                }
                $sql = '';
            }
        }
    }
////////////////////////////////////////////////////////////

    /**
     * @param $sql
     * @param $file
     * @return bool
     */
    public function save(&$sql, $file)
    {
        if (!$sql) return false;
        try {
            if (!file_exists($this->backup_path)) {
                mkdir($this->backup_path, 0777, true);
            }
            file_put_contents("{$this->backup_path}/{$file}", $sql);
            $this->gzip("{$this->backup_path}/{$file}");
        } catch (Exception $e) {
            print_r($e->getMessage());
            return false;
        }
        return true;
    }
////////////////////////////////////////////////////////////

    /**
     * @param $file
     * @param int $level
     * @return bool|string
     */
    public function gzip($file, $level = 9)
    {
        $method = "wb{$level}";
        $new_file = "{$file}.gz";
        if ($file_open = gzopen($new_file, $method)) {
            if ($file_in = fopen($file, 'rb')) {
                while (!feof($file_in)) {
                    gzwrite($file_open, fread($file_in, 1024 * 256));
                }
                fclose($file_in);
            } else {
                return false;
            }
            gzclose($file_open);
        }
        if (!unlink($file)) return false;

        return $new_file;
    }

////////////////////////////////////////////////////////////
    public function print_to_user($msg, $breaks = 1)
    {
        if (!$msg) return false;
        if ($msg != "OK") {
            $msg = date("Y-m-d H:i:s") . " - " . $msg;
        }
        $output = '';

        $output .= $msg;
        if ($breaks > 0) {
            for ($i = 1; $i <= $breaks; $i++) {
                $output .= "<br/>";
            }
        }
        if (ob_get_level() > 0) {
            ob_get_flush();
        }
        echo $output;

        flush();
    }
////////////////////////////////////////////////////////////
}
