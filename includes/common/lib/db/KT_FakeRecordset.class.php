<?php
/*
	Copyright (c) InterAKT Online 2000-2005
*/

/**
 * The FakeRecordset class
 */
class KT_FakeRecordset {

	/**
	 * The connection
	 * @var object Connection
	 * @access private
	 */
	var $connection = null;
	
	/**
	 * The fields on wich we create a fake recordset
	 * @var array fields
	 * @access private
	 */
	var $fields = null;
	
	/**
	 * The name of the temporary table
	 * @var array fields
	 * @access private
	 */
	var $tmpTableName = 'KT_fakeRS';
	
	/**
	 * If there were errors
	 * @var bool hasError
	 * @access public
	 */
	var $hasError = false;
	
	/**
	 * The error
	 * @var string error
	 * @access private
	 */
	var $error = '';

	/**
	 * The constructor
	 * @param object Connection &$connection - either a ADODB Connection or a KT_Connection object
	 * @access public
	 */
	function KT_FakeRecordset(&$connection) {
		$this->connection = &$connection;
		if (!isset($GLOBALS['KT_serverModel'])) {
			KT_setDbType($this->connection);
		}
	}
	
	function getFakeRecordset(&$fields) {
		$this->fields = $fields;
		
		if (!is_array($this->fields)) {
			$this->error = KT_getResource('PHP_DB_ARG_NOT_ARRAY_D','DB');
			$this->hasError = true;
			return null;
		}
		
		if ($GLOBALS['KT_serverModel'] == 'mysql') {
			return $this->getMySQLfakeRS();
		} else {
			return $this->getADODBfakeRS();
		}
	}
	
	function getMySQLfakeRS() {
		$this->tmpTableName = $this->tmpTableName . '_' . date('Ymd');
		$test_create_sql = 'CREATE TEMPORARY TABLE ' . $this->tmpTableName . ' (kt_test TEXT)';
		$delete_sql = 'DROP TABLE ' . $this->tmpTableName;
		$create_sql = 'CREATE TEMPORARY TABLE ' . $this->tmpTableName . ' (';
		$insert_sql = 'INSERT INTO ' . $this->tmpTableName . ' (';
		$select_sql = 'SELECT * FROM ' . $this->tmpTableName;
		$insert_values = '';
		$result = '';
		$doInsert = true;
		$multiple = false;
		
		if ( count($this->fields) == 0 ) {
			// empty fake rs
			$doInsert = false;
			$this->fields['KT_fakeField'] = '';
		}
		
		$this->connection->Execute($delete_sql);
		$response = $this->connection->Execute($test_create_sql);
		if ($response === false) {
			return $this->getMySQL4fakeRS();
		}
		
		foreach ($this->fields as $key => $value) {
			$create_sql .= $key . ' TEXT, ';
			$insert_sql .= $key . ', ';
			if (!is_array($value)) {
				$insert_values .= "'" . mysql_escape_string($value) . "', ";
			} else {
				$multiple = true;
			}
		}
		
		$create_sql = substr(trim($create_sql), 0, -1) . ')';
		$insert_sql = substr(trim($insert_sql), 0, -1) . ') VALUES (';
		if ($multiple) {
			$multiple_values = array();
			foreach ($this->fields as $key => $values) {
				$i = 0;
				foreach ($values as $key => $value) {
					if (!isset($multiple_values[$i])) {
						$multiple_values[$i] = '';
					}
					$multiple_values[$i] .= "'" . mysql_escape_string($value) . "', ";
					$i++;
				}
			}
			for ($i = 0; $i < count($multiple_values); $i++) {
				$multiple_values[$i] = substr(trim($multiple_values[$i]), 0, -1);
			}
			$insert_values = implode('), (', $multiple_values);
		} else {
			$insert_values = substr(trim($insert_values), 0, -1);
		}
		$insert_sql .=  $insert_values . ')';
		
		$this->connection->Execute($delete_sql);
		$response = $this->connection->Execute($create_sql);
		if ($response === false) {
			$this->error = KT_getResource('PHP_DB_CREATE_TMP_D','DB',array($this->connection->ErrorMsg(), $create_sql));
			$this->hasError = true;
			return null;
		}
		if ($doInsert) {
			$response = $this->connection->Execute($insert_sql);
			if ($response === false) {
				$this->error = KT_getResource('PHP_DB_INSERT_TMP_D','DB',array($this->connection->ErrorMsg(), $insert_sql));
				$this->hasError = true;
				return null;
			}
		}
		$result = $this->connection->MySQL_Execute($select_sql);
		$response = $this->connection->Execute($delete_sql);
		if ($response === false) {
			$this->error = KT_getResource('PHP_DB_DROP_TMP_D','DB',array($this->connection->ErrorMsg(), $delete_sql));
			$this->hasError = true;
			return null;
		}
		return $result;
	}
	
	function getMySQL4fakeRS() {
		$fields = array();
		$i = 0;
		$select_sql = '';
		
		foreach ($this->fields as $key => $value) {
			$i = 0;
			if (!is_array($value)) {
				$fields[$i][$key] = $value;
			} else {
				foreach ($value as $key2 => $val) {
					$fields[$i][$key] = $val;
					$i++;
				}
			}
		}
		
		for ($i = 0; $i < count($fields); $i++) {
			$row = $fields[$i];
			if ($i > 0) {
				$select_sql .= ' UNION ALL ';
			}
			$select_sql .= 'SELECT ';
			foreach ($row as $colName => $value) {
				$select_sql .=  "'" . mysql_escape_string($value) . "' AS " . $colName . ", ";
			}
			$select_sql = substr(trim($select_sql), 0, -1);
		}
		
		$result = $this->connection->MySQL_Execute($select_sql);
		if ($result === false) {
			$this->error = KT_getResource('PHP_DB_SELECT_UNION_D','DB',array($this->connection->ErrorMsg(), $select_sql));
			$this->hasError = true;
			return null;
		}
		return $result;
	}
	
	function getADODBfakeRS() {
		$result = '';
		$fields = array();
		
		$j = 0;
		foreach ($this->fields as $key => $value) {
			$i = 0;
			if (!is_array($value)) {
				$fields[$i][$key] = $value;
				$fields[$i][$j] = $value;
			} else {
				foreach ($value as $key2 => $val) {
					$fields[$i][$key] = $val;
					$fields[$i][$j] = $val;
					$i++;
				}
			}
			$j++;
		}
		
		$result = new KT_fakeADORecordset($fields);
		return $result;
	}
	
	function getError() {
		return $this->error;
	}
}

/**
 * util KT_fakeADORecordset class
 */
class KT_fakeADORecordset {
	
	var $allFields = array();
	var $fields = array();
	var $index = 0;
	var $EOF = true;
	var $_numOfRows = 0;
	var $_numOfFields = 0;
	
	function KT_fakeADORecordset(&$fields) {
		if ( is_array($fields) && count($fields) > 0 ) {
			$this->allFields = $fields;
			$this->fields = $fields[0];
			$this->EOF = false;
			$this->_numOfRows = count($fields);
			$this->_numOfFields = count($fields[0]) / 2;
		}
	}
	
	function MoveNext() {
		$this->index++;
		if (isset($this->allFields[$this->index])) {
			$this->fields = $this->allFields[$this->index];
		} else {
			$this->EOF = true;
		}
	}
	
	function Fields($colName) {
		return $this->fields[$colName];
	}

	function RecordCount() {
		return $this->_numOfRows;
	}

	function FieldCount() {
		return $this->_numOfFields;
	}

}

?>