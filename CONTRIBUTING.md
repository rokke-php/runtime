# Contributing to Rokke Runtime

Contributions welcome! This guide covers setup, development workflow, testing, and submission process.

## Development Setup

### Prerequisites
- PHP 8.4+
- Swoole extension
- Composer
- Git

### Local Installation

```bash
git clone https://github.com/rokke-php/runtime.git
cd runtime
composer install
```

### Verify Setup

Run full check to confirm environment:
```bash
composer check
```

This runs linting, static analysis, and tests.

## Making Changes

### Before Starting

1. Check [GitHub Issues](https://github.com/rokke-php/runtime/issues) for related work
2. For new features, open issue first to discuss approach
3. Branch from latest `main`

### Development Workflow

1. **Create feature branch**
   ```bash
   git checkout -b feature/description
   ```
   Use clear names: `feature/coroutine-pooling`, `fix/context-leak`, `docs/api`

2. **Make focused changes**
   - One logical change per commit
   - Keep commits atomic and reversible
   - Update related documentation

3. **Write tests**
   - Unit tests for new logic
   - Integration tests for component interaction
   - Test both happy path and edge cases
   - Aim for high coverage on changed code

4. **Format and verify**
   ```bash
   composer lint:fix      # Auto-fix code style
   composer analyse       # Static analysis
   composer test          # Run tests
   ```

5. **Commit with clear message**
   ```
   [component] Brief description
   
   More details if needed. Reference issues: #123
   ```

## Code Standards

### PHP Style
- PSR-12 standard (enforced via PHP CS Fixer)
- Run `composer lint:fix` before committing
- 4-space indentation
- Line length max 120 characters recommended

### Type Hints
- Required on all parameters and return types
- Use strict types: `declare(strict_types=1);`
- No loose comparisons in comparisons
- Use typed properties where possible

### Testing
- PHPUnit for all tests
- Test classes in `tests/` mirror `src/` structure
- Namespace: `Rokke\Runtime\Tests\`
- Test methods: `testDoesXWhenConditionY()`
- Use assertions wisely, focus on behavior

### Static Analysis
- PHPStan Level 8
- Must pass before merge
- No `@phpstan-ignore` without justification

### Documentation
- Add docblocks to public methods
- Update README if affecting users
- Include example usage for complex features

## Testing Requirements

### Test Coverage

- **New features**: 80%+ code coverage minimum
- **Bug fixes**: Test that reproduces bug, then fix
- **Refactoring**: All existing tests must pass

### Running Tests

```bash
# All tests
composer test

# Specific test file
vendor/bin/phpunit tests/Runtime/ContextTest.php

# Specific test method
vendor/bin/phpunit tests/Runtime/ContextTest.php::testContextStoresData

# With coverage report
composer test:coverage
```

### Test Organization

```
tests/
├── Unit/                    # Isolated component tests
│   ├── ContextTest.php
│   ├── ServiceContainerTest.php
│   └── ...
├── Integration/             # Multi-component tests
│   ├── RuntimeBootstrapTest.php
│   └── ...
└── Fixtures/                # Shared test data
    └── MockModule.php
```

### Mock Strategy

- Mock external dependencies (DB, HTTP, etc.)
- Use real objects for internal Rokke components
- Test integration between Rokke components in integration tests

## Submitting Changes

### Before Opening PR

- [ ] Code follows PSR-12 standard
- [ ] All tests pass: `composer check`
- [ ] Static analysis passes: `composer analyse`
- [ ] Tests added for new functionality
- [ ] Existing tests still pass
- [ ] Commits have clear messages
- [ ] Branch rebased on latest `main`

### Pull Request Process

1. **Push to fork**
   ```bash
   git push origin feature/your-feature
   ```

2. **Open PR on GitHub**
   - Title: Clear, descriptive (max 70 chars)
   - Description: What changed and why
   - Link related issues: "Fixes #123" or "Related to #456"
   - Reference relevant documentation

3. **PR Template** (use when creating)
   ```markdown
   ## Description
   Brief summary of changes
   
   ## Type of Change
   - [ ] Bug fix
   - [ ] New feature
   - [ ] Breaking change
   - [ ] Documentation
   
   ## Testing
   How to verify changes
   
   ## Checklist
   - [ ] Tests pass
   - [ ] Coverage maintained/improved
   - [ ] Docs updated
   - [ ] No breaking changes
   ```

4. **Address Review Feedback**
   - Respond to all comments
   - Push new commits (don't force-push)
   - Mark conversations as resolved
   - Request re-review when ready

### PR Review Process

Reviews cover:
- **Correctness**: Does it work as intended?
- **Design**: Does it fit architecture?
- **Performance**: Any bottlenecks introduced?
- **Testing**: Adequate test coverage?
- **Documentation**: Clear and complete?
- **Standards**: Follows project conventions?

## Common Tasks

### Running a Specific Test
```bash
vendor/bin/phpunit tests/Runtime/ApplicationTest.php
```

### Checking Code Coverage
```bash
composer test:coverage
# Open coverage/index.html
```

### Finding Type Errors
```bash
composer analyse
```

### Fixing Code Style Automatically
```bash
composer lint:fix
```

### Running Just Linting
```bash
composer lint
```

## Project Structure

```
runtime/
├── src/
│   ├── Contracts/          # Interfaces (PSR abstractions)
│   ├── Runtime/            # Core runtime components
│   ├── Module/             # Module system
│   ├── Event/              # Event bus
│   ├── Pipeline/           # Request pipeline
│   └── Builder/            # Application builder
├── tests/                  # Test suite
├── composer.json
├── phpunit.xml
├── phpstan.neon
└── .php-cs-fixer.dist.php
```

## Questions or Need Help?

- Check existing [GitHub Issues](https://github.com/rokke-php/runtime/issues)
- Open new issue with `[question]` tag
- Review [rokke.dev](https://rokke.dev) documentation
- Join community discussions

## Code of Conduct

Be respectful, inclusive, and constructive. All participants in this project are expected to uphold our values.

## License

Contributions are licensed under MIT (see [LICENSE](LICENSE)).
