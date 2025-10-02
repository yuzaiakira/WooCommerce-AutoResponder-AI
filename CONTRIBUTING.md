# Contributing to WooCommerce AutoResponder AI

Thank you for your interest in contributing to WooCommerce AutoResponder AI! This document provides guidelines and information for contributors.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [Contributing Guidelines](#contributing-guidelines)
- [Pull Request Process](#pull-request-process)
- [Issue Reporting](#issue-reporting)
- [Development Workflow](#development-workflow)
- [Code Standards](#code-standards)
- [Testing](#testing)
- [Documentation](#documentation)
- [Release Process](#release-process)

## Code of Conduct

This project adheres to the WordPress community guidelines and expects all contributors to:

- Be respectful and inclusive
- Focus on constructive feedback
- Help maintain a welcoming environment
- Follow WordPress coding standards
- Respect intellectual property rights

## Getting Started

### Prerequisites

Before contributing, ensure you have:

- **WordPress**: 5.8 or higher
- **PHP**: 8.0 or higher
- **WooCommerce**: 5.0 or higher
- **Composer**: For dependency management
- **Git**: For version control
- **Node.js**: For asset compilation (optional)

### Development Setup

1. **Fork the repository** on GitHub
2. **Clone your fork** locally:
   ```bash
   git clone https://github.com/your-username/woocommerce-autoresponder-ai.git
   cd woocommerce-autoresponder-ai
   ```

3. **Install dependencies**:
   ```bash
   composer install
   ```

4. **Set up WordPress development environment**:
   - Use a local development server (XAMPP, WAMP, MAMP, or Docker)
   - Create a test WordPress installation
   - Install and activate WooCommerce
   - Install the plugin in development mode

5. **Configure your development environment**:
   ```bash
   # Copy environment file
   cp .env.example .env
   
   # Edit .env with your local settings
   nano .env
   ```

## Contributing Guidelines

### Types of Contributions

We welcome various types of contributions:

- **Bug fixes**: Fix issues and improve stability
- **Feature enhancements**: Add new functionality
- **Documentation**: Improve README, code comments, and guides
- **Translations**: Add or improve language files
- **Testing**: Write or improve tests
- **Performance**: Optimize code and database queries
- **Security**: Improve security measures

### Before You Start

1. **Check existing issues** to avoid duplicates
2. **Create an issue** for significant changes to discuss the approach
3. **Fork the repository** and create a feature branch
4. **Follow the coding standards** outlined below

## Development Workflow

### Branch Naming

Use descriptive branch names:

- `feature/description` - New features
- `fix/description` - Bug fixes
- `docs/description` - Documentation updates
- `refactor/description` - Code refactoring
- `test/description` - Test improvements

### Commit Messages

Follow conventional commit format:

```
type(scope): description

[optional body]

[optional footer]
```

Examples:
- `feat(ai): add Claude AI provider support`
- `fix(database): resolve memory leak in review processing`
- `docs(readme): update installation instructions`
- `refactor(admin): improve settings page UI`

### Development Process

1. **Create a feature branch**:
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make your changes** following the code standards

3. **Test your changes**:
   ```bash
   composer test
   composer lint
   ```

4. **Commit your changes**:
   ```bash
   git add .
   git commit -m "feat(scope): your commit message"
   ```

5. **Push to your fork**:
   ```bash
   git push origin feature/your-feature-name
   ```

6. **Create a Pull Request** on GitHub

## Code Standards

### PHP Standards

- Follow **WordPress Coding Standards**
- Use **PSR-4 autoloading**
- Enable strict typing: `declare(strict_types=1);`
- Use **object-oriented programming** principles
- Follow **WordPress naming conventions**

### Code Style

```php
<?php
declare(strict_types=1);

namespace WC_AutoResponder_AI\Providers;

/**
 * Example provider class
 */
class ExampleProvider extends BaseProvider
{
    /**
     * Process review and generate response
     *
     * @param array $review_data Review data array
     * @return string Generated response
     * @throws \Exception If processing fails
     */
    public function generateResponse(array $review_data): string
    {
        // Implementation here
    }
}
```

### File Organization

- **Classes**: Place in appropriate namespace directories
- **Functions**: Use descriptive names and proper documentation
- **Hooks**: Use WordPress hooks (actions and filters)
- **Security**: Always sanitize input and validate data

### Database Operations

- Use `$wpdb` for database queries
- Use `prepare()` for dynamic queries
- Use `dbDelta()` for schema changes
- Follow WordPress database conventions

## Testing

### Running Tests

```bash
# Run all tests
composer test

# Run specific test suite
vendor/bin/phpunit tests/unit/
vendor/bin/phpunit tests/integration/

# Run with coverage
vendor/bin/phpunit --coverage-html coverage/
```

### Writing Tests

- Write **unit tests** for individual functions
- Write **integration tests** for WordPress functionality
- Test **error conditions** and edge cases
- Aim for **high code coverage**

### Test Structure

```php
<?php

namespace WC_AutoResponder_AI\Tests\Unit;

use WC_AutoResponder_AI\Providers\OpenAIProvider;
use PHPUnit\Framework\TestCase;

class OpenAIProviderTest extends TestCase
{
    public function testGenerateResponse(): void
    {
        // Test implementation
    }
}
```

## Documentation

### Code Documentation

- Use **PHPDoc** for all functions and classes
- Include **parameter types** and **return types**
- Document **exceptions** that may be thrown
- Provide **usage examples** for complex functions

### README Updates

- Update **installation instructions** if needed
- Add **new features** to the feature list
- Update **screenshots** for UI changes
- Keep **changelog** up to date

### Translation Files

- Update `.pot` file for new strings
- Add translations for new languages
- Test translations in WordPress admin

## Pull Request Process

### Before Submitting

1. **Ensure tests pass**:
   ```bash
   composer test
   composer lint
   ```

2. **Update documentation** if needed

3. **Test in WordPress environment**

4. **Squash commits** if necessary

### PR Requirements

- **Clear description** of changes
- **Reference related issues**
- **Include screenshots** for UI changes
- **Update changelog** if applicable
- **Ensure CI passes**

### Review Process

1. **Automated checks** must pass
2. **Code review** by maintainers
3. **Testing** in different environments
4. **Approval** from at least one maintainer

## Issue Reporting

### Bug Reports

When reporting bugs, include:

- **WordPress version**
- **PHP version**
- **WooCommerce version**
- **Plugin version**
- **Steps to reproduce**
- **Expected vs actual behavior**
- **Error logs** (if any)
- **Screenshots** (if applicable)

### Feature Requests

For feature requests, include:

- **Use case description**
- **Proposed solution**
- **Alternative solutions considered**
- **Additional context**

### Issue Templates

Use the provided issue templates:
- Bug report template
- Feature request template
- Documentation template

## Release Process

### Version Numbering

Follow **Semantic Versioning** (SemVer):
- **MAJOR**: Breaking changes
- **MINOR**: New features (backward compatible)
- **PATCH**: Bug fixes (backward compatible)

### Release Checklist

1. **Update version numbers**
2. **Update changelog**
3. **Run full test suite**
4. **Update documentation**
5. **Create release notes**
6. **Tag release**
7. **Deploy to WordPress.org**

## Development Tools

### Recommended Tools

- **IDE**: VS Code, PhpStorm, or similar
- **PHP Debugger**: Xdebug
- **Git Client**: GitKraken, SourceTree, or command line
- **API Testing**: Postman or Insomnia

### VS Code Extensions

- PHP Intelephense
- WordPress Snippets
- GitLens
- PHPUnit Test Explorer

## Getting Help

### Resources

- **WordPress Codex**: https://codex.wordpress.org/
- **WooCommerce Documentation**: https://woocommerce.com/documentation/
- **WordPress Coding Standards**: https://developer.wordpress.org/coding-standards/
- **Plugin Developer Handbook**: https://developer.wordpress.org/plugins/

### Community

- **WordPress.org Support Forums**
- **GitHub Discussions**
- **Slack/Discord** (if available)

## License

By contributing to this project, you agree that your contributions will be licensed under the **GPL-2.0-or-later** license.

## Recognition

Contributors will be recognized in:
- **README.md** contributors section
- **Release notes**
- **Plugin credits**

---

Thank you for contributing to WooCommerce AutoResponder AI! Your contributions help make this plugin better for the entire WordPress community.

**Happy coding! 🚀**
