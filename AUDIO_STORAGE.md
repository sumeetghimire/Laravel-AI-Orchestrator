# Audio Storage Configuration

This guide explains how to configure audio file storage for the Laravel AI Orchestrator package.

## Overview

The package supports flexible audio storage configuration for text-to-speech (TTS) generated audio files. You can customize:
Storage disk (local, public, S3, etc.)
Storage path structure
User-specific folders
Automatic cleanup
Public URL paths

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# Audio Storage Configuration
AI_AUDIO_DISK=public              # Storage disk (public, local, s3, etc.)
AI_AUDIO_PATH=audio               # Base path within storage disk
AI_AUDIO_PUBLIC_PATH=storage/audio # Public URL path
AI_AUDIO_USER_SUBFOLDER=true      # Store in user-specific folders
AI_AUDIO_AUTO_CLEANUP=false       # Auto cleanup old files
AI_AUDIO_CLEANUP_DAYS=30          # Cleanup files older than X days
```

### Config File

The configuration is in `config/ai.php`:

```php
'audio' => [
    'storage_disk' => env('AI_AUDIO_DISK', 'public'),
    'storage_path' => env('AI_AUDIO_PATH', 'audio'),
    'public_path' => env('AI_AUDIO_PUBLIC_PATH', 'storage/audio'),
    'user_subfolder' => env('AI_AUDIO_USER_SUBFOLDER', true),
    'auto_cleanup' => env('AI_AUDIO_AUTO_CLEANUP', false),
    'cleanup_after_days' => env('AI_AUDIO_CLEANUP_DAYS', 30),
],
```

## Storage Options

### Option 1: Public Storage (Default)

Files are stored in `storage/app/public/audio/` and accessible via public URLs.

```env
AI_AUDIO_DISK=public
AI_AUDIO_PATH=audio
```

**Structure:**
```
storage/app/public/audio/
├── 1/                    # User ID 1
│   ├── tts_1234567890_abc123.mp3
│   └── tts_1234567891_def456.mp3
├── 2/                    # User ID 2
│   └── tts_1234567892_ghi789.mp3
└── guest/                # Guest users
    └── tts_1234567893_jkl012.mp3
```

**Usage:**
```php
$audioPath = Ai::speak("Hello world")->toAudio();
$url = Storage::disk('public')->url($audioPath);
```

### Option 2: Private Storage

Files are stored in `storage/app/audio/` and not accessible via public URLs.

```env
AI_AUDIO_DISK=local
AI_AUDIO_PATH=audio
```

**Usage:**
```php
$audioPath = Ai::speak("Hello world")->toAudio();
$url = Storage::disk('local')->url($audioPath);
```

### Option 3: Cloud Storage (S3, etc.)

Store audio files in cloud storage like AWS S3.

```env
AI_AUDIO_DISK=s3
AI_AUDIO_PATH=audio
```

**Setup:**
1. Configure S3 in `config/filesystems.php`
2. Set `AI_AUDIO_DISK=s3` in `.env`

**Usage:**
```php
$audioPath = Ai::speak("Hello world")->toAudio();
$url = Storage::disk('s3')->url($audioPath);
```

### Option 4: Custom Storage Path

Specify a custom output path when generating audio.

```php
$audioPath = Ai::speak("Hello world")
->withOptions([
        'output_path' => storage_path('app/custom/audio/output.mp3'),
    ])
->toAudio();
$audioPath = Ai::speak("Hello world")
->withOptions([
        'output_path' => 'custom/audio/output.mp3',
        'disk' => 'public',
    ])
->toAudio();
```

## User-Specific Folders

By default, audio files are organized by user ID:

```env
AI_AUDIO_USER_SUBFOLDER=true
```

**Structure:**
```
audio/
├── 1/          # User ID 1
├── 2/          # User ID 2
└── guest/      # Guest users
```

**Disable user folders:**
```env
AI_AUDIO_USER_SUBFOLDER=false
```

**Structure:**
```
audio/
├── tts_1234567890_abc123.mp3
├── tts_1234567891_def456.mp3
└── tts_1234567892_ghi789.mp3
```

## Automatic Cleanup

Enable automatic cleanup of old audio files:

```env
AI_AUDIO_AUTO_CLEANUP=true
AI_AUDIO_CLEANUP_DAYS=30
```

**Manual Cleanup:**
```php
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

$disk = config('ai.audio.storage_disk', 'public');
$path = config('ai.audio.storage_path', 'audio');
$days = config('ai.audio.cleanup_after_days', 30);

$files = Storage::disk($disk)->allFiles($path);
$cutoff = Carbon::now()->subDays($days);

foreach ($files as $file) {
    $lastModified = Carbon::createFromTimestamp(
        Storage::disk($disk)->lastModified($file)
    );
    
    if ($lastModified->lt($cutoff)) {
        Storage::disk($disk)->delete($file);
    }
}
```

## Examples

### Example 1: Basic Usage (Auto Storage)

```php
use Sumeetghimire\AiOrchestrator\Facades\Ai;
use Illuminate\Support\Facades\Storage;
$audioPath = Ai::speak("Hello, this is a test")
->using('openai')
->toAudio();
$url = Storage::disk('public')->url($audioPath);
```

### Example 2: Custom Path

```php
$audioPath = Ai::speak("Custom message")
->using('openai')
->withOptions([
        'output_path' => storage_path('app/public/audio/custom/output.mp3'),
    ])
->toAudio();
```

### Example 3: User-Specific Storage

```php
$audioPath = Ai::speak("User-specific audio")
->using('openai')
->toAudio();
```

### Example 4: Get Base64 (No Storage)

```php
$base64 = Ai::speak("Quick test")
->using('openai')
->withOptions([
        'output_path' => null, // Don't save
    ])
->toAudio();
return response(base64_decode($base64))
->header('Content-Type', 'audio/mpeg');
```

### Example 5: Download Audio File

```php
Route::get('/audio/{path}', function ($path) {
    $disk = config('ai.audio.storage_disk', 'public');
    
    if (!Storage::disk($disk)->exists($path)) {
        abort(404);
    }
    
    return Storage::disk($disk)->download($path);
})->where('path', '.*');
```

## Best Practices

1. **Use Public Storage for User-Generated Content**
Files are accessible via URLs
Good for temporary audio files

2. **Use Private Storage for Sensitive Content**
Files require authentication to access
Better for sensitive or private audio

3. **Use Cloud Storage for Scalability**
Offload storage to S3, etc.
Better for production environments

4. **Enable Cleanup for Temporary Files**
Prevents storage bloat
Automatically removes old files

5. **Use User-Specific Folders**
Better organization
Easier to manage per-user files

## Troubleshooting

### Files Not Accessible

Make sure the storage link is created:
```bash
php artisan storage:link
```

### Permission Issues

Ensure storage directory has write permissions:
```bash
chmod -R 775 storage/app/public/audio
```

### Storage Disk Not Found

Check `config/filesystems.php` for disk configuration.

### Base64 Returned Instead of Path

If no `output_path` is specified and storage fails, base64 is returned. Check:
Storage disk permissions
Storage path configuration
User ID availability (if `user_subfolder` is enabled)

