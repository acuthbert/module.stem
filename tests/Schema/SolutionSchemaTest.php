<?php
/**
 *
 * @author acuthbert
 * @copyright GCD Technologies 2013
 */

namespace Gcd\Tests;


use Rhubarb\Stem\Models\Model;
use Rhubarb\Stem\Schema\ModelSchema;
use Rhubarb\Stem\Schema\SolutionSchema;
use Rhubarb\Stem\Tests\Fixtures\Company;
use Rhubarb\Stem\Tests\Fixtures\ModelUnitTestCase;
use Rhubarb\Stem\Tests\Fixtures\UnitTestingSolutionSchema;
use Rhubarb\Stem\Tests\Fixtures\User;

class SolutionSchemaTest extends ModelUnitTestCase
{
	public function testSchemaMustBeRegistered()
	{
		$this->setExpectedException( "Rhubarb\Stem\Exceptions\SchemaNotFoundException" );

		SolutionSchema::getSchema( "UnRegisteredSchema" );
	}

	public function testSchemaRegistration()
	{
		SolutionSchema::registerSchema( "MySchema", "Rhubarb\Stem\Tests\Fixtures\UnitTestingSolutionSchema" );

		$schema = SolutionSchema::getSchema( "MySchema" );

		$this->assertInstanceOf( "Rhubarb\Stem\Tests\Fixtures\UnitTestingSolutionSchema", $schema );
	}

	public function testInvalidSchemaType()
	{
		SolutionSchema::registerSchema( "MyBadSchema", "Rhubarb\Stem\ModellingModule" );

		$this->setExpectedException( "Rhubarb\Stem\Exceptions\SchemaRegistrationException" );

		SolutionSchema::getSchema( "MyBadSchema" );
	}

	public function testSchemaCache()
	{
		SolutionSchema::clearSchemas();
		SolutionSchema::registerSchema( "MySchema", "Rhubarb\Stem\Tests\Fixtures\UnitTestingSolutionSchema" );

		$schema = SolutionSchema::getSchema( "MySchema" );
		$schema->test = true;

		$schema = SolutionSchema::getSchema( "MySchema" );

		$this->assertTrue( $schema->test );
	}

	public function testGetModelSchema()
	{
		$modelSchema = SolutionSchema::getModelSchema( "UnitTestUser" );
		$user = new User();

		$this->assertEquals( $user->getSchema(), $modelSchema );
	}

	public function testRelationships()
	{
		SolutionSchema::clearSchemas();
		SolutionSchema::registerSchema( "MySchema", "Rhubarb\Stem\Tests\Fixtures\UnitTestingSolutionSchema" );

		error_reporting( E_ALL );
		ini_set( "display_errors", "on" );

		$schema = new UnitTestingSolutionSchema();
		$schema->defineRelationships();

		$relationship = $schema->getRelationship( "UnitTestUser", "Company" );

		$this->assertInstanceOf( "Rhubarb\Stem\Schema\Relationships\OneToOne", $relationship );
		$this->assertInstanceOf( "Rhubarb\Stem\Schema\Relationships\OneToMany", $relationship->getOtherSide() );

		$relationship = $schema->getRelationship( "Company", "Users" );

		$this->assertInstanceOf( "Rhubarb\Stem\Schema\Relationships\OneToMany", $relationship );

		$relationship = $schema->getRelationship( "Company", "Unknown" );

		$this->assertNull( $relationship );

		$relationship = $schema->getRelationship( "Example", "ExampleRelationshipName" );

		$this->assertInstanceOf( "Rhubarb\Stem\Schema\Relationships\OneToOne", $relationship );

		$columnRelationships = SolutionSchema::getAllOneToOneRelationshipsForModelBySourceColumnName( "UnitTestUser" );

		$this->assertArrayHasKey( "CompanyID", $columnRelationships );
		$this->assertInstanceOf( "Rhubarb\Stem\Schema\Relationships\OneToOne", $columnRelationships[ "CompanyID" ] );

		$company = new Company();
		$company->CompanyName = "GCD";
		$company->save();

		$user = new User();
		$user->getRepository()->clearObjectCache();
		$user->Forename = "a";
		$user->save();

		$company->Users->Append( $user );

		$b = $user = new User();
		$user->Forename = "b";
		$user->save();

		$company->Users->Append( $user );

		// Just to make sure this doesn't get in our relationship!
		$user = new User();
		$user->Forename = "c";
		$user->save();

		$company = new Company( $company->CompanyID );

		$this->assertCount( 2, $company->Users );
		$this->assertEquals( "a", $company->Users[0]->Forename );
		$this->assertEquals( "b", $company->Users[1]->Forename );

		$company = $b->Company;

		$this->assertEquals( "GCD", $company->CompanyName );
	}

	public function testManyToManyRelationships()
	{

	}

	public function testModelCanBeRetrievedByName()
	{
		$company = SolutionSchema::getModel( "Company" );

		$this->assertInstanceOf( "Rhubarb\Stem\Tests\Fixtures\Company", $company );
		$this->assertTrue( $company->isNewRecord() );

		$company->CompanyName = "Boyo";
		$company->save();

		$model2 = SolutionSchema::getModel( "Company", $company->CompanyID );
		$this->assertEquals( $company->CompanyID, $model2->UniqueIdentifier );
	}

	public function testSuperseededModelIsReturnedWhenUsingPreviousNamespacedClassName()
	{
		SolutionSchema::registerSchema( "SchemaA", __NAMESPACE__."\\SchemaA" );

		$class = SolutionSchema::getModelClass( __NAMESPACE__."\\ModelA" );

		$this->assertEquals( __NAMESPACE__."\\ModelA", $class );

		SolutionSchema::registerSchema( "SchemaB", __NAMESPACE__."\\SchemaB" );

		$class = SolutionSchema::getModelClass( __NAMESPACE__."\\ModelA" );

		$this->assertEquals( __NAMESPACE__."\\ModelB", $class );
	}
}

class ModelA extends Model
{
	protected function createSchema()
	{
		return new ModelSchema( "ModelA" );
	}
}

class ModelB extends Model
{
	protected function createSchema()
	{
		return new ModelSchema( "ModelB" );
	}
}

class SchemaA extends SolutionSchema
{
	public function __construct($version = 0)
	{
		parent::__construct($version);

		$this->addModel( "TestModel", __NAMESPACE__."\\ModelA" );
	}
}

class SchemaB extends SolutionSchema
{
	public function __construct($version = 0)
	{
		parent::__construct($version);

		$this->addModel( "TestModel", __NAMESPACE__."\\ModelB" );
	}
}