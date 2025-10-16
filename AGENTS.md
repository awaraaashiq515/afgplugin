# AGENTS.md - AFG Academy WordPress Site

## Build/Lint/Test Commands
No custom build/lint/test commands specified. For WordPress development:
- Use WP-CLI for testing: `wp test` (if PHPUnit is set up).
- Run a single test: `phpunit --filter test_function_name` (if using PHPUnit).
- Lint PHP: `phpcs --standard=WordPress wp-content/plugins/your-plugin/`.
- No Node.js build system detected; use standard WordPress deployment.

## Code Style Guidelines
Follow WordPress Coding Standards (PHP, JS, CSS):
- **Imports**: Use `require_once` for PHP includes; avoid globals unless necessary (e.g., $wpdb).
- **Formatting**: Tabs for indentation; 80-100 char lines; PHPDoc comments for functions/classes.
- **Types**: Strict typing in PHP 7+; use PHPDoc for documentation.
- **Naming**: CamelCase for classes/methods (e.g., DKM_Database); snake_case for prefixes/files.
- **Error Handling**: Use WP_Error for errors; log via error_log(); avoid die/exit in production.
- **Security**: Escape outputs with esc_html(); sanitize inputs; use nonces for forms.
- No Cursor or Copilot rules found in the project.