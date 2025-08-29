# Nested Structure Demo

This demo shows how the **syriable/localizator** package creates intelligent nested translation file structures.

## Example Translation Keys

Consider these translation keys found in your Laravel application:

```php
// In your PHP files
__('auth.login.title')
__('auth.login.button')
__('auth.register.title')
__('validation.custom.email.required')
__('validation.custom.password.min')
__('dashboard.widgets.sales.title')
__('dashboard.widgets.users.count')
__('messages.welcome')
__('simple_key')
```

## Generated File Structure

With `'nested' => true` (default), the package generates:

### resources/lang/en/auth.php
```php
<?php

return [
    'login' => [
        'title' => 'Login',
        'button' => 'Sign In',
    ],
    'register' => [
        'title' => 'Create Account',
    ],
];
```

### resources/lang/en/validation.php
```php
<?php

return [
    'custom' => [
        'email' => [
            'required' => 'Email is required',
        ],
        'password' => [
            'min' => 'Password must be at least 8 characters',
        ],
    ],
];
```

### resources/lang/en/dashboard.php
```php
<?php

return [
    'widgets' => [
        'sales' => [
            'title' => 'Sales Overview',
        ],
        'users' => [
            'count' => 'User Count',
        ],
    ],
];
```

### resources/lang/en/messages.php
```php
<?php

return [
    'welcome' => 'Welcome to our application',
    'simple_key' => 'Simple Value',
];
```

## Usage in Laravel

These nested structures work seamlessly with Laravel's translation helpers:

```php
// Access nested translations
__('auth.login.title')                    // "Login"
trans('validation.custom.email.required') // "Email is required"
__('dashboard.widgets.sales.title')      // "Sales Overview"
__('messages.welcome')                    // "Welcome to our application"
```

## Benefits

✅ **Organized**: Files are logically grouped by context  
✅ **Maintainable**: Easy to find and manage related translations  
✅ **Scalable**: Supports unlimited nesting levels  
✅ **Laravel Standard**: Follows Laravel's recommended structure  
✅ **Backward Compatible**: Can be disabled with `'nested' => false`  

## Command Usage

```bash
# Scan and generate nested structure
php artisan localizator:scan en es fr

# With AI translation
php artisan localizator:scan es --auto-translate

# Preview without changes
php artisan localizator:scan en --dry-run
```