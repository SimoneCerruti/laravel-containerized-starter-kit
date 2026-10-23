---
paths:
    - tests/App/Policies/**
    - tests/App/Models/**
---

# Testing roles and permissions

- Build the user with the factory role states, never by attaching permissions by hand in each test.
- For every policy method, test one user who holds the permission and one who does not.
- Reset the permission cache (`app(PermissionRegistrar::class)->forgetCachedPermissions()`) in `beforeEach` when permissions are created inside the test.
