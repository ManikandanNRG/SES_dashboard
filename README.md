# SES Email Dashboard

A comprehensive Moodle plugin for tracking Amazon SES email performance with real-time analytics and reporting.

## 🚀 Features

- **📊 Interactive Dashboard**: Real-time email statistics with Chart.js visualizations
- **📈 Email Analytics**: Track Send, Delivery, Open, Click, and Bounce rates
- **🔍 Advanced Filtering**: Filter by status, search by email/subject/message ID
- **📋 Detailed Reports**: Paginated email reports with comprehensive metadata
- **💾 CSV Export**: Export filtered data for external analysis
- **🗑️ Auto Cleanup**: Automatic 7-day data retention for performance
- **📱 Responsive Design**: Works seamlessly on desktop and mobile devices
- **🎨 Modern UI**: Bootstrap-styled interface with colored status badges

## 📋 Requirements

- **Moodle**: 4.2 or higher
- **PHP**: 7.4 or higher
- **Database**: MySQL 5.7+ or PostgreSQL 9.6+
- **Amazon SES**: Configured email service
- **Web Server**: Apache or Nginx

## 🛠️ Installation

1. **Download the plugin:**
   ```bash
   git clone https://github.com/yourusername/ses-dashboard.git
   ```

2. **Place in Moodle:**
   ```bash
   cp -r ses-dashboard/local/sesdashboard /path/to/moodle/local/
   ```

3. **Install via Moodle Admin:**
   - Go to `Site Administration → Notifications`
   - Follow the installation prompts

4. **Configure Settings:**
   - Navigate to `Site Administration → Plugins → Local plugins → SES Dashboard`
   - Configure your Amazon SES settings

## 📊 Usage

### Dashboard
Access the main dashboard at: `/local/sesdashboard/pages/index.php`

- View email statistics for the last 3, 5, or 7 days
- Interactive charts showing daily email activity
- Status distribution pie chart

### Reports
Access detailed reports at: `/local/sesdashboard/pages/report.php`

- Filter emails by status (Send, Delivery, Open, Click, Bounce)
- Search by email address, subject, or message ID
- Export filtered results to CSV
- View detailed email metadata

## 🎯 Email Status Types

| Status | Icon | Description |
|--------|------|-------------|
| Send | 📤 | Email sent to SES |
| Delivery | ✅ | Successfully delivered |
| Open | 👁️ | Email opened by recipient |
| Click | 👆 | Link clicked in email |
| Bounce | ❌ | Delivery failed |

## 🔧 Configuration

### Amazon SES Setup
1. Configure SES in your AWS account
2. Set up SNS topics for email events
3. Configure webhook endpoints in Moodle

### Data Retention
- Emails are automatically cleaned up after 7 days
- Configurable via scheduled tasks
- Optimizes database performance

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature-name`
3. Make your changes and test thoroughly
4. Commit: `git commit -m "feat: add new feature"`
5. Push: `git push origin feature-name`
6. Create a Pull Request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

- **Documentation**: [Wiki](https://github.com/yourusername/ses-dashboard/wiki)
- **Issues**: [GitHub Issues](https://github.com/yourusername/ses-dashboard/issues)
- **Email**: nrgmanikandan91@gmail.com

## 🏆 Version History

- **v1.2.0** - Complete dashboard with filtering and export
- **v1.1.0** - Added detailed email reports
- **v1.0.0** - Initial release with basic tracking

---

**Made with ❤️ for better email analytics** 