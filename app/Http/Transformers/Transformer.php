<?php

namespace App\Http\Transformers;

use Illuminate\Support\Collection;
use App\Http\Paginate\Paginate;

abstract class Transformer
{
    /**
     * Resource name of the json object.
     *
     * @var string
     */
    protected $resourceName = 'data';

    /**
     * Transform a collection of items.
     *
     * @param Collection $data
     * @return array
     */
    public function collection(Collection $data)
    {
        return [
            str_plural($this->resourceName) => $data->map([$this, 'transform'])
        ];
    }

    /**
     * Transform a single item.
     *
     * @param mixed $data
     * @return array
     */
    public function item($data)
    {
        return [
            $this->resourceName => $this->transform($data)
        ];
    }

    /**
     * Transform a paginated item.
     *
     * @param Paginate $paginated
     * @return array
     */
    public function paginate(Paginate $paginated)
    {
        $resourceName = str_plural($this->resourceName);

        // $countName = str_plural($this->resourceName) . 'Count';

        $data = [
            $resourceName => $paginated->getData()->map([$this, 'transform'])
        ];

        return array_merge($data, [
            'total' => $paginated->getTotal()
        ]);
    }

    /**
     * Apply the transformation.
     *
     * @param mixed $data
     * @return mixed
     */
    abstract public function transform($data);
}
