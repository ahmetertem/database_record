<?php

namespace ahmetertem;

abstract class dbr
{
    public static $PHP_FAST_CACHE_PREFIX = null;
    public static $PHP_FAST_CACHE = null;
    public static $PDO = null;

    protected $_fields = array();
    protected $_table_name;
    protected $_id_field = null;
    protected $_where_extra_fields = array();
	protected $_cache_expire_seconds = 0;

    private $_parsed_fields = array();

    public function __construct()
    {
        if (self::$PDO == null) {
            throw new \Exception('PDO must be set as static variable!');
        }
    }

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
        if (is_null($this->_id_field) || $this->_id_field == null) {
            throw new \Exception('_id_field is null');
        }
		return $this->getByArgs(array($this->_id_field => $id));
    }

	public function getByArgs($fields)
	{
		if (is_null($this->_table_name) || $this->_table_name == null) {
            throw new \Exception('_table_name is null');
        }
		$this->parseFields();
        $row = null;
        if (self::$PHP_FAST_CACHE !== null) {
			$cache_key = self::$PHP_FAST_CACHE_PREFIX.$this->_table_name.'.';
			foreach($fields as $key => $value) {
				$cache_key .= $key.'.'.$value;
			}
            $cached_string = self::$PHP_FAST_CACHE->getItem($cache_key);
            $row = $cached_string->get();
        }
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
            $sth = self::$PDO->prepare($qb->getSelect());
            $sth->execute($execute);
            $row = $sth->fetch(\PDO::FETCH_ASSOC);
            if (self::$PHP_FAST_CACHE !== null) {
                $cached_string->set($row)->expiresAfter($this->_cache_expire_seconds);
                self::$PHP_FAST_CACHE->save($cached_string);
            }
        }
        if (method_exists($this, 'afterGet')) {
            $this->afterGet();
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
            } else {
				$this->$key = null;
			}
        }
    }

    public function insert()
    {
        if (is_null($this->_table_name) || $this->_table_name == null) {
            throw new \Exception('_table_name is null');
        }
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
        $result = self::$PDO->exec($qb->getInsert());
        if ($result == false) {
            $error = self::$PDO->errorInfo();
            throw new \Exception($error[2]);
        } else {
            if ($this->_id_field != null && intval($this->{$this->_id_field}) == 0) {
                $this->{$this->_id_field} = self::$PDO->lastInsertId();
            }
            if (method_exists($this, 'afterInsert')) {
                $this->afterInsert();
            }
            if (self::$PHP_FAST_CACHE !== null) {
                self::$PHP_FAST_CACHE->deleteItemsByTag($this->_table_name);
            }
        }
    }

    public function update()
    {
        if (is_null($this->_table_name) || $this->_table_name == null) {
            throw new \Exception('_table_name is null');
        }
        if (is_null($this->_id_field) || $this->_id_field == null) {
            throw new \Exception('_id_field is null');
        }
        $this->parseFields();
        if (self::$PHP_FAST_CACHE !== null) {
            self::$PHP_FAST_CACHE->deleteItem(self::$PHP_FAST_CACHE_PREFIX.$this->_table_name.'.'.$this->{$this->_id_field});
            self::$PHP_FAST_CACHE->deleteItemsByTag($this->_table_name);
        }
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
        $sth = self::$PDO->prepare($qb->getUpdate());
        $result = $sth->execute($exec);
        if ($result === false) {
            $error = self::$PDO->errorInfo();
            if (false) {
                echo '<pre>';
                var_dump($this);
                echo '</pre>';
            }
            throw new \Exception($error[2]);
        }
        if (method_exists($this, 'afterUpdate')) {
            $this->afterUpdate();
        }
    }

    public function delete($is_psychical = false)
    {
        if (is_null($this->_table_name) || $this->_table_name == null) {
            throw new \Exception('_table_name is null');
        }
        if (is_null($this->_id_field) || $this->_id_field == null) {
            throw new \Exception('_id_field is null');
        }
        if (self::$PHP_FAST_CACHE !== null) {
            self::$PHP_FAST_CACHE->deleteItem(self::$PHP_FAST_CACHE_PREFIX.$this->_table_name.'.'.$this->{$this->_id_field});
            self::$PHP_FAST_CACHE->deleteItemsByTag($this->_table_name);
        }
        $qb = new qb();
        $qb->table($this->_table_name)->where($this->_id_field, $this->{$this->_id_field})->setLimit(1);
        $execute = array();
        if (count($this->_where_extra_fields) > 0) {
            foreach ($this->_where_extra_fields as $ff) {
                $execute[$ff] = $this->$ff;
                $qb->where($ff, ':'.$ff);
            }
        }
        if ($is_psychical) {
            $sth = self::$PDO->prepare($qb->getDelete());
            $sth->execute($execute);
            if (method_exists($this, 'afterDelete')) {
                $this->afterDelete(true);
            }

            return;
        }
        $qb->set('is_deleted', 1, 1);
        self::$PDO->exec($qb->getUpdate());
        if (method_exists($this, 'afterDelete')) {
            $this->afterDelete();
        }
    }
}
