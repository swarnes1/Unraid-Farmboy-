# Discord Webhook Setup Guide

## Step 1: Configure Discord Webhook URL

Edit the dashboard file and add your Discord webhook URL:

\`\`\`php
$discord_webhook_url = 'https://discord.com/api/webhooks/YOUR_WEBHOOK_ID/YOUR_WEBHOOK_TOKEN';
\`\`\`

## Step 2: Discord Webhook Setup

1. Go to your Discord server
2. Right-click on the channel where you want bot updates
3. Select "Edit Channel" → "Integrations" → "Webhooks"
4. Click "New Webhook"
5. Copy the webhook URL
6. Paste it into the dashboard configuration

## Step 3: Bot Integration Options

### Option A: Direct Webhook Calls (Recommended)
Configure your bots to send data directly to this dashboard:

\`\`\`
POST http://YOUR_UNRAID_IP/eternalfarm-dashboard.php
Content-Type: application/json

{
  "embeds": [{
    "title": "Bot Status Update",
    "fields": [
      {"name": "Account: YourBot1", "value": "Gained 50,000 GP\n2,500 XP\n150 items\n2h 30m runtime"},
      {"name": "Account: YourBot2", "value": "Gained 75,000 GP\n3,200 XP\n200 items\n3h 15m runtime"}
    ]
  }]
}
\`\`\`

### Option B: Parse Existing Discord Messages
The dashboard automatically parses common bot message formats:

- "Bot: Account123 gained 50000 GP"
- "Account: TestBot collected 150 items"
- Embed fields with account stats

## Step 4: Test the Integration

1. Click "Test Webhook" button in dashboard
2. Check your Discord channel for test message
3. Send a test bot update to verify parsing

## Step 5: Message Formats Supported

The dashboard recognizes these patterns:

### Simple Messages:
\`\`\`
Bot: MyAccount gained 50,000 GP
Account: TestBot collected 150 items
\`\`\`

### Embed Fields:
\`\`\`json
{
  "embeds": [{
    "fields": [
      {
        "name": "Account: BotName",
        "value": "GP: 50,000\nXP: 2,500\nItems: 150\nRuntime: 2h 30m"
      }
    ]
  }]
}
\`\`\`

## Troubleshooting

- Check webhook URL is correct
- Ensure dashboard has write permissions to /tmp/
- Verify Discord channel permissions
- Check Unraid firewall settings
