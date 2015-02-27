<?php

namespace Rhubarb\Stem\Tests\Repositories\MySql\Schema\Columns;

use Rhubarb\Stem\Repositories\MySql\MySql;
use Rhubarb\Stem\Tests\Repositories\MySql\MySqlTestCase;
use Rhubarb\Stem\Tests\Fixtures\Company;

class DateTimeTest extends MySqlTestCase
{
	public function testRepositoryGetsDateFormat()
	{
		$company = new Company();
		$company->CompanyName = "GCD";
		$company->LastUpdatedDate = "2012-01-01 10:01:02";
		$company->save();

		$params = MySql::getPreviousParameters();

		$this->assertContains( "2012-01-01 10:01:02", $params[ "LastUpdatedDate" ] );

		$company->reload();

		$this->assertEquals( "2012-01-01 10:01:02", $company->LastUpdatedDate->format( "Y-m-d H:i:s" ) );
	}
}
