<?php
require_once 'vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use phputil\PDOWrapper;


class Person {

    public $id;
    public $name;
    public $age;

    function __construct( $id, $name, $age ) {
        $this->id = $id;
        $this->name = $name;
        $this->age = $age;
    }
}

const PERSON_DATA = array(
    array( 'id' => 1, 'name' => 'Bob', 'age' => 20 ),
    array( 'id' => 2, 'name' => 'Alice', 'age' => 21 )
);

const TEST_TABLE = 'person';
const TEST_CLASS = 'Person';

/**
 * Tests `\phputil\PDOWrapper`.
 *
 * @see \phputil\PDOWrapper
 * @author Thiago Delgado Pinto
 */
class PDOWrapperTest extends TestCase {

    /** @var \phputil\PDOWrapper */
    private $pdoW = null; // under test

    /** @var \PDO */
    private $pdo = null; // helper

    function makePDO() {
        $pdo = new \PDO( 'sqlite::memory:', null, null,
            array( \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION )
        );
        $pdo->exec( 'CREATE TABLE IF NOT EXISTS ' . TEST_TABLE . ' ( id INT, name VARCHAR(60), age INT )' );
        return $pdo;
    }

    function makeWrapper() {
        return new PDOWrapper( $this->pdo );
    }

    function rowToObject( array $row ) {
        return new Person( $row[ 'id' ], $row[ 'name' ], $row[ 'age' ] );
    }

    function preparePDO() {
        if ( null === $this->pdo ) {
            $this->pdo = $this->makePDO();
        }
        $this->pdo->exec( 'DELETE FROM ' . TEST_TABLE );
        $ps = $this->pdo->prepare( 'INSERT INTO ' . TEST_TABLE . ' VALUES ( :id, :name, :age )' );
        foreach ( PERSON_DATA as $data ) {
            $ps->execute( $data );
        }
        return $this->pdo;
    }

    function setUp(): void {
        $this->pdoW = $this->makeWrapper( $this->preparePDO() );
    }

    function tearDown(): void {
        $this->pdoW = null;
    }

    /**
     * @test
     * @check lastId
     */
    function returnsTheLastId() {
        $id = $this->pdoW->lastId( TEST_TABLE );
        $this->assertEquals( 2, $id );
    }

    /**
     * @test
     * @check generateId
     */
    function generatesAnId() {
        $id = $this->pdoW->generateId( TEST_TABLE );
        $this->assertEquals( 3, $id );
    }

    /**
     * @test
     * @check deleteWithId
     */
    function deletesWithAnId() {
        $affected = $this->pdoW->deleteWithId( 2, TEST_TABLE );
        $this->assertEquals( 1, $affected );
    }

    /**
     * @test
     * @check countRows
     */
    function canCountRows() {
        $count = $this->pdoW->countRows( TEST_TABLE );
        $this->assertEquals( 2, $count );
    }

    /**
     * @test
     * @check makeLimitOffset
     */
    function canMakeLimitQueryWithoutOffset() {
        $query = $this->pdoW->makeLimitOffset( 10 );
        $this->assertStringContainsString( 'LIMIT 10', $query );
        $this->assertStringNotContainsString( 'OFFSET', $query );
    }

/**
     * @test
     * @check makeLimitOffset
     */
    function offsetQueryMustIncludeLimitWhenLimitIsZero() {
        $query = $this->pdoW->makeLimitOffset( 0, 50 );
        $this->assertStringContainsString( 'OFFSET 50', $query );
        $this->assertStringContainsString( 'LIMIT', $query );
    }

    /**
     * @test
     * @check makeLimitOffset
     */
    function canMakeLimitAndOffsetQueryWithBothValues() {
        $query = $this->pdoW->makeLimitOffset( 10, 50 );
        $this->assertStringContainsString( 'LIMIT 10', $query );
        $this->assertStringContainsString( 'OFFSET 50', $query );
    }

    /**
     * @test
     * @check fetchObjects
     */
    function fetchObjectsCorrectly() {
        $objects = $this->pdoW->fetchObjects(
            'SELECT * FROM ' . TEST_TABLE,
            array(),
            TEST_CLASS,
            array( 0, null, null )
        );
        $this->assertCount( 2, $objects );
        $this->assertEquals( 'Bob', $objects[ 0 ]->name );
        $this->assertEquals( 'Alice', $objects[ 1 ]->name );
    }

    /**
     * @test
     * @check queryObjects
     */
    function queriesExistingObjects() {
        $objects = $this->pdoW->queryObjects(
            array( $this, 'rowToObject' ),
            'SELECT * FROM ' . TEST_TABLE
        );
        $this->assertCount( 2, $objects );
        $this->assertEquals( 'Bob', $objects[ 0 ]->name );
        $this->assertEquals( 'Alice', $objects[ 1 ]->name );
    }

    /**
     * @test
     * @check objectWithId
     */
    function retrievesAnObjectWithTheGivenId() {
        $obj = $this->pdoW->objectWithId( array( $this, 'rowToObject' ), 2, TEST_TABLE );
        $this->assertEquals( 'Alice', $obj->name );
    }

    /**
     * @test
     * @check allObjects
     */
    function retrievesAllObjects() {
        $objects = $this->pdoW->allObjects( array( $this, 'rowToObject' ), TEST_TABLE );
        $this->assertCount( 2, $objects );
        $this->assertEquals( 'Bob', $objects[ 0 ]->name );
        $this->assertEquals( 'Alice', $objects[ 1 ]->name );
    }

    /**
     * @test
     * @check run
     */
    function canRunDMLCommand() {
        $count = $this->pdoW->run( 'UPDATE ' . TEST_TABLE . ' SET age = age + 1' );
        $this->assertEquals( 2, $count );
    }

    /**
     * @test
     * @check query
     */
    function canRunSQLCommand() {
        $rows = $this->pdoW->query( 'SELECT * FROM ' . TEST_TABLE );
        $this->assertCount( 2, $rows );
        $this->assertEquals( $rows[ 0 ][ 'name' ], 'Bob' );
        $this->assertEquals( $rows[ 1 ][ 'name' ], 'Alice' );
    }

}

?>