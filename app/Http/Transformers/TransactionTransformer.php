<?php
namespace App\Http\Transformers;

class TransactionTransformer extends Transformer
{
    protected $resourceName = 'transaction';

    public function transform($data)
    {
        return $data->toArray();
    }
}
