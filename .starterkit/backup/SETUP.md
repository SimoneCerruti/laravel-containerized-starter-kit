## Backup

`backup:run` runs every night at 01:30 and saves the database and files to the disks listed in `BACKUP_DISKS`.

1. `local` stores the archive in the same volume as the app: it is **not** an offsite backup. Add a remote disk (see below) for production.
2. Set `BACKUP_ARCHIVE_PASSWORD` to encrypt the archives and `BACKUP_NOTIFICATION_EMAIL` to receive failures (mail must be configured).
3. Check once that a restore works: unzip an archive and load the dump into a scratch database.
