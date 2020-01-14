<?php

class DBMan {
  var $db;
  var $tablename;
  var $schema;
  var $mask;
  var $limit;
  var $label;
  var $order;
  var $type;


  function DBMan($db, $tablename) {
    $this->db = $db;
    $this->tablename = $tablename;
    $this->mask = [];
    $this->limit = 300;
    $this->label = [];
    $this->order = [];
    $this->input_type = [];
  }

  function get_schema($tablename = null){
    if(!$tablename) $tablename = $this->tablename;
    $this->schema = $this->db->array("DESC ${tablename}");
    return $this->schema;
  }

  function get_primary_key($tablename = null){
    if(!$tablename) { $tablename = $this->tablename; }
    if(!$this->schema) { $this->get_schema($tablename); }
    $res = [];
    foreach($this->schema as $s){
      if(strpos($s['Key'], 'PRI') !== FALSE){
        $res[] = $s;
      }
    }
    return $res;
  }

  function get_primary_key_field($tablename = null){
    if(!$tablename) { $tablename = $this->tablename; }
    if(!$this->schema) { $this->get_schema($tablename); }
    $keys = [];
    foreach($this->get_primary_key($tablename) as $key){
      $keys[] = $key['Field'];
    };
    return $keys;
  }

  function is_numeric($col){
    switch(substr($col['Type'], 0, 3)){
      case 'int':
      case 'sma':
      case 'tin':
      case 'big':
      case 'med':
      case 'dec':
      case 'num':
      case 'flo':
      case 'dou':
        return true;
      default:
        return false;
    }
  }

  function get_schema_unmasked($tablename = null){
    if(!$tablename) { $tablename = $this->tablename; }
    if(!$this->schema) { $this->get_schema($tablename); }
    $cols = [];
    foreach($this->get_schema($tablename) as $col){
      if(!in_array($col["Field"], $this->mask)){
        $cols[] = $col;
      }
    }
    return $cols;
  }

  function get_schema_field_unmasked($tablename = null){
    if(!$tablename) { $tablename = $this->tablename; }
    if(!$this->schema) { $this->get_schema($tablename); }
    $cols = [];
    foreach($this->get_schema($tablename) as $col){
      if(!in_array($col["Field"], $this->mask)){
        $cols[] = $col["Field"];
      }
    }
    return $cols;
  }

  function get_input_type($key){
    if($this->input_type[$key['Field']]) { return $this->input_type[$key['Field']]; }
    switch(substr($key["Type"], 0, 3)){
      case 'tim':
        return 'time';
      case 'dat':
        switch(substr($key["Type"], 0, 5)){
          case 'datet':
            //return 'datetime-local';
            return 'text';
          default:
            return 'date';
        }
      case 'int':
      case 'sma':
      case 'tin':
      case 'big':
      case 'med':
      case 'dec':
      case 'num':
      case 'flo':
      case 'dou':
        return 'number';
      default:
        return 'text';
    }

  }

  function quote_from_post($key){
    if($this->get_input_type($key)==='checkbox' && !isset($_POST[$key['Field']])) return '0';
    if(!isset($_POST[$key['Field']]) || $_POST[$key['Field']]=='') return 'null';
    if($this->is_numeric($key)){
      return $_POST[$key['Field']];
    }
    else {
      return "'".$this->db->escape($_POST[$key['Field']])."'";
    } 
  }

  function delete(){
    $tablename = $this->tablename;
    $cond = [];
    $keys = $this->get_primary_key();
    if(count($keys)==0){
      $keys = $this->get_schema_unmasked();
    }
    foreach($keys as $key){
      $cond[] = '`'.$key['Field'].'` = '.$this->quote_from_post($key);
    }
    $where = join(' and ', $cond);
    echo "<div class=\"uk-alert-success\" uk-alert>削除しました<blockquote>${where}</blockquote></div>";
    $q = "delete from ${tablename} where ${where}";
    $this->db->query($q);
  }

  function update(){
    $tablename = $this->tablename;
    $fields = $this->get_schema_unmasked();
    $pkeys = $this->get_primary_key_field();
    $vals = [];
    $wheres = [];
    foreach($fields as $f){
      $k = $f['Field'];
      $v = $this->quote_from_post($f);
      if(in_array($k, $pkeys)) {
        $wheres[] = "`$k` = $v";
      }
      else {
        $vals[] = "`$k` = $v"; 
      }
    }
    $set = join(',', $vals);
    $where = join(' and ', $wheres);
    $this->db->query("update ${tablename} set $set where $where"); 
    echo '<div class="uk-alert-success" uk-alert>更新しました<form action="" method="POST">';
    echo '<blockquote><dl class="uk-description-list">';
    foreach($_POST as $key => $val){
      if($key !== 'command'){
        echo "<dt>${key}</dt><dd>${val}</dd>";
      }
    }
    echo '</dl></blockquote></div>';
  }

  function insert(){
    $tablename = $this->tablename;
    $keys = $this->get_schema_unmasked();
    $cols = [];
    $vals = [];
    $values = [];
    foreach($keys as $key){
      $cols[] = $key['Field'];
      $vals[] = $this->quote_from_post($key);
      $values[$key['Field']] = $this->quote_from_post($key);
    }

    $q = $this->get_insert_query($values, $tablename);
    $this->db->query($q); 
    echo '<div class="uk-alert-success" uk-alert>追加しました<form action="" method="POST">';
    echo '<blockquote class=\"uk-alert-success\"><dl class="uk-description-list">';
    foreach($_POST as $key => $val){
      if($key !== 'command'){
        echo "<dt>${key}</dt><dd>${val}</dd>";
      }
    }
    echo '</dl></blockquote></div>';
  }

  function get_insert_query($values, $tablename){
    foreach($values as $key => $val){
        $cols[] = $key;
        $vals[] = $val;
    }
    $val = join(',', $vals);
    $col = '`'.join('`,`', $cols).'`';
    return "insert into $tablename ($col) values ($val)";;
  }

  function confirm_delete(){
    echo '<div class="uk-alert-warning" uk-alert><h3>削除しますか？</h3><form action="" method="POST">';
    echo '<a href="./" class="uk-button uk-button-default">キャンセル</a> ';
    echo '<button class="uk-button uk-button-secondary" type="submit" name="command" value="delete">削除</button>';
    echo '<blockquote><dl class="uk-description-list">';
    foreach($_POST as $key => $val){
      if($key !== 'command'){
        echo "<dt>${key}</dt><dd>${val}</dd>";
        echo "<input type=\"hidden\" name=\"${key}\"value=\"${val}\">\n";
      }
    }
    echo '</dl></blockquote></form></div>';
  }


  function show_basic_table($tablename = null){
    $result = $this->execute_command();
    if(!$tablename) { $tablename = $this->tablename; }
    if(!$this->schema) { $this->get_schema($tablename); }
    if(isset($_GET["page"])){
      $page = $_GET["page"];
    }
    else {
      $page = 1;
    }
    $limit = $this->limit;
    $offset = $limit * ($page - 1);
    if(count($this->order)>0){
      $order = 'order by '.join(',', $this->order);
    }
    else {
      $order = '';
    }
    $rows = $this->db->array("select * from ${tablename} ${order} limit ${limit} offset ${offset}");
    $pkeys = $this->get_primary_key_field($tablename);
    $cols = $this->get_schema_field_unmasked($tablename);
    $schema = $this->get_schema_unmasked($tablename);

    echo '<table class="uk-text-nowrap" id="dbman"><thead>';
    foreach($cols as $col){
      if($this->label[$col]){
        $col = $this->label[$col];
      }
      echo "<th>${col}</th>\n";
    }

    echo "</thead>\n<tbody>\n";
    echo '<tr><form action="" method="POST">';
    foreach($schema as $s){
      $col = $s['Field'];
      $type = $this->get_input_type($s);
      if($type==='checkbox'){
        $value = 'value="1"';
        $class = 'uk-checkbox';
      }
      else {
        $value = '';
        $class = 'uk-input';
      }

      if($this->label[$col]){
        $label = $this->label[$col];
      }
      else {
        $label = $col;
      }
      echo "<td><input class=\"$class\" type=\"${type}\" name=\"${col}\" placeholder=\"${label}\" ${value}></td>\n";
    }
    echo '<td><button class="uk-button uk-button-primary" name="command" value="insert" type="submit">追加</button></td></form></tr>';
    foreach($rows as $row){
      ?>
      <form action="" method="POST">
      <tr>
      <?php
      foreach($pkeys as $key){
        $val = $row[$key];
        echo "<input type=\"hidden\" name=\"${key}\" value=\"${val}\">\n";
      }
      foreach($schema as $s){
        $col = $s['Field'];
        $value = $row[$col];
        $type = $this->get_input_type($s);
        if($this->label[$col]){
          $label = $this->label[$col];
        }
        else {
          $label = $col;
        }
        if(in_array($col, $pkeys)){
          $disabled = 'disabled';
        }
        else {
          $disabled = '';
        }
        if($type==='checkbox'){
          $class = 'uk-checkbox';
          if($value!='0'){
            $checked = 'checked';
          }
          else{
            $checked = '';
          }
          $value = '1';
        }
        else{
          $class = 'uk-input';
          $checked = '';
        }
        echo "<td><input class=\"$class\" type=\"${type}\" name=\"${col}\" value=\"${value}\" placeholder=\"${label}\" ${disabled} ${checked}></td>\n";
      }
      echo '<td><button class="uk-button uk-button-default" type="submit" name="command" value="update">更新</button> ';
      echo '<button class="uk-button uk-button-secondary" type="submit" name="command" value="confirm_delete">削除</button></td>';
      ?>
      </tr>
      </form>
      <?php
    }
    echo '</tbody></table><p>';
    if($page > 1){
      echo '<a class="uk-button uk-button-default" href="?page='.($page-1).'">&lt;&lt;前の'.$limit.'件</a> ';
    }
    echo '<a class="uk-button uk-button-default" href="?page='.($page+1).'">次の'.$limit.'件&gt;&gt;</a>';
  }

  function execute_command(){
    switch($_POST['command']) {
      case 'update':
        $this->update();
        break;
      case 'delete':
        $this->delete();
        break;
      case 'insert':
        $this->insert();
        break;
      case 'confirm_delete':
        $this->confirm_delete();
      default:
        break;
    }
  }
}


?>