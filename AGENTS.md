# AGENTS.md - AI Coding Agent Guidelines

This document provides guidelines for AI coding agents working in the `seal_ai` repository.

## Project Overview

**TYPO3 Extension**: `seal_ai` (EXT:seal_ai)  
**Purpose**: AI Vector search integration for EXT:seal using Symfony AI platform  
**Namespace**: `Lochmueller\SealAi\`  
**PHP Version**: 8.3+  
**TYPO3 Versions**: 13.4, 14.1

## Build Commands

### Install Dependencies
```bash
composer install
```

### Code Quality Commands
```bash
# Fix code style (PHP-CS-Fixer)
composer code-fix

# Run static analysis (PHPStan level 8)
composer code-check

# Run all tests
composer code-test

# Run tests with coverage report (requires Xdebug)
composer code-test-coverage
```

## Testing

### Test Framework
- PHPUnit via typo3/testing-framework
- Configuration: `Tests/UnitTests.xml`
- Test directory: `Tests/Unit/`

### Running Tests
```bash
# Run all unit tests
composer code-test

# Run a single test file
.Build/bin/phpunit -c Tests/UnitTests.xml Tests/Unit/Adapter/AiIndexerTest.php

# Run a single test method
.Build/bin/phpunit -c Tests/UnitTests.xml --filter testSaveNewDocumentsToIndex

# Run tests matching a pattern
.Build/bin/phpunit -c Tests/UnitTests.xml --filter "testSave"
```

### Writing Tests
- Place unit tests in `Tests/Unit/` mirroring the `Classes/` structure
- Extend `Lochmueller\SealAi\Tests\Unit\AbstractTest` (which extends TYPO3's `UnitTestCase`)
- Test method names should start with `test` and be descriptive

```php
<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Adapter;

use Lochmueller\SealAi\Tests\Unit\AbstractTest;

class ExampleTest extends AbstractTest
{
    public function testExampleBehavior(): void
    {
        // Arrange, Act, Assert
    }
}
```

## Code Style Guidelines

### PHP-CS-Fixer Configuration
The project uses `.php-cs-fixer.dist.php` with these rules:
- `@PER-CS3.0` - PER Coding Style 3.0 (PSR-12 evolution)
- `@PER-CS3.0:risky`
- `@PHP8x3Migration` and `:risky`
- `@PHPUnit10x0Migration:risky`

### Strict Types
**Every PHP file MUST start with:**
```php
<?php

declare(strict_types=1);
```

### Imports
- No unused imports allowed
- Group imports by type (PHP classes, then vendor, then project)
- Use full class names in imports, not `use function` or `use const`

### Formatting
- Use spaces, not tabs (4 spaces per indent)
- Opening braces on same line for classes and methods
- One blank line between methods
- No trailing whitespace

### Type Declarations
- Always use type hints for parameters and return types
- Use union types where appropriate: `StoreInterface&ManagedStoreInterface`
- Use `readonly` for immutable properties and classes
- Prefer constructor property promotion

### Naming Conventions
- **Classes**: PascalCase (`AiAdapter`, `PlatformFactory`)
- **Methods**: camelCase (`getStore`, `createAdapter`)
- **Variables**: camelCase (`$apiKey`, `$hostUrl`)
- **Constants**: UPPER_SNAKE_CASE
- **Interfaces**: Suffix with `Interface` (`AdapterInterface`)
- **Factories**: Suffix with `Factory` (`AiAdapterFactory`)

### Class Structure
```php
<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Example;

use External\Dependency;

readonly class ExampleClass implements ExampleInterface
{
    public function __construct(
        private DependencyA $depA,
        private DependencyB $depB,
    ) {}

    public function publicMethod(): ReturnType
    {
        // Implementation
    }
}
```

### Error Handling
- Use specific exception types (`\RuntimeException`, `\InvalidArgumentException`)
- Include meaningful error messages with context
- Use numeric error codes for TYPO3 exceptions

```php
throw new \RuntimeException('No site found in current request', 1236891231);
```

## Architecture Patterns

### Dependency Injection
- Use constructor injection
- Symfony DI with autowiring enabled (`Configuration/Services.yaml`)
- Use `#[AutoconfigureTag(...)]` attribute for service tagging

### Factory Pattern
- Create factories for complex object instantiation
- Factory methods return interface types when possible

### Adapter Pattern
- Implements `CmsIg\Seal\Adapter\AdapterInterface`
- Components: `SchemaManager`, `Indexer`, `Searcher`

## Directory Structure

```
seal_ai/
├── Classes/                    # PHP source code
│   ├── Adapter/Ai/            # SEAL adapter implementation
│   ├── Factory/               # Factory classes
│   └── AiBridge.php           # Main bridge class
├── Configuration/
│   └── Services.yaml          # Symfony DI configuration
├── Tests/
│   ├── Unit/                  # Unit tests
│   └── UnitTests.xml          # PHPUnit configuration
└── Resources/                 # Assets, translations
```

## Static Analysis

PHPStan is configured at **level 8** (strictest):
```bash
composer code-check
# or directly:
.Build/bin/phpstan analyse Classes --memory-limit 1G --level 8
```

## CI/CD

GitHub Actions runs on push to `main` and pull requests:
- **Matrix**: PHP 8.3, 8.4, 8.5 with TYPO3 13.4 and 14.1
- Runs: `composer code-test`

## Important Notes

1. **Always run `composer code-fix` before committing** to ensure consistent style
2. **Run `composer code-check` to catch type errors** before pushing
3. **The `.Build/` directory contains vendor dependencies** - do not modify
4. **PSR-4 autoloading**: Classes in `Classes/` map to `Lochmueller\SealAi\`
5. **Tests in `Tests/`** map to `Lochmueller\SealAi\Tests\`
