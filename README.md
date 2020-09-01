# Amazon Simple Email Service (SES)

Adds basic support for Amazon's Simple Email Service to the FuelPHP 1.1 Email Driver. (Support AWS Signature V4).
If you are using AWS SDK for PHP, you don't have to do anything.

# Install

In the repository's root directory, run `git clone git://github.com/GodStorm91/fuel-amazon-ses.git fuel/packages/amazon-ses`.
This packages support AWS Signature V4 ( with will be effective from 2020 October).
If you are using V3 Signature ( can detect by the line below), please consider upgrade your package.

# インストール方法：

* projectをクローンする
* クローンしたフォルダーから、`classes/email/driver/ses.php` を`fuel/packages/email/classes/driver/ses.php` にコピーする
* `fuel/packages/email/bootstrap.php`を下記の行を追加する

```php
'Email\\Email_Driver_Ses'                  => __DIR__.'/classes/email/driver/ses.php',
```
* `fuel/app/config/ses.php`ファイルを作って、シークリートキー、アクセスキー、リージョンを設定する

```php
return array(
	
		'access_key' => '<access_key>',
 		'secret_key' => '<secret_key>',
        'region' => '<region>'

);
```

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


## Licence            
This package is [Treeware](https://treeware.earth). If you use it in production, then we ask that you [**buy the world a tree**](https://plant.treeware.earth/GodStorm91/fuel-amazon-ses) to thank us for our work. By contributing to the Treeware forest you’ll be creating employment for local families and restoring wildlife habitats.

