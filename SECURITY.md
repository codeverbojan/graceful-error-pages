# Security Policy

## Supported Versions

| Version | Supported          |
|---------|--------------------|
| 1.0.x   | Yes                |

## Reporting a Vulnerability

If you discover a security vulnerability, please report it responsibly.

**Do not open a public GitHub issue for security vulnerabilities.**

Instead, email **security@codever.io** with:

- A description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

We will acknowledge your report within 48 hours and aim to release a patch within 7 days for critical issues.

## Security Practices

This plugin follows WordPress security best practices:

- All output is escaped with `esc_html()`, `esc_attr()`, `esc_url()`
- All inputs are sanitized with appropriate WordPress functions
- CSRF protection via nonces on all forms and admin actions
- Capability checks on all admin operations
- No external CDN or third-party resource loading on error pages
