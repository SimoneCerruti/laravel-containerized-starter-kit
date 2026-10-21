#!/usr/bin/env bash
#
# Deploy gateway: the only thing the CI can run on the server.
#
# Installed as /usr/local/bin/deploy-gateway and bound to each deploy key in the authorized_keys
# of the `deploy` user, one key per app+environment:
#
#   restrict,command="/usr/local/bin/deploy-gateway <app> <env>" ssh-ed25519 AAAA...
#
# The CI chooses only the verb (SSH_ORIGINAL_COMMAND), which must match one of the fixed ones below.
# `deploy` is in the docker group, which is root on the host: that is why the compose file is checked
# against a policy before it can run, and why the checked (normalized) file is the one that runs.
#
# Layout, per app and environment:
#   $ROOT/<app>/<env>/deploy.conf   owned by root: ALLOWED_HOSTS="host1 host2"
#   $ROOT/<app>/<env>/state/        owned by deploy: compose.yml, .env, previous/, *.incoming
#
# Verbs, in the order the pipeline runs them:
#   put-compose   stdin -> compose file, not active yet
#   put-env       stdin -> .env, not active yet (must contain IMAGE_REF=repo@sha256:...)
#   activate      validate both and make them the active ones
#   pull          stdin: registry user and read-only token, two lines; pulls the images
#   up-services   start everything but `app` (the database)
#   migrate       one-off container: php artisan migrate --force
#   rollout       zero-downtime replacement of `app`
#   prune         remove old images of the app, keeping the current and the previous one
#   status        containers, versions and recent logs

set -euo pipefail
umask 077

ROOT=${DEPLOY_ROOT:-/var/www}
CONF_OWNER_UID=${DEPLOY_CONF_OWNER_UID:-0}
PROXY_NETWORK=traefik
MAX_INPUT_BYTES=262144
WORKER_PROGRAMS=('laravel-worker:*' laravel-cron)

die() {
    echo "gateway: $*" >&2
    exit 1
}

cleanup_paths=()
cleanup() {
    if ((${#cleanup_paths[@]} > 0)); then
        rm -rf -- "${cleanup_paths[@]}"
    fi
}
trap cleanup EXIT

# Nothing from the caller's environment may steer docker or compose.
for variable in $(compgen -e | grep -E '^(COMPOSE_|DOCKER_)' || true); do
    unset "$variable"
done

app=${1:-}
environment=${2:-}
verb=${SSH_ORIGINAL_COMMAND:-}

name_pattern='^[a-z0-9][a-z0-9-]*$'
[[ $app =~ $name_pattern ]] || die "invalid app name"
[[ $environment =~ $name_pattern ]] || die "invalid environment name"

PROJECT="$app-$environment"
APP_DIR="$ROOT/$app/$environment"
STATE="$APP_DIR/state"
CONF="$APP_DIR/deploy.conf"

[[ -d $STATE ]] || die "$STATE does not exist: set up the server first"
[[ -f $CONF ]] || die "$CONF does not exist"

# deploy.conf is sourced, so it must belong to root and be writable by nobody else.
[[ $(stat -c %u "$CONF") == "$CONF_OWNER_UID" ]] || die "$CONF is not owned by the expected user"
((("8#$(stat -c %a "$CONF")" & 8#022) == 0)) || die "$CONF must not be writable by group or others"

ALLOWED_HOSTS=
# shellcheck source=/dev/null
. "$CONF"
[[ -n $ALLOWED_HOSTS ]] || die "ALLOWED_HOSTS is empty in $CONF"
host_pattern='^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$'
for host in $ALLOWED_HOSTS; do
    [[ $host =~ $host_pattern ]] || die "invalid host in ALLOWED_HOSTS: $host"
done

export COMPOSE_PROJECT_NAME=$PROJECT

logger -t deploy-gateway "$PROJECT: ${verb:-<none>} from ${SSH_CLIENT:-local}" 2>/dev/null || true

compose() {
    docker compose --project-name "$PROJECT" --project-directory "$STATE" \
        -f "$STATE/compose.yml" --env-file "$STATE/.env" "$@"
}

# Last assignment of KEY in an env file, as compose and Laravel read it. Only used for values the
# pipeline writes itself (IMAGE_REF, APP_VERSION).
env_value() {
    local key=$1 file=$2
    { grep -E "^${key}=" "$file" || true; } | tail -n 1 | cut -d= -f2- | sed -E "s/^[\"']//; s/[\"']\$//"
}

# Read stdin into $1 through a temporary file, so a failed or oversized upload never leaves a
# half-written file behind.
read_input_into() {
    local destination=$1 incoming
    incoming=$(mktemp "$STATE/.incoming.XXXXXX")
    cleanup_paths+=("$incoming")
    head -c $((MAX_INPUT_BYTES + 1)) > "$incoming"

    (($(stat -c %s "$incoming") <= MAX_INPUT_BYTES)) || die "input larger than $MAX_INPUT_BYTES bytes"
    [[ -s $incoming ]] || die "empty input"

    mv -f -- "$incoming" "$destination"
}

# Policy applied to the normalized compose (docker compose config --format json). It prints one
# line per violation. Allow-lists are used wherever possible: what is not listed is refused.
POLICY=$(cat <<'JQ'
def service_keys: [
    "image", "command", "entrypoint", "environment", "env_file", "labels", "volumes", "networks",
    "healthcheck", "restart", "depends_on", "stop_grace_period", "stop_signal", "mem_limit",
    "mem_reservation", "memswap_limit", "cpus", "pids_limit", "security_opt", "cap_drop", "user",
    "working_dir", "init", "tmpfs", "read_only", "shm_size", "expose", "pull_policy", "profiles",
    "ulimits"
];

def inside_state:
    (startswith($state + "/")) and ((split("/") | index("..")) == null);

def owned_name($name):
    $name == $project or ($name | startswith($project + "-"));

def volume_violations($service; $declared):
    if .type == "bind" then
        if (.source | inside_state) then empty
        else "service \($service): bind mount outside \($state): \(.source)" end
    elif .type == "volume" then
        if .source == null or (.source as $source | $declared | has($source)) then empty
        else "service \($service): volume \(.source) is not declared" end
    elif .type == "tmpfs" then empty
    else "service \($service): mount type not allowed: \(.type)" end;

def rule_violations($key):
    . as $rule
    | if ($rule | test("^Host\\(")) | not then "\($key): the rule must start with Host(...)"
      elif ($rule | test("\\|\\||!|HostRegexp|HostSNI"; "i")) then "\($key): operators and matchers other than Host/&& are not allowed"
      else
        [$rule | match("Host\\(([^)]*)\\)"; "g").captures[0].string
            | match("`([^`]+)`"; "g").captures[0].string]
        | .[] | select(. as $host | $hosts | index($host) | not)
        | "\($key): host \(.) is not in ALLOWED_HOSTS"
      end;

def reference_violations($key):
    split(",") | .[] | sub("@docker$"; "") | select(owned_name(.) | not)
    | "\($key): reference to \(.) outside \($project)";

def label_violations($service):
    .key as $key | (.value | tostring) as $value
    | select($key | startswith("traefik."))
    | if $key == "traefik.enable" then empty
      elif $key == "traefik.docker.network" then
          (if $value != $proxy then "service \($service): traefik.docker.network must be \($proxy)" else empty end)
      elif ($key | test("^traefik\\.http\\.(routers|services|middlewares)\\.[^.]+\\.")) then
          ($key | capture("^traefik\\.http\\.(?<kind>routers|services|middlewares)\\.(?<name>[^.]+)\\.(?<rest>.+)$")) as $parts
          | (if owned_name($parts.name) then empty
             else "service \($service): \($key): name must be \($project) or start with \($project)-" end),
            (if $parts.kind == "routers" and $parts.rest == "rule" then ($value | rule_violations($key)) else empty end),
            (if $parts.kind == "routers" and ($parts.rest == "service" or $parts.rest == "middlewares")
             then ($value | reference_violations($key)) else empty end),
            (if $parts.kind == "services" and ($parts.rest | test("(^|\\.)(url|servers)(\\.|$)"))
             then "service \($service): \($key): static server addresses are not allowed" else empty end)
      else "service \($service): label not allowed: \($key)" end;

def env_file_violations($service):
    (if type == "string" then . else .path end) as $path
    | if ($path | inside_state) then empty else "service \($service): env_file outside \($state): \($path)" end;

. as $compose
| ($compose.services // {}) as $services
| ($compose.networks // {}) as $networks
| ($compose.volumes // {}) as $volumes
| [
    ($compose | keys[] | select(IN("name", "services", "networks", "volumes") | not) | select(startswith("x-") | not)
        | "top-level key not allowed: \(.)"),

    (if $compose.name != $project then "project name must be \($project)" else empty end),
    (if $services.app == null then "service app is required" else empty end),

    ($networks | to_entries[] | .key as $key | .value as $network
        | if $network.external == true then
              (if $network.name != $proxy then "network \($key): the only external network allowed is \($proxy)" else empty end)
          else
              ($network | keys[] | select(IN("name", "internal", "driver", "ipam") | not) | "network \($key): option not allowed: \(.)"),
              (if ($network.driver // "bridge") != "bridge" then "network \($key): driver must be bridge" else empty end),
              (if ($network.ipam // {} | length) > 0 then "network \($key): ipam options are not allowed" else empty end),
              (if ($network.name | startswith($project + "_")) | not then "network \($key): name must start with \($project)_" else empty end)
          end),

    ($volumes | to_entries[] | .key as $key | .value as $volume
        | (if $volume.external == true then "volume \($key): external volumes are not allowed" else empty end),
          ($volume | keys[] | select(. != "name") | "volume \($key): option not allowed: \(.)"),
          (if ($volume.name // "" | startswith($project + "_")) | not then "volume \($key): name must start with \($project)_" else empty end)),

    ($services | to_entries[] | .key as $service | .value as $definition
        | ($definition | keys[] | select(IN(service_keys[]) | not) | select(startswith("x-") | not)
            | "service \($service): key not allowed: \(.)"),
          (($definition.security_opt // [])[] | select(IN("no-new-privileges:true", "no-new-privileges") | not)
            | "service \($service): security_opt not allowed: \(.)"),
          (($definition.volumes // [])[] | volume_violations($service; $volumes)),
          (($definition.env_file // []) | if type == "array" then .[] else . end | env_file_violations($service)),
          (($definition.networks // {}) | keys[] | select(. as $key | $networks | has($key) | not)
            | "service \($service): unknown network \(.)"),
          (($definition.networks // {}) | to_entries[] | select(.value != null and (.value | length) > 0)
            | "service \($service): network \(.key): aliases, static addresses and other options are not allowed"),
          (($definition.labels // {}) | to_entries[] | label_violations($service)))
  ] | .[]
JQ
)

check_policy() {
    local compose_json=$1 violations hosts_json hosts
    read -ra hosts <<< "$ALLOWED_HOSTS"
    hosts_json=$(printf '%s\n' "${hosts[@]}" | jq -R . | jq -s .)

    violations=$(jq -r --arg project "$PROJECT" --arg state "$STATE" --arg proxy "$PROXY_NETWORK" \
        --argjson hosts "$hosts_json" "$POLICY" "$compose_json") || die "the policy could not be applied"

    if [[ -n $violations ]]; then
        echo "gateway: the compose file violates the deploy policy:" >&2
        while IFS= read -r violation; do
            echo "  - $violation" >&2
        done <<< "$violations"
        exit 1
    fi
}

verb_put_compose() {
    read_input_into "$STATE/compose.incoming.yml"
}

verb_put_env() {
    read_input_into "$STATE/env.incoming"
}

verb_activate() {
    local incoming_compose=$STATE/compose.incoming.yml incoming_env=$STATE/env.incoming
    [[ -f $incoming_compose && -f $incoming_env ]] || die "nothing to activate: run put-compose and put-env first"

    # include and extends are resolved by compose before the policy can see them
    if grep -Eq '^[[:space:]]*(include|extends)[[:space:]]*:' "$incoming_compose"; then
        die "include and extends are not allowed in the compose file"
    fi

    local image_ref image_pattern='^[a-z0-9][a-z0-9._:/-]*@sha256:[0-9a-f]{64}$'
    image_ref=$(env_value IMAGE_REF "$incoming_env")
    [[ $image_ref =~ $image_pattern ]] || die "IMAGE_REF must be repo@sha256:<digest>"

    local normalized
    normalized=$(mktemp "$STATE/.normalized.XXXXXX")
    cleanup_paths+=("$normalized")

    docker compose --project-name "$PROJECT" --project-directory "$STATE" \
        -f "$incoming_compose" --env-file "$incoming_env" \
        config --no-env-resolution --format json > "$normalized" || die "the compose file is not valid"

    check_policy "$normalized"

    if [[ -f $STATE/.env && $(env_value IMAGE_REF "$STATE/.env") != "$image_ref" ]]; then
        mkdir -p "$STATE/previous"
        cp -f -- "$STATE/compose.yml" "$STATE/previous/compose.yml"
        cp -f -- "$STATE/.env" "$STATE/previous/.env"
    fi

    # The container's www-data must read the .env through the bind mount; the state directory
    # (700) is what keeps it away from other users of the host.
    chmod 644 "$incoming_env"
    mv -f -- "$incoming_env" "$STATE/.env"
    mv -f -- "$normalized" "$STATE/compose.yml"
    rm -f -- "$incoming_compose"

    echo "activated $image_ref (version $(env_value APP_VERSION "$STATE/.env"))"
}

verb_pull() {
    local registry_user registry_token
    IFS= read -r registry_user || true
    IFS= read -r registry_token || true

    local user_pattern='^[A-Za-z0-9._-]+$' token_pattern='^[A-Za-z0-9._-]+$'
    [[ $registry_user =~ $user_pattern ]] || die "invalid registry user"
    [[ $registry_token =~ $token_pattern ]] || die "invalid registry token"

    # Credentials live in a throwaway docker config for the duration of the pull only.
    local docker_config
    docker_config=$(mktemp -d "$STATE/.docker.XXXXXX")
    cleanup_paths+=("$docker_config")

    printf '%s' "$registry_token" | docker --config "$docker_config" login --username "$registry_user" --password-stdin > /dev/null
    DOCKER_CONFIG=$docker_config compose pull
}

verb_up_services() {
    local services
    mapfile -t services < <(compose config --services | grep -vx app || true)

    if ((${#services[@]} > 0)); then
        compose up --detach --wait --wait-timeout 180 "${services[@]}"
    fi
}

verb_migrate() {
    # Runs as www-data like the app, with Traefik disabled so the one-off container never gets traffic.
    compose run --rm --no-deps -T --label traefik.enable=false app \
        su www-data -s /bin/bash -c 'HOME=/var/www php artisan migrate --force'
}

# Two app containers can't run side by side without running every job and cron twice, so the old
# one stops its workers first and gets them back if the rollout fails. A minute without workers is
# preferred over duplicated work.
verb_rollout() {
    local old_containers
    mapfile -t old_containers < <(compose ps --quiet app)

    local container
    for container in "${old_containers[@]}"; do
        docker exec "$container" supervisorctl stop "${WORKER_PROGRAMS[@]}" || echo "gateway: could not stop the workers of $container" >&2
    done

    if ! docker rollout --project-name "$PROJECT" --file "$STATE/compose.yml" --env-file "$STATE/.env" --timeout 180 app; then
        for container in "${old_containers[@]}"; do
            docker exec "$container" supervisorctl start "${WORKER_PROGRAMS[@]}" || true
        done
        die "rollout failed: the previous version is still serving"
    fi
}

verb_prune() {
    local current repo
    current=$(env_value IMAGE_REF "$STATE/.env")
    # docker lists Docker Hub images without the registry prefix
    repo=${current%@*}
    repo=${repo#docker.io/}

    # images are compared by digest: the name is spelled differently by docker and by IMAGE_REF
    local keep=("${current#*@}")
    if [[ -f $STATE/previous/.env ]]; then
        local previous
        previous=$(env_value IMAGE_REF "$STATE/previous/.env")
        keep+=("${previous#*@}")
    fi

    local listed_repo digest image_id
    while read -r listed_repo digest image_id; do
        [[ $listed_repo == "$repo" ]] || continue
        [[ $digest == '<none>' ]] && continue
        [[ " ${keep[*]} " == *" $digest "* ]] && continue

        if docker image rm "$image_id" > /dev/null 2>&1; then
            echo "removed $repo@$digest"
        else
            echo "kept $repo@$digest (in use)"
        fi
    done < <(docker image ls --all --digests --no-trunc --format '{{.Repository}} {{.Digest}} {{.ID}}')
}

verb_status() {
    [[ -f $STATE/.env ]] || die "$PROJECT has not been deployed yet"

    echo "image:   $(env_value IMAGE_REF "$STATE/.env")"
    echo "version: $(env_value APP_VERSION "$STATE/.env")"
    echo
    compose ps --all
    echo
    compose logs --no-color --tail 40 app 2>&1 || true
}

case $verb in
    put-compose | put-env | activate | pull | up-services | migrate | rollout | prune | status) ;;
    *) die "unknown verb: ${verb:-<none>}" ;;
esac

# One deploy at a time per app and environment. Status is read-only and never waits.
if [[ $verb != status ]]; then
    exec 9> "$STATE/.lock"
    flock --timeout 900 9 || die "another deploy of $PROJECT is running"
fi

"verb_${verb//-/_}"
