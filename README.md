# Statamic Support

A Statamic addon that provides a support contact form with pluggable helpdesk provider integration and spam validation.

## Features

- Pre-built support contact form
- Pluggable provider system (Kayako included, easily add others)
- Comprehensive spam validation with configurable patterns
- Honeypot field for bot prevention
- Works with Statamic's reCAPTCHA integration
- Dark mode support
- Fully customizable via config and publishable views

## Requirements

- PHP 8.1+
- Statamic 4.0+ or 5.0+
- Laravel 10.0+ or 11.0+

## Installation

### Via Composer

```bash
composer require acoustica/statamic-support
```

### Local Development (Path Repository)

Add the package to your `composer.json` repositories:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/acoustica/statamic-support"
        }
    ]
}
```

Then require the package:

```bash
composer require acoustica/statamic-support:@dev
```

## Setup

### 1. Run the Install Command

```bash
php artisan support:install
```

This will publish:
- Configuration file
- Form blueprint and configuration
- View template
- Fieldset for page builder
- Sample support contact page

Options:
- `--force` - Overwrite existing files
- `--without-page` - Skip creating the sample page

### 2. Configure Environment Variables

Add these to your `.env` file:

```env
# Set the provider (null, kayako, or your custom provider)
SUPPORT_PROVIDER=kayako

# Kayako configuration (only needed if using Kayako)
KAYAKO_URL=https://your-instance.kayako.com
KAYAKO_EMAIL=your-agent@email.com
KAYAKO_PASSWORD=your-password
```

### 3. Add to Page Builder (Optional)

If you're using a page builder fieldset, add the support form section to your `resources/fieldsets/page_builder.yaml`:

```yaml
support_form_section:
  display: 'Support Contact Form'
  fields: []
```

## Configuration

Publish the config file if you haven't already:

```bash
php artisan vendor:publish --tag=statamic-support-config
```

### Available Providers

- **null** - Logs submissions locally only (good for development)
- **kayako** - Sends submissions to Kayako helpdesk

### Adding a Custom Provider

1. Create a class that implements `Acoustica\StatamicSupport\Contracts\SupportProvider`
2. Add it to the `providers` array in `config/support.php`:

```php
'providers' => [
    'my_provider' => [
        'driver' => \App\Support\MyProvider::class,
        'api_key' => env('MY_PROVIDER_API_KEY'),
        // ... other config
    ],
],
```

3. Set `SUPPORT_PROVIDER=my_provider` in your `.env`

### Provider Interface

```php
interface SupportProvider
{
    public function isConfigured(): bool;
    public function createCase(array $data): array;
    public function testConnection(): bool;
    public function getName(): string;
}
```

## Customization

### Custom Form Fields

If you want to use different field names in your form, update the `field_mapping` in your config:

```php
'field_mapping' => [
    'name' => 'full_name',
    'email' => 'email_address',
    'subject' => 'topic',
    'message' => 'details',
    'priority' => 'urgency',
],
```

### Custom Views

Publish the views to customize the form appearance:

```bash
php artisan vendor:publish --tag=statamic-support-views
```

### Additional Spam Patterns

Add custom spam patterns in your config:

```php
'spam' => [
    'patterns' => [
        '/your-custom-pattern/i',
    ],
    'forbidden_words' => [
        'custom-word',
    ],
],
```

## Usage

### Creating a Support Page

The install command creates a page at `/support-contact`. You can also manually create a page with the `support_form_section` section type.

### Programmatic Usage

```php
use Acoustica\StatamicSupport\Contracts\SupportProvider;
use Acoustica\StatamicSupport\Services\SpamValidationService;

// Check for spam
$validator = app(SpamValidationService::class);
$result = $validator->validate([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'subject' => 'Help needed',
    'message' => 'I need help with...',
]);

if ($result['is_spam']) {
    // Handle spam
}

// Create a support case
$provider = app(SupportProvider::class);

if ($provider->isConfigured()) {
    $response = $provider->createCase([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'subject' => 'Help needed',
        'message' => 'I need help with...',
        'priority' => 'normal',
    ]);
}
```

### Testing Provider Connection

```php
$provider = app(SupportProvider::class);

if ($provider->testConnection()) {
    echo 'Connected to ' . $provider->getName();
}
```

## Spam Protection

The package includes multiple layers of spam protection:

1. **Honeypot Field** - Hidden field that bots typically fill out
2. **reCAPTCHA** - Google's reCAPTCHA (requires separate addon)
3. **Pattern Matching** - Regex patterns for common spam phrases
4. **Forbidden Words** - Instant rejection for specific words
5. **Message Length** - Configurable min/max message length
6. **Gibberish Detection** - Detects random character names
7. **Suspicious Name Detection** - Catches URLs in names, all-number names, etc.

## License

MIT License. See [LICENSE](LICENSE) for details.
