# Installation Guide - WooCommerce AutoResponder AI

This guide will walk you through the complete installation and setup process for the WooCommerce AutoResponder AI plugin.

## Prerequisites

Before installing the plugin, ensure your WordPress site meets the following requirements:

### System Requirements
- **WordPress**: Version 5.8 or higher
- **PHP**: Version 8.0 or higher
- **MySQL/MariaDB**: Compatible with WordPress
- **WooCommerce**: Version 5.0 or higher (must be installed and activated)

### AI Provider Requirements
You'll need API keys from at least one of the following providers:
- **OpenAI**: For GPT models
- **Google Gemini**: For Gemini models  
- **OpenRouter**: For various open-source models

## Installation Methods

### Method 1: Manual Installation (Recommended)

1. **Download the Plugin**
   - Download the plugin ZIP file
   - Extract the contents to your local computer

2. **Upload to WordPress**
   - Log in to your WordPress admin dashboard
   - Navigate to **Plugins > Add New**
   - Click **Upload Plugin**
   - Choose the plugin ZIP file and click **Install Now**
   - Click **Activate Plugin** when installation completes

### Method 2: FTP Installation

1. **Extract Plugin Files**
   - Extract the plugin ZIP file on your local computer
   - You should see a folder named `woocommerce-autoresponder-ai`

2. **Upload via FTP**
   - Connect to your website using an FTP client
   - Navigate to `/wp-content/plugins/`
   - Upload the `woocommerce-autoresponder-ai` folder
   - Ensure the folder structure is: `/wp-content/plugins/woocommerce-autoresponder-ai/`

3. **Activate the Plugin**
   - Log in to your WordPress admin dashboard
   - Go to **Plugins > Installed Plugins**
   - Find "WooCommerce AutoResponder AI" and click **Activate**

## Initial Setup

### Step 1: Verify Installation

After activation, you should see:
- A new menu item "AI Reviews" in your WordPress admin sidebar
- A success message confirming the plugin is active
- Database tables created automatically

### Step 2: Check System Compatibility

The plugin will automatically check your system requirements. If any issues are found:
- Update WordPress to version 5.8 or higher
- Update PHP to version 8.0 or higher
- Install and activate WooCommerce version 5.0 or higher

### Step 3: Configure AI Providers

1. **Navigate to Settings**
   - Go to **AI Reviews > Settings** in your WordPress admin
   - Scroll to the "API Keys" section

2. **Set Up OpenAI (Optional)**
   - Visit [OpenAI Platform](https://platform.openai.com/api-keys)
   - Create a new API key
   - Copy the key and paste it in the "OpenAI API Key" field

3. **Set Up Google Gemini (Optional)**
   - Visit [Google AI Studio](https://makersuite.google.com/app/apikey)
   - Create a new API key
   - Copy the key and paste it in the "Google Gemini API Key" field

4. **Set Up OpenRouter (Optional)**
   - Visit [OpenRouter](https://openrouter.ai/keys)
   - Create a new API key
   - Copy the key and paste it in the "OpenRouter API Key" field

### Step 4: Configure Basic Settings

1. **Enable Automation**
   - Check "Enable Automation" to start automatic response generation
   - Choose your preferred "Workflow Mode":
     - **Auto-publish**: Responses are published immediately
     - **Semi-automatic**: Responses require approval before publishing
     - **Draft only**: Responses are saved as drafts

2. **Set Response Tone**
   - Choose from: Professional, Friendly, Casual, Technical, or Promotional
   - This affects the style of all AI-generated responses

3. **Select Primary AI Provider**
   - Choose your preferred provider from the dropdown
   - Set fallback providers in case the primary provider fails

### Step 5: Test the Setup

1. **Test AI Provider Connection**
   - In the Settings page, scroll to "Test AI Providers"
   - Select a provider and click "Test Connection"
   - You should see a success message if the connection works

2. **Test Response Generation**
   - Go to **WooCommerce > Reviews**
   - Find a product review
   - Click "Generate AI Response" to test the system

## Advanced Configuration

### Product Data Fields

Configure which product information to include in AI prompts:
- Product Title
- Product Description
- Short Description
- Product Attributes
- SKU
- Price
- Categories
- Tags

### Review Filters

Set up filters to control which reviews trigger AI responses:
- **Rating Range**: Set minimum and maximum rating thresholds
- **Exclude Spam**: Automatically filter out spam reviews
- **Exclude Negative Only**: Skip reviews that are purely negative
- **Exclude Questions**: Skip reviews that are actually questions

### Privacy Settings

Configure data handling and privacy:
- **Allow External Data Sharing**: Enable/disable sending data to AI providers
- **Anonymize Customer Data**: Remove personal information from AI prompts
- **Data Retention**: Set how long to keep logs and response history

### Notification Settings

Set up email notifications:
- **Email Notifications**: Enable/disable email alerts
- **Notification Email**: Set the email address for notifications
- **Error Notifications**: Get notified when errors occur
- **High Volume Alerts**: Get notified when response volume is high

## Troubleshooting Installation Issues

### Common Problems

1. **Plugin Won't Activate**
   - Check WordPress and PHP version requirements
   - Ensure WooCommerce is installed and activated
   - Check for plugin conflicts by deactivating other plugins temporarily

2. **Database Tables Not Created**
   - Ensure your database user has CREATE TABLE permissions
   - Check WordPress database connection settings
   - Try deactivating and reactivating the plugin

3. **API Keys Not Working**
   - Verify the API key is correct and active
   - Check if the API provider has rate limits
   - Ensure external data sharing is enabled in privacy settings

4. **Responses Not Generating**
   - Check the Activity Logs for error messages
   - Verify at least one AI provider is properly configured
   - Test the AI provider connection in settings

### Getting Help

If you encounter issues during installation:

1. **Check Error Logs**
   - Enable WordPress debug mode
   - Check the Activity Logs in the plugin dashboard
   - Look for specific error messages

2. **Test System Requirements**
   - Use the plugin's built-in requirement checker
   - Update WordPress, PHP, or WooCommerce if needed

3. **Contact Support**
   - Provide specific error messages
   - Include your WordPress and PHP versions
   - Describe the exact steps that led to the issue

## Post-Installation Checklist

After successful installation, verify the following:

- [ ] Plugin is activated without errors
- [ ] Database tables are created
- [ ] At least one AI provider is configured and tested
- [ ] Basic settings are configured (automation, tone, workflow)
- [ ] Test response generation works
- [ ] Admin dashboard is accessible
- [ ] WooCommerce integration is working
- [ ] Privacy settings are configured appropriately

## Next Steps

Once installation is complete:

1. **Review the User Guide** for detailed usage instructions
2. **Configure Advanced Settings** based on your specific needs
3. **Test with Sample Reviews** to ensure everything works correctly
4. **Set Up Monitoring** to track AI response performance
5. **Train Your Team** on how to manage AI responses

## Uninstallation

To completely remove the plugin:

1. **Deactivate the Plugin**
   - Go to **Plugins > Installed Plugins**
   - Click "Deactivate" for WooCommerce AutoResponder AI

2. **Remove Plugin Files**
   - Delete the plugin folder from `/wp-content/plugins/woocommerce-autoresponder-ai/`

3. **Clean Up Database (Optional)**
   - The plugin will ask if you want to remove database tables
   - Choose "Yes" to completely remove all plugin data
   - Choose "No" to keep data for potential reinstallation

**Note**: Uninstalling will permanently delete all AI responses, logs, and settings. Make sure to export any important data before uninstalling.
