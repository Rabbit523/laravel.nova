<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Project;
use App\Record;
use App\Transaction;

const TYPES = [
    'mrr',
    'net_revenue',
    'annual_run_rate',
    'arpu',
    'lifetime_value',
    'mrr_growth_rate',
    'revenue_churn'
];

class StatsController extends ApiController
{
    /**
     * Get project statistics.
     *
     * @param  Project  $project
     * @param  string   $type
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Project $project, $type)
    {
        try {
            $start = as_date(request()->get('start_from', now('UTC')->subMonth()), 'UTC');
            // info('start', [$start]);
        } catch (\Exception $e) {
            return $this->respondError("invalid date");
        }
        $bucket_count = request()->get('bucket_count', 30);
        $bucket_count = $bucket_count > 30 ? 30 : $bucket_count;
        $unit = request()->get('unit', 'day');
        $end = as_date($start);
        switch ($unit) {
            case 'month':
                $end = $end->addMonth($bucket_count);
                // info('end', [$end]);
                break;
            default:
                $end = $end->addDay($bucket_count);
                // info('end', [$end]);
                break;
        }
        if (!in_array($type, TYPES)) {
            return $this->respondNotFound();
        }
        $query = (new Transaction())->newQuery();
        switch ($type) {
            case 'net_revenue':
                $results = $query
                    ->select(DB::raw("SUM(`price`*`quantity`) as value, DAY(`date`) as day"))
                    ->leftJoin('records', 'records.id', '=', 'transactions.record_id')
                    ->whereBetween('date', [$start, $end])
                    ->where('project_id', $project->id)
                    ->where('type', 'revenue')
                    ->groupBy(DB::raw("DAY(`date`)"))
                    ->orderBy('date')
                    ->get();

                $possibleDateResults = $this->getAllPossibleDateResults($start, $end);

                $results = array_merge($possibleDateResults, $results);
                $data->each(function ($item, $key) {
                    info($key, [$item->value, $item->day]);
                });
                $result = [
                    'primary_value' => $data->last() ? $data->last()['value'] : 0,
                    'series' => $results
                ];
                break;
            default:
                return $this->respondNotFound();
        }
        return $this->respond($result);
    }
}
