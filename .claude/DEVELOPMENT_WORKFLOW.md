# Development Workflow
## Markup by Attribute for WooCommerce

This document outlines the development and testing workflow for maintaining PHP 7.4+ compatibility while developing on PHP 8+.

---

## Development Environment Setup

### Development Server (PHP 8+)
- **Location:** 192.168.1.90/wpdev/
- **PHP Version:** 8.0+
- **Purpose:** Primary development, modern PHP features
- **Plugin Location:** Symlinked to Samba share

### Test Server (PHP 7.4)
- **Location:** 192.168.1.191/backrev/
- **PHP Version:** 7.4.3
- **WordPress:** 5.0.8
- **WooCommerce:** 4.0.0
- **Purpose:** Backward compatibility testing
- **Plugin Location:** Symlinked to Samba share

### Samba Share
- **Network Path:** //192.168.1.90/MarkupbyAttr
- **Local Path:** /home/mark/markup-by-attribute-for-woocommerce/
- **Mount Point on Test Server:** /mnt/markupbyattr
- **Symlink:** Both dev and test servers symlink to this share for instant reflection of changes

---

## PHP 7.4 Compatibility Guidelines

### ✅ SAFE - Features We Can Use

#### 1. Simple Return Type Declarations
```php
// ✅ GOOD - Simple types work in PHP 7.4
public function calculatePrice(): float {
    return 19.99;
}

public function getProductId(): int {
    return 123;
}

public function isValid(): bool {
    return true;
}

public function getItems(): array {
    return [];
}

public function getName(): string {
    return "Product";
}

public function doSomething(): void {
    // No return value
}

public function getInstance(): self {
    return $this;
}
```

#### 2. Parameter Type Hints
```php
// ✅ GOOD - Parameter types work in PHP 7.4
public function setPrice(float $price): void {
    $this->price = $price;
}

public function processData(array $data, bool $validate = true): array {
    // ...
}
```

#### 3. Arrow Functions
```php
// ✅ GOOD - Arrow functions added in PHP 7.4
$prices = array_map(fn($x) => $x * 1.1, $basePrices);
```

#### 4. Null Coalescing Assignment
```php
// ✅ GOOD - Added in PHP 7.4
$value ??= 'default';
```

---

### ❌ AVOID - PHP 8.0+ Only Features

#### 1. Constructor Return Types
```php
// ❌ BAD - Constructors can't have return types in PHP 7.4
public function __construct(): void {  // PHP 8.0+ only!
    $this->init();
}

// ✅ GOOD - No return type on constructor
public function __construct() {
    $this->init();
}
```

#### 2. Magic Method Return Types
```php
// ❌ BAD - Magic methods can't have return types in PHP 7.4
public function __toString(): string {  // PHP 8.0+ only!
    return $this->name;
}

// ✅ GOOD - No return type on magic methods
public function __toString() {
    return $this->name;
}
```

#### 3. Union Types
```php
// ❌ BAD - Union types are PHP 8.0+ only
public function getValue(): int|float {  // PHP 8.0+ only!
    return $this->value;
}

// ✅ GOOD - Use single type or no type
public function getValue() {
    return $this->value;  // Can return int or float
}
```

#### 4. Mixed Type
```php
// ❌ BAD - Mixed type is PHP 8.0+ only
public function process(mixed $data): mixed {  // PHP 8.0+ only!
    return $data;
}

// ✅ GOOD - Omit type hint for mixed
public function process($data) {
    return $data;
}
```

#### 5. Named Arguments
```php
// ❌ BAD - Named arguments are PHP 8.0+ only
calculatePrice(base: 100, markup: 5);

// ✅ GOOD - Use positional arguments
calculatePrice(100, 5);
```

#### 6. Nullsafe Operator
```php
// ❌ BAD - Nullsafe operator is PHP 8.0+ only
$price = $product?->getPrice();

// ✅ GOOD - Use traditional null checks
$price = $product !== null ? $product->getPrice() : null;
```

#### 7. Match Expression
```php
// ❌ BAD - Match is PHP 8.0+ only
$result = match($type) {
    'percent' => $value * 0.01,
    'fixed' => $value,
};

// ✅ GOOD - Use switch or if/else
switch($type) {
    case 'percent':
        $result = $value * 0.01;
        break;
    case 'fixed':
        $result = $value;
        break;
}
```

---

## Development Workflow

### Step 1: Development on PHP 8+ Server
1. Make code changes on dev server (192.168.1.90/wpdev/)
2. Use modern IDE with PHP 8+ type checking
3. Test functionality on dev environment
4. Commit changes to Git

### Step 2: PHP 7.4 Compatibility Check
**Before committing, verify:**
- ✅ No constructor return types
- ✅ No magic method return types
- ✅ No union types
- ✅ No mixed type hints
- ✅ No PHP 8.0+ only features
- ✅ All return types use simple types (void, string, int, float, bool, array, self)

### Step 3: Test on PHP 7.4 Server
1. Changes automatically available on test server (symlinked)
2. Navigate to test site: 192.168.1.191/backrev/
3. Enable WP_DEBUG and error logging:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```
4. Execute all modified functionality
5. Check `/wp-content/debug.log` for errors
6. Verify zero PHP errors or warnings

### Step 4: Run Test Suite
Execute tests from TEST_PLAN_4.4.0.md:
- Security tests
- Performance tests
- Code quality tests
- Core functionality tests
- European decimal tests
- Reapply markup tests
- Edge case tests

### Step 5: Verify European Decimal Handling
**Critical Pattern: Always call `wc_format_decimal()` before `is_numeric()`**
```php
// ✅ CORRECT ORDER
$normalized = wc_format_decimal($input);
if (is_numeric($normalized)) {
    // Process normalized value
}

// ❌ WRONG ORDER
if (is_numeric($input)) {  // Fails with "5,50"
    $normalized = wc_format_decimal($input);
}
```

**Why:** `is_numeric("5,50")` returns false, but `is_numeric(wc_format_decimal("5,50"))` returns true (5.50).

**Verified Locations:**
All 5 `is_numeric()` calls in the codebase correctly occur AFTER `wc_format_decimal()`.

### Step 6: Commit to Git
```bash
cd /home/mark/markup-by-attribute-for-woocommerce
git add .
git commit -m "Descriptive commit message"
git push origin Security-Enhancements
```

---

## Return Type Declaration Checklist

When adding return types to functions:

### ✅ DO Add Return Types To:
- [x] Regular public methods
- [x] Regular protected methods
- [x] Regular private methods
- [x] Static methods (non-magic)

### ❌ DON'T Add Return Types To:
- [ ] `__construct()` - Constructors
- [ ] `__destruct()` - Destructors
- [ ] `__toString()` - Magic method
- [ ] `__get()` - Magic method
- [ ] `__set()` - Magic method
- [ ] `__call()` - Magic method
- [ ] `__callStatic()` - Magic method
- [ ] `__isset()` - Magic method
- [ ] `__unset()` - Magic method
- [ ] `__sleep()` - Magic method
- [ ] `__wakeup()` - Magic method
- [ ] `__clone()` - Magic method

---

## IDE Configuration for PHP 7.4

### PhpStorm / IntelliJ
1. Settings → Languages & Frameworks → PHP
2. Set PHP Language Level: 7.4
3. Enable inspections for PHP 7.4 compatibility

### VS Code with PHP Intelephense
```json
{
    "php.version": "7.4",
    "intelephense.compatibility.correctForPhpVersion": "7.4"
}
```

---

## Testing Checklist Before Release

- [ ] All modified files tested on PHP 7.4.3
- [ ] Zero PHP errors in debug.log
- [ ] Zero PHP warnings in debug.log
- [ ] All 32 tests in TEST_PLAN_4.4.0.md passed
- [ ] European decimal handling verified
- [ ] Return types verified (31 functions)
- [ ] No PHP 8.0+ features used
- [ ] Git commit clean
- [ ] Ready for WordPress.org release

---

## Version Update Checklist

Before release, update version numbers in:
- [ ] `markup-by-attribute-for-woocommerce.php` - Plugin header
- [ ] `readme.txt` - Version and Stable tag
- [ ] `readme.txt` - Changelog entry
- [ ] Git tag: `git tag v4.4.0`

---

## Symlink Setup Reference

**On Test Server (192.168.1.191):**
```bash
# Mount Samba share
sudo mount -t cifs //192.168.1.90/MarkupbyAttr /mnt/markupbyattr -o username=mark,password=PASSWORD,uid=33,gid=33

# Create symlink
sudo ln -s /mnt/markupbyattr /var/www/html/backrev/wp-content/plugins/markup-by-attribute-for-woocommerce

# Auto-mount in /etc/fstab
//192.168.1.90/MarkupbyAttr /mnt/markupbyattr cifs credentials=/root/.smbcredentials,uid=33,gid=33 0 0
```

---

*Created: November 20, 2025*
*By: Akina 🌸*
*For: PHP 7.4+ Compatibility Maintenance*
