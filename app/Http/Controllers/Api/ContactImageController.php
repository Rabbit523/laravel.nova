<?php

namespace App\Http\Controllers\Api;

use App\Contact;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Api\ContactImage;
use App\Services\AWS\AwsS3Service;
use Aws\Exception\AwsException;
use League\Flysystem\FileNotFoundException;
use Illuminate\Support\Facades\Storage;

class ContactImageController extends ApiController
{
    /**
     * ContactImageController constructor.
     *
     * @param AwsS3Service $awsS3Service
     */
    public function __construct(AwsS3Service $awsS3Service)
    {
        $this->awsS3Service = $awsS3Service;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  ContactImage  $request
     * @param  Contact       $contact
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(ContactImage $request, Contact $contact)
    {
        try {
            $bucket_name = $this->awsS3Service->createBucketIfNotExist();

            $this->awsS3Service->setBucketName($bucket_name);
            $path = Storage::cloud()->putFile('contact_images', $request->file('file'));

            $contact->update(['contact_image' => $path]);

            return $this->respondNoContent();
        } catch (AwsException $e) {
            log_error($e);
            return $this->respondError('Error saving image', 500);
        } catch (\Exception $e) {
            log_error($e);
            return $this->respondInternalError();
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  Contact  $contact
     * @return \Illuminate\Http\Response
     */
    public function show(Contact $contact)
    {
        try {
            return $this->awsS3Service
                ->setUser($contact->user)
                ->setBucketName()
                ->downloadImageFromUserBucket($contact->contact_image);
        } catch (AwsException | FileNotFoundException $e) {
            return response('Image not found', 404);
        } catch (\Exception $e) {
            log_error($e);
            return response('Internal server error', 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param ContactImage $request
     * @param  Contact  $contact
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(ContactImage $request, Contact $contact)
    {
        try {
            $this->awsS3Service->setBucketName();
            Storage::cloud()->delete($contact->contact_image);

            $contact->update(['contact_image' => null]);

            return $this->respond([
                'status' => true,
                'message' => 'Image deleted successfully'
            ]);
        } catch (AwsException $e) {
            log_error($e);
            return $this->respondError('Error removing image', 500);
        } catch (\Exception $e) {
            log_error($e);
            return $this->respondInternalError();
        }
    }
}
