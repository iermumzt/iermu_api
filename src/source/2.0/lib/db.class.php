<?php

class apiserver_db {
    var $querynum = 0;
    var $link;
    var $histories;

    var $dbhost;
    var $dbuser;
    var $dbpw;
    var $dbcharset;
    var $pconnect;
    var $tablepre;
    var $time;

    var $goneaway = 5;

    function connect($dbhost, $dbuser, $dbpw, $dbname = '', $dbcharset = '', $pconnect = 0, $tablepre='', $time = 0) {
        $this->dbhost = $dbhost;
        $this->dbuser = $dbuser;
        $this->dbpw = $dbpw;
        $this->dbname = $dbname;
        $this->dbcharset = $dbcharset;
        $this->pconnect = $pconnect;
        $this->tablepre = $tablepre;
        $this->time = $time;

        if($pconnect) {
            if(!$this->link = mysqli_pconnect($dbhost, $dbuser, $dbpw)) {
                $this->halt('Can not connect to MySQL server');
            }
        } else {
            if(!$this->link = mysqli_connect($dbhost, $dbuser, $dbpw)) {
                $this->halt('Can not connect to MySQL server');
            }
        }

        if($this->version() > '4.1') {
            if($dbcharset) {
                mysqli_query($this->link,"SET character_set_connection=".$dbcharset.", character_set_results=".$dbcharset.", character_set_client=binary");
            }

            if($this->version() > '5.0.1') {
                mysqli_query($this->link , "SET sql_mode=''");
            }
        }

        if($dbname) {
            mysqli_select_db($this->link,$dbname);
        }

    }

    function fetch_array($query, $result_type = MYSQLI_ASSOC) {
        if($query->num_rows!=0){
            return mysqli_fetch_array($query, $result_type);
        }
        return array();
    }

    function result_first($sql) {
        $query = $this->query($sql);
        return $this->result($query, 0);
    }

    function fetch_first($sql) {
        $query = $this->query($sql, 'SILENT');
        return $this->fetch_array($query);
    }

    function fetch_all($sql, $id = '') {
        $arr = array();
        $query = $this->query($sql);
        while($data = $this->fetch_array($query)) {
            $id ? $arr[$data[$id]] = $data : $arr[] = $data;
        }
        return $arr;
    }

    function cache_gc() {
        $this->query("DELETE FROM {$this->tablepre}sqlcaches WHERE expiry<$this->time");
    }

    function query($sql, $type = '', $cachetime = FALSE) {
        $func = $type == 'UNBUFFERED' && @function_exists('mysqli_unbuffered_query') ? 'mysqli_unbuffered_query' : 'mysqli_query';
        
        if(!($query = $func($this->link,$sql)) && $type != 'SILENT') {
            $this->halt('MySQL Query Error', $sql);
        }
        $this->querynum++;
        $this->histories[] = $sql;
        return $query;
    }

    function affected_rows() {
        return mysqli_affected_rows($this->link);
    }

    function error() {
        return (($this->link) ? mysqli_error($this->link) : mysqli_error());
    }

    function errno() {
        return intval(($this->link) ? mysqli_errno($this->link) : mysqli_errno());
    }

    function result($query, $row) {
        // $query = @mysql_result($query, $row);
        if($row==0){
            $query = @mysqli_fetch_row($query);
            return $query['0'];
        }else{
            $query = @mysqli_fetch_assoc($query);
            return $query;
        }
        
    }

    function num_rows($query) {
        $query = mysqli_num_rows($query);
        return $query;
    }

    function num_fields($query) {
        return mysqli_num_fields($query);
    }

    function free_result($query) {
        return mysqli_free_result($query);
    }

    function insert_id() {
        return ($id = mysqli_insert_id($this->link)) >= 0 ? $id : $this->result($this->query("SELECT last_insert_id()"), 0);
    }

    function fetch_row($query) {
        $query = mysqli_fetch_row($query);
        return $query;
    }

    function fetch_fields($query) {
        return mysqli_fetch_field($query);
    }

    function version() {
        return mysqli_get_server_info($this->link);
    }

    function close() {
        return mysqli_close($this->link);
    }

    function halt($message = '', $sql = '') {
        $error = mysqli_error($this->link);
        $errorno = mysqli_errno($this->link);

        // $error = mysql_error();
        // $errorno = mysql_errno();

        if($errorno == 2006 && $this->goneaway-- > 0) {
            $this->connect($this->dbhost, $this->dbuser, $this->dbpw, $this->dbname, $this->dbcharset, $this->pconnect, $this->tablepre, $this->time);
            $this->query($sql);
        } else {
            // log记录 ----------- error_log
            log::error_log('db error', $sql, $message, $errorno, $error);
            apierror(API_HTTP_INTERNAL_SERVER_ERROR, API_ERROR_DB_FAILED, $message);
        }
    }
}
