<?php

namespace App\Http\Controllers\Api;

use App\Project;
use App\Record;
use App\User;
use App\MonthlyRecords;
use App\DailyRecords;
use App\DataSource;
use App\Jobs\ProcessDatasource;

use App\Http\Controllers\ApiController;
use App\Http\Transformers\RecordTransformer;
use App\Http\Requests\Api\CreateRecord;
use App\Http\Requests\Api\UpdateRecord;
use App\Http\Requests\Api\DeleteRecord;
use App\Http\Requests\Api\StoreMonthlyRecord;
use App\Http\Requests\Api\StoreDailyRecord;
use App\Http\Requests\Api\UploadCSV;

use League\Csv\Writer;
use League\Csv\Reader;

use Illuminate\Support\Facades\Lang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RecordController extends ApiController
{
    /**
     * RecordController constructor.
     *
     * @param RecordTransformer $transformer
     */
    public function __construct(RecordTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    /**
     * Get records with monthly data.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Project $project, $type = 'cogs', $date = '')
    {
        switch ($type) {
            case 'cogs':
            case 'opex':
            case 'revenue':
                // case 'launch':
                break;
            default:
                return $this->respondError('Unknown record type');
        }

        if (!empty($date)) {
            try {
                $date = as_date($date);
            } catch (\Exception $e) {
                info($e->getMessage());
                return $this->respondError("invalid date");
            }
            $actual = $project->recordsDailyActual($type, $date);
            $planned = $project->recordsDailyPlanned($type, $date);
        } else {
            $actual = $project->recordsActual($type);
            $planned = $project->recordsPlanned($type);
        }
        $actual = $actual->map([$this->transformer, 'transform']);
        $planned = $planned->map([$this->transformer, 'transform']);

        return $this->respond(['records' => compact('actual', 'planned')]);
    }

    public function store(CreateRecord $request, Project $project)
    {
        $record_data = $request->get('record');
        $date = array_pull($record_data, 'date');
        $record = normalize_record($record_data, $project);

        $record['contact_id'] = $this->getContact($record, $project);
        $record = $this->fillProduct($record, $project);
        $cost = array_pull($record, 'meta.cost');
        array_pull($record, 'contact'); // removing contact leftovers
        $new_record = $project->records()->create($record);

        if (!empty($new_record->autofill)) {
            $new_record->autoFill();
        } elseif ($date && !is_null($new_record->plan->amount)) {
            // don't save empty transactions
            // TODO: take either cost or plan info here. if user submited without product
            $date = as_date($date, 'UTC');
            debug("have date, saving transaction", [
                $cost,
                $date,
                $new_record->plan->amount,
            ]);

            $new_record->transactions()->create([
                'source_id' => user()->id,
                'source_type' => User::class,

                'price' => $new_record->plan->amount,
                'quantity' => array_get($cost, 'quantity', 1),
                'total' => $new_record->plan->amount * array_get($cost, 'quantity', 1),
                'meta' => $new_record->getPriceFields(),

                'date' => $date,
            ]);
            $new_record->recalculateDay($date->format("Y-m-d"));
            $new_record->recalculateMonth($date->format("Y-m"));
        }

        if (!empty($record['planned'])) {
            array_set($record, 'autofill', null);
            $record['planned'] = false;
            $project->records()->create($record);
        }

        return ok();
    }

    public function update(UpdateRecord $request, Project $project, Record $record)
    {
        $record_data = $request->get('record');

        if ($record->project_id != $project->id) {
            return $this->respondForbidden("wrong project");
        }

        unset($record_data['id'],
        $record_data['project_id'],
        $record_data['created_at'],
        $record_data['updated_at'],
        $record_data['start_date'],
        $record_data['end_date']);

        $fields = normalize_record($record_data, $project);
        $fields['contact_id'] = $this->getContact($fields, $project);
        array_pull($fields, 'contact'); // remove contact leftovers
        $fields = $this->fillProduct($fields, $project);
        $record->fill($fields);

        $record->save();
        // if name changed, should we update mirror record?

        if (!empty($record->autofill)) {
            $record->autoFill();
        }

        return ok();
    }

    public function destroy(DeleteRecord $request, Project $project, Record $record)
    {
        // TODO: save event on deleting record, delete in delayed queue?
        $record->monthly()->delete();
        $record->delete();
        return $this->respondSuccess();
    }

    public function storeMonthly(
        StoreMonthlyRecord $request,
        Project $project,
        Record $record
    ) {
        $record->meta = array_merge($record->meta, ['autofill' => null]);
        $record->save();

        $data = $request->get('record');
        $month = array_pull($data, 'date');
        $data = array_only($data, ['price', 'quantity', 'meta']);
        $data['date'] = as_date($project->start_date, 'UTC')
            ->addMonth($month + 1)
            ->subDay();

        $this->saveManualTransaction($record, $data);

        if ($project->duration <= ($month + 1)) {
            $project->duration = $month + 2;
            $project->save();
        }
        return $this->respondSuccess();
    }

    public function storeDaily(StoreDailyRecord $request, Project $project, Record $record)
    {
        $record->meta = array_merge($record->meta, ['autofill' => null]);
        $record->save();

        $data = $request->get('record');
        $data = array_only($data, ['date', 'price', 'quantity', 'meta']);
        $date = str_replace('/', '-', $data['date']);
        // TODO: validate date?
        $data['date'] = as_date($date, 'UTC');
        $this->saveManualTransaction($record, $data);

        return ok();
    }

    public function upload(UploadCSV $request, Project $project, $type)
    {
        $result = Storage::makeDirectory('/csv/' . $project->id);
        if (!$result) {
            return $this->respondError(
                Lang::getFromJson(
                    "There has been an error while saving a file. Please try again."
                ),
                500
            );
        }
        $file = request()->file('file');
        // TODO: check file type first. gz,zip,csv
        $hash = sha1_file($file->path());
        $planned = (request()->input('planned') == 'true');
        // disable dupe check for now
        /*
        if (
            $project
                ->csvs()
                ->whereHash($hash)
                ->wherePlanned($planned)
                ->whereRecordType($type)
                ->first() != null
        ) {
            return $this->respondError(
                Lang::getFromJson(
                    "Already uploaded, the same file can be uploaded only once."
                ),
                400
            );
        }
        */

        $path = $file->store('/csv/' . $project->id);

        $datasource = [
            'type' => 'csv',
            'record' => [
                'planned' => $planned,
                'type' => $type,
            ],
            'project_id' => $project->id,
            'hash' => $hash,
            'name' => $file->getClientOriginalName(),
            'meta' => [
                'size' => $file->getSize(),
                'path' => $path,
                'type' => pathinfo(Storage::path($path), PATHINFO_EXTENSION),
            ],
        ];

        $csv = user()
            ->datasources()
            ->create($datasource);
        logger()->debug('new datasource', ['csv', $csv->id]);
        // disable horizon for now
        // if (\App::environment('prod')) {
        //     ProcessDatasource::dispatch($csv);
        // } else {
        $result = $csv->parse();
        if (!$result) {
            return $this->respondError($csv->meta['message'], 400);
        }
        // }

        return $this->respondNoContent();
    }

    public function download($project_id, $type = 'cogs', $name)
    {
        switch ($type) {
            case 'cogs':
            case 'opex':
                // case 'launch':
            case 'revenue':
                break;
            default:
                return $this->respondError('Unknown record type', 400);
        }
        $name = '/export/csv/' . $project_id . '/' . $name;
        $file = Storage::path($name);
        if (!file_exists($file)) {
            return $this->respondError('File does not exist', 404); // TODO: show actual error view
        }
        return // ->header('Content-Type', 'text/csv; charset=UTF-8')
            // ->header('Content-Encoding', 'UTF-8')
            // ->header('Content-Transfer-Encoding', 'binary')
            // ->header('Content-Description', 'File Transfer')
            response()
            ->download($file, $type . '.csv')
            ->deleteFileAfterSend();
    }

    /**
     * Export records into file by name
     *
     * @param  Request  $request
     * @param  Project  $project
     * @param  string $budget
     * @param  string $type
     * @return \Illuminate\Http\JsonResponse
     */
    public function export(
        Request $request,
        Project $project,
        $budget = 'planned',
        $type = 'cogs'
    ) {
        //TODO: get export format from request (zip/csv?)
        if (!Storage::makeDirectory('/export/csv/' . $project->id)) {
            return $this->respondError(
                Lang::getFromJson(
                    "There has been an error while saving a file. Please try again."
                ),
                500
            );
        }
        $name = md5(str_random(40)) . '.csv';

        $planned = $budget === 'planned';
        $records = $project->recordsPlanned($type, $planned);
        $project_duration = $project->duration;
        $project_start = as_date($project->start_date, 'UTC');

        // TODO: export vendor?
        $header = ['record_category', 'record_title'];
        if ($type == 'revenue') {
            $header = ['product', 'plan', 'customer'];
        }
        for ($i = 0; $i < $project_duration; $i++) {
            $header[] = $project_start->addMonths($i)->format("Y/m");
        }

        $result = $records->map(function ($record) use ($project_duration, $project_start) {
            $monthly = [];
            foreach ($record->monthly as $value) {
                $month = $project_start->diffInMonths(as_date($value->date, 'UTC'), false);
                $monthly[$month] = $value->getTotal();
            }

            for ($i = 0; $i < $project_duration; $i++) {
                if (isset($monthly[$i])) {
                    continue;
                }
                $monthly[$i] = 0;
            }
            ksort($monthly);

            $category = Record::getCategoryName($record->category_code, app()->getLocale());
            $category =
                $record->type != 'revenue'
                ? $category
                : ($record->product ? $record->product->name : $category);

            $name = $record->name;
            $name =
                $record->type != 'revenue'
                ? $name
                : ($record->plan ? $record->plan->name : $name);
            $meta = [$category, $name];
            if ($record->type == 'revenue') {
                $meta[] = $record->contact ? get_contact_name($record->contact) : '';
            }
            return collect($meta)->concat($monthly);
        });

        $csv = Writer::createFromString('');
        $csv->setOutputBOM(Reader::BOM_UTF8);
        $csv->setEscape('"');
        $csv->insertOne($header);
        $csv->insertAll($result->toArray());

        if (
            !Storage::put(
                '/export/csv/' . $project->id . '/' . $name,
                chr(239) . chr(187) . chr(191) . $csv->getContent()
            )
        ) {
            return $this->respondError(
                Lang::getFromJson(
                    "There has been an error while saving a file. Please try again."
                ),
                500
            );
        }
        return $this->respond(['name' => $name]);
    }

    private function getContact($record, $project)
    {
        if ($contact_data = array_get($record, 'contact')) {
            $contact = $project->user->contacts();
            if (array_has($contact_data, 'id')) {
                $contact = $contact->where('id', $contact_data['id']);
            } else {
                $contact = $contact
                    ->where('name', $contact_data['name'])
                    ->orWhere('email', $contact_data['name']);
            }
            $contact = $contact->first();
            if (!$contact) {
                logger()->debug('getContact', ['creating new contact']);
                // TODO: if it's company fill name, else - split by space and fill first/last name depending on user locale
                $contact_data['is_company'] = $record['type'] != 'revenue';
                $contact_data['source'] = 'app';
                $contact_data['status'] = $record['type'] == 'revenue' ? 'customer' : 'new';
                $contact_data['is_vendor'] = $record['type'] != 'revenue';
                $contact = $project->user->contacts()->create($contact_data);
            } else {
                logger()->debug('getContact', [$contact->id]);
            }
            return $contact->id;
        }
        return null;
    }

    private function fillProduct($record, $project)
    {
        $product_data = array_pull($record, 'product');
        if (!$product_data) {
            return $record;
        }

        $plan = null;
        $product = null;

        if (array_has($product_data, 'product_id')) {
            // have a plan and product
            $product = $project->user->products_sold()
                ->where('id', $product_data['product_id'])
                ->first();
            $plan = $product
                ->plans()
                ->where('id', array_get($product_data, 'id'))
                ->first();
            $plan = $plan ? $plan->id : null;
        } elseif (array_has($product_data, 'id')) {
            // have only product
            $product = $project->user->products_sold()
                ->where('id', $product_data['id'])
                ->first();
        }

        if (!$product) {
            // new product
            $product = $project->user->products()->create([
                'name' => $product_data['name'],
                'slug' => str_slug($product_data['name']),
                'sold' => true,
                'type' => !empty($record['autofill']) ? 'service' : 'retail',
            ]);
            $cost = array_has($record, 'meta.cost') ?
                array_get($record, 'meta.cost.price') : array_get($record, 'autofill.price', 0);
            // FIXME: should we add price to product for retail products?
            $plan = $product->plans()->create([
                'name' => 'default',
                'interval' => $product->type == 'retail' ? 'once' : 'month',
                'amount' => $cost,
                'currency' => $project->currency,
            ]);
            $plan = $plan ? $plan->id : null;
        }

        $product->projects()->syncWithoutDetaching([$project->id]);
        $record['product_id'] = $product->id;
        $record['plan_id'] = $plan;

        return $record;
    }

    private function saveManualTransaction($record, $data)
    {
        $price = array_pull($data, 'price', 0);
        if (is_null($price)) {
            // don't save empty transactions
            return;
        }
        $quantity = array_pull($data, 'quantity', 1);
        $meta = array_pull($data, 'meta');

        $data['source_id'] = user()->id;
        $data['source_type'] = User::class;
        $transaction = $record->saveTransaction($data);

        array_pull($meta, 'aggregated');
        $transaction->meta = $meta;
        $transaction->price = $price;
        $transaction->total = $price * $quantity;
        $transaction->quantity = $quantity;
        $transaction->save();

        $record->recalculateDay($data['date']->format("Y-m-d"));
        $record->recalculateMonth($data['date']->format("Y-m"));
    }
}

function get_contact_name($contact)
{
    return trim(
        $contact->email
            ? $contact->email
            : ($contact->name
                ? $contact->name
                : ($contact->first_name
                    ? $contact->last_name . ' ' . $contact->first_name
                    : $contact->id))
    );
}
