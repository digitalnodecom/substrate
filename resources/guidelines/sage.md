# Sage Theme Development Guidelines

## Theme Structure

```
sage/
├── app/                    # PHP application code
│   ├── Providers/          # Service Providers
│   ├── View/
│   │   ├── Composers/      # View Composers
│   │   └── Components/     # Blade Components
│   ├── filters.php         # WordPress filters
│   └── setup.php           # Theme setup
├── config/                 # Configuration files
├── public/                 # Built assets (generated)
├── resources/
│   ├── fonts/              # Font files
│   ├── images/             # Image assets
│   ├── scripts/            # JavaScript/TypeScript
│   ├── styles/             # CSS/SCSS/PostCSS
│   └── views/              # Blade templates
│       ├── components/     # Blade components
│       ├── forms/          # Form templates
│       ├── layouts/        # Layout templates
│       ├── partials/       # Partial templates
│       └── sections/       # Section templates
└── bud.config.js           # Bud/Vite configuration
```

## Blade Templates

### Views Location
All views are in `resources/views/` with `.blade.php` extension.

### Layouts
```blade
{{-- resources/views/layouts/app.blade.php --}}
<!doctype html>
<html @php(language_attributes())>
  <head>
    @include('partials.head')
  </head>
  <body @php(body_class())>
    @include('partials.header')
    <main>
      @yield('content')
    </main>
    @include('partials.footer')
  </body>
</html>
```

### Page Templates
```blade
{{-- resources/views/page.blade.php --}}
@extends('layouts.app')

@section('content')
  @while(have_posts()) @php(the_post())
    @include('partials.page-header')
    @include('partials.content-page')
  @endwhile
@endsection
```

### Using WordPress Functions
```blade
@php
  $posts = get_posts([
    'post_type' => 'post',
    'posts_per_page' => 5,
  ]);
@endphp

@foreach($posts as $post)
  <article>
    <h2>{{ $post->post_title }}</h2>
  </article>
@endforeach
```

## View Composers

### Creating a Composer
```php
namespace App\View\Composers;

use Roots\Acorn\View\Composer;

class Navigation extends Composer
{
    protected static $views = ['partials.navigation'];

    public function with(): array
    {
        return [
            'primaryMenu' => $this->primaryMenu(),
        ];
    }

    protected function primaryMenu(): array
    {
        return wp_get_nav_menu_items('primary') ?: [];
    }
}
```

### Registering Composers
Composers are auto-discovered from `app/View/Composers/`.

## Blade Components

### Creating a Component
```php
namespace App\View\Components;

use Illuminate\View\Component;

class Button extends Component
{
    public function __construct(
        public string $type = 'button',
        public string $variant = 'primary',
    ) {}

    public function render()
    {
        return view('components.button');
    }
}
```

### Using Components
```blade
<x-button type="submit" variant="secondary">
  Submit Form
</x-button>
```

## Service Providers

### Creating a Provider
```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class CustomProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register bindings
    }

    public function boot(): void
    {
        // Bootstrap services
        add_action('init', [$this, 'registerPostTypes']);
    }

    public function registerPostTypes(): void
    {
        register_post_type('event', [
            'labels' => ['name' => 'Events'],
            'public' => true,
            'show_in_rest' => true,
            'supports' => ['title', 'editor', 'thumbnail'],
        ]);
    }
}
```

## Asset Compilation

### Bud Configuration
```javascript
// bud.config.js
export default async (app) => {
  app
    .entry('app', ['@scripts/app', '@styles/app'])
    .entry('editor', ['@scripts/editor', '@styles/editor'])
    .assets(['images'])
    .watch(['resources/views/**/*', 'app/**/*']);
};
```

### Development
```bash
# Start development server
yarn dev

# Build for production
yarn build
```

## Best Practices

1. **Use View Composers** for complex data logic instead of putting it in templates
2. **Create components** for reusable UI elements
3. **Keep templates clean** - minimal PHP logic in Blade files
4. **Use WordPress hooks** through Service Providers
5. **Follow PSR-12** for PHP code
6. **Type-hint** all method parameters and return types
