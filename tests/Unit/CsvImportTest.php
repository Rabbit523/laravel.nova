<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
// use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;

use App\Record;
use App\DataSource;

class CsvImportTest extends TestCase
{
    use DatabaseMigrations;

    public function testGetMonthlyDataSkip()
    {
        $datasource = $this->getDataSource();
        // $this->assertTrue(true);
        // $this->assertEquals(Cost::mapCategory('Labor'), 'labor');

        $record = [
            "2018/09" => "50000",
            "2018/10" => "50000",
            "2018/11" => "50000",
            "2018/12" => "0",
            "2019/01" => "0",
            "2019/02" => "0",
            "2019/03" => "0",
            "2019/04" => "0",
            "2019/05" => "0",
            "2019/06" => "0",
            "2019/07" => "0",
            "Aug-19" => "999.99"
        ];
        $result = $datasource->getMonthlyData($record);
        $first = floatval(preg_replace("/[^0-9]/", "", $record['2018/11']));
        $second = floatval(preg_replace("/[^0-9]/", "", $record['2018/12']));
        $last = to_float($record['Aug-19']);
        $this->assertEquals($result[0][0]['price'], $first);
        $this->assertEquals($result[0][1]['price'], $second);
        $this->assertEquals($result[0][count($result[0]) - 1]['price'], $last);
    }

    public function testGetDailyData()
    {
        $datasource = $this->getDataSource();

        $record = [
            "2019/01/01" => "10000",
            "2019/01/02" => "20000",
            "2019/01/03" => "30000",
            "2019/01/04" => "0",
            "2019/01/05" => "0",
            "2019/01/06" => "0",
            "2019/01/07" => "0",
            "2019/01/08" => "0",
            "2019/01/09" => "0",
            "2019/01/10" => "0",
            "2019/01/11" => "10999.99",
            "2019-jan-12" => "12000"
        ];

        $result = $datasource->getDailyData($record);

        $first = to_float($record['2019/01/01']);
        $last = to_float($record['2019-jan-12']);
        $one_before_last = to_float($record['2019/01/11']);
        $this->assertEquals($result[0][0]['price'], $first);
        $this->assertEquals($result[0][count($result[0]) - 1]['price'], $last);
        $this->assertEquals(
            $result[0][count($result[0]) - 1]['date']->format("Y-m-d"),
            "2019-01-12"
        );
        $this->assertEquals($result[0][count($result[0]) - 2]['price'], $one_before_last);
    }

    public function testWrongCategory()
    {
        $this->assertFalse(DataSource::isWrongCategory('labor', 'cogs'));
        $this->assertFalse(DataSource::isWrongCategory('revenue', 'revenue'));
        $this->assertFalse(DataSource::isWrongCategory('promotion', 'opex'));
        $this->assertFalse(DataSource::isWrongCategory('tools_equipment', 'cogs'));
        $this->assertFalse(DataSource::isWrongCategory('utility', 'opex'));
        $this->assertFalse(DataSource::isWrongCategory('utility', 'cogs'));

        $this->assertTrue(DataSource::isWrongCategory('utility', 'revenue'));
        $this->assertTrue(DataSource::isWrongCategory('tools_equipment', 'opex'));
        $this->assertTrue(DataSource::isWrongCategory('promotion', 'cogs'));
        $this->assertTrue(DataSource::isWrongCategory('raw_materials_parts', 'opex'));
        $this->assertTrue(DataSource::isWrongCategory('communication', 'cogs'));
        $this->assertTrue(DataSource::isWrongCategory('revenue', 'opex'));
        $this->assertTrue(DataSource::isWrongCategory('revenue', 'cogs'));
    }

    public function testCsvImport()
    {
        $project = $this->getProject();
        $this->assertEquals($project->title, 'test project');

        $datasource = $this->getDataSource('cogs.csv', $project);

        $this->assertTrue($datasource->parse());

        $records = $project->recordsPlanned('cogs');
        $labor_n = $records->firstWhere('name', 'タイトルN');
        $this->assertTrue(!is_null($labor_n));

        $monthly_record = $labor_n->monthly->firstWhere('month', 0);
        $this->assertTrue(!is_null($monthly_record));
        $this->assertEquals($monthly_record->price, 100);
        $this->assertEquals($monthly_record->meta['welfare'], 1);
        $this->assertEquals($monthly_record->meta['overtime'], 2);
        // clean up monthly
        unlink(Storage::path('csv/' . $project->id . '/cogs.csv'));

        // test daily
        $datasource = $this->getDataSource('cogs_daily.csv', $project);

        $this->assertTrue($datasource->parse());

        $records = $project->recordsDailyPlanned('cogs', '2018-11-01');
        $labor_n = $records->firstWhere('name', 'タイトルN');
        $this->assertTrue(!is_null($labor_n));
        $daily_record = $labor_n->daily->first();
        $this->assertTrue(!is_null($daily_record));
        $this->assertEquals($daily_record->price, 100);
        $this->assertEquals($daily_record->meta['welfare'], 1);
        $this->assertEquals($daily_record->meta['overtime'], 2);

        // clean up
        unlink(Storage::path('csv/' . $project->id . '/cogs_daily.csv'));
        rmdir(Storage::path('csv/' . $project->id));
    }

    function getDataSource($csvfile = '', $project = false)
    {
        $project = $project ?: $this->getProject();

        if ($csvfile) {
            $result = Storage::makeDirectory('/csv/' . $project->id);
            $this->assertTrue($result);
            $path = Storage::path('csv/' . $project->id . '/' . $csvfile);
            $this->assertTrue(copy(__DIR__ . '/' . $csvfile, $path));
        }

        $data = [
            'type' => 'csv',
            'project_id' => $project->id,
            'record' => [
                'type' => 'cogs',
                'planned' => true
            ],
            'hash' => sha1($project->id),
            'name' => $csvfile ?: 'test',
            'meta' => [
                'path' => 'csv/' . $project->id . '/' . $csvfile,
                'type' => 'csv'
            ]
        ];

        // create datasource
        return $project->user->datasources()->create($data);
    }

    function getProject()
    {
        $user = factory(\App\User::class)->create();
        $project = $user->projects()->create([
            'title' => 'test project',
            'start_date' => '2018-11-01',
            'with_launch' => false,
            'duration' => 12,
            'currency' => 'jpy'
        ]);

        $this->assertEquals($project->title, 'test project');
        return $project;
    }
}
