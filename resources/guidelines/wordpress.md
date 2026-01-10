# WordPress Development Guidelines

## Hooks System

### Actions
Actions allow you to execute code at specific points.

```php
// Adding an action
add_action('init', function () {
    // Runs during WordPress initialization
});

// With priority (default is 10)
add_action('wp_head', 'my_function', 5);

// Passing arguments
add_action('save_post', function ($post_id, $post) {
    // Access post data
}, 10, 2);
```

### Filters
Filters modify data before it's used or displayed.

```php
// Modifying content
add_filter('the_content', function ($content) {
    return $content . '<p>Added at the end</p>';
});

// With priority
add_filter('the_title', 'my_title_function', 20);
```

## Custom Post Types

```php
add_action('init', function () {
    register_post_type('event', [
        'labels' => [
            'name' => 'Events',
            'singular_name' => 'Event',
            'add_new' => 'Add New Event',
            'add_new_item' => 'Add New Event',
            'edit_item' => 'Edit Event',
            'view_item' => 'View Event',
        ],
        'public' => true,
        'show_in_rest' => true, // Enable Gutenberg
        'menu_icon' => 'dashicons-calendar-alt',
        'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
        'has_archive' => true,
        'rewrite' => ['slug' => 'events'],
    ]);
});
```

## Custom Taxonomies

```php
add_action('init', function () {
    register_taxonomy('event_type', 'event', [
        'labels' => [
            'name' => 'Event Types',
            'singular_name' => 'Event Type',
        ],
        'public' => true,
        'show_in_rest' => true,
        'hierarchical' => true, // Like categories (false = like tags)
        'rewrite' => ['slug' => 'event-type'],
    ]);
});
```

## Database Queries

### WP_Query
```php
$query = new WP_Query([
    'post_type' => 'event',
    'posts_per_page' => 10,
    'meta_query' => [
        [
            'key' => 'event_date',
            'value' => date('Y-m-d'),
            'compare' => '>=',
            'type' => 'DATE',
        ],
    ],
    'orderby' => 'meta_value',
    'meta_key' => 'event_date',
    'order' => 'ASC',
]);

while ($query->have_posts()) {
    $query->the_post();
    // Display post
}
wp_reset_postdata();
```

### Direct Database Access
```php
global $wpdb;

// Prepared statements (ALWAYS use for user input)
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
        'post',
        'publish'
    )
);

// Insert
$wpdb->insert(
    $wpdb->prefix . 'custom_table',
    ['column' => 'value'],
    ['%s']
);

// Update
$wpdb->update(
    $wpdb->prefix . 'custom_table',
    ['column' => 'new_value'],
    ['id' => 1],
    ['%s'],
    ['%d']
);
```

## Security

### Sanitization (Input)
```php
// Text
$clean = sanitize_text_field($_POST['field']);

// Email
$email = sanitize_email($_POST['email']);

// URL
$url = esc_url_raw($_POST['url']);

// HTML (allow safe tags)
$html = wp_kses_post($_POST['content']);
```

### Escaping (Output)
```php
// In HTML context
echo esc_html($text);

// In attributes
echo '<input value="' . esc_attr($value) . '">';

// URLs
echo '<a href="' . esc_url($url) . '">Link</a>';

// JavaScript strings
echo '<script>var data = "' . esc_js($data) . '";</script>';
```

### Nonces
```php
// Create nonce field
wp_nonce_field('my_action', 'my_nonce');

// Verify nonce
if (!wp_verify_nonce($_POST['my_nonce'] ?? '', 'my_action')) {
    wp_die('Security check failed');
}
```

### Capability Checks
```php
if (!current_user_can('edit_posts')) {
    wp_die('Unauthorized');
}
```

## REST API

### Register Endpoint
```php
add_action('rest_api_init', function () {
    register_rest_route('myplugin/v1', '/items', [
        'methods' => 'GET',
        'callback' => 'get_items_callback',
        'permission_callback' => function () {
            return current_user_can('read');
        },
    ]);
});

function get_items_callback($request) {
    $data = get_posts(['post_type' => 'item']);
    return rest_ensure_response($data);
}
```

## Meta Data

### Post Meta
```php
// Get
$value = get_post_meta($post_id, 'my_key', true);

// Update (creates if doesn't exist)
update_post_meta($post_id, 'my_key', 'my_value');

// Delete
delete_post_meta($post_id, 'my_key');
```

### User Meta
```php
$value = get_user_meta($user_id, 'my_key', true);
update_user_meta($user_id, 'my_key', 'my_value');
```

### Options
```php
$value = get_option('my_option', 'default');
update_option('my_option', 'value');
delete_option('my_option');
```

## Transients (Caching)

```php
// Get with cache check
$data = get_transient('my_cache_key');

if ($data === false) {
    // Cache miss - fetch data
    $data = expensive_operation();

    // Cache for 1 hour
    set_transient('my_cache_key', $data, HOUR_IN_SECONDS);
}

// Delete cache
delete_transient('my_cache_key');
```

## AJAX

### Backend
```php
// For logged-in users
add_action('wp_ajax_my_action', 'my_ajax_handler');

// For non-logged-in users
add_action('wp_ajax_nopriv_my_action', 'my_ajax_handler');

function my_ajax_handler() {
    check_ajax_referer('my_nonce', 'security');

    $result = ['success' => true, 'data' => 'Response'];

    wp_send_json($result);
}
```

### Frontend
```javascript
fetch(wpApiSettings.root + 'wp/v2/posts')
  .then(response => response.json())
  .then(posts => console.log(posts));
```
