<?php

namespace ahmetertem;

class dbrs
{
    protected $_records = array();
    protected $_table_name;
    protected $_id_field = null;
    protected $_where_extra_fields = array();
    private $_instance_of = null;

    private $_parsed_fields = array();

    public function __construct($instance_of)
    {
        if (dbr::$PDO == null) {
            throw new \Exception('PDO must be set as static variable!');
        }
        $this->_instance_of = $instance_of;
    }

    public function getByQb(qb $qb)
    {
        $rows = null;
        if (dbr::$PHP_FAST_CACHE !== null) {
            $cached_string = dbr::$PHP_FAST_CACHE->getItem(dbr::$PHP_FAST_CACHE_PREFIX.$qb->getSelect());
            $rows = $cached_string->get();
        }
        if ($rows == null) {
            $sth = dbr::$PDO->prepare($qb->getSelect());
            $sth->execute();
            $rows = array();
            while ($row = $sth->fetch(\PDO::FETCH_ASSOC)) {
                $x = new $this->_instance_of();
                $x->fillFromArray($row);
                $rows[] = $x;
            }
            if (dbr::$PHP_FAST_CACHE !== null) {
                $cached_string->set($rows);
                $cached_string->addTag($qb->table(null));
                dbr::$PHP_FAST_CACHE->save($cached_string);
            }
        }

        return $rows;
    }
}
