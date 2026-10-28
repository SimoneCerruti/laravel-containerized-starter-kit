### Backup on Google Drive

1. In Google Cloud Console create a project, enable the **Google Drive API** and create an OAuth client (type *Web application*, redirect `https://developers.google.com/oauthplayground`).
2. On [OAuth Playground](https://developers.google.com/oauthplayground) use your own credentials, authorize the scope `https://www.googleapis.com/auth/drive` and exchange the code for a **refresh token**.
3. Set `GOOGLE_DRIVE_CLIENT_ID`, `GOOGLE_DRIVE_CLIENT_SECRET`, `GOOGLE_DRIVE_REFRESH_TOKEN` and `GOOGLE_DRIVE_FOLDER` (default `Backups/__KIT_APP_SLUG__`) in the `.env` of the environment.
4. Publish the OAuth consent screen (or keep it in *Production*): in *Testing* the refresh token expires after 7 days.
5. Run `php artisan backup:run` once and check the file in Drive.
