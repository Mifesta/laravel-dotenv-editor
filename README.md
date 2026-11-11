# Laravel Dotenv Editor

Edit a Laravel `.env` file from PHP or Artisan: read keys, change them, and keep comments, `export` and quoting intact.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mifesta/dotenv-editor.svg?style=flat-square)](https://packagist.org/packages/mifesta/dotenv-editor)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Total Downloads](https://img.shields.io/packagist/dt/mifesta/dotenv-editor.svg?style=flat-square)](https://packagist.org/packages/mifesta/dotenv-editor)

## Versions

| Package | Laravel | PHP |
| --- | --- | --- |
| `^13.0` | 13 | 8.3–8.5 |
| `^12.0` | 12 | 8.2–8.5 |
| `^11.0` | 11 | 8.2–8.4 |
| `^10.0` | 10 | 8.1–8.3 |
| `1.0.2` | 5 | legacy release, no longer maintained |

`composer require mifesta/dotenv-editor` installs the newest release whose Laravel constraint matches the application.

## Installation

```bash
composer require mifesta/dotenv-editor
```

Laravel discovers the service provider and the `DotenvEditor` facade. To register them manually:

```php
// bootstrap/providers.php (Laravel 11+) or config/app.php
Mifesta\DotenvEditor\DotenvEditorServiceProvider::class,
```

```php
'aliases' => [
    'DotenvEditor' => Mifesta\DotenvEditor\Facades\DotenvEditor::class,
],
```

Publish the config when you want to change the backup directory or turn automatic backups off:

```bash
php artisan vendor:publish --provider="Mifesta\DotenvEditor\DotenvEditorServiceProvider" --tag=config
```

`autoBackup` copies the current file before every `save()`. Backups are stored in `storage/dotenv-editor/backups/`.

## Usage

```php
use Mifesta\DotenvEditor\Facades\DotenvEditor;

DotenvEditor::load(base_path('.env.example'))
    ->setKey('APP_URL', 'https://example.com')
    ->save();

$value = DotenvEditor::getValue('APP_URL');
```

`load($path)` uses the given path as-is. Pass `base_path()` yourself when the path is relative to the application. `load()` with no arguments reads the environment file Laravel is using.

### Reading

- `getContent()` returns the raw file.
- `getLines()` returns every line with its parsed type, key, value and comment.
- `getKeys(['APP_DEBUG', 'APP_URL'])` returns those setters. An empty list returns all of them.
- `keyExists('APP_URL')` checks the file on disk.
- `getValue('APP_URL')` returns the value or throws `KeyNotFoundException`.

### Writing

Changes go into a buffer. `save()` writes that buffer to disk.

```php
DotenvEditor::setKey('ENV_KEY');
DotenvEditor::setKey('ENV_KEY', 'anything-you-want');
DotenvEditor::setKey('ENV_KEY', 'anything-you-want', 'your-comment');
DotenvEditor::setKey('ENV_KEY', 'new-value');              // keeps the comment and the export prefix
DotenvEditor::setKey('ENV_KEY', 'new-value', null, true);  // force "export "
DotenvEditor::setKey('ENV_KEY', 'new-value', null, false); // remove "export "
DotenvEditor::setKey('ENV_KEY', 'new-value', '');          // clear the comment

DotenvEditor::setKeys([
    ['key' => 'ENV_KEY_1', 'value' => 'one', 'comment' => 'first', 'export' => true],
    ['key' => 'ENV_KEY_2', 'value' => 'two'],
]);

DotenvEditor::addEmpty();
DotenvEditor::addComment('Mail');
DotenvEditor::deleteKey('ENV_KEY');
DotenvEditor::deleteKeys(['ENV_KEY_1', 'ENV_KEY_2']);
DotenvEditor::save();
```

Calling `setKey()` again before `save()` updates the buffered line. `deleteKey()` followed by `setKey()` for the same name puts the key back. Values `true`, `false` and `null` are written as those words. A boolean `true` or `false` passed from PHP is written as `true` or `false` as well.

Paths and quotes are escaped so Laravel's dotenv parser can read them:

```php
DotenvEditor::setKey('CERT_PATH', 'C:\\certs\\app.pem')->save();
// CERT_PATH="C:\\certs\\app.pem"
```

### Backups

```php
DotenvEditor::backup();
DotenvEditor::getBackups();
DotenvEditor::getLatestBackup();
DotenvEditor::restore();
DotenvEditor::restore(storage_path('dotenv-editor/backups/.env.backup_2017_04_10_152709'));
DotenvEditor::deleteBackup(storage_path('dotenv-editor/backups/.env.backup_2017_04_10_152709'));
DotenvEditor::deleteBackups();
DotenvEditor::autoBackup(false);
```

`load($path, true)` recreates a missing file from the latest backup. `load($path, true, $source)` copies `$source` instead. A failed copy throws `UnableWriteToFileException`.

### Artisan

```bash
php artisan dotenv:get-keys
php artisan dotenv:set-key APP_DEBUG false
php artisan dotenv:set-key MAIL_MAILER null
php artisan dotenv:delete-key EXAMPLE_KEY
php artisan dotenv:backup
php artisan dotenv:get-backups --latest
php artisan dotenv:restore
```

`dotenv:set-key` writes the value exactly as it was typed, including the words `true`, `false` and `null`. Pass `false` as the comment argument to clear an existing comment. `--export-key` adds the `export` prefix; without that option the current prefix is left in place. Use `--help` on each command for the path and restore options.

## Testing

```bash
composer test
```

## Security

Report vulnerabilities to [cardee@mifesta.com](mailto:cardee@mifesta.com).

## Credits

- [Sergei Gruzdov](https://github.com/cardee2000)

## License

MIT. See [LICENSE](LICENSE).
