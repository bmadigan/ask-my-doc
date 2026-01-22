# Laravel Fundamentals

Laravel is a PHP web application framework with expressive, elegant syntax. This guide covers the essential concepts every Laravel developer should know.

## Routing

Routes define the entry points to your application. In Laravel, routes are defined in the `routes/web.php` file for web routes and `routes/api.php` for API routes.

To define a basic route, use the Route facade:

```php
Route::get('/welcome', function () {
    return view('welcome');
});
```

You can also route to controller actions:

```php
Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);
Route::get('/users/{id}', [UserController::class, 'show']);
```

Route parameters allow you to capture segments of the URI. Parameters are always encased within curly braces and should consist of alphabetic characters.

## Controllers

Controllers group related request handling logic into a single class. Instead of defining all request handling logic in route files, you may organize this behavior using controller classes.

Create a controller using Artisan:

```bash
php artisan make:controller UserController
```

A basic controller method receives the request and returns a response:

```php
public function index()
{
    $users = User::all();
    return view('users.index', compact('users'));
}
```

## Eloquent ORM

Eloquent is Laravel's ActiveRecord implementation for working with databases. Each database table has a corresponding Model that interacts with that table.

### Defining Models

```php
class User extends Model
{
    protected $fillable = ['name', 'email', 'password'];
}
```

### Relationships

Eloquent relationships are defined as methods on your model classes. Laravel supports several relationship types:

**One to Many**: A user has many posts.
```php
public function posts()
{
    return $this->hasMany(Post::class);
}
```

**Belongs To**: A post belongs to a user.
```php
public function user()
{
    return $this->belongsTo(User::class);
}
```

**Many to Many**: A user belongs to many roles.
```php
public function roles()
{
    return $this->belongsToMany(Role::class);
}
```

### Query Builder

Eloquent provides a fluent query builder for database operations:

```php
$users = User::where('active', true)
    ->orderBy('name')
    ->take(10)
    ->get();
```

## Request Handling

Laravel provides several ways to access incoming request data. The Request class provides methods to examine the HTTP request:

```php
public function store(Request $request)
{
    $name = $request->input('name');
    $email = $request->input('email');

    // Validate the request
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users',
    ]);
}
```

Form Request classes provide a clean way to handle validation:

```php
class StoreUserRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
        ];
    }
}
```
