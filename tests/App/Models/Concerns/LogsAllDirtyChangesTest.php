<?php

declare(strict_types=1);

use App\Models\Concerns\LogsAllDirtyChanges;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function (): void {
    Schema::create('loggable_test_models', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('secret')->nullable();
        $table->timestamps();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('loggable_test_models');
});

describe('loggable attributes resolution', function (): void {
    describe('snake_case loggable_attributes property', function (): void {
        test('logs only the declared attributes, on create and on update', function (): void {
            $model = new class extends Model {
                use HasUuids, LogsAllDirtyChanges;

                protected $table = 'loggable_test_models';

                /** @var list<string> */
                protected $fillable = ['name', 'email', 'secret'];

                /** @var list<string> */
                private array $loggable_attributes = ['name'];
            };

            $model = $model->newQuery()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'secret' => 'shh']);

            assertDatabaseHas('loggable_test_models', ['name' => 'Ada', 'email' => 'ada@example.com', 'secret' => 'shh']);
            assertCountActivityLogsFor($model, 1);

            $created = getLastActivityLogsFor($model, 'created');
            assertActivityLogHasChanges($created);
            expect($created?->attribute_changes?->get('attributes'))->toBe(['name' => 'Ada']);

            $model->update(['name' => 'Grace', 'email' => 'grace@example.com', 'secret' => 'shh-shh']);

            assertCountActivityLogsFor($model, 2);

            $updated = getLastActivityLogsFor($model, 'updated');
            assertActivityLogHasChanges($updated);
            expect($updated?->attribute_changes?->get('attributes'))->toBe(['name' => 'Grace']);
        });

        test('throws when the property is not an array', function (): void {
            $model = new class extends Model {
                use HasUuids, LogsAllDirtyChanges;

                protected $table = 'loggable_test_models';

                private string $loggable_attributes = 'name';
            };

            expect(fn () => $model->getActivitylogOptions())
                ->toThrow(RuntimeException::class, 'The loggable_attributes property must be an array of strings.');
        });
    });

    describe('camelCase loggableAttributes property', function (): void {
        test('logs only the declared attributes, on create and on update', function (): void {
            $model = new class extends Model {
                use HasUuids, LogsAllDirtyChanges;

                protected $table = 'loggable_test_models';

                /** @var list<string> */
                protected $fillable = ['name', 'email', 'secret'];

                /** @var list<string> */
                private array $loggableAttributes = ['email'];
            };

            $model = $model->newQuery()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'secret' => 'shh']);

            assertDatabaseHas('loggable_test_models', ['name' => 'Ada', 'email' => 'ada@example.com', 'secret' => 'shh']);
            assertCountActivityLogsFor($model, 1);

            $created = getLastActivityLogsFor($model, 'created');
            assertActivityLogHasChanges($created);
            expect($created?->attribute_changes?->get('attributes'))->toBe(['email' => 'ada@example.com']);

            $model->update(['name' => 'Grace', 'email' => 'grace@example.com']);

            assertCountActivityLogsFor($model, 2);

            $updated = getLastActivityLogsFor($model, 'updated');
            assertActivityLogHasChanges($updated);
            expect($updated?->attribute_changes?->get('attributes'))->toBe(['email' => 'grace@example.com']);
        });

        test('throws when the property is not an array', function (): void {
            $model = new class extends Model {
                use HasUuids, LogsAllDirtyChanges;

                protected $table = 'loggable_test_models';

                private string $loggableAttributes = 'email';
            };

            expect(fn () => $model->getActivitylogOptions())
                ->toThrow(RuntimeException::class, 'The loggableAttributes property must be an array of strings.');
        });
    });

    describe('getLoggableAttributes() method', function (): void {
        test('logs only what the method returns, on create and on update', function (): void {
            $model = new class extends Model {
                use HasUuids, LogsAllDirtyChanges;

                protected $table = 'loggable_test_models';

                /** @var list<string> */
                protected $fillable = ['name', 'email', 'secret'];

                /** @return list<string> */
                public function getLoggableAttributes(): array {
                    return ['secret'];
                }
            };

            $model = $model->newQuery()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'secret' => 'shh']);

            assertDatabaseHas('loggable_test_models', ['name' => 'Ada', 'email' => 'ada@example.com', 'secret' => 'shh']);
            assertCountActivityLogsFor($model, 1);

            $created = getLastActivityLogsFor($model, 'created');
            assertActivityLogHasChanges($created);
            expect($created?->attribute_changes?->get('attributes'))->toBe(['secret' => 'shh']);

            $model->update(['secret' => 'new-secret']);

            assertCountActivityLogsFor($model, 2);

            $updated = getLastActivityLogsFor($model, 'updated');
            assertActivityLogHasChanges($updated);
            expect($updated?->attribute_changes?->get('attributes'))->toBe(['secret' => 'new-secret']);
        });

        test('throws when the method does not return an array', function (): void {
            $model = new class extends Model {
                use HasUuids, LogsAllDirtyChanges;

                protected $table = 'loggable_test_models';

                public function getLoggableAttributes(): mixed {
                    return 'secret';
                }
            };

            expect(fn () => $model->getActivitylogOptions())
                ->toThrow(RuntimeException::class, 'The getLoggableAttributes method must return an array of strings.');
        });
    });

    describe('priority between the property and the method', function (): void {
        test('the snake_case property wins over getLoggableAttributes()', function (): void {
            $model = new class extends Model {
                use HasUuids, LogsAllDirtyChanges;

                protected $table = 'loggable_test_models';

                /** @var list<string> */
                private array $loggable_attributes = ['name'];

                /** @return list<string> */
                public function getLoggableAttributes(): array {
                    return ['email'];
                }
            };

            expect($model->getActivitylogOptions()->logAttributes)->toBe(['name']);
        });
    });
});

describe('non-loggable attributes resolution', function (): void {
    describe('snake_case non_loggable_attributes property', function (): void {
        test('logs everything except the declared attributes, on create and on update', function (): void {
            $model = new class extends Model {
                use HasUuids, LogsAllDirtyChanges;

                protected $table = 'loggable_test_models';

                /** @var list<string> */
                protected $fillable = ['name', 'email', 'secret'];

                /** @var list<string> */
                private array $non_loggable_attributes = ['secret'];
            };

            $model = $model->newQuery()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'secret' => 'shh']);

            assertDatabaseHas('loggable_test_models', ['name' => 'Ada', 'email' => 'ada@example.com', 'secret' => 'shh']);
            assertCountActivityLogsFor($model, 1);

            $created = getLastActivityLogsFor($model, 'created');
            assertActivityLogHasChanges($created);
            expect($created?->attribute_changes?->get('attributes'))
                ->toHaveKeys(['name', 'email'])
                ->not->toHaveKey('secret');

            $model->update(['secret' => 'new-secret']);

            assertCountActivityLogsFor($model, 1);
        });

        test('throws when the property is not an array', function (): void {
            $model = new class extends Model {
                use HasUuids, LogsAllDirtyChanges;

                protected $table = 'loggable_test_models';

                private string $non_loggable_attributes = 'secret';
            };

            expect(fn () => $model->getActivitylogOptions())
                ->toThrow(RuntimeException::class, 'The non_loggable_attributes property must be an array of strings.');
        });
    });

    describe('camelCase nonLoggableAttributes property', function (): void {
        test('logs everything except the declared attributes, on create and on update', function (): void {
            $model = new class extends Model {
                use HasUuids, LogsAllDirtyChanges;

                protected $table = 'loggable_test_models';

                /** @var list<string> */
                protected $fillable = ['name', 'email', 'secret'];

                /** @var list<string> */
                private array $nonLoggableAttributes = ['email'];
            };

            $model = $model->newQuery()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'secret' => 'shh']);

            assertDatabaseHas('loggable_test_models', ['name' => 'Ada', 'email' => 'ada@example.com', 'secret' => 'shh']);
            assertCountActivityLogsFor($model, 1);

            $created = getLastActivityLogsFor($model, 'created');
            assertActivityLogHasChanges($created);
            expect($created?->attribute_changes?->get('attributes'))
                ->toHaveKeys(['name', 'secret'])
                ->not->toHaveKey('email');
        });

        test('throws when the property is not an array', function (): void {
            $model = new class extends Model {
                use HasUuids, LogsAllDirtyChanges;

                protected $table = 'loggable_test_models';

                private string $nonLoggableAttributes = 'email';
            };

            expect(fn () => $model->getActivitylogOptions())
                ->toThrow(RuntimeException::class, 'The nonLoggableAttributes property must be an array of strings.');
        });
    });

    describe('getNonLoggableAttributes() method', function (): void {
        test('logs everything except what the method returns, on create and on update', function (): void {
            $model = new class extends Model {
                use HasUuids, LogsAllDirtyChanges;

                protected $table = 'loggable_test_models';

                /** @var list<string> */
                protected $fillable = ['name', 'email', 'secret'];

                /** @return list<string> */
                public function getNonLoggableAttributes(): array {
                    return ['name'];
                }
            };

            $model = $model->newQuery()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'secret' => 'shh']);

            assertDatabaseHas('loggable_test_models', ['name' => 'Ada', 'email' => 'ada@example.com', 'secret' => 'shh']);
            assertCountActivityLogsFor($model, 1);

            $created = getLastActivityLogsFor($model, 'created');
            assertActivityLogHasChanges($created);
            expect($created?->attribute_changes?->get('attributes'))
                ->toHaveKeys(['email', 'secret'])
                ->not->toHaveKey('name');
        });

        test('throws when the method does not return an array', function (): void {
            $model = new class extends Model {
                use HasUuids, LogsAllDirtyChanges;

                protected $table = 'loggable_test_models';

                public function getNonLoggableAttributes(): mixed {
                    return 'name';
                }
            };

            expect(fn () => $model->getActivitylogOptions())
                ->toThrow(RuntimeException::class, 'The getNonLoggableAttributes method must return an array of strings.');
        });
    });

    describe('priority between the loggable and non-loggable branches', function (): void {
        test('the loggable branch wins over the non-loggable one', function (): void {
            $model = new class extends Model {
                use HasUuids, LogsAllDirtyChanges;

                protected $table = 'loggable_test_models';

                /** @var list<string> */
                private array $loggable_attributes = ['name'];

                /** @var list<string> */
                private array $non_loggable_attributes = ['secret'];
            };

            $options = $model->getActivitylogOptions();

            expect($options->logAttributes)->toBe(['name'])
                ->and($options->logExceptAttributes)->toBeEmpty();
        });
    });
});

describe('default resolution', function (): void {
    test('logs every attribute when nothing is declared', function (): void {
        $model = new class extends Model {
            use HasUuids, LogsAllDirtyChanges;

            protected $table = 'loggable_test_models';

            /** @var list<string> */
            protected $fillable = ['name', 'email', 'secret'];
        };

        $model = $model->newQuery()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'secret' => 'shh']);

        $created = getLastActivityLogsFor($model, 'created');
        assertActivityLogHasChanges($created);
        expect($created?->attribute_changes?->get('attributes'))->toHaveKeys(['name', 'email', 'secret']);
    });

    test('always logs only dirty attributes and skips empty changes', function (): void {
        $model = new class extends Model {
            use HasUuids, LogsAllDirtyChanges;

            protected $table = 'loggable_test_models';

            /** @var list<string> */
            protected $fillable = ['name', 'email', 'secret'];
        };

        $model = $model->newQuery()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'secret' => 'shh']);

        assertCountActivityLogsFor($model, 1);

        $model->update(['name' => 'Ada']);

        assertCountActivityLogsFor($model, 1);
    });
});
