<?php
namespace phputil;

/**
 *  PDO Builder.
 *  
 *  @author		Thiago Delgado Pinto
 *  
 *  @see		http://www.php.net/manual/en/pdo.connections.php
 *  @see		http://php.net/manual/en/pdo.setattribute.php
 *  @see		http://php.net/manual/en/pdo.constants.php
 *  
 *  Example on how to use it:
 *  
 *  	<?php
 *  	require_once 'vendor/autoload.php';
 *  	use \phputil\PDOBuilder;
 *  
 *  	$pdo = PDOBuilder::with()
 *  		->dsn( 'mysql:dbname=mydb;host=127.0.0.1;' )
 *  		->username( 'myuser' )
 *  		->password( 'mypass' )
 *  		->modeException()
 *  		->persistent()
 *  		->mySqlUTF8()
 *  		->build();
 *  	?>
 *  
 */
class PDOBuilder {
	
	private $dsn = '';
	private $username = '';
	private $password = '';
	private $options = array();
	private $modeException = false;
	
	static function with() {
		return new PDOBuilder();
	}
	
	/**
	 *	Build the PDO. This is the last method in the sequence you should call.  
	 *
	 *  @return \PDO
	 *  @throws \PDOException
	 */
	function build() {
		$pdo = new \PDO( $this->dsn, $this->username, $this->password, $this->options );
		if ( $this->modeException ) {
			$pdo->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
		}
		return $pdo;
	}
	
	// Basic data
	
	function dsn( $dsn ) {
		$this->dsn = $dsn;
		return $this;
	}
	
	function username( $username ) {
		$this->username = $username;
		return $this;
	}
	
	function password( $password ) {
		$this->password = $password;
		return $this;
	}
	
	function options( $options ) {
		$this->options = is_array( $options ) ? $options : array();
		return $this;
	}
	
	// Mode Exception
	
	function modeException( $b = true ) {
		$this->modeException = $b;
		return $this;
	}	
	
	function inModeException() {
		return $this->modeException( true );
	}
	
	function notInModeException() {
		return $this->modeException( false );
	}
	
	// Persistent
	
	function persistence( $b = true ) {
		$this->ensureOptionsIsArray();
		$this->options[ \PDO::ATTR_PERSISTENT ] = $b;
		return $this;
	}	
	
	function persistent() {
		return $this->persistence( true );
	}
	
	function notPersistent() {
		return $this->persistence( false );
	}
	
	// MySQL
	
	function mySqlUTF8() {
		$this->ensureOptionsIsArray();
		$this->options[ \PDO::MYSQL_ATTR_INIT_COMMAND ] = 'SET NAMES utf8';
		return $this;
	}
	
	//
	// PRIVATE
	//
	
	private function ensureOptionsIsArray() {
		if ( ! is_array( $this->options ) ) {
			$this->options = array();
		}		
	}
}

?>