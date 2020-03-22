<?php
namespace phputil;

/**
 * A wrapper for PDO.
 *
 * @see \PDO
 *
 * @author	Thiago Delgado Pinto
 */
class PDOWrapper {

	private $pdo;

	function __construct( \PDO $pdo ) {
		$this->pdo = $pdo;
	}

	/**
	 * @return \PDO
	 */
	function getPDO() {
		return $this->pdo;
	}

	/**
	 * Generates an ID for a table based on its MAX value.
	 *
	 * @param string $tableName		Table name.
	 * @param string $idFieldName	Name of the id field (optional).
	 * @return int
	 */
	function generateId( $tableName, $idFieldName = 'id' ) { // throws
		return 1 + $this->lastId( $tableName, $idFieldName );
	}

	/**
	 * Returns the last id for the given table or zero if there are no records.
	 *
	 * @param string $tableName		Table name.
	 * @param string $idFieldName	Name of the id field (optional).
	 * @return int
	 */
	function lastId( $tableName, $idFieldName = 'id' ) { // throws
		$maxColumn = 'M_A_X_';
		$cmd = "SELECT MAX( $idFieldName ) AS '$maxColumn' FROM $tableName";
		$result = $this->query( $cmd );
		if ( is_array( $result ) && count( $result ) > 0 && isset( $result[ 0 ][ $maxColumn ] ) ) {
			return (int) $result[ 0 ][ $maxColumn ];
		} else if ( is_object( $result ) ) {
			return (int) $result->{ $maxColumn };
		}
		return 0;
	}

	/**
	 * Deletes a record by its id and returns the number of affected rows.
	 *
	 * @param int $id				Id value.
	 * @param string $tableName		Table name.
	 * @param string $idFieldName	Name of the id field (optional).
	 * @return int
	 */
	function deleteWithId( $id, $tableName, $idFieldName = 'id' ) { // throws
		$cmd = "DELETE FROM $tableName WHERE $idFieldName = ?";
		return $this->run( $cmd, array( $id ) );
	}

	/**
	 * Counts the number of rows of a table.
	 *
	 * @param string $tableName		Table name.
	 * @param string $idFieldName	Name of the id field (optional).
	 * @param string $whereClause	SQL WHERE clause (optional).
	 * @param array $parameters		Parameters for the where clause (optional).
	 * @return int
	 */
	function countRows(
		$tableName,
		$idFieldName = 'id',
		$whereClause = '',
		array $parameters = array()
		) {	// throws
		$countColumn = 'C_O_U_N_T_';
		$cmd = "SELECT COUNT( $idFieldName ) AS '$countColumn' FROM $tableName $whereClause" ;
		$result = $this->query( $cmd, $parameters );
		if ( is_array( $result ) && count( $result ) > 0 && isset( $result[ 0 ][ $countColumn ] ) ) {
			return (int) $result[ 0 ][ $countColumn ];
		} else if ( is_object( $result ) ) {
			return (int) $result->{ $countColumn };
		}
		return 0;
	}

	/**
	 * Makes LIMIT and OFFSET clauses for a SQL statement.
	 *
	 * @param int $limit	Maximum number of records to retrieve (optional).
	 * @param int $offset	Number of records to ignore/jump (optional).
	 * @return string
	 * @throws \InvalidArgumentException
	 *
	 * IMPORTANT: Different databases use different SQL dialects. Please see if your database is
	 * supported.<br />
	 *
	 * <h3>Currently supported databases:</h3>
	 * <ul>
	 *	<li>MySQL</li>
	 *	<li>PostgreSQL</li>
	 *	<li>Firebird</li>
	 *	<li>SQLite</li>
	 *	<li>DB2</li>
	 *	<li>MS SQL Server 2008 (just for limit)</li>
	 * </ul>
	 *
	 * @see http://troels.arvin.dk/db/rdbms/
	 * @see http://www.jooq.org/doc/3.0/manual/sql-building/sql-statements/select-statement/limit-clause/
	 * on the support about limit and offset in different relational databases.
	 */
	function makeLimitOffset( $limit = 0, $offset = 0 ) { // throw
		if ( ! is_integer( $limit ) ) throw new \InvalidArgumentException( 'Limit is not a number.' );
		if ( ! is_integer( $offset ) ) throw new \InvalidArgumentException( 'Offset is not a number.' );
		$sql = '';
		$drv = $this->driverName();
		// Limit clause
		if ( $limit > 0 ) {
			// MySQL, PostgreSQL, SQLite, HSQLDB, H2
			if (   $this->isMySQL( $drv )
				|| $this->isPostgreSQL( $drv )
				|| $this->isSQLite( $drv )
				) $sql .= " LIMIT $limit ";
			// Firebird
			else if ( $this->isFirebird ) $sql .= " FIRST $limit ";
			// MS SQL Server 2008+
			else if ( $this->isSQLServer( $drv ) ) $sql .= " TOP $limit ";
			// IBM DB2, ANSI-SQL 2008
			else $sql .= " FETCH FIRST $limit ROWS ONLY ";
		}
		// Offset clause
		if ( $offset > 0 ) {
			// MySQL, PostgreSQL, SQLite, HSQLDB, H2
			if (   $this->isMySQL( $drv )
				|| $this->isPostgreSQL( $drv )
				|| $this->isSQLite( $drv )
				) {
				if ( $limit > 0 ) $sql .= " OFFSET $offset ";
				else $sql .= " LIMIT 9999999999999 OFFSET $offset "; // OFFSET needs a LIMIT
			// Firebird
			} else if ( $this->isFirebird ) $sql .= " SKIP $offset ";
			// IBM DB2, ANSI-SQL 2008
			else $sql .= " OFFSET $offset ROWS ";
		}
		return $sql;
	}

	/**
	 * Fetches objects from the rows returned by the given query.
	 *
	 * @param string $sql 				SQL query.
	 * @param array $parameters			Query parameters (optional).
	 * @param string $className			Name of the class used to create objects (optional).
	 * @param array $constructorArgs	Constructor arguments (optional).
	 * @return array
	 *
	 * Whether the class is defined, the objects' private attributes will
	 * receive the columns' values.
	 * Whether the class does not have a private attribute with the same name
	 * of a column, a public attribute will be created and it will receive the
	 * respective value.
	 *
	 * Usage:
	 * <code>
	 *	$users = $pdoW->fetchObjects( 'SELECT * FROM user', array(), 'User' );
	 * </code>
	 */
	function fetchObjects( $sql, array $parameters = array(), $className = '', $constructorArgs = array() ) {
		$ps = $this->execute( $sql, $parameters );
		return $this->fetchObjectsFromStatement( $ps, $className, $constructorArgs );
	}

	/**
	 * Fetches objects from a prepared statement.
	 *
	 * @param \PDOStatement $ps			Prepared statement.
	 * @param string $className			Class name for the objects (optional).
	 * @param array $constructorArgs	Constructor arguments (optional).
	 * @return array
	 */
	function fetchObjectsFromStatement( \PDOStatement $ps, $className = '', $constructorArgs = array() ) {
		$fetchMode = \PDO::FETCH_OBJ;
		if ( $className != '' ) {
			$fetchMode = \PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE;
			$ps->setFetchMode( $fetchMode, $className, $constructorArgs );
		}
		$objects = array();
		while ( ( $obj = $ps->fetch( $fetchMode ) ) !== false ) {
			array_push( $objects, $obj );
		}
		return $objects;
	}

	/**
	 * Performs a query and returns an array of objects.
	 *
	 * @param callable $recordToObjectCallback	Callback to transform a record into an object.
	 * @param string $sql						Query to execute.
	 * @param array $parameters					Query parameters (optional).
	 * @param array $callbackArguments			Callback arguments (optional).
	 * @return array
	 *
	 * How to use it:< br />
	 * <code>
	 * class UserRepositoryInRDB { // User repository in a relational database
	 * 		...
	 *		private function rowToUser( array $row ) {
	 *			// Creates a User object with a login and a password
	 *			return new User( $row[ 'login' ], $row[ 'password' ] );
	 *		}
	 *
	 *		public function allUsers($limit = 0, $offset = 0) { // throw
	 *			$query = 'SELECT * FROM user' . $this->makeLimitOffset( $limit, $offset );
	 *			return $this->pdoWrapper->queryObjects( array( $this, 'rowToUser' ), $query );
	 *		}
	 * }
	 * </code>
	 *
	 */
	function queryObjects(
			$recordToObjectCallback,
			$sql,
			array $parameters = array(),
			array $callbackArguments = array()
		) { // throws
		$objects = array();
		$ps = $this->execute( $sql, $parameters );
		foreach ( $ps as $row ) {
			array_unshift($callbackArguments, $row);
			$obj = call_user_func_array( $recordToObjectCallback, $callbackArguments ); // Transform a row into an object with arguments to callback function
			array_push( $objects, $obj );
		}
		return $objects;
	}

	/**
	 * Returns an object with the given id or null if not found.
	 *
	 * @param callable $recordToObjectCallback	Callback to transform a record into an object.
	 * @param int $id							Id value.
	 * @param string $tableName					Table name.
	 * @param string $idFieldName				Name of the id field (optional).
	 * @return object | null
	 */
	function objectWithId( $recordToObjectCallback, $id, $tableName, $idFieldName = 'id' ) {
		$cmd = "SELECT * FROM $tableName WHERE $idFieldName = ?";
		$params = array( $id );
		$objects = $this->queryObjects( $recordToObjectCallback, $cmd, $params );
		if ( count( $objects ) > 0 ) {
			return $objects[ 0 ];
		}
		return null;
	}

	/**
	 * Returns all the records as objects.
	 *
	 * @param callable $recordToObjectCallback	Callback to transform a record into an object.
	 * @param string $tableName					Table name.
	 * @param int $limit						Maximum number of records to retrieve (optional).
	 * @param int $offset						Number of records to ignore/jump (optional).
	 * @return array
	 */
	function allObjects( $recordToObjectCallback, $tableName, $limit = 0, $offset = 0 ) {
		$cmd = "SELECT * FROM $tableName" . $this->makeLimitOffset( $limit, $offset );
		return $this->queryObjects( $recordToObjectCallback, $cmd );
	}

	/**
	 * Runs a command with the supplied parameters and return the number of affected rows.
	 *
	 * @param string $command	Command to run.
	 * @param array $parameters	Parameters for the command (optional).
	 * @return int
	 */
	function run( $command, array $parameters = array() ) { // throws
		$ps = $this->execute( $command, $parameters );
		return $ps->rowCount();
	}

	/**
	 * Runs a query with the supplied parameters and return an array of rows.
	 *
	 * @param query			the query to run.
	 * @param parameters	the array of parameters for the query.
	 * @return array		an array of rows.
	 */
	function query( $query, array $parameters = array() ) { // throws
		$ps = $this->execute( $query, $parameters );
		return $ps->fetchAll();
	}

	/**
	 * Executes a command with the supplied parameters and return a PDOStatement object.
	 *
	 * @param command			the command to execute.
	 * @param parameters		the array of parameters for the command.
	 * @return \PDOStatement	a {@code PDOStatement} object.
	 */
	function execute( $command, array $parameters = array() ) { // throws
		$ps = $this->pdo->prepare( $command );
		if ( ! $ps || ! $ps->execute( $parameters ) ) {
			throw new \RuntimeException( 'SQL error: ' . $command );
		}
		return $ps;
	}

	// UTIL

	/**
	 * Returns the last inserted id.
	 *
	 * @param string $name	Name of the sequence (optional).
	 * @return string
	 */
	function lastInsertId( $name = null ) {
		return $this->pdo->lastInsertId( $name );
	}

	// TRANSACTION

	function inTransaction() {
		return $this->pdo->inTransaction();
	}

	function beginTransaction() {
		if ( ! $this->inTransaction() ) {
			$this->pdo->beginTransaction();
		}
	}

	function commit() {
		if ( $this->inTransaction() ) {
			$this->pdo->commit();
		}
	}

	function rollBack() {
		if ( $this->inTransaction() ) {
			$this->pdo->rollBack();
		}
	}

	// ATTRIBUTES

	function driverName() {
		return $this->pdo->getAttribute( constant( 'PDO::ATTR_DRIVER_NAME' ) );
	}

	private function isDriverName( $expected, $value = '' ) {
		$driverName = empty( $value ) ? $this->driverName() : $value;
		return $expected === $driverName;

	}

	function isMySQL( $driverName = '' ) {
		return $this->isDriverName( 'mysql', $driverName );
	}

	function isFirebird( $driverName = '' ) {
		return $this->isDriverName( 'firebird', $driverName );
	}

	function isPostgreSQL( $driverName = '' ) {
		return $this->isDriverName( 'pgsql', $driverName );
	}

	function isSQLite( $driverName = '' ) {
		return $this->isDriverName( 'sqlite', $driverName )
			|| $this->isDriverName( 'sqlite2', $driverName );
	}

	function isSQLServer( $driverName = '' ) {
		return $this->isDriverName( 'sqlsrv', $driverName );
	}

	function isOracle( $driverName = '' ) {
		return $this->isDriverName( 'oci', $driverName );
	}

	function isDB2( $driverName = '' ) {
		return $this->isDriverName( 'ibm', $driverName );
	}

	function isODBC( $driverName = '' ) {
		return $this->isDriverName( 'odbc', $driverName );
	}
}
?>