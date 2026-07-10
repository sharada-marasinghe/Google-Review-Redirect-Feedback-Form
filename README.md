# Google Review Redirect Feedback Form

**Google Review Redirect Feedback Form** is a lightweight, powerful WordPress plugin that allows website owners to build fully customizable feedback forms, save all responses to their WordPress database, and conditionally redirect happy customers to a Google Review page based on their rating. 

This plugin is designed to help businesses collect private feedback and boost their public rating on Google Reviews.

---

## Key Features

*   **Custom Question Builder**: Add, delete, and order fields (Short Text, Paragraph/Textarea, Yes/No, and Star Ratings).
*   **Conditional Google Review Redirect**:
    *   Redirect users only if they submit a positive rating (e.g., 4 or 5 stars).
    *   Show a customizable "Thank you" message to users who leave lower ratings, keeping their constructive feedback private.
    *   Optionally set it to always redirect all submissions.
*   **AJAX-Powered Submissions**: Smooth submissions without annoying page reloads or loss of entered data.
*   **Built-in Spam Protection**: Transparent honeypot field checks for bots without disrupting real users with tedious CAPTCHAs.
*   **Admin Dashboard**:
    *   **Questions Manager**: Manage and order fields easily.
    *   **Responses Viewer**: View up to 200 of the latest feedback submissions directly in the WordPress dashboard.
    *   **CSV Export**: Export all feedback history to a clean, UTF-8 encoded CSV file (compatible with Excel and other spreadsheet editors).
*   **Clean Styling**: Minimalist, neutral, and responsive front-end design that blends seamlessly into any theme.

---

## High Traffic & Performance Optimization

This plugin is specifically optimized to handle sudden traffic spikes and high concurrency (e.g., during live events, student surveys, or launch campaigns) with the following architectural choices:

*   **Conditional Asset Loading**: Stylesheets and JavaScript assets are *only* enqueued on pages/posts containing the `[sff_form]` shortcode. This keeps other site pages lightweight and free of unnecessary code.
*   **Custom Database Tables**: Storing responses in a dedicated custom table (`wp_sff_responses`) rather than WP custom post types (`wp_posts` and `wp_postmeta`) avoids slow, nested joins and database locking under high-concurrency write operations.
*   **Asynchronous AJAX Handling**: Submissions utilize the modern Fetch API, keeping requests fast, responsive, and light on PHP worker processes.
*   **Soft Nonce Validation**: Nonce validation is handled gracefully to ensure cached pages or expired nonces during high-traffic windows do not block real submissions or result in critical crashes.
*   **Fail-Safe Graceful Degradation**: If the database writes fail under extreme DB load spikes, the frontend is built to fail silently and still guide the customer to their redirect URL or thank-you message instead of presenting a jarring system error.
*   **Lightweight Honeypot Spam Protection**: The honeypot field filters out spam bots locally, avoiding expensive, slow third-party API calls (like Google reCAPTCHA) that add latency and degrade server response times during peak traffic.

---

## Installation Guide

1.  **Download the Plugin**: Obtain the plugin folder or ZIP archive.
2.  **Upload to WordPress**:
    *   **Option A**: Upload the `simple-feedback-form` folder directly to the `/wp-content/plugins/` directory on your server.
    *   **Option B**: Go to **WordPress Admin > Plugins > Add New > Upload Plugin** and select the plugin's ZIP archive.
3.  **Activate the Plugin**: Go to your **Plugins** page and click **Activate**. The database tables (`wp_sff_questions` and `wp_sff_responses`) will be created automatically.

---

## How to Use & Configure

### Step 1: Add Your Questions
1. Go to **Feedback Form > Questions** in your WordPress dashboard.
2. Add a new question:
   * Enter the **Question text**.
   * Choose an **Answer type** (Short text, Long text, Star rating, or Yes / No).
   * Check the **Required** box if the field is mandatory.
3. Click **Add Question**. You can manage and delete existing questions from the list.

### Step 2: Configure the Redirect & Thank-You Message
1. Go to **Feedback Form > Settings**.
2. **Google Review link**: Paste your Google Business Profile review link (e.g., `https://g.page/r/xxxxxxxx/review`). You can get this from your Google Business Profile under "Ask for reviews".
3. **Only redirect to Google if rating is at least**: Select the minimum star rating required to trigger the redirect (e.g. 4 stars or 5 stars). If set to *Always redirect*, every submission goes to Google regardless of rating.
4. **Thank-you message**: Edit the message that will be shown on-page to customers who are not redirected (those who left lower ratings).
5. Click **Save Settings**.

### Step 3: Embed the Form
Display the form on any page, post, or widget using the following shortcode:
```text
[sff_form]
```

### Step 4: View & Export Responses
1. Go to **Feedback Form > Responses** to view recent feedback submissions.
2. View the dates, overall ratings, and all question-answer pairs.
3. Click the **Export All to CSV** button to download a spreadsheet containing all submissions.

---

## Technical Requirements

*   **WordPress**: Version 5.0 or higher
*   **PHP**: Version 7.4 or higher
*   **Database**: MySQL 5.6+ or MariaDB 10.1+

---

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## Author

*   **sharada-marasinghe**
