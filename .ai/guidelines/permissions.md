# Permissions

- Policies gate on **permissions** (`can('Ability:Model')`), not roles.
- Always reference roles via the `App\Enums\Role` enum, never raw strings.
