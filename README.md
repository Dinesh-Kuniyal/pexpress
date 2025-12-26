# Pexpress Router

A lightweight, Express.js-inspired PHP router with tree-based routing and middleware support.

## Features

- 🚀 **Fast tree-based routing** - O(n) lookup time where n = number of path segments
- 🛣️ **Dynamic route parameters** - Support for `/users/:id` style routes
- 🔧 **Middleware support** - Global and route-specific middlewares
- 📝 **Multiple HTTP methods** - GET, POST, PUT, DELETE, PATCH
- 🎯 **Express.js-like API** - Familiar syntax if you've used Express

## Installation

1. Set up the directory structure:
```
project-root/
├── .htaccess
├── config.php
├── Pexpress.php
└── public/
    ├── .htaccess
    └── index.php
```

2. Create `config.php` in project root:

```php
<?php
$exlucedPath = "/pexpress";
```

The `$exlucedPath` variable should match your subdirectory. Examples:
- App at root (`http://example.com/`): set to `""`
- App in subdirectory (`http://example.com/myapp/`): set to `"/myapp"`
- Local development (`http://localhost/pexpress/`): set to `"/pexpress"`

3. Create `public/index.php`:

```php
<?php
// Go up one level to access project files
require_once __DIR__ . '/../Pexpress.php';

$app = new Pexpress();

// Your routes here
$app->get('/', function($req) {
    echo "Hello World!";
});

$app->dispatch();
```

## Basic Usage

### Setting Up Your Application

Create `public/index.php` as your entry point:

```php
<?php
require_once __DIR__ . '/../Pexpress.php';

$app = new Pexpress();

// Define routes
$app->get('/', function($req) {
    echo "Hello World!";
});

// Dispatch the request
$app->dispatch();
```

All your application code (controllers, middleware, business logic) should live **outside** the `public/` folder for security.

### Route Parameters

```php
// Single parameter
$app->get('/users/:id', function($req) {
    $userId = $req['params']['id'];
    echo "User ID: $userId";
});

// Multiple parameters
$app->get('/posts/:postId/comments/:commentId', function($req) {
    $postId = $req['params']['postId'];
    $commentId = $req['params']['commentId'];
    echo "Post: $postId, Comment: $commentId";
});
```

### HTTP Methods

```php
$app->get('/users', function($req) {
    // Get all users
});

$app->post('/users', function($req) {
    // Create a user
});

$app->put('/users/:id', function($req) {
    // Update a user
});

$app->delete('/users/:id', function($req) {
    // Delete a user
});

$app->patch('/users/:id', function($req) {
    // Partially update a user
});
```

## Middleware

### Global Middleware

Global middleware runs for **all routes**:

```php
// Logging middleware
$app->use(function($req, $next) {
    error_log("Request: {$req['method']} {$req['path']}");
    $next(); // Continue to next middleware/handler
});

// CORS middleware
$app->use(function($req, $next) {
    header("Access-Control-Allow-Origin: *");
    $next();
});
```

### Route-Specific Middleware

Middleware that runs only for specific routes:

```php
// Authentication middleware
$authMiddleware = function($req, $next) {
    if (!isset($_SESSION['user'])) {
        header("HTTP/1.0 401 Unauthorized");
        echo "Unauthorized";
        return; // Don't call $next() to stop execution
    }
    $next(); // User is authenticated, continue
};

// Apply to a single route
$app->get('/admin/dashboard', $authMiddleware, function($req) {
    echo "Welcome to Admin Dashboard";
});
```

### Multiple Middlewares

You can chain multiple middlewares:

```php
$authMiddleware = function($req, $next) {
    // Check authentication
    if (!isset($_SESSION['user'])) {
        header("HTTP/1.0 401 Unauthorized");
        echo "Unauthorized";
        return;
    }
    $next();
};

$adminMiddleware = function($req, $next) {
    // Check admin role
    if ($_SESSION['user']['role'] !== 'admin') {
        header("HTTP/1.0 403 Forbidden");
        echo "Forbidden";
        return;
    }
    $next();
};

$validateMiddleware = function($req, $next) {
    // Validate request data
    if (empty($_POST['title'])) {
        header("HTTP/1.0 400 Bad Request");
        echo "Title is required";
        return;
    }
    $next();
};

// Method 1: Pass middlewares as separate arguments
$app->post('/admin/posts', $authMiddleware, $adminMiddleware, $validateMiddleware, function($req) {
    echo "Creating post...";
});

// Method 2: Pass middlewares as an array
$app->post('/admin/users', [$authMiddleware, $adminMiddleware], function($req) {
    echo "Creating user...";
});
```

### Middleware Execution Flow

1. **Global middlewares** execute first (in the order they were added)
2. **Route-specific middlewares** execute next (in the order they were defined)
3. **Handler** executes if all middlewares called `next()`

```php
$app->use(function($req, $next) {
    echo "1. Global middleware\n";
    $next();
});

$app->get('/test', 
    function($req, $next) {
        echo "2. First route middleware\n";
        $next();
    },
    function($req, $next) {
        echo "3. Second route middleware\n";
        $next();
    },
    function($req) {
        echo "4. Handler";
    }
);

// Output: 1. Global middleware
//         2. First route middleware
//         3. Second route middleware
//         4. Handler
```

## Request Object

The request object passed to handlers and middlewares contains:

```php
$req = [
    'params' => [],        // Route parameters
    'method' => 'GET',     // HTTP method
    'path' => '/users/123' // Request path
    // All $_REQUEST data is also merged in
];
```

### Accessing Request Data

```php
$app->post('/users/:id', function($req) {
    // Route parameters
    $id = $req['params']['id'];
    
    // POST data
    $name = $req['name'] ?? '';
    
    // Query parameters
    $sort = $req['sort'] ?? 'asc';
    
    // Request metadata
    $method = $req['method'];
    $path = $req['path'];
});
```

## Complete Example

**Project Structure:**
```
project-root/
├── .htaccess
├── config.php
├── Pexpress.php
├── middleware/
│   └── auth.php
├── controllers/
│   └── UserController.php
└── public/
    ├── .htaccess
    ├── index.php
    ├── css/
    │   └── style.css
    └── js/
        └── app.js
```

**public/index.php:**
```php
<?php
session_start();
require_once __DIR__ . '/../Pexpress.php';

$app = new Pexpress();

// Global logging middleware
$app->use(function($req, $next) {
    error_log("{$req['method']} {$req['path']}");
    $next();
});

// Load authentication middleware
$authMiddleware = require __DIR__ . '/../middleware/auth.php';

// Public routes
$app->get('/', function($req) {
    echo "Welcome to Pexpress!";
});

$app->get('/about', function($req) {
    echo "About page";
});

// API routes with parameters
$app->get('/api/users/:id', function($req) {
    $userId = $req['params']['id'];
    echo json_encode(['user' => ['id' => $userId, 'name' => 'John Doe']]);
});

// Protected routes
$app->get('/dashboard', $authMiddleware, function($req) {
    echo "Welcome, " . $_SESSION['user']['name'];
});

$app->post('/api/posts', $authMiddleware, function($req) {
    $title = $req['title'] ?? '';
    $content = $req['content'] ?? '';
    
    // Save post logic here
    echo json_encode(['success' => true, 'post_id' => 123]);
});

// Nested parameters
$app->get('/api/posts/:postId/comments/:commentId', function($req) {
    $postId = $req['params']['postId'];
    $commentId = $req['params']['commentId'];
    
    echo json_encode([
        'post_id' => $postId,
        'comment_id' => $commentId
    ]);
});

// 404 handler is built-in
$app->dispatch();
```

**middleware/auth.php:**
```php
<?php
return function($req, $next) {
    if (!isset($_SESSION['user'])) {
        header("HTTP/1.0 401 Unauthorized");
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    $next();
};
```

**config.php:**
```php
<?php
// Adjust based on your setup
$exlucedPath = "/pexpress"; // or "" for root
```

## Project Structure

Pexpress uses a secure two-tier directory structure:

```
project-root/
├── .htaccess                 # Root htaccess - redirects to public/
├── config.php                # Configuration (excluded path, etc.)
├── Pexpress.php              # Router class
├── middleware/               # Your middleware files
├── controllers/              # Your controller files
└── public/                   # Web root (only this is accessible)
    ├── .htaccess             # Public htaccess - routes to index.php
    ├── index.php             # Application entry point
    ├── css/                  # Public assets
    ├── js/
    └── images/
```

### Root `.htaccess`

Located at project root - redirects all requests to `public/` folder:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    # Set public folder as the web root
    RewriteCond %{REQUEST_URI} !^/public/
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>

# Disable directory listing
Options -Indexes

# Deny access to sensitive files
<FilesMatch "\.(env|ini|log|json|lock|yml|yaml|xml)$">
    Require all denied
</FilesMatch>
```

### Public `.htaccess`

Located in `public/` folder - routes all requests to `index.php`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # If file or directory exists, serve it directly
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]
    
    # Otherwise route everything to index.php
    RewriteRule ^ index.php [L]
</IfModule>
```

This setup ensures:
- ✅ Only `public/` folder is accessible via web
- ✅ Sensitive files (.env, config.php, etc.) are protected
- ✅ Static assets (CSS, JS, images) are served directly
- ✅ All other requests go through your router

## Tips & Best Practices

### 1. Keep Sensitive Files Outside public/

**✅ Good:**
```
project-root/
├── config.php              # Protected
├── .env                    # Protected
├── middleware/             # Protected
├── models/                 # Protected
└── public/
    ├── index.php          # Entry point only
    └── assets/            # Public assets
```

**❌ Bad:**
```
public/
├── config.php             # EXPOSED!
├── .env                   # EXPOSED!
└── index.php
```

### 2. Organize Middleware in Separate Files

Keep your middleware outside the `public/` folder:

```php
// middleware/auth.php (in project root)
<?php
return function($req, $next) {
    if (!isset($_SESSION['user'])) {
        header("HTTP/1.0 401 Unauthorized");
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    $next();
};

// public/index.php
$authMiddleware = require __DIR__ . '/../middleware/auth.php';
$app->get('/dashboard', $authMiddleware, function($req) {
    // handler
});
```

### 3. Use JSON Responses

```php
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
}

$app->get('/api/users', function($req) {
    jsonResponse(['users' => [/* data */]]);
});
```

### 4. Middleware Must Call next()

Always remember to call `$next()` in middleware if you want execution to continue:

```php
// ❌ Wrong - Handler will never execute
$app->get('/test', function($req, $next) {
    echo "Middleware";
    // Forgot to call $next()
}, function($req) {
    echo "Handler"; // This never runs
});

// ✅ Correct
$app->get('/test', function($req, $next) {
    echo "Middleware";
    $next(); // Call next to continue
}, function($req) {
    echo "Handler"; // This runs
});
```

### 5. Order Matters

Routes are matched in the order path segments are processed, but specific routes should still be defined before parameterized ones for clarity:

```php
// Good practice - specific before generic
$app->get('/users/admin', function($req) {
    echo "Admin page";
});

$app->get('/users/:id', function($req) {
    echo "User: " . $req['params']['id'];
});
```

## License

MIT License - feel free to use in your projects!