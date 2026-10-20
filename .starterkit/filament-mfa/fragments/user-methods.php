public function getAppAuthenticationSecret(): ?string {
    return $this->app_authentication_secret;
}

public function saveAppAuthenticationSecret(?string $secret): void {
    $this->app_authentication_secret = $secret;
    $this->save();
}

public function getAppAuthenticationHolderName(): string {
    return $this->email;
}

/**
 * @return list<string>|null
 */
public function getAppAuthenticationRecoveryCodes(): ?array {
    return $this->app_authentication_recovery_codes;
}

/**
 * @param  list<string>|null  $codes
 */
public function saveAppAuthenticationRecoveryCodes(?array $codes): void {
    $this->app_authentication_recovery_codes = $codes;
    $this->save();
}

public function hasEmailAuthentication(): bool {
    return $this->has_email_authentication;
}

public function toggleEmailAuthentication(bool $condition): void {
    $this->has_email_authentication = $condition;
    $this->save();
}
