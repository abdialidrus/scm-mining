# Setup PHPUnit Code Coverage

## Overview

Code coverage driver diperlukan untuk menganalisa berapa persen kode yang ter-cover oleh tests.

## Options untuk Laravel Herd + PHP 8.4

### Option 1: Xdebug (Recommended untuk Development)

#### Via Homebrew (Easiest):

```bash
# Install PHP 8.4 with Xdebug
brew install shivammathur/php/php@8.4
brew install shivammathur/extensions/xdebug@8.4

# Check if installed
php -m | grep xdebug
```

#### Configure Xdebug for Coverage Only:

Create or edit `~/Library/Application Support/Herd/config/php/84/conf.d/xdebug.ini`:

```ini
[xdebug]
zend_extension=xdebug.so
xdebug.mode=coverage
xdebug.start_with_request=no
```

Restart PHP:

```bash
herd restart
```

#### Verify Installation:

```bash
php -v
# Should show: "with Xdebug v3.x.x"

php -m | grep xdebug
# Should output: xdebug
```

---

### Option 2: PCOV (Faster, Coverage Only)

PCOV is much faster than Xdebug but only does coverage (no debugging).

#### Install via PECL (if available):

```bash
# Check if pecl is available
which pecl

# If available:
pecl install pcov

# Add to php.ini
echo "extension=pcov.so" >> ~/Library/Application\ Support/Herd/config/php/84/php.ini
echo "pcov.enabled=1" >> ~/Library/Application\ Support/Herd/config/php/84/php.ini

# Restart
herd restart
```

#### Verify:

```bash
php -m | grep pcov
```

---

### Option 3: Manual Xdebug Install (Advanced)

If Homebrew options don't work:

```bash
# Download Xdebug for PHP 8.4
cd /tmp
wget https://xdebug.org/files/xdebug-3.3.1.tgz
tar -xzf xdebug-3.3.1.tgz
cd xdebug-3.3.1

# Compile
phpize
./configure
make
sudo make install

# Add to php.ini
echo "zend_extension=xdebug.so" >> ~/Library/Application\ Support/Herd/config/php/84/php.ini
echo "xdebug.mode=coverage" >> ~/Library/Application\ Support/Herd/config/php/84/php.ini

# Restart
herd restart
```

---

## Running Tests with Coverage

### Basic Coverage Report:

```bash
php artisan test --coverage
```

### With Minimum Coverage Threshold:

```bash
# Fail if coverage is below 60%
php artisan test --coverage --min=60
```

### HTML Coverage Report:

```bash
# Generate detailed HTML report
php artisan test --coverage-html=coverage-report

# Open in browser
open coverage-report/index.html
```

### Coverage for Specific Paths:

```bash
# Only check coverage for app/ directory
php artisan test --coverage --coverage-path=app
```

### Specific Test with Coverage:

```bash
# Only Department tests with coverage
php artisan test --filter=DepartmentTest --coverage
```

---

## Coverage Configuration in phpunit.xml

Add coverage settings to `phpunit.xml`:

```xml
<coverage>
    <report>
        <html outputDirectory="coverage-report"/>
        <text outputFile="php://stdout" showOnlySummary="true"/>
    </report>
    <include>
        <directory suffix=".php">app</directory>
    </include>
    <exclude>
        <directory>app/Console</directory>
        <directory>app/Exceptions</directory>
        <file>app/Providers/RouteServiceProvider.php</file>
    </exclude>
</coverage>
```

---

## Alternative: Codecov/Coveralls (CI/CD)

For GitHub Actions integration:

### Install PHPUnit Coverage Package:

```bash
composer require --dev phpunit/php-code-coverage
```

### GitHub Actions Workflow:

```yaml
name: Tests with Coverage

on: [push, pull_request]

jobs:
    test:
        runs-on: ubuntu-latest
        steps:
            - uses: actions/checkout@v3

            - name: Setup PHP with Xdebug
              uses: shivammathur/setup-php@v2
              with:
                  php-version: '8.4'
                  coverage: xdebug

            - name: Install Dependencies
              run: composer install

            - name: Run Tests with Coverage
              run: php artisan test --coverage-clover coverage.xml

            - name: Upload to Codecov
              uses: codecov/codecov-action@v3
              with:
                  file: ./coverage.xml
```

---

## Current Workaround (No Driver)

If you can't install coverage driver immediately, you can still:

### 1. Run Tests Without Coverage:

```bash
php artisan test
```

### 2. Manual Code Review:

Check which files have tests:

```bash
# List all test files
find tests/ -name "*.php" -type f

# List all app files
find app/ -name "*.php" -type f
```

### 3. Use PHPStan for Static Analysis:

```bash
composer require --dev phpstan/phpstan

./vendor/bin/phpstan analyse app
```

---

## Troubleshooting

### Issue: "Code coverage driver not available"

**Solution:** Install Xdebug or PCOV (see options above)

### Issue: "xdebug.so: cannot open shared object file"

**Solution:** Check extension_dir path matches installation:

```bash
php -i | grep extension_dir
ls -la /path/to/extension_dir/xdebug.so
```

### Issue: Coverage is very slow

**Solution:**

1. Use PCOV instead of Xdebug
2. Or configure Xdebug mode to coverage only:

```ini
xdebug.mode=coverage
```

### Issue: Laravel Herd conflicts

**Solution:** Make sure php.ini is in correct Herd directory:

```bash
# Find active php.ini
php --ini

# Edit the correct file
nano ~/Library/Application\ Support/Herd/config/php/84/php.ini
```

---

## Quick Start (Recommended) ✅ TESTED

**For macOS with Homebrew + Laravel Herd:**

```bash
# 1. Install Xdebug
brew tap shivammathur/php
brew install shivammathur/extensions/xdebug@8.4

# 2. Copy Xdebug config to Herd directory
mkdir -p ~/Library/Application\ Support/Herd/config/php/84/conf.d
cp /opt/homebrew/etc/php/8.4/conf.d/20-xdebug.ini ~/Library/Application\ Support/Herd/config/php/84/conf.d/

# 3. Configure for coverage mode in php.ini
cat >> ~/Library/Application\ Support/Herd/config/php/84/php.ini << 'EOF'

[xdebug]
zend_extension="/opt/homebrew/opt/xdebug@8.4/xdebug.so"
xdebug.mode=coverage
xdebug.start_with_request=no
EOF

# 4. Restart Herd
herd restart

# 5. Verify (should show "with Xdebug v3.5.0")
php -v

# 6. Run coverage
php artisan test --coverage

# 7. Generate HTML report
php artisan test --coverage-html=coverage-report

# 8. Open report in browser
open coverage-report/index.html
```

**Expected verification output:**

```
PHP 8.4.16 (cli) (built: Dec 19 2025 08:17:35) (NTS clang 15.0.0)
Copyright (c) The PHP Group
Built by Laravel Herd
Zend Engine v4.4.16, Copyright (c) Zend Technologies
    with Xdebug v3.5.0, Copyright (c) 2002-2025, by Derick Rethans
    with Zend OPcache v8.4.16, Copyright (c), by Zend Technologies
```

---

## Expected Output

With coverage driver installed:

```
   PASS  Tests\Unit\Models\DepartmentTest
  ✓ it can create a department with valid data
  ...

   PASS  Tests\Feature\Api\DepartmentApiTest
  ✓ it can list all departments
  ...

  Tests:    57 passed (195 assertions)
  Duration: 1.47s

  Code Coverage:
  - app/Models:                45.2%
  - app/Http/Controllers/Api:  62.8%
  - app/Services:              12.5%
  - Overall:                   38.4%
```

---

## Next Steps After Setup

1. ✅ Run full coverage: `php artisan test --coverage`
2. ✅ Generate HTML report: `php artisan test --coverage-html=coverage`
3. ✅ Set minimum threshold: `php artisan test --coverage --min=40`
4. ✅ Focus on low-covered areas
5. ✅ Add more tests to increase coverage

---

## Resources

- [Xdebug Documentation](https://xdebug.org/docs/install)
- [PCOV Documentation](https://github.com/krakjoe/pcov)
- [Laravel Testing Documentation](https://laravel.com/docs/testing)
- [PHPUnit Coverage](https://docs.phpunit.de/en/11.0/code-coverage.html)
