# SES Dashboard - Quick Setup Guide

## 🚀 Quick Installation (15 minutes)

### 1. AWS Setup (5 minutes)
```bash
# 1. Create SES domain verification
# 2. Create SNS topic: "ses-email-events"  
# 3. Create SNS subscription: HTTPS → https://yourmoodle.com/local/sesdashboard/pages/webhook.php
# 4. Create SES configuration set: "moodle-email-tracking"
# 5. Create IAM user with SES permissions
```

### 2. Plugin Installation (3 minutes)
```bash
# Upload plugin to /local/sesdashboard/
# Visit: Site administration → Notifications
# Click: "Upgrade Moodle database now"
```

### 3. Configuration (5 minutes)
```bash
# Go to: Site administration → Plugins → Local plugins → SES Dashboard
# Set: Sender Email = noreply@yourdomain.com
# Set: Data Retention = 14 days  
# Set: SNS Topic ARN = arn:aws:sns:region:account:ses-email-events

# Configure Moodle SMTP:
# SMTP hosts: email-smtp.region.amazonaws.com:587
# SMTP security: TLS
# SMTP username: [SES SMTP username]
# SMTP password: [SES SMTP password]
```

### 4. Test (2 minutes)
```bash
# Send test email: Site administration → Server → Email → Test outgoing mail
# Check dashboard: /local/sesdashboard/pages/index.php
# Verify webhook: Check SNS subscription is "Confirmed"
```

## 🔧 Essential URLs
- **Dashboard**: `/local/sesdashboard/pages/index.php`
- **Reports**: `/local/sesdashboard/pages/report.php`
- **Debug Tool**: `/local/sesdashboard/comprehensive_debug.php`
- **Webhook**: `/local/sesdashboard/pages/webhook.php`

## ⚡ Common Issues & Quick Fixes

| Issue | Quick Fix |
|-------|-----------|
| No data showing | Check SMTP configuration uses SES |
| Webhook not working | Verify SNS subscription is confirmed |
| Permission denied | Assign `local/sesdashboard:view` capability |
| Charts not loading | Check Chart.js is loading in browser |

## 📋 AWS Configuration Checklist
- [ ] Domain verified in SES
- [ ] DKIM enabled
- [ ] SNS topic created
- [ ] SNS subscription confirmed
- [ ] Configuration set created
- [ ] IAM user with permissions
- [ ] SMTP credentials generated

## 🎯 Moodle Configuration Checklist  
- [ ] Plugin installed and enabled
- [ ] Database tables created
- [ ] Settings configured
- [ ] SMTP using SES
- [ ] User permissions assigned
- [ ] Test email sent successfully

---
For detailed instructions, see [INSTALLATION_GUIDE.md](INSTALLATION_GUIDE.md) 