# PDOWrapper

A useful PDO wrapper.

Classes:

* [phputil\PDOWrapper](https://github.com/thiagodp/pdowrapper/blob/master/lib/PDOWrapper.php)
* [phputil\PDOBuilder](https://github.com/thiagodp/pdowrapper/blob/master/lib/PDOBuilder.php)

This project uses [semantic version](http://semver.org/). See our [releases](https://github.com/thiagodp/pdowrapper/releases).

## Installation

```command
composer require phputil/pdowrapper
```

## Example 1

Creating `PDO` with `PDOBuilder` and counting rows with `PDOWrapper`.

```php
<?php
require_once 'vendor/autoload.php';
use \phputil\PDOBuilder;
use \phputil\PDOWrapper;

$pdo = PDOBuilder::with()
	->dsn( 'mysql:dbname=mydb;host=127.0.0.1;' )
	->username( 'myuser' )
	->password( 'mypass' )
	->modeException()
	->persistent()
	->mySqlUTF8()
	->build();

$pdoW = new PDOWrapper( $pdo );

echo 'Table "customer" has ', $pdoW->countRows( 'customer' ), ' rows.';
?>
```
### Example 2

Delete by id

```php
$id = $_GET[ 'id' ];
// ... <-- validate $id here
$deleted = $pdoW->deleteWithId( $id, 'customer' );
echo 'Deleted ', $deleted, ' rows.';
```

### Example 3

Paginated query

```php
$limit = $_GET[ 'limit' ];
$offset = $_GET[ 'offset' ];
// ... <-- validate $limit and $offset here
// makeLimitOffset returns a SQL clause depending of the used database.
// Currently supports MySQL, PostgreSQL, SQLite, HSQLDB, H2, Firebird, MS SQL Server,
// or an ANSI SQL 2008 database.
$pdoStatement = $pdo->execute( 'SELECT name FROM customer',
	$pdoW->makeLimitOffset( $limit, $offset ) );
echo 'Showing customers from ', $limit, ' to ', $offset, '<br />';
foreach ( $pdoStatement as $customer ) {
	echo $customer[ 'name' ], '<br />';
}
```

### Example 4

Query objects

```php
class User {
	private $id;
	private $name;

	function __construct( $id = 0, $name = '' ) {
		$this->id = $id;
		$this->name = $name;
	}

	function getId() { return $this->id; }
	function getName() { return $this->name; }
}

class UserRepositoryInRelationalDatabase {

	private $pdoW;

	function __construct( PDOWrapper $pdoW ) {
		$this->pdoW = $pdoW;
	}

	/**
	 * Return all the users, considering a limit and an offset.
	 * @return array of User
	 */
	function allUsers( $limit = 0, $offset = 0 ) { // throw
		// Paginated query
		$sql = 'SELECT * FROM user' .
			$this->pdoW->makeLimitOffset( $limit, $offset );
		// Call rowToUser to convert each row to a User
		return $this->pdoW->queryObjects( array( $this, 'rowToUser' ), $sql );
	}

	/**
	 * Converts a row into a User.
	 * @return User
	 */
	function rowToUser( array $row ) {
		return new User( $row[ 'id' ], $row[ 'name' ] );
	}
}

$limit = $_GET[ 'limit' ];
$offset = $_GET[ 'offset' ];
// ... <-- validate $limit and $offset here
$repository = new UserRepositoryInRelationalDatabase( $pdoW );
$users = $repository->allUsers( $limit, $offset );
foreach ( $users as $u ) {
	echo 'Name: ', $u->getName(), '<br />';
}
```


## Development

After cloning the repo, run `composer install` to install the dependencies.

How to run the test cases:
```shell
./vendor/bin/phpunit tests
```