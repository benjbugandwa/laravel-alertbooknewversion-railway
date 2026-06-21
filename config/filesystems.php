<?php

$envValue = static function (array $keys, mixed $default = null): mixed {
    foreach ($keys as $key) {
        $value = env($key);

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value !== null && $value !== '' && !in_array(strtolower((string) $value), ['undefined', 'null'], true)) {
            return $value;
        }
    }

    return $default;
};

$documentationDisk = $envValue(['ALERTBOOK_DOCUMENTATION_DISK']);
$documentationPrefix = $envValue(['ALERTBOOK_DOCUMENTATION_PREFIX']);
$documentationDiskIsBucket = is_string($documentationDisk)
    && !in_array($documentationDisk, ['s3', 'local', 'public'], true)
    && !filter_var($documentationDisk, FILTER_VALIDATE_URL);
$documentationPrefixIsEndpoint = is_string($documentationPrefix)
    && filter_var($documentationPrefix, FILTER_VALIDATE_URL);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => $envValue(['AWS_ACCESS_KEY_ID', 'ACCESS_KEY_ID']),
            'secret' => $envValue(['AWS_SECRET_ACCESS_KEY', 'SECRET_ACCESS_KEY']),
            'region' => $envValue(['AWS_DEFAULT_REGION', 'AWS_REGION', 'REGION'], 'auto'),
            'bucket' => $envValue(
                ['AWS_BUCKET', 'BUCKET', 'ALERTBOOK_DOCUMENTATION_BUCKET'],
                $documentationDiskIsBucket ? $documentationDisk : null
            ),
            'url' => $envValue(['AWS_URL']),
            'endpoint' => $envValue(
                ['AWS_ENDPOINT', 'ENDPOINT', 'ALERTBOOK_DOCUMENTATION_ENDPOINT'],
                $documentationPrefixIsEndpoint ? $documentationPrefix : null
            ),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
