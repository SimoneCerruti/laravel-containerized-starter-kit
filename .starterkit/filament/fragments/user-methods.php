public function getFilamentName(): string {
    return $this->full_name;
}

/**
 * Every condition added by the installed modules (email domain, roles) must hold.
 */
public function canAccessPanel(Panel $panel): bool {
    return collect([
        true,
        // @kit:user-panel-access
    ])->every(fn (bool $allowed): bool => $allowed);
}
