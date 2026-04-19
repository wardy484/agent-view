# Deployment

## Production — Laravel Cloud

- **URL**: https://nexus-ui-production-w1o1a4.laravel.cloud
- **Region**: `eu-west-2` (London)
- **Application**: `nexus-ui`
- **Environment**: `production` (branch `main`, push-to-deploy enabled)
- **Database**: `neon_serverless_postgres_17` cluster `nexus-ui-pg`, schema `nexus`
- **Cache / Queue / Sessions**: Upstash Redis 250 MB (`nexus-ui-redis`)
- **Worker**: 1× `queue:work redis` background process on the app instance

One environment only — dogfooding happens against production by this project's
owner until a public launch is planned. New environments can be added via
`cloud environment:create <app-id> --name=<name> --branch=<branch>` later.

## Deploying

Every push to `main` triggers a deploy automatically (Laravel Cloud GitHub app).
To deploy manually:

```bash
cloud deploy nexus-ui production
```

To tail logs:

```bash
cloud environment:logs nexus-ui production
```

## Build and deploy commands

Configured on the environment (not in `cloud.yaml` — Laravel Cloud uses its own
control-plane config). Current commands:

```
# build
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci --audit false
npm run build

# deploy
php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## Runtime versions

- PHP: 8.5 (Laravel Cloud default; app is compatible with 8.4+)
- Node: 24

Local dev via Laravel Herd is pinned to PHP 8.4 / Node 22 in `.herd.yml` for
reproducibility; production can safely run on a newer PHP because Laravel 13
supports both.

## Secrets

All environment variables are managed through `cloud environment:variables`.
Never commit secrets to the repo. To update the full env:

```bash
cloud environment:variables nexus-ui production --action=replace --force < path/to/env
```

To set a single key:

```bash
cloud environment:variables nexus-ui production --action=set --key=FOO --value=bar --force
```
