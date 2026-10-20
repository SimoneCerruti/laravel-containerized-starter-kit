/**
 * Authenticates a user holding the given role, built with the factory role state.
 */
function actingAsRole(Role $role): User
{
    $user = User::factory()->withRole($role)->create();

    actingAs($user);

    return $user;
}

function actingAsUser(): User
{
    return actingAsRole(Role::USER);
}

function actingAsAdmin(): User
{
    return actingAsRole(Role::ADMIN);
}

function actingAsSuperAdmin(): User
{
    return actingAsRole(Role::SUPER_ADMIN);
}
