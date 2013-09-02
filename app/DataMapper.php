<?php

/**
 * Class for handling data manipulation
 * 
 */
class DataMapper {

    private $db;

    public function __construct() {
        $config = new ConfigIni();
        if (!$config->getConfigItem('dsn', 'DBconfig') || !$config->getConfigItem('username', 'DBconfig') || !$config->getConfigItem('password', 'DBconfig')) {
            throw new InvalidArgumentException('DB settings not found', 500);
        }

        $this->db = new PDO($config->getConfigItem('dsn', 'DBconfig'), $config->getConfigItem('username', 'DBconfig'), $config->getConfigItem('password', 'DBconfig'));

        // I hate default attributes. Bastards.
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function saveProperty($property) {

        if ($property->isValid()) {
            if ($property->propertyId) {
                $query = 'UPDATE property SET `address` = :address, `city` = :city, `zip` = :zip, `state` = :state WHERE propertyId = :propertyId';
            } else {
                $query = 'INSERT INTO property (`address`, `city`, `zip`, `state`) VALUES (:address, :city, :zip, :state)';
            }

            $stmt = $this->db->prepare($query);
            $res = $stmt->execute($property->toArray());

            if (!$res) {
                $error = $this->db->errorInfo();
                throw new Exception('Couldnt insert or update record. ErrorInfo was: ' . $error[2] . ' ' . $error[2], $error[0]);
            }
            if ($property->propertyId) {
                return $property->propertyId;
            }
            return $this->db->lastInsertId();
        }
        throw new InvalidArgumentException('Property does not contain valid data');
    }

    /**
     * Returns string of format like " WHERE 1=1 AND property.propertyId=3 AND property.city='Oceanside' ORDER BY property.city, property.zip ASC LIMIT 0,10"
     * where everything is defined by $filter array and $table contains optional table name that is prependent to every column name
     * @param array $filter
     * @param string $table
     */
    private function prepareFilter($filter, $table = '') {
        $where = '';
        $limit = '';
        $order = '';
        $whereArray = array();
        if (is_array($filter)) {
            if (isset($filter['where'])) {
                $where = ' WHERE 1=1 ';
                foreach ($filter['where'] as $column => $value) {
                    $where .= ' AND ' . (($table) ? $table . '.' : '') . $column . ' = :' . $column;
                    $whereArray[':' . $column] = $value;
                }
            }
            if (isset($filter['limit'])) {
                $limit = ' LIMIT ' . $filter['limit']['position'] . ', ' . $filter['limit']['count'];
            }
            if (isset($filter['order'])) {
                $order = ' ORDER BY ';
                foreach ($filter['order']['by'] as $by) {
                    $order .= $by . ', ';
                }

                // Drop last comma
                $order = substr($order, 0, -1);
                $order .= $filter['order']['direction'];
            }
        }
        return $where . $order . $limit;
    }

    /**
     * Returns list of records in property table joined with property_history according to filtering rules.
     * Should retrive the latest hitory for each record if available.
     * @param array $filter
     * @return type
     */
    public function getProperties($filter = NULL) {
        $where = $this->prepareFilter($filter, 'property');
        $whereArray = array();
        if (isset($filter['where'])) {
            foreach ($filter['where'] as $column => $value) {
                $whereArray[':' . $column] = $value;
            }
        }
        $query = 'SELECT property.propertyId,
            property.address,
            property.zip,
            property.state,
            property.city,
            ph.saleDate,
            ph.salePrice FROM property
            LEFT OUTER JOIN 
            (select propertyId, date_format(max(saleDate),"%m/%d/%Y") saleDate, salePrice from sale_history group by propertyId) 
            ph on property.propertyId = ph.propertyId' . $where;
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute($whereArray);
        while($row = $stmt->fetch()) {
            $history = new SaleHistory($row);
            $row['saleHistory'] = array(0=>$history);
            $properties[] = new Property($row);
        }
        return $properties;
    }

    /**
     * Returns array filled with property data and property history in form of 
     *      array('property'=>array('address'=>$address, 'city'=>$city, 'zip'=>$zip, 'state'=>$state, 'propertyId'=>'propertyId'),
     *            'history'=>array(0=>array('salePrice'=>$salePrice, 'saleDate'=>$saleDate)))
     * @param integer | string $propertyId
     * @return array
     */
    public function getProperty($propertyId) {
        $query = 'SELECT * FROM property WHERE propertyId=:propertyId';
        $prop_stmt = $this->db->prepare($query);
        $res = $prop_stmt->execute(array(':propertyId' => $propertyId));
        $query = 'SELECT saleHistoryId, propertyId, salePrice, date_format(saleDate, "%m/%d/%Y") saleDate FROM sale_history WHERE propertyId=:propertyId';
        $hist_stmt = $this->db->prepare($query);
        $res = $hist_stmt->execute(array(':propertyId' => $propertyId));
        $propertyArray = $prop_stmt->fetch();
        $historiesArray = $hist_stmt->fetchAll();
        $histories = array();
        if (!empty($historiesArray)) {
            foreach ($historiesArray as $historyArray) {
                $histories[] = new SaleHistory($historyArray);
            }
        }
        $propertyArray['saleHistory'] = $histories;
        $property = new Property($propertyArray);
        return $property;
    }

    public function deleteProperty($propertyId) {
        $query = 'DELETE FROM property WHERE propertyId = :propertyId';
        $stmt = $this->db->prepare($query);
        return $stmt->execute(array(':propertyId' => $propertyId));
    }

    public function addSale($history) {
        if ($history->saleHistoryId) {
            // Update existing record
            $query = 'UPDATE sale_history SET propertyId = :propertyId, salePrice = :salePrice, saleDate = :saleDate WHERE saleHistoryId = :saleHistoryId';
        } else {
            //Add new record
            $query = 'INSERT INTO sale_history (propertyId, salePrice, saleDate) VALUES(:propertyId, :salePrice, :saleDate)';
        }
        $stmt = $this->db->prepare($query);
        $res = $stmt->execute($history->toArray());
        if (!$res) {
            throw new Exception('Something wrong ');
        }
        
        if ($history->saleHistoryId) {
            return $history->saleHistoryId;
        }
        return $this->db->lastInsertId();
    }

    
    public function getNumberOfRecords($table, $filter = NULL) {
        $idName = $table . 'Id';
        $where = $this->prepareFilter($filter, $table);
        $query = 'SELECT count(' . $idName .') FROM ' . $table . $where;
        $stmt = $this->db->prepare($query);
        $res = $stmt->execute();
        return $stmt->fetchColumn();
    }
}

?>