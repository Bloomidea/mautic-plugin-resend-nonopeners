# Mautic Plugin: Resend to Non-Openers

[![Packagist Version](https://img.shields.io/packagist/v/bloomidea/mautic-plugin-resend-nonopeners.svg)](https://packagist.org/packages/bloomidea/mautic-plugin-resend-nonopeners)
[![License](https://img.shields.io/github/license/Bloomidea/mautic-plugin-resend-nonopeners.svg)](LICENSE)

A Mautic plugin that adds a one-click "Resend to Non-Openers" action for segment (broadcast) emails.

After a segment email has been sent, this plugin lets you resend it to contacts who did not open it. It automates the manual workflow of cloning the segment with a "not read email" filter, cloning the email (including all its translations), and publishing it for the broadcast cron to send.

## Why

Every major email marketing platform (Mailchimp, Brevo, ActiveCampaign) has a one-click resend-to-non-openers feature. In Mautic it previously required a tedious manual workflow. Mailchimp data from 1,300 resend campaigns shows resends add **+8.7 percentage points** to the original open rate (from a 26.7% average) — it's one of the highest-ROI email actions.

## Features

- **One-click resend** from the email detail page (Options dropdown → "Resend to Non-Openers")
- **CLI command** for automation: `bin/console mautic:emails:resend-nonopeners <email-id> [--dry-run]`
- **API endpoint**: `POST /api/resend-nonopeners/{id}`
- **Multilingual support** — clones parent email and all translation children, filters across all translation IDs
- **Automatic segment creation** — new segment combines "member of original audience" + "did not read email" filters
- **Safe by default** — each email can only be resent once; a resend cannot be resent again
- **Uses the standard broadcast cron** — scales to any list size, respects rate limits

## How it works

1. You click "Resend to Non-Openers" on a sent segment email
2. The plugin creates a new segment with two filters:
   - **Segment Membership — including any of — [original segment(s)]**
   - **Read a specific email — excluding any of — [original + all translations]**
3. The segment is rebuilt to populate the non-opener contacts
4. The plugin clones the email and all its translation children, assigns them to the new segment, and publishes them
5. Mautic's `mautic:broadcasts:send` cron picks up the cloned email and sends it to the non-openers
6. A record of the resend is stored in the `email_resends` table so the email can't be resent again

## Requirements

- Mautic 7.0 or newer
- PHP 8.2+

## Installation

### Via Composer (recommended)

The plugin is published on [Packagist](https://packagist.org/packages/bloomidea/mautic-plugin-resend-nonopeners). If your Mautic installation has a `composer.json` (source-based install), run:

```bash
composer require bloomidea/mautic-plugin-resend-nonopeners
bin/console mautic:plugins:reload
bin/console cache:clear
```

Composer reads `install-directory-name` from the plugin's `composer.json` and installs it to `plugins/MauticResendNonOpenersBundle/` automatically.

### Manual installation (for runtime Docker images)

The official `mautic/mautic:7-apache` Docker image is a runtime build without `composer.json`, so `composer require` will not work inside it. In that case, clone the plugin directly into the plugins directory and pin a tag for reproducible builds:

```bash
cd /path/to/mautic/plugins
git clone --branch v1.0.1 --depth 1 https://github.com/Bloomidea/mautic-plugin-resend-nonopeners.git MauticResendNonOpenersBundle
rm -rf MauticResendNonOpenersBundle/.git
bin/console mautic:plugins:reload
bin/console cache:clear
```

For Dockerfile usage, add the same commands as a `RUN` step. To upgrade, bump the tag and rebuild.

The plugin creates an `email_resends` table automatically from its entity metadata during plugin reload.

## Usage

### UI

1. Open a segment email that has been sent to at least one contact
2. Click the **Options** dropdown (the chevron next to the Edit button)
3. Click **Resend to Non-Openers**
4. Confirm in the modal
5. The broadcast cron will send the cloned email to non-openers on its next run

> **Note on continuous-sending emails:** if the original email has "continue sending" enabled, "non-openers" will include very recent recipients. The plugin still allows the resend — the decision of when to trigger it is up to you.

### CLI

```bash
# Dry run — check if an email is eligible without doing anything
bin/console mautic:emails:resend-nonopeners 42 --dry-run

# Execute the resend
bin/console mautic:emails:resend-nonopeners 42
```

### API

```bash
curl -X POST "https://your-mautic.example.com/api/resend-nonopeners/42" \
  -u admin:password
```

Response:
```json
{
  "success": 1,
  "emailId": 123,
  "segmentIds": [45]
}
```

## Constraints

- Only segment (broadcast) emails can be resent — not template/campaign emails
- The original email must have finished sending (`sendingStatus === 'sent'`)
- Each email can only be resent once
- A resend email cannot be resent again
- Triggering from a translation child automatically resolves to the parent
- Permission check: requires `email:emails:editown` or `email:emails:editother`

## Technical approach

The plugin is implemented without any modifications to Mautic core:

- **No core entity changes** — a plugin-owned `EmailResend` entity stores the relationship between an original email and its resend, instead of a column on the `emails` table
- **No template modifications** — the UI button is injected via the `CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS` event
- **Leverages existing Mautic primitives** — uses the `lead_email_received` segment filter (with "excluding any of" operator) and the `leadlist` membership filter; no custom SQL
- **Standard broadcast pipeline** — the cloned email is published and picked up by `mautic:broadcasts:send` like any other segment email

## Related links

- GitHub issue: [mautic/mautic#16004](https://github.com/mautic/mautic/issues/16004)
- Forum discussion: [Add Resend to Non-Openers action for segment emails](https://forum.mautic.org/t/add-resend-to-non-openers-action-for-segment-emails/38042)

## License

GPL-3.0-or-later — same license as Mautic core.
