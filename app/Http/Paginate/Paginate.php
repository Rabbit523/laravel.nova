<?php

namespace App\Http\Paginate;

use Illuminate\Database\Eloquent\Builder;

class Paginate
{
    /**
     * Total count of the items.
     *
     * @var int
     */
    protected $total;

    /**
     * Collection of items.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $data;

    /**
     * Paginate constructor.
     *
     * @param int $limit
     * @param int $offset
     */
    public function __construct($builder, $limit = 10, $offset = 0)
    {
        $limit = request()->get('rowsPerPage', $limit);
        $page = request()->get('page', 1);
        $sortBy = request()->get('sortBy', false);
        $desc = request()->get('descending', false);
        $sort = $desc != 'false' ? 'desc' : 'asc';
        $page = $page < 1 ? 1 : $page;
        $limit = $limit > 50 || $limit < 0 ? 50 : $limit;

        $offset = $offset ?: $limit * ($page - 1);

        $this->total = $builder->count();
        if ($sortBy) {
            // get model table name to prevent ambiguous column if query had joined tables
            $builder = $builder->orderBy(
                $builder
                    ->getQuery()
                    ->getModel()
                    ->getTable() .
                    '.' .
                    $sortBy,
                $sort
            );
        } else {
            $builder = $builder->latest();
        }
        $this->data = $builder
            ->skip($offset)
            ->take($limit)
            ->get();
    }

    /**
     * Get the total count of the items.
     *
     * @return int
     */
    public function getTotal()
    {
        return $this->total;
    }

    /**
     * Get the paginated collection of items.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getData()
    {
        return $this->data;
    }
}
