<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Record;

class RecordTest extends TestCase
{
    // use DatabaseMigrations;
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testCategoryMap()
    {
        $this->assertEquals(Record::mapCategory('Labor'), 'labor');
        $this->assertEquals(Record::mapCategory('工具器具備品'), 'tools_equipment');
        $this->assertEquals(Record::mapCategory('広告宣伝費'), 'promotion');
        $this->assertEquals(Record::mapCategory('販売キャンペーン'), 'promotion');
        $this->assertEquals(Record::mapCategory('SEO'), 'promotion');
        $this->assertEquals(Record::mapCategory('SEO', true), 'seo');
        $this->assertEquals(Record::mapCategory('地代家賃'), 'rent');
        $this->assertEquals(Record::mapCategory('packing & delivery expenses'), 'delivery');
        $this->assertEquals(Record::mapCategory('Revenue'), 'revenue');
        $this->assertEquals(Record::mapCategory('収益'), 'revenue');
        $this->assertEquals(Record::mapCategory('水道光熱費'), 'utility');
        $this->assertEquals(Record::mapCategory('法定福利費'), 'labor');
        $this->assertEquals(Record::mapCategory('法定福利費', true), 'welfare');
    }
}
