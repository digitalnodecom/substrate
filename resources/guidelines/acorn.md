# Acorn Development Guidelines

## Overview

Acorn brings Laravel's elegant syntax and powerful features to WordPress. It provides:

- Service Container & Dependency Injection
- Blade templating
- Service Providers
- Configuration management
- Console commands
- Facades

## Service Providers

### Creating a Provider
```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     */
    public function register(): void
    {
        $this->app->singleton(MyService::class, function ($app) {
            return new MyService($app['config']['my.setting']);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register WordPress hooks
        add_action('init', [$this, 'registerPostTypes']);

        // Register views
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'myplugin');
    }
}
```

### Auto-Discovery
Providers in `app/Providers/` are auto-discovered by Acorn.

## Dependency Injection

### Constructor Injection
```php
namespace App\View\Composers;

use App\Services\NavigationService;
use Roots\Acorn\View\Composer;

class Navigation extends Composer
{
    public function __construct(
        protected NavigationService $navigation
    ) {}

    public function with(): array
    {
        return [
            'menu' => $this->navigation->getPrimaryMenu(),
        ];
    }
}
```

### Method Injection
```php
public function handle(Request $request, MyService $service)
{
    return $service->process($request->input('data'));
}
```

## Configuration

### Config Files
Located in `config/` directory:

```php
// config/app.php
return [
    'name' => env('APP_NAME', 'My Site'),
    'debug' => env('WP_DEBUG', false),
];
```

### Accessing Config
```php
// Get value
$name = config('app.name');

// Get with default
$value = config('my.setting', 'default');

// Set at runtime
config(['app.timezone' => 'UTC']);
```

## Blade Templating

### Directives
```blade
{{-- Output escaped --}}
{{ $variable }}

{{-- Output unescaped (be careful!) --}}
{!! $html !!}

{{-- WordPress functions --}}
@php(the_title())

{{-- Comments --}}
{{-- This won't be in output --}}
```

### Control Structures
```blade
@if($condition)
    Content
@elseif($other)
    Other
@else
    Fallback
@endif

@foreach($items as $item)
    {{ $item->name }}
@endforeach

@forelse($items as $item)
    {{ $item->name }}
@empty
    No items found.
@endforelse
```

### Custom Directives
```php
// In a service provider boot() method
Blade::directive('datetime', function ($expression) {
    return "<?php echo date('Y-m-d H:i', strtotime({$expression})); ?>";
});

// Usage
@datetime($post->post_date)
```

## Console Commands

### Creating a Command
```php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImportData extends Command
{
    protected $signature = 'app:import {source} {--force}';

    protected $description = 'Import data from external source';

    public function handle(): int
    {
        $source = $this->argument('source');
        $force = $this->option('force');

        $this->info("Importing from {$source}...");

        // Progress bar
        $bar = $this->output->createProgressBar(100);
        $bar->start();

        for ($i = 0; $i < 100; $i++) {
            // Do work
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Import complete!');

        return Command::SUCCESS;
    }
}
```

### Running Commands
```bash
wp acorn app:import external --force
```

## Facades

### Using Facades
```php
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

// Cache
Cache::put('key', 'value', now()->addHours(2));
$value = Cache::get('key');

// Logging
Log::info('Something happened', ['context' => $data]);

// Views
return View::make('my-view', ['data' => $data]);
```

## Routing (Optional)

### Web Routes
```php
// routes/web.php
use Illuminate\Support\Facades\Route;

Route::get('/api/items', function () {
    return response()->json(get_posts(['post_type' => 'item']));
});

Route::post('/api/items', [ItemController::class, 'store']);
```

## Collections

### Working with Data
```php
use Illuminate\Support\Collection;

$posts = collect(get_posts(['post_type' => 'post', 'numberposts' => -1]));

// Filter
$published = $posts->filter(fn ($post) => $post->post_status === 'publish');

// Transform
$titles = $posts->pluck('post_title');

// Group
$byMonth = $posts->groupBy(fn ($post) => date('Y-m', strtotime($post->post_date)));

// Paginate
$page = $posts->forPage(2, 10);
```

## Best Practices

1. **Use Service Providers** for organizing WordPress hooks and registrations
2. **Leverage Dependency Injection** for testable, decoupled code
3. **Use Configuration Files** instead of hardcoding values
4. **Follow PSR-12** coding standards
5. **Type-hint** all parameters and return types
6. **Use Collections** for working with arrays of data
7. **Create Console Commands** for CLI tasks
