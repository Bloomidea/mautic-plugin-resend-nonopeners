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
- **Auto-categorization** — creates a "Resend Non-Openers" category and assigns it to all cloned emails and segments for easy filtering in lists
- **Auto-cleanup** — when the resend email finishes sending, is manually unpublished or is deleted, the linked segment is automatically unpublished too
- **Safe by default** — each email can only be resent once; a resend cannot be resent again
- **Fully async** — the HTTP request returns immediately; segment rebuild and delivery happen in the background via Mautic's standard crons, so it works for segments of any size without timeouts
- **Uses the standard broadcast cron** — scales to any list size, respects rate limits

## Screenshots

**"Resend to Non-Openers" in the Options dropdown** (only appears for segment emails with sends)

![Dropdown menu showing Resend to Non-Openers button](https://github.com/user-attachments/assets/dc774791-511b-4a02-b585-eee2705e9b5e)

**Confirmation modal**

![Confirmation modal with email name and notes](https://github.com/user-attachments/assets/4743dc05-6976-459e-bfa4-5a77cf35db34)

**Auto-generated segment with membership + non-opener filters**

![Segment filters showing segment membership and read email excluding](https://github.com/user-attachments/assets/0635beeb-d253-4c92-9e91-607e86c9d4b3)

For the full visual walkthrough (8 screenshots), see [mautic/mautic#16004](https://github.com/mautic/mautic/issues/16004).

## How it works

1. You click "Resend to Non-Openers" on a sent segment email
2. The plugin creates a new segment with two filters:
   - **Segment Membership — including any of — [original segment(s)]**
   - **Read a specific email — excluding any of — [original + all translations]**
3. The plugin clones the email and all its translation children, assigns them to the new segment, and publishes them without a "Publish at" date, so the broadcast cron leaves them alone until the segment is built
4. Both the segment and the cloned emails are tagged with a "Resend Non-Openers" category (auto-created on first use)
5. A record of the resend is stored in the `email_resends` table so the email can't be resent again
6. The HTTP request returns immediately — everything from here is async via Mautic's standard crons
7. `mautic:segments:update` rebuilds the new segment to populate the non-opener contacts
8. After the segment's first full build, the plugin sets "Publish at" on the cloned emails to the next full minute. Mautic only sends a segment email to contacts who joined the segment before that date, so every non-opener found by the build is included, and contacts who join later are not
9. `mautic:broadcasts:send` picks up the cloned email and sends it to the non-openers
10. When all non-openers have been sent to, Mautic auto-unpublishes the resend email
11. The plugin detects the unpublish and automatically unpublishes the linked segment too — no cleanup needed

Deleting a resend email also unpublishes its segment and removes the resend record, so the original email can be resent again.

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
git clone --branch v1.0.6 --depth 1 https://github.com/Bloomidea/mautic-plugin-resend-nonopeners.git MauticResendNonOpenersBundle
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
5. Sending starts on the first broadcast cron run after `mautic:segments:update` has built the new segment

> **Note on continuous-sending emails:** if the original email has "continue sending" enabled, "non-openers" will include very recent recipients. The plugin still allows the resend — the decision of when to trigger it is up to you.

### Upgrading from 1.0.5 or earlier

Resends created with 1.0.5 got a "Publish at" date before their segment was built, so they send to nobody ([#4](https://github.com/Bloomidea/mautic-plugin-resend-nonopeners/issues/4)). The upgrade does not touch them, nor resends created before 1.0.5, which never got a "Publish at" date: the plugin only schedules a resend on its segment's first build. To send one of them, set "Publish at" on the resend email and its translations to a time after the segment was built, in the email form or directly:

```sql
UPDATE emails SET publish_up = UTC_TIMESTAMP() WHERE id IN (<resend parent id>, <translation ids>);
```

Resend emails deleted before 1.0.6 left their segment published and blocked a new resend of the original ([#5](https://github.com/Bloomidea/mautic-plugin-resend-nonopeners/issues/5)). To find them:

```sql
SELECT er.*, ll.name, ll.is_published
FROM email_resends er
LEFT JOIN emails e ON e.id = er.resend_email_id
LEFT JOIN lead_lists ll ON ll.id = er.resend_segment_id
WHERE e.id IS NULL;
```

For each row, unpublish the segment (`UPDATE lead_lists SET is_published = 0 WHERE id = <resend_segment_id>`) and delete the row.

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
- The original email must have been sent to at least one contact (`sentCount > 0`). This includes emails with "continue sending" enabled, since those never reach `sendingStatus === 'sent'` despite having real non-openers to target.
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
