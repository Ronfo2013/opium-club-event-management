<?php

namespace App\Lib;

use Google\Cloud\Storage\StorageClient;

class GcsUploader
{
    private $bucketName;
    private $storage;

    public function __construct(string $bucketName)
    {
        $this->bucketName = $bucketName;
        $this->storage = new StorageClient();
    }

    public function upload(string $destinationPath, string $localFilePath, ?string $contentType = null): string
    {
        $bucket = $this->storage->bucket($this->bucketName);
        $options = [
            'name' => ltrim($destinationPath, '/'),
            'predefinedAcl' => 'publicRead',
        ];
        if ($contentType) {
            $options['metadata'] = ['contentType' => $contentType];
        }
        $bucket->upload(fopen($localFilePath, 'r'), $options);
        return sprintf('https://storage.googleapis.com/%s/%s', $this->bucketName, ltrim($destinationPath, '/'));
    }

    public function uploadString(string $destinationPath, string $contents, ?string $contentType = 'application/json'): string
    {
        $bucket = $this->storage->bucket($this->bucketName);
        $options = [
            'name' => ltrim($destinationPath, '/'),
            'predefinedAcl' => 'publicRead',
        ];
        if ($contentType) {
            $options['metadata'] = ['contentType' => $contentType];
        }
        $bucket->upload($contents, $options);
        return sprintf('https://storage.googleapis.com/%s/%s', $this->bucketName, ltrim($destinationPath, '/'));
    }

    public function objectExists(string $path): bool
    {
        $bucket = $this->storage->bucket($this->bucketName);
        $object = $bucket->object(ltrim($path, '/'));
        return $object->exists();
    }

    public function download(string $path): ?string
    {
        $bucket = $this->storage->bucket($this->bucketName);
        $object = $bucket->object(ltrim($path, '/'));
        if (!$object->exists()) {
            return null;
        }
        return $object->downloadAsString();
    }

    public function listPrefix(string $prefix): array
    {
        $bucket = $this->storage->bucket($this->bucketName);
        $objects = $bucket->objects(['prefix' => rtrim($prefix, '/') . '/']);
        $result = [];
        foreach ($objects as $obj) {
            $name = $obj->name();
            if (substr($name, -1) === '/') continue;
            $result[] = [
                'name' => $name,
                'url' => sprintf('https://storage.googleapis.com/%s/%s', $this->bucketName, $name),
                'updated' => $obj->info()['updated'] ?? null,
            ];
        }
        return $result;
    }

    public function uploadFile(string $localFilePath, string $destinationPath, ?string $contentType = null): string
    {
        return $this->upload($destinationPath, $localFilePath, $contentType);
    }

    public function deleteObject(string $path): bool
    {
        try {
            $bucket = $this->storage->bucket($this->bucketName);
            $object = $bucket->object(ltrim($path, '/'));
            if ($object->exists()) {
                $object->delete();
                return true;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}

