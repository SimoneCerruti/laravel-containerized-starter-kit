/** @return HasMany<SocialAccount, $this> */
public function socialAccounts(): HasMany {
    return $this->hasMany(SocialAccount::class);
}
