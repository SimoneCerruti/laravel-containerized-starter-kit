## Social login

For each provider register an OAuth app and use as redirect URI `https://<APP_HOST>/auth/<provider>/callback` (and the local URL for development). Then fill the keys of the provider in the `.env`.
