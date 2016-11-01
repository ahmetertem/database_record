<?php

namespace ahmetertem;

class dbr
{
    protected $_fields = array();
    protected $_table_name;
    protected $_id_field = null;
    protected $_where_extra_fields = array();

    private $_parsed_fields = array();

    protected function parseFields()
    {
        $this->_parsed_fields = array();
        foreach ($this->_fields as $key => $value) {
            if (is_numeric($key)) {
                $this->_parsed_fields[$value] = $value;
            } else {
                $this->_parsed_fields[$key] = $value;
            }
        }
    }

    public function getById($id)
    {
        if (is_null($this->_table_name) || $this->_table_name == null) {
            throw new Exception('_table_name is null');
        }
        if (is_null($this->_id_field) || $this->_id_field == null) {
            throw new Exception('_id_field is null');
        }
        global $app;
        $this->parseFields();
        // must be commented !!!
        // $this->{$this->_id_field} = $id;
        $cached_string = $app->CACHE->getItem($this->_table_name.'.'.$id);
        $row = $cached_string->get();
        if ($row == null) {
            $qb = new qb();
            $execute = array('id' => $id);
            $qb->table($this->_table_name)
                ->where($this->_id_field, ':id')
                ->limit = 1;
            if (count($this->_where_extra_fields) > 0) {
                foreach ($this->_where_extra_fields as $ff) {
                    $execute[$ff] = $this->$ff;
                    $qb->where($ff, ':'.$ff);
                }
            }
            $sth = $app->DB->prepare($qb->getSelect());
            $sth->execute($execute);
            $row = $sth->fetch(PDO::FETCH_ASSOC);
            $cached_string->set($row);
            $app->CACHE->save($cached_string);
        }
        if ($row == false) {
            return false;
        }
        $this->fillFromArray($row);
    }

    public function fillFromArray($array)
    {
        $this->parseFields();
        foreach ($this->_parsed_fields as $key => $value) {
            if (isset($array[$value])) {
                $this->$key = $array[$value];
            }
        }
    }

    public function insert()
    {
        if (is_null($this->_table_name) || $this->_table_name == null) {
            throw new Exception('_table_name is null');
        }
        global $app;
        $this->parseFields();
        $qb = new qb();
        $qb->table($this->_table_name);
        foreach ($this->_parsed_fields as $key => $value) {
            if (!is_null($this->$key)) {
                $val = $this->$key;
                if (is_bool($val)) {
                    $val = $val ? 1 : 0;
                }
                $qb->set($value, $val, $val == '(NULL)' || is_numeric($val) ? 1 : 0);
            }
        }
        $result = $app->DB->exec($qb->getInsert());
        if ($result == false) {
            $error = $app->DB->errorInfo();
            throw new Exception($error[2]);
        } else {
            if ($this->_id_field != null && intval($this->{$this->_id_field}) == 0) {
                $this->{$this->_id_field} = $app->DB->lastInsertId();
            }
        }
    }

    public function update()
    {
        if (is_null($this->_table_name) || $this->_table_name == null) {
            throw new Exception('_table_name is null');
        }
        if (is_null($this->_id_field) || $this->_id_field == null) {
            throw new Exception('_id_field is null');
        }
        global $app;
        $this->parseFields();
        $app->CACHE->deleteItem($this->_table_name.'.'.$this->{$this->_id_field});
        $qb = new qb();
        $qb->table($this->_table_name);
        foreach ($this->_parsed_fields as $key => $value) {
            if (!is_null($this->$key)) {
                $val = $this->$key;
                if (is_bool($val)) {
                    $val = $val ? 1 : 0;
                }
                $qb->set($value, $val, $val == '(NULL)' || is_numeric($val) ? 1 : 0);
            }
        }
        $qb->where($this->_id_field, $this->{$this->_id_field})->limit = 1;
        $exec = array();
        foreach ($this->_where_extra_fields as $ff) {
            $qb->where($ff, ':'.$ff);
            $exec[$ff] = $this->$ff;
        }
        $sth = $app->DB->prepare($qb->getUpdate());
        $result = $sth->execute($exec);
        if ($result === false) {
            $error = $app->DB->errorInfo();
            if (false) {
                echo '<pre>';
                var_dump($this);
                echo '</pre>';
            }
            throw new Exception($error[2]);
        }
    }

    public function delete($is_psychical = false)
    {
        if (is_null($this->_table_name) || $this->_table_name == null) {
            throw new Exception('_table_name is null');
        }
        if (is_null($this->_id_field) || $this->_id_field == null) {
            throw new Exception('_id_field is null');
        }
        global $app;
        $app->CACHE->deleteItem($this->_table_name.'.'.$this->{$this->_id_field});
        $qb = new qb();
        $qb->table($this->_table_name)->where($this->_id_field, $this->{$this->_id_field})->setLimit(1);
        if ($is_psychical) {
            $app->DB->exec($qb->getDelete());
        }
        $qb->set('is_deleted', 1, 1);
        $app->DB->exec($qb->getUpdate());
    }
}
