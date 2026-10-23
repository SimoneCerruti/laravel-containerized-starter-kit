## Slack

1. Create a Slack app, add the bot scope `chat:write` (and `chat:write.public` for public channels without invite) and install it in the workspace.
2. Set `SLACK_BOT_USER_OAUTH_TOKEN` (the `xoxb-` token) and `SLACK_BOT_USER_DEFAULT_CHANNEL`.
3. For the Slack log channel create an incoming webhook and set `LOG_SLACK_WEBHOOK_URL`, then add `slack` to `LOG_STACK`.
