# Amazon Simple Email Service (SES)

Adds basic support for Amazon's Simple Email Service to the FuelPHP 1.1 Email Driver.

# Install

In the repository's root directory, run "git clone git://github.com/GodStorm91/fuel-amazon-ses.git fuel/packages/amazon-ses" (without quotes).
This packages support AWS Signature V4 ( with will be effective from 2020 October).
If you are using V3 Signature ( can detect by the line below), please consider upgrade your package.

```php
$curl->set_header('X-Amzn-Authorization','AWS3-HTTPS AWSAccessKeyId='.\Config::get('ses.access_key').', Algorithm=HmacSHA256, Signature=' . $signature)
```

# Usage

I just changed the signing algorithm, so the usage should stay the same with the original package.

```php
Email::forge(array('driver' => 'ses'))
	->to('to@yoursite.com')
	->from('from@yoursite.com')
	->subject('testing123')
	->body('Your message goes here.')
	->send();
```

