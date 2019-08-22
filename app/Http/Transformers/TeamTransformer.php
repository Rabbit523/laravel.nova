<?php

namespace App\Http\Transformers;

class TeamTransformer extends Transformer
{
    protected $resourceName = 'team';

    public function transform($data)
    {
        $team = [
            'id' => $data['id'],
            'user_id' => $data['user_id'],
            'name' => $data['name'],

            'projects_count' => $data['projects_count'],
            'members_count' => $data['members_count'],

            'created_at' => $data['created_at']->toAtomString(),
            'updated_at' => $data['updated_at']->toAtomString()
        ];
        $data = $data->toArray();
        if (isset($data['projects'])) {
            $team['projects'] = $data['projects'];
        }
        if (isset($data['members'])) {
            $team['members'] = $data['members'];
        }
        return $team;
    }
}
