# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 0.9.x   | :white_check_mark: |
| 0.2.x   | :white_check_mark: |
| < 0.2   | :x:                |

## Reporting a Vulnerability

If you discover a security vulnerability, please report it via email:

**Email:** security@aivec.co.jp

Please do **NOT** create a public GitHub issue for security vulnerabilities.

### What to include

- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

### Response timeline

- We will acknowledge receipt within 48 hours
- We will provide an initial assessment within 5 business days
- We will work with you to understand and resolve the issue
- Once fixed, we will credit you in the changelog (unless you prefer to remain anonymous)

## Security Best Practices

This plugin follows WordPress security best practices:

- All settings require administrator privileges (`manage_options` capability)
- User inputs are sanitized using WordPress sanitization functions
- All outputs are escaped using WordPress escaping functions
- Nonces are used for all AJAX requests and form submissions
- API keys are stored securely and never exposed in HTML/JS output
- Direct file access is prevented with `ABSPATH` checks
