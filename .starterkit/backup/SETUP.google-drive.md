### Backup on Google Drive

Backups are uploaded to Google Drive through an OAuth client and a long-lived **refresh token**. You need three credentials (`client ID`, `client secret`, `refresh token`) and a destination folder. Use the Google account that owns the Drive where backups must end up.

#### 1. Create a Google Cloud project

1. Open [Google Cloud Console](https://console.cloud.google.com/) and sign in.
2. Click the project selector in the top bar > **New project**.
3. Name it (e.g. `__KIT_APP_SLUG__-backup`) and click **Create**.
4. Make sure the new project is selected in the top bar.

#### 2. Enable the Google Drive API

1. Go to **APIs & Services > Library**.
2. Search for **Google Drive API**, open it and click **Enable**.

#### 3. Configure the OAuth consent screen

1. Go to **APIs & Services > OAuth consent screen** (in newer consoles: **Google Auth Platform > Branding / Audience**).
2. Choose **External** as user type and click **Create** / **Get started**.
3. Fill in the required fields: app name (e.g. `__KIT_APP_SLUG__ backup`), user support email and developer contact email.
4. In **Data Access** (or *Scopes*) add the scope `https://www.googleapis.com/auth/drive.file`. It only gives access to the files and folders this app creates, not to the rest of your Drive.
5. In **Audience** (or *Publishing status*) click **Publish app** and confirm, so that the status becomes **In production**.

> **Important:** in *Testing* status the refresh token expires after **7 days** and backups will silently start failing. Verification by Google is not required for this scope when you are the only user: the "unverified app" warning shown during authorization can be skipped (*Advanced > Go to ... (unsafe)*).

#### 4. Create the OAuth client (client ID and secret)

1. Go to **APIs & Services > Credentials** (or **Google Auth Platform > Clients**).
2. Click **Create credentials > OAuth client ID**.
3. Application type: **Web application**. Name: anything (e.g. `OAuth Playground`).
4. Under **Authorized redirect URIs** click **Add URI** and enter exactly:

   ```
   https://developers.google.com/oauthplayground
   ```
5. Click **Create**. Copy the **Client ID** and **Client secret** shown in the dialog (you can find them again later by opening the client).

These are `GOOGLE_DRIVE_CLIENT_ID` and `GOOGLE_DRIVE_CLIENT_SECRET`.

#### 5. Get the refresh token

1. Open [Google OAuth 2.0 Playground](https://developers.google.com/oauthplayground).
2. Click the gear icon (top right), tick **Use your own OAuth credentials**, paste the **Client ID** and **Client secret** and close the panel.
3. In *Step 1* (left column), type the scope in the input at the bottom instead of picking from the list:

   ```
   https://www.googleapis.com/auth/drive.file
   ```

   then click **Authorize APIs**.
4. Sign in with the Google account that will own the backups and accept the permissions. If you see "Google hasn't verified this app", click *Advanced > Go to ... (unsafe)*.
5. In *Step 2* click **Exchange authorization code for tokens**.
6. Copy the **Refresh token** value.

This is `GOOGLE_DRIVE_REFRESH_TOKEN`.

> If the refresh token is missing, revoke the app at [myaccount.google.com/permissions](https://myaccount.google.com/permissions) and repeat from step 3 of this section. The refresh token is only returned the first time an account authorizes a given client. It is a secret: treat it like a password.

#### 6. Choose the destination folder

`GOOGLE_DRIVE_FOLDER` is a path inside your Drive, e.g. `Backups/__KIT_APP_SLUG__` (default). Folders are created automatically on the first backup. Because the `drive.file` scope only sees what the app itself created, **do not** create the folder by hand and do not point it at an existing folder: the app would not see it and would create a second one with the same name.

#### 7. Configure the environment

Set these in the `.env` of the environment (or in the deploy secrets):

```
GOOGLE_DRIVE_CLIENT_ID=<client id>
GOOGLE_DRIVE_CLIENT_SECRET=<client secret>
GOOGLE_DRIVE_REFRESH_TOKEN=<refresh token>
GOOGLE_DRIVE_FOLDER=Backups/__KIT_APP_SLUG__
```

#### 8. Verify

Run `php artisan backup:run` once and check that the zip appears in the folder on [Google Drive](https://drive.google.com/).

#### Troubleshooting

| Error | Cause / fix |
| --- | --- |
| `invalid_grant` | Refresh token expired or revoked: consent screen still in *Testing*, access revoked, or token unused for 6 months. Publish the app and generate a new token. |
| `invalid_client` | Wrong client ID/secret, or the secret was regenerated. |
| `redirect_uri_mismatch` (in the Playground) | The redirect URI in the OAuth client is not exactly `https://developers.google.com/oauthplayground`. |
| `access_denied` / "app not verified" blocked | Add your account under **Audience > Test users**, or publish the app. |
| `Google Drive API has not been used in project ...` | Enable the Drive API (step 2) and wait a minute. |
| Duplicate or missing folders | The folder was created manually or with another scope; remove it and let the app create it. |
