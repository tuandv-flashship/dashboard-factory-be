### Media Container

Container path: `app/Containers/AppSection/Media`

### Scope

- Upload media files (regular and chunk upload flows).
- List media and folder trees/lists.
- Create media folders.
- Build signed/authorized download URLs.
- Resolve/show media files by hashed route params.
- Run global media actions.

### API Routes

Route files:
- `app/Containers/AppSection/Media/UI/API/Routes/ListMedia.v1.private.php`
- `app/Containers/AppSection/Media/UI/API/Routes/ListMediaFolderTree.v1.private.php`
- `app/Containers/AppSection/Media/UI/API/Routes/ListMediaFolderList.v1.private.php`
- `app/Containers/AppSection/Media/UI/API/Routes/CreateMediaFolder.v1.private.php`
- `app/Containers/AppSection/Media/UI/API/Routes/UploadMediaFile.v1.private.php`
- `app/Containers/AppSection/Media/UI/API/Routes/ShowMediaFile.v1.private.php`
- `app/Containers/AppSection/Media/UI/API/Routes/DownloadMediaFile.v1.private.php`
- `app/Containers/AppSection/Media/UI/API/Routes/MediaGlobalAction.v1.private.php`

Media API auth model:
- Most endpoints use `auth:api`.
- `ShowMediaFile` (`media.indirect.url`) is public-by-design and protected by route throttle.

### Main Config

- `app/Containers/AppSection/Media/Configs/media.php`
- `app/Containers/AppSection/Media/Configs/permissions.php`

Common env keys:
- `MEDIA_DISK`, `MEDIA_DRIVER`, `MEDIA_PRIVATE_DISK`, `MEDIA_PRIVATE_ACCESS_MODE`
- `MEDIA_SIGNED_URL_TTL_MINUTES`
- `MEDIA_CHUNK_ENABLED`, `MEDIA_CHUNK_SIZE`, `MEDIA_MAX_FILE_SIZE`
- `MEDIA_USER_ITEM_CACHE_TTL_SECONDS`
- `MEDIA_SHOW_FILE_THROTTLE`
- `MEDIA_ALLOWED_MIME_TYPES`
- `MEDIA_USE_STORAGE_SYMLINK`

### Operational Notes

- Chunk cleanup scheduling config is under `media.chunk.clear`.
- Recent/favorites metadata cache TTL is configured by `media.cache.user_item_ttl_seconds`.
- **AuditLog & file uploads**: `AuditHandlerEvent` strips `UploadedFile` instances from request input before serialization to prevent queue failures. File names are preserved as `[file:filename.ext]` in audit logs.

### Upload File Size Limits

Default max upload file size: **50 MB** (`50 * 1024 * 1024 = 52428800 bytes`).

Override via `.env`:

```env
# 50 MB (default)
MEDIA_MAX_FILE_SIZE=52428800

# 100 MB
MEDIA_MAX_FILE_SIZE=104857600
```

**Important**: When changing the upload limit, also update server-level configs:

| Layer | Setting | Example (50 MB) |
|-------|---------|-----------------|
| **PHP** | `upload_max_filesize` | `50M` |
| **PHP** | `post_max_size` | `55M` (slightly larger) |
| **Nginx** | `client_max_body_size` | `55m` |

If any layer has a lower limit than `MEDIA_MAX_FILE_SIZE`, uploads will fail at that layer before reaching the app.

### Tests

Available tests:
- `app/Containers/AppSection/Media/Tests/Functional/API`
- `app/Containers/AppSection/Media/Tests/Unit`

Run:

```bash
php artisan test app/Containers/AppSection/Media/Tests
```

### Change Log

- `2026-02-24`: Default max upload size changed from 1 MB to 50 MB. Added AuditLog serialization fix for file uploads.
- `2026-02-07`: Added dedicated Media container documentation.
