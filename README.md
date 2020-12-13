# pinger
Making sure that a web is online

## What for?
This is a script that requests an URL and checks if it's alive, by validating the HTTP status code and if the body returned contains any expected string.

## How to set it up
To run this script you need the following steps:

1. Clone the project in any server with PHP support.
1. Create a `config.json` file and place there the configurations that fit on you.

```json
{
  "last-update": "2019-03-19 13:47:00",
  "output_path": "pinger_results_%s",
  "filename_template": "pinged_at_%s_%s.json",
  "services": [
    {
      "id": "i-am-a-string-id",
      "name": "I am the name of the service",
      "url": "http://i.am/the/url?to=ping",
      "validate": {
        "http-status": 200,
        "dom-contains": "<h2>I am part of the DOM</h2>"
      }
    }
  ]
}
```

1. Be sure the user that executes the script has write access to the filesystem, as the scripts writes and reads files.
1. Set up a CronJob over `index.php` in a periodicity that fits on you.

## How to read the results
The output files are placed in a directory per day, and contain the results of all checks done for all URLs given per service.
The concept is to deliver these files to any gateway so that YOU create a panel/dashboard with this info.
An example of a results file could be:

```json
{
    "version": 1,
    "date": "2019-03-20 09:03:56",
    "service_results": [
        {
            "service_id": "devnamic-test",
            "service_name": "Devnamic test",
            "request": {
                "url": "http:\/\/www.devnamic.com\/whatever.html",
                "status_code": 200,
                "header": "HTTP\/1.1 200 OK\r\nServer: nginx\r\nDate: Wed, 20 Mar 2019 09:03:56 GMT\r\nContent-Type: text\/html\r\nContent-Length: 6403\r\nConnection: keep-alive\r\nVary: Accept-Encoding\r\nLast-Modified: Tue, 12 Mar 2019 14:14:52 GMT\r\nETag: \"1903-583e6500e8c47\"\r\nAccept-Ranges: bytes\r\n\r\n",
                "body": "<!DOCTYPE html>\n    <html>\n    <head>\n      <meta charset='utf-8'>\n      <meta name='viewport' content='width=device-width'>\n      <title>Devnamic<\/title>\n      <style> body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; padding:1em; } <\/style>\n    <\/head>\n    <body>\n    <h2>Devnamic<\/h2> <p>Devnamic is great<\/p>\n    <\/body>\n    <\/html>\n      \n",
                "duration": 0.28943896293640137
            },
            "error": false,
            "validation_results": [
                {
                    "validation_class": "StatusValidator",
                    "valid_value": 200,
                    "is_valid": true
                },
                {
                    "validation_class": "ContentValidator",
                    "valid_value": "<h2>Devnamic<\/h2>",
                    "is_valid": true
                }
            ],
            "duration": 0.29410696029663086
        },
        {
            "service_id": "devnamic-test-2",
            "service_name": "Devnamic test 2",
            "request": {
                "url": "http:\/\/www.devnamic.com\/whatever2.html",
                "status_code": 200,
                "header": "HTTP\/1.1 200 OK\r\nServer: nginx\r\nDate: Wed, 20 Mar 2019 09:03:56 GMT\r\nContent-Type: text\/html\r\nContent-Length: 6403\r\nConnection: keep-alive\r\nVary: Accept-Encoding\r\nLast-Modified: Tue, 12 Mar 2019 14:14:52 GMT\r\nETag: \"1903-583e6500e8c47\"\r\nAccept-Ranges: bytes\r\n\r\n",
                "body": "<!DOCTYPE html>\n    <html>\n    <head>\n      <meta charset='utf-8'>\n      <meta name='viewport' content='width=device-width'>\n      <title>Devnamic<\/title>\n      <style> body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; padding:1em; } <\/style>\n    <\/head>\n    <body>\n    <h2>Devnamic<\/h2> <p>Devnamic is great<\/p>\n    <\/body>\n    <\/html>\n      \n",
                "duration": 0.2879199981689453
            },
            "error": false,
            "validation_results": [
                {
                    "validation_class": "StatusValidator",
                    "valid_value": 200,
                    "is_valid": true
                },
                {
                    "validation_class": "ContentValidator",
                    "valid_value": "<h3>Xavier Arnaus<\/h3>",
                    "is_valid": false
                }
            ],
            "duration": 0.28998684883117676
        }
    ],
    "duration": 0.9967279434204102
}
```

## The Dashboard

As mentioned above, you can access today's reports by readong the resulting JSON files.
To make everything a bit more easy and packed, there is a `public_html` directory with a dashboard
that builds a simple report.

The intended combination is that:
* The `crontab` runs the root's `index.php` periodically.
* A webserver points a vhost to `public_html/index.php` to access to the last report.

### TODO
* Use the `latest` generated report instead the logic to find what is the latest.
* Show any way to relate to previous reports of the day
* Improve the documentation:
    * Update the `config.json` options
    * Explain the `maintenance` actions. Zips!!
    * Explain the `templates`
    * Explain the `htaccess` security level
    * Explain the `Makefile`
* Improve the `Makefile` by scripting the add of new resources

