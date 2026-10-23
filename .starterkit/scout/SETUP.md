## Search (Meilisearch)

The Meilisearch container is added to `docker-compose.local.yml` only. In production run Meilisearch yourself (or Meilisearch Cloud) and set `MEILISEARCH_HOST` and a strong `MEILISEARCH_KEY` in the `.env`, then index with `php artisan scout:import "App\Models\<Model>"`.
