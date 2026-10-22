Storage::extend('google', function (ApplicationContract $app, array $config): FilesystemAdapter {
    $client = new GoogleClient;
    $client->setClientId($config['clientId']);
    $client->setClientSecret($config['clientSecret']);
    $client->refreshToken($config['refreshToken']);

    $adapter = new GoogleDriveAdapter(new GoogleDrive($client), $config['folder'] ?? '/');

    return new FilesystemAdapter(new Filesystem($adapter, $config), $adapter, $config);
});
