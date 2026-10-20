/*
 * Email domains allowed to access the admin panel.
 *
 * @var list<string>
 */
'allowed_admin_email_domains' => array_filter(explode(',', (string) env('ALLOWED_ADMIN_EMAIL_DOMAINS', ''))),
