# CRUSH.md - GraphRedis Development Guide

## Build/Lint/Test Commands

```bash
# Run all tests
composer test

# Run a single test class
./vendor/bin/phpunit tests/GraphRedisTest.php

# Run a specific test method
./vendor/bin/phpunit --filter testAddNode tests/GraphRedisTest.php

# Generate code coverage
composer test-coverage

# Check code style (PSR-12)
composer cs-check

# Auto-fix code style issues
composer cs-fix

# Static analysis
composer phpstan

# Run demo
composer demo
```

## Code Style Guidelines

### PHP Version
- Minimum PHP version: 7.4
- Use type declarations where possible
- Use strict typing (`declare(strict_types=1);`)

### Imports
- Group `use` statements at the top of the file after the namespace
- Sort imports alphabetically
- Remove unused imports

### Naming Conventions
- Classes: PascalCase (e.g., `GraphRedis`)
- Methods: camelCase (e.g., `addNode`, `shortestPath`)
- Variables: camelCase (e.g., `$nodeId`, `$pageSize`)
- Constants: UPPER_SNAKE_CASE

### Formatting
- Follow PSR-12 coding standards
- Use 4 spaces for indentation (no tabs)
- Line length should not exceed 120 characters
- Opening braces for classes and methods on the same line
- Closing braces on their own line

### Types
- Use PHP 7.4 typed properties where applicable
- Use return type declarations for all methods
- Use parameter type declarations where possible
- Use `?Type` for nullable types
- Use `array` type hint for arrays

### Error Handling
- Throw specific exceptions (`InvalidArgumentException`, `RedisException`)
- Include meaningful error messages
- Validate input parameters at the beginning of methods
- Use early returns for validation failures

### Documentation
- Use PHPDoc for all public methods
- Include `@param` and `@return` annotations
- Keep comments concise and accurate
- Update documentation when changing method signatures

### Additional Rules
- Keep methods focused and small
- Use meaningful variable names
- Avoid deep nesting
- Prefer early returns over else clauses
- Use constants for magic numbers