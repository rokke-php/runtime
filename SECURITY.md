# Security Policy

## Supported Versions

Supported versions are:
- Latest stable release (receives security updates)
- One previous major version (receives critical security updates only)

## Reporting Security Vulnerabilities

**Do not open public issues for security vulnerabilities.**

Report security issues by emailing: **support@rokke.dev**

Include:
- Type of vulnerability
- Location/component affected
- Steps to reproduce
- Impact assessment
- Suggested fix (optional)

## Vulnerability Response

Timeline:
1. **24 hours**: Acknowledge receipt
2. **48 hours**: Initial assessment
3. **7 days**: Plan and develop fix
4. **Release ASAP**: Security patch (may be expedited)

## Security Considerations

### Coroutine Safety
- All coroutine operations must be thread-safe
- Shared state requires proper locking
- Resource leaks in coroutine cleanup are security issues

### Container & Dependency Injection
- Validate service definitions
- Be cautious with dynamic service registration
- Avoid code injection via service configuration

### Event Bus
- Events may contain sensitive data
- Document what data events carry
- Be careful exposing internal state through events

### Resource Management
- Ensure resource cleanup in error paths
- Prevent resource exhaustion attacks
- Monitor resource limits and enforce them

### Signal Handling
- Signal handlers must be async-safe
- Avoid complex operations in signal context
- Document which signals are safe to trap

## Best Practices for Contributors

- Keep security in mind during development
- Run static analysis and tests before submitting
- Use type hints to prevent type confusion
- Validate inputs at system boundaries
- Document security implications in PRs
- Flag potential security issues in reviews

## Security-Related Issues

If you discover security issue in development:
1. Don't commit or push compromised code
2. Alert maintainers immediately
3. Follow responsible disclosure
4. Don't publicize until fix is released

## Dependencies

Runtime depends on:
- **Swoole**: Async PHP extension. Monitor Swoole security releases.
- **PSR Container**: Standard library, low security surface.

Keep dependencies updated via `composer update`.

## Questions

Security policy questions? Email support@rokke.dev.
