<?php

class Database {
  var $db_obj;
  var $db_name = DB_NAME;
  var $db_user = DB_USER;
  var $db_pass = DB_PASSWORD;
  var $db_host = DB_HOST;
  var $sql;

  function Database() {
    $this->connect();
  }

  function insert($table, $fields, $values) {
    $this->sql = "INSERT INTO "
      . $table
        . " ("
          . join(",", $fields)
          . ") VALUES('"
            . join("','", $this->escape($values))
              . "')";
    return $this->query($this->sql);
  }

  function update($table, $data, $condition) {
    foreach($data as $k => $v) {
      $d[] = $k . "='" . $this->escape($v) . "'";
    }
    $this->sql = "UPDATE "
      . $table
        . " SET "
          . join(",", $d)
            . " WHERE "
              . $condition;
    return $this->query($this->sql);
  }

  function escape($values) {
    if (is_array($values)) {
      foreach($values as $k) {
        $result[] = mysqli_real_escape_string($this->db_obj, $k);
      }
    } elseif (is_string($values)) {
      $result = mysqli_real_escape_string($this->db_obj, $values);
    }
    return $result;
  }

  function commit() {
    mysqli_commit($this->$db_obj);
  }

  function connect() {
    $this->db_obj = mysqli_connect($this->db_host, $this->db_user, $this->db_pass, $this->db_name);
  }

  function query($sql) {
    return mysqli_query($this->db_obj, $sql);
  }

  function array($sql) {
    $res = $this->query($sql);
    $rows = [];
    if($res){
      while($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
      }
    }
    return $rows;
  }

  function json($sql) {
    if($this->db_obj){
        $res = $this->array($sql);
        if($res) {
            return $this->to_json($res);
        }
        else {
          return '[]';
        }
    }
    else {
      throw new Exception('database connection error');
    }
  }

  function array_as_json($sql){
    if($this->db_obj){
      $rows = $this->array($sql);
        $res = [];
        if($rows) {
          foreach($rows as $row) {
            foreach($row as $key => $val ){
                $row[$key] = json_decode($val, true);
            }
            $res[] = $row;
          }
          return $res;
        }
        else {
          return [];
        }
    }
    else {
      throw new Exception('database connection error');
    }
  }

  function to_json($array){
    return json_encode($array, JSON_UNESCAPED_UNICODE);
  }
}


?>