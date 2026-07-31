# Meta Feed Plugin

The **Meta Feed** plugin for [Grav CMS](https://getgrav.org) fetches posts from Facebook Pages and
Instagram Business accounts through the Meta Graph API, stores them locally as Flex Objects, and
makes them available to your templates.

Media files are downloaded and stored alongside each post, so your site keeps working even when
Meta's CDN URLs expire.

## Features

- Facebook Pages and Instagram Business accounts through a single Graph API client
- Posts stored as Flex Objects — enable or disable individual posts in the admin
- Media downloaded locally, no expiring CDN URLs
- Automatic access token refresh before expiry
- One access token can serve both a Facebook Page and its linked Instagram account
- Scheduled fetching through Grav's built-in scheduler
- No Composer dependencies

## Requirements

- Grav 2.0 or later
- Flex Objects plugin
- PHP 8.1 or later with the cURL extension

## Installation

### GPM Installation (preferred)

```bash
bin/gpm install meta-feed
```

### Manual Installation

Download the zip of this repository, unzip it under `/your/site/grav/user/plugins`, and rename the
folder to `meta-feed`.

### Admin Plugin

Browse the `Plugins` menu and click `Add`.

## Configuration

Copy `user/plugins/meta-feed/meta-feed.yaml` to `user/config/plugins/meta-feed.yaml` and edit that
copy, or use the admin panel.

```yaml
enabled: true
api_version: '21.0'
fetch_limit: 20
accounts:
  - name: My Instagram
    platform: instagram
    page_id: '17841445266872043'
    token_type: page
    access_token: 'EAAxxx...'
```

### Account options

| Option | Description |
|---|---|
| `name` | Display name, shown in the admin and in log messages |
| `platform` | `facebook` or `instagram` |
| `page_id` | Facebook Page ID or Instagram Business Account ID — identifies the account |
| `token_type` | `page`, `fb_user` or `ig_login` |
| `access_token` | The access token |
| `token_expires` | Expiry date `YYYY-MM-DD`, not needed for `page` tokens |
| `app_id` | Meta App ID, required to refresh `fb_user` tokens |
| `app_secret` | Meta App Secret, required to refresh `fb_user` tokens |
| `use_shared_token` | Use another account's token |
| `shared_token_page_id` | The `page_id` of the account whose token to use |

### Token types

- **`page`** — A Page Access Token derived from a long-lived user token. Does not expire, and works
  for both a Facebook Page and its linked Instagram Business account. Recommended.
- **`fb_user`** — A long-lived Facebook user token, valid for 60 days. Refreshed automatically
  through `fb_exchange_token`; requires `app_id` and `app_secret`.
- **`ig_login`** — An Instagram Login token, valid for 60 days. Refreshed through
  `graph.instagram.com`. Only relevant when not using Facebook Login.

### Sharing one token between accounts

When a Facebook Page and an Instagram account are linked, one token covers both. Configure the
Facebook account normally, then point the Instagram account at it:

```yaml
accounts:
  - name: My Facebook Page
    platform: facebook
    page_id: '1033808586790860'
    token_type: page
    access_token: 'EAAxxx...'

  - name: My Instagram
    platform: instagram
    page_id: '17841445266872043'
    use_shared_token: true
    shared_token_page_id: '1033808586790860'
```

## Getting your credentials

1. Create an app at [developers.facebook.com](https://developers.facebook.com), type **Business**.
2. Add the use cases *Manage everything on your Page* and *Manage messaging and content on Instagram*.
3. In the Instagram use case, open **API setup with Facebook login** and add the required content
   permissions: `instagram_basic`, `pages_show_list`, `pages_read_engagement`.
4. Open the [Graph API Explorer](https://developers.facebook.com/tools/explorer), select your app and
   generate a user token, opting in to the Page and Instagram account you want to read.
5. Fetch the Page Access Token and Instagram account ID:
   ```
   GET /me/accounts?fields=name,id,access_token,instagram_business_account
   ```

The Instagram account must be a Business or Creator account linked to the Facebook Page, and the
Page must belong to a Meta Business Portfolio.

## Usage

### Twig

```twig
{% for post in meta_feed_posts() %}
    <img src="{{ post.image_url }}" alt="">
    <p>{{ post.message }}</p>
{% endfor %}
```

Filter by provider and limit the number of posts:

```twig
{% for post in meta_feed_posts('instagram', 6) %}
    ...
{% endfor %}
```

### Post properties

| Property | Description |
|---|---|
| `post.provider` | `facebook` or `instagram` |
| `post.page_id` | The Page / User ID the post came from |
| `post.message` | Post text or caption |
| `post.image_url` | URL of the locally stored image |
| `post.thumbnail_url` | Thumbnail URL, videos only |
| `post.media_type` | `IMAGE`, `VIDEO` or `CAROUSEL_ALBUM` |
| `post.link` | Link to the original post |
| `post.published_at` | Publication timestamp |
| `post.active` | Whether the post is enabled in the admin |

Inactive posts are filtered out by `meta_feed_posts()`.

### Templates

Default layouts live in `templates/flex/post/`. Override them in your theme:

```
your-theme/templates/flex/post/object/default.html.twig
your-theme/templates/flex/post/collection/default.html.twig
```

## Scheduling

The plugin registers two jobs with Grav's scheduler:

| Job | Schedule | Purpose |
|---|---|---|
| `meta-feed.fetch` | daily at 06:00 | Fetch new posts |
| `meta-feed.refresh-tokens` | daily at 05:00 | Refresh tokens within 15 days of expiry |

Make sure Grav's scheduler cron entry is installed — see **Tools → Scheduler** in the admin.

### Manual runs

```bash
bin/plugin meta-feed fetch
bin/plugin meta-feed refresh-tokens
```

## Storage

```
user/data/meta-feed/posts/
└── instagram-17854360229135492/
    ├── instagram-17854360229135492.json
    ├── image.jpg
    └── thumbnail.jpg
```

Posts are never overwritten once fetched, so admin changes such as disabling a post are preserved.

## License

MIT — see [LICENSE](LICENSE).
