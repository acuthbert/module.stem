<?php

namespace Rhubarb\Stem\Tests\Fixtures;

use Rhubarb\Stem\Filters\AndGroup;
use Rhubarb\Stem\Filters\Equals;
use Rhubarb\Stem\Filters\Filter;
use Rhubarb\Stem\Repositories\MySql\Schema\Columns\AutoIncrement;
use Rhubarb\Stem\Repositories\MySql\Schema\Columns\MySqlDecimal;
use Rhubarb\Stem\Repositories\MySql\Schema\Columns\MySqlEnum;
use Rhubarb\Stem\Repositories\MySql\Schema\Columns\MySqlForeignKey;
use Rhubarb\Stem\Repositories\MySql\Schema\Columns\TinyMySqlInt;
use Rhubarb\Stem\Repositories\MySql\Schema\Columns\MySqlString;
use Rhubarb\Stem\Repositories\MySql\Schema\MySqlModelSchema;

/**
 *
 * @property int $UserID
 * @property int $CompanyID
 * @property string $Username
 * @property string $Forename
 * @property string $Surname
 * @property string $Password
 * @property bool $Active
 *
 * @author acuthbert
 * @copyright GCD Technologies 2013
 */
class User extends \Rhubarb\Stem\Models\Model
{
	/**
	 * Returns the schema for this data object.
	 *
	 * @return \Rhubarb\Stem\Schema\ModelSchema
	 */
	protected function createSchema()
	{
		$schema = new MySqlModelSchema( "tblUser" );

		$schema->addColumn(
			new AutoIncrement( "UserID" ),
			new MySqlForeignKey( "CompanyID" ),
			new MySqlEnum( "UserType", "Staff", [ "Staff", "Administrator" ] ),
			new MySqlString( "Username", 40 ),
			new MySqlString( "Forename", 40 ),
			new MySqlString( "Surname", 40 ),
			new MySqlString( "Password", 120 ),
			new TinyMySqlInt( "Active", 0 ),
			new MySqlDecimal( "Wage" )
		);

		$schema->uniqueIdentifierColumnName = "UserID";
		$schema->labelColumnName = "FullName";

		return $schema;
	}

	public function GetBigWage()
	{
		return $this->Wage * 10;
	}

	public function GetFullName()
	{
		return $this->Forename." ".$this->Surname;
	}

	public static function FromUsername( $username )
	{
		return self::findFirst( new Equals( "Username", $username ) );
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
