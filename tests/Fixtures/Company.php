<?php

namespace Rhubarb\Stem\Tests\Fixtures;

/**
 * A sample data object modelling a company for use with unit testing.
 *
 * @property int $CompanyID
 * @property string $CompanyName
 *
 * @author acuthbert
 * @copyright GCD Technologies 2012
 */
use Rhubarb\Stem\Filters\AndGroup;
use Rhubarb\Stem\Filters\Equals;
use Rhubarb\Stem\Filters\Filter;
use Rhubarb\Stem\Models\Validation\HasValue;
use Rhubarb\Stem\Models\Validation\Validator;
use Rhubarb\Stem\Models\Model;
use Rhubarb\Stem\Repositories\MySql\Schema\Columns\AutoIncrement;
use Rhubarb\Stem\Repositories\MySql\Schema\Columns\MySqlDate;
use Rhubarb\Stem\Repositories\MySql\Schema\Columns\MySqlDateTime;
use Rhubarb\Stem\Repositories\MySql\Schema\Columns\MySqlInteger;
use Rhubarb\Stem\Repositories\MySql\Schema\Columns\Json;
use Rhubarb\Stem\Repositories\MySql\Schema\Columns\MySqlMoney;
use Rhubarb\Stem\Repositories\MySql\Schema\Columns\MySqlTime;
use Rhubarb\Stem\Repositories\MySql\Schema\Columns\TinyMySqlInt;
use Rhubarb\Stem\Repositories\MySql\Schema\Columns\MySqlString;
use Rhubarb\Stem\Repositories\MySql\Schema\Index;
use Rhubarb\Stem\Repositories\MySql\Schema\MySqlModelSchema;

class Company extends Model
{

	/**
	 * Returns the schema for this data object.
	 *
	 * @return \Rhubarb\Stem\Schema\ModelSchema
	 */
	protected function createSchema()
	{
		$schema = new MySqlModelSchema( "tblCompany" );
		$schema->uniqueIdentifierColumnName = "CompanyID";

		$companyId = new AutoIncrement( "CompanyID" );

		$schema->addColumn( $companyId );
		$schema->addColumn( new MySqlString( "CompanyName", 200 ) );
		$schema->addColumn( new MySqlMoney( "Balance" ) );
		$schema->addColumn( new MySqlDate( "InceptionDate" ) );
		$schema->addColumn( new MySqlDateTime( "LastUpdatedDate" ) );
		$schema->addColumn( new MySqlTime( "KnockOffTime" ) );
		$schema->addColumn( new TinyMySqlInt( "BlueChip", 0 ) );
		$schema->addColumn( new MySqlInteger( "ProjectCount" ) );
		$schema->addIndex( new Index( "CompanyID", Index::PRIMARY ) );
		$schema->addColumn( new Json( "CompanyData" ) );
		$schema->addColumn( new TinyMySqlInt( "Active", 1 ) );

		$schema->labelColumnName = "CompanyName";

		return $schema;
	}

	protected function getPublicPropertyList()
	{
		$list = parent::getPublicPropertyList();
		$list[] = "Balance";

		return $list;
	}

	public function GetCompanyIDSquared()
	{
		return $this->CompanyID * $this->CompanyID;
	}

	protected function getConsistencyValidationErrors()
	{
		$errors = [];

		if ( !$this->CompanyName )
		{
			$errors[ "CompanyName" ] = "Company name must be supplied";
		}

		return $errors;
	}

	protected function CreateConsistencyValidator()
	{
		$validator = new Validator();
		$validator->validations[] = new HasValue( "CompanyName" );

		return $validator;
	}

	public static function find( Filter $filter = null )
	{
		$activeFilter = new Equals( 'Active', 1 );
		if( $filter === null )
		{
			$filter = $activeFilter;
		}
		else
		{
			$filter = new AndGroup( [ $filter, $activeFilter ] );
		}

		return parent::find( $filter );
	}

}