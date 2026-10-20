---
paths:
    - app/Models/**
---

# Models

- Apply the `App\Models\Concerns\LogsAllDirtyChanges` trait on every model.
- Use the `Illuminate\Database\Eloquent\Concerns\HasUuids` trait — all Models use UUID as primary key.
- Give every model a factory and the `Illuminate\Database\Eloquent\Factories\HasFactory` trait with the respective generic annotation.
- Carry a PHPDoc block on every model class declaring its attributes: `@property` for each database column (typed to the cast, nullable where the column is) and `@property-read` for each accessor and relation property. Keep the block in sync when a migration or accessor changes.
- Cast every attribute according to its migration's column type.
- Cast any enum-backed column (typed as string/int/... but only populated with backed enum cases) as that enum.
- Cast dates as immutable.
- Declare an explicit **`$fillable` allowlist** on every model via the `#[Fillable([...])]` attribute.
