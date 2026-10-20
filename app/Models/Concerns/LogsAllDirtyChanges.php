<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Collection;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property-read Collection<int, Activity> $activitiesAsSubject
 */
trait LogsAllDirtyChanges {
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions {
        $options = LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();

        if ($prop = $this->getLoggableAttributesPropertyName()) {
            $loggable_attributes = $this->{$prop};

            throw_unless(\is_array($loggable_attributes), \RuntimeException::class, "The {$prop} property must be an array of strings.");

            return $options->logOnly($loggable_attributes);
        }

        if (method_exists($this, 'getLoggableAttributes')) {
            $loggable_attributes = $this->getLoggableAttributes();

            throw_unless(\is_array($loggable_attributes), \RuntimeException::class, 'The getLoggableAttributes method must return an array of strings.');

            return $options->logOnly($loggable_attributes);
        }

        if ($prop = $this->getNonLoggableAttributesPropertyName()) {
            $non_loggable_attributes = $this->{$prop};

            throw_unless(\is_array($non_loggable_attributes), \RuntimeException::class, "The {$prop} property must be an array of strings.");

            return $options->logExcept($non_loggable_attributes);
        }

        if (method_exists($this, 'getNonLoggableAttributes')) {
            $non_loggable_attributes = $this->getNonLoggableAttributes();

            throw_unless(\is_array($non_loggable_attributes), \RuntimeException::class, 'The getNonLoggableAttributes method must return an array of strings.');

            return $options->logExcept($non_loggable_attributes);
        }

        return $options;
    }

    private function getLoggableAttributesPropertyName(): ?string {
        if (property_exists($this, 'loggable_attributes')) {
            return 'loggable_attributes';
        }

        if (property_exists($this, 'loggableAttributes')) {
            return 'loggableAttributes';
        }

        return null;
    }

    private function getNonLoggableAttributesPropertyName(): ?string {
        if (property_exists($this, 'non_loggable_attributes')) {
            return 'non_loggable_attributes';
        }

        if (property_exists($this, 'nonLoggableAttributes')) {
            return 'nonLoggableAttributes';
        }

        return null;
    }
}
