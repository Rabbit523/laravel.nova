<?php
namespace App\Services\AWS;

use Illuminate\Support\Facades\Storage;
use App\User;

class AwsS3Service
{
    /**
     * @var $aws_client - S3 client
     */
    private $aws_client;
    /**
     * @var App\User
     */
    private $user;

    /**
     * AwsS3Service constructor.
     *
     */
    public function __construct()
    {
        $this->aws_client = Storage::cloud()
            ->getDriver()
            ->getAdapter()
            ->getClient();
    }

    /**
     * Get default user bucket name
     *
     * @return string
     */
    public function getUserBucketName()
    {
        $bucket_prefix = 'kinchaku-';

        return $bucket_prefix . str_replace('-', '', $this->getUser()->id);
    }

    private function getUser()
    {
        return $this->user ?: user();
    }

    /**
     * Set default user
     *
     * @param  App\User $user
     * @return $this
     */
    public function setUser(User $user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Set aws bucket name, default - $this->getUserBucketName()
     *
     * @param string $bucket_name
     * @return $this
     */
    public function setBucketName($bucket_name = null)
    {
        $bucket_name = $bucket_name ?? $this->getUserBucketName();

        Storage::cloud()
            ->getDriver()
            ->getAdapter()
            ->setBucket($bucket_name);

        return $this;
    }

    /**
     * A method to check if bucket exist, create new bucket if not exist
     *
     * @param array $bucket_params
     * @return string return bucket name
     */
    public function createBucketIfNotExist($bucket_params = [])
    {
        if (empty($bucket_params['Bucket'])) {
            $bucket_params['Bucket'] = $this->getUserBucketName();
        }

        if (!$this->aws_client->doesBucketExist($bucket_params['Bucket'])) {
            $this->aws_client->createBucket($bucket_params);
        }

        return $bucket_params['Bucket'];
    }

    /**
     * Put file to cloud user bucket
     *
     * @param $folder_name
     * @param $file
     * @return mixed
     */
    public function putFileToUserBucket($folder_name, $file)
    {
        $bucket_name = $this->createBucketIfNotExist();
        $this->setBucketName($bucket_name);

        return Storage::cloud()->putFile($folder_name, $file);
    }

    /**
     * Download image from user bucket
     *
     * @param $image_path
     * @return mixed
     */
    public function downloadImageFromUserBucket($image_path)
    {
        return Storage::cloud()->download($image_path);
    }

    /**
     * @param string $image_path
     * @return bool
     */
    public function exists($image_path)
    {
        return Storage::cloud()->exists($image_path);
    }

    /**
     * Get the file contents at the given "short" path.
     *
     * @param  string  $image_path
     * @return string
     */
    public function get($image_path)
    {
        return Storage::cloud()->get($image_path);
    }

    /**
     * Copy image from s3 to local storage.
     * @param string $from
     * @param string $to
     * @return bool True on success, false on failure.
     */
    public function copy($from, $to)
    {
        $inputStream = Storage::cloud()->getDriver()->readStream($from);
        return Storage::disk('local')->getDriver()->putStream($to, $inputStream);
    }
}
