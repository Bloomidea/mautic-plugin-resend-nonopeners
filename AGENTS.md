# AGENTS.md

This file provides guidance to agents when working with code in this repository.

## Project Overview

This is a **Mautic plugin** that adds a "Resend to Non-Openers" feature for segment (broadcast) emails. It is a standalone Symfony bundle distributed as a Composer package, installed into a Mautic instance at `plugins/MauticResendNonOpenersBundle/`.

- **Bundle namespace**: `MauticPlugin\MauticResendNonOpenersBundle`
- **Package**: `bloomidea/mautic-plugin-resend-nonopeners`
- **Type**: `mautic-plugin` (Composer type)
- **Target**: Mautic 7.0+ (Symfony 7.4, PHP 8.2+)

## Plugin Architecture

### Directory layout (at the repo root)

```
.
├── Command/                # CLI commands
├── Config/                 # Plugin config (routes) and service definitions
│   ├── config.php          # Routes + plugin metadata (name, description, version)
│   └── services.php        # Symfony service definitions (autowired)
├── Controller/             # HTTP controllers
│   ├── Api/                # REST API controllers
│   └── ...                 # Main UI controllers
├── DependencyInjection/    # Symfony DI extension (required by plugin system)
├── Entity/                 # Doctrine entities + repositories
├── EventListener/          # Event subscribers (e.g., button injection)
├── Resources/views/        # Twig templates
├── Service/                # Business logic services
├── Tests/                  # Unit and functional tests
├── Translations/en_US/     # Translation files (messages.ini, flashes.ini)
├── MauticResendNonOpenersBundle.php  # Bundle bootstrap class
└── composer.json
```

### Key architectural rules for Mautic plugins

1. **No modifications to Mautic core** — plugins cannot edit core entities, templates, or services. All integration must go through events, DI, or the plugin's own code.

2. **Each plugin needs its own `DependencyInjection/Mautic<Name>Extension.php`** — without this, `Config/services.php` is never loaded and services aren't registered. This is a Symfony bundle convention; Mautic plugin loader uses it to wire DI.

3. **Service registration** — use autowiring via `Config/services.php` with `$services->load('MauticPlugin\\MauticResendNonOpenersBundle\\', '../')`. The same pattern used by `MauticFocusBundle`.

4. **Entity discovery** — entities in `Entity/` are auto-discovered by Doctrine because the plugin bundle is registered with the kernel. The plugin's custom tables are created automatically on plugin install via `doctrine:schema:update`.

5. **UI injection via events** — plugins cannot edit core templates. To add UI elements (buttons on detail pages, etc.), listen for `CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS` and inspect `$event->getRoute()` and `$event->getItem()` to decide what to inject.

6. **Routes must not conflict with core catch-all routes** — Mautic core has routes like `mautic_email_action` with the path `/emails/{objectAction}/{objectId}` that swallow any `/s/emails/X/Y` URL. Plugin routes must use distinct URL prefixes (this plugin uses `/resend-nonopeners/...`).

7. **Flash messages use the `flashes` domain** — `addFlashMessage()` translates using `flashes.ini` by default. UI labels go in `messages.ini`.

8. **INI translation files must quote values** — PHP's `parse_ini_file` requires quoted values for strings containing special characters like `:`, `?`, etc. Match Mautic's existing format.

## Development Workflow

### Setting up for local development

The plugin must be installed into a Mautic instance to run. Two options:

**Option 1: Symlink from Mautic's plugins directory** (recommended for iterative development)

```bash
cd /path/to/mautic/plugins
ln -s /path/to/mautic-plugin-resend-nonopeners MauticResendNonOpenersBundle
cd /path/to/mautic
bin/console mautic:plugins:reload
bin/console cache:clear
```

**Option 2: Clone directly into plugins/**

```bash
cd /path/to/mautic/plugins
git clone <repo-url> MauticResendNonOpenersBundle
```

### After making changes

```bash
# From the Mautic root directory
bin/console cache:clear

# If you added/modified entities, reload the plugin to trigger schema update
bin/console mautic:plugins:reload
```

### Running tests

```bash
# From the Mautic root directory (tests run in the Mautic test environment)
bin/phpunit plugins/MauticResendNonOpenersBundle/Tests/
```

## Essential Commands (Mautic context)

These commands are run from the Mautic root, not the plugin repo:

```bash
# Plugin management
bin/console mautic:plugins:reload      # Re-register plugins after structural changes
bin/console mautic:plugins:install     # Install newly added plugins
bin/console cache:clear                # Clear cache after any service/config change

# This plugin's CLI command
bin/console mautic:emails:resend-nonopeners <email-id>            # Execute resend
bin/console mautic:emails:resend-nonopeners <email-id> --dry-run  # Check eligibility only
```

## Feature Overview

### What the plugin does

After a segment email has finished sending, this plugin lets the user resend it to contacts who did not open it. It automates the manual workflow of cloning the segment with a "not read email" filter, cloning the email tree (parent + translations), and publishing it for the broadcast cron to send.

### Key components

- **`NonOpenersService`** — orchestrates the entire flow: segment cloning, email cloning, translation handling, persistence
- **`EmailResend` entity** — stores the relationship between an original email and its resend (replaces what would have been a FK column on the core `emails` table)
- **`ButtonSubscriber`** — injects the "Resend to Non-Openers" button on the email detail page via `VIEW_INJECT_CUSTOM_BUTTONS`
- **`ResendNonOpenersController`** — modal + execute actions for the UI
- **`ResendApiController`** — REST API endpoint
- **`ResendNonOpenersCommand`** — CLI command with `--dry-run` option

### Segment filter approach

The plugin does not write any custom SQL. It leverages existing Mautic segment filters:

- **`leadlist` filter** (Segment Membership) with `in` operator — scopes the audience to the original segment(s)
- **`lead_email_received` filter** (Read a specific email) with `!in` operator — excludes contacts who have read any of the original email's translations

The cloned segment is rebuilt via `mautic:segments:update` to populate contacts, then the cloned email is published and picked up by Mautic's standard broadcast cron (`mautic:broadcasts:send`).

## Coding Standards

- PHP 8.2+ with `declare(strict_types=1);`
- PSR-12 / Symfony code style
- 4-space indentation, short array syntax `[]`, ordered imports
- Explicit types everywhere (parameters, return types, properties)
- Constructor promotion for dependencies
- Follow the patterns used by existing Mautic plugins (especially `MauticFocusBundle`)

## Constraints (enforced in the service layer)

- Only segment emails (`emailType === 'list'`) can be resent
- The email must have finished sending (`getSendingStatus() === 'sent'`)
- Each email can only be resent once (enforced by `email_resends` table + application check)
- A resend email cannot be resent again
- Triggering from a translation child resolves to the translation parent automatically
- Permission check: `email:emails:editown` or `email:emails:editother`

## Related Links

- GitHub issue: https://github.com/mautic/mautic/issues/16004
- Forum discussion: https://forum.mautic.org/t/add-resend-to-non-openers-action-for-segment-emails/38042
- Reference plugin: `mautic/plugins/MauticFocusBundle` (similar structure)
