/*
 * Google Drive: create an OAuth client in Google Cloud, publish the consent screen "In production"
 * (in "Testing" the refresh token expires after 7 days) and get the refresh token for the
 * `https://www.googleapis.com/auth/drive.file` scope, which only sees the files this app creates.
 */
'google' => [
    'driver' => 'google',
    'clientId' => env('GOOGLE_DRIVE_CLIENT_ID'),
    'clientSecret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
    'refreshToken' => env('GOOGLE_DRIVE_REFRESH_TOKEN'),
    'folder' => env('GOOGLE_DRIVE_FOLDER'),
    'throw' => true,
    'report' => false,
],
