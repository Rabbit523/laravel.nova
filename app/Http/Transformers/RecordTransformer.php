<?php
namespace App\Http\Transformers;

class RecordTransformer extends Transformer
{
    protected $resourceName = 'record';

    public function transform($data)
    {
        $meta = is_array($data['meta']) ? $data['meta'] : [];

        $record_data = $data->toArray();
        $monthly = isset($record_data['monthly']);

        $detailed = $this->getAggregatedRecords($record_data, $data);

        if (!empty($data['autofill'])) {
            $meta['cost'] = array_only($data['autofill'], ['price', 'quantity']);
            $meta['period'] = array_only($data['autofill'], ['duration', 'start_from']);
        }

        $product = null;
        if ($record_data['plan_id']) {
            $plan = array_get($record_data, 'plan');
            $product = array_get($record_data, 'product');
            $plan['product_name'] = array_get($product, 'name', 'deleted');
            $product = $plan;
        } elseif ($record_data['product_id']) {
            $product = array_get($record_data, 'product');
            $product['product_name'] = array_get($product, 'name', 'deleted');
        }

        if ($data['type'] == 'revenue' && !empty($record_data['contact'])) {
            $data['name'] = $this->getContactName($record_data['contact']);
        }

        $contact = null;
        if (!empty($record_data['contact'])) {
            $contact = $record_data['contact'];
            $contact['name'] = $this->getContactName($record_data['contact']);
        } elseif ($data['contact_id']) {
            $contact = $data['contact_id'];
        }

        return array_merge($meta, [
            'id' => $data['id'] ?? '',
            'project_id' => $data['project_id'],
            'type' => $data['type'],
            'planned' => $data['planned'],
            'direct' => $data['direct'],
            'contact' => $contact,
            'product' => $product,
            'category' => $data['category_code'],
            'name' => $data['name'],
            'monthly' => $monthly ? $detailed : [],
            'daily' => $monthly ? [] : $detailed,
            'created_at' => $data['created_at']->format("Y-m-d"),
            'auto' => !empty($data['autofill']),
        ]);
    }

    private function getAggregatedRecords($record_data, $data)
    {
        // FIXME: get date from request
        $date = array_get(
            request()
                ->route()
                ->parameters(),
            'date',
            false
        );

        $monthly = !$date;

        $project_duration = $data['project']->duration;
        $project_start = as_date($data['project']->start_date, 'UTC');
        $detailed = [];

        if ($monthly) {
            foreach ($data['monthly'] as $record) {
                if (as_date($record->date, 'UTC')->lt($project_start)) {
                    logger()->debug("skipped record before project start!", [$record]);
                    continue;
                }
                $month = $project_start->diffInMonths(as_date($record->date, 'UTC'), false);

                $detailed[$month] = [
                    'date' => $record->date->format("Y-m"),
                    'price' => $record->price,
                    'quantity' => $record->quantity,
                    'total' => $record->getTotal(),
                    'meta' => $record->meta ?? [],
                ];
            }
        } else {
            $project_duration = as_date($date, 'UTC')->daysInMonth;
            foreach ($data['daily'] as $key => $record) {
                $detailed[$record->date->day - 1] = [
                    'date' => $record->date->format("Y-m-d"),
                    'price' => $record->price,
                    'quantity' => $record->quantity,
                    'total' => $record->getTotal(),
                    'meta' => $record->meta ?? [],
                ];
            }
        }

        // fill empty records with zero values
        for ($i = 0; $i < $project_duration; $i++) {
            if (isset($detailed[$i])) {
                continue;
            }
            $detailed[$i] = [
                'price' => 0,
                'quantity' => 0,
                'meta' => $data->getPriceFields(),
            ];
        }
        // TODO: sort by date
        ksort($detailed);

        return $detailed;
    }

    private function getContactName($contact)
    {
        if ($contact['is_company'] && $contact['name']) {
            return $contact['name'];
        }
        if ($contact['first_name'] || $contact['last_name']) {
            return trim($contact['last_name'] . ' ' . $contact['first_name']);
        }
        if ($contact['name']) {
            return $contact['name'];
        }
        if ($contact['email']) {
            return $contact['email'];
        }
        return 'no name';
    }
}
