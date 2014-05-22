<?php
class Model {

    private $dbh;
    private $stmt;

    public function __construct(){
        global $config;
        try {
            $dsn = 'mysql:host='.$config['db_host'].';dbname='.$config['db_name'];
            $options = array(
                PDO::ATTR_PERSISTENT => true,
                PDO::ATTR_ERRMODE    => PDO::ERRMODE_EXCEPTION
            );
            $this->dbh = new PDO($dsn, $config['db_username'], $config['db_password'], $options);
        }
        catch(PDOException $e){
            exit($e->getMessage());
        }
    }

    public function insertOne($table, $data){
        $fields = '('.implode(', ', array_keys($data)).')';
        $binded_data = array();
        foreach($data as $k => $v){
            $binded_data[':'.strtolower($k)] = $v;
        }
        $bind_fields = '('.implode(', ', array_keys($binded_data)).')';
        $query = 'INSERT INTO '.$table.' '.$fields.' VALUES '.$bind_fields;
        $this->query($query);
        foreach($binded_data as $field => $value){
            $this->bind($field, $value);
        }
        $this->execute();
        return $this->lastInsertId();
    }

    public function insertMultiple($table, $all_data){
        $inserted_ids = array();
        $this->beginTransaction();
        try {
            foreach($all_data as $data){
                array_push($inserted_ids, $this->insertOne($table, $data));
            }
            $this->endTransaction();
        }
        catch(PDOException $e){
            $this->cancelTransaction();
        }
        return $inserted_ids;
    }

    private function select($table, $fields, $where=null, $other=null){
        $fields = !is_array($fields) ? array($fields) : $fields;
        $fields = implode(', ', $fields);
        $where_query = '';
        if($where != null){
            $where_query .= ' WHERE ';
            $i = 1;
            foreach($where as $field => $value){
                $where_query .= $field.'=:'.strtolower($field);
                if($i < sizeof($where)){
                    $where_query .= ' AND ';
                }
            }
        }
        $query = 'SELECT '.$fields.' FROM '.$table.$where.' '.$other;
        $this->query($query);
        if($where != null){
            foreach($where as $field => $value){
                $this->bind(':'.strtolower($field), $value);
            }
        }
    }

    public function selectOne($table, $fields, $where=null, $other=null){
        $this->select($table, $fields, $where=null, $other=null);
        return $this->single();
    }

    public function selectAll($table, $fields, $where=null, $other=null){
        $this->select($table, $fields, $where=null, $other=null);
        return $this->resultSet();
    }

    private function query($query){
        $this->stmt = $this->dbh->prepare($query);
    }

    private function bind($param, $value, $type=null){
        if(is_null($type)){
            switch (true){
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
        }
        $this->stmt->bindValue($param, $value, $type);
    }

    public function execute(){
        return $this->stmt->execute();
    }

    public function resultSet(){
        $this->execute();
        return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function single(){
        $this->execute();
        return $this->stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function rowCount(){
        return $this->stmt->rowCount();
    }

    public function lastInsertId(){
        return $this->dbh->lastInsertId();
    }

    public function beginTransaction(){
        return $this->dbh->beginTransaction();
    }

    public function endTransaction(){
        return $this->dbh->commit();
    }

    public function cancelTransaction(){
        return $this->dbh->rollBack();
    }

    public function dump(){
        return $this->stmt->debugDumpParams();
    }

    /**
     * Utilities
     */
    public function to_bool($val){
        return !!$val;
    }
    public function to_date($val){
        return date('Y-m-d', $val);
    }
    public function to_time($val){
        return date('H:i:s', $val);
    }
    public function to_datetime($val){
        return date('Y-m-d H:i:s', $val);
    }

}