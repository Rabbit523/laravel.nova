<?php

namespace App\Http\Transformers;

class ProjectTransformer extends Transformer
{
    protected $resourceName = 'project';

    public function transform($data)
    {
        $project = [
            'id' => $data['id'],
            'user_id' => $data['user_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'duration' => $data['duration'],
            'created_at' => $data['created_at']->toAtomString(),
            'updated_at' => $data['updated_at']->toAtomString(),
            'start_date' => $data['start_date']->format("Y-m-d"),
            'currency' => $data['currency'],
            'business_model' => $data['business_model'],
            'with_cost_manager' => $data['with_cost_manager']
            // 'url' => $data['url'],
            // 'model' => $data['simulation'],
            // 'with_model' => $data['with_model'],
            // 'with_launch' => $data['with_launch']
        ];
        $data = $data->toArray();

        if (isset($data['user'])) {
            $project['user'] = [
                'name' => $data['user']['name'],
                'email' => $data['user']['email']
            ];
        }

        if (isset($data['subs'])) {
            $project['subscription'] = $data['subs'];
            $project['subscription']['on_grace_period'] = $data['subs']->onGracePeriod();
        }
        return $project;
    }
}
