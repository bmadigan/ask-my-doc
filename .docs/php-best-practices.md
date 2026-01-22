# PHP Best Practices

This guide covers essential PHP best practices for writing clean, maintainable, and secure code.

## Type Declarations

PHP 7+ introduced scalar type declarations. Always use type hints for function parameters and return types:

```php
function calculateTotal(float $price, int $quantity): float
{
    return $price * $quantity;
}
```

Use nullable types when a value can be null:

```php
function findUser(int $id): ?User
{
    return User::find($id);
}
```

Union types (PHP 8+) allow multiple types:

```php
function processInput(string|array $input): void
{
    // Handle both string and array inputs
}
```

## Error Handling

Proper exception handling makes your code more robust. Use try-catch blocks for operations that may fail:

```php
try {
    $result = $this->processPayment($amount);
} catch (PaymentException $e) {
    Log::error('Payment failed: ' . $e->getMessage());
    throw new PaymentFailedException('Unable to process payment');
}
```

Create custom exceptions for your domain:

```php
class InsufficientFundsException extends Exception
{
    public function __construct(float $required, float $available)
    {
        parent::__construct("Required: {$required}, Available: {$available}");
    }
}
```

Never catch exceptions silently. Always log or handle them appropriately.

## SOLID Principles

### Single Responsibility Principle
Each class should have one reason to change. A UserService should handle user operations, not email sending.

### Open/Closed Principle
Classes should be open for extension but closed for modification. Use interfaces and abstract classes.

### Liskov Substitution Principle
Subtypes must be substitutable for their base types without altering program correctness.

### Interface Segregation Principle
Many specific interfaces are better than one general-purpose interface.

### Dependency Inversion Principle
Depend on abstractions, not concretions. Use dependency injection.

## Dependency Injection

Inject dependencies through constructors rather than creating them inside classes:

```php
// Good: Dependencies injected
class OrderService
{
    public function __construct(
        private PaymentGateway $payment,
        private InventoryService $inventory
    ) {}

    public function processOrder(Order $order): void
    {
        $this->inventory->reserve($order->items);
        $this->payment->charge($order->total);
    }
}

// Bad: Dependencies created internally
class OrderService
{
    public function processOrder(Order $order): void
    {
        $payment = new StripeGateway(); // Hard dependency
        $payment->charge($order->total);
    }
}
```

## Testing

Write tests for your code. Use PHPUnit or Pest for testing:

```php
test('it calculates order total correctly', function () {
    $order = new Order([
        ['price' => 10.00, 'quantity' => 2],
        ['price' => 5.00, 'quantity' => 3],
    ]);

    expect($order->total())->toBe(35.00);
});
```

Mock external dependencies in tests to isolate the unit under test. Test both happy paths and edge cases.
