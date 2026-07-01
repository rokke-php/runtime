# Rokke Runtime - Package Information

Professional PHP runtime library for building high-performance, coroutine-driven applications.

## Package Details

**Package Name**: `rokke/runtime`  
**Latest Version**: TBD (currently in development)  
**License**: MIT  
**PHP Version**: 8.4+  
**Main Repository**: https://github.com/rokke-php/runtime  
**Package Registry**: https://packagist.org/packages/rokke/runtime  

## Installation

```bash
composer require rokke/runtime
```

## Quick Links

- **Documentation**: [README.md](README.md)
- **Contributing**: [CONTRIBUTING.md](CONTRIBUTING.md)
- **Architecture**: [ARCHITECTURE.md](ARCHITECTURE.md)
- **Testing Guide**: [TESTING.md](TESTING.md)
- **Security Policy**: [SECURITY.md](SECURITY.md)
- **Code of Conduct**: [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md)
- **Changelog**: [CHANGELOG.md](CHANGELOG.md)

## Key Features

- **Coroutine-Driven**: Built on Swoole for non-blocking, concurrent execution
- **Module System**: Pluggable architecture for extending functionality
- **Event Bus**: Publish-subscribe pattern for event handling
- **Pipeline Architecture**: Handler chains for request processing
- **Service Container**: PSR-11 compatible dependency injection
- **Resource Management**: Lifecycle-aware resource pooling
- **Observability**: Built-in diagnostics and monitoring
- **Type-Safe**: Strict PHP 8.4+ typing throughout

## Development

### Prerequisites
- PHP 8.4+
- Swoole Extension
- Composer

### Setup
```bash
composer install
```

### Quality Assurance
```bash
composer check      # Run all checks (lint, analyse, test)
composer lint       # Check code style
composer lint:fix   # Auto-fix code style
composer analyse    # Run static analysis
composer test       # Run test suite
composer test:coverage  # Generate coverage report
```

### CI/CD
Automated testing via GitHub Actions on:
- All push events to `main` and `develop`
- All pull requests

Tests must pass before merging.

## Publishing to Packagist

### Pre-Publication Checklist

- [ ] Version number assigned in composer.json
- [ ] CHANGELOG.md updated with release notes
- [ ] All tests passing locally: `composer check`
- [ ] Code coverage acceptable (80%+ for new code)
- [ ] Documentation updated
- [ ] Security review completed
- [ ] No outstanding security issues
- [ ] Git tag created: `git tag v1.0.0`
- [ ] GitHub release created with release notes

### Publishing Steps

1. **Ensure Packagist account setup**
   - Register at https://packagist.org
   - Connect GitHub repository

2. **Tag release in Git**
   ```bash
   git tag v1.0.0
   git push origin v1.0.0
   ```

3. **Create GitHub Release**
   - Tag: `v1.0.0`
   - Title: `Release v1.0.0`
   - Description: Copy from CHANGELOG.md

4. **Packagist auto-detects**
   - GitHub webhook automatically syncs
   - Check packagist.org for package visibility

5. **Verify package**
   - Check https://packagist.org/packages/rokke/runtime
   - Test installation: `composer require rokke/runtime`

## Version Strategy

Follows Semantic Versioning (MAJOR.MINOR.PATCH):

- **MAJOR**: Breaking API changes
- **MINOR**: New features (backward compatible)
- **PATCH**: Bug fixes

## Support

- **Issues**: https://github.com/rokke-php/runtime/issues
- **Discussions**: https://github.com/rokke-php/runtime/discussions
- **Email**: support@rokke.dev
- **Security**: See [SECURITY.md](SECURITY.md) for vulnerability reporting

## Requirements for Distribution

Distribution package (.zip from Packagist) excludes:
- `/tests` - Test suite
- `/docs` - Development documentation
- `/.github` - GitHub-specific files
- `phpunit.xml.dist` - Test configuration
- `phpstan.neon.dist` - Analysis configuration
- `.php-cs-fixer.dist.php` - Linting configuration
- Development-only documentation files

See [.gitattributes](.gitattributes) for export-ignore rules.

## Maintenance

- Monitor GitHub Issues for bug reports
- Review security alerts
- Keep dependencies updated
- Maintain backward compatibility when possible
- Document breaking changes clearly

## License

MIT License - See [LICENSE](LICENSE) file for details.

## Credits

**Author**: Fernando Duarte (fduarte@rokke.dev)

**Contributors**: See GitHub Contributors page

## Related Projects

- **Rokke Framework**: Full-stack framework built on Runtime
- **Swoole**: Async PHP framework powering the runtime
