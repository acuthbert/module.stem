<?php

namespace Rhubarb\Stem\Tests\Fixtures;

use Rhubarb\Stem\Models\Model;
use Rhubarb\Stem\Repositories\MySql\Schema\Columns\AutoIncrement;
use Rhubarb\Stem\Repositories\MySql\Schema\Columns\MySqlInteger;
use Rhubarb\Stem\Repositories\MySql\Schema\MySqlModelSchema;
use Rhubarb\Stem\Schema\Columns\Integer;
use Rhubarb\Stem\Schema\ModelSchema;

/** 
 * 
 *
 * @package Rhubarb\Stem\Tests\Fixtures
 * @author      acuthbert
 * @copyright   2013 GCD Technologies Ltd.
 */
class CompanyCategory extends Model
{

	/**
	 * Returns the schema for this data object.
	 *
	 * @return \Rhubarb\Stem\Schema\ModelSchema
	 */
	protected function createSchema()
	{
		$schema = new MySqlModelSchema( "tblCompanyCategory" );
		$schema->addColumn(
			new AutoIncrement( "CompanyCategoryID" ),
			new MySqlInteger( "CompanyID" ),
			new MySqlInteger( "CategoryID" )
		);

		$schema->uniqueIdentifierColumnName = "CompanyCategoryID";

		return $schema;
	}
}