## Update: Plugin released

Following the maintainer's suggestion to start as a plugin, this feature is now available as a standalone Mautic plugin with zero core modifications:

**[bloomidea/mautic-plugin-resend-nonopeners](https://github.com/Bloomidea/mautic-plugin-resend-nonopeners)** — [Packagist](https://packagist.org/packages/bloomidea/mautic-plugin-resend-nonopeners)

### Install

```
composer require bloomidea/mautic-plugin-resend-nonopeners
bin/console mautic:plugins:reload
```

Or for Docker runtime images (no composer.json): clone directly into `plugins/MauticResendNonOpenersBundle/`.

### What it does

One-click resend of segment emails to contacts who didn't open them. Automates the manual workflow of cloning the segment with a "not read email" filter, cloning the email (including translations), and publishing it for the broadcast cron.

### Highlights

- **UI button** in the email detail page dropdown + **CLI command** + **REST API**
- **Multilingual support** — clones parent and all translation children
- **Auto-categorization** — tags cloned emails and segments with a "Resend Non-Openers" category
- **Auto-cleanup** — when sending completes, the resend segment is automatically unpublished
- **Fully async** — works for segments of any size without HTTP timeouts
- Works with emails that have "continue sending" enabled

Tested in production with multilingual emails and segments of 12,000+ contacts.

The GitHub issue has a [full visual walkthrough with screenshots](https://github.com/mautic/mautic/issues/16004).

Would love feedback from the community. Is this useful for your workflow? Any features you'd want added?
