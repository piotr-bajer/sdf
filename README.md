Spark Donation Form
=======

This is a WordPress plugin written for [Spark SF](http://sparksf.org).
It handles payments with Stripe and saves user data to Salesforce.

The Stripe side is capable of being set up with any Stripe account. The plugin will set up the plans for subscriptions, and everything else is handled per request. You must set your secret and public keys in the plugin settings page at /wp-admin/ > Settings > Spark Donation Form

Your Stripe account also needs to have webhooks configured to point at `<your domain>/wp-content/plugins/sdf/webhook.php`. The only event currently handled is charge.succeeded but more could be handled in the future.

The plugin also requires that you set the Salesforce user information, and it depends on the existence of a custom Contact object, and several custom fields for that object to save the data from the form, so an out-of-the-box Salesforce account probably won't work.

Several emails are generated at donation time, using templates from Salesforce. These must also exist for the plugin to work. On the plugin's option page, there is a field to enter email addresses to notify.

The form markup is generated when the proper page slug is loaded. This is set to 'donate' as the default.

To deploy, set the `LIVEMODE` setting to 1 in the main file, sdf.php.

You should have an SSL certificate set up. The page automatically redirects to https:// when in `LIVEMODE`.


---


Copyright (c) 2014,2015 Steven Avery, Spark SF.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:
