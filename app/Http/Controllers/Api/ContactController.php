<?php

namespace App\Http\Controllers\Api;

use App\Contact;
use App\Http\Controllers\ApiController;
use App\Http\Paginate\Paginate;
use App\Http\Requests\Api\ContactBulk;
use App\Http\Requests\Api\CSVRequest;
use App\Http\Transformers\ContactTransformer;
use App\Http\Requests\Api\CreateContact;
use App\Services\Importers\CSVContactImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Storage;
use Fouladgar\EloquentBuilder\Facade as Filters;


class ContactController extends ApiController
{
    /**
     * ContactController constructor.
     *
     * @param ContactTransformer $transformer
     */
    public function __construct(ContactTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    /**
     * Get the Contacts.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        // get builder
        $contacts = user()
            ->contacts()
            ->with(['tags', 'customers']);

        $search = request()->get('search', false);
        if (!empty($search) && mb_strlen($search) > 1) {
            if (mb_strlen($search) == 36 && is_uuid($search)) {
                return $this->respond([
                    'contacts' => [$contacts->where('id', $search)->firstOrFail()]
                ]);
            }
            // apply search
            $contacts = $contacts->search($search, 5);
        }

        $tags = request()->get('tags', '');
        if (!empty($tags)) {
            // filter by tags
            $contacts = $contacts->withAnyTags($tags);
        }

        $filters = json_decode(request()->get('filters'), true);
        if (!empty($filters)) {
            $contacts = $contacts->withFilters($filters);
        }

        return $this->respondWithPagination(new Paginate($contacts));
    }

    /**
     * Get all Customers.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function customers()
    {
        return $this->respond([
            'customers' => []
        ]);
    }

    /**
     * Create a new Contact and return the record if successful.
     *
     * @param CreateContact $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CreateContact $request)
    {
        $data = $request->get('contact');
        $tags = array_pull($data, 'tags');
        $contact = user()
            ->contacts()
            ->create($data);
        if (!empty($tags)) {
            $contact->retag($tags);
        }

        return $this->respondWithTransformer($contact);
    }

    /**
     * Update the Contact given by its id and return the Contact if successful.
     *
     * @param Request $request
     * @param Contact $contact
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Contact $contact)
    {
        if (!$request->has('contact')) {
            return $this->respondError("no contact data present", 400);
        }
        if ($contact->user_id != auth()->id() && user()->acl < 9) {
            // && !$contact->members->contains(auth()->id()) // TODO: check team contact access
            return $this->respondForbidden();
        }
        $data = $request->get('contact');
        $tags = array_pull($data, 'tags');
        if (!empty($tags)) {
            $contact->retag($tags);
        }

        $data = array_only($data, [
            'name',
            'first_name',
            'last_name',
            'gender',
            'name_katakana',
            'email',
            'birthday',
            'website',
            'phone',
            'status',
            'language',
            'accepts_marketing',
            'is_company',
            'notes',
            'assigned_to',
            'is_vendor',
            'meta',
            'source',
            'company_id',
            'parent_id',
            'contact_image',
            'industry_id'
        ]);

        $contact->update($data);

        return $this->respondWithTransformer($contact);
    }

    /**
     * Delete the Contact given by its id.
     *
     * @param Contact $contact
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Contact $contact)
    {
        if ($contact->user_id != auth()->id() && user()->acl < 9) {
            // && !$contact->members->contains(auth()->id()) // TODO: check team contact access
            return $this->respondForbidden();
        }
        $contact->delete();

        return $this->respondSuccess();
    }

    /**
     * Bulk contacts delete
     *
     * @param ContactBulk $request
     * @return JsonResponse
     */
    public function bulkDestroy(ContactBulk $request)
    {
        user()
            ->contacts()
            ->whereIn('id', $request->get('contacts'))
            ->delete();

        return $this->respondSuccess();
    }

    /**
     * Get the Contact by id.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $contact = Contact::with(['company', 'parent', 'assignee', 'addresses'])
            ->where('id', $id)
            ->firstOrFail();
        if ($contact->user_id != auth()->id() && user()->acl < 9) {
            // && !$contact->members->contains(auth()->id()) // check contact access
            return $this->respondForbidden();
        }

        return $this->respondWithTransformer($contact);
    }

    /**
     * Upload CSV file and import contacts.
     *
     * @param CSVRequest $request
     *
     * @return JsonResponse
     */
    public function upload(CSVRequest $request): JsonResponse
    {
        $directory = '/csv/contacts/' . $request->user()->id;

        if (!Storage::makeDirectory($directory) || !$file = $request->file('file')->store($directory)) {
            return $this->respondError(
                'There has been an error while saving a file. Please try again.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        $CSVImporter = new CSVContactImporter($file);
        if ($CSVImporter->import()) {
            Storage::delete($file);

            return $this->respondNoContent();
        }

        Storage::delete($file);
        return $this->respondError('CSV import failed.', Response::HTTP_BAD_REQUEST);
    }

    public function trends($type)
    {
        $model = Contact::class;
        switch ($type) {
            case 'created':
                return $this->respond([
                    'trend' => $this->aggregate($model, 'day', 'count', 'id', 'created_at')
                ]);
            default:
                return $this->respondMessage('unknown metric');
        }
    }

    private function aggregate($model, $unit, $function, $column, $dateColumn = null)
    {
        $endingDate = now();
        $endingDate->minute = 59;
        $endingDate->hour = 23;

        $startingDate = now()->subMonth();
        $startingDate->minute = 59;
        $startingDate->hour = 23;
        $startingDate = $startingDate->addMinute();

        $query = $model instanceof Builder ? $model : (new $model())->newQuery();

        $wrappedColumn = $query
            ->getQuery()
            ->getGrammar()
            ->wrap($column);
        $dateColumn = $dateColumn ?: 'created_at';
        $results = $query
            ->select(
                DB::raw(
                    "date_format(`{$dateColumn}`, '%Y-%m-%d') as date_result, count({$wrappedColumn}) as aggregate"
                )
            )
            ->whereBetween('created_at', [$startingDate, $endingDate])
            ->where('user_id', auth()->id())
            ->groupBy(DB::raw("date_format(`created_at`, '%Y-%m-%d')"))
            ->orderBy('date_result')
            ->get();
        $possibleDateResults = $this->getAllPossibleDateResults($startingDate, $endingDate);

        $results = array_merge(
            $possibleDateResults,
            $results
                ->mapWithKeys(function ($result) use ($unit) {
                    return [
                        $this->formatAggregateResultDate($result->date_result) => round(
                            $result->aggregate,
                            0
                        )
                    ];
                })
                ->all()
        );

        return [
            'value' => last($results),
            'trend' => $results
        ];
    }

    private function formatAggregateResultDate($date)
    {
        $date = as_date($date);
        return __($date->format('F')) . ' ' . $date->format('j') . ', ' . $date->format('Y');
    }

    protected function getAllPossibleDateResults($startingDate, $endingDate)
    {
        $possibleDateResults = [];
        $date = $startingDate;

        $possibleDateResults[$this->formatAggregateResultDate($date)] = 0;

        while ($date->lt($endingDate)) {
            $date = $date->addDay();

            if ($date->lte($endingDate)) {
                $possibleDateResults[$this->formatAggregateResultDate($date)] = 0;
            }
        }

        return $possibleDateResults;
    }
}
