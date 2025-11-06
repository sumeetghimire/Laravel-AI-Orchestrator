# Dashboard Security Guide

## Security by Default

The dashboard is disabled by default for security reasons. You must explicitly enable it in your `.env` file.

## How to Enable Dashboard

### Step 1: Enable Dashboard

Add to your `.env` file:

```env
AI_DASHBOARD_ENABLED=true
```

### Step 2: Protect with Middleware

**Option A: Use Laravel's built-in auth middleware**

Update your `.env`:
```env
AI_DASHBOARD_ENABLED=true
AI_DASHBOARD_MIDDLEWARE=auth
```

**Option B: Use custom middleware (recommended for admin)**

First, create a middleware or use existing one:

```php
// In routes/web.php or your route service provider
Route::prefix('ai-orchestrator')
    ->middleware(['auth', 'admin']) // Add your admin middleware
    ->name('ai-orchestrator.')
    ->group(function () {
        // Dashboard routes will be registered here
    });
```

Or set in `.env`:
```env
AI_DASHBOARD_ENABLED=true
AI_DASHBOARD_MIDDLEWARE=auth,admin
```

**Option C: IP Whitelist (for local development)**

Create a middleware to whitelist IPs:

```php
// app/Http/Middleware/IpWhitelist.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IpWhitelist
{
    public function handle(Request $request, Closure $next)
    {
        $allowedIps = ['127.0.0.1', '::1', '192.168.1.0/24'];
        
        if (!in_array($request->ip(), $allowedIps)) {
            abort(403);
        }
        
        return $next($request);
    }
}
```

Then set in `.env`:
```env
AI_DASHBOARD_ENABLED=true
AI_DASHBOARD_MIDDLEWARE=web,ip.whitelist
```

**Option D: Disable in Production**

```env
# .env.production
AI_DASHBOARD_ENABLED=false

# .env.local
AI_DASHBOARD_ENABLED=true
AI_DASHBOARD_MIDDLEWARE=auth
```

## Configuration Options

In `config/ai.php`:

```php
'dashboard' => [
    'enabled' => env('AI_DASHBOARD_ENABLED', false), // Disabled by default
    'middleware' => env('AI_DASHBOARD_MIDDLEWARE', 'web'), // Protection middleware
    'prefix' => env('AI_DASHBOARD_PREFIX', 'ai-orchestrator'), // URL prefix
],
```

## Best Practices

1. **Never enable dashboard in production without middleware**
   ```env
   # ❌ BAD - No protection
   AI_DASHBOARD_ENABLED=true
   AI_DASHBOARD_MIDDLEWARE=web
   
   # ✅ GOOD - Protected
   AI_DASHBOARD_ENABLED=true
   AI_DASHBOARD_MIDDLEWARE=auth
   ```

2. **Use role-based access**
   ```env
   AI_DASHBOARD_ENABLED=true
   AI_DASHBOARD_MIDDLEWARE=auth,role:admin
   ```

3. **Disable in production by default**
   ```env
   # Production
   AI_DASHBOARD_ENABLED=false
   
   # Development
   AI_DASHBOARD_ENABLED=true
   AI_DASHBOARD_MIDDLEWARE=auth
   ```

4. **Change the URL prefix**
   ```env
   AI_DASHBOARD_ENABLED=true
   AI_DASHBOARD_PREFIX=admin-ai-dashboard
   ```

## Example: Admin-Only Dashboard

```env
AI_DASHBOARD_ENABLED=true
AI_DASHBOARD_MIDDLEWARE=auth,admin
AI_DASHBOARD_PREFIX=admin/ai
```

This will:
- Require authentication
- Require admin role
- Be accessible at `/admin/ai/dashboard`

## Security Checklist

- [ ] Dashboard is disabled by default
- [ ] Middleware protection is configured
- [ ] Authentication is required
- [ ] Role/permission checks are in place
- [ ] Dashboard is disabled in production (or properly secured)
- [ ] URL prefix is changed from default
- [ ] Sensitive data is not exposed in views

