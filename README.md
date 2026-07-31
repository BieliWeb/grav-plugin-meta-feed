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
- Scheduled fetching through Grav's built-in scheduler, plus a manual fetch button in the admin
- A plain, unstyled template you can drop in or copy and adapt
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

This is the fiddliest part of the setup, so it is worth following step by step. The whole process
happens once per site; afterwards the plugin refreshes tokens on its own.

### Step 0 — Prerequisites

Before touching the developer console, make sure of the following. Most failed setups fail here.

- The Instagram account is a **Business** or **Creator** account, not a personal one.
  Check in the Instagram app under *Settings → Account type*.
- The Instagram account is **linked to a Facebook Page**.
- The Facebook Page belongs to a **Meta Business Portfolio** at
  [business.facebook.com](https://business.facebook.com).
- Your own Facebook account has **full access to that Page**. If the site belongs to a client, they
  add you at *business.facebook.com → Settings → People → Add person*, granting full access to the
  Page. A classic Page role is not always enough for Business apps — the access has to come through
  the Business Portfolio.

### Step 1 — Create the Meta app

1. Go to [developers.facebook.com](https://developers.facebook.com) → *My Apps* → **Create App**.
2. Fill in the app details.
3. On the **Use cases** screen, select **both** of these:
   - *Manage everything on your Page*
   - *Manage messaging and content on Instagram*
4. Finish the wizard.

### Step 2 — Add the permissions

The Graph API Explorer only offers permissions your app has actually enabled, so this step has to
come first.

1. In the app dashboard, open **Use cases** → next to the Instagram use case click **Customise**.
2. In the left sidebar choose **API setup with Facebook login**.
   This matters: the other entry, *API setup with Instagram login*, grants `instagram_business_*`
   permissions, which this plugin does not use.
3. Click **Add required content permissions**. That adds:
   - `instagram_basic`
   - `pages_read_engagement`
   - `pages_show_list`
   - plus `business_management` and `instagram_content_publishing`, which are harmless

You do not need the messaging permissions, and you do not need to configure webhooks.

### Step 3 — Generate a user token

1. Open the [Graph API Explorer](https://developers.facebook.com/tools/explorer).
2. Under **Meta App** select your app, and set *User or Page* to **User token**.
3. Add the permissions `pages_show_list`, `pages_read_engagement` and `instagram_basic`.
   The permission dropdown is a search field — clear it before typing a new permission name,
   otherwise it keeps filtering on the previous entry.
4. Click **Generate Access Token** and work through the Facebook Login for Business dialog:
   - tick the **Facebook Page** you want to read
   - tick the **Instagram account** you want to read
   - confirm the summary screen

The dialog lists the numeric ID underneath each Page and Instagram account. **Write both down** —
these are the `page_id` values for your configuration, and they are easier to grab here than
anywhere else.

### Step 4 — Find your IDs

If you did not note the IDs in the login dialog, query them:

```
GET /me/accounts?fields=name,id,access_token,instagram_business_account
```

This returns the Page ID, the linked Instagram Business Account ID, and a **Page Access Token**,
which is the best token to use because it does not expire.

If the response is an empty `{"data": []}` even though you can see the Page in your account, the
token is scoped through a Business Portfolio and does not enumerate Pages this way. That is normal
for Business apps. Use the IDs from the login dialog and continue with Step 5.

### Step 5 — Get a long-lived token

The token from the Explorer is short-lived and expires after 1–2 hours, so it cannot be used
directly.

**If you have a Page Access Token** from Step 4, use it as-is with `token_type: page`. It does not
expire, and it works for both the Facebook Page and its linked Instagram account.

**Otherwise**, exchange the short-lived user token for a 60-day one. You need your App ID and App
Secret from *App settings → Basic*:

```
GET /oauth/access_token
    ?grant_type=fb_exchange_token
    &client_id={app-id}
    &client_secret={app-secret}
    &fb_exchange_token={short-lived-token}
```

Configure the result with `token_type: fb_user`, along with `app_id`, `app_secret` and a
`token_expires` date 60 days out. From then on the plugin refreshes it automatically.

### Step 6 — Verify

Click **Fetch Social Posts** in the admin toolbar, or run:

```bash
bin/plugin meta-feed fetch
```

Then check `user/data/meta-feed/posts/` for one folder per post, each containing `item.json` and
`image.jpg`.

### App Review

As long as only app administrators, developers and testers generate tokens, **no App Review is
required**. Review only becomes necessary if you want other people to log in through your app.

### Troubleshooting

| Symptom | Cause |
|---|---|
| `me/accounts` returns `{"data": []}` | Business-scoped token, or you lack Page access. Use the IDs from the login dialog. |
| `(#210) A page access token is required` | You are using a user token on a Page endpoint. Fetch the Page token, or use Instagram only. |
| `Invalid OAuth 2.0 Access Token` | The token expired. Short-lived Explorer tokens last 1–2 hours. |
| A permission is missing from the dropdown | It is not enabled in the app's use cases — see Step 2. Also clear the dropdown's search field. |
| Only `instagram_business_*` permissions offered | You are in *API setup with Instagram login*. Switch to *API setup with Facebook login*. |

## Usage

### The bundled template

The plugin ships a plain, unstyled partial as a starting point:

```twig
{% include 'partials/meta-feed.html.twig' %}
{% include 'partials/meta-feed.html.twig' with { provider: 'instagram', limit: 6 } %}
```

| Variable | Default | Description |
|---|---|---|
| `provider` | `''` | `instagram`, `facebook`, or empty for both |
| `limit` | `12` | Maximum number of posts, `0` for all |

It outputs a simple `<ul>` with `meta-feed` and `meta-feed__item` classes and brings no CSS of its
own, so it inherits your theme's styling. Copy it into your theme to change the markup:

```
user/plugins/meta-feed/templates/partials/meta-feed.html.twig
  →  your-theme/templates/partials/meta-feed.html.twig
```

### Building your own markup

```twig
{% for post in meta_feed_posts('instagram', 6) %}
    <img src="{{ post.imageUrl }}" alt="">
    <p>{{ post.message }}</p>
{% endfor %}
```

`meta_feed_posts(provider, limit)` returns posts sorted newest first. Both arguments are optional;
inactive posts are always filtered out.

### Post properties

| Property | Description |
|---|---|
| `post.provider` | `facebook` or `instagram` |
| `post.page_id` | The Page / User ID the post came from |
| `post.message` | Post text or caption |
| `post.imageUrl` | URL of the locally stored image; for videos this is the poster frame |
| `post.image_file` | Bare filename, e.g. `image.jpg` |
| `post.media_type` | `IMAGE`, `VIDEO` or `CAROUSEL_ALBUM` |
| `post.link` | Link to the original post |
| `post.published_at` | Publication timestamp |
| `post.active` | Whether the post is enabled in the admin |

Note the camelCase in `post.imageUrl`: it is a derived value backed by a `getImageUrl()` method
rather than a stored field, and Twig does not map `image_url` onto that method. The underlying
Flex media is also reachable directly as `post.media[post.image_file].url`.

### Flex Object templates

The admin list and the Flex rendering layouts live in `templates/flex/post/`. Override them in your
theme if needed:

```
your-theme/templates/flex/post/object/default.html.twig
your-theme/templates/flex/post/collection/default.html.twig
```

### Disabling individual posts

Posts appear in the admin under **Social Posts**. Switching a post to inactive removes it from
`meta_feed_posts()` without deleting it, and it will not be re-imported on the next fetch.

## Scheduling

The plugin registers two jobs with Grav's scheduler:

| Job | Default schedule | Purpose |
|---|---|---|
| `meta-feed-fetch` | hourly | Fetch new posts |
| `meta-feed-refresh-tokens` | Mondays at 05:00 | Refresh tokens within 15 days of expiry |

Hourly fetching is well within Meta's rate limits: it is one API call per account per hour, and
media is only downloaded for posts that are actually new. A weekly token check is enough because
tokens are renewed 15 days before they expire, leaving a comfortable margin.

Both schedules are configurable through the `fetch_at` and `refresh_at` options in the plugin
settings. Make sure Grav's scheduler cron entry is installed — see **Tools → Scheduler** in the
admin.

### Manual runs

A **Fetch Social Posts** button is available in the admin toolbar. It asks for confirmation, then
fetches immediately and reports how many new posts were imported. The button requires the
`admin.post.create` permission, or super admin.

From the command line:

```bash
bin/plugin meta-feed fetch
bin/plugin meta-feed refresh-tokens
```

## Storage

```
user/data/meta-feed/posts/
└── instagram-17854360229135492/
    ├── item.json
    └── image.jpg
```

Each post gets a deterministic `{platform}-{post_id}` folder, so a post is only ever imported once.
Admin changes such as disabling a post are preserved across fetches.

## Caching

Grav's page cache only watches the `pages` folder, so posts arriving under `user/data` would not
show up on a cached page until the cache expired on its own — by default after seven days. To avoid
that, the plugin clears Grav's cache whenever a fetch actually imported new posts. A fetch that
finds nothing new leaves the cache alone, so the hourly schedule does not cause needless churn.

Processed images are kept, unless you have enabled `system.cache.clear_images_by_default`.

Images under `user/data/` are served directly by the web server, while the JSON files are blocked —
Grav's shipped `.htaccess` and nginx configuration make exactly this distinction. If you use a
custom server configuration, make sure image files below `user/data/` remain reachable.

## License

MIT — see [LICENSE](LICENSE).
