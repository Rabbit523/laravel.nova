<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Api\ContactTags;
use Illuminate\Http\Request;

class ContactTagController extends ApiController
{
    /**
     * Get all Tags.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function tags()
    {
        return $this->respond([
            'tags' => user()
                ->contacts()
                ->select('taggable_tags.name')
                ->leftJoin('taggable_taggables', function ($join) {
                    $join
                        ->on('taggable_taggables.taggable_id', '=', 'contacts.id')
                        ->where('taggable_taggables.taggable_type', '=', 'App\\Contact');
                })
                ->leftJoin(
                    'taggable_tags',
                    'taggable_tags.tag_id',
                    '=',
                    'taggable_taggables.tag_id'
                )
                ->whereNotNull('taggable_tags.name')
                ->get()
                ->map(function ($t) {
                    return ucfirst($t->name);
                })
        ]);
    }

    /**
     * Bulk create tags for contacts
     *
     * @param ContactTags $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkCreate(ContactTags $request)
    {
        return $this->respond([
            'contacts' => user()
                ->contacts()
                ->findOrFail($request->get('contacts'))
                ->map(function ($contact) use ($request) {
                    return $contact->tag($request->get('tags'));
                })
        ]);
    }

    /**
     * Bulk delete tags for contacts
     *
     * @param ContactTags $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDestroy(ContactTags $request)
    {
        return $this->respond([
            'contacts' => user()
                ->contacts()
                ->findOrFail($request->get('contacts'))
                ->map(function ($contact) use ($request) {
                    return $contact->untag($request->get('tags'));
                })
        ]);
    }
}
